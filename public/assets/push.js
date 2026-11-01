// Notifications on this device, without any app store. The browser subscribes
// to its own system's push service; Ágora only ever learns the address that
// service hands out. Nothing here runs by itself: the person presses a button.
const decode=value=>{
  const padded=(value+'='.repeat((4-value.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/');
  const raw=atob(padded);
  return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));
};
const encode=buffer=>btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');

export const supported=()=>'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;
// On iOS notifications only exist once the app is on the home screen.
export const isApple=()=>/iP(hone|ad|od)/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
export const installed=()=>window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;

async function api(path,body,csrf,method='POST'){
  const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})},...(body!==undefined?{body:JSON.stringify(body)}:{})});
  let data={};try{data=await r.json()}catch{}
  if(!r.ok)throw Object.assign(Error(data.error||'No se pudo completar la operación'),{status:r.status});
  return data;
}

/** What this device is: enough to tell two phones apart in the diagnostics. */
function label(){
  const ua=navigator.userAgent;
  const system=/iP(hone|ad|od)/.test(ua)?'iPhone o iPad':/Android/.test(ua)?'Android':/Mac/.test(ua)?'Mac':/Windows/.test(ua)?'Windows':'Navegador';
  return installed()?system+' (instalada)':system;
}

/** Whether this device already receives notices, without asking for anything. */
export async function state(){
  if(!supported())return isApple()&&!installed()?'needs-install':'unsupported';
  if(isApple()&&!installed())return 'needs-install';
  if(Notification.permission==='denied')return 'blocked';
  try{
    const registration=await navigator.serviceWorker.ready;
    return await registration.pushManager.getSubscription()?'on':'off';
  }catch{return 'off'}
}

/**
 * Subscribes this device. Must be called from a real tap: both Safari and
 * Chrome refuse to ask otherwise, and on iOS a refusal is permanent.
 */
export async function enable(csrf){
  if(!supported())return 'unsupported';
  if(isApple()&&!installed())return 'needs-install';
  const answer=await api('push-key',undefined,'','GET');
  if(!answer.ready||!answer.key)return {state:'unconfigured',reason:answer.error||null};
  const permission=await Notification.requestPermission();
  if(permission!=='granted')return 'blocked';
  const registration=await navigator.serviceWorker.ready;
  // userVisibleOnly is required, and on iOS every notice must be shown anyway.
  const subscription=await registration.pushManager.getSubscription()
    ||await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:decode(answer.key)});
  const json=subscription.toJSON();
  await api('devices',{token:subscription.endpoint,platform:'web',label:label(),
    keys:{p256dh:json.keys?.p256dh||encode(subscription.getKey('p256dh')),auth:json.keys?.auth||encode(subscription.getKey('auth'))}},csrf);
  return 'on';
}

/**
 * Rehace los avisos de este navegador cuando el permiso ya está dado pero algo se rompió: falta la suscripción
 * (el navegador la borró), el servidor ya no la tiene (el servicio de avisos la dio de baja, o era de otra persona
 * que usó antes este navegador). No pregunta nada: sólo actúa si el permiso ya se concedió.
 * Devuelve 'ok' (todo bien), 'renewed' (se rehízo), 'skipped' (no aplica) o 'failed'.
 */
export async function heal(csrf){
  try{
    if(!supported()||Notification.permission!=='granted')return 'skipped';
    if(isApple()&&!installed())return 'skipped';
    const registration=await navigator.serviceWorker.ready;
    let subscription=await registration.pushManager.getSubscription();
    if(subscription){
      const known=await api('devices?token='+encodeURIComponent(subscription.endpoint),undefined,'','GET').catch(()=>null);
      if(known&&known.registered)return 'ok';
      // El servidor no la tiene a nombre de quien está dentro: se empieza de cero con una suscripción nueva.
      try{await subscription.unsubscribe()}catch{}
      subscription=null;
    }
    const answer=await api('push-key',undefined,'','GET');
    if(!answer.ready||!answer.key)return 'skipped';
    subscription=await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:decode(answer.key)});
    const json=subscription.toJSON();
    await api('devices',{token:subscription.endpoint,platform:'web',label:label(),
      keys:{p256dh:json.keys?.p256dh||encode(subscription.getKey('p256dh')),auth:json.keys?.auth||encode(subscription.getKey('auth'))}},csrf);
    return 'renewed';
  }catch{return 'failed'}
}
/** Compatibilidad con el nombre anterior. */
export const claim=heal;

/** Stops this device from being notified, here and in the browser. */
export async function disable(csrf){
  try{
    const registration=await navigator.serviceWorker.ready;
    const subscription=await registration.pushManager.getSubscription();
    if(!subscription)return 'off';
    await api('devices',{token:subscription.endpoint},csrf,'DELETE').catch(()=>{});
    await subscription.unsubscribe();
  }catch{}
  return 'off';
}
