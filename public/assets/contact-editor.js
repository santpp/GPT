// The contact editor, shared by the conversation sheet and the contacts screen.
// It only builds markup and reads it back; who saves, and where, is the caller's.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];

const EDITABLE=[['name','Nombre completo','text'],['firstName','Nombre','text'],['lastName','Apellido','text'],['email','Correo','email'],['phone','Teléfono','tel'],['companyName','Empresa','text'],['website','Sitio web','url'],['address1','Dirección','text'],['city','Ciudad','text'],['state','Provincia o estado','text'],['postalCode','Código postal','text'],['country','País','text'],['source','Fuente','text'],['timezone','Zona horaria','text'],['dateOfBirth','Fecha de nacimiento','date']];
const READ_ONLY_TYPES=['FILE_UPLOAD','SIGNATURE'];
// One control per published field type, so every field can actually be filled.
export function customControl(field,current){
 const id=esc(field.id),name=esc(field.name||'Campo personalizado'),hint=field.placeholder?` placeholder="${esc(field.placeholder)}"`:'';
 const value=current?.value??'';
 const chosen=asList(current?.values).length?asList(current.values):(value?String(value).split(', ').filter(Boolean):[]);
 if(READ_ONLY_TYPES.includes(field.dataType))return `<label>${name}<input value="${esc(value)}" disabled></label>`;
 if(field.dataType==='CHECKBOX')return `<label class="live-check"><input type="checkbox" data-field="${id}" data-type="CHECKBOX"${current?.checked||value==='Sí'?' checked':''}>${name}</label>`;
 if(field.dataType==='MULTIPLE_OPTIONS'&&field.options?.length)
  return `<div class="live-field-group" data-field="${id}" data-type="MULTIPLE_OPTIONS"><span class="live-field-name">${name}</span>${field.options.map(option=>`<label class="live-check"><input type="checkbox" data-option="${esc(option)}"${chosen.includes(option)?' checked':''}>${esc(option)}</label>`).join('')}</div>`;
 if((field.dataType==='SINGLE_OPTIONS'||field.dataType==='RADIO')&&field.options?.length)
  return `<label>${name}<select data-field="${id}" data-type="SINGLE_OPTIONS"><option value="">Sin elegir</option>${field.options.map(option=>`<option${option===value?' selected':''}>${esc(option)}</option>`).join('')}</select></label>`;
 if(field.dataType==='LARGE_TEXT'||field.dataType==='TEXTBOX_LIST')
  return `<label>${name}<textarea data-field="${id}" data-type="${esc(field.dataType)}" rows="3" maxlength="5000"${hint}>${esc(value)}</textarea></label>`;
 const type=field.dataType==='NUMERICAL'||field.dataType==='MONETORY'?'number':field.dataType==='DATE'?'date':field.dataType==='EMAIL'?'email':field.dataType==='PHONE'?'tel':'text';
 return `<label>${name}<input type="${type}" data-field="${id}" data-type="${esc(field.dataType)}" value="${esc(value)}" maxlength="1000"${hint}></label>`;
}
export function contactForm(profile,directory=[],assignees=[]){
 const fields=EDITABLE.map(([key,label,type])=>`<label>${esc(label)}<input type="${type}" name="${key}" value="${esc(profile[key]??'')}" maxlength="250"></label>`).join('')
  +`<label class="live-check"><input type="checkbox" name="dnd"${profile.dnd?' checked':''}>No contactar</label>`
  +(asList(assignees).length?`<label>Responsable<select name="assignedTo"><option value="">Sin asignar</option>${assignees.map(user=>`<option value="${esc(user.id)}"${user.id===profile.assignedTo?' selected':''}>${esc(user.name)}</option>`).join('')}</select></label>`:'');
 // Every field the account defines is offered, even the ones with no value yet.
 const values=new Map(asList(profile.customFields).map(field=>[field.id,field]));
 const defined=asList(directory);
 const extra=asList(profile.customFields).filter(field=>!defined.some(d=>d.id===field.id)).map(field=>({id:field.id,name:'Campo personalizado',dataType:'TEXT',options:[]}));
 const custom=[...defined,...extra].map(field=>customControl(field,values.get(field.id))).join('')
  ||'<span class="muted">Esta cuenta no define campos personalizados.</span>';
 const tags=asList(profile.tags).map(tag=>`<span class="tag">${esc(tag)}<button type="button" data-drop-tag="${esc(tag)}" aria-label="Quitar etiqueta ${esc(tag)}">×</button></span>`).join('')||'<span class="muted">Sin etiquetas</span>';
 return `<form id="live-contact-form" class="live-contact-form"><header class="live-profile-header"><div><span class="section-label">EDITAR FICHA</span><h2>${esc(profile.name)}</h2></div><button type="button" id="live-edit-cancel" aria-label="Cancelar edición">×</button></header>${fields}<div class="section-label">CAMPOS PERSONALIZADOS</div>${custom}<div class="section-label">ETIQUETAS</div><div class="live-profile-tags" id="live-tag-list">${tags}</div><label>Añadir etiqueta<input id="live-new-tag" maxlength="100" placeholder="Escribe y pulsa Añadir"></label><button type="button" id="live-add-tag">Añadir etiqueta</button><button class="primary" id="live-save-contact">Guardar ficha</button><p id="live-contact-status" role="status"></p></form>`;
}

