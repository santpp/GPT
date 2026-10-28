const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const channel=value=>({TYPE_WHATSAPP:'WhatsApp',TYPE_INSTAGRAM:'Instagram',TYPE_SMS:'SMS',TYPE_EMAIL:'Email',TYPE_FACEBOOK:'Messenger',TYPE_CALL:'Llamada',TYPE_LIVE_CHAT:'Chat web',TYPE_INTERNAL_COMMENTS:'Nota interna'}[value]||'Conversación');
const name=c=>c.contactName||c.fullName||[c.firstName,c.lastName].filter(Boolean).join(' ')||'Contacto sin nombre';
const initials=c=>name(c).split(/\s+/).slice(0,2).map(s=>[...s][0]).join('').toUpperCase();
const date=value=>{if(!value)return '';const d=new Date(typeof value==='string'&&/^\d{4}-\d\d-\d\d \d\d:/.test(value)?value.replace(' ','T')+'Z':value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'short',timeStyle:'short'}).format(d)};
const status=value=>({delivered:'Entregado',sent:'Enviado',read:'Leído',pending:'Pendiente',failed:'Fallido',received:'Recibido'}[value]||'');
const normalize=s=>String(s??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
async function request(path,method='GET',csrf=''){
 const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
 let data;try{data=await r.json()}catch{throw Error('No se pudo conectar. Inténtalo de nuevo.')}
 if(!r.ok)throw Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:''));return data;
}
export async function renderLiveInbox(container){
 const instance=Symbol();container.agoraLiveInstance=instance;
 const active=()=>container.agoraLiveInstance===instance&&container.dataset.view==='live';
 container.innerHTML='<p class="empty">Cargando conversaciones…</p>';
 try{
  const me=await request('me');const {items}=await request('workspace/connections');if(!active())return;
  container.innerHTML=`<div class="live-toolbar"><label>Cuenta<select id="live-connection" aria-label="Cuenta">${items.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select></label><button id="sync-live" class="primary">Actualizar</button></div><p id="live-feedback" class="live-feedback" role="status"></p><div class="live-inbox-layout"><section class="live-sidebar" aria-label="Lista de conversaciones"><label class="search"><span aria-hidden="true">⌕</span><input id="live-search" type="search" placeholder="Buscar nombre o mensaje" aria-label="Buscar en conversaciones cargadas"></label><div class="tabs"><button data-live-filter="all" class="active" aria-pressed="true">Todas</button><button data-live-filter="unread" aria-pressed="false">Pendientes</button><span id="live-count"></span></div><p class="live-search-hint">Búsqueda en las conversaciones cargadas</p><div id="live-list"></div></section><section id="live-messages" aria-label="Conversación"><p class="empty">Selecciona una conversación para consultar su historial.</p></section></div>`;
  const $=s=>container.querySelector(s), select=$('#live-connection'),feedback=$('#live-feedback'),button=$('#sync-live'),layout=$('.live-inbox-layout');
  if(!items.length){feedback.textContent='Añade una cuenta desde Conexiones para empezar.';button.disabled=true;return}
  let generation=0, chatGeneration=0, conversations=[], selected=null, filter='all', messages=new Map(), cursor=null;
  function list(){
   const search=normalize($('#live-search').value);
   const visible=conversations.filter(c=>(filter!=='unread'||Number(c.unreadCount)>0)&&normalize(name(c)+' '+(c.lastMessageBody||'')).includes(search));
   $('#live-count').textContent=visible.length;
   $('#live-list').innerHTML=visible.map(c=>`<button class="conversation-item live-conversation ${selected?.id===c.id?'selected':''}" data-live-id="${esc(c.id)}" aria-label="Abrir conversación de ${esc(name(c))}" aria-pressed="${selected?.id===c.id}"><span class="avatar">${esc(initials(c))}</span><span class="conversation-info"><header><strong>${esc(name(c))}</strong></header><p>${esc(c.lastMessageBody||'Sin vista previa')}</p><footer><span class="channel">${channel(c.lastMessageType)}</span>${Number(c.unreadCount)>0?`<b class="unread">${Number(c.unreadCount)}<span class="sr-only"> pendientes</span></b>`:''}</footer><time>${esc(date(c.lastMessageDate))}</time></span></button>`).join('')||'<p class="empty">No hay conversaciones con este filtro.</p>';
   container.querySelectorAll('[data-live-id]').forEach(b=>b.onclick=()=>open(conversations.find(c=>c.id===b.dataset.liveId)));
  }
  function renderMessages(){
   $('#live-log').innerHTML=[...messages.values()].sort((a,b)=>(Date.parse(a.dateAdded)||0)-(Date.parse(b.dateAdded)||0)).map(m=>`<div class="bubble ${m.direction==='outbound'?'out':''}">${esc(m.body||'Mensaje sin texto')}<time>${esc(date(m.dateAdded))}${status(m.status)?' · '+status(m.status):''}</time></div>`).join('')||'<p class="empty">No hay mensajes disponibles.</p>';
  }
  async function page(older=false){
   const current=chatGeneration, connection=select.value, conversation=selected.id, previous=older?cursor:null;
   const more=$('#live-older'),retry=$('#live-retry');more.disabled=true;retry.hidden=true;$('#live-chat-status').textContent=older?'Cargando mensajes anteriores…':'Cargando mensajes…';
   try{
    const r=await request(`workspace/messages?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(conversation)}${previous?'&lastMessageId='+encodeURIComponent(previous):''}`);
    if(!active()||current!==chatGeneration)return;
    const log=$('#live-log'),height=log.scrollHeight,top=log.scrollTop;
    for(const m of r.items)messages.set(m.id,m);
    cursor=r.hasMore&&r.lastMessageId&&r.lastMessageId!==previous?r.lastMessageId:null;
    renderMessages();more.hidden=!cursor;$('#live-chat-status').textContent=r.hasMore&&!cursor?'No se recibió una referencia válida para continuar el historial.':'';
    log.scrollTop=older?top+log.scrollHeight-height:log.scrollHeight;
   }catch(e){if(active()&&current===chatGeneration){$('#live-chat-status').textContent=e.message;retry.hidden=false;retry.onclick=()=>page(older)}}
   finally{if(active()&&current===chatGeneration)more.disabled=false}
  }
  function open(c){
   selected=c;chatGeneration++;messages=new Map();cursor=null;list();layout.classList.add('chat-open');
   $('#live-messages').innerHTML=`<header class="live-chat-header"><button id="live-back" aria-label="Volver a conversaciones">←</button><span class="avatar">${esc(initials(c))}</span><div><h2>${esc(name(c))}</h2><p>${channel(c.lastMessageType)}</p></div><button id="live-refresh" aria-label="Actualizar mensajes">↻</button></header><div class="live-history-actions"><button id="live-older" hidden>Cargar mensajes anteriores</button><p id="live-chat-status" role="status"></p><button id="live-retry" hidden>Reintentar</button></div><div id="live-log" class="messages live-message-log" aria-label="Historial de mensajes"></div><p class="live-readonly">Consulta de historial · Las respuestas y el cambio de estado de lectura estarán disponibles en una próxima actualización.</p>`;
   $('#live-back').onclick=()=>{layout.classList.remove('chat-open');$('#live-search').focus()};
   $('#live-older').onclick=()=>page(true);$('#live-refresh').onclick=()=>open(selected);page();
  }
  async function load(){
   const version=++generation;chatGeneration++;selected=null;conversations=[];list();layout.classList.remove('chat-open');$('#live-messages').innerHTML='<p class="empty">Selecciona una conversación para consultar su historial.</p>';
   feedback.textContent='Cargando conversaciones…';
   try{
    const data=await request(`workspace/inbox?connectionId=${encodeURIComponent(select.value)}`);
    if(version!==generation||!active())return;
    conversations=data.items;list();
    feedback.textContent=data.syncedAt?`Mostrando ${data.items.length} de ${data.total} conversaciones · Actualizado ${date(data.syncedAt)}${data.partial?' · Vista parcial':''}`:'Todavía no hay conversaciones cargadas. Comprueba la conexión y pulsa Actualizar.';
    if(data.state?.last_error)feedback.textContent+=' No se pudo completar la última sincronización. Revisa Conexiones.';
   }catch(e){if(version===generation&&active())feedback.textContent=e.message}
  }
  $('#live-search').oninput=list;
  container.querySelectorAll('[data-live-filter]').forEach(b=>b.onclick=()=>{filter=b.dataset.liveFilter;container.querySelectorAll('[data-live-filter]').forEach(n=>{n.classList.toggle('active',n===b);n.setAttribute('aria-pressed',String(n===b))});list()});
  select.onchange=load;
  button.onclick=async()=>{const connection=select.value;button.disabled=true;select.disabled=true;feedback.textContent='Actualizando conversaciones…';try{await request(`admin/integrations/${connection}/sync`,'POST',me.csrf);if(active())await load()}catch(e){if(active())feedback.textContent=e.message}finally{button.disabled=false;select.disabled=false}};
  await load();
 }catch(e){if(!active())return;container.innerHTML=`<h2>Conversaciones</h2><p class="settings-notice">${esc(e.message)} Inicia sesión como administrador para usar esta primera versión.</p><button id="live-login" class="primary">Iniciar sesión</button>`;container.querySelector('#live-login').onclick=()=>document.querySelector('#connect').click()}
}
