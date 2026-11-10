import './maintenance.js';
import {workspace} from './workspace.js';
import {skeleton} from './skeleton.js';
import {renderIntegrations} from './integrations.js';
import {renderLiveInbox} from './live-inbox.js';
import {renderContacts} from './contacts.js';
import {renderDashboard} from './dashboard.js';
import {connectDevice,releaseDevice,isNative} from './native.js';
import {enable as enablePush,disable as disablePush,heal as healPush,state as pushState,isApple,installed} from './push.js';
import {renderBoard} from './board.js';
import {windowMarkup} from './announce.js';
import {startWonAlert} from './won-alert.js';
import {runTour,tourDone} from './tour.js';
import {initHelp,renderHelp} from './help.js';
import {renderConsumption} from './consumption.js';
import {supported as passkeysSupported,platformReady,remembered as passkeyRemembered,remember as rememberPasskey,create as createPasskey,sign as signPasskey,trouble as passkeyTrouble} from './passkeys.js';

const $=s=>document.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const view=$('#view');

const VIEWS={
  dashboard:{path:'/tablero',title:'Tablero',eyebrow:'TU DÍA',heading:'Así va tu cuenta.',subtitle:'Lo que espera respuesta y lo que está en juego.',render:renderDashboard,account:true},
  inbox:{path:'/bandeja',title:'Conversaciones',eyebrow:'BANDEJA',heading:'Cada conversación cuenta.',subtitle:'Todo el contexto, en un solo lugar.',render:renderLiveInbox,account:true},
  contacts:{path:'/contactos',title:'Contactos',eyebrow:'TU GENTE',heading:'Personas, no registros.',subtitle:'Tu siguiente conversación empieza aquí.',render:renderContacts,account:true},
  board:{path:'/oportunidades',title:'Oportunidades',eyebrow:'EMBUDO',heading:'De la conexión al cierre.',subtitle:'Cada oportunidad abierta, a la vista.',render:renderBoard,account:true},
  help:{path:'/ayuda',title:'Ayuda',eyebrow:'MANUAL',heading:'Manual de uso.',subtitle:'Lo básico para trabajar en Ágora.',render:renderHelp,account:false},
  consumption:{path:'/consumos',title:'Consumos',eyebrow:'ADMINISTRACIÓN',heading:'Lo que gasta cada subcuenta.',subtitle:'El consumo del mes y el plan de cada subcuenta, desde la agencia.',render:renderConsumption,account:false},
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

// ---- Menú de abajo: «Más» --------------------------------------------------------
// En el teléfono la barra muestra cuatro pestañas; lo demás (sólo la propietaria) va en un panel que sube desde abajo.
const MORE_VIEWS=['consumption','settings','help'];
const DOTS='<svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
const HELP_ICON='<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.2-2.4 3.7"/><path d="M12 17h.01"/></svg>';
function setupMore(){
  const nav=document.querySelector('.sidebar nav');
  if(!nav||document.getElementById('nav-more'))return;
  const button=document.createElement('button');
  button.type='button';button.id='nav-more';button.className='nav nav-more';button.hidden=true;button.dataset.adminOnly='';
  button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-expanded','false');
  button.innerHTML=`<span aria-hidden="true">${DOTS}</span> Más`;
  nav.append(button);
  const sheet=document.createElement('div');
  sheet.id='more-sheet';sheet.hidden=true;
  sheet.innerHTML='<div class="more-panel" role="dialog" aria-modal="true" aria-labelledby="more-title"><div class="more-grip" aria-hidden="true"></div><h2 id="more-title">Más</h2><div class="more-grid"></div></div>';
  document.body.append(sheet);
  button.onclick=()=>sheet.hidden?openMore():closeMore();
  sheet.onclick=e=>{if(e.target===sheet)closeMore()};
  sheet.addEventListener('keydown',e=>{if(e.key==='Escape'){closeMore();button.focus()}});
}
function openMore(){
  const sheet=document.getElementById('more-sheet');if(!sheet)return;
  const items=MORE_VIEWS.map(view=>{
    if(view==='help')return {view,label:'Ayuda',icon:HELP_ICON};
    const source=document.querySelector(`.sidebar nav .nav[data-view="${view}"]`);
    if(!source||source.hidden)return null;
    return {view,label:source.textContent.trim(),icon:source.querySelector('span')?.innerHTML||''};
  }).filter(Boolean);
  sheet.querySelector('.more-grid').innerHTML=items.map((i,n)=>`<button type="button" data-more="${i.view}" style="--i:${n}" class="${i.view===currentView?'active':''}">${i.icon}<span>${esc(i.label)}</span></button>`).join('');
  sheet.querySelectorAll('[data-more]').forEach(b=>b.onclick=()=>show(b.dataset.more));
  sheet.classList.remove('closing');sheet.hidden=false;
  document.getElementById('nav-more')?.setAttribute('aria-expanded','true');
  sheet.querySelector('[data-more]')?.focus();
}
function closeMore(){
  const sheet=document.getElementById('more-sheet');
  if(!sheet||sheet.hidden||sheet.classList.contains('closing'))return;
  document.getElementById('nav-more')?.setAttribute('aria-expanded','false');
  // Baja antes de esconderse; sin animación (movimiento reducido) se esconde al momento.
  const done=()=>{sheet.classList.remove('closing');sheet.hidden=true};
  if(matchMedia('(prefers-reduced-motion: reduce)').matches)return done();
  sheet.classList.add('closing');
  const end=e=>{if(e.target!==sheet)return;sheet.removeEventListener('animationend',end);if(sheet.classList.contains('closing'))done()};
  sheet.addEventListener('animationend',end);
  setTimeout(()=>{if(sheet.classList.contains('closing'))done()},400);
}

function show(name,{push=true}={}){
  const target=VIEWS[name]?name:'inbox';
  view.agoraLiveDispose?.();
  currentView=target;
  const meta=VIEWS[target];
  if(push&&location.pathname!==meta.path)history.pushState(null,'',meta.path);
  document.querySelectorAll('button[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===target));
  document.getElementById('nav-more')?.classList.toggle('active',MORE_VIEWS.includes(target));
  closeMore();
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
const BELL='<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/></svg>';
const BELL_OFF='<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/><path d="M4 4l16 16"/></svg>';
const PUSH_TEXT={
  off:'Activar avisos',
  on:'Avisos activos',
  blocked:'Avisos bloqueados',
  'needs-install':'Instala Ágora para recibir avisos',
  unconfigured:'Avisos no configurados',
  unsupported:'Sin avisos en este navegador',
};
const PUSH_HELP={
  off:'Los avisos están desactivados en este dispositivo. Actívalos para no perder mensajes.',
  on:'Este dispositivo recibe avisos. Toca para desactivarlos.',
  blocked:'El navegador tiene bloqueados los avisos de Ágora. Toca el candado junto a la dirección, entra en Permisos y activa Notificaciones; al volver aquí se activarán solos.',
  'needs-install':'Para recibir avisos, añade Ágora a la pantalla de inicio.',
  unconfigured:'Los avisos aún no están configurados en el servidor.',
  unsupported:'Este navegador no admite avisos.',
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
  button.querySelector('.push-mark').innerHTML=pushNow==='on'?BELL:BELL_OFF;
  button.title=PUSH_HELP[pushNow]||'';button.setAttribute('aria-label',PUSH_TEXT[pushNow]||PUSH_TEXT.off);
  paintNudge();
}
// Si los avisos no están activos, se le dice a la persona, una vez al día como mucho.
const NUDGE_KEY='agora-push-nudge';
const nudgeSnoozed=()=>{try{return Date.now()-Math.max(Number(localStorage.getItem(NUDGE_KEY)||0),Number(localStorage.getItem('agora-welcome-later')||0))<864e5}catch{return false}};
function paintNudge(){
  const bar=$('#push-banner');if(!bar)return;
  const needs=['off','blocked','needs-install'].includes(pushNow);
  if(!needs||nudgeSnoozed()||$('#app').hidden||($('#welcome')&&!$('#welcome').hidden)){bar.hidden=true;return}
  const action=pushNow==='off'?'<button type="button" class="primary" id="push-nudge-go">Activar avisos</button>':'';
  bar.innerHTML=`<p>${esc(PUSH_HELP[pushNow])}</p><div class="push-banner-actions">${action}<button type="button" class="link-button" id="push-nudge-later">Ahora no</button></div>`;
  bar.hidden=false;
  const go=$('#push-nudge-go');if(go)go.onclick=()=>$('#push-toggle').click();
  $('#push-nudge-later').onclick=()=>{try{localStorage.setItem(NUDGE_KEY,String(Date.now()))}catch{}bar.hidden=true};
}
// Al volver a la pestaña (por ejemplo, tras activar el permiso en los ajustes) se comprueba y, si hace falta, se rehacen los avisos.
let healing=false;
async function checkPush(){
  if(healing||$('#app').hidden||isNative())return;
  healing=true;
  try{await healPush(workspace.me?.csrf||'');await paintPush()}finally{healing=false}
}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')checkPush()});
$('#push-toggle').onclick=async()=>{
  const button=$('#push-toggle');
  if(pushNow==='needs-install'){
    toast(isApple()
      ? 'En iPhone: toca Compartir en Safari y elige «Añadir a pantalla de inicio». Abre Ágora desde ese icono y vuelve a pulsar aquí.'
      : 'Instala Ágora desde el menú del navegador («Instalar aplicación») y vuelve a pulsar aquí.');
    return;
  }
  if(pushNow==='blocked'){toast(PUSH_HELP.blocked);return}
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

// ---- Novedades: lo que escribe el administrador, una vez al entrar ----------------------
let announcing=false;
/** Muestra las novedades pendientes, una tras otra; devuelve una promesa que se cumple cuando ya no queda ninguna en pantalla. */
function announce(tries=0){
  return new Promise(done=>announceNow(tries,done));
}
async function announceNow(tries,done){
  if(announcing||$("#app").hidden)return done();
  const dialog=$('#action-dialog');
  // Si hay otra cosa en pantalla (bienvenida, huella, un formulario), se espera.
  if(dialog.open||!$('#welcome').hidden){if(tries<8)setTimeout(()=>announceNow(tries+1,done),3000);else done();return}
  let items;
  try{items=(await api('announcements')).items}catch{return done()}
  if(!Array.isArray(items)||!items.length)return done();
  announcing=true;
  const csrf=workspace.me?.csrf||'';
  const showOne=index=>{
    const item=items[index];
    if(!item){announcing=false;return done()}
    $('#dialog-content').innerHTML=windowMarkup(item);
    dialog.showModal();
    // Cerrada de la forma que sea (botón, ×, Esc), cuenta como vista.
    const closed=()=>{dialog.removeEventListener('close',closed);api(`announcements/${item.id}/read`,'POST',{},csrf).catch(()=>{});setTimeout(()=>showOne(index+1),250)};
    dialog.addEventListener('close',closed);
    $('#announce-ok').onclick=()=>dialog.close();
    document.querySelectorAll('[data-announce-link]').forEach(a=>a.onclick=event=>{
      const url=a.getAttribute('href');
      if(url&&url.startsWith('/')){event.preventDefault();const name=byPath(url);dialog.close();if(name)show(name)}
    });
  };
  showOne(0);
}
// ---- Bienvenida: instalar la app y activar los avisos, a un toque ---------------------
// El sistema exige que la persona toque «Permitir»: ninguna web puede activar los
// avisos sola. Lo que sí se hace es pedirlo en cuanto entra, sin ir a buscarlo.
let installPrompt=null;
window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();installPrompt=event;if(!$('#app').hidden)welcome()});
window.addEventListener('appinstalled',()=>{installPrompt=null;welcome()});
const LATER='agora-welcome-later';
const resting=()=>{try{return Date.now()-Number(localStorage.getItem(LATER)||0)<3*86400000}catch{return false}};
let welcomeDone=null;
async function welcome(){
  const sheet=$('#welcome');if(!sheet||$('#app').hidden||isNative())return finishWelcome();
  if(resting())return finishWelcome();
  // Si el service worker tarda (primera visita), no se bloquea lo que viene detrás.
  const notices=await Promise.race([pushState().catch(()=>'unsupported'),new Promise(done=>setTimeout(()=>done('unknown'),3000))]);
  let step=null;
  if(!installed()&&(installPrompt||isApple()))step='install';
  else if(notices==='off')step='notices';
  if(!step){sheet.hidden=true;return finishWelcome()}
  const apple=isApple();
  $('#welcome-title').textContent=step==='install'?'Instala Ágora en tu teléfono':'Activa los avisos';
  $('#welcome-copy').textContent=step==='install'
    ?(apple?'Toca Compartir (el cuadrado con la flecha) y elige «Añadir a pantalla de inicio». Después abre Ágora desde su icono: así llegan los avisos.':'Se abre como una app, con su icono, y los avisos llegan aunque esté cerrada.')
    :'Para enterarte de cada mensaje nuevo, aunque Ágora esté cerrada. El teléfono te pedirá permiso: toca «Permitir».';
  const go=$('#welcome-go');
  go.textContent=step==='install'?(apple?'Entendido':'Instalar'):'Activar avisos';
  go.disabled=false;sheet.hidden=false;sheet.dataset.step=step;
  go.onclick=async()=>{
    go.disabled=true;
    if(step==='install'){
      if(apple){sheet.hidden=true;try{localStorage.setItem(LATER,String(Date.now()))}catch{};return finishWelcome()}
      try{await installPrompt.prompt();const choice=await installPrompt.userChoice;installPrompt=null;if(choice?.outcome!=='accepted'){sheet.hidden=true;try{localStorage.setItem(LATER,String(Date.now()))}catch{};return finishWelcome()}}catch{}
      return welcome();
    }
    try{
      const answer=await enablePush(workspace.me?.csrf||'');
      const result=typeof answer==='string'?answer:answer.state;
      toast(result==='on'?'Listo: este dispositivo avisará de los mensajes nuevos.':result==='blocked'?'El teléfono no dio permiso. Puedes activarlo en sus ajustes cuando quieras.':'No se pudieron activar los avisos ahora.');
    }catch{toast('No se pudieron activar los avisos ahora.')}
    sheet.hidden=true;await paintPush();finishWelcome();
  };
  $('#welcome-later').onclick=()=>{try{localStorage.setItem(LATER,String(Date.now()))}catch{};sheet.hidden=true;finishWelcome()};
}
function finishWelcome(){const done=welcomeDone;welcomeDone=null;if(done)done()}
/** La bienvenida y, cuando termina, lo que venga detrás (la oferta de la huella). */
function welcomeThen(next){welcomeDone=next;welcome()}

// ---- Qué se muestra al entrar, y en qué orden ------------------------------------------
// Una sola ventana a la vez: tutorial (sólo vendedores, una vez por dispositivo) o, si no, la invitación a instalar y
// activar avisos; después la huella (al iniciar sesión), las novedades y, al final, la alerta de ventas sin monto.
// La alerta urgente pasa justo detrás del tutorial. La alerta se consulta a los 4 segundos igualmente: sólo espera su turno.
const defer=()=>{let resolve;const promise=new Promise(r=>resolve=r);return {promise,resolve}};
let gate={tutorial:defer(),all:defer()};
const gateFor=which=>Promise.race([(which==='tutorial'?gate.tutorial:gate.all).promise,new Promise(r=>setTimeout(r,90000))]);
const isSeller=me=>me?.user?.identity_type==='external'&&me?.user?.capabilities?.assignedDataOnly===true;
const welcomeStep=()=>new Promise(done=>{welcomeDone=done;welcome()});
async function runTutorial(force=false){
  if(isNative())return false;
  if(!force&&(tourDone()||!isSeller(workspace.me)))return false;
  await new Promise(r=>setTimeout(r,300));
  return runTour({pushState:()=>pushNow,activatePush:()=>$('#push-toggle').click()});
}
async function startup(fresh){
  try{
    const shown=await runTutorial();
    gate.tutorial.resolve();
    if(!shown)await welcomeStep();
    if(fresh)await offerPasskey();
    await announce();
  }catch{/* un fallo de una ventana no impide las siguientes */}
  finally{gate.tutorial.resolve();gate.all.resolve()}
}

function renderAccounts(){
  const select=$('#account'),box=$('.account-switch');
  const many=workspace.connections.length>1;
  select.innerHTML=workspace.connections.map(c=>`<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
  select.value=workspace.current();
  select.disabled=!many;
  $('#account-name').textContent=workspace.name()||'Sin cuenta';
  // Con una sola subcuenta no hay nada que elegir: se muestra su nombre y se entra directo.
  box.classList.toggle('single',!many);
  const label=box.querySelector(':scope > span');if(label)label.textContent=many?'Cuenta':(workspace.name()||'');
  box.hidden=!VIEWS[currentView].account||!workspace.connections.length;
}

/** «Ágora v1.0»: la versión del pie, sin el último número. */
const versionLabel=()=>{const v=($('#app-version')?.textContent.match(/v(\d+)\.(\d+)\.\d+/)||[]);return v[1]?`Ágora v${v[1]}.${v[2]}`:'Ágora'};
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
  $('#menu-version').textContent=versionLabel();
  setupMore();
  document.querySelectorAll('[data-admin-only]').forEach(n=>n.hidden=me.user.role!=='administrator');
  try{const {items,reason}=await api('workspace/connections');workspace.setConnections(items,reason)}
  catch{workspace.setConnections([])}
  renderAccounts();
  paintViewAs(me.viewAs);
  // Los avisos del navegador pasan a ser de quien entra ahora, y se rehacen si se perdieron.
  // Mirando como otra persona no se registra nada: los avisos siguen siendo de la propietaria.
  if(!me.viewAs)checkPush();
  // Ventas ganadas sin monto: se consulta 4 segundos después de entrar.
  gate={tutorial:defer(),all:defer()};
  startWonAlert(gateFor);
  // Un aviso trae a su conversación: /conversations/{id}?c={subcuenta}.
  let deep=location.pathname.match(/^\/conversations\/([A-Za-z0-9_-]{1,80})$/);
  let wanted=deep?new URLSearchParams(location.search).get('c'):null;
  // Si la página se recarga antes de abrir la conversación (por ejemplo, al actualizarse la app), no se pierde.
  try{
    if(deep)sessionStorage.setItem('agora-open',JSON.stringify({id:deep[1],c:wanted,at:Date.now()}));
    else{const kept=JSON.parse(sessionStorage.getItem('agora-open')||'null');if(kept&&Date.now()-kept.at<60000){deep=[null,kept.id];wanted=kept.c}else sessionStorage.removeItem('agora-open')}
  }catch{}
  if(deep){
    if(wanted&&workspace.connections.some(c=>String(c.id)===wanted)&&workspace.select(wanted))renderAccounts();
    workspace.pendingConversation={id:deep[1],explicit:!!wanted};
    history.replaceState(null,'','/bandeja');
  }
  show(requested()||'inbox',{push:false});
  paintPush();
  // Inside the Android shell the phone registers itself so it can be notified.
  if(isNative())connectDevice(me.csrf,path=>{const name=byPath(path);if(name)show(name);else{const m=path.match(/^\/conversations\/([A-Za-z0-9_-]{1,80})$/);if(m){workspace.pendingConversation={id:m[1],explicit:false};history.pushState(null,'','/bandeja');show('inbox',{push:false})}}})
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
workspace.onChange(()=>{startWonAlert(gateFor);renderAccounts();if(VIEWS[currentView].account)show(currentView,{push:false})});
document.querySelectorAll('button[data-view]').forEach(b=>b.onclick=()=>show(b.dataset.view));
window.addEventListener('agora:goto',e=>{const d=e.detail||{};if(!VIEWS[d.view])return;workspace.pendingFilter=d.filter||null;show(d.view)});
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
  const closedPromise=new Promise(r=>dialog.addEventListener('close',r,{once:true}));
  $('#dialog-content').innerHTML=`<h2>Entra con tu huella o tu cara</h2><p class="dialog-copy">La próxima vez no tendrás que escribir nada: Ágora te pedirá la huella, Face ID o el PIN de este dispositivo. Tu huella o tu cara nunca salen del teléfono.</p><div class="dialog-actions"><button id="offer-yes" class="primary">Activar</button><button id="offer-later" class="link-button">Ahora no</button></div><p id="offer-result" class="dialog-copy" role="status"></p>`;
  dialog.showModal();
  $('#offer-later').onclick=()=>{try{localStorage.setItem('agora-passkey-later','1')}catch{};dialog.close()};
  $('#offer-yes').onclick=async e=>{
    e.currentTarget.disabled=true;
    try{await enrollPasskey();dialog.close();toast('Listo. La próxima vez entra con tu huella o tu cara.')}
    catch(error){$('#offer-result').textContent=error.status?error.message:passkeyTrouble(error);e.target.disabled=false}
  };
  await closedPromise;
}
async function enrollPasskey(){
  const csrf=workspace.me?.csrf||'';
  const {publicKey}=await api('passkeys/options','POST',{},csrf);
  await api('passkeys','POST',{credential:await createPasskey(publicKey)},csrf);
  rememberPasskey(true);
  if(workspace.me)workspace.me.passkeys=(workspace.me.passkeys||0)+1;
}

// ---- Entrar como ------------------------------------------------------------------
/** Franja fija mientras la propietaria mira Ágora como otra persona. */
function paintViewAs(viewAs){
  let bar=document.getElementById('view-as-bar');
  if(!viewAs){bar?.remove();document.body.classList.remove('viewing-as');return}
  if(!bar){bar=document.createElement('div');bar.id='view-as-bar';bar.setAttribute('role','status');document.body.prepend(bar)}
  bar.innerHTML=`<span>Viendo como <b>${esc(viewAs.name)}</b> · ${esc(viewAs.connection)} · sólo lectura</span><button type="button" id="view-as-stop">Volver a mi vista</button>`;
  document.body.classList.add('viewing-as');
  $('#view-as-stop').onclick=stopViewAs;
}
async function stopViewAs(){
  try{await api('view-as/stop','POST',{},workspace.me?.csrf||'')}catch{}
  location.reload();
}
async function openViewAs(){
  const box=$('#dialog-content');
  box.innerHTML='<h2>Entrar como</h2>'+skeleton('lines',4);
  let data;
  try{data=await api('admin/view-as')}catch(error){box.innerHTML=`<h2>Entrar como</h2><p class="dialog-copy">${esc(error.message)}</p>`;return}
  const groups=data.connections||[];
  box.innerHTML=`<h2>Entrar como</h2><p class="dialog-copy">Elige a quién. Verás lo mismo que esa persona en su subcuenta, durante una hora como máximo.</p>
   ${groups.length?groups.map(g=>`<section class="security-block"><h3>${esc(g.name)}</h3><ul class="security-list view-as-list">${g.users.map(u=>`<li><span><b>${esc(u.name)}</b><small>${esc(u.email||'')}${u.admin?' · administra la subcuenta':' · ve sólo lo asignado'}</small></span><button type="button" class="link-button" data-view-as="${u.id}">Entrar</button></li>`).join('')}</ul></section>`).join(''):'<p class="dialog-copy">Todavía no hay usuarios sincronizados en ninguna subcuenta.</p>'}
   <p id="view-as-result" class="dialog-copy" role="status"></p>`;
  box.querySelectorAll('[data-view-as]').forEach(b=>b.onclick=async()=>{
    b.disabled=true;
    try{await api('admin/view-as','POST',{userId:Number(b.dataset.viewAs)},workspace.me?.csrf||'');location.reload()}
    catch(error){$('#view-as-result').textContent=error.message;b.disabled=false}
  });
}

// ---- Seguridad y sesión -------------------------------------------------------------
const when=value=>{if(!value)return 'todavía no';const d=new Date(value);return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('es',{dateStyle:'medium',timeStyle:'short'}).format(d)};
async function openSecurity(){
  const dialog=$('#action-dialog');
  $('#dialog-content').innerHTML='<h2>Seguridad y sesión</h2>'+skeleton('lines',4);
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
    <label class="security-check"><input type="checkbox" id="text-large"${globalThis.agoraText?.get?.()==='large'?' checked':''}><span><b>Texto grande</b><small>Letra más grande y en negrita, en este dispositivo.</small></span></label>
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
   ${workspace.me?.user?.identity_type==='native'&&workspace.me?.user?.role==='administrator'?'<section class="security-block security-maintenance"><h3>Mantenimiento</h3><p class="dialog-copy">Mientras esté activo, tu equipo ve la pantalla de mantenimiento. Tú sigues entrando con normalidad.</p><label class="security-check"><input type="checkbox" id="maintenance-on" disabled><span><b>Poner Ágora en mantenimiento</b><small id="maintenance-state">Comprobando…</small></span></label></section>':''}
   ${workspace.me?.viewAs?`<section class="security-block"><h3>Entrar como</h3><p class="dialog-copy">Ahora ves Ágora como ${esc(workspace.me.viewAs.name)}, en ${esc(workspace.me.viewAs.connection)}.</p><button id="view-as-stop-dialog" class="secondary" type="button">Volver a mi vista</button></section>`:workspace.me?.canViewAs?'<section class="security-block"><h3>Entrar como</h3><p class="dialog-copy">Mira Ágora como cualquier usuario de una subcuenta: sus pestañas, sus permisos y sólo lo que tiene asignado. Es sólo para mirar; no se puede enviar ni cambiar nada en su nombre, y queda anotado.</p><button id="view-as-open" class="secondary" type="button">Elegir usuario…</button></section>':''}
   <button id="signout" class="primary signout-button">Cerrar sesión en este dispositivo</button><small class="account-version">${esc(versionLabel())}</small>`;
  const csrf=workspace.me?.csrf||'';
  const result=$('#security-result');
  $('#signout').onclick=signOut;
  const viewAsOpen=$('#view-as-open');if(viewAsOpen)viewAsOpen.onclick=openViewAs;
  const viewAsStop=$('#view-as-stop-dialog');if(viewAsStop)viewAsStop.onclick=stopViewAs;
  $('#theme-choice').onchange=e=>globalThis.agoraTheme?.set?.(e.target.value);
  const maintenance=$('#maintenance-on');
  if(maintenance){
    const label=$('#maintenance-state'),paint=on=>{maintenance.checked=on;label.textContent=on?'Activo: tu equipo ve la pantalla de mantenimiento.':'Apagado: todo el equipo trabaja con normalidad.'};
    api('admin/maintenance','GET',null,csrf).then(state=>{paint(!!state.on);maintenance.disabled=false}).catch(()=>{label.textContent='No se pudo consultar ahora.'});
    maintenance.onchange=async()=>{const want=maintenance.checked;maintenance.disabled=true;try{const state=await api('admin/maintenance','POST',{on:want},csrf);paint(!!state.on)}catch(error){paint(!want);label.textContent=error.message}maintenance.disabled=false};
  }
  $('#text-large').onchange=e=>globalThis.agoraText?.set?.(e.target.checked?'large':'normal');
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
window.addEventListener('agora:toast',event=>toast(String(event.detail||'')));
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
    if(r.ok){await startSession();startup(true)}else $('#team-verify-result').textContent=data.error||'No se ha podido acceder.';
  }catch{$('#team-verify-result').textContent='No se ha podido conectar con el servidor.'}
  finally{button.disabled=false}
};
$('#login-form').onsubmit=async e=>{
  e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
  try{
    const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target)))});
    const data=await r.json();
    if(r.ok){await startSession();startup(true)}else $('#login-result').textContent=data.error||'No se ha podido acceder.';
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
startSession().then(()=>{if(!$('#app').hidden)startup(false)}).catch(showSignin);
initHelp({openManual:()=>show('help'),startTour:()=>runTutorial(true)});
export {toast};
