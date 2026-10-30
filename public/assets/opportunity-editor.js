// The opportunity editor, shared by the conversation panel and the board.
// It builds the form and reads it back; who saves, and where, is the caller's.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];

export const STATUSES=[['open','Abierta'],['won','Ganada'],['lost','Perdida'],['abandoned','Abandonada']];

/**
 * One form for opening an opportunity and for editing one. `pipelines` is what
 * the account publishes, so no stage can be named that it does not have.
 */
export function opportunityForm({pipelines=[],assignees=[],opportunity=null,title=''}={}){
  const list=asList(pipelines);
  const current=opportunity||{};
  const chosen=list.find(p=>p.id===current.pipelineId)||list[0]||{stages:[]};
  const stages=asList(chosen.stages);
  return `<form class="opportunity-form" data-opportunity="${esc(current.id??'')}">
   <div class="section-label">${esc(title||(current.id?'EDITAR OPORTUNIDAD':'NUEVA OPORTUNIDAD'))}</div>
   <label>Nombre<input name="name" maxlength="190" value="${esc(current.name??'')}" required></label>
   <label>Embudo<select name="pipelineId">${list.map(p=>`<option value="${esc(p.id)}"${p.id===chosen.id?' selected':''}>${esc(p.name)}</option>`).join('')}</select></label>
   <label>Etapa<select name="pipelineStageId">${stages.map(stage=>`<option value="${esc(stage.id)}"${stage.id===current.pipelineStageId?' selected':''}>${esc(stage.name)}</option>`).join('')}</select></label>
   <label>Valor<input name="monetaryValue" type="number" min="0" max="999999999" step="0.01" value="${esc(typeof current.monetaryValue==='number'?current.monetaryValue:'')}"></label>
   <label>Responsable<select name="assignedTo"><option value="">Sin asignar</option>${asList(assignees).map(user=>`<option value="${esc(user.id)}"${user.id===current.assignedTo?' selected':''}>${esc(user.name)}</option>`).join('')}</select></label>
   <label>Estado<select name="status">${STATUSES.map(([value,label])=>`<option value="${value}"${value===(current.status??'open')?' selected':''}>${label}</option>`).join('')}</select></label>
   <div class="opportunity-form-actions"><button class="primary" data-save>${current.id?'Guardar cambios':'Crear oportunidad'}</button><button type="button" data-cancel>Cancelar</button></div>
   <p data-status role="status"></p>
  </form>`;
}

/** Keeps the stage list in step with the chosen pipeline. */
export function bindPipelineChoice(form,pipelines){
  const list=asList(pipelines);
  const select=form.querySelector('[name="pipelineId"]'),stages=form.querySelector('[name="pipelineStageId"]');
  if(!select||!stages)return;
  select.onchange=()=>{
    const chosen=list.find(p=>p.id===select.value);
    stages.innerHTML=asList(chosen?.stages).map(stage=>`<option value="${esc(stage.id)}">${esc(stage.name)}</option>`).join('');
  };
}

/** What the form asks for: everything when opening, only changes when editing. */
export function readOpportunityForm(form,opportunity=null){
  const read=name=>form.querySelector(`[name="${name}"]`)?.value??'';
  const value=read('monetaryValue');
  const all={
    name:read('name').trim(),
    pipelineId:read('pipelineId'),
    pipelineStageId:read('pipelineStageId'),
    monetaryValue:value===''?0:Number(value),
    assignedTo:read('assignedTo'),
    status:read('status'),
  };
  if(!opportunity)return all;
  const was={
    name:opportunity.name??'',
    pipelineId:opportunity.pipelineId??'',
    pipelineStageId:opportunity.pipelineStageId??'',
    monetaryValue:typeof opportunity.monetaryValue==='number'?opportunity.monetaryValue:0,
    assignedTo:opportunity.assignedTo??'',
    status:opportunity.status??'open',
  };
  const changed={};
  for(const [key,now] of Object.entries(all))if(now!==was[key])changed[key]=now;
  // A stage only means something inside its pipeline, so the two travel together.
  if(changed.pipelineId!==undefined&&changed.pipelineStageId===undefined)changed.pipelineStageId=all.pipelineStageId;
  return changed;
}
