// Entrar con huella, cara o el PIN del teléfono (llaves de acceso). El teléfono
// guarda la llave y la desbloquea; Ágora sólo recibe una firma y la comprueba.
const MARK='agora-passkey';

const bytes=value=>Uint8Array.from(atob(value.replace(/-/g,'+').replace(/_/g,'/')+'==='.slice((value.length+3)%4)),c=>c.charCodeAt(0));
const text=buffer=>btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');

export const supported=()=>typeof window.PublicKeyCredential==='function'&&!!navigator.credentials;

/** ¿Tiene este aparato huella, cara o PIN que sirva? */
export async function platformReady(){
  if(!supported())return false;
  try{return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()}catch{return false}
}

/** Este dispositivo ya tiene llave para Ágora: la pantalla de acceso ofrece el botón. */
export const remembered=()=>{try{return localStorage.getItem(MARK)==='1'}catch{return false}};
export const remember=on=>{try{on?localStorage.setItem(MARK,'1'):localStorage.removeItem(MARK)}catch{}};

/** Crea la llave con las opciones del servidor y devuelve lo que hay que mandarle. */
export async function create(options){
  const publicKey={...options,challenge:bytes(options.challenge),user:{...options.user,id:bytes(options.user.id)},
    excludeCredentials:(options.excludeCredentials||[]).map(c=>({...c,id:bytes(c.id)}))};
  const credential=await navigator.credentials.create({publicKey});
  return {id:credential.id,rawId:text(credential.rawId),type:credential.type,
    response:{clientDataJSON:text(credential.response.clientDataJSON),attestationObject:text(credential.response.attestationObject)}};
}

/** Pide la huella o la cara para firmar el reto del servidor. */
export async function sign(options){
  const publicKey={...options,challenge:bytes(options.challenge)};
  if(options.allowCredentials)publicKey.allowCredentials=options.allowCredentials.map(c=>({...c,id:bytes(c.id)}));
  const credential=await navigator.credentials.get({publicKey});
  return {id:credential.id,rawId:text(credential.rawId),type:credential.type,
    response:{clientDataJSON:text(credential.response.clientDataJSON),authenticatorData:text(credential.response.authenticatorData),
      signature:text(credential.response.signature),userHandle:credential.response.userHandle?text(credential.response.userHandle):null}};
}

/** Lo que se le dice a la persona si el teléfono no completó el paso. */
export function trouble(error){
  if(error?.name==='NotAllowedError')return 'Se canceló o se agotó el tiempo. Vuelve a intentarlo cuando quieras.';
  if(error?.name==='InvalidStateError')return 'Este dispositivo ya tiene una llave de acceso para ti.';
  if(error?.name==='NotSupportedError'||error?.name==='SecurityError')return 'Este navegador no permite llaves de acceso aquí. Prueba desde la app instalada o con el navegador del teléfono.';
  return error?.message||'No se pudo completar. Vuelve a intentarlo.';
}
