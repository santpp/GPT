const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const statusNames={untested:'Pendiente de comprobar',connected:'Conexión comprobada',error:'Revisar conexión'};
let csrf='';
async function api(path,method='GET',data){
 const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(method==='GET'?{}:{'X-CSRF-Token':csrf})},...(data?{body:JSON.stringify(data)}:{})});
 let b;try{b=await r.json()}catch{throw Error('El backend no está disponible.')}
 if(!r.ok){const e=Error(b.error||'No se ha podido completar la operación.');e.status=r.status;e.code=b.code;e.reference=b.reference;throw e}return b;
}
export async function renderIntegrations(container){
 const instance=Symbol();container.agoraIntegrationsInstance=instance;const active=()=>container.agoraIntegrationsInstance===instance&&container.dataset.view==='settings';
 container.innerHTML='<p class="empty">Comprobando tu sesión…</p>';
 let user;
 try{const me=await api('me');user=me.user;csrf=me.csrf;if(!active())return}catch(e){if(!active())return;container.innerHTML=`<h2>Conexiones de Ágora</h2><p class="settings-notice">${e.status===401?'Inicia sesión con tu usuario administrador para configurar las subcuentas.':'No se ha podido conectar con el backend. Comprueba su estado.'}</p><button id="settings-login" class="primary">Iniciar sesión</button>`;container.querySelector('#settings-login').onclick=()=>document.querySelector('#connect').click();return}
 if(user.role!=='administrator'){container.innerHTML='<h2>Acceso restringido</h2><p class="settings-notice">Sólo el administrador puede gestionar las integraciones. Esta configuración está reservada al administrador.</p>';return}
 try{
 const installation=await api('admin/diagnostics');if(!active())return;
 if(!installation.ready){renderSetup(container,installation);return}
 const {items}=await api('admin/integrations');if(!active())return;
 container.innerHTML=`<div class="integration-heading"><div><span class="eyebrow">ADMINISTRACIÓN</span><h2 style="margin-top:8px">Cuentas conectadas</h2></div><button id="add-integration" class="primary">+ Añadir subcuenta</button></div><p class="settings-notice">Conecta tus cuentas para consultar sus conversaciones desde Ágora. Las claves se guardan protegidas y sólo el administrador puede cambiarlas. Puedes consultar el historial y responder por los canales disponibles. El envío requiere permisos de escritura de mensajes; las notificaciones aún no están disponibles.</p><div id="integration-cards">${items.map(i=>`<article class="card integration-card"><span class="integration-status">${statusNames[i.status]||'Pendiente'}</span><h3>${esc(i.name)}</h3><p>Identificador: ${esc(i.location_id)}<br>Clave protegida · intervalo: ${i.sync_interval_seconds} s<br>Última comprobación: ${esc(i.last_checked_at||'Sin comprobar')}<br>Consultas programadas: ${Number(i.sync_enabled)?'activadas':'desactivadas'}<br>Última lectura: ${esc(i.last_success_at||'Sin sincronizar')}</p><footer><button data-edit="${i.id}">Configurar conexión</button><button data-test="${i.id}">Comprobar conexión</button><button data-schedule="${i.id}" data-enabled="${Number(i.sync_enabled)||0}">${Number(i.sync_enabled)?'Pausar consultas':'Activar consultas'}</button><button data-remove="${i.id}" class="danger">Eliminar</button></footer></article>`).join('')||'<p class="empty">Todavía no tienes cuentas conectadas.<br>Añade una cuenta para empezar.</p>'}</div><p id="integration-feedback" class="dialog-copy" role="status"></p><button id="settings-logout" class="primary">Cerrar sesión</button>`;
 container.querySelector('#add-integration').onclick=()=>edit(null,container);
 container.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>edit(items.find(i=>String(i.id)===b.dataset.edit),container));
 container.querySelectorAll('[data-test]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{const r=await api(`admin/integrations/${b.dataset.test}/test`,'POST');await renderIntegrations(container);container.querySelector('#integration-feedback').textContent=r.message}catch(e){container.querySelector('#integration-feedback').textContent=e.message}finally{b.disabled=false}});
 container.querySelectorAll('[data-schedule]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{await api(`admin/integrations/${b.dataset.schedule}/schedule`,'POST',{enabled:!Number(b.dataset.enabled)});await renderIntegrations(container);container.querySelector('#integration-feedback').textContent='Preferencia guardada. La actualización automática requiere que el servicio de sincronización esté activo.'}catch(e){container.querySelector('#integration-feedback').textContent=e.message}finally{b.disabled=false}});
 container.querySelectorAll('[data-remove]').forEach(b=>b.onclick=()=>remove(b.dataset.remove,container));
 container.querySelector('#settings-logout').onclick=async()=>{await api('logout','POST');csrf='';window.dispatchEvent(new Event('agora:logout'));document.querySelector('[data-view="inbox"]').click()};
 }catch(e){if(!active())return;container.innerHTML=`<h2>Integraciones</h2><p class="settings-notice">${esc(e.message)}</p>`}
}
function edit(item,container){
 const dialog=document.querySelector('#action-dialog');
 document.querySelector('#dialog-content').innerHTML=`<span class="eyebrow">CONEXIÓN SEGURA</span><h2 style="margin-top:12px">${item?'Configurar subcuenta':'Añadir subcuenta'}</h2><p class="dialog-copy">Introduce el identificador y la clave de la cuenta. La clave se guarda protegida y no se vuelve a mostrar.</p><form id="integration-form" class="dialog-form"><label>Nombre de referencia<input name="name" required maxlength="160" value="${esc(item?.name||'')}" autocomplete="off"></label><label>Identificador de cuenta<input name="locationId" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="${esc(item?.location_id||'')}" ${item?'readonly':''} autocomplete="off"></label><label>Clave de conexión ${item?'(dejar vacío para conservar)':''}<input name="token" type="password" ${item?'':'required'} maxlength="1024" autocomplete="new-password" spellcheck="false" autocapitalize="off"></label><label>Intervalo de sincronización<select name="syncIntervalSeconds">${[60,120,300,900,3600].map(n=>`<option value="${n}" ${n===(Number(item?.sync_interval_seconds)||120)?'selected':''}>${n<3600?n/60+' minutos':'1 hora'}</option>`).join('')}</select></label><p id="save-feedback" class="dialog-copy" role="status"></p><button type="submit" class="primary">Guardar en el servidor</button></form>`;
 dialog.showModal();const form=document.querySelector('#integration-form');
 // No token goes to a URL, localStorage, IndexedDB, console or cached API response.
 const clearToken=()=>{const field=form.elements.namedItem('token');if(field)field.value=''};
 dialog.addEventListener('close',clearToken,{once:true});
 form.onsubmit=async e=>{e.preventDefault();const b=form.querySelector('button[type="submit"]');b.disabled=true;const data=Object.fromEntries(new FormData(form));data.syncIntervalSeconds=Number(data.syncIntervalSeconds);try{await api('admin/integrations'+(item?'/'+item.id:''),item?'PUT':'POST',data);clearToken();dialog.close();await renderIntegrations(container)}catch(e){clearToken();document.querySelector('#save-feedback').textContent=e.message+(e.code?' Código: '+e.code+'.':' Comprueba el estado de las conexiones antes de repetir la operación.')}finally{data.token='';b.disabled=false}};
}
function remove(id,container){
 const d=document.querySelector('#action-dialog');document.querySelector('#dialog-content').innerHTML='<h2>Eliminar la conexión</h2><p class="dialog-copy">Se eliminará la configuración guardada de esta cuenta. Podrás volver a conectarla cuando lo necesites.</p><button id="confirm-remove" class="primary">Eliminar conexión</button><p id="remove-error" class="dialog-copy" role="status"></p>';d.showModal();document.querySelector('#confirm-remove').onclick=async()=>{try{await api(`admin/integrations/${id}`,'DELETE');d.close();await renderIntegrations(container)}catch(e){document.querySelector('#remove-error').textContent=e.message}};
}
window.addEventListener('agora:login',()=>{const c=document.querySelector('#other-view');if(document.querySelector('[data-view="settings"]').classList.contains('active'))renderIntegrations(c)});

function renderSetup(container,installation){
 const labels={runtime:'Entorno de la aplicación',origin:'Dominio seguro',encryption:'Protección de conexiones',schema:'Base de datos'};
 const canMigrate=(installation.checks||[]).some(c=>c.code==='schema_pending')&&(installation.checks||[]).every(c=>['schema','encryption'].includes(c.name)||c.ready);
 const canPrepare=(installation.checks||[]).some(c=>c.code==='encryption_key_missing')&&(installation.checks||[]).every(c=>c.name==='encryption'||c.ready);
 container.innerHTML=`<div class="integration-heading"><h2>Preparación de las conexiones</h2><span class="integration-status">Ágora ${esc(installation.version||'')}</span></div><p class="settings-notice">Hay un paso de instalación pendiente. Resuélvelo antes de introducir la clave de tu cuenta.</p>${(installation.checks||[]).map(c=>`<article class="card integration-card"><h3>${esc(labels[c.name]||'Comprobación')}</h3><p>${c.ready?'Correcto':esc(c.message)}</p>${c.code?`<small class="danger">Código: ${esc(c.code)}</small>`:''}</article>`).join('')}${canMigrate?'<button id="prepare-schema" class="primary">Actualizar base de datos</button> ':''}${canPrepare?'<button id="prepare-encryption" class="primary">Preparar protección inicial</button> ':''}<button id="recheck-setup" class="primary">Comprobar de nuevo</button> <button id="copy-setup" class="primary">Copiar resultado</button><p id="setup-feedback" class="dialog-copy" role="status"></p>`;
 container.querySelector('#recheck-setup').onclick=()=>renderIntegrations(container);
 const migrate=container.querySelector('#prepare-schema');
 if(migrate)migrate.onclick=async()=>{migrate.disabled=true;try{await api('admin/installation/migrations','POST');await renderIntegrations(container)}catch(e){container.querySelector('#setup-feedback').textContent=e.message}finally{migrate.disabled=false}};
 const prepare=container.querySelector('#prepare-encryption');
 if(prepare)prepare.onclick=async()=>{prepare.disabled=true;try{await api('admin/installation/encryption','POST');await renderIntegrations(container)}catch(e){container.querySelector('#setup-feedback').textContent=e.message}finally{prepare.disabled=false}};
 container.querySelector('#copy-setup').onclick=async()=>{
  const summary='Ágora '+installation.version+'\n'+(installation.checks||[]).map(c=>`${c.name}: ${c.ready?'OK':c.code}`).join('\n');
  try{await navigator.clipboard.writeText(summary);container.querySelector('#setup-feedback').textContent='Resultado copiado. Puedes compartirlo con soporte; no contiene claves.'}
  catch{container.querySelector('#setup-feedback').textContent=summary}
 };
}
