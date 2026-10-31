import {friendly} from './workspace.js';
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const accessPanel=(items,channel)=>`<section class="settings-notice access-panel"><h3>Canal de acceso del equipo</h3><p>Ágora entrega los códigos temporales de acceso usando el correo de una de tus cuentas conectadas. Elige cuál se encarga. Tu acceso de administrador con contraseña funciona siempre, incluso sin canal.</p><label>Cuenta encargada<select id="access-channel-select"><option value="">Sin asignar</option>${items.map(i=>`<option value="${i.id}"${Number(channel.connectionId)===Number(i.id)?' selected':''}${i.status==='connected'?'':' disabled'}>${esc(i.name)}${i.status==='connected'?'':' · comprueba la conexión primero'}</option>`).join('')}</select></label><p class="access-state">${channel.connectionId?(channel.ready?'Listo para entregar códigos de acceso.':'La cuenta elegida necesita una comprobación correcta antes de entregar códigos.'):'Sin canal asignado: los integrantes del equipo no podrán recibir códigos.'}</p></section>`;
const statusNames={untested:'Pendiente de comprobar',connected:'Conexión comprobada',error:'Revisar conexión'};
let csrf='';
async function api(path,method='GET',data){
 const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(method==='GET'?{}:{'X-CSRF-Token':csrf})},...(data?{body:JSON.stringify(data)}:{})});
 let b;try{b=await r.json()}catch{throw Error('El backend no está disponible.')}
 if(!r.ok){const e=Error(b.error||'No se ha podido completar la operación.');e.status=r.status;e.code=b.code;e.reference=b.reference;throw e}return b;
}
/**
 * Which devices will be told about a new message, and when each was last told.
 * It is the quickest way to tell a delivery problem from a sending one.
 */
