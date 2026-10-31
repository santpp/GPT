import {workspace} from './workspace.js';
import {renderIntegrations} from './integrations.js';
import {renderLiveInbox} from './live-inbox.js';
import {renderContacts} from './contacts.js';
import {renderDashboard} from './dashboard.js';
import {connectDevice,releaseDevice,isNative} from './native.js';
import {enable as enablePush,disable as disablePush,state as pushState,isApple,installed} from './push.js';
import {renderBoard} from './board.js';

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
  $('#signin').hidden=true;$('#app').hidden=false;
  $('#user-email').firstChild.textContent=me.user.email||'Mi cuenta';
  $('#user-initials').textContent=(me.user.email||'··').slice(0,2).toUpperCase();
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
  $('#app').hidden=true;$('#signin').hidden=false;
  view.agoraLiveDispose?.();
  view.innerHTML='';
}

$('#account').onchange=e=>{if(workspace.select(e.target.value))renderAccounts()};
workspace.onChange(()=>{renderAccounts();if(VIEWS[currentView].account)show(currentView,{push:false})});
document.querySelectorAll('button[data-view]').forEach(b=>b.onclick=()=>show(b.dataset.view));
window.addEventListener('popstate',()=>{const name=requested();if(name)show(name,{push:false})});

$('#signout').onclick=async()=>{
  await releaseDevice(workspace.me?.csrf||'');
  try{await api('logout','POST',null,workspace.me?.csrf||'')}catch{}
  window.dispatchEvent(new Event('agora:logout'));
  showSignin();
};
window.addEventListener('agora:logout',()=>{if(!$('#app').hidden)showSignin()});

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
    if(r.ok)await startSession();else $('#team-verify-result').textContent=data.error||'No se ha podido acceder.';
  }catch{$('#team-verify-result').textContent='No se ha podido conectar con el servidor.'}
  finally{button.disabled=false}
};
$('#login-form').onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
  try{
    const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target)))});
    const data=await r.json();
    if(r.ok)await startSession();else $('#login-result').textContent=data.error||'No se ha podido acceder.';
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
  }).catch(()=>toast('La caché offline no está disponible en este navegador.'));
}

// A visit always starts from the real session: there is no sample workspace.
startSession().catch(showSignin);
export {toast};
