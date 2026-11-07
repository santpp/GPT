import {workspace,friendly} from './workspace.js';
import {skeleton} from './skeleton.js';
import {money as fmtMoney} from './format.js';
import {opportunityForm,readOpportunityForm,bindPipelineChoice} from './opportunity-editor.js';
import {renderWon} from './won.js';
import {wonAlertState} from './won-alert.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// The account publishes no currency code, so the amount is shown without a symbol.
const amount=value=>typeof value==='number'&&isFinite(value)?fmtMoney(value):null;
const asList=value=>Array.isArray(value)?value:[];

async function request(path,method='GET',csrf='',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

const norm=t=>String(t??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/\s+/g,' ').trim();
async function renderFunnels(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='board';
  const connection=workspace.current();
  if(!connection){container.innerHTML=`<p class="empty">${esc(workspace.noAccountText('oportunidades'))}</p>`;return}
  container.innerHTML=skeleton('cards',6);
  let generation=0,pipeline='',csrf='',board=null,directory=null,editing=null,notice='';

  try{csrf=(await request('me')).csrf||''}catch{}

  function card(item,canEdit){
    return `<div class="card board-card"><h3>${esc(item.name)}</h3>${item.contactName&&norm(item.contactName)!==norm(item.name)?`<p>${esc(item.contactName)}</p>`:''}${amount(item.monetaryValue)?`<strong>${esc(amount(item.monetaryValue))}</strong>`:''}${item.assignedName?`<span class="tag">${esc(item.assignedName)}</span>`:''}${canEdit?`<button class="live-opportunity-edit" data-edit-opportunity="${esc(item.id)}">Editar</button>`:''}</div>`;
  }

  function show(){
    const data=board;
    const note=data.total
      ? `${data.total} oportunidad${data.total===1?'':'es'} abierta${data.total===1?'':'s'}${data.restricted?' asignadas a tu usuario':''}${data.partial?' · las más recientes':''}`
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
      window.dispatchEvent(new Event('agora:opportunity-edited'));
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

/** Oportunidades tiene dos pestañas: los embudos (lo de siempre) y las ventas ganadas. */
export async function renderBoard(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const connection=workspace.current();
  if(!connection){container.innerHTML=`<p class="empty">${esc(workspace.noAccountText('oportunidades'))}</p>`;return}
  let tab=workspace.pendingOppTab||sessionStorage.getItem('agora-opp-tab')||'funnels';workspace.pendingOppTab=null;
  if(!['funnels','won'].includes(tab))tab='funnels';
  const badge=()=>{const n=wonAlertState()?.count||0;return n>0?`<span class="tab-badge" aria-label="${n} sin monto">${n}</span>`:''};
  container.innerHTML=`<div class="opp-tabs" role="tablist" aria-label="Oportunidades"><button type="button" role="tab" data-opp-tab="funnels">Embudos</button><button type="button" role="tab" data-opp-tab="won">Ganadas<span id="won-badge">${badge()}</span></button></div><div id="opp-pane"></div>`;
  const pane=container.querySelector('#opp-pane');
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='board'&&container.querySelector('#opp-pane')===pane;
  const open=name=>{
    tab=name;try{sessionStorage.setItem('agora-opp-tab',name)}catch{}
    container.querySelectorAll('[data-opp-tab]').forEach(b=>{const on=b.dataset.oppTab===name;b.classList.toggle('on',on);b.setAttribute('aria-selected',String(on))});
    pane.dataset.view='board';
    if(name==='won')renderWon(pane,active);else renderFunnels(pane);
  };
  container.querySelectorAll('[data-opp-tab]').forEach(b=>b.onclick=()=>{if(b.dataset.oppTab!==tab)open(b.dataset.oppTab)});
  const onAlert=()=>{const el=container.querySelector('#won-badge');if(!el){window.removeEventListener('agora:won-alert',onAlert);return}el.innerHTML=badge()};
  window.addEventListener('agora:won-alert',onAlert);
  open(tab);
}
