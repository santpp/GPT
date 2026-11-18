// 1.10.21 · Contratos: la página de contratos de la subcuenta, dentro de Ágora y sin salir de la app.
// La dirección la activa quien administra Ágora en Conexiones (por subcuenta). Todos los de esa subcuenta la ven.
import {workspace} from './workspace.js';
import {skeleton} from './skeleton.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
/** Dirección de contratos de la subcuenta en uso, o null. */
export function contractsUrl(){
  const c=workspace.connections.find(x=>String(x.id)===workspace.current());
  const url=typeof c?.contracts_url==='string'?c.contracts_url.trim():'';
  try{const u=new URL(url);return u.protocol==='https:'?u.href:null}catch{return null}
}
const embedded=url=>{const u=new URL(url);u.searchParams.set('embed','1');return u.href};

// Volver: una flecha dentro de un círculo pequeño, sobre la página de contratos.
const BACK='<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 6 8.5 12l6 6"/></svg>';

/** 1.10.22 · La página ocupa toda la pantalla (sin la barra de Ágora); un botón flotante devuelve a la app. */
export function renderContracts(container){
  const url=contractsUrl();
  if(!url){
    container.innerHTML=`<p class="empty">${workspace.current()?'Esta subcuenta no tiene página de contratos. Quien administra Ágora la activa en Conexiones.':esc(workspace.noAccountText('los contratos'))}</p>`;
    return;
  }
  const origin=new URL(url).origin;
  container.innerHTML=`<div class="contracts-full" role="region" aria-label="Contratos de ${esc(workspace.name())}">
    <div class="contracts-loading">${skeleton('lines',4)}</div>
    <iframe class="contracts-frame" title="Contratos" src="${esc(embedded(url))}" referrerpolicy="strict-origin-when-cross-origin" allow="clipboard-write"
      sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe>
    <button type="button" class="contracts-back" aria-label="Volver a Ágora" title="Volver a Ágora">${BACK}</button>
  </div>`;
  document.documentElement.classList.add('contracts-open');
  const full=container.querySelector('.contracts-full');
  container.querySelector('.contracts-frame').addEventListener('load',()=>full.classList.add('loaded'),{once:true});
  const back=()=>window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:workspace.prevView&&workspace.prevView!=='contracts'?workspace.prevView:'dashboard'}}));
  container.querySelector('.contracts-back').onclick=back;
  // La página puede pedir volver a Ágora (por ejemplo, al cerrar sesión).
  const onMessage=e=>{if(e.origin===origin&&e.data&&e.data.fuente==='contratos'&&e.data.evento==='cerrar')back()};
  window.addEventListener('message',onMessage);
  container.agoraLiveDispose=()=>{window.removeEventListener('message',onMessage);document.documentElement.classList.remove('contracts-open');container.agoraLiveDispose=null};
}
