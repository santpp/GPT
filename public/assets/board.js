import {workspace,friendly} from './workspace.js';
import {opportunityForm,readOpportunityForm,bindPipelineChoice} from './opportunity-editor.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// The account publishes no currency code, so the amount is shown without a symbol.
const amount=value=>typeof value==='number'&&isFinite(value)?new Intl.NumberFormat('es-ES',{maximumFractionDigits:2,useGrouping:'always'}).format(value):null;
const asList=value=>Array.isArray(value)?value:[];

async function request(path,method='GET',csrf='',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
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
  let generation=0,pipeline='',csrf='',board=null,directory=null,editing=null,notice='';

  try{csrf=(await request('me')).csrf||''}catch{}

  function card(item,canEdit){
    return `<div class="card board-card"><h3>${esc(item.name)}</h3>${item.contactName?`<p>${esc(item.contactName)}</p>`:''}${amount(item.monetaryValue)?`<strong>${esc(amount(item.monetaryValue))}</strong>`:''}${item.assignedName?`<span class="tag">${esc(item.assignedName)}</span>`:''}${canEdit?`<button class="live-opportunity-edit" data-edit-opportunity="${esc(item.id)}">Editar</button>`:''}</div>`;
  }

  function show(){
    const data=board;
    const note=data.total
      ? `${data.total} oportunidad${data.total===1?'':'es'} abierta${data.total===1?'':'s'}${data.restricted?' asignadas a tu usuario':''}${data.partial?' · se muestran las más recientes':''}`
      : (data.restricted?'No tienes oportunidades abiertas asignadas en este embudo.':'Este embudo no tiene oportunidades abiertas.');
    const canEdit=data.canEdit===true;
    const cards=asList(data.stages).flatMap(stage=>asList(stage.items));
    const panel=editing
      ? `<section class="board-editor">${directory
          ? opportunityForm({pipelines:directory.pipelines,assignees:directory.assignees,opportunity:cards.find(item=>item.id===editing)||null})
          : '<span class="muted">Cargando embudos…</span>'}</section>`
      : '';
    container.innerHTML=`<div class="live-toolbar"><label>Embudo<select id="board-pipeline" aria-label="Embudo">${data.pipelines.map(p=>`<option value="${esc(p.id)}"${p.id===data.pipelineId?' selected':''}>${esc(p.name)}</option>`).join('')}</select></label></div><p class="live-feedback" role="status">${esc(note)}${notice?' · '+esc(notice):''}</p>${panel}<div class="kanban">${data.stages.map(stage=>`<div class="board-column"><h2>${esc(stage.name)}</h2><p class="board-total">${stage.total} · ${esc(amount(stage.value)??'0')}</p>${stage.items.map(item=>card(item,canEdit)).join('')||'<p class="board-empty">Sin oportunidades</p>'}</div>`).join('')}</div>`;
    container.querySelector('#board-pipeline').onchange=e=>load(e.target.value);
    container.querySelectorAll('[data-edit-opportunity]').forEach(button=>button.onclick=()=>edit(button.dataset.editOpportunity));
    const form=container.querySelector('.opportunity-form');
    if(form){
      if(directory)bindPipelineChoice(form,directory.pipelines);
      form.querySelector('[data-cancel]').onclick=()=>{editing=null;notice='';show()};
      form.onsubmit=event=>{event.preventDefault();save(form)};
      form.scrollIntoView({block:'nearest'});
    }
  }

  // Moving a card, changing its value, its status or who answers for it all go
  // through the same form, because the account writes them together.
  async function edit(id){
    editing=id;notice='';show();
    if(!directory){
      try{directory=await request(`workspace/pipelines?connectionId=${encodeURIComponent(connection)}`)}
      catch(error){editing=null;notice=friendly(error)}
      if(!active())return;
      show();
    }
  }

  async function save(form){
    const cards=asList(board.stages).flatMap(stage=>asList(stage.items));
    const target=cards.find(item=>item.id===editing)||null;
    const payload=readOpportunityForm(form,target);
    const status=form.querySelector('[data-status]'),button=form.querySelector('[data-save]');
    if(Object.keys(payload).length===0){status.textContent='No has cambiado nada en la oportunidad.';return}
    button.disabled=true;status.textContent='Guardando la oportunidad…';
    // One key per content, so a retry cannot apply the same change twice.
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(editing+JSON.stringify(payload)));
    const storageKey='agora-opportunity:'+editing+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
    const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID().replace(/-/g,'');
    sessionStorage.setItem(storageKey,requestId);
    try{
      await request(`workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(editing)}`,'POST',csrf,{...payload,requestId});
      sessionStorage.removeItem(storageKey);
      if(!active())return;
      editing=null;notice='Oportunidad guardada.';
      await load(pipeline);
    }catch(error){
      if(['action_rejected','invalid_opportunity_name','invalid_opportunity_value','unknown_pipeline','unknown_stage','unknown_assignee','assignee_forbidden','invalid_status','nothing_to_change','action_forbidden'].includes(error.code))sessionStorage.removeItem(storageKey);
      if(!active())return;
      const failed=container.querySelector('[data-status]');
      if(failed)failed.textContent=friendly(error);
      const retry=container.querySelector('[data-save]');if(retry)retry.disabled=false;
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

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
      board=data;
      show();
    }catch(error){
      if(!active()||version!==generation)return;
      container.innerHTML=`<p class="empty">${esc(friendly(error))}</p>`;
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  container.agoraLiveDispose=()=>{container.agoraViewInstance=null};
  await load(pipeline);
}
