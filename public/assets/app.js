import {workspace} from './workspace.js';
import {renderIntegrations} from './integrations.js';
import {renderLiveInbox} from './live-inbox.js';
import {renderContacts} from './contacts.js';
import {renderDashboard} from './dashboard.js';
import {connectDevice,releaseDevice,isNative} from './native.js';
import {enable as enablePush,disable as disablePush,state as pushState,isApple,installed} from './push.js';
import {renderBoard} from './board.js';
import {supported as passkeysSupported,platformReady,remembered as passkeyRemembered,remember as rememberPasskey,create as createPasskey,sign as signPasskey,trouble as passkeyTrouble} from './passkeys.js';

const $=s=>document.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const view=$('#view');

const VIEWS={
  dashboard:{path:'/tablero',title:'Tablero',eyebrow:'TU DÍA',heading:'Así va tu cuenta.',subtitle:'Lo que espera respuesta y lo que está en juego.',render:renderDashboard,account:true},
  inbox:{path:'/bandeja',title:'Conversaciones',eyebrow:'BANDEJA',heading:'Cada conversación cuenta.',subtitle:'Todo el contexto, en un solo lugar.',render:renderLiveInbox,account:true},
  contacts:{path:'/contactos',title:'Contactos',eyebrow:'TU GENTE',heading:'Personas, no registros.',subtitle:'Tu siguiente conversación empieza aquí.',render:renderContacts,account:true},
  board:{path:'/oportunidades',title:'Oportunidades',eyebrow:'EMBUDO',heading:'De la conexión al cierre.',subtitle:'Cada oportunidad abierta, a la vista.',render:renderBoard,account:true},
  settings:{path:'/conexiones',title:'Conexiones',eyebrow:'ADMINISTRACIÓN',heading:'Tu espacio, a tu manera.',subtitle:'Gestiona las cuentas conectadas y el acceso del equipo.',render:renderIntegrations,account:false},
};
const byPath=path=>Object.keys(VIEWS).find(name=>VIEWS[name].path===path);
// A host that cannot rewrite paths can still deep-link with a hash.
const requested=()=>byPath(location.pathname)||byPath('/'+location.hash.replace(/^#\/?/,''));

let currentView='inbox';
let toastTimer;
function toast(text){$('#toast').textContent=text;$('#toast').hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('#toast').hidden=true,4500)}

async function api(path,method='GET',body=null,csrf=''){
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error(data.error||'Error de conexión'),{status:r.status,code:data.code});
  return data;
}

