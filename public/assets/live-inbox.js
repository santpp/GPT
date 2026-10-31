import {contactForm,readContactForm,bindTagEditor} from './contact-editor.js';
import {opportunityForm,readOpportunityForm,bindPipelineChoice} from './opportunity-editor.js';
import {attachmentsOf,attachmentMarkup,bindAttachments,statusMarkup,UI_ICONS} from './message-view.js';
import {workspace,friendly} from './workspace.js';
const drafts=new Map();
window.addEventListener('agora:logout',()=>{drafts.clear();for(const key of Object.keys(sessionStorage))if(key.startsWith('agora-send:'))sessionStorage.removeItem(key)});
const sendChannels={TYPE_SMS:'SMS',TYPE_WHATSAPP:'WhatsApp',TYPE_INSTAGRAM:'IG',TYPE_FACEBOOK:'FB',TYPE_EMAIL:'Email'};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const channel=value=>({TYPE_WHATSAPP:'WhatsApp',TYPE_INSTAGRAM:'Instagram',TYPE_SMS:'SMS',TYPE_EMAIL:'Email',TYPE_FACEBOOK:'Messenger',TYPE_CALL:'Llamada',TYPE_LIVE_CHAT:'Chat web',TYPE_INTERNAL_COMMENTS:'Nota interna'}[value]||'Conversación');
const name=c=>c.contactName||c.fullName||[c.firstName,c.lastName].filter(Boolean).join(' ')||'Contacto sin nombre';
const initials=c=>name(c).split(/\s+/).slice(0,2).map(s=>[...s][0]).join('').toUpperCase();
// En la lista basta la hora si es de hoy, «Ayer», el día de la semana o la fecha corta.
const when=value=>{if(!value)return '';const d=new Date(typeof value==='string'&&/^\d{4}-\d\d-\d\d \d\d:/.test(value)?value.replace(' ','T')+'Z':value);if(Number.isNaN(d.getTime()))return '';const today=new Date();today.setHours(0,0,0,0);const day=new Date(d);day.setHours(0,0,0,0);const ago=Math.round((today-day)/86400000);if(ago<=0)return new Intl.DateTimeFormat('es',{timeStyle:'short'}).format(d);if(ago===1)return 'Ayer';if(ago<7)return new Intl.DateTimeFormat('es',{weekday:'short'}).format(d);return new Intl.DateTimeFormat('es',{day:'numeric',month:'short'}).format(d)};
const date=value=>{if(!value)return '';const d=new Date(typeof value==='string'&&/^\d{4}-\d\d-\d\d \d\d:/.test(value)?value.replace(' ','T')+'Z':value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'short',timeStyle:'short'}).format(d)};
const normalize=s=>String(s??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
async function request(path,method='GET',csrf='',body=null){
 const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
 let data;try{data=await r.json()}catch{const error=Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}). Reintenta; si persiste, comparte este código.`);error.status=r.status;throw error}
 if(!r.ok){const error=Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:''));error.code=data.code;error.status=r.status;error.retryAfter=Number(data.retryAfter)||0;throw error}return data;
}
export async function renderLiveInbox(container){
 container.agoraLiveDispose?.();
 const instance=Symbol();container.agoraLiveInstance=instance;
 const active=()=>container.agoraLiveInstance===instance&&container.dataset.view==='inbox';
 container.innerHTML='<p class="empty">Cargando conversaciones…</p>';
 try{
  const me=await request('me');const {items}=await request('workspace/connections');if(!active())return;
  container.innerHTML=`<div class="live-toolbar"><p id="live-feedback" class="live-feedback" role="status"></p><label>Mostrar<select id="live-limit" aria-label="Cantidad de conversaciones"><option value="20">20</option><option value="50">50</option><option value="100">100</option></select></label></div><p id="live-auto-status" class="live-auto-status" role="status" hidden></p><div class="live-inbox-layout"><section class="live-sidebar" aria-label="Lista de conversaciones"><label class="search"><span aria-hidden="true">⌕</span><input id="live-search" type="search" placeholder="Buscar nombre o mensaje" aria-label="Buscar en conversaciones cargadas"></label><div class="tabs"><button data-live-filter="all" class="active" aria-pressed="true">Todas</button><button data-live-filter="unread" aria-pressed="false">No leídas</button><button data-live-filter="mine" aria-pressed="false" id="live-filter-mine" hidden>Mías</button><span class="live-channel-filters" id="live-channel-filters"></span><span id="live-count"></span></div><div id="live-list"></div></section><section id="live-messages" aria-label="Conversación"><p class="empty">Selecciona una conversación para consultar su historial.</p></section><aside id="live-contact-panel" class="live-contact-panel" aria-label="Ficha del contacto"></aside></div>`;
  const $=s=>container.querySelector(s), limit=$('#live-limit'),feedback=$('#live-feedback'),layout=$('.live-inbox-layout');
  const mineButton=$('#live-filter-mine');
  if(mineButton&&me?.user?.identity_type==='external')mineButton.hidden=false;
  // The chosen account is read from the header on every use, so switching there
  // takes effect without this screen owning a control of its own.
  const select={get value(){return workspace.current()},set disabled(v){}};
  if(!items.length){feedback.textContent='Añade una cuenta desde Conexiones para empezar.';return}
  const PULSE=5,CHAT_FALLBACK=60;
  let disposed=false,timer=null,chatTimer=null,pollTimer=null,interval=120,pause=0,syncBusy=false,messageBusy=0,chatNeedsRefresh=false;
  // El modo foco esconde el menú y la lista, así que sólo puede estar puesto
  // mientras hay una conversación abierta: recordado a secas dejaba la pantalla
  // en blanco al volver a entrar, sin menú y sin forma de salir.
  let focusWanted=false;
  try{focusWanted=localStorage.getItem('agora-focus')==='1'}catch{}
  document.body.classList.remove('agora-focus');
  const canRefresh=()=>active()&&!disposed&&!document.hidden&&navigator.onLine;
  const autoStatus=$('#live-auto-status');
  // Nothing is said while everything works: a line that is always there stops
  // being read, and then it cannot warn about anything.
  function say(text=''){autoStatus.textContent=text;autoStatus.hidden=text==='';}
  function schedule(seconds=interval){clearTimeout(timer);if(!canRefresh())return;timer=setTimeout(tick,Math.max(1,seconds)*1000)}
  // The chat no longer asks the account on a timer: the pulse says when there is
  // something new. This stays as a slow safety net in case a change is missed.
  function scheduleChat(seconds=CHAT_FALLBACK){clearTimeout(pollTimer);if(!canRefresh()||!selected||!layout.classList.contains('chat-open'))return;pollTimer=setTimeout(pollChat,Math.max(2,seconds)*1000)}
  /**
   * Near-live updates. The pulse asks Ágora, never the account, so it is free of
   * the account's budget: it answers a token that only changes when the
   * conversations changed, and one shared pass per account does the reading
   * however many people are looking.
   */
  function schedulePulse(seconds=PULSE){clearTimeout(pulseTimer);if(!canRefresh())return;pulseTimer=setTimeout(pulseTick,Math.max(2,seconds)*1000)}
  async function pulseTick(){
   if(!canRefresh())return;
   if(syncBusy||messageBusy||[...drafts.values()].some(d=>d.sending)){schedulePulse(3);return}
   const connection=select.value;
   try{
    const beat=await request(`workspace/pulse?connectionId=${encodeURIComponent(connection)}`);
    if(!active()||connection!==select.value)return;
    if(beat.retryAfter)pause=Math.max(pause,Date.now()/1000+Number(beat.retryAfter));
    window.dispatchEvent(new CustomEvent('agora:unread',{detail:Number(beat.unread)||0}));
    say(beat.reason==='paused'?'En espera unos segundos para respetar la cuota de la cuenta'
     :beat.reason==='failed'?'Reintentando la conexión con la cuenta'
     :'');
    if(beat.token&&beat.token!==pulseToken){
     const first=pulseToken===null;
     pulseToken=beat.token;
     if(!first){
      await load(true);
      if(active()&&selected&&!messageBusy)await page(false,true);
     }
    }
   }catch(e){if(e.status===401){container.agoraLiveDispose();return}}
   finally{schedulePulse()}
  }
  async function pollChat(){
   if(!canRefresh()||!selected)return;
   if(syncBusy||messageBusy||drafts.get(select.value+':'+selected.id)?.sending){scheduleChat(3);return}
   await page(false,true);
  }
  function wake(){clearTimeout(timer);clearTimeout(chatTimer);clearTimeout(pollTimer);clearTimeout(pulseTimer);if(canRefresh()){say();schedule(Math.max(1,pause-Date.now()/1000));scheduleChat();schedulePulse(1)}else say(navigator.onLine?'En pausa mientras la pantalla está oculta':'Sin conexión · se reanudará solo')}
  container.agoraLiveDispose=()=>{disposed=true;document.body.classList.remove('agora-focus');clearTimeout(timer);clearTimeout(chatTimer);clearTimeout(pollTimer);clearTimeout(pulseTimer);document.removeEventListener('visibilitychange',wake);window.removeEventListener('online',wake);window.removeEventListener('offline',wake)};
  document.addEventListener('visibilitychange',wake);window.addEventListener('online',wake);window.addEventListener('offline',wake);
  async function tick(){if(!canRefresh())return;if(syncBusy||messageBusy||[...drafts.values()].some(d=>d.sending)){schedule(5);return}if(pause>Date.now()/1000){schedule(pause-Date.now()/1000);return}await sync(true)}
  let generation=0, chatGeneration=0, conversations=[], selected=null, filter='all', nextCursor=null, hasMore=false, loadingMore=false, pulseToken=null, pulseTimer=null, messages=new Map(), cursor=null, chatChannel=null, chatChannels=null, channelFilter='', searchTerm='', searchTimer=null, channelNames={}, templates={open:false,items:null,configured:true,error:'',chosen:'',values:{},sending:false,notice:'',generation:-1};
  const inboxQuery=()=>{
   const parts=[`connectionId=${encodeURIComponent(select.value)}`,`limit=${encodeURIComponent(limit.value||'20')}`];
   if(filter==='unread')parts.push('status=unread');
   if(filter==='mine')parts.push('mine=1');
   if(channelFilter)parts.push('channel='+encodeURIComponent(channelFilter));
   if(searchTerm)parts.push('query='+encodeURIComponent(searchTerm));
   return parts.join('&');
  };
  function list(){
   const visible=conversations;
   $('#live-count').textContent=visible.length;
   $('#live-list').innerHTML=visible.map(c=>`<button class="conversation-item live-conversation ${selected?.id===c.id?'selected':''}" data-live-id="${esc(c.id)}" aria-label="Abrir conversación de ${esc(name(c))}" aria-pressed="${selected?.id===c.id}"><span class="avatar">${esc(initials(c))}</span><span class="conversation-info"><header><strong>${esc(name(c))}</strong><time title="${esc(date(c.lastMessageDate))}">${esc(when(c.lastMessageDate))}</time></header><p>${esc(c.lastMessageBody||'Sin vista previa')}</p><footer><span class="channel">${channel(c.lastMessageType)}</span>${Number(c.unreadCount)>0?`<b class="unread">${Number(c.unreadCount)}<span class="sr-only"> pendientes</span></b>`:''}</footer></span></button>`).join('')||'<p class="empty">No hay conversaciones con este filtro.</p>';
   // The account keeps a position to continue from, so the list is not confined
   // to the window of 20, 50 or 100.
   if(hasMore)$('#live-list').insertAdjacentHTML('beforeend',`<button id="live-more" class="live-more"${loadingMore?' disabled':''}>${loadingMore?'Cargando…':'Cargar más conversaciones'}</button>`);
   container.querySelectorAll('[data-live-id]').forEach(b=>b.onclick=()=>open(conversations.find(c=>c.id===b.dataset.liveId)));
   const more=$('#live-more');
   if(more)more.onclick=()=>page_more();
  }
  async function page_more(){
   if(loadingMore||!nextCursor)return;
   loadingMore=true;list();
   const connection=select.value,version=generation,cursor=nextCursor;
   try{
    const data=await request(`workspace/inbox?${inboxQuery()}&startAfter=${encodeURIComponent(cursor)}`);
    if(version!==generation||!active()||connection!==select.value)return;
    const known=new Set(conversations.map(c=>c.id));
    const added=asList(data.items).filter(c=>!known.has(c.id));
    conversations=conversations.concat(added);
    nextCursor=data.nextCursor||null;
    // Two conversations can share a position; a page that adds nothing new ends
    // the list instead of asking for the same page again.
    hasMore=data.hasMore===true&&!!nextCursor&&added.length>0;
    feedback.textContent=`${conversations.length} de ${data.total} conversaciones`;
   }catch(e){
    if(e.status===401)container.agoraLiveDispose();
    if(active())feedback.textContent=friendly(e);
    hasMore=false;
   }finally{loadingMore=false;if(active())list()}
  }
  function renderMessages(){
   const log=$('#live-log');
   // El aviso de las 24 horas depende de lo último que escribió el contacto.
   queueMicrotask(()=>{const hint=$('#live-window-hint');if(hint)hint.hidden=!(chatChannel==='WhatsApp'&&windowClosed())});
   log.innerHTML=[...messages.values()].sort((a,b)=>(Date.parse(a.dateAdded)||0)-(Date.parse(b.dateAdded)||0)).map(m=>{
    const files=attachmentsOf(m);
    const body=m.body?esc(m.body):(files.length?'':'Mensaje sin texto');
    const failed=['failed','undelivered','opt_out'].includes(m.status);
    return `<div class="bubble ${m.direction==='outbound'?'out':''}${failed?' failed':''}">${body}${files.length?`<div class="live-attachments">${files.map(attachmentMarkup).join('')}</div>`:''}${statusMarkup(m,date(m.dateAdded))}</div>`;
   }).join('')||'<p class="empty">No hay mensajes disponibles.</p>';
   bindAttachments(log);
   // Volver a escribir un mensaje que no salió: el texto vuelve al cuadro de
   // respuesta para revisarlo, y se envía sólo cuando la persona lo decide.
   log.querySelectorAll('[data-retry]').forEach(button=>button.onclick=()=>{
    const original=messages.get(button.dataset.retry);
    const field=$('#live-reply');
    if(!original||!field)return;
    field.value=original.body;
    field.dispatchEvent(new Event('input',{bubbles:true}));
    field.focus();
    const notice=$('#live-send-status');
    if(notice)notice.textContent='Revisa el texto, elige el canal y pulsa Enviar.';
   });
  }
  async function page(older=false,refresh=false){
   messageBusy++;
   const current=chatGeneration, connection=select.value, conversation=selected.id, previous=older?cursor:null;
   const more=$('#live-older'),retry=$('#live-retry');more.disabled=true;
   if(!refresh){retry.hidden=true;$('#live-chat-status').textContent=older?'Cargando mensajes anteriores…':'Cargando mensajes…'}
   try{
    const r=await request(`workspace/messages?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(conversation)}${previous?'&lastMessageId='+encodeURIComponent(previous):''}`);
    if(!active()||current!==chatGeneration)return;
    const log=$('#live-log'),height=log.scrollHeight,top=log.scrollTop,hadMessages=messages.size>0;
    for(const m of r.items)messages.set(m.id,m);
    if(!refresh||!hadMessages)cursor=r.hasMore&&r.lastMessageId&&r.lastMessageId!==previous?r.lastMessageId:null;
    chatNeedsRefresh=false;renderMessages();more.hidden=!cursor;$('#live-chat-status').textContent=r.hasMore&&!cursor?'No se recibió una referencia válida para continuar el historial.':'';
    if(refresh)say();
    log.scrollTop=older?top+log.scrollHeight-height:refresh&&height-top-log.clientHeight>60?top:log.scrollHeight;
   }catch(e){if(e.retryAfter)pause=Math.max(pause,Date.now()/1000+e.retryAfter);if(e.status===401)container.agoraLiveDispose();if(active()&&current===chatGeneration){if(!refresh||!messages.size){$('#live-chat-status').textContent=friendly(e);retry.hidden=false;retry.onclick=()=>page(older)}else say('Reintentando la actualización del chat')}}
   finally{messageBusy--;if(active()&&current===chatGeneration){more.disabled=false;if(refresh)scheduleChat(effectiveChatDelay())}}
  }
  // The pulse says when there is something new, so this is only the safety net.
  function effectiveChatDelay(){return Math.max(CHAT_FALLBACK,pause-Date.now()/1000)}
  // Simple marks drawn here, not official brand artwork: enough to recognise the
  // channel at a glance without redistributing anyone's logo files.
  const channelMarks={
   WhatsApp:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.6 7.9c.3-.1.7 0 .9.4l.7 1.4c.1.3.1.6-.1.8l-.5.6c-.1.2-.2.4 0 .7.5.9 1.3 1.7 2.2 2.2.3.2.5.1.7 0l.6-.5c.2-.2.5-.2.8-.1l1.4.7c.4.2.5.6.4.9-.3.8-1.1 1.3-2 1.2-3-.3-5.6-2.9-5.9-5.9-.1-.9.4-1.7 1.2-2Z" fill="currentColor"/></svg>',
   IG:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5.2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="4.1" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="17.2" cy="6.8" r="1.25" fill="currentColor"/></svg>',
   FB:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.6c-5.3 0-9.4 3.9-9.4 9 0 2.9 1.3 5.4 3.5 7.1v3.6l3.2-1.8c.9.2 1.8.4 2.7.4 5.3 0 9.4-3.9 9.4-9s-4.1-9.3-9.4-9.3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m6.9 14.3 3.5-3.7 1.9 1.9 3-1.9-3.5 3.7-1.9-1.9-3 1.9Z" fill="currentColor"/></svg>',
   SMS:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4.8h16a1.2 1.2 0 0 1 1.2 1.2v9.6a1.2 1.2 0 0 1-1.2 1.2H9.2L4.6 20.4V16.8H4A1.2 1.2 0 0 1 2.8 15.6V6A1.2 1.2 0 0 1 4 4.8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="8.4" cy="10.8" r="1.1" fill="currentColor"/><circle cx="12" cy="10.8" r="1.1" fill="currentColor"/><circle cx="15.6" cy="10.8" r="1.1" fill="currentColor"/></svg>',
   Email:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.8" y="5" width="18.4" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m3.4 6.6 8.6 6 8.6-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
  };
  const CHANNEL_REASONS={needs_phone:'Este contacto no tiene teléfono en su ficha.',needs_email:'Este contacto no tiene correo en su ficha.',needs_thread:'Sólo se puede responder por aquí si el contacto escribió primero.'};
  const threadChannel=c=>sendChannels[c.lastMessageType]
    ? [{type:sendChannels[c.lastMessageType],label:channel(c.lastMessageType),needsSubject:sendChannels[c.lastMessageType]==='Email'}] : [];
  const usableChannels=offered=>asList(offered).filter(o=>o.available!==false);
  const pickerMarkup=offered=>{
   const list=asList(offered);
   if(list.length<2&&!list.some(o=>o.available===false))return `<p class="live-channel-single">${esc(list[0]?.label??'')} · Texto</p>`;
   return list.map(o=>{
    const off=o.available===false;
    const title=off?` title="${esc(CHANNEL_REASONS[o.reason]||'No disponible para este contacto.')}"`:'';
    return `<button type="button" data-channel="${esc(o.type)}" class="channel-${esc(o.type)}${o.type===chatChannel&&!off?' selected':''}${off?' unavailable':''}"${off?' disabled aria-disabled="true"':''} aria-pressed="${o.type===chatChannel&&!off}"${title}><span class="live-channel-mark" aria-hidden="true">${channelMarks[o.type]||''}</span><span class="live-channel-label">${esc(o.label)}</span></button>`;
   }).join('');
  };
  function composer(c,channels=null){
   const offered=channels??threadChannel(c);
   const usable=usableChannels(offered);
   if(!usable.length||!c.contactId)return '<p class="live-readonly">Este canal todavía no admite respuestas desde aquí.</p>';
   const chosen=usable.find(o=>o.type===chatChannel)||usable[0];
   chatChannel=chosen.type;
   return `<form id="live-composer" class="live-composer"><div class="live-channels" id="live-channel-row" role="group" aria-label="Canal de envío">${pickerMarkup(offered)}</div><div id="live-subject-slot">${chosen.needsSubject?'<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>':''}</div><p id="live-window-hint" class="live-window-hint" hidden>Han pasado más de 24 horas desde su último mensaje. WhatsApp sólo permite escribir con una plantilla aprobada.</p><div id="live-template-slot"></div><label class="sr-only" for="live-reply">Respuesta</label><textarea id="live-reply" maxlength="4000" rows="3" required placeholder="Escribe una respuesta…"></textarea><div class="live-composer-actions"><span id="live-channel-name">${esc(chosen.label)}</span><button type="button" id="live-template-toggle" class="link-button" hidden>Usar plantilla</button><button id="live-send" class="primary">Enviar</button></div><p id="live-send-status" role="status"></p></form>`;
  }

  /**
   * Updates only the channel row and the subject slot. The textarea is never
   * replaced, so what is being written, the caret and an in-flight send survive.
   */
  function applyChannels(c,offered,draft){
   const row=$('#live-channel-row');if(!row)return;
   const usable=usableChannels(offered);
   const chosen=usable.find(o=>o.type===chatChannel)||usable[0];
   if(!chosen)return;
   chatChannel=chosen.type;
   row.innerHTML=pickerMarkup(offered);
   row.classList.toggle('live-channels-single',asList(offered).length<2);
   const name=$('#live-channel-name');if(name)name.textContent=chosen.label;
   syncTemplates(c);
   const slot=$('#live-subject-slot');
   if(slot){
    const present=!!$('#live-subject');
    if(chosen.needsSubject&&!present)slot.innerHTML='<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>';
    if(!chosen.needsSubject&&present)slot.innerHTML='';
    const topic=$('#live-subject');
    if(topic){topic.value=draft.subject;topic.disabled=draft.sending;topic.oninput=()=>draft.subject=topic.value}
   }
   row.querySelectorAll('[data-channel]:not([disabled])').forEach(button=>button.onclick=()=>{
    if(draft.sending||button.dataset.channel===chatChannel)return;
    chatChannel=button.dataset.channel;draft.notice='';
    applyChannels(c,offered,draft);
    const notice=$('#live-send-status');if(notice)notice.textContent='';
   });
  }
  // ---- Plantillas de WhatsApp: para escribir pasadas las 24 horas ------------------
  const FIELD_NAMES={'contact.name':'nombre del contacto','contact.first_name':'nombre','contact.last_name':'apellidos','contact.full_name':'nombre completo','contact.phone':'teléfono','contact.email':'correo','contact.company_name':'empresa','user.name':'tu nombre','user.first_name':'tu nombre','location.name':'nombre de la empresa'};
  const CATEGORY={MARKETING:'Promoción',UTILITY:'Servicio',AUTHENTICATION:'Verificación'};
  /** ¿Pasaron más de 24 horas desde lo último que escribió el contacto? */
  function windowClosed(){
   let last=0;
   for(const m of messages.values())if(m.direction==='inbound'){const t=Date.parse(m.dateAdded);if(t>last)last=t}
   return last>0&&Date.now()-last>24*3600*1000;
  }
  function chosenTemplate(){return (templates.items||[]).find(t=>t.id===templates.chosen)||null}
  /** ¿Falta alguna variable por escribir? Sin ellas, Meta rechaza la plantilla. */
  function templateIncomplete(t){return ['header','body'].some(part=>(t.variables[part]||[]).some(v=>v.fixed===null&&!String((templates.values[part]||{})[v.token]||'').trim()))}
  /** El texto de la plantilla, con lo escrito y con los campos de la ficha nombrados. */
  function templatePreview(t){
   const fill=(text,part)=>esc(text).replace(/\{\{\s*([A-Za-z0-9_.]{1,60})\s*\}\}/g,(all,token)=>{
    const v=(t.variables[part]||[]).find(x=>x.token===token);
    if(/^\d+$/.test(token)&&v&&v.fixed===null){const typed=(templates.values[part]||{})[token];return typed?`<mark>${esc(typed)}</mark>`:`<mark class="empty">${esc(v.label)}</mark>`}
    const field=v&&v.fixed?v.fixed.replace(/^\{\{\s*|\s*\}\}$/g,''):token;
    return `<mark class="field">${esc(FIELD_NAMES[field]||field)}</mark>`;
   });
   return `${t.header?`<strong>${fill(t.header,'header')}</strong>`:''}<p>${fill(t.body,'body').replace(/\n/g,'<br>')}</p>${t.footer?`<small>${esc(t.footer)}</small>`:''}${t.buttons.length?`<div class="tpl-buttons">${t.buttons.map(b=>`<span>${esc(b)}</span>`).join('')}</div>`:''}`;
  }
  function templateMarkup(){
   if(!templates.open)return '';
   const close='<button type="button" class="link-button" id="tpl-close">Volver a escribir</button>';
   if(templates.items===null)return `<div class="tpl-panel"><p class="muted">Cargando plantillas…</p>${close}</div>`;
   if(templates.error)return `<div class="tpl-panel"><p class="muted">${esc(templates.error)}</p>${close}</div>`;
   if(!templates.configured)return `<div class="tpl-panel"><p class="muted">Esta cuenta todavía no tiene el número de WhatsApp para plantillas. El administrador lo añade en Conexiones.</p>${close}</div>`;
   if(!templates.items.length)return `<div class="tpl-panel"><p class="muted">Esta cuenta no tiene plantillas aprobadas de WhatsApp.</p>${close}</div>`;
   const t=chosenTemplate();
   const free=t?['header','body'].flatMap(part=>(t.variables[part]||[]).filter(v=>v.fixed===null).map(v=>({part,...v}))):[];
   return `<div class="tpl-panel"><label class="tpl-pick">Plantilla aprobada<select id="tpl-select"><option value="">Elige una plantilla</option>${templates.items.map(x=>`<option value="${esc(x.id)}"${x.id===templates.chosen?' selected':''}>${esc(x.name.replace(/_/g,' '))}${x.category?' · '+esc(CATEGORY[x.category]||x.category):''}</option>`).join('')}</select></label>
    ${t?`<div class="tpl-preview">${templatePreview(t)}</div>${t.unsupported?'<p class="muted">Esta plantilla lleva una imagen o un enlace que cambia. Por ahora envíala desde la cuenta.</p>':''}
    ${free.map(v=>`<label class="tpl-var">${esc(v.label)}<input data-tpl-part="${v.part}" data-tpl-token="${esc(v.token)}" maxlength="200" value="${esc((templates.values[v.part]||{})[v.token]||'')}"></label>`).join('')}
    <p class="muted tpl-note">Los datos marcados en gris, como el nombre, los completa la cuenta con la ficha del contacto.</p>`:''}
    <div class="tpl-actions">${close}<button type="button" class="primary" id="tpl-send"${!t||t.unsupported||templates.sending||templateIncomplete(t)?' disabled':''}>${templates.sending?'Enviando…':'Enviar plantilla'}</button></div>
    ${templates.notice?`<p class="muted" role="status">${esc(templates.notice)}</p>`:''}</div>`;
  }
  async function loadTemplates(c,refresh=false){
   const version=chatGeneration;templates.items=null;templates.error='';syncTemplates(c);
   try{
    const data=await request(`workspace/templates?connectionId=${encodeURIComponent(select.value)}${refresh?'&refresh=1':''}`);
    if(version!==chatGeneration||!active())return;
    templates.items=Array.isArray(data.items)?data.items:[];templates.configured=data.configured!==false;
   }catch(error){if(version!==chatGeneration||!active())return;templates.items=[];templates.error=friendly(error)}
   syncTemplates(c);
  }
  /** Pinta el botón, el aviso de 24 horas y el panel, sin tocar lo que se está escribiendo. */
  function syncTemplates(c){
   if(templates.generation!==chatGeneration){templates={open:false,items:null,configured:true,error:'',chosen:'',values:{},sending:false,notice:'',generation:chatGeneration}}
   const form=$('#live-composer');if(!form)return;
   const whatsapp=chatChannel==='WhatsApp';
   if(!whatsapp)templates.open=false;
   const toggle=$('#live-template-toggle');if(toggle)toggle.hidden=!whatsapp||templates.open;
   const hint=$('#live-window-hint');if(hint)hint.hidden=!(whatsapp&&windowClosed());
   form.classList.toggle('template-mode',templates.open);
   const slot=$('#live-template-slot');if(!slot)return;
   slot.innerHTML=templateMarkup();
   const back=$('#tpl-close');if(back)back.onclick=()=>{templates.open=false;templates.notice='';syncTemplates(c)};
   const pick=$('#tpl-select');if(pick)pick.onchange=()=>{templates.chosen=pick.value;templates.values={};templates.notice='';syncTemplates(c)};
   slot.querySelectorAll('[data-tpl-token]').forEach(input=>input.oninput=()=>{
    (templates.values[input.dataset.tplPart]??={})[input.dataset.tplToken]=input.value;
    const t=chosenTemplate(),box=slot.querySelector('.tpl-preview');if(t&&box)box.innerHTML=templatePreview(t);
    const go=$('#tpl-send');if(go&&t)go.disabled=!!t.unsupported||templates.sending||templateIncomplete(t);
   });
   const send=$('#tpl-send');if(send)send.onclick=()=>sendTemplate(c);
  }
  async function sendTemplate(c){
   const t=chosenTemplate();if(!t||templates.sending)return;
   const connection=select.value,version=chatGeneration,key=connection+':'+c.id;
   const body={channel:'WhatsApp',template:{id:t.id,values:templates.values}};
   templates.sending=true;templates.notice='';syncTemplates(c);
   let storageKey;
   try{
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(JSON.stringify(body)));
    storageKey='agora-send:'+key+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
    const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID();sessionStorage.setItem(storageKey,requestId);
    const result=await request(`workspace/send?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`,'POST',me.csrf,{...body,requestId});
    sessionStorage.removeItem(storageKey);
    if(version!==chatGeneration||!active())return;
    const shown=$('.tpl-preview')?.innerText||t.body;
    messages.set(result.messageId,{id:result.messageId,body:shown,direction:'outbound',dateAdded:new Date().toISOString(),status:result.deliveryStatus});
    renderMessages();$('#live-log').scrollTop=$('#live-log').scrollHeight;
    templates.open=false;templates.chosen='';templates.values={};
    const drafted=drafts.get(key);if(drafted){drafted.notice='Plantilla aceptada por el canal.';}
    const notice=$('#live-send-status');if(notice)notice.textContent='Plantilla aceptada por el canal.';
   }catch(error){
    templates.notice=friendly(error);
    if(storageKey&&['send_rejected','template_values','template_unknown','template_unsupported','needs_phone','number_missing'].includes(error.code))sessionStorage.removeItem(storageKey);
   }finally{templates.sending=false;if(version===chatGeneration&&active())syncTemplates(c)}
  }

  function bindComposer(c){
   const form=$('#live-composer');if(!form)return;
   const connection=select.value,version=chatGeneration,key=connection+':'+c.id;
   const draft=drafts.get(key)||{message:'',subject:'',sending:false,notice:''};drafts.set(key,draft);
   applyChannels(c,chatChannels??threadChannel(c),draft);
   const text=$('#live-reply'),subject=$('#live-subject');
   // Looked up when used, not captured: switching channel replaces these nodes
   // and an in-flight send must still reach the form the person is looking at.
   const show=()=>{
    const field=$('#live-reply'),topic=$('#live-subject'),send=$('#live-send'),notice=$('#live-send-status');
    if(!field)return;
    field.value=draft.message;field.disabled=draft.sending;
    if(topic){topic.value=draft.subject;topic.disabled=draft.sending}
    if(send){send.disabled=draft.sending;send.textContent=draft.sending?'Enviando…':'Enviar'}
    if(notice)notice.textContent=draft.notice;
   };
   show();
   text.oninput=()=>draft.message=text.value;if(subject)subject.oninput=()=>draft.subject=subject.value;
   form.onsubmit=async e=>{
    e.preventDefault();if(draft.sending||!draft.message.trim())return;
    draft.sending=true;draft.notice='Enviando…';show();
    let storageKey;
    const body={channel:chatChannel||sendChannels[c.lastMessageType],message:draft.message.trim(),subject:draft.subject.trim()};
    try{
     const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(JSON.stringify(body)));
     storageKey='agora-send:'+key+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
     // Persist only a random request key and content hash, never message text or credentials.
     const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID();sessionStorage.setItem(storageKey,requestId);
     const result=await request(`workspace/send?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`,'POST',me.csrf,{...body,requestId});
     sessionStorage.removeItem(storageKey);draft.message='';draft.notice=result.deliveryStatus==='failed'?'El canal registró el mensaje como fallido. Revisa su configuración.':'Mensaje aceptado por el canal.';
     if(active()&&version===chatGeneration){messages.set(result.messageId,{id:result.messageId,body:body.message,direction:'outbound',dateAdded:new Date().toISOString(),status:result.deliveryStatus});renderMessages();$('#live-log').scrollTop=$('#live-log').scrollHeight;c.lastMessageBody=body.message;list()}
    }catch(error){draft.notice=friendly(error);if(storageKey&&['send_rejected','invalid_message','invalid_subject','unsupported_channel','invalid_contact','connection_unverified'].includes(error.code))sessionStorage.removeItem(storageKey)}
    finally{draft.sending=false;if(active()&&version===chatGeneration)show()}
   };
   const toggle=$('#live-template-toggle');
   if(toggle)toggle.onclick=()=>{templates.open=true;templates.notice='';if(templates.items===null||templates.error)loadTemplates(c);else syncTemplates(c)};
   syncTemplates(c);
  }
  const amount=value=>typeof value==='number'&&isFinite(value)?new Intl.NumberFormat('es-ES',{maximumFractionDigits:2,useGrouping:'always'}).format(value):null;
  function opportunityCard(item,canEdit=false){
   const rows=[['Pipeline',item.pipelineName],['Etapa',item.stageName],['Estado',item.statusLabel],['Valor',amount(item.monetaryValue)],['Responsable',item.assignedName],['Fuente',item.source]].filter(([,value])=>value);
   return `<div class="live-opportunity"><h3>${esc(item.name||'Oportunidad sin nombre')}</h3>${rows.map(([label,value])=>`<div><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}${canEdit?`<button class="live-opportunity-edit" data-edit-opportunity="${esc(item.id)}">Editar oportunidad</button>`:''}</div>`;
  }
  function opportunitySection(opportunities,editor={}){
   if(!opportunities)return '<span class="muted">Cargando oportunidades…</span>';
   if(opportunities.error)return `<span class="muted">${esc(opportunities.error)}</span>`;
   const items=asList(opportunities.items);
   const canEdit=opportunities.canEdit===true;
   // While a form is open it takes the block, so the state being changed is one.
   if(editor.open)return (editor.pipelines
     ? opportunityForm({pipelines:editor.pipelines.pipelines,assignees:editor.pipelines.assignees,opportunity:editor.open==='new'?null:items.find(item=>item.id===editor.open)||null})
     : '<span class="muted">Cargando embudos…</span>')
    +(editor.notice?`<span class="muted">${esc(editor.notice)}</span>`:'');
   const add=canEdit?'<button class="live-opportunity-new" data-new-opportunity>+ Nueva oportunidad</button>':'';
   const notice=editor.notice?`<span class="muted">${esc(editor.notice)}</span>`:'';
   if(!items.length||!opportunities.active)return `<span class="muted">${opportunities.restricted?'Sin oportunidades asignadas a tu usuario.':'Este contacto no tiene oportunidades.'}</span>${add}${notice}`;
   const others=items.filter(item=>item.id!==opportunities.active.id);
   return opportunityCard(opportunities.active,canEdit)
    +(others.length?`<details class="live-opportunity-all"><summary>Ver todas las oportunidades (${items.length})</summary>${others.map(item=>opportunityCard(item,canEdit)).join('')}</details>`:'')
    +(opportunities.partial?'<span class="muted">Se muestran las oportunidades más recientes de este contacto.</span>':'')
    +add+notice;
  }
  const dayOnly=value=>{if(!value)return '';const d=new Date(value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'medium'}).format(d)};
  const asList=value=>Array.isArray(value)?value:[];
  function activitySection(activity){
   if(!activity)return '<span class="muted">Cargando actividad…</span>';
   if(activity.error)return `<span class="muted">${esc(activity.error)}</span>`;
   // A panel must not break because one block came back in another shape.
   activity={...activity,tasks:asList(activity.tasks),notes:asList(activity.notes),appointments:asList(activity.appointments)};
   const tasks=activity.tasks.length?activity.tasks.map(task=>`<div class="live-task${task.completed?' done':''}"><button data-task="${esc(task.id)}" data-completed="${task.completed?1:0}"${activity.canEdit?'':' disabled'} aria-label="${task.completed?'Reabrir tarea':'Completar tarea'}">${task.completed?'☑':'☐'}</button><div><strong>${esc(task.title)}</strong>${task.body?`<p>${esc(task.body)}</p>`:''}<small>${esc([dayOnly(task.dueDate),task.assignedName].filter(Boolean).join(' · ')||'Sin fecha')}</small></div></div>`).join(''):'<span class="muted">Sin tareas para este contacto.</span>';
   const notes=activity.notes.length?activity.notes.map(note=>`<div class="live-note"><p>${esc(note.body)}</p><small>${esc([date(note.dateAdded),note.authorName].filter(Boolean).join(' · '))}</small></div>`).join(''):'<span class="muted">Sin notas para este contacto.</span>';
   const appointments=!activity.appointmentsVisible?'<span class="muted">Tu usuario no tiene el módulo de citas activo.</span>'
    :activity.appointments.length?activity.appointments.map(item=>`<div class="live-appointment"><strong>${esc(item.title)}</strong><small>${esc([date(item.startTime),item.statusLabel,item.assignedName,item.address].filter(Boolean).join(' · '))}</small></div>`).join(''):'<span class="muted">Sin citas para este contacto.</span>';
   const forms=activity.canEdit?`<div class="live-quick-actions"><details><summary>+ Crear tarea</summary><form id="live-task-form"><label class="sr-only" for="live-task-title">Título de la tarea</label><input id="live-task-title" maxlength="190" placeholder="Título de la tarea" required><label class="sr-only" for="live-task-due">Vencimiento</label><input id="live-task-due" type="date" required><label class="sr-only" for="live-task-body">Detalle</label><textarea id="live-task-body" maxlength="2000" rows="2" placeholder="Detalle (opcional)"></textarea><button class="primary">Crear tarea</button></form></details><details><summary>+ Crear nota</summary><form id="live-note-form"><label class="sr-only" for="live-note-body">Nota</label><textarea id="live-note-body" maxlength="5000" rows="3" placeholder="Escribe una nota…" required></textarea><button class="primary">Guardar nota</button></form></details><p id="live-activity-status" role="status"></p></div>`:'<p id="live-activity-status" role="status"></p>';
   return `<div class="section-label">TAREAS</div><div class="live-tasks">${tasks}</div><div class="section-label">NOTAS</div><div class="live-notes">${notes}</div><div class="section-label">CITAS</div><div class="live-appointments">${appointments}</div>${forms}`;
  }
  function contactProfile(profile,labels={},opportunities=null,activity=null,editor={}){
   const details=[['Teléfono',profile.phone],['Email',profile.email],['Empresa',profile.companyName],['Sitio web',profile.website],['Fuente',profile.source],['Responsable',profile.assignedName],['Última actividad',date(profile.lastActivity)],['Alta',date(profile.dateAdded)],['Nacimiento',profile.dateOfBirth],['Zona horaria',profile.timezone],['Dirección',profile.address],['No contactar',profile.dnd?'Sí':null]].filter(([,value])=>value);
   const phone=String(profile.phone||'').replace(/[^0-9+]/g,'');
   return `<header class="live-profile-header"><div><span class="section-label">CONTEXTO DEL CONTACTO</span><h2>${esc(profile.name)}</h2></div><div class="live-profile-controls">${profile.canEdit?'<button id="live-edit-contact">Editar</button>':''}<button id="live-profile-close" aria-label="Cerrar ficha">×</button></div></header>${profile.email?`<div class="live-profile-actions"><a href="mailto:${encodeURIComponent(profile.email)}">Escribir por correo</a></div>`:''}<div class="live-profile-details">${details.map(([label,value])=>`<div><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}</div><div class="section-label">ETIQUETAS</div><div class="live-profile-tags">${profile.tags?.length?profile.tags.map(tag=>`<span class="tag">${esc(tag)}</span>`).join(''):'<span class="muted">Sin etiquetas</span>'}</div><div class="section-label">CAMPOS PERSONALIZADOS</div><div class="live-profile-fields">${asList(profile.customFields).filter(field=>field.value!==''&&field.value!=null).map(field=>`<div><span>${esc(labels[field.id]||field.label)}</span><strong>${esc(field.value)}</strong></div>`).join('')||'<span class="muted">Sin campos con valor</span>'}</div><div class="section-label">OPORTUNIDAD ACTIVA</div><div class="live-profile-opportunities">${opportunitySection(opportunities,editor)}</div><div class="live-profile-activity">${activitySection(activity)}</div>`;
  }
  async function loadContact(c,version){
   const panel=$('#live-contact-panel');panel.innerHTML='<p class="empty">Cargando ficha…</p>';
   try{
    const data=await request(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`);if(!active()||version!==chatGeneration)return;
    if(Array.isArray(data.channels)&&data.channels.length){
     chatChannels=data.channels;
     const draft=drafts.get(select.value+':'+c.id);
     // Una conversación recién abierta desde la ficha no tiene canal propio: el
     // cuadro de respuesta aparece cuando la ficha dice por dónde se le puede escribir.
     const readonly=$('.live-readonly');
     if(!$('#live-composer')&&readonly){readonly.outerHTML=composer(c,chatChannels);bindComposer(c)}
     else if(draft)applyChannels(c,chatChannels,draft);
    }
    let bindActivity=()=>{},bindOpportunities=()=>{};
    const bind=()=>{
     $('#live-profile-close').onclick=()=>layout.classList.remove('contact-open');
     const edit=$('#live-edit-contact');
     if(edit)edit.onclick=()=>{editing=true;show()};
     bindActivity();
     bindOpportunities();
    };
    let labels=Object.fromEntries(asList(data.fields).map(field=>[field.id,field.name])),opportunities=null,activity=null,editing=false;
    const editor={open:null,pipelines:null,notice:''};
    data.contact.canEdit=data.canEdit===true;
    const show=()=>{
     if(editing){panel.innerHTML=contactForm(data.contact,data.fields,data.assignees);bindEdit();return}
     panel.innerHTML=contactProfile(data.contact,labels,opportunities,activity,editor);bind();
    };
    let tags={added:[],dropped:[]};
    function bindEdit(){
     const form=panel.querySelector('#live-contact-form');if(!form)return;
     const status=panel.querySelector('#live-contact-status');
     panel.querySelector('#live-edit-cancel').onclick=()=>{editing=false;show()};
     tags=bindTagEditor(panel,data.contact);
     form.onsubmit=async event=>{
      event.preventDefault();
      const save=panel.querySelector('#live-save-contact');save.disabled=true;status.textContent='Guardando ficha…';
      const fields=readContactForm(form,data.contact);
      const payload={fields,addTags:tags.added,removeTags:tags.dropped,requestId:crypto.randomUUID().replace(/-/g,'')};
      try{
       await request(`workspace/activity/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`,'POST',me.csrf,payload);
       const fresh=await request(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}&refresh=1`);
       if(!active()||version!==chatGeneration)return;
       data.contact=fresh.contact;data.contact.canEdit=fresh.canEdit===true;
       editing=false;show();
      }catch(error){
       const failed=panel.querySelector('#live-contact-status');
       if(failed)failed.textContent=friendly(error);
       const button=panel.querySelector('#live-save-contact');if(button)button.disabled=false;
      }
     };
    }
    show();
    // Each extra call waits its turn so the shared per-account budget is respected.


    const query=`connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`;
    const reload=async()=>{
     try{const result=await request(`workspace/activity?${query}&refresh=1`);if(!active()||version!==chatGeneration)return;activity=result}
     catch(error){if(!active()||version!==chatGeneration)return;activity={error:friendly(error)}}
     show();
    };
    // A quick action keeps one key per content, so a retry can never duplicate it.
    const act=async(path,payload,pending,done)=>{
     const status=$('#live-activity-status');if(status)status.textContent=pending;
     const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(path+JSON.stringify(payload)));
     const storageKey='agora-action:'+c.id+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
     const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID().replace(/-/g,'');
     sessionStorage.setItem(storageKey,requestId);
     try{
      await request(`workspace/activity/${path}?${query}`,'POST',me.csrf,{...payload,requestId});
      sessionStorage.removeItem(storageKey);
      await reload();
      const settled=$('#live-activity-status');if(settled)settled.textContent=done;
     }catch(error){
      if(['action_rejected','invalid_note','invalid_task','invalid_due_date','invalid_task_id','invalid_task_state','action_forbidden'].includes(error.code))sessionStorage.removeItem(storageKey);
      const failed=$('#live-activity-status');if(failed)failed.textContent=friendly(error);
     }
    };
    bindActivity=()=>{
     panel.querySelectorAll('[data-task]').forEach(button=>button.onclick=async()=>{
      panel.querySelectorAll('[data-task]').forEach(other=>other.disabled=true);
      await act('task-state',{taskId:button.dataset.task,completed:button.dataset.completed!=='1'},'Actualizando tarea…','Tarea actualizada.');
     });
     const taskForm=$('#live-task-form');
     if(taskForm)taskForm.onsubmit=async event=>{
      event.preventDefault();const title=$('#live-task-title').value.trim(),due=$('#live-task-due').value,body=$('#live-task-body').value.trim();
      if(!title||!due)return;
      taskForm.querySelector('button').disabled=true;
      await act('task',{title,dueDate:due,...(body?{body}:{})},'Creando tarea…','Tarea creada.');
     };
     const noteForm=$('#live-note-form');
     if(noteForm)noteForm.onsubmit=async event=>{
      event.preventDefault();const body=$('#live-note-body').value.trim();
      if(!body)return;
      noteForm.querySelector('button').disabled=true;
      await act('note',{body},'Guardando nota…','Nota guardada.');
     };
    };
    // Opportunities: open one for this contact, or edit the one shown. The form
    // only ever offers the pipelines, stages and team the account publishes.
    const openEditor=async(which)=>{
     editor.open=which;editor.notice='';show();
     if(!editor.pipelines) {
      try{editor.pipelines=await request(`workspace/pipelines?connectionId=${encodeURIComponent(select.value)}`)}
      catch(error){editor.open=null;editor.notice=friendly(error)}
      if(!active()||version!==chatGeneration)return;
      show();
     }
    };
    const saveOpportunity=async(form)=>{
     const status=form.querySelector('[data-status]'),save=form.querySelector('[data-save]');
     const target=editor.open==='new'?null:asList(opportunities?.items).find(item=>item.id===editor.open)||null;
     const payload=readOpportunityForm(form,target);
     if(!payload.name&&!target){status.textContent='Escribe un nombre para la oportunidad.';return}
     if(Object.keys(payload).length===0){status.textContent='No has cambiado nada en la oportunidad.';return}
     save.disabled=true;status.textContent='Guardando la oportunidad…';
     // One key per content, so a retry can never open a second opportunity.
     const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(editor.open+JSON.stringify(payload)));
     const storageKey='agora-opportunity:'+c.id+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
     const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID().replace(/-/g,'');
     sessionStorage.setItem(storageKey,requestId);
     const where=editor.open==='new'
      ? `workspace/opportunity/new?connectionId=${encodeURIComponent(select.value)}&contactId=${encodeURIComponent(data.contact.id)}`
      : `workspace/opportunity/edit?connectionId=${encodeURIComponent(select.value)}&opportunityId=${encodeURIComponent(editor.open)}`;
     try{
      await request(where,'POST',me.csrf,{...payload,requestId});
      sessionStorage.removeItem(storageKey);
      try{opportunities=await request(`workspace/opportunities?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}&refresh=1`)}
      catch(error){opportunities={error:friendly(error)}}
      if(!active()||version!==chatGeneration)return;
      editor.open=null;editor.notice='Oportunidad guardada.';show();
     }catch(error){
      if(['action_rejected','invalid_opportunity_name','invalid_opportunity_value','unknown_pipeline','unknown_stage','unknown_assignee','assignee_forbidden','invalid_status','nothing_to_change','action_forbidden'].includes(error.code))sessionStorage.removeItem(storageKey);
      const failed=panel.querySelector('[data-status]');
      if(failed)failed.textContent=friendly(error);
      const button=panel.querySelector('[data-save]');if(button)button.disabled=false;
     }
    };
    bindOpportunities=()=>{
     const add=panel.querySelector('[data-new-opportunity]');
     if(add)add.onclick=()=>openEditor('new');
     panel.querySelectorAll('[data-edit-opportunity]').forEach(button=>button.onclick=()=>openEditor(button.dataset.editOpportunity));
     const form=panel.querySelector('.opportunity-form');
     if(form) {
      if(editor.pipelines)bindPipelineChoice(form,editor.pipelines.pipelines);
      form.querySelector('[data-cancel]').onclick=()=>{editor.open=null;editor.notice='';show()};
      form.onsubmit=event=>{event.preventDefault();saveOpportunity(form)};
     }
    };
    bindActivity();
    // One read at a time: the account's budget is shared with the chat poll,
    // and racing for the same lock only turns into a queue with errors.
    (async()=>{
     if(!asList(data.fields).length){
      try{const fields=await request(`workspace/contact-fields?connectionId=${encodeURIComponent(select.value)}`);if(!active()||version!==chatGeneration)return;data.fields=fields.items;labels=Object.fromEntries(fields.items.map(field=>[field.id,field.name]));show()}catch{}
     }
     if(!active()||version!==chatGeneration)return;
     try{opportunities=await request(`workspace/opportunities?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`)}
     catch(error){opportunities={error:friendly(error)}}
     if(!active()||version!==chatGeneration)return;
     show();
     try{activity=await request(`workspace/activity?${query}`)}
     catch(error){activity={error:friendly(error)}}
     if(!active()||version!==chatGeneration)return;
     show();
    })();
   }
   catch(error){if(active()&&version===chatGeneration)panel.innerHTML=`<p class="empty">${esc(friendly(error))}</p>`}
  }
  function open(c){
   selected=c;chatNeedsRefresh=false;chatGeneration++;messages=new Map();cursor=null;chatChannel=sendChannels[c.lastMessageType]||null;chatChannels=null;list();layout.classList.add('chat-open');layout.classList.remove('contact-open');
   $('#live-messages').innerHTML=`<header class="live-chat-header"><button id="live-back" aria-label="Volver a conversaciones">${UI_ICONS.back}</button><span class="avatar">${esc(initials(c))}</span><div><h2>${esc(name(c))}</h2><p>${channel(c.lastMessageType)}</p></div><button id="live-focus" aria-label="Ampliar la conversación" title="Ampliar la conversación">${UI_ICONS.expand}</button><button id="live-contact-toggle" aria-label="Ver ficha del contacto" title="Ver ficha del contacto">${UI_ICONS.contact}</button></header><div class="live-history-actions"><button id="live-older" hidden>Cargar mensajes anteriores</button><p id="live-chat-status" role="status"></p><button id="live-retry" hidden>Reintentar</button></div><div id="live-log" class="messages live-message-log" aria-label="Historial de mensajes"></div>${composer(c)}`;
   $('#live-back').onclick=()=>{clearTimeout(pollTimer);document.body.classList.remove('agora-focus');layout.classList.remove('chat-open','contact-open');say();$('#live-search').focus()};
   $('#live-contact-toggle').onclick=()=>layout.classList.toggle('contact-open');
   if(Number(c.unreadCount)>0&&me?.csrf){
    request(`workspace/read?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`,'POST',me.csrf)
     .then(()=>{c.unreadCount=0;if(active())list();window.dispatchEvent(new CustomEvent('agora:unread',{detail:conversations.reduce((sum,item)=>sum+(Number(item.unreadCount)||0),0)}))})
     .catch(()=>{});
   }
   $('#live-older').onclick=()=>page(true);
   const focus=$('#live-focus');
   // Focus hides the menu and the list so the conversation owns the screen.
   const paintFocus=()=>{const on=document.body.classList.contains('agora-focus');focus.innerHTML=on?UI_ICONS.shrink:UI_ICONS.expand;focus.classList.toggle('active',on);focus.setAttribute('aria-label',on?'Reducir la conversación':'Ampliar la conversación');focus.title=focus.getAttribute('aria-label')};
   // Se recupera la preferencia al abrir una conversación, nunca antes.
   document.body.classList.toggle('agora-focus',focusWanted);
   focus.onclick=()=>{focusWanted=!document.body.classList.contains('agora-focus');document.body.classList.toggle('agora-focus',focusWanted);try{localStorage.setItem('agora-focus',focusWanted?'1':'0')}catch{}paintFocus()};
   paintFocus();
   bindComposer(c);const profileVersion=chatGeneration;page().finally(()=>{if(active()&&profileVersion===chatGeneration)loadContact(c,profileVersion)});
   say();scheduleChat();schedulePulse(2);
  }
  async function load(preserve=false){
   const version=++generation,connection=select.value;
   if(!preserve){clearTimeout(pollTimer);chatNeedsRefresh=false;chatGeneration++;selected=null;conversations=[];list();layout.classList.remove('chat-open');$('#live-messages').innerHTML='<p class="empty">Selecciona una conversación para consultar su historial.</p>';feedback.textContent='Cargando conversaciones…'}
   try{
    const data=await request(`workspace/inbox?${inboxQuery()}`);
    if(version!==generation||!active()||connection!==select.value)return false;
    const updated=selected?data.items.find(c=>c.id===selected.id):null;
    const changed=!!selected&&(!messages.size||!!updated&&[updated.lastMessageDate,updated.lastMessageBody,updated.unreadCount].join('|')!==[selected.lastMessageDate,selected.lastMessageBody,selected.unreadCount].join('|'));
    if(updated)for(const field of ['lastMessageDate','lastMessageBody','unreadCount'])selected[field]=updated[field];
    interval=Math.max(60,Number(data.syncIntervalSeconds)||120);
    pause=Math.max(pause,Number(data.state?.request_not_before)||0);
    conversations=data.items;limit.value=String(data.limit||20);
    nextCursor=data.nextCursor||null;hasMore=data.hasMore===true&&!!nextCursor;
    if(data.channels&&Object.keys(data.channels).length){channelNames=data.channels;paintChannelFilters()}
    list();
    // Una conversación pedida desde otra pantalla (la ficha de un contacto) se abre
    // aunque no esté entre las últimas de la lista.
    const wanted=workspace.pendingConversation;
    if(wanted&&!selected){
     workspace.pendingConversation=null;
     const known=conversations.find(c=>c.id===wanted.id);
     if(!known){conversations=[wanted,...conversations];list()}
     open(known||wanted);
    }
    window.dispatchEvent(new CustomEvent('agora:unread',{detail:Number(data.unread)||0}));
    const scope=[filter==='unread'?'no leídas':filter==='mine'?'mías':null,channelNames[channelFilter],searchTerm?`«${searchTerm}»`:null].filter(Boolean).join(' · ');
    feedback.textContent=data.items.length
     ? `${data.items.length} de ${data.total} conversaciones${scope?' · '+scope:''}${data.partial?' · ventana parcial':''}`
     : (scope?`Ninguna conversación con este filtro (${scope}).`:'Esta cuenta todavía no tiene conversaciones.');
    if(data.stale)feedback.textContent+=data.reason==='paused'?` · Mostrando la última lista, de ${date(data.syncedAt)}; la consulta está en espera.`:` · Mostrando la última lista, de ${date(data.syncedAt)}; no se pudo leer la cuenta ahora.`;
    return changed;
   }catch(e){if(e.status===401)container.agoraLiveDispose();if(version===generation&&active())feedback.textContent=friendly(e);return false}
  }
  $('#live-search').oninput=event=>{
   clearTimeout(searchTimer);
   const value=event.target.value.trim();
   // The account answers the search, so it is asked once typing settles.
   searchTimer=setTimeout(()=>{if(value===searchTerm)return;searchTerm=value;load()},450);
  };
  container.querySelectorAll('[data-live-filter]').forEach(b=>b.onclick=()=>{
   if(filter===b.dataset.liveFilter)return;
   filter=b.dataset.liveFilter;
   container.querySelectorAll('[data-live-filter]').forEach(n=>{n.classList.toggle('active',n===b);n.setAttribute('aria-pressed',String(n===b))});
   load();
  });
  // Un menú desplegable, no una fila de botones: ocupa una línea en el teléfono.
  function paintChannelFilters(){
   const row=$('#live-channel-filters');if(!row)return;
   const entries=Object.entries(channelNames);
   if(!entries.length){row.innerHTML='';return}
   row.innerHTML=`<select id="live-channel-select" aria-label="Filtrar por canal" class="${channelFilter?'filtered':''}"><option value="">Todos los canales</option>${entries.map(([type,label])=>`<option value="${esc(type)}"${channelFilter===type?' selected':''}>${esc(label)}</option>`).join('')}</select>`;
   $('#live-channel-select').onchange=e=>{
    const value=e.target.value;
    if(value===channelFilter)return;
    channelFilter=value;paintChannelFilters();load();
   };
  }

  async function sync(automatic=false){
   if(syncBusy)return;syncBusy=true;clearTimeout(timer);clearTimeout(chatTimer);
   const connection=select.value,requested=limit.value;limit.disabled=true;
   if(!automatic)feedback.textContent='Actualizando conversaciones…';
   let changed=false;
   try{await request(`workspace/sync?connectionId=${encodeURIComponent(connection)}&limit=${requested}`,'POST',me.csrf);if(active()&&connection===select.value)changed=await load(true);say();}
   catch(e){
    if(e.status===401){container.agoraLiveDispose();say('Inicia sesión para reanudar');}
    else {pause=Math.max(pause,Date.now()/1000+(e.retryAfter||interval));if(active()&&connection===select.value)changed=await load(true);if(active())say(e.retryAfter?'En espera unos segundos para respetar la cuota de la cuenta':friendly(e));}
    if(!automatic&&active())feedback.textContent=friendly(e);
   }finally{
    syncBusy=false;limit.disabled=false;
    chatNeedsRefresh=chatNeedsRefresh||changed;
    if(canRefresh()&&chatNeedsRefresh&&selected&&!messageBusy&&pause<=Date.now()/1000+1){const version=chatGeneration;chatTimer=setTimeout(()=>{if(canRefresh()&&version===chatGeneration&&!messageBusy)page(false,true)},1200);}
    schedule(Math.max(interval,pause-Date.now()/1000));
   }
  }
  limit.onchange=()=>sync();
  // A first visit with nothing loaded yet refreshes itself instead of asking.
  await load();if(!conversations.length)sync(true);schedule(interval);schedulePulse(2);

 }catch(e){if(!active())return;container.innerHTML=`<h2>Conversaciones</h2><p class="settings-notice">${esc(friendly(e))} Inicia sesión con tu cuenta de equipo.</p><button id="live-login" class="primary">Iniciar sesión</button>`;container.querySelector('#live-login').onclick=()=>document.querySelector('#connect').click()}
}