// El resultado del último aviso de prueba sobrevive al redibujado del panel:
// si no, se borraba en el mismo instante en que aparecía.
let lastTestResult='';
// La dirección de revisión se enseña una sola vez; sobrevive al redibujado.
let freshCronUrl='',freshCron=null,askCronPassword=false;
async function devicePanel(container){
 const panel=container.querySelector('#device-panel');if(!panel)return;
 let data;
 try{data=await api('devices')}catch{panel.innerHTML='';return}
 const names={web:'Navegador o aplicación instalada',android:'Aplicación Android'};
 const when=value=>{if(!value)return null;const d=new Date(String(value).includes('T')?value:String(value).replace(' ','T')+'Z');return Number.isNaN(d.getTime())?String(value):d.toLocaleString('es-ES',{dateStyle:'short',timeStyle:'short'})};
 const ago=value=>{if(!value)return null;const d=new Date(value);const s=Math.round((Date.now()-d.getTime())/1000);return Number.isNaN(s)?null:s<90?'hace un momento':s<3600?`hace ${Math.round(s/60)} min`:`hace ${Math.round(s/3600)} h`};
 const ready=[data.browser?'navegador':null,data.android?'Android':null].filter(Boolean).join(' y ');
 // Cada eslabón de la cadena, en el orden en que viaja un aviso.
 const links=[];
 const discovered=data.discovery?.at;
 const recent=at=>at&&Date.now()-new Date(at).getTime()<5*60000;
 // Un solo cron revisa y entrega: si no corre, no hay avisos con la app cerrada.
 const via=data.discovery?.via==='url'?' (desde la dirección de revisión)':data.discovery?.via==='cron'?' (desde el servidor)':'';
 links.push(recent(discovered)
   ? ['ok',`Revisión de mensajes nuevos: activa, ${ago(discovered)}${via}.`]
   : ['bad',discovered?`Revisión de mensajes nuevos: la última fue ${ago(discovered)}. El cron de revisión ha dejado de ejecutarse en el servidor.`:'Revisión de mensajes nuevos: el cron de revisión no se está ejecutando en el servidor. Sin él no hay avisos con la app cerrada.']);
 const delivered=data.delivery;
 if(delivered?.configured===false)links.push(['bad','Entrega de avisos: no encuentra las claves de avisos. Revisa la línea de configuración y que la carpeta privada sea legible por el cron.']);
 else if(delivered?.error)links.push(['bad',`Entrega de avisos: la última pasada falló (${delivered.error}).`]);
 else links.push(recent(delivered?.at)
   ? ['ok',`Entrega de avisos: activa, ${ago(delivered.at)}${delivered.sent?` · ${delivered.sent} enviados en la última pasada`:''}.`]
   : ['bad',delivered?.at?`Entrega de avisos: la última fue ${ago(delivered.at)}. La hace el cron de revisión: revisa que siga ejecutándose.`:'Entrega de avisos: todavía no ha corrido. La hace el cron de revisión, cada minuto.']);
 const q=data.queue||{};
 links.push([q.failed?'bad':'ok',`Últimos 7 días: ${Number(q.sent)||0} avisos entregados, ${Number(q.pending)||0} pendientes, ${Number(q.failed)||0} fallidos.${q.lastError?` Último error: ${q.lastError}.`:''}`]);
 panel.innerHTML=`<div class="section-label">AVISOS EN DISPOSITIVOS</div><p class="settings-notice">${ready
   ? `Preparado para avisar por ${esc(ready)}. Cada persona activa los avisos desde su propio dispositivo.`
   : 'Todavía no hay avisos preparados en el servidor. Revisa la configuración privada antes de activarlos en un dispositivo.'}</p>
  <ul class="push-chain">${links.map(([kind,text])=>`<li class="${kind}">${esc(text)}</li>`).join('')}</ul>
  ${externalCheck(data,recent(discovered))}
  <button id="push-test" class="primary">Enviar un aviso de prueba a mis dispositivos</button>
  <p id="push-test-result" class="dialog-copy" role="status">${esc(lastTestResult)}</p>
  <div class="device-list">${
  (data.items||[]).map(d=>`<article class="card"><h3>${esc(d.label||names[d.platform]||'Dispositivo')}</h3><small>${esc(names[d.platform]||d.platform)}<br>Registrado: ${esc(when(d.created_at)||'—')}<br>Visto: ${esc(when(d.last_seen_at)||'—')}<br>Último aviso enviado: ${esc(when(d.last_sent_at)||'Ninguno todavía')}</small></article>`).join('')
  ||'<p class="empty">Ningún dispositivo ha activado los avisos todavía.</p>'}</div>`;
 bindExternalCheck(container,panel);
 const RESULT={rejected:'el servicio de avisos rechazó el envío; el dispositivo sigue en la lista y se volverá a intentar',sent:'enviado',unregistered:'el servicio ya no reconoce este dispositivo y se ha quitado de la lista; vuelve a activar los avisos en él',unavailable:'el servicio de avisos no respondió; vuelve a probar en un momento',unconfigured:'faltan las claves de avisos en el servidor',invalid_credentials:'las claves de avisos del servidor no se pueden leer',unexpected:'error inesperado en el servidor'};
 panel.querySelector('#push-test').onclick=async event=>{
  const button=event.currentTarget,out=panel.querySelector('#push-test-result');
  button.disabled=true;out.textContent='Enviando…';
  try{
   const {results=[],error}=await api('devices/test','POST');
   if(error){lastTestResult=error;return}
   lastTestResult=results.length
     ? results.map(r=>`${r.label||names[r.platform]||'Dispositivo'}: ${RESULT[r.result]||r.result}`).join(' · ')+(results.some(r=>r.result==='sent')?'. Si un dispositivo dice «enviado» y no muestra nada, el aviso salió de aquí y el teléfono lo retuvo: revisa los ajustes de notificaciones del sistema para Ágora y el ahorro de batería.':'')
     : 'Este usuario no tiene ningún dispositivo con los avisos activados. Actívalos primero en el teléfono, con el mismo usuario.';
  }catch(error){lastTestResult=friendly(error)}
  finally{button.disabled=false;out.textContent=lastTestResult;devicePanel(container)}
 };
}

/**
 * Cuando el cron del servidor no corre, la revisión puede hacerla un servicio
 * de cron externo llamando cada minuto a una dirección secreta.
 */