function show(name,{push=true}={}){
  const target=VIEWS[name]?name:'inbox';
  view.agoraLiveDispose?.();
  currentView=target;
  const meta=VIEWS[target];
  if(push&&location.pathname!==meta.path)history.pushState(null,'',meta.path);
  document.querySelectorAll('button[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===target));
  $('#breadcrumb').textContent=meta.title;
  $('#page-title').innerHTML=esc(meta.heading).replace(/\.$/,'<span>.</span>');
  $('#page-subtitle').textContent=meta.subtitle;
  $('#page-eyebrow').textContent=meta.eyebrow;
  $('.account-switch').hidden=!meta.account;
  view.dataset.view=target;
  view.innerHTML='';
  meta.render(view);
}

// Notifications on this very device. Nothing is asked until the person asks:
// on iOS a refused prompt cannot be shown again, so the button explains first.
const PUSH_TEXT={
  off:'Activar avisos en este dispositivo',
  on:'Avisos activados · tocar para desactivar',
  blocked:'Avisos bloqueados en los ajustes del navegador',
  'needs-install':'Para recibir avisos, añade Ágora a la pantalla de inicio',
  unconfigured:'Los avisos aún no están configurados en el servidor',
  unsupported:'Este navegador no admite avisos',
};
let pushNow='off';
async function paintPush(){
  const button=$('#push-toggle');if(!button)return;
  if(isNative()){button.hidden=true;return}
  pushNow=await pushState();
  button.hidden=false;
  button.disabled=['unsupported','unconfigured'].includes(pushNow);
  button.dataset.state=pushNow;
  $('#push-label').textContent=PUSH_TEXT[pushNow]||PUSH_TEXT.off;
}
$('#push-toggle').onclick=async()=>{
  const button=$('#push-toggle');
  if(pushNow==='needs-install'){
    toast(isApple()
      ? 'En iPhone: toca Compartir en Safari y elige «Añadir a pantalla de inicio». Abre Ágora desde ese icono y vuelve a pulsar aquí.'
      : 'Instala Ágora desde el menú del navegador («Instalar aplicación») y vuelve a pulsar aquí.');
    return;
  }
  if(pushNow==='blocked'){toast('Los avisos están bloqueados para este sitio. Actívalos en los ajustes del navegador y vuelve a pulsar aquí.');return}
  button.disabled=true;
  try{
    const answer=pushNow==='on'?await disablePush(workspace.me?.csrf||''):await enablePush(workspace.me?.csrf||'');
    const result=typeof answer==='string'?answer:answer.state;
    if(result==='on')toast('Listo: este dispositivo avisará de los mensajes nuevos.');
    else if(result==='blocked')toast('El dispositivo no ha dado permiso para avisar.');
    else if(result==='unconfigured')toast(answer.reason||'El servidor todavía no tiene los avisos preparados.');
    else if(result==='off'&&pushNow==='on')toast('Este dispositivo ya no recibirá avisos.');
  }catch(error){toast(error.message||'No se pudo cambiar los avisos de este dispositivo.')}
  finally{await paintPush();button.disabled=['unsupported','unconfigured'].includes(pushNow)}
};

function renderAccounts(){
  const select=$('#account');
  select.innerHTML=workspace.connections.map(c=>`<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
  select.value=workspace.current();
  select.disabled=workspace.connections.length<2;
  $('#account-name').textContent=workspace.name()||'Sin cuenta';
  $('.account-switch').hidden=!VIEWS[currentView].account||!workspace.connections.length;
}

async function startSession(){
  const me=await api('me');
  workspace.setProfile(me);
  if(me.passkeys>0)rememberPasskey(true);
  // Rehecha desde el dispositivo con el bloqueo puesto: primero la huella.
  if(me.locked){showLock();return}
  $('#signin').hidden=true;$('#lock').hidden=true;$('#app').hidden=false;
  $('#user-email').firstChild.textContent=me.user.email||'Mi cuenta';
  $('#user-initials').textContent=(me.user.email||'··').slice(0,2).toUpperCase();
  $('#user-initials-top').textContent=(me.user.email||'··').slice(0,2).toUpperCase();
  document.querySelectorAll('[data-admin-only]').forEach(n=>n.hidden=me.user.role!=='administrator');
  try{const {items}=await api('workspace/connections');workspace.setConnections(items)}
  catch{workspace.setConnections([])}
  renderAccounts();
  show(requested()||'inbox',{push:false});
  paintPush();
  // Inside the Android shell the phone registers itself so it can be notified.
  if(isNative())connectDevice(me.csrf,path=>{const name=byPath(path);if(name)show(name);else if(path.startsWith('/conversations/'))history.pushState(null,'',path),show('inbox',{push:false})})
    .then(result=>{globalThis.agoraDeviceToken=result.token;if(result.state==='denied')toast('Activa las notificaciones del sistema para recibir avisos de mensajes nuevos.')})
    .catch(()=>{});
}

function showSignin(){
  workspace.reset();
  $('#app').hidden=true;$('#lock').hidden=true;$('#signin').hidden=false;
  $('#passkey-login').hidden=!(passkeysSupported()&&passkeyRemembered());
  view.agoraLiveDispose?.();
  view.innerHTML='';
}

$('#account').onchange=e=>{if(workspace.select(e.target.value))renderAccounts()};
workspace.onChange(()=>{renderAccounts();if(VIEWS[currentView].account)show(currentView,{push:false})});
document.querySelectorAll('button[data-view]').forEach(b=>b.onclick=()=>show(b.dataset.view));
window.addEventListener('popstate',()=>{const name=requested();if(name)show(name,{push:false})});

async function signOut(){
  $('#action-dialog').open&&$('#action-dialog').close();
  await releaseDevice(workspace.me?.csrf||'');
  try{await api('logout','POST',null,workspace.me?.csrf||'')}catch{}
  window.dispatchEvent(new Event('agora:logout'));
  showSignin();
}
$('#lock-signout').onclick=signOut;

// ---- Bloqueo: lo decide el servidor; la página sólo pide la huella ----------------
function showLock(){
  view.agoraLiveDispose?.();view.innerHTML='';
  if($('#action-dialog').open)$('#action-dialog').close();
  $('#app').hidden=true;$('#signin').hidden=true;$('#lock').hidden=false;
  $('#unlock-result').textContent='';
}
$('#unlock').onclick=async e=>{
  const button=e.currentTarget;button.disabled=true;$('#unlock-result').textContent='';
  try{
    const csrf=workspace.me?.csrf||'';
    const {publicKey}=await api('passkey-unlock/options','POST',{},csrf);
    await api('passkey-unlock','POST',{credential:await signPasskey(publicKey)},csrf);
    await startSession();
  }catch(error){$('#unlock-result').textContent=error.status?error.message:passkeyTrouble(error)}
  finally{button.disabled=false}
};
// Cualquier pantalla que reciba «bloqueada» lleva a la pantalla de bloqueo.
const plainFetch=window.fetch.bind(window);
window.fetch=async(...args)=>{
  const response=await plainFetch(...args);
  try{if(response.status===423&&String(args[0]?.url??args[0]).includes('/api/'))window.dispatchEvent(new Event('agora:locked'))}catch{}
  return response;
};
window.addEventListener('agora:locked',()=>{if(!$('#app').hidden)showLock()});

// ---- Entrar con huella o cara ------------------------------------------------------
$('#passkey-login').onclick=async e=>{
  const button=e.currentTarget;button.disabled=true;$('#passkey-login-result').textContent='';
  try{
    const {publicKey}=await api('passkey-login/options','POST',{});
    await api('passkey-login','POST',{credential:await signPasskey(publicKey)});
    rememberPasskey(true);
    await startSession();
  }catch(error){
    if(error.code==='passkey_unknown')rememberPasskey(false);
    $('#passkey-login-result').textContent=error.status?error.message:passkeyTrouble(error);
  }finally{button.disabled=false}
};

/** Tras entrar con contraseña o código, una vez: «¿la próxima vez con la huella?». */
async function offerPasskey(){
  const me=workspace.me;
  if(!me||me.passkeys>0||!(await platformReady()))return;
  try{if(localStorage.getItem('agora-passkey-later')==='1')return}catch{}
  const dialog=$('#action-dialog');if(dialog.open)return;
  $('#dialog-content').innerHTML=`<h2>Entra con tu huella o tu cara</h2><p class="dialog-copy">La próxima vez no tendrás que escribir nada: Ágora te pedirá la huella, Face ID o el PIN de este dispositivo. Tu huella o tu cara nunca salen del teléfono.</p><div class="dialog-actions"><button id="offer-yes" class="primary">Activar</button><button id="offer-later" class="link-button">Ahora no</button></div><p id="offer-result" class="dialog-copy" role="status"></p>`;
  dialog.showModal();
  $('#offer-later').onclick=()=>{try{localStorage.setItem('agora-passkey-later','1')}catch{};dialog.close()};
  $('#offer-yes').onclick=async e=>{
    e.currentTarget.disabled=true;
    try{await enrollPasskey();dialog.close();toast('Listo. La próxima vez entra con tu huella o tu cara.')}
    catch(error){$('#offer-result').textContent=error.status?error.message:passkeyTrouble(error);e.target.disabled=false}
  };
}
async function enrollPasskey(){
  const csrf=workspace.me?.csrf||'';
  const {publicKey}=await api('passkeys/options','POST',{},csrf);
  await api('passkeys','POST',{credential:await createPasskey(publicKey)},csrf);
  rememberPasskey(true);
  if(workspace.me)workspace.me.passkeys=(workspace.me.passkeys||0)+1;
}

// ---- Seguridad y sesión -------------------------------------------------------------
const when=value=>{if(!value)return 'todavía no';const d=new Date(value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'medium',timeStyle:'short'}).format(d)};
async function openSecurity(){
  const dialog=$('#action-dialog');
  $('#dialog-content').innerHTML='<h2>Seguridad y sesión</h2><p class="dialog-copy">Cargando…</p>';
  if(!dialog.open)dialog.showModal();
  let data;
  try{data=await api('security')}catch(error){$('#dialog-content').innerHTML=`<h2>Seguridad y sesión</h2><p class="dialog-copy">${esc(error.message)}</p><button id="signout" class="primary">Cerrar sesión</button>`;$('#signout').onclick=signOut;return}
  data={team:null,lockMinutes:null,deviceRemembered:false,...data};
  for(const key of ['passkeys','sessions'])if(!Array.isArray(data[key]))data[key]=[];
  if(!Array.isArray(data.lockChoices))data.lockChoices=[5,15,30,60];
  const canPasskey=passkeysSupported();
  const hasKey=data.passkeys.length>0;
  const lockOptions=[['','Nunca'],...data.lockChoices.map(m=>[String(m),m<60?`Tras ${m} minutos sin usarla`:'Tras 1 hora sin usarla'])];
  const item=(title,detail,action)=>`<li><span><b>${esc(title)}</b><small>${esc(detail)}</small></span>${action||''}</li>`;
  const sessions=list=>list.map(s=>item(s.label+(s.current?' · este dispositivo':''),`${s.mine?'':s.email+' · '}Último uso: ${when(s.lastUsedAt)}`,s.current?'':`<button class="link-button" data-close-session="${s.id}">Cerrar</button>`)).join('');
  const keys=list=>list.map(k=>item(k.label||'Llave de acceso',`${k.mine?'':k.email+' · '}Último uso: ${when(k.lastUsedAt)}`,`<button class="link-button" data-remove-key="${k.id}">Quitar</button>`)).join('');
  const others=data.team?{sessions:data.team.sessions.filter(s=>!s.mine),keys:data.team.passkeys.filter(k=>!k.mine)}:null;
  const theme=globalThis.agoraTheme?.get?.()||'auto';
  $('#dialog-content').innerHTML=`<h2>Mi cuenta</h2>
   <section class="security-block"><h3>Apariencia</h3>
    <label class="security-lock">Tema<select id="theme-choice"><option value="auto"${theme==='auto'?' selected':''}>Automático (como el dispositivo)</option><option value="light"${theme==='light'?' selected':''}>Claro</option><option value="dark"${theme==='dark'?' selected':''}>Oscuro</option></select></label>
   </section>
   <section class="security-block"><h3>Este dispositivo</h3>
    ${canPasskey?(hasKey?'<p class="dialog-copy">Puedes entrar con tu huella, Face ID o el PIN del dispositivo.</p>':'<p class="dialog-copy">Entra sin escribir nada, con tu huella, Face ID o el PIN de este dispositivo.</p><button id="add-passkey" class="primary">Activar huella o Face ID</button>'):'<p class="dialog-copy">Este navegador no admite entrar con huella o cara.</p>'}
    <label class="security-lock">Pedir la huella o la cara al volver<select id="lock-minutes" ${hasKey&&data.deviceRemembered?'':'disabled'}>${lockOptions.map(([v,l])=>`<option value="${v}"${String(data.lockMinutes??'')===v?' selected':''}>${l}</option>`).join('')}</select></label>
    ${hasKey?'':'<small class="security-hint">Disponible cuando actives la huella o Face ID.</small>'}
    <p id="security-result" class="dialog-copy" role="status"></p>
   </section>
   ${hasKey?`<section class="security-block"><h3>Tus llaves de acceso</h3><ul class="security-list">${keys(data.passkeys)}</ul></section>`:''}
   <section class="security-block"><h3>Sesiones abiertas</h3><p class="dialog-copy">Cada dispositivo sigue dentro 60 días desde su último uso. Cierra la de un teléfono que ya no tengas.</p><ul class="security-list">${sessions(data.sessions)}</ul></section>
   ${others&&(others.sessions.length||others.keys.length)?`<section class="security-block"><h3>Equipo</h3>${others.sessions.length?`<ul class="security-list">${sessions(others.sessions)}</ul>`:''}${others.keys.length?`<h4>Llaves de acceso del equipo</h4><ul class="security-list">${keys(others.keys)}</ul>`:''}</section>`:''}
   <button id="signout" class="primary signout-button">Cerrar sesión en este dispositivo</button>`;
  const csrf=workspace.me?.csrf||'';
  const result=$('#security-result');
  $('#signout').onclick=signOut;
  $('#theme-choice').onchange=e=>globalThis.agoraTheme?.set?.(e.target.value);
  const add=$('#add-passkey');
  if(add)add.onclick=async()=>{add.disabled=true;try{await enrollPasskey();await openSecurity();toast('Listo. La próxima vez entra con tu huella o tu cara.')}catch(error){result.textContent=error.status?error.message:passkeyTrouble(error);add.disabled=false}};
  $('#lock-minutes').onchange=async e=>{
    const value=e.target.value;e.target.disabled=true;
    try{await api('security/lock','POST',{minutes:value===''?null:Number(value)},csrf);result.textContent=value?'Hecho. Al volver tras ese tiempo se pedirá tu huella o tu cara.':'Hecho. Ya no se pedirá al volver.'}
    catch(error){result.textContent=error.message}
    finally{e.target.disabled=false}
  };
  document.querySelectorAll('[data-close-session]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{await api('sessions/'+b.dataset.closeSession,'DELETE',null,csrf);await openSecurity()}catch(error){result.textContent=error.message;b.disabled=false}});
  document.querySelectorAll('[data-remove-key]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{await api('passkeys/'+b.dataset.removeKey,'DELETE',null,csrf);const me=await api('me');workspace.setProfile(me);if(!me.passkeys)rememberPasskey(false);await openSecurity()}catch(error){result.textContent=error.message;b.disabled=false}});
}
$('#profile').onclick=openSecurity;
$('#profile-top').onclick=openSecurity;
window.addEventListener('agora:logout',()=>{if(!$('#app').hidden)showSignin()});
// Desde la ficha de un contacto: abrir su conversación en la bandeja.
window.addEventListener('agora:open-conversation',event=>{
  const conversation=event.detail;if(!conversation?.id)return;
  workspace.pendingConversation=conversation;
  history.pushState(null,'','/conversations/'+encodeURIComponent(conversation.id));
  show('inbox',{push:false});
});

const requestForm=$('#team-login-request'),verifyForm=$('#team-login-verify');
requestForm.onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button'),email=new FormData(e.target).get('email');button.disabled=true;
  try{
    const r=await fetch('/api/team-login/request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email})});
    const data=await r.json();
    $('#team-request-result').textContent=data.message||data.error||'No se ha podido solicitar el código.';
    if(r.ok){verifyForm.elements.email.value=email;verifyForm.hidden=false;verifyForm.elements.code.focus()}
  }catch{$('#team-request-result').textContent='No se ha podido conectar con el servidor.'}
  finally{button.disabled=false}
};
verifyForm.onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
  try{
    const r=await fetch('/api/team-login/verify',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target)))});
    const data=await r.json();
    if(r.ok){await startSession();offerPasskey()}else $('#team-verify-result').textContent=data.error||'No se ha podido acceder.';
  }catch{$('#team-verify-result').textContent='No se ha podido conectar con el servidor.'}
  finally{button.disabled=false}
};
$('#login-form').onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
  try{
    const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target)))});
    const data=await r.json();
    if(r.ok){await startSession();offerPasskey()}else $('#login-result').textContent=data.error||'No se ha podido acceder.';
  }catch{$('#login-result').textContent='No se ha podido conectar con el servidor.'}
  finally{button.disabled=false}
};

// The inbox publishes what it read; the menu only shows it.
window.addEventListener('agora:unread',event=>{
  const badge=$('#nav-unread');if(!badge)return;
  const count=Number(event.detail)||0;
  badge.hidden=count<1;
  badge.textContent=count>99?'99+':String(count);
  // El mismo número en el icono de la app, donde el sistema lo admite.
  try{if(navigator.setAppBadge)(count>0?navigator.setAppBadge(count):navigator.clearAppBadge()).catch(()=>{})}catch{}
  badge.setAttribute('aria-label',`${count} conversaciones sin leer`);
});

function network(){const mark=$('#network');mark.textContent='○ Sin conexión';mark.hidden=navigator.onLine}
window.addEventListener('online',network);window.addEventListener('offline',network);network();

/**
 * Si hay algo escrito y sin enviar, que es lo único que justifica retrasar una
 * versión nueva. Sólo cuenta lo que se ve y lo que la persona cambió: el
 * formulario de entrada sigue guardando el correo y la contraseña aunque esté
 * oculto, y el selector de mes del tablero siempre tiene valor. Contarlos hacía
 * creer que siempre se estaba escribiendo, y la versión nueva no llegaba nunca.
 */
function hasUnsentText(){
  const TEXT=['text','email','tel','url','number','password',''];
  return [...document.querySelectorAll('textarea, input')].some(field=>{
    if(field.tagName==='INPUT'&&!TEXT.includes(field.type))return false;
    if(field.offsetParent===null||field.closest('[hidden]'))return false;
    return field.value.trim()!==''&&field.value!==field.defaultValue;
  });
}
globalThis.agoraHasUnsentText=hasUnsentText;

/**
 * Los avisos los pinta el service worker, que se actualiza aparte de la página.
 * Si se queda en una versión anterior, el pie lo dice: así no vuelve a pasar
 * desapercibido, como cuando los avisos mostraban un texto genérico.
 */
function checkWorker(){
  const worker=navigator.serviceWorker?.controller;if(!worker)return;
  const page=($('#app-version')?.textContent.match(/v(\d+\.\d+\.\d+)/)||[])[1];
  const channel=new MessageChannel();
  channel.port1.onmessage=event=>{
    const served=(String(event.data?.cache||'').match(/agora-shell-(\d+\.\d+\.\d+)/)||[])[1];
    const label=$('#app-version');
    if(!page||!served||!label)return;
    label.dataset.worker=served;
    if(served!==page)label.textContent=`ÁGORA · v${page} · avisos en v${served}: cierra y vuelve a abrir la app`;
  };
  try{worker.postMessage({type:'VERSION'},[channel.port2])}catch{}
}

// Offline shell. A new build installs itself: nobody should have to press
// anything. The only thing worth waiting for is unsent text, so the swap is
// held back while something is being written and applied as soon as it is not.
if('serviceWorker' in navigator){
  navigator.serviceWorker.register('/sw.js',{updateViaCache:'none'}).then(registration=>{
    let pending=null,timer=null;
    const busy=()=>document.querySelector('#action-dialog')?.open||hasUnsentText();
    const apply=()=>{
      if(!pending)return;
      if(busy()){clearTimeout(timer);timer=setTimeout(apply,5000);return}
      toast('Actualizando Ágora a la última versión…');
      pending.postMessage({type:'ACTIVATE'});
    };
    const take=worker=>{if(!worker)return;pending=worker;apply()};
    take(registration.waiting);
    registration.addEventListener('updatefound',()=>{
      const worker=registration.installing;
      worker?.addEventListener('statechange',()=>{if(worker.state==='installed'&&navigator.serviceWorker.controller)take(worker)});
    });
    // Look for a new build on every return to the app, not only on a cold start.
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)registration.update().catch(()=>{})});
    checkWorker();
    let reloading=false,hadController=!!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange',()=>{
      if(!hadController){hadController=true;return}
      if(!reloading){reloading=true;location.reload()}
    });
  }).catch(()=>{});
}

// A visit always starts from the real session: there is no sample workspace.
startSession().catch(showSignin);
export {toast};
