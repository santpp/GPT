const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function request(path,method='GET',csrf=''){
 const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
 let data;try{data=await r.json()}catch{throw Error('El backend no responde.')}
 if(!r.ok)throw Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:''));return data;
}
export async function renderLiveInbox(container){
 const instance=Symbol();container.agoraLiveInstance=instance;const active=()=>container.agoraLiveInstance===instance&&container.dataset.view==='live';
 container.innerHTML='<p class="empty">Cargando conexiones…</p>';
 try{
  const me=await request('me');const {items}=await request('ghl/connections');if(!active())return;
  container.innerHTML=`<div class="integration-heading"><div><span class="eyebrow">DATOS DE TU SUBCUENTA</span><h2 style="margin-top:8px">Inbox conectado · lectura</h2></div><button id="sync-live" class="primary">Sincronizar ahora</button></div><p class="settings-notice">Últimas 20 conversaciones por subcuenta. La lectura no marca mensajes como leídos. Envío, historial completo y push todavía pendientes. No se muestran ejemplos en esta vista.</p><label class="dialog-form">Subcuenta<select id="live-connection">${items.map(c=>`<option value="${c.id}">${esc(c.name)} · ${esc(c.location_id)}</option>`).join('')}</select></label><p id="live-feedback" class="dialog-copy" role="status"></p><div class="live-inbox-layout"><div id="live-list"></div><section id="live-messages"><p class="empty">Selecciona una conversación para leer sus mensajes.</p></section></div>`;
  const select=container.querySelector('#live-connection'),feedback=container.querySelector('#live-feedback'),button=container.querySelector('#sync-live');
  if(!items.length){feedback.textContent='Configura una subcuenta en Integraciones para empezar.';button.disabled=true;return}
  let generation=0;
  async function load(){
   const version=++generation;const connection=select.value;
   try{
    const data=await request(`ghl/inbox?connectionId=${encodeURIComponent(connection)}`);
    if(version!==generation||!active())return;
    feedback.textContent=data.syncedAt?`Última sincronización (UTC): ${data.syncedAt}. Mostrando ${data.items.length} de ${data.total}. ${data.partial?'Vista parcial: el resto del inbox aún no se ha importado.':''}`:'Aún no hay datos importados. Comprueba la conexión y pulsa Sincronizar ahora.';
    if(data.state?.last_error)feedback.textContent+=' La última consulta requiere revisión: '+data.state.last_error;
    container.querySelector('#live-list').innerHTML=data.items.map(c=>`<button class="card live-conversation" data-live-id="${esc(c.id)}"><h3>${esc(c.contactName||c.fullName||[c.firstName,c.lastName].filter(Boolean).join(' ')||c.contactId||c.id)}</h3><p>${esc(c.lastMessageBody||'Sin vista previa')}</p><small>${esc(c.lastMessageType||'Conversación')} · ${Number(c.unreadCount)||0} pendientes</small></button>`).join('')||'<p class="empty">No hay conversaciones en esta instantánea.</p>';
    container.querySelector('#live-messages').innerHTML='<p class="empty">Selecciona una conversación.</p>';
    container.querySelectorAll('[data-live-id]').forEach(b=>b.onclick=async()=>{
     const current=++generation;const messages=container.querySelector('#live-messages');messages.innerHTML='<p class="empty">Leyendo mensajes…</p>';
     try{
      const r=await request(`ghl/messages?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(b.dataset.liveId)}`);
      if(current!==generation||!active())return;
      messages.innerHTML=`<h2>Mensajes recientes · sólo lectura</h2>${r.hasMore?'<p class="dialog-copy">Hay historial anterior; esta versión muestra hasta 20 mensajes.</p>':''}<div class="messages live-message-log">${[...r.items].sort((a,b)=>Date.parse(a.dateAdded)-Date.parse(b.dateAdded)).map(m=>`<div class="bubble ${m.direction==='outbound'?'out':''}">${esc(m.body||'(Mensaje sin texto)')}<time>${esc(m.dateAdded)} · ${esc(m.status)}</time></div>`).join('')||'<p class="empty">No hay mensajes disponibles.</p>'}</div>`;
     }catch(e){if(current===generation&&active())messages.innerHTML=`<p class="empty">${esc(e.message)}</p>`}
    });
   }catch(e){if(version===generation&&active())feedback.textContent=e.message}
  }
  select.onchange=load;
  button.onclick=async()=>{button.disabled=true;feedback.textContent='Consultando GoHighLevel…';try{await request(`admin/integrations/${select.value}/sync`,'POST',me.csrf);await load()}catch(e){feedback.textContent=e.message}finally{button.disabled=false}};
  await load();
 }catch(e){if(!active())return;container.innerHTML=`<h2>Inbox conectado</h2><p class="settings-notice">${esc(e.message)} Inicia sesión como administrador para usar esta primera versión.</p>`}
}
