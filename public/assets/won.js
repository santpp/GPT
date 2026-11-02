// Pestaña «Ganadas» de Oportunidades: las ventas ganadas del mes, su monto, y las que están sin monto para completarlas.
import {workspace,friendly} from './workspace.js';
import {skeleton} from './skeleton.js';
import {money,number,symbol} from './format.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const MONTHS=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const pad=n=>String(n).padStart(2,'0');
const monthOptions=(selected,count=24)=>{
  const now=new Date(),seen=[];
  for(let i=0;i<count;i++){const d=new Date(now.getFullYear(),now.getMonth()-i,1);seen.push(`${d.getFullYear()}-${pad(d.getMonth()+1)}`)}
  if(selected&&!seen.includes(selected))seen.push(selected);
  return seen.map(v=>{const [y,m]=v.split('-');const t=MONTHS[Number(m)-1];return `<option value="${v}"${v===selected?' selected':''}>${t[0].toUpperCase()+t.slice(1)} ${y}</option>`}).join('');
};
const dateLabel=v=>{const d=v?new Date(v):null;return d&&!Number.isNaN(d.getTime())?new Intl.DateTimeFormat('es-ES',{day:'numeric',month:'short'}).format(d):''};

async function request(path,method='GET',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(workspace.me?.csrf&&method!=='GET'?{'X-CSRF-Token':workspace.me.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

export async function renderWon(pane,active=()=>true){
  const connection=workspace.current();
  let period=new Date().toISOString().slice(0,7),seller='',data=null,generation=0;
  pane.innerHTML=`<div class="won-toolbar"><label>Mes<select id="won-month">${monthOptions(period)}</select></label><label id="won-seller-box" hidden>Vendedor<select id="won-seller"><option value="">Todos</option></select></label></div><div id="won-summary"></div><div id="won-list">${skeleton('list',5)}</div>`;
  const $=s=>pane.querySelector(s);
  async function load(){
    const mine=++generation;
    try{
      const d=await request(`workspace/won?connectionId=${encodeURIComponent(connection)}&period=${period}${seller?'&seller='+encodeURIComponent(seller):''}`);
      if(!active()||mine!==generation)return;
      data=d;paint();
    }catch(error){if(active()&&mine===generation){$('#won-summary').innerHTML='';$('#won-list').innerHTML=`<p class="empty">${esc(friendly(error))}</p>`}}
  }
  function paint(){
    const t=data.totals;
    $('#won-summary').innerHTML=`<div class="won-stats"><div><span>Ventas ganadas</span><strong>${number(t.count)}</strong></div><div><span>Monto vendido</span><strong>${esc(money(t.sum)||'—')}</strong></div><div class="${t.missing?'bad':''}"><span>Sin monto</span><strong>${number(t.missing)}</strong></div></div>${data.partial?'<p class="won-note">La cuenta tiene muchas oportunidades ganadas en estos meses y se revisaron las 500 más recientes por fecha de creación; alguna venta antigua podría no aparecer.</p>':''}`;
    const box=$('#won-seller-box');
    if(data.canSeeAll&&data.bySeller.length){
      box.hidden=false;
      $('#won-seller').innerHTML='<option value="">Todos</option>'+data.bySeller.map(b=>`<option value="${esc(b.id??'none')}"${seller===(b.id??'none')?' selected':''}>${esc(b.name)} · ${b.count}${b.missing?` (${b.missing} sin monto)`:''}</option>`).join('');
    }else box.hidden=true;
    const rows=data.items.map(item=>{
      // Muchas oportunidades se llaman igual que su contacto: no se repite.
      const contact=item.contactName&&item.contactName!==item.name?item.contactName:null;
      const meta=[contact,item.assignedName||(item.assignedTo?null:'Sin responsable'),dateLabel(item.wonAt)].filter(Boolean).join(' · ');
      return `<article class="won-row${item.missing?' missing':''}" data-won-id="${esc(item.id)}">
        <div class="won-row-info"><strong>${esc(item.name||'Venta ganada')}</strong><small>${item.missing?'<b class="won-miss">Sin monto</b>'+(meta?' · ':''):''}${esc(meta)}</small></div>
        ${item.missing
          ?(data.canEdit?`<form class="won-inline" data-won-form="${esc(item.id)}"><div class="won-field"><label class="sr-only" for="wv-${esc(item.id)}">Monto</label><span class="won-currency" aria-hidden="true">${symbol()}</span><input id="wv-${esc(item.id)}" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0,00" autocomplete="off"><button class="primary won-save">Guardar</button></div></form>`:'')
          :`<strong class="won-amount">${esc(money(item.value)||'—')}</strong>`}
        <p class="won-inline-status" role="status"></p></article>`;
    }).join('');
    $('#won-list').innerHTML=rows||'<p class="empty">No hay ventas ganadas en este mes.</p>';
    pane.querySelectorAll('[data-won-form]').forEach(form=>form.onsubmit=e=>save(e,form));
  }
  async function save(event,form){
    event.preventDefault();
    const id=form.dataset.wonForm,input=form.querySelector('input'),button=form.querySelector('button'),status=form.closest('.won-row').querySelector('.won-inline-status');
    const value=Number(input.value);
    if(!(value>0)){status.textContent='Escribe un monto mayor que cero.';return}
    button.disabled=true;status.textContent='Guardando…';
    try{
      await request(`workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(id)}`,'POST',{monetaryValue:value,requestId:crypto.randomUUID().replace(/-/g,'')});
      window.dispatchEvent(new Event('agora:opportunity-edited'));
      await load();
    }catch(error){button.disabled=false;status.textContent=friendly(error)}
  }
  $('#won-month').onchange=e=>{period=e.target.value;$('#won-list').innerHTML=skeleton('list',5);load()};
  $('#won-seller').onchange=e=>{seller=e.target.value;load()};
  await load();
}
