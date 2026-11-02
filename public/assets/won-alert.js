// Alerta de ventas ganadas sin monto. Se consulta a los 4 segundos de entrar y cada vez que se edita una oportunidad;
// nunca por sondeo. Quien tiene ventas sin monto ve una ventana al entrar, una franja fija y un contador en la pestaña;
// pasados los días que fija el propietario, la ventana ya no se puede cerrar hasta completarlas.
import {workspace,friendly} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let state=null,timer=null,popupPending=false,dialog=null,relaxed=false;
export const wonAlertState=()=>state;

async function api(path,method='GET',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(workspace.me?.csrf&&method!=='GET'?{'X-CSRF-Token':workspace.me.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  let data={};try{data=await r.json()}catch{}
  if(!r.ok)throw Object.assign(new Error(data.error||'No se pudo completar.'),{status:r.status,code:data.code});
  return data;
}

/** Tras entrar (o cambiar de subcuenta) se espera 4 segundos antes de consultar, y la primera vez se abre la ventana. */
export function startWonAlert(){popupPending=true;relaxed=false;schedule(4000)}
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
    state={...alert,connection};
    paintChrome();
    if(alert.count>0&&(popupPending||dialog?.open))openDialog();
    else if(alert.count===0&&dialog?.open)finish();
    popupPending=false;
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

function render(){
  const urgent=mustFinish();
  const rows=(state.items||[]).map(item=>`<form class="won-item" data-won-form="${esc(item.id)}">
    <div class="won-item-info"><strong>${esc(item.name||'Venta ganada')}</strong><small>${esc([item.contactName,item.assignedName,daysText(item.days)].filter(Boolean).join(' · '))}</small></div>
    ${state.canEdit===false?'<small class="muted">Pide a un administrador que complete el monto.</small>'
      :`<label class="sr-only" for="won-v-${esc(item.id)}">Monto</label><input id="won-v-${esc(item.id)}" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="Monto" required><button class="primary">Guardar</button>`}
    <p class="won-item-status" role="status"></p></form>`).join('');
  const more=state.count>(state.items||[]).length?`<p class="muted">Y ${state.count-state.items.length} más. Complétalas en Oportunidades → Ganadas.</p>`:'';
  dialog.innerHTML=`<div class="won-head${urgent?' urgent':''}"><h2>${state.count===1?'Una venta ganada sin monto':`${state.count} ventas ganadas sin monto`}</h2>
    <p>${urgent?`<b>Llevas ${state.oldestDays} días con ventas sin monto.</b> Complétalas ahora para seguir: sin el monto, la venta no cuenta en el tablero ni en tu comisión.`
      :'Escribe el monto de cada venta. Sin él, la venta no cuenta en el tablero ni en tu comisión.'}</p></div>
    <div class="won-items">${rows}</div>${more}
    <div class="won-actions"><button type="button" class="secondary" id="won-go">Ver todas en Ganadas</button>${urgent?'':'<button type="button" class="link-button" id="won-later">Más tarde</button>'}</div>`;
  dialog.querySelector('#won-go').onclick=()=>{if(!mustFinish())dialog.close();workspace.pendingOppTab='won';window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'board'}}))};
  const later=dialog.querySelector('#won-later');if(later)later.onclick=()=>dialog.close();
  dialog.querySelectorAll('[data-won-form]').forEach(form=>form.onsubmit=e=>save(e,form));
}

async function save(event,form){
  event.preventDefault();
  const id=form.dataset.wonForm,input=form.querySelector('input'),button=form.querySelector('button'),status=form.querySelector('.won-item-status');
  const value=Number(input.value);
  if(!(value>0)){status.textContent='Escribe un monto mayor que cero.';return}
  button.disabled=true;status.textContent='Guardando…';
  try{
    const connection=encodeURIComponent(state.connection);
    await api(`workspace/opportunity/edit?connectionId=${connection}&opportunityId=${encodeURIComponent(id)}`,'POST',{monetaryValue:value,requestId:crypto.randomUUID().replace(/-/g,'')});
    state.items=state.items.filter(i=>i.id!==id);state.count=Math.max(0,state.count-1);
    // Si ya no queda ninguna con los días cumplidos, la alerta deja de ser urgente.
    state.oldestDays=Math.max(0,...state.items.map(i=>i.days||0));state.escalate=state.days>0&&state.oldestDays>=state.days&&state.count>0;
    paintChrome();
    window.dispatchEvent(new Event('agora:opportunity-edited'));
    if(state.count===0)finish();else render();
  }catch(error){
    button.disabled=false;status.textContent=friendly(error);
    // Si no se puede guardar por permisos, la ventana no atrapa a nadie.
    if(error.status===403){relaxed=true;state.canEdit=false;render()}
  }
}

function finish(){
  if(!dialog)return;
  dialog.innerHTML=`<div class="won-head"><h2>¡Listo!</h2><p>Todas tus ventas ganadas tienen su monto.</p></div>`;
  setTimeout(()=>{if(dialog?.open)dialog.close()},1400);
}
