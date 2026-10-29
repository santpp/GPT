import {workspace} from './workspace.js';
const drafts=new Map();
window.addEventListener('agora:logout',()=>{drafts.clear();for(const key of Object.keys(sessionStorage))if(key.startsWith('agora-send:'))sessionStorage.removeItem(key)});
const sendChannels={TYPE_SMS:'SMS',TYPE_WHATSAPP:'WhatsApp',TYPE_INSTAGRAM:'IG',TYPE_FACEBOOK:'FB',TYPE_EMAIL:'Email'};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const channel=value=>({TYPE_WHATSAPP:'WhatsApp',TYPE_INSTAGRAM:'Instagram',TYPE_SMS:'SMS',TYPE_EMAIL:'Email',TYPE_FACEBOOK:'Messenger',TYPE_CALL:'Llamada',TYPE_LIVE_CHAT:'Chat web',TYPE_INTERNAL_COMMENTS:'Nota interna'}[value]||'Conversación');
const name=c=>c.contactName||c.fullName||[c.firstName,c.lastName].filter(Boolean).join(' ')||'Contacto sin nombre';
const initials=c=>name(c).split(/\s+/).slice(0,2).map(s=>[...s][0]).join('').toUpperCase();
const date=value=>{if(!value)return '';const d=new Date(typeof value==='string'&&/^\d{4}-\d\d-\d\d \d\d:/.test(value)?value.replace(' ','T')+'Z':value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'short',timeStyle:'short'}).format(d)};
const status=value=>({delivered:'Entregado',sent:'Enviado',read:'Leído',pending:'Pendiente',failed:'Fallido',received:'Recibido'}[value]||'');
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
  container.innerHTML=`<div class="live-toolbar"><label>Mostrar<select id="live-limit" aria-label="Cantidad de conversaciones"><option value="20">20</option><option value="50">50</option><option value="100">100</option></select></label></div><p id="live-auto-status" class="live-auto-status" role="status">Actualización automática al mantener abierta esta pantalla</p><p id="live-feedback" class="live-feedback" role="status"></p><div class="live-inbox-layout"><section class="live-sidebar" aria-label="Lista de conversaciones"><label class="search"><span aria-hidden="true">⌕</span><input id="live-search" type="search" placeholder="Buscar nombre o mensaje" aria-label="Buscar en conversaciones cargadas"></label><div class="tabs"><button data-live-filter="all" class="active" aria-pressed="true">Todas</button><button data-live-filter="unread" aria-pressed="false">Pendientes</button><span id="live-count"></span></div><p class="live-search-hint">Búsqueda en las conversaciones cargadas</p><div id="live-list"></div></section><section id="live-messages" aria-label="Conversación"><p class="empty">Selecciona una conversación para consultar su historial.</p></section><aside id="live-contact-panel" class="live-contact-panel" aria-label="Ficha del contacto"><p class="empty">Selecciona una conversación.</p></aside></div>`;
  const $=s=>container.querySelector(s), limit=$('#live-limit'),feedback=$('#live-feedback'),layout=$('.live-inbox-layout');
  // The chosen account is read from the header on every use, so switching there
  // takes effect without this screen owning a control of its own.
  const select={get value(){return workspace.current()},set disabled(v){}};
  if(!items.length){feedback.textContent='Añade una cuenta desde Conexiones para empezar.';return}
  let disposed=false,timer=null,chatTimer=null,pollTimer=null,interval=120,pause=0,syncBusy=false,messageBusy=0,chatNeedsRefresh=false;
  const canRefresh=()=>active()&&!disposed&&!document.hidden&&navigator.onLine;
  const autoStatus=$('#live-auto-status');
  function schedule(seconds=interval){clearTimeout(timer);if(!canRefresh())return;timer=setTimeout(tick,Math.max(1,seconds)*1000)}
  function scheduleChat(seconds=10){clearTimeout(pollTimer);if(!canRefresh()||!selected||!layout.classList.contains('chat-open'))return;pollTimer=setTimeout(pollChat,Math.max(2,seconds)*1000)}
  async function pollChat(){
   if(!canRefresh()||!selected)return;
   if(syncBusy||messageBusy||drafts.get(select.value+':'+selected.id)?.sending){scheduleChat(3);return}
   await page(false,true);
  }
  function wake(){clearTimeout(timer);clearTimeout(chatTimer);clearTimeout(pollTimer);if(canRefresh()){autoStatus.textContent=selected?'Chat activo · actualización frecuente':'Actualización automática activa';schedule(Math.max(1,pause-Date.now()/1000));scheduleChat(1)}else autoStatus.textContent=navigator.onLine?'Actualización pausada mientras la pantalla está oculta':'Sin conexión · Se reanudará automáticamente'}
  container.agoraLiveDispose=()=>{disposed=true;clearTimeout(timer);clearTimeout(chatTimer);clearTimeout(pollTimer);document.removeEventListener('visibilitychange',wake);window.removeEventListener('online',wake);window.removeEventListener('offline',wake)};
  document.addEventListener('visibilitychange',wake);window.addEventListener('online',wake);window.addEventListener('offline',wake);
  async function tick(){if(!canRefresh())return;if(syncBusy||messageBusy||[...drafts.values()].some(d=>d.sending)){schedule(5);return}if(pause>Date.now()/1000){schedule(pause-Date.now()/1000);return}await sync(true)}
  let generation=0, chatGeneration=0, conversations=[], selected=null, filter='all', messages=new Map(), cursor=null, chatChannel=null, chatChannels=null;
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
    if(refresh)autoStatus.textContent='Chat activo · actualizado automáticamente';
    log.scrollTop=older?top+log.scrollHeight-height:refresh&&height-top-log.clientHeight>60?top:log.scrollHeight;
   }catch(e){if(e.retryAfter)pause=Math.max(pause,Date.now()/1000+e.retryAfter);if(e.status===401)container.agoraLiveDispose();if(active()&&current===chatGeneration){if(!refresh||!messages.size){$('#live-chat-status').textContent=e.message;retry.hidden=false;retry.onclick=()=>page(older)}else autoStatus.textContent='Chat activo · reintentando actualización'}}
   finally{messageBusy--;if(active()&&current===chatGeneration){more.disabled=false;if(refresh)scheduleChat(effectiveChatDelay())}}
  }
  function effectiveChatDelay(){return Math.max(10,pause-Date.now()/1000)}
  const channelMarks={SMS:'✉',WhatsApp:'◉',IG:'◌',FB:'◍',Email:'✎'};
  const threadChannel=c=>sendChannels[c.lastMessageType]
    ? [{type:sendChannels[c.lastMessageType],label:channel(c.lastMessageType),needsSubject:sendChannels[c.lastMessageType]==='Email'}] : [];
  const pickerMarkup=offered=>offered.length>1
    ? offered.map(o=>`<button type="button" data-channel="${esc(o.type)}" class="${o.type===chatChannel?'selected':''}" aria-pressed="${o.type===chatChannel}"><span aria-hidden="true">${channelMarks[o.type]||'◦'}</span>${esc(o.label)}</button>`).join('')
    : `<p class="live-channel-single">${esc(offered[0]?.label??'')} · Texto</p>`;
  function composer(c,channels=null){
   const offered=channels??threadChannel(c);
   if(!offered.length||!c.contactId)return '<p class="live-readonly">Este canal todavía no admite respuestas desde aquí.</p>';
   const chosen=offered.find(o=>o.type===chatChannel)||offered[0];
   chatChannel=chosen.type;
   return `<form id="live-composer" class="live-composer"><div class="live-channels" id="live-channel-row" role="group" aria-label="Canal de envío">${pickerMarkup(offered)}</div><div id="live-subject-slot">${chosen.needsSubject?'<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>':''}</div><label class="sr-only" for="live-reply">Respuesta</label><textarea id="live-reply" maxlength="4000" rows="3" required placeholder="Escribe una respuesta…"></textarea><div><span id="live-channel-name">${esc(chosen.label)}</span><button id="live-send" class="primary">Enviar</button></div><p id="live-send-status" role="status"></p></form>`;
  }

  /**
   * Updates only the channel row and the subject slot. The textarea is never
   * replaced, so what is being written, the caret and an in-flight send survive.
   */
  function applyChannels(c,offered,draft){
   const row=$('#live-channel-row');if(!row)return;
   const chosen=offered.find(o=>o.type===chatChannel)||offered[0];
   if(!chosen)return;
   chatChannel=chosen.type;
   row.innerHTML=pickerMarkup(offered);
   row.classList.toggle('live-channels-single',offered.length<2);
   const name=$('#live-channel-name');if(name)name.textContent=chosen.label;
   const slot=$('#live-subject-slot');
   if(slot){
    const present=!!$('#live-subject');
    if(chosen.needsSubject&&!present)slot.innerHTML='<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>';
    if(!chosen.needsSubject&&present)slot.innerHTML='';
    const topic=$('#live-subject');
    if(topic){topic.value=draft.subject;topic.disabled=draft.sending;topic.oninput=()=>draft.subject=topic.value}
   }
   row.querySelectorAll('[data-channel]').forEach(button=>button.onclick=()=>{
    if(draft.sending||button.dataset.channel===chatChannel)return;
    chatChannel=button.dataset.channel;draft.notice='';
    applyChannels(c,offered,draft);
    const notice=$('#live-send-status');if(notice)notice.textContent='';
   });
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
    }catch(error){draft.notice=error.message;if(storageKey&&['send_rejected','invalid_message','invalid_subject','unsupported_channel','invalid_contact','connection_unverified'].includes(error.code))sessionStorage.removeItem(storageKey)}
    finally{draft.sending=false;if(active()&&version===chatGeneration)show()}
   };
  }
  const amount=value=>typeof value==='number'&&isFinite(value)?new Intl.NumberFormat('es-ES',{maximumFractionDigits:2,useGrouping:'always'}).format(value):null;
  function opportunityCard(item){
   const rows=[['Pipeline',item.pipelineName],['Etapa',item.stageName],['Estado',item.statusLabel],['Valor',amount(item.monetaryValue)],['Responsable',item.assignedName],['Fuente',item.source]].filter(([,value])=>value);
   return `<div class="live-opportunity"><h3>${esc(item.name||'Oportunidad sin nombre')}</h3>${rows.map(([label,value])=>`<div><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}</div>`;
  }
  function opportunitySection(opportunities){
   if(!opportunities)return '<span class="muted">Cargando oportunidades…</span>';
   if(opportunities.error)return `<span class="muted">${esc(opportunities.error)}</span>`;
   const items=asList(opportunities.items);
   if(!items.length||!opportunities.active)return `<span class="muted">${opportunities.restricted?'Sin oportunidades asignadas a tu usuario.':'Este contacto no tiene oportunidades.'}</span>`;
   const others=items.filter(item=>item.id!==opportunities.active.id);
   return opportunityCard(opportunities.active)
    +(others.length?`<details class="live-opportunity-all"><summary>Ver todas las oportunidades (${items.length})</summary>${others.map(opportunityCard).join('')}</details>`:'')
    +(opportunities.partial?'<span class="muted">Se muestran las oportunidades más recientes de este contacto.</span>':'');
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
  const EDITABLE=[['name','Nombre'],['firstName','Nombre de pila'],['lastName','Apellido'],['email','Correo'],['phone','Teléfono'],['companyName','Empresa'],['address1','Dirección'],['city','Ciudad'],['state','Provincia'],['postalCode','Código postal'],['country','País'],['website','Sitio web'],['source','Fuente'],['timezone','Zona horaria']];
  function contactForm(profile,labels={}){
   const fields=EDITABLE.map(([key,label])=>`<label>${esc(label)}<input name="${key}" value="${esc(profile[key]??'')}" maxlength="250"></label>`).join('');
   const custom=asList(profile.customFields).map(field=>`<label>${esc(labels[field.id]||field.label||'Campo personalizado')}<input data-field="${esc(field.id)}" value="${esc(field.value??'')}" maxlength="1000"></label>`).join('');
   const tags=asList(profile.tags).map(tag=>`<span class="tag">${esc(tag)}<button type="button" data-drop-tag="${esc(tag)}" aria-label="Quitar etiqueta ${esc(tag)}">×</button></span>`).join('')||'<span class="muted">Sin etiquetas</span>';
   return `<form id="live-contact-form" class="live-contact-form"><header class="live-profile-header"><div><span class="section-label">EDITAR FICHA</span><h2>${esc(profile.name)}</h2></div><button type="button" id="live-edit-cancel" aria-label="Cancelar edición">×</button></header>${fields}${custom?`<div class="section-label">CAMPOS PERSONALIZADOS</div>${custom}`:''}<div class="section-label">ETIQUETAS</div><div class="live-profile-tags" id="live-tag-list">${tags}</div><label>Añadir etiqueta<input id="live-new-tag" maxlength="100" placeholder="Escribe y pulsa Añadir"></label><button type="button" id="live-add-tag">Añadir etiqueta</button><button class="primary" id="live-save-contact">Guardar ficha</button><p id="live-contact-status" role="status"></p></form>`;
  }
  function contactProfile(profile,labels={},opportunities=null,activity=null){
   const details=[['Teléfono',profile.phone],['Email',profile.email],['Empresa',profile.companyName],['Fuente',profile.source],['Responsable',profile.assignedName],['Última actividad',date(profile.lastActivity)],['Zona horaria',profile.timezone],['Dirección',profile.address]].filter(([,value])=>value);
   const phone=String(profile.phone||'').replace(/[^0-9+]/g,'');
   return `<header class="live-profile-header"><div><span class="section-label">CONTEXTO DEL CONTACTO</span><h2>${esc(profile.name)}</h2></div><div class="live-profile-controls">${profile.canEdit?'<button id="live-edit-contact">Editar</button>':''}<button id="live-profile-close" aria-label="Cerrar ficha">×</button></div></header>${phone||profile.email?`<div class="live-profile-actions">${phone?`<a href="tel:${esc(phone)}">Llamar</a>`:''}${profile.email?`<a href="mailto:${encodeURIComponent(profile.email)}">Email</a>`:''}</div>`:''}<div class="live-profile-details">${details.map(([label,value])=>`<div><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}</div><div class="section-label">ETIQUETAS</div><div class="live-profile-tags">${profile.tags?.length?profile.tags.map(tag=>`<span class="tag">${esc(tag)}</span>`).join(''):'<span class="muted">Sin etiquetas</span>'}</div><div class="section-label">CAMPOS PERSONALIZADOS</div><div class="live-profile-fields">${profile.customFields?.length?profile.customFields.map(field=>`<div><span>${esc(labels[field.id]||field.label)}</span><strong>${esc(field.value)}</strong></div>`).join(''):'<span class="muted">Sin campos con valor</span>'}</div><div class="section-label">OPORTUNIDAD ACTIVA</div><div class="live-profile-opportunities">${opportunitySection(opportunities)}</div><div class="live-profile-activity">${activitySection(activity)}</div>`;
  }
  async function loadContact(c,version){
   const panel=$('#live-contact-panel');panel.innerHTML='<p class="empty">Cargando ficha…</p>';
   try{
    const data=await request(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`);if(!active()||version!==chatGeneration)return;
    if(Array.isArray(data.channels)&&data.channels.length){
     chatChannels=data.channels;
     const draft=drafts.get(select.value+':'+c.id);
     if(draft)applyChannels(c,chatChannels,draft);
    }
    let bindActivity=()=>{};
    const bind=()=>{
     $('#live-profile-close').onclick=()=>layout.classList.remove('contact-open');
     const edit=$('#live-edit-contact');
     if(edit)edit.onclick=()=>{editing=true;show()};
     bindActivity();
    };
    let labels={},opportunities=null,activity=null,editing=false;
    data.contact.canEdit=data.canEdit===true;
    const show=()=>{
     if(editing){panel.innerHTML=contactForm(data.contact,labels);bindEdit();return}
     panel.innerHTML=contactProfile(data.contact,labels,opportunities,activity);bind();
    };
    let added=[],dropped=[];
    function bindEdit(){
     const form=panel.querySelector('#live-contact-form');if(!form)return;
     const status=panel.querySelector('#live-contact-status');
     panel.querySelector('#live-edit-cancel').onclick=()=>{editing=false;added=[];dropped=[];show()};
     const newTag=panel.querySelector('#live-new-tag');
     const addTag=()=>{
      const value=newTag.value.trim();if(!value)return;
      if(!added.includes(value))added.push(value);
      dropped=dropped.filter(t=>t!==value);
      newTag.value='';
      const list=panel.querySelector('#live-tag-list');
      list.insertAdjacentHTML('beforeend',`<span class="tag">${esc(value)}<button type="button" data-drop-tag="${esc(value)}" aria-label="Quitar etiqueta ${esc(value)}">×</button></span>`);
      bindTagButtons();
     };
     panel.querySelector('#live-add-tag').onclick=addTag;
     newTag.onkeydown=event=>{if(event.key==='Enter'){event.preventDefault();addTag()}};
     function bindTagButtons(){
      panel.querySelectorAll('[data-drop-tag]').forEach(button=>button.onclick=()=>{
       const tag=button.dataset.dropTag;
       added=added.filter(t=>t!==tag);
       if((data.contact.tags||[]).includes(tag)&&!dropped.includes(tag))dropped.push(tag);
       button.parentElement.remove();
      });
     }
     bindTagButtons();
     form.onsubmit=async event=>{
      event.preventDefault();
      const save=panel.querySelector('#live-save-contact');save.disabled=true;status.textContent='Guardando ficha…';
      const fields={};
      for(const [key] of EDITABLE){
       const input=form.querySelector(`[name="${key}"]`);
       if(input&&input.value.trim()!==String(data.contact[key]??''))fields[key]=input.value.trim();
      }
      const customFields=[...form.querySelectorAll('[data-field]')]
       .map(input=>({id:input.dataset.field,value:input.value}))
       .filter(field=>field.value!==String((data.contact.customFields||[]).find(f=>f.id===field.id)?.value??''));
      if(customFields.length)fields.customFields=customFields;
      const payload={fields,addTags:added,removeTags:dropped,requestId:crypto.randomUUID().replace(/-/g,'')};
      try{
       await request(`workspace/activity/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`,'POST',me.csrf,payload);
       const fresh=await request(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}&refresh=1`);
       if(!active()||version!==chatGeneration)return;
       data.contact=fresh.contact;data.contact.canEdit=fresh.canEdit===true;
       editing=false;added=[];dropped=[];show();
      }catch(error){
       const failed=panel.querySelector('#live-contact-status');
       if(failed)failed.textContent=error.message;
       const button=panel.querySelector('#live-save-contact');if(button)button.disabled=false;
      }
     };
    }
    show();
    // Each extra call waits its turn so the shared per-account budget is respected.


    const query=`connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`;
    const reload=async()=>{
     try{const result=await request(`workspace/activity?${query}&refresh=1`);if(!active()||version!==chatGeneration)return;activity=result}
     catch(error){if(!active()||version!==chatGeneration)return;activity={error:error.message}}
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
      const failed=$('#live-activity-status');if(failed)failed.textContent=error.message;
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
    bindActivity();
    // One read at a time: the account's budget is shared with the chat poll,
    // and racing for the same lock only turns into a queue with errors.
    (async()=>{
     try{const fields=await request(`workspace/contact-fields?connectionId=${encodeURIComponent(select.value)}`);if(!active()||version!==chatGeneration)return;labels=Object.fromEntries(fields.items.map(field=>[field.id,field.name]));show()}catch{}
     if(!active()||version!==chatGeneration)return;
     try{opportunities=await request(`workspace/opportunities?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`)}
     catch(error){opportunities={error:error.message}}
     if(!active()||version!==chatGeneration)return;
     show();
     try{activity=await request(`workspace/activity?${query}`)}
     catch(error){activity={error:error.message}}
     if(!active()||version!==chatGeneration)return;
     show();
    })();
   }
   catch(error){if(active()&&version===chatGeneration)panel.innerHTML=`<p class="empty">${esc(error.message)}</p>`}
  }
  function open(c){
   selected=c;chatNeedsRefresh=false;chatGeneration++;messages=new Map();cursor=null;chatChannel=sendChannels[c.lastMessageType]||null;chatChannels=null;list();layout.classList.add('chat-open');layout.classList.remove('contact-open');
   $('#live-messages').innerHTML=`<header class="live-chat-header"><button id="live-back" aria-label="Volver a conversaciones">←</button><span class="avatar">${esc(initials(c))}</span><div><h2>${esc(name(c))}</h2><p>${channel(c.lastMessageType)}</p></div><button id="live-contact-toggle" aria-label="Ver ficha del contacto">ⓘ</button></header><div class="live-history-actions"><button id="live-older" hidden>Cargar mensajes anteriores</button><p id="live-chat-status" role="status"></p><button id="live-retry" hidden>Reintentar</button></div><div id="live-log" class="messages live-message-log" aria-label="Historial de mensajes"></div>${composer(c)}`;
   $('#live-back').onclick=()=>{clearTimeout(pollTimer);layout.classList.remove('chat-open','contact-open');autoStatus.textContent='Actualización automática activa';$('#live-search').focus()};
   $('#live-contact-toggle').onclick=()=>layout.classList.toggle('contact-open');
   $('#live-older').onclick=()=>page(true);bindComposer(c);const profileVersion=chatGeneration;page().finally(()=>{if(active()&&profileVersion===chatGeneration)loadContact(c,profileVersion)});
   autoStatus.textContent='Chat activo · actualización cada 10 segundos';scheduleChat();
  }
  async function load(preserve=false){
   const version=++generation,connection=select.value;
   if(!preserve){clearTimeout(pollTimer);chatNeedsRefresh=false;chatGeneration++;selected=null;conversations=[];list();layout.classList.remove('chat-open');$('#live-messages').innerHTML='<p class="empty">Selecciona una conversación para consultar su historial.</p>';feedback.textContent='Cargando conversaciones…'}
   try{
    const data=await request(`workspace/inbox?connectionId=${encodeURIComponent(select.value)}`);
    if(version!==generation||!active()||connection!==select.value)return false;
    const updated=selected?data.items.find(c=>c.id===selected.id):null;
    const changed=!!selected&&(!messages.size||!!updated&&[updated.lastMessageDate,updated.lastMessageBody,updated.unreadCount].join('|')!==[selected.lastMessageDate,selected.lastMessageBody,selected.unreadCount].join('|'));
    if(updated)for(const field of ['lastMessageDate','lastMessageBody','unreadCount'])selected[field]=updated[field];
    interval=Math.max(60,Number(data.syncIntervalSeconds)||120);
    pause=Math.max(pause,Number(data.state?.request_not_before)||0);
    conversations=data.items;limit.value=String(data.limit||20);list();
    feedback.textContent=data.syncedAt?`Mostrando ${data.items.length} de ${data.total} conversaciones · Actualizado ${date(data.syncedAt)}${data.partial?' · Vista parcial':''}`:'Todavía no hay conversaciones cargadas. Se están pidiendo a la cuenta…';
    if(data.state?.last_error)feedback.textContent+=' No se pudo completar la última sincronización. Revisa Conexiones.';
    return changed;
   }catch(e){if(e.status===401)container.agoraLiveDispose();if(version===generation&&active())feedback.textContent=e.message;return false}
  }
  $('#live-search').oninput=list;
  container.querySelectorAll('[data-live-filter]').forEach(b=>b.onclick=()=>{filter=b.dataset.liveFilter;container.querySelectorAll('[data-live-filter]').forEach(n=>{n.classList.toggle('active',n===b);n.setAttribute('aria-pressed',String(n===b))});list()});

  async function sync(automatic=false){
   if(syncBusy)return;syncBusy=true;clearTimeout(timer);clearTimeout(chatTimer);
   const connection=select.value,requested=limit.value;limit.disabled=true;
   if(!automatic)feedback.textContent='Actualizando conversaciones…';
   let changed=false;
   try{await request(`workspace/sync?connectionId=${encodeURIComponent(connection)}&limit=${requested}`,'POST',me.csrf);if(active()&&connection===select.value)changed=await load(true);autoStatus.textContent='Actualización automática activa';}
   catch(e){
    if(e.status===401){container.agoraLiveDispose();autoStatus.textContent='Inicia sesión para reanudar';}
    else {pause=Math.max(pause,Date.now()/1000+(e.retryAfter||interval));if(active()&&connection===select.value)changed=await load(true);if(active())autoStatus.textContent=e.retryAfter?'Actualización automática · En espera para respetar el intervalo':e.message;}
    if(!automatic&&active())feedback.textContent=e.message;
   }finally{
    syncBusy=false;limit.disabled=false;
    chatNeedsRefresh=chatNeedsRefresh||changed;
    if(canRefresh()&&chatNeedsRefresh&&selected&&!messageBusy&&pause<=Date.now()/1000+1){const version=chatGeneration;chatTimer=setTimeout(()=>{if(canRefresh()&&version===chatGeneration&&!messageBusy)page(false,true)},1200);}
    schedule(Math.max(interval,pause-Date.now()/1000));
   }
  }
  limit.onchange=()=>sync();
  // A first visit with nothing loaded yet refreshes itself instead of asking.
  await load();if(!conversations.length)sync(true);schedule(interval);

 }catch(e){if(!active())return;container.innerHTML=`<h2>Conversaciones</h2><p class="settings-notice">${esc(e.message)} Inicia sesión con tu cuenta de equipo.</p><button id="live-login" class="primary">Iniciar sesión</button>`;container.querySelector('#live-login').onclick=()=>document.querySelector('#connect').click()}
}