function externalCheck(data,running){
 const created=data.externalCheck;
 const copyRow=(id,label,value)=>`<label class="external-check-label">${label}</label><div class="external-check-url"><input id="${id}" readonly value="${esc(value)}" aria-label="${label}"><button type="button" data-copy="${id}">Copiar</button></div>`;
 if(freshCron)return `<div class="external-check"><h3>Clave de revisión creada</h3>
   <p>Cópiala ahora: <strong>no se volverá a mostrar</strong>. Si la pierdes, crea otra y la anterior dejará de funcionar.</p>
   ${copyRow('cron-endpoint','Dirección',freshCron.endpoint)}
   ${copyRow('cron-header','Nombre de la cabecera',freshCron.header)}
   ${copyRow('cron-key','Valor de la cabecera (la clave)',freshCron.key)}
   <ol><li>Entra en <strong>cron-job.org</strong> (es gratis) y crea una tarea nueva con la <strong>dirección</strong> de arriba, <strong>cada minuto</strong>.</li>
   <li>En la pestaña <strong>Avanzado</strong>, en <strong>Cabeceras</strong>, añade una con el nombre <code>${esc(freshCron.header)}</code> y la clave como valor.</li>
   <li>Guarda. En un par de minutos la revisión saldrá aquí en verde.</li></ol>
   <p class="muted">Así la clave viaja dentro de la petición y no queda escrita en la dirección, ni en registros ni en historiales.</p>
   <details><summary>Mi servicio de cron no permite cabeceras</summary><p class="muted">Usa esta dirección, que lleva la clave dentro. Funciona igual, pero la clave puede quedar en registros de servidores intermedios.</p>${copyRow('cron-url','Dirección con la clave',freshCron.url)}</details>
   <button id="cron-done" type="button">Hecho</button></div>`;
 const ask=askCronPassword?`<form id="cron-confirm" class="dialog-form cron-confirm"><label>Para crear la clave, confirma tu contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="primary">Crear clave</button> <button type="button" id="cron-cancel" class="link-button">Cancelar</button><p id="cron-confirm-result" class="dialog-copy" role="status"></p></form>`:'';
 if(created){
  const use=data.externalCheckUse;
  const used=use?.at?`Último uso: ${esc(new Date(use.at).toLocaleString('es-ES'))}${use.how==='address'?' · con la clave en la dirección':''}.`:'Todavía no se ha usado.';
  return `<div class="external-check"><p>La revisión desde fuera está activada desde el ${esc(new Date(created).toLocaleDateString('es-ES'))}. ${used}</p>
   ${ask||'<button id="cron-new" type="button">Cambiar la clave</button> <button id="cron-off" type="button" class="danger">Desactivar</button>'}</div>`;
 }
 if(running&&!ask)return '';
 return `<div class="external-check"><h3>La revisión no está corriendo en el servidor</h3>
   <p>Sin ella los avisos sólo salen cuando alguien tiene Ágora abierta. Si el cron del servidor no se puede poner o no funciona, un servicio de cron externo puede hacer la revisión llamando cada minuto a Ágora con una clave.</p>
   ${ask||'<button id="cron-new" class="primary" type="button">Crear clave de revisión</button>'}</div>`;
}
function bindExternalCheck(container,panel){
 const create=panel.querySelector('#cron-new');
 if(create)create.onclick=()=>{
  if(document.querySelector('#cron-off')&&!confirm('La clave actual dejará de funcionar. ¿Crear una nueva?'))return;
  askCronPassword=true;devicePanel(container);
 };
 const form=panel.querySelector('#cron-confirm');
 if(form){
  form.elements.password.focus();
  form.onsubmit=async e=>{
   e.preventDefault();const button=form.querySelector('button.primary');button.disabled=true;
   try{freshCron=await api('admin/cron-url','POST',{password:form.elements.password.value});askCronPassword=false;devicePanel(container)}
   catch(error){form.querySelector('#cron-confirm-result').textContent=friendly(error);button.disabled=false}
  };
  panel.querySelector('#cron-cancel').onclick=()=>{askCronPassword=false;devicePanel(container)};
 }
 const off=panel.querySelector('#cron-off');
 if(off)off.onclick=async()=>{
  if(!confirm('La revisión desde fuera dejará de funcionar. ¿Desactivarla?'))return;
  off.disabled=true;
  try{await api('admin/cron-url','DELETE')}catch(error){alert(friendly(error))}
  devicePanel(container);
 };
 panel.querySelectorAll('[data-copy]').forEach(copy=>copy.onclick=async()=>{
  const input=panel.querySelector('#'+copy.dataset.copy);
  try{await navigator.clipboard.writeText(input.value);copy.textContent='Copiado'}
  catch{input.select();copy.textContent='Selecciona y copia'}
 });
 const done=panel.querySelector('#cron-done');
 if(done)done.onclick=()=>{freshCron=null;devicePanel(container)};
}

