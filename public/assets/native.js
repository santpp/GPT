// The bridge to the Android shell. On the web it does nothing at all: the
// plugin is only there when the app runs inside the native wrapper, and
// everything here is wrapped so a missing plugin can never break a screen.
const plugin=()=>globalThis.Capacitor?.Plugins?.PushNotifications||null;
export const isNative=()=>globalThis.Capacitor?.isNativePlatform?.()===true;

async function post(path,body,csrf,method='POST'){
  const r=await fetch('/api/'+path,{method,headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})},body:JSON.stringify(body)});
  if(!r.ok)throw Object.assign(Error('No se pudo registrar el dispositivo'),{status:r.status});
  return r.json();
}

/**
 * Asks the phone for permission, registers the token Ágora should notify, and
 * takes a notification tap to the conversation it refers to. Returns what
 * happened so the caller can report it without guessing.
 */
export async function connectDevice(csrf,navigate){
  const push=plugin();
  if(!isNative()||!push)return {state:'unsupported'};
  try{
    let status=await push.checkPermissions();
    if(status.receive!=='granted')status=await push.requestPermissions();
    if(status.receive!=='granted')return {state:'denied'};
    const registered=new Promise(resolve=>{
      push.addListener('registration',token=>resolve(token?.value||null));
      push.addListener('registrationError',()=>resolve(null));
    });
    await push.register();
    const token=await Promise.race([registered,new Promise(resolve=>setTimeout(()=>resolve(null),15000))]);
    if(!token)return {state:'failed'};
    const answer=await post('devices',{token,platform:'android'},csrf);
    // A tap goes to the conversation; the notice itself carries no message text.
    push.addListener('pushNotificationActionPerformed',event=>{
      const path=event?.notification?.data?.path;
      if(typeof path==='string'&&/^\/[A-Za-z0-9/_-]{0,120}$/.test(path))navigate?.(path);
    });
    return {state:'registered',token,notifications:answer.notifications===true};
  }catch(error){return {state:'failed'}}
}

/** Signing out on a phone must stop that phone from being notified. */
export async function releaseDevice(csrf){
  const push=plugin();
  if(!isNative()||!push)return;
  try{
    const token=globalThis.agoraDeviceToken;
    if(typeof token==='string'&&token)await post('devices',{token},csrf,'DELETE');
    await push.removeAllListeners?.();
  }catch{}
}
