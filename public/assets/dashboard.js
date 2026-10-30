import {workspace} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];
const number=value=>new Intl.NumberFormat('es-ES').format(Number(value)||0);
const money=value=>new Intl.NumberFormat('es-ES',{maximumFractionDigits:0}).format(Number(value)||0);

async function request(path){
  const r=await fetch('/api/'+path,{cache:'no-store',headers:{'Content-Type':'application/json'}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

function tile(label,value,note){
  return `<div class="board-tile"><span>${esc(label)}</span><strong>${esc(value)}</strong>${note?`<small>${esc(note)}</small>`:''}</div>`;
}

export async function renderDashboard(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='dashboard';
  const connection=workspace.current();
  if(!connection){container.innerHTML='<p class="empty">Añade una cuenta desde Conexiones para ver el tablero.</p>';return}
  container.innerHTML='<p id="dashboard-feedback" class="live-feedback" role="status">Leyendo la cuenta…</p><div id="dashboard-body"></div>';
  const $=s=>container.querySelector(s);

  async function load(){
    $('#dashboard-feedback').textContent='Leyendo la cuenta…';
    try{
      const data=await request(`workspace/dashboard?connectionId=${encodeURIComponent(connection)}`);
      if(!active())return;
      const read=data.readAt?new Date(data.readAt).toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit'}):'';
      $('#dashboard-feedback').textContent=`${data.restricted?'Tus cifras en esta cuenta':'Cifras de la cuenta'}${read?` · leídas a las ${read}`:''}`;
      const unread=data.conversations?.unread??0;
      const tiles=[
        tile('Conversaciones',number(data.conversations?.total??0),'Todas las abiertas en la cuenta'),
        tile('Sin leer',number(unread),unread?'Esperan respuesta':'Nada pendiente'),
        ...(data.contacts!==null&&data.contacts!==undefined?[tile('Contactos',number(data.contacts),'Personas en la cuenta')]:[]),
        ...(data.opportunities?[tile('Oportunidades abiertas',number(data.opportunities.count),`${money(data.opportunities.value)} en juego`)]:[]),
      ].join('');
      const stages=data.opportunities?asList(data.opportunities.stages):[];
      const stageList=stages.length
        ? `<div class="dashboard-stages">${stages.map(stage=>`<div class="board-card card"><h3>${esc(stage.name)}</h3><p>${number(stage.count)} oportunidad${stage.count===1?'':'es'}</p><strong>${money(stage.value)}</strong></div>`).join('')}</div>`
        : '<p class="board-empty">Todavía no hay oportunidades abiertas en esta cuenta.</p>';
      $('#dashboard-body').innerHTML=`<div class="dashboard-tiles">${tiles}</div>
        <div class="section-label">EMBUDO POR ETAPA</div>${stageList}
        ${data.opportunities?.sampled?'<p class="board-empty">Se resumen las oportunidades abiertas más recientes.</p>':''}
        <div class="dashboard-links"><button class="primary" data-go="inbox">Ir a conversaciones</button><button class="primary" data-go="board">Ver el embudo</button></div>`;
      container.querySelectorAll('[data-go]').forEach(button=>button.onclick=()=>document.querySelector(`button.nav[data-view="${button.dataset.go}"]`)?.click());
    }catch(error){
      if(!active())return;
      $('#dashboard-feedback').textContent=error.message;
      $('#dashboard-body').innerHTML='';
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  container.agoraLiveDispose=()=>{container.agoraViewInstance=null};
  await load();
}
