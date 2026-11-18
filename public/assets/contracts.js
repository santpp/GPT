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

export function renderContracts(container){
  const url=contractsUrl();
  if(!url){
    container.innerHTML=`<p class="empty">${workspace.current()?'Esta subcuenta no tiene página de contratos. Quien administra Ágora la activa en Conexiones.':esc(workspace.noAccountText('los contratos'))}</p>`;
    return;
  }
  const origin=new URL(url).origin;
  container.innerHTML=`<div class="contracts-view">
    <div class="contracts-bar"><p class="contracts-note" id="contracts-note">Página de contratos de ${esc(workspace.name())}</p>
      <a class="link-button" href="${esc(url)}" target="_blank" rel="noopener">Abrir en pantalla completa</a></div>
    <div class="contracts-frame-wrap">${skeleton('lines',4)}<iframe class="contracts-frame" title="Contratos" src="${esc(embedded(url))}"
      referrerpolicy="strict-origin-when-cross-origin" allow="clipboard-write"
      sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe></div>
  </div>`;
  const frame=container.querySelector('.contracts-frame'),wrap=container.querySelector('.contracts-frame-wrap');
  frame.addEventListener('load',()=>{wrap.classList.add('loaded');clearTimeout(slow)},{once:true});
  // Si tarda o el teléfono no deja abrir la sesión dentro de Ágora, se ofrece abrirla aparte.
  const slow=setTimeout(()=>{const n=container.querySelector('#contracts-note');if(n)n.textContent='¿No carga o pide entrar cada vez? Usa «Abrir en pantalla completa».'},12000);
  // La página puede pedir volver a Ágora (por ejemplo, al cerrar sesión).
  const onMessage=e=>{if(e.origin===origin&&e.data&&e.data.fuente==='contratos'&&e.data.evento==='cerrar')window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'dashboard'}}))};
  window.addEventListener('message',onMessage);
  container.agoraLiveDispose=()=>{window.removeEventListener('message',onMessage);clearTimeout(slow);container.agoraLiveDispose=null};
}