/** Reads a filled form back into the shape the server expects. */
export function readContactForm(form,contact){
  const fields={};
  for(const [key] of EDITABLE){
    const input=form.querySelector(`[name="${key}"]`);
    if(input&&input.value.trim()!==String(contact[key]??''))fields[key]=input.value.trim();
  }
  const dnd=form.querySelector('[name="dnd"]');
  if(dnd&&dnd.checked!==(contact.dnd===true))fields.dnd=dnd.checked;
  const assigned=form.querySelector('[name="assignedTo"]');
  if(assigned&&assigned.value!==String(contact.assignedTo??''))fields.assignedTo=assigned.value;
  // Each control reports its own shape: a box is a boolean, a group is a list.
  const previous=new Map(asList(contact.customFields).map(field=>[field.id,field]));
  const customFields=[];
  for(const node of form.querySelectorAll('[data-field]')){
    if(node.disabled)continue;
    const id=node.dataset.field,was=previous.get(id);
    let value;
    if(node.dataset.type==='MULTIPLE_OPTIONS')value=[...node.querySelectorAll('[data-option]')].filter(box=>box.checked).map(box=>box.dataset.option);
    else if(node.type==='checkbox')value=node.checked;
    else value=node.value;
    const before=Array.isArray(value)?asList(was?.values).join('\u0000')
      :typeof value==='boolean'?(was?.checked===true)
      :String(was?.value??'');
    const now=Array.isArray(value)?value.join('\u0000'):value;
    if(now!==before)customFields.push({id,value});
  }
  if(customFields.length)fields.customFields=customFields;
  return fields;
}

/** Wires the tag chips and returns the pending additions and removals. */
export function bindTagEditor(root,contact){
  const state={added:[],dropped:[]};
  const list=root.querySelector('#live-tag-list');
  const input=root.querySelector('#live-new-tag');
  const rebind=()=>root.querySelectorAll('[data-drop-tag]').forEach(button=>button.onclick=()=>{
    const tag=button.dataset.dropTag;
    state.added=state.added.filter(t=>t!==tag);
    if(asList(contact.tags).includes(tag)&&!state.dropped.includes(tag))state.dropped.push(tag);
    button.parentElement.remove();
  });
  const add=()=>{
    const value=input.value.trim();if(!value)return;
    if(!state.added.includes(value))state.added.push(value);
    state.dropped=state.dropped.filter(t=>t!==value);
    input.value='';
    list.insertAdjacentHTML('beforeend',`<span class="tag">${esc(value)}<button type="button" data-drop-tag="${esc(value)}" aria-label="Quitar etiqueta ${esc(value)}">×</button></span>`);
    rebind();
  };
  root.querySelector('#live-add-tag').onclick=add;
  input.onkeydown=event=>{if(event.key==='Enter'){event.preventDefault();add()}};
  rebind();
  return state;
}
