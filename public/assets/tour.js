// Tutorial del primer inicio: una guía corta sobre la propia pantalla, siempre omitible.
// Sale solo una vez por dispositivo (a vendedores) y se puede repetir desde el logo de Ágora.
const KEY='agora-tour-done';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const tourDone=()=>{try{return localStorage.getItem(KEY)==='1'}catch{return true}};
const markDone=()=>{try{localStorage.setItem(KEY,'1')}catch{}};
const visible=el=>!!el&&el.getClientRects().length>0&&getComputedStyle(el).visibility!=='hidden';
const first=(...selectors)=>{for(const s of selectors){const el=[...document.querySelectorAll(s)].find(visible);if(el)return el}return null};

/**
 * @param {{pushState:()=>string, activatePush:()=>void}} hooks lo que la guía necesita saber de los avisos
 * @returns {Promise<boolean>} true si se mostró
 */
export function runTour(hooks){
  return new Promise(resolve=>{
    const steps=[
      {title:'Bienvenida a Ágora',body:'Es tu bandeja de trabajo: lo que te asignan, lo que te escriben y lo que vendes, en un solo lugar. Te enseñamos lo básico en un minuto, y puedes omitirlo cuando quieras.'},
      {target:()=>first('button.nav[data-view="inbox"]'),title:'Conversaciones',body:'Aquí llegan las conversaciones que tienes asignadas. Entra en una para responder y elige por qué canal (WhatsApp, Instagram…) con el botón que hay sobre el cuadro de respuesta. «Sin respuesta» te muestra las que esperan.'},
      {target:()=>first('button.nav[data-view="contacts"]'),title:'Contactos',body:'La ficha de cada persona: sus datos, etiquetas y campos. Puedes completarla y corregirla sin salir de Ágora.'},
      {target:()=>first('button.nav[data-view="board"]'),title:'Oportunidades',body:'Tus embudos y, en «Ganadas», tus ventas cerradas. Cada venta ganada necesita su monto: si falta alguno, Ágora te lo recuerda al entrar hasta que lo completes.'},
      {target:()=>first('button.nav[data-view="dashboard"]'),title:'Tablero',body:'Tus cifras: leads, conversaciones sin responder y tus ventas del mes. Cambia el periodo con el selector de arriba.'},
      {target:()=>first('#push-toggle'),title:'Avisos',dynamic:true},
      {target:()=>first('.sidebar .brand','.brand-help'),title:'¿Necesitas ayuda?',body:'Toca el logo de Ágora cuando quieras: ahí está el manual, este tutorial y el PDF para descargar.'},
    ].filter(step=>!step.target||step.target());
    // Un paso cuyo botón no se ve (por ejemplo, los avisos en la app nativa) se salta.
    const root=document.createElement('div');root.id='tour';root.setAttribute('role','presentation');
    root.innerHTML='<div class="tour-dim"></div><div class="tour-spot" hidden></div><section class="tour-card" role="dialog" aria-modal="true" aria-labelledby="tour-title"><p class="tour-count"></p><h2 id="tour-title"></h2><p class="tour-body"></p><div class="tour-extra"></div><div class="tour-actions"><button type="button" class="link-button" data-skip>Omitir tutorial</button><span class="tour-nav"><button type="button" class="secondary" data-prev>Anterior</button><button type="button" class="primary" data-next>Siguiente</button></span></div></section>';
    document.body.append(root);document.body.classList.add('touring');
    const card=root.querySelector('.tour-card'),spot=root.querySelector('.tour-spot'),dim=root.querySelector('.tour-dim');
    let index=0;
    const textFor=step=>{
      if(!step.dynamic)return {body:step.body,action:null};
      const s=hooks.pushState?.()||'off';
      if(s==='on')return {body:'Tienes los avisos activados: te llegará una notificación con cada mensaje nuevo, cuando te asignen una conversación y si algo lleva rato sin leerse.',action:null};
      if(s==='needs-install')return {body:'Para recibir avisos en el iPhone, añade Ágora a la pantalla de inicio (Compartir → «Añadir a pantalla de inicio») y ábrela desde su icono.',action:null};
      if(s==='blocked')return {body:'El navegador tiene bloqueados los avisos. Actívalos en los ajustes del sitio (el candado junto a la dirección → Permisos → Notificaciones) y se activarán solos.',action:null};
      return {body:'Activa los avisos para enterarte de cada mensaje nuevo aunque Ágora esté cerrada. El teléfono te pedirá permiso: toca «Permitir».',action:'Activar avisos'};
    };
    function place(){
      const step=steps[index],target=step.target?.();
      if(target){
        target.scrollIntoView?.({block:'nearest',inline:'nearest'});
        const r=target.getBoundingClientRect(),pad=6;
        spot.hidden=false;dim.hidden=true;
        Object.assign(spot.style,{top:`${r.top-pad}px`,left:`${r.left-pad}px`,width:`${r.width+pad*2}px`,height:`${r.height+pad*2}px`});
        const vw=innerWidth,vh=innerHeight,w=Math.min(360,vw-24);
        card.style.width=`${w}px`;
        const left=Math.max(12,Math.min(vw-w-12,r.left+r.width/2-w/2));
        card.style.left=`${left}px`;card.style.transform='none';
        const below=r.top<vh/2;
        if(below){card.style.top=`${Math.min(vh-card.offsetHeight-12,r.bottom+pad+14)}px`;card.style.bottom='auto'}
        else{card.style.bottom=`${Math.max(12,vh-r.top+pad+14)}px`;card.style.top='auto'}
      }else{
        spot.hidden=true;dim.hidden=false;
        {const w=Math.min(380,innerWidth-24);card.style.width=`${w}px`;card.style.left=`${(innerWidth-w)/2}px`;card.style.top=`${Math.max(12,(innerHeight-card.offsetHeight)/2)}px`;card.style.bottom='auto';card.style.transform='none'}
      }
    }
    function render(){
      const step=steps[index],{body,action}=textFor(step);
      root.querySelector('.tour-count').textContent=`${index+1} de ${steps.length}`;
      root.querySelector('#tour-title').textContent=step.title;
      root.querySelector('.tour-body').textContent=body;
      const extra=root.querySelector('.tour-extra');extra.innerHTML='';
      if(action){const b=document.createElement('button');b.type='button';b.className='secondary tour-action';b.textContent=action;b.onclick=()=>{hooks.activatePush?.();setTimeout(render,1200)};extra.append(b)}
      root.querySelector('[data-prev]').hidden=index===0;
      root.querySelector('[data-next]').textContent=index===steps.length-1?'Terminar':'Siguiente';
      root.querySelector('[data-skip]').hidden=index===steps.length-1;
      place();
      root.querySelector('[data-next]').focus({preventScroll:true});
    }
    function close(){
      removeEventListener('resize',place);removeEventListener('keydown',keys,true);
      root.remove();document.body.classList.remove('touring');markDone();
      // La invitación a instalar o activar avisos ya se vio aquí: no se repite al instante.
      try{localStorage.setItem('agora-welcome-later',String(Date.now()))}catch{}
      resolve(true);
    }
    const keys=e=>{if(e.key==='Escape'){e.preventDefault();close()}};
    addEventListener('resize',place);addEventListener('keydown',keys,true);
    root.querySelector('[data-skip]').onclick=close;
    root.querySelector('[data-prev]').onclick=()=>{if(index>0){index--;render()}};
    root.querySelector('[data-next]').onclick=()=>{if(index>=steps.length-1)close();else{index++;render()}};
    render();
  });
}