// La copia diaria: cuándo fue la última y si incluye las claves.
function backupNotice(b){
 const day=86400000;
 if(!b)return '<p class="settings-notice backup-notice warn"><b>Copias de seguridad:</b> todavía no se ha hecho ninguna. Añade en el servidor la tarea diaria <code>php agora-private/bin/backup.php</code> (las instrucciones están en la nota de la versión).</p>';
 const when=new Date(b.at);const old=Date.now()-when.getTime()>2*day;
 if(!b.ok)return `<p class="settings-notice backup-notice warn"><b>Copias de seguridad:</b> la última, el ${esc(when.toLocaleString('es-ES'))}, falló${b.error==='passphrase_short'?': la frase de cifrado necesita al menos 12 caracteres':'. Revisa los permisos de la carpeta de copias'}.</p>`;
 return `<p class="settings-notice backup-notice${old||!b.encrypted?' warn':''}"><b>Copias de seguridad:</b> la última, el ${esc(when.toLocaleString('es-ES'))}${old?' (hace más de dos días: revisa la tarea diaria)':''}. ${b.encrypted?'Cifrada, con la base de datos y las claves.':'Sólo la base de datos: añade una frase de cifrado para que incluya también las claves.'}</p>`;
}
export async function renderIntegrations(container){
 const instance=Symbol();container.agoraIntegrationsInstance=instance;const active=()=>container.agoraIntegrationsInstance===instance&&container.dataset.view==='settings';
 container.innerHTML='<p class="empty">Comprobando tu sesión…</p>';
 let user;
 try{const me=await api('me');user=me.user;csrf=me.csrf;if(!active())return}catch(e){if(!active())return;container.innerHTML=`<h2>Conexiones de Ágora</h2><p class="settings-notice">${e.status===401?'Inicia sesión con tu usuario administrador para configurar las subcuentas.':'No se ha podido conectar con el backend. Comprueba su estado.'}</p><button id="settings-login" class="primary">Iniciar sesión</button>`;container.querySelector('#settings-login').onclick=()=>window.dispatchEvent(new Event('agora:logout'));return}
 if(user.role!=='administrator'){container.innerHTML='<h2>Acceso restringido</h2><p class="settings-notice">Sólo el administrador puede gestionar las integraciones. Esta configuración está reservada al administrador.</p>';return}
 try{
 const installation=await api('admin/diagnostics');if(!active())return;
 if(!installation.ready){renderSetup(container,installation);return}
 const {items,accessChannel={connectionId:null,ready:false},backup=null}=await api('admin/integrations');if(!active())return;
 container.innerHTML=`<div class="integration-heading"><div><span class="eyebrow">ADMINISTRACIÓN</span><h2 style="margin-top:8px">Cuentas conectadas</h2></div><div><button id="test-access-channel"${accessChannel.ready?'':' disabled'}>Probar canal de acceso</button> <button id="add-integration" class="primary">+ Añadir subcuenta</button></div></div><p class="settings-notice">Tu cuenta es la única administración propia de Ágora. El equipo se importa desde cada cuenta conectada con sus permisos y asignaciones; no se crean contraseñas locales para empleados.</p>${accessPanel(items,accessChannel)}<div id="integration-cards">${items.map(i=>`<article class="card integration-card"><span class="integration-status">${statusNames[i.status]||'Pendiente'}</span><h3>${esc(i.name)}</h3><p>Identificador: ${esc(i.location_id)}<br>Clave protegida · intervalo: ${i.sync_interval_seconds} s<br>Última comprobación: ${esc(i.last_checked_at||'Sin comprobar')}<br>Consultas programadas: ${Number(i.sync_enabled)?'activadas':'desactivadas'}<br>Última lectura: ${esc(i.last_success_at||'Sin sincronizar')}<br>Equipo importado: ${Number(i.externalUsers)||0} · ${esc(i.users_last_success_at||'Sin sincronizar')}${i.accessChannel?'<br>Canal de acceso del equipo':''}<br>Plantillas de WhatsApp: ${i.whatsapp_number_id?'número '+esc(i.whatsapp_number_id):'sin configurar'}</p><footer><button data-edit="${i.id}">Configurar conexión</button><button data-whatsapp="${i.id}">Plantillas de WhatsApp</button><button data-test="${i.id}">Comprobar conexión</button><button data-users="${i.id}">Equipo y permisos</button><button data-schedule="${i.id}" data-enabled="${Number(i.sync_enabled)||0}">${Number(i.sync_enabled)?'Pausar consultas':'Activar consultas'}</button><button data-remove="${i.id}" class="danger">Eliminar</button></footer></article>`).join('')||'<p class="empty">Todavía no tienes cuentas conectadas.<br>Añade una cuenta para empezar.</p>'}</div><p id="integration-feedback" class="dialog-copy" role="status"></p>${backupNotice(backup)}<section id="device-panel"></section>`;
 devicePanel(container);
 container.querySelector('#add-integration').onclick=()=>edit(null,container);
 container.querySelector('#test-access-channel').onclick=async e=>{e.currentTarget.disabled=true;const feedback=container.querySelector('#integration-feedback');feedback.textContent='Enviando prueba…';try{await api('admin/access-channel/test','POST');feedback.textContent='Prueba entregada al correo del administrador. Revisa la bandeja y la carpeta de spam.'}catch(error){feedback.textContent=friendly(error)}finally{e.currentTarget.disabled=false}};
 const channelSelect=container.querySelector('#access-channel-select');
 if(channelSelect)channelSelect.onchange=async()=>{channelSelect.disabled=true;const feedback=container.querySelector('#integration-feedback');try{await api('admin/access-channel','PUT',{connectionId:channelSelect.value?Number(channelSelect.value):null});await renderIntegrations(container);container.querySelector('#integration-feedback').textContent=channelSelect.value?'Canal de acceso guardado.':'Canal de acceso sin asignar. El equipo no podrá recibir códigos.'}catch(error){feedback.textContent=friendly(error);channelSelect.disabled=false}};
 container.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>edit(items.find(i=>String(i.id)===b.dataset.edit),container));
 container.querySelectorAll('[data-test]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{const r=await api(`admin/integrations/${b.dataset.test}/test`,'POST');await renderIntegrations(container);container.querySelector('#integration-feedback').textContent=r.message}catch(e){container.querySelector('#integration-feedback').textContent=friendly(e)}finally{b.disabled=false}});
 container.querySelectorAll('[data-whatsapp]').forEach(b=>b.onclick=()=>whatsappNumber(items.find(i=>String(i.id)===b.dataset.whatsapp),container));
 container.querySelectorAll('[data-users]').forEach(b=>b.onclick=()=>team(b.dataset.users,items.find(i=>String(i.id)===b.dataset.users),container));
 container.querySelectorAll('[data-schedule]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{await api(`admin/integrations/${b.dataset.schedule}/schedule`,'POST',{enabled:!Number(b.dataset.enabled)});await renderIntegrations(container);container.querySelector('#integration-feedback').textContent='Preferencia guardada. La actualización automática requiere que el servicio de sincronización esté activo.'}catch(e){container.querySelector('#integration-feedback').textContent=friendly(e)}finally{b.disabled=false}});
 container.querySelectorAll('[data-remove]').forEach(b=>b.onclick=()=>remove(b.dataset.remove,container));
 }catch(e){if(!active())return;container.innerHTML=`<h2>Integraciones</h2><p class="settings-notice">${esc(friendly(e))}</p>`}
}
// El número de WhatsApp de la cuenta, como lo nombra Meta: con él se leen sus
// plantillas aprobadas y se envían desde ese número.
const DROP_REASONS={name:'sin nombre válido',language:'sin idioma reconocible',status_pending:'en revisión de Meta',status_in_appeal:'en apelación',status_rejected:'rechazadas',status_paused:'pausadas',status_disabled:'desactivadas',status_deleted:'eliminadas',unknown:'sin motivo claro'};
function diagnosisMarkup(d){
 if(!d)return '';
 const dropped=Object.entries(d.dropped||{}).map(([why,n])=>`${n} ${DROP_REASONS[why]||why.replace(/^status_/,'con estado ')}`).join(', ');
 const via=d.origin==='account'?'la cuenta de WhatsApp Business':'el número';
 const lines=d.received===0
  ?`<p><b>La cuenta no devolvió ninguna plantilla</b> al pedirlas con ${via}. Si en la cuenta sí hay plantillas aprobadas, prueba a indicar el identificador de la cuenta de WhatsApp Business (WABA) en el segundo campo y vuelve a comprobar.</p>`
  :`<p>La cuenta devolvió <b>${d.received}</b> plantilla${d.received===1?'':'s'}; se muestran <b>${d.shown}</b> en el chat.${dropped?` Fuera: ${esc(dropped)}.`:''}</p>${(d.names||[]).length?`<p class="muted">${(d.names||[]).map(esc).join(' · ')}</p>`:''}`;
 const fields=d.fields||{};
 return `<div class="wa-diagnosis">${lines}<details><summary>Detalle para soporte</summary><p class="muted">Campos de la respuesta: ${esc((fields.top||[]).join(', ')||'ninguno')}<br>Campos de cada plantilla: ${esc((fields.item||[]).join(', ')||'—')}${(fields.template||[]).length?`<br>Dentro de «template»: ${esc(fields.template.join(', '))}`:''}<br>Comprobado: ${esc(d.at||'')}</p></details></div>`;
}
// El número de WhatsApp de la cuenta, como lo nombra Meta: con él se envían las
// plantillas aprobadas. Si se leen con la cuenta de WhatsApp Business, se indica aparte.
function whatsappNumber(item,container){
 const dialog=document.querySelector('#action-dialog');
 document.querySelector('#dialog-content').innerHTML=`<h2>Plantillas de WhatsApp</h2><p class="dialog-copy">Para escribir a un contacto pasadas 24 horas desde su último mensaje, WhatsApp exige una plantilla aprobada. Indica los identificadores que Meta asigna a <b>${esc(item.name)}</b>; aparecen en la configuración de WhatsApp de la cuenta.</p><form id="whatsapp-form" class="dialog-form"><label>Identificador del número («Phone number ID»)<input name="numberId" inputmode="numeric" pattern="[0-9]{6,30}" value="${esc(item.whatsapp_number_id||'')}" placeholder="Ej.: 920050454518509"></label><label>Identificador de la cuenta de WhatsApp Business («WABA ID»), opcional<input name="accountId" inputmode="numeric" pattern="[0-9]{6,30}" value="${esc(item.whatsapp_account_id||'')}" placeholder="Sólo si las plantillas no aparecen con el número"></label><div class="dialog-actions"><button class="primary">Guardar</button><button type="button" id="whatsapp-check" class="secondary"${item.whatsapp_number_id?'':' disabled'}>Comprobar plantillas</button></div><p id="whatsapp-result" class="dialog-copy" role="status"></p></form><div id="whatsapp-diagnosis"></div><p class="dialog-copy">Deja el número vacío para no usar plantillas en esta cuenta.</p>`;
 if(!dialog.open)dialog.showModal();
 const result=document.querySelector('#whatsapp-result');
 const check=async()=>{
  const button=document.querySelector('#whatsapp-check');button.disabled=true;result.textContent='Pidiendo las plantillas a la cuenta…';
  try{const {diagnosis}=await api(`admin/integrations/${item.id}/whatsapp/check`,'POST');result.textContent='';document.querySelector('#whatsapp-diagnosis').innerHTML=diagnosisMarkup(diagnosis)}
  catch(error){result.textContent=friendly(error)}
  finally{button.disabled=false}
 };
 document.querySelector('#whatsapp-check').onclick=check;
 document.querySelector('#whatsapp-form').onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button.primary');button.disabled=true;
  try{
   const value=e.target.elements.numberId.value.trim(),account=e.target.elements.accountId.value.trim();
   await api(`admin/integrations/${item.id}/whatsapp`,'PUT',{numberId:value||null,accountId:account||null});
   item.whatsapp_number_id=value||null;item.whatsapp_account_id=value?(account||null):null;
   result.textContent=value?'Guardado.':'Plantillas desactivadas en esta cuenta.';
   document.querySelector('#whatsapp-check').disabled=!value;
   if(value)await check();
  }catch(error){result.textContent=friendly(error)}
  finally{button.disabled=false}
 };
 dialog.addEventListener('close',()=>renderIntegrations(container),{once:true});
}
async function team(id,item,container){
 const dialog=document.querySelector('#action-dialog');document.querySelector('#dialog-content').innerHTML='<h2>Equipo y permisos</h2><p class="dialog-copy">Cargando usuarios importados…</p>';dialog.showModal();
 async function show(message=''){
  try{const data=await api(`admin/integrations/${id}/users`);if(!dialog.open)return;
   document.querySelector('#dialog-content').innerHTML=`<span class="eyebrow">${esc(item.name)}</span><h2 style="margin-top:12px">Equipo y permisos</h2><p class="dialog-copy">Estos usuarios proceden de la cuenta conectada. Ágora no guarda sus contraseñas. ${data.authenticationConfigured?'El canal de acceso está listo para entregar códigos.':'Falta elegir la cuenta encargada del canal de acceso.'}</p><button id="sync-team" class="primary">Sincronizar equipo</button><p id="team-feedback" class="dialog-copy" role="status">${esc(message)}</p><div class="team-list">${data.items.map(u=>`<article class="card"><h3>${esc(u.display_name)}</h3><p>${esc(u.email||'Sin email')}<br>Rol: ${esc(u.role_name||u.role_type||'Sin rol')} · ${Number(u.assigned_data_only)?'Sólo datos asignados':'Acceso según permisos'}<br>${u.active?'Activo':'Ya no aparece en la cuenta'} · ${u.identityLinked?'Identidad vinculada':'Acceso aún no utilizado'}</p><small>${Object.entries(u.capabilities).filter(([,v])=>v===true).map(([k])=>esc(k)).join(' · ')||'Sin módulos habilitados'}</small></article>`).join('')||'<p class="empty">Aún no hay usuarios importados.</p>'}</div>`;
   document.querySelector('#sync-team').onclick=async e=>{e.currentTarget.disabled=true;try{const r=await api(`admin/integrations/${id}/users/sync`,'POST');await show(`${r.synced.count} usuarios actualizados.`);await renderIntegrations(container)}catch(error){document.querySelector('#team-feedback').textContent=friendly(error)+(error.code?' Código: '+error.code:'')}finally{const button=document.querySelector('#sync-team');if(button)button.disabled=false}};
  }catch(error){document.querySelector('#dialog-content').innerHTML=`<h2>Equipo y permisos</h2><p class="dialog-copy">${esc(friendly(error))}</p>`}
 }
 await show();
}
function edit(item,container){
 const dialog=document.querySelector('#action-dialog');
 document.querySelector('#dialog-content').innerHTML=`<span class="eyebrow">CONEXIÓN SEGURA</span><h2 style="margin-top:12px">${item?'Configurar subcuenta':'Añadir subcuenta'}</h2><p class="dialog-copy">Introduce el identificador y la clave de la cuenta. La clave se guarda protegida y no se vuelve a mostrar.</p><form id="integration-form" class="dialog-form"><label>Nombre de referencia<input name="name" required maxlength="160" value="${esc(item?.name||'')}" autocomplete="off"></label><label>Identificador de cuenta<input name="locationId" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="${esc(item?.location_id||'')}" ${item?'readonly':''} autocomplete="off"></label><label>Clave de conexión ${item?'(dejar vacío para conservar)':''}<input name="token" type="password" ${item?'':'required'} maxlength="1024" autocomplete="new-password" spellcheck="false" autocapitalize="off"></label><label>Intervalo de sincronización<select name="syncIntervalSeconds">${[60,120,300,900,3600].map(n=>`<option value="${n}" ${n===(Number(item?.sync_interval_seconds)||120)?'selected':''}>${n<3600?n/60+' minutos':'1 hora'}</option>`).join('')}</select></label><p id="save-feedback" class="dialog-copy" role="status"></p><button type="submit" class="primary">Guardar en el servidor</button></form>`;
 dialog.showModal();const form=document.querySelector('#integration-form');
 // No token goes to a URL, localStorage, IndexedDB, console or cached API response.
 const clearToken=()=>{const field=form.elements.namedItem('token');if(field)field.value=''};
 dialog.addEventListener('close',clearToken,{once:true});
 form.onsubmit=async e=>{e.preventDefault();const b=form.querySelector('button[type="submit"]');b.disabled=true;const data=Object.fromEntries(new FormData(form));data.syncIntervalSeconds=Number(data.syncIntervalSeconds);try{await api('admin/integrations'+(item?'/'+item.id:''),item?'PUT':'POST',data);clearToken();dialog.close();await renderIntegrations(container)}catch(e){clearToken();document.querySelector('#save-feedback').textContent=friendly(e)+(e.code?' Código: '+e.code+'.':' Comprueba el estado de las conexiones antes de repetir la operación.')}finally{data.token='';b.disabled=false}};
}
function remove(id,container){
 const d=document.querySelector('#action-dialog');document.querySelector('#dialog-content').innerHTML='<h2>Eliminar la conexión</h2><p class="dialog-copy">Se eliminará la configuración guardada de esta cuenta. Podrás volver a conectarla cuando lo necesites.</p><button id="confirm-remove" class="primary">Eliminar conexión</button><p id="remove-error" class="dialog-copy" role="status"></p>';d.showModal();document.querySelector('#confirm-remove').onclick=async()=>{try{await api(`admin/integrations/${id}`,'DELETE');d.close();await renderIntegrations(container)}catch(e){document.querySelector('#remove-error').textContent=friendly(e)}};
}
window.addEventListener('agora:login',()=>{const c=document.querySelector('#view');if(document.querySelector('[data-view="settings"]').classList.contains('active'))renderIntegrations(c)});

