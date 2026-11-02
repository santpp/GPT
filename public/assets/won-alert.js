// Alerta de ventas ganadas sin monto. Se consulta a los 4 segundos de entrar y cada vez que se edita una oportunidad;
// nunca por sondeo. Quien tiene ventas sin monto ve una ventana al entrar, una franja fija y un contador en la pestaña;
// pasados los días que fija el propietario, la ventana ya no se puede cerrar hasta completarlas.
import {workspace,friendly} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let state=null,timer=null,popupPending=false,dialog=null,relaxed=false,gateFn=null;
export const wonAlertState=()=>state;

async function api(path,method='GET',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(workspace.me?.csrf&&method!=='GET'?{'X-CSRF-Token':workspace.me.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  let data={};try{data=await r.json()}catch{}
  if(!r.ok)throw Object.assign(new Error(data.error||'No se pudo completar.'),{status:r.status,code:data.code});
  return data;
}

/** Tras entrar (o cambiar de subcuenta) se espera 4 segundos antes de consultar, y la primera vez se abre la ventana. */
export function startWonAlert(gate=null){gateFn=gate;popupPending=true;relaxed=false;schedule(4000)}
function schedule(ms){clearTimeout(timer);timer=setTimeout(check,ms)}
window.addEventListener('agora:opportunity-edited',()=>schedule(1500));
window.addEventListener('agora:logout',()=>{clearTimeout(timer);state=null;paintChrome();if(dialog?.open)dialog.close()});

async function check(){
  const connection=workspace.current();
  const app=document.querySelector('#app');
  if(!connection||!app||app.hidden)return;
  try{
    const alert=await api(`workspace/won-alert?connectionId=${encodeURIComponent(connection)}`);
    if(connection!==workspace.current())return;
    state={...alert,connection,total:state&&state.connection===connection&&state.total?state.total:alert.count};
    paintChrome();
    if(alert.count>0&&dialog?.open)openDialog();
    else if(alert.count>0&&popupPending){
      // La ventana espera su turno detrás del tutorial, la huella y las novedades; la urgente, sólo detrás del tutorial.
      popupPending=false;
      const urgent=!!alert.escalate;
      Promise.resolve(gateFn?gateFn(urgent?'tutorial':'all'):null).then(()=>{if(state&&state.count>0&&state.connection===workspace.current())openDialog()});
    }
    else if(alert.count===0){popupPending=false;if(dialog?.open)finish()}
  }catch{/* la alerta nunca estorba: si no se puede consultar, no se muestra */}
}

function paintChrome(){
  const count=state?.count||0;
  document.querySelectorAll('button[data-view="board"]').forEach(b=>{b.classList.toggle('has-alert',count>0);if(count>0)b.dataset.count=String(count);else delete b.dataset.count});
  const bar=document.querySelector('#won-banner');
  if(bar){
    if(count>0){
      bar.hidden=false;bar.classList.toggle('urgent',!!state.escalate);
      bar.innerHTML=`<p><b>${count===1?'Tienes 1 venta ganada sin monto':`Tienes ${count} ventas ganadas sin monto`}.</b> Cada venta necesita su monto para contar en el tablero y en las comisiones.</p><button type="button" class="primary" id="won-banner-go">Completar ahora</button>`;
      bar.querySelector('#won-banner-go').onclick=()=>openDialog();
    }else{bar.hidden=true;bar.innerHTML=''}
  }
  window.dispatchEvent(new CustomEvent('agora:won-alert',{detail:{count}}));
}

function mustFinish(){return !!state?.escalate&&!relaxed&&state?.canEdit!==false}
const daysText=d=>d===null||d===undefined?'':d<1?'hoy':d===1?'hace 1 día':`hace ${d} días`;

function openDialog(){
  if(!state||state.count<1)return;
  if(dialog&&!dialog.open)delete dialog.dataset.focused;
  if(!dialog){
    dialog=document.createElement('dialog');dialog.id='won-dialog';dialog.className='won-dialog';
    document.body.append(dialog);
    // Con la alerta urgente no se puede cerrar con Escape ni tocando fuera.
    dialog.addEventListener('cancel',e=>{if(mustFinish())e.preventDefault()});
    dialog.addEventListener('close',()=>{if(mustFinish()&&state?.count>0)setTimeout(()=>{if(!dialog.open)dialog.showModal()},0)});
    dialog.addEventListener('click',e=>{if(e.target===dialog&&!mustFinish())dialog.close()});
  }
  render();
  if(!dialog.open)dialog.showModal();
}

const symbol=()=>workspace.currency()==='EUR'?'€':'$';

function render(){
  const urgent=mustFinish();
  const total=state.total??state.count;
  const done=Math.max(0,total-state.count);
  const rows=(state.items||[]).map(item=>{
    const late=state.days>0&&(item.days||0)>=state.days;
    return `<form class="won-item${late?' late':''}" data-won-form="${esc(item.id)}">
      <div class="won-item-info"><strong>${esc(item.name||'Venta ganada')}</strong>
        <small>${esc([item.contactName,item.assignedName].filter(Boolean).join(' · '))}</small>
        <span class="won-age${late?' late':''}">${esc(daysText(item.days)||'')}</span></div>
      ${state.canEdit===false?'<small class="muted won-nopermit">Pide a un administrador que complete el monto.</small>'
        :`<div class="won-field"><label class="sr-only" for="won-v-${esc(item.id)}">Monto</label><span class="won-currency" aria-hidden="true">${symbol()}</span><input id="won-v-${esc(item.id)}" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0,00" autocomplete="off"><button class="primary won-save">Guardar</button></div>`}
      <p class="won-item-status" role="status"></p></form>`;
  }).join('');
  const more=state.count>(state.items||[]).length?`<p class="won-more">Y ${state.count-state.items.length} más. Complétalas en Oportunidades → Ganadas.</p>`:'';
  dialog.innerHTML=`<div class="won-sheet${urgent?' urgent':''}">
    <header class="won-head">
      <h2>${state.count===1?'Una venta ganada sin monto':`${state.count} ventas ganadas sin monto`}</h2>
      <p>${urgent?`Llevas <b>${state.oldestDays} días</b> con ventas sin monto. Complétalas para seguir: sin el monto, la venta no cuenta en el tablero ni en tu comisión.`
        :'Escribe el monto de cada venta. Sin él, la venta no cuenta en el tablero ni en tu comisión.'}</p>
      ${total>1?`<div class="won-progress" role="img" aria-label="${done} de ${total} completadas"><i style="width:${Math.round(done/total*100)}%"></i></div><small>${done} de ${total} completadas</small>`:''}
    </header>
    <div class="won-items">${rows}</div>${more}
    <footer class="won-actions"><button type="button" class="link-button" id="won-go">Ver todas en Ganadas</button>
      <span class="won-actions-end">${urgent?'':'<button type="button" class="secondary" id="won-later">Más tarde</button>'}<button type="button" class="primary" id="won-save-all" hidden></button></span></footer></div>`;
  dialog.querySelector('#won-go').onclick=()=>{if(!mustFinish())dialog.close();workspace.pendingOppTab='won';window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'board'}}))};
  const later=dialog.querySelector('#won-later');if(later)later.onclick=()=>dialog.close();
  dialog.querySelectorAll('[data-won-form]').forEach(form=>{form.onsubmit=e=>{e.preventDefault();save(form)};form.querySelector('input')?.addEventListener('input',syncAll)});
  const all=dialog.querySelector('#won-save-all');if(all)all.onclick=saveAll;
  syncAll();
  // El cursor queda en el primer monto para escribir sin tocar nada.
  const first=dialog.querySelector('.won-item input');if(first&&!dialog.dataset.focused){dialog.dataset.focused='1';setTimeout(()=>first.focus(),50)}
}

/** Con varias rellenadas aparece «Guardar todas», para no repetir el gesto fila por fila. */
function filled(){return [...dialog.querySelectorAll('[data-won-form]')].filter(f=>Number(f.querySelector('input')?.value)>0)}
function syncAll(){
  const all=dialog?.querySelector('#won-save-all');if(!all)return;
  const n=filled().length;
  all.hidden=n<2;all.textContent=`Guardar ${n} ventas`;
}
async function saveAll(){
  const all=dialog.querySelector('#won-save-all');all.disabled=true;
  for(const form of filled())await save(form,true);
  if(dialog?.open&&state.count>0)render();
}

async function save(form,quiet=false){
  const id=form.dataset.wonForm,input=form.querySelector('input'),button=form.querySelector('.won-save'),status=form.querySelector('.won-item-status');
  const value=Number(input.value);
  if(!(value>0)){status.textContent='Escribe un monto mayor que cero.';input.focus();return}
  button.disabled=true;status.textContent='Guardando…';
  try{
    const connection=encodeURIComponent(state.connection);
    await api(`workspace/opportunity/edit?connectionId=${connection}&opportunityId=${encodeURIComponent(id)}`,'POST',{monetaryValue:value,requestId:crypto.randomUUID().replace(/-/g,'')});
    state.total??=state.count;
    state.items=state.items.filter(i=>i.id!==id);state.count=Math.max(0,state.count-1);
    // Si ya no queda ninguna con los días cumplidos, la alerta deja de ser urgente.
    state.oldestDays=Math.max(0,...state.items.map(i=>i.days||0));state.escalate=state.days>0&&state.oldestDays>=state.days&&state.count>0;
    paintChrome();
    window.dispatchEvent(new Event('agora:opportunity-edited'));
    form.classList.add('saved');
    if(state.count===0)finish();else if(!quiet)setTimeout(()=>{if(dialog?.open)render()},350);
  }catch(error){
    button.disabled=false;status.textContent=friendly(error);
    // Si no se puede guardar por permisos, la ventana no atrapa a nadie.
    if(error.status===403){relaxed=true;state.canEdit=false;render()}
  }
}

function finish(){
  if(!dialog)return;
  dialog.innerHTML=`<div class="won-sheet"><header class="won-head won-done"><h2>¡Listo!</h2><p>Todas tus ventas ganadas tienen su monto.</p></header></div>`;
  setTimeout(()=>{if(dialog?.open)dialog.close()},1400);
}
