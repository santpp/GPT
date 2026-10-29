import {workspace} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// The account publishes no currency code, so the amount is shown without a symbol.
const amount=value=>typeof value==='number'&&isFinite(value)?new Intl.NumberFormat('es-ES',{maximumFractionDigits:2,useGrouping:'always'}).format(value):null;

async function request(path){
  const r=await fetch('/api/'+path,{cache:'no-store',headers:{'Content-Type':'application/json'}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

export async function renderBoard(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='board';
  const connection=workspace.current();
  if(!connection){container.innerHTML='<p class="empty">Añade una cuenta desde Conexiones para ver oportunidades.</p>';return}
  container.innerHTML='<p class="empty">Cargando oportunidades…</p>';
  let generation=0,pipeline='';

  async function load(selected=''){
    const version=++generation;
    try{
      const data=await request(`workspace/board?connectionId=${encodeURIComponent(connection)}${selected?'&pipelineId='+encodeURIComponent(selected):''}`);
      if(!active()||version!==generation)return;
      if(data.unavailable==='pipelines'){
        container.innerHTML='<p class="empty">No se pudieron consultar los embudos de esta cuenta.<br>Revisa el permiso de oportunidades y vuelve a entrar.</p>';
        return;
      }
      pipeline=data.pipelineId||'';
      const note=data.total
        ? `${data.total} oportunidad${data.total===1?'':'es'} abierta${data.total===1?'':'s'}${data.restricted?' asignadas a tu usuario':''}${data.partial?' · se muestran las más recientes':''}`
        : (data.restricted?'No tienes oportunidades abiertas asignadas en este embudo.':'Este embudo no tiene oportunidades abiertas.');
      container.innerHTML=`<div class="live-toolbar"><label>Embudo<select id="board-pipeline" aria-label="Embudo">${data.pipelines.map(p=>`<option value="${esc(p.id)}"${p.id===data.pipelineId?' selected':''}>${esc(p.name)}</option>`).join('')}</select></label></div><p class="live-feedback" role="status">${esc(note)}</p><div class="kanban">${data.stages.map(stage=>`<div class="board-column"><h2>${esc(stage.name)}</h2><p class="board-total">${stage.total} · ${esc(amount(stage.value)??'0')}</p>${stage.items.map(item=>`<div class="card board-card"><h3>${esc(item.name)}</h3>${item.contactName?`<p>${esc(item.contactName)}</p>`:''}${amount(item.monetaryValue)?`<strong>${esc(amount(item.monetaryValue))}</strong>`:''}${item.assignedName?`<span class="tag">${esc(item.assignedName)}</span>`:''}</div>`).join('')||'<p class="board-empty">Sin oportunidades</p>'}</div>`).join('')}</div>`;
      container.querySelector('#board-pipeline').onchange=e=>load(e.target.value);
    }catch(error){
      if(!active()||version!==generation)return;
      container.innerHTML=`<p class="empty">${esc(error.message)}</p>`;
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  container.agoraLiveDispose=()=>{container.agoraViewInstance=null};
  await load(pipeline);
}