function renderSetup(container,installation){
 const labels={runtime:'Entorno de la aplicación',origin:'Dominio seguro',encryption:'Protección de conexiones',schema:'Base de datos'};
 const canPrepare=(installation.checks||[]).some(c=>c.code==='encryption_key_missing')&&(installation.checks||[]).every(c=>c.name==='encryption'||c.ready);
 container.innerHTML=`<div class="integration-heading"><h2>Preparación de las conexiones</h2><span class="integration-status">Ágora ${esc(installation.version||'')}</span></div><p class="settings-notice">Hay un paso de instalación pendiente. Resuélvelo antes de introducir la clave de tu cuenta. Las actualizaciones de la base de datos se aplican solas al entrar.</p>${(installation.checks||[]).map(c=>`<article class="card integration-card"><h3>${esc(labels[c.name]||'Comprobación')}</h3><p>${c.ready?'Correcto':esc(c.message)}</p>${c.code?`<small class="danger">Código: ${esc(c.code)}</small>`:''}</article>`).join('')}${canPrepare?'<button id="prepare-encryption" class="primary">Preparar protección inicial</button> ':''}<button id="recheck-setup" class="primary">Comprobar de nuevo</button> <button id="copy-setup" class="primary">Copiar resultado</button><p id="setup-feedback" class="dialog-copy" role="status"></p>`;
 container.querySelector('#recheck-setup').onclick=()=>renderIntegrations(container);
 const prepare=container.querySelector('#prepare-encryption');
 if(prepare)prepare.onclick=async()=>{prepare.disabled=true;try{await api('admin/installation/encryption','POST');await renderIntegrations(container)}catch(e){container.querySelector('#setup-feedback').textContent=friendly(e)}finally{prepare.disabled=false}};
 container.querySelector('#copy-setup').onclick=async()=>{
  const summary='Ágora '+installation.version+'\n'+(installation.checks||[]).map(c=>`${c.name}: ${c.ready?'OK':c.code}`).join('\n');
  try{await navigator.clipboard.writeText(summary);container.querySelector('#setup-feedback').textContent='Resultado copiado. Puedes compartirlo con soporte; no contiene claves.'}
  catch{container.querySelector('#setup-feedback').textContent=summary}
 };
}
