// Ayuda: el logo de Ágora abre un menú (manual, tutorial, PDF) y el manual es una pantalla más de la app.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// Las rutas siguen la carpeta de este módulo, así funcionan igual en desarrollo y en el paquete de cada versión.
const BASE=new URL('./',import.meta.url).pathname;
// El PDF vive en una ruta fija (no dentro de la carpeta de cada versión): se sube una vez y las actualizaciones no lo tocan.
export const MANUAL_PDF='/assets/manual-vendedores.pdf';
let menu=null;

function closeMenu(){if(menu){menu.remove();menu=null;document.removeEventListener('click',outside,true);document.removeEventListener('keydown',keys,true)}}
const outside=e=>{if(menu&&!menu.contains(e.target)&&!e.target.closest('[data-help-logo]'))closeMenu()};
const keys=e=>{if(e.key==='Escape'){closeMenu()}};

/** Convierte el logo (arriba, o en la barra lateral) en el botón de ayuda. */
export function initHelp({openManual,startTour}){
  document.querySelectorAll('.sidebar .brand,.brand-help').forEach(el=>{
    el.setAttribute('data-help-logo','');el.setAttribute('aria-haspopup','menu');el.setAttribute('title','Ayuda');
    el.addEventListener('click',e=>{e.preventDefault();toggle(el,{openManual,startTour})});
  });
}

function toggle(anchor,actions){
  if(menu){closeMenu();return}
  menu=document.createElement('div');menu.className='help-menu';menu.setAttribute('role','menu');
  menu.innerHTML=`<button type="button" role="menuitem" data-act="manual">Ver el manual</button>
    <button type="button" role="menuitem" data-act="tour">Repetir el tutorial</button>
    <a role="menuitem" href="${MANUAL_PDF}" download>Descargar el PDF</a>`;
  document.body.append(menu);
  const r=anchor.getBoundingClientRect();
  menu.style.top=`${r.bottom+8}px`;menu.style.left=`${Math.max(12,Math.min(r.left,innerWidth-menu.offsetWidth-12))}px`;
  menu.querySelector('[data-act="manual"]').onclick=()=>{closeMenu();actions.openManual()};
  menu.querySelector('[data-act="tour"]').onclick=()=>{closeMenu();actions.startTour()};
  menu.querySelector('a').addEventListener('click',()=>setTimeout(closeMenu,50));
  menu.querySelector('button').focus();
  setTimeout(()=>{document.addEventListener('click',outside,true);document.addEventListener('keydown',keys,true)},0);
}

/** La pantalla del manual: el mismo texto que el PDF, con su versión. */
export async function renderHelp(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  container.innerHTML='<div class="manual-wrap"><p class="empty">Abriendo el manual…</p></div>';
  try{
    const r=await fetch(BASE+'manual/vendedores.html',{cache:'no-cache'});
    if(!r.ok)throw new Error('missing');
    const html=await r.text();
    if(container.agoraViewInstance!==instance)return;
    const version=(document.querySelector('#app-version')?.textContent.match(/v(\d+\.\d+\.\d+)/)||[])[1]||'';
    container.innerHTML=`<div class="manual-wrap"><div class="manual-actions"><a class="secondary" href="${MANUAL_PDF}" download>Descargar el PDF</a></div><article class="manual" id="manual">${html.replace(/\{\{version\}\}/g,esc(version)).replace(/(src=")\/assets\//g,'$1'+BASE)}</article></div>`;
    container.querySelectorAll('.manual a[href^="#"]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();container.querySelector(a.getAttribute('href'))?.scrollIntoView({behavior:'smooth',block:'start'})}));
  }catch{if(container.agoraViewInstance===instance)container.innerHTML='<div class="manual-wrap"><p class="empty">No se pudo abrir el manual ahora. Prueba de nuevo en un momento.</p></div>'}
}
