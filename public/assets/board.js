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

  // Filtros y búsqueda de esta pantalla (sólo en el navegador, sobre las oportunidades ya cargadas).
  const ui={q:'',who:'',stage:'',collapsed:new Set(),move:null};
  const digits=t=>String(t??'').replace(/\D/g,'');
  /** Nombre, correo o teléfono: sin importar mayúsculas, acentos ni cómo esté escrito el número. */
  const matches=(item,q)=>{
    const text=norm(q);if(!text)return true;
    if(norm([item.name,item.contactName,item.contactEmail,item.assignedName].join(' ')).includes(text))return true;
    const d=digits(q);return d.length>=5&&digits(item.contactPhone).includes(d.replace(/^0+/,''));
  };
  const visibleItems=stage=>asList(stage.items).filter(item=>matches(item,ui.q)&&(!ui.who||(ui.who==='none'?!item.assignedName:item.assignedName===ui.who)));
  const ICONS={chat:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg>',person:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.6"/><path d="M5 20c.8-3.6 3.6-5.4 7-5.4s6.2 1.8 7 5.4"/></svg>',move:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',chevron:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'};
  function card(item,canEdit,stages){
    const sub=[item.contactName&&norm(item.contactName)!==norm(item.name)?item.contactName:null,item.assignedName].filter(Boolean);
    const stageMenu=ui.move===item.id?`<div class="board-move" role="menu" aria-label="Mover a">${stages.filter(st=>st.id&&st.id!==item.pipelineStageId).map(st=>`<button type="button" role="menuitem" data-move-to="${esc(st.id)}" data-opp="${esc(item.id)}">${esc(st.name)}</button>`).join('')||'<span class="muted">No hay otra etapa.</span>'}</div>`:'';
    return `<article class="board-card2"${canEdit?` data-edit-opportunity="${esc(item.id)}" tabindex="0" role="button" aria-label="Editar ${esc(item.name)}"`:''}>
      <div class="board-card2-top"><h3>${esc(item.name)}</h3>${amount(item.monetaryValue)?`<strong>${esc(amount(item.monetaryValue))}</strong>`:''}</div>
      ${sub.length?`<p class="board-card2-sub">${sub.map(esc).join(' · ')}</p>`:''}
      <div class="board-card2-acts">
       ${item.contactId?`<button type="button" class="board-ic" data-open-chat="${esc(item.contactId)}" aria-label="Abrir la conversación">${ICONS.chat}<span>Conversación</span></button><button type="button" class="board-ic" data-open-contact="${esc(item.contactId)}" aria-label="Abrir la ficha del contacto">${ICONS.person}<span>Ficha</span></button>`:''}
       ${canEdit&&item.pipelineStageId?`<button type="button" class="board-ic" data-move="${esc(item.id)}" aria-haspopup="menu" aria-expanded="${ui.move===item.id}" aria-label="Mover a otra etapa">${ICONS.move}<span>Mover a…</span></button>`:''}
      </div>${stageMenu}</article>`;
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
    const allItems=cards;
    const who=[...new Set(allItems.map(i=>i.assignedName).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'es'));
    const hasUnassigned=allItems.some(i=>!i.assignedName);
    const filtered=ui.q||ui.who;
    const counted=data.stages.map(stage=>({stage,items:visibleItems(stage)}));
    // «Etapa»: todas, o sólo la elegida.
    if(ui.stage&&!counted.some(x=>String(x.stage.id??'none')===ui.stage))ui.stage='';
    const shown=ui.stage?counted.filter(x=>String(x.stage.id??'none')===ui.stage):counted;
    const shownTotal=counted.reduce((n,x)=>n+x.items.length,0);
    const whoOptions=who.length+(hasUnassigned&&who.length?1:0)>1;
    container.innerHTML=`<div class="live-toolbar board-selects"><label>Embudo<select id="board-pipeline">${data.pipelines.map(p=>`<option value="${esc(p.id)}"${p.id===data.pipelineId?' selected':''}>${esc(p.name)}</option>`).join('')}</select></label>
      ${whoOptions?`<label>Responsable<select id="board-who"><option value="">Todos</option>${who.map(n=>`<option value="${esc(n)}"${ui.who===n?' selected':''}>${esc(n)}</option>`).join('')}${hasUnassigned?`<option value="none"${ui.who==='none'?' selected':''}>Sin responsable</option>`:''}</select></label>`:''}
      <label>Etapa<select id="board-stage"><option value="">Todas las etapas · ${shownTotal}</option>${counted.map(({stage,items})=>{const k=String(stage.id??'none');return `<option value="${esc(k)}"${ui.stage===k?' selected':''}>${esc(stage.name)} · ${items.length}</option>`}).join('')}</select></label></div>
     <div class="board-tools"><label class="search board-search"><span aria-hidden="true">⌕</span><input id="board-search" type="search" inputmode="search" autocomplete="off" placeholder="Buscar por nombre, teléfono o correo" value="${esc(ui.q)}" aria-label="Buscar oportunidades"></label>
      ${filtered||ui.stage?'<button type="button" class="board-clear" id="board-clear">Quitar filtros</button>':''}</div>
     <p class="live-feedback" role="status">${esc(filtered?`${shownTotal} de ${allItems.length} oportunidades`:note)}${notice?' · '+esc(notice):''}</p>${panel}
     <div class="kanban">${shown.map(({stage,items})=>{const key=String(stage.id??'none'),closed=ui.collapsed.has(key)&&!ui.stage;return `<section class="board-column${closed?' collapsed':''}" id="stage-${esc(key)}"><button type="button" class="board-stage-h" data-toggle="${esc(key)}" aria-expanded="${!closed}"><span class="board-stage-name">${esc(stage.name)}</span><span class="board-stage-meta">${items.length}${filtered?'':` · ${esc(amount(stage.value)??'0')}`}</span>${ICONS.chevron}</button><div class="board-stage-body">${items.map(item=>card(item,canEdit,data.stages)).join('')||`<p class="board-empty">${filtered?'Nada coincide':'Sin oportunidades'}</p>`}</div></section>`}).join('')}</div>`;
    const rerender=()=>{const at=container.querySelector('#board-search')===document.activeElement;show();if(at){const i=container.querySelector('#board-search');i.focus();i.setSelectionRange(i.value.length,i.value.length)}};
    let searchTimer=null;
    container.querySelector('#board-search').oninput=e=>{clearTimeout(searchTimer);const v=e.target.value;searchTimer=setTimeout(()=>{ui.q=v;rerender()},180)};
    const whoSelect=container.querySelector('#board-who');if(whoSelect)whoSelect.onchange=e=>{ui.who=e.target.value;show()};
    container.querySelector('#board-stage').onchange=e=>{ui.stage=e.target.value;show()};
    const clear=container.querySelector('#board-clear');if(clear)clear.onclick=()=>{ui.q='';ui.who='';ui.stage='';show()};
    container.querySelectorAll('[data-toggle]').forEach(b=>b.onclick=()=>{const k=b.dataset.toggle;ui.collapsed.has(k)?ui.collapsed.delete(k):ui.collapsed.add(k);show()});
    container.querySelectorAll('[data-edit-opportunity]').forEach(el=>{
      const open=()=>edit(el.dataset.editOpportunity);
      el.onclick=e=>{if(e.target.closest('button,a,.board-move'))return;open()};
      el.onkeydown=e=>{if((e.key==='Enter'||e.key===' ')&&e.target===el){e.preventDefault();open()}};
    });
    container.querySelectorAll('[data-move]').forEach(b=>b.onclick=e=>{e.stopPropagation();ui.move=ui.move===b.dataset.move?null:b.dataset.move;show()});
    container.querySelectorAll('[data-move-to]').forEach(b=>b.onclick=e=>{e.stopPropagation();move(b.dataset.opp,b.dataset.moveTo)});
    container.querySelectorAll('[data-open-chat]').forEach(b=>b.onclick=e=>{e.stopPropagation();openChat(b.dataset.openChat,b)});
    container.querySelectorAll('[data-open-contact]').forEach(b=>b.onclick=e=>{e.stopPropagation();workspace.pendingContact=b.dataset.openContact;window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'contacts'}}))});
    container.querySelector('#board-pipeline').onchange=e=>load(e.target.value);
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

  /** Mover de etapa en un toque: sólo cambia la etapa, con la misma protección contra repetir la acción. */
  async function move(id,stageId){
    ui.move=null;const target=asList(board.stages).flatMap(st=>asList(st.items)).find(i=>i.id===id);
    const stageName=asList(board.stages).find(st=>st.id===stageId)?.name??'esa etapa';
    notice='Moviendo…';show();
    const payload={pipelineStageId:stageId};
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(id+JSON.stringify(payload)));
    const storageKey='agora-opportunity:'+id+':'+Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
    const requestId=sessionStorage.getItem(storageKey)||crypto.randomUUID().replace(/-/g,'');sessionStorage.setItem(storageKey,requestId);
    try{
      await request(`workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(id)}`,'POST',csrf,{...payload,requestId});
      sessionStorage.removeItem(storageKey);window.dispatchEvent(new Event('agora:opportunity-edited'));
      if(!active())return;notice=`${target?.name??'La oportunidad'} pasó a «${stageName}».`;await load(pipeline);
    }catch(error){
      if(error.code)sessionStorage.removeItem(storageKey);
      if(!active())return;notice=friendly(error);show();
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }
  async function openChat(contactId,button){
    button.disabled=true;
    try{
      const {conversation}=await request(`workspace/contact-conversation?connectionId=${encodeURIComponent(connection)}`,'POST',csrf,{contactId});
      window.dispatchEvent(new CustomEvent('agora:open-conversation',{detail:conversation}));
    }catch(error){button.disabled=false;if(active()){notice=friendly(error);show()}}
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
