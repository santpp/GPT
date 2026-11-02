// Pantalla de mantenimiento: aparece sola cuando Ágora está en pausa o el servidor no responde.
// Envuelve fetch para las llamadas a /api/: no cambia lo que cada pantalla recibe, sólo vigila.
const nativeFetch=window.fetch.bind(window);
let shown=false,checking=false,timer=null;
const isApi=url=>/^(?:https?:\/\/[^/]+)?\/api\//.test(url);
const isProbe=url=>/\/api\/(?:status|health)(?:\?|$)/.test(url);

async function probe(){
  try{
    const r=await nativeFetch('/api/status',{cache:'no-store'});
    if(!r.ok)return 'down';
    const data=await r.json();
    return data.maintenance?'paused':'ok';
  }catch{return 'down'}
}

function show(){
  if(shown)return;shown=true;
  const box=document.createElement('div');
  box.id='maintenance';box.setAttribute('role','alertdialog');box.setAttribute('aria-labelledby','maintenance-title');
  box.innerHTML=`<div class="maintenance-card"><img class="maintenance-logo" src="/assets/agora-logo.png" alt="Ágora">
    <h1 id="maintenance-title">Oops! Algo ocurre.</h1>
    <p>El servidor puede estar tomando una siesta o dándose un masaje de mantenimiento.</p>
    <p>Contacta a Ágora para más información.</p>
    <button type="button" class="primary" id="maintenance-retry">Reintentar</button></div>`;
  document.body.append(box);document.body.classList.add('in-maintenance');
  document.querySelectorAll('dialog[open]').forEach(d=>{try{d.close()}catch{}});
  box.querySelector('#maintenance-retry').onclick=async e=>{e.target.disabled=true;await recover();e.target.disabled=false};
  timer=setInterval(recover,15000);
}

async function recover(){
  if(await probe()==='ok')location.reload();
}

// Ante un fallo se comprueba una vez: si el servidor contesta y no está en pausa, el fallo era otro y no se muestra nada.
async function check(){
  if(shown||checking)return;checking=true;
  try{if(await probe()!=='ok')show()}finally{checking=false}
}

window.fetch=async function(input,init){
  const url=typeof input==='string'?input:(input&&input.url)||'';
  if(!isApi(url)||isProbe(url))return nativeFetch(input,init);
  let response;
  try{response=await nativeFetch(input,init)}
  catch(error){if(navigator.onLine!==false)check();throw error}
  if(response.status===502||response.status===504)check();
  else if(response.status===503){
    response.clone().json().then(data=>{if(data&&data.code==='maintenance')show();else check()}).catch(()=>check());
  }
  return response;
};

export const maintenanceShown=()=>shown;
