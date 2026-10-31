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
/** Agrupa campos por la carpeta que tienen en la cuenta, en el orden de la cuenta; lo que no tiene carpeta va al final. */
export function groupByFolder(items,directory=[]){
 const dir=asList(directory),folderOf=new Map(dir.map(f=>[f.id,f.folder||null]));
 const order=[];for(const f of dir){const k=f.folder||null;if(!order.includes(k))order.push(k)}
 const groups=new Map();
 for(const item of asList(items)){const k=folderOf.get(item.id)??null;if(!groups.has(k))groups.set(k,[]);groups.get(k).push(item)}
 const keys=[...groups.keys()].sort((a,b)=>(a===null)-(b===null)||(order.indexOf(a)<0?999:order.indexOf(a))-(order.indexOf(b)<0?999:order.indexOf(b)));
 const named=keys.some(k=>k!==null);
 return keys.map(k=>({title:k??(named?'Otros campos':'Campos personalizados'),items:groups.get(k)}));
}
// Grupos del formulario, en el orden en que se piensa en una persona.
const GROUPS=[['Contacto',['name','firstName','lastName','email','phone']],['Empresa',['companyName','website']],
 ['Dirección',['address1','city','state','postalCode','country']],['Seguimiento',['source','timezone','dateOfBirth']]];
const WIDE=['name','email','address1','website'];
export function contactForm(profile,directory=[],assignees=[]){
 const byKey=Object.fromEntries(EDITABLE.map(row=>[row[0],row]));
 const control=key=>{const [k,label,type]=byKey[key];return `<label class="${WIDE.includes(k)?'wide':''}">${esc(label)}<input type="${type}" name="${k}" value="${esc(profile[k]??'')}" maxlength="250"></label>`};
 const used=new Set(GROUPS.flatMap(([,keys])=>keys));
 const rest=EDITABLE.map(row=>row[0]).filter(key=>!used.has(key));
 const groups=GROUPS.map(([title,keys],i)=>{
  const inner=keys.filter(key=>byKey[key]).map(control).join('')+(i===GROUPS.length-1?rest.map(control).join('')
   +(asList(assignees).length?`<label class="wide">Responsable<select name="assignedTo"><option value="">Sin asignar</option>${assignees.map(user=>`<option value="${esc(user.id)}"${user.id===profile.assignedTo?' selected':''}>${esc(user.name)}</option>`).join('')}</select></label>`:'')
   +`<label class="contact-switch wide"><input type="checkbox" name="dnd"${profile.dnd?' checked':''}><span class="switch-track" aria-hidden="true"></span><span>No contactar<small>No se le enviarán mensajes desde ningún canal.</small></span></label>`:'');
  return `<fieldset class="contact-group"><legend>${esc(title)}</legend><div class="contact-grid">${inner}</div></fieldset>`;
 }).join('');
 // Every field the account defines is offered, even the ones with no value yet.
 const values=new Map(asList(profile.customFields).map(field=>[field.id,field]));
 const defined=asList(directory);
 const extra=asList(profile.customFields).filter(field=>!defined.some(d=>d.id===field.id)).map(field=>({id:field.id,name:'Campo personalizado',dataType:'TEXT',options:[]}));
 const customGroups=groupByFolder([...defined,...extra],defined);
 const customHtml=customGroups.length?customGroups.map(g=>`<fieldset class="contact-group"><legend>${esc(g.title)}</legend><div class="contact-grid">${g.items.map(field=>customControl(field,values.get(field.id))).join('')}</div></fieldset>`).join('')
  :'<fieldset class="contact-group"><legend>Campos personalizados</legend><span class="muted">Esta cuenta no define campos personalizados.</span></fieldset>';
 const noFolders=defined.length>0&&defined.every(f=>!f.folder)&&defined.some(f=>f.folderStatus&&f.folderStatus!=='ok');
 const tags=asList(profile.tags).map(tag=>`<span class="tag">${esc(tag)}<button type="button" data-drop-tag="${esc(tag)}" aria-label="Quitar etiqueta ${esc(tag)}">×</button></span>`).join('')||'<span class="muted">Sin etiquetas</span>';
 return `<form id="live-contact-form" class="live-contact-form contact-editor"><header class="live-profile-header"><div><span class="section-label">Editar ficha</span><h2>${esc(profile.name)}</h2></div><button type="button" id="live-edit-cancel" aria-label="Cancelar edición">×</button></header>
  ${groups}
  ${noFolders?'<p class="muted folder-note">No se pudieron leer las carpetas de campos de la cuenta; se muestran sin agrupar.</p>':''}${customHtml}
  <fieldset class="contact-group"><legend>Etiquetas</legend><div class="live-profile-tags" id="live-tag-list">${tags}</div>
   <div class="tag-add"><label class="sr-only" for="live-new-tag">Añadir etiqueta</label><input id="live-new-tag" maxlength="100" placeholder="Nueva etiqueta"><button type="button" id="live-add-tag">Añadir</button></div></fieldset>
  <div class="contact-save"><p id="live-contact-status" role="status"></p><button type="button" class="link-button" data-cancel-edit>Cancelar</button><button class="primary" id="live-save-contact">Guardar ficha</button></div></form>`;
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

/** Wires the tag chips and returns the pending additions and removals. Offers the tags that already exist in the account. */
export function bindTagEditor(root,contact,loadTags=null){
  const state={added:[],dropped:[]};
  const list=root.querySelector('#live-tag-list');
  const input=root.querySelector('#live-new-tag');
  const box=root.querySelector('.tag-add');
  let known=null,failed=false;
  const suggest=document.createElement('div');suggest.className='tag-suggest';suggest.hidden=true;box.after(suggest);
  const current=()=>[...list.querySelectorAll('[data-drop-tag]')].map(b=>b.dataset.dropTag);
  const paintSuggest=()=>{
    if(known===null&&!failed){suggest.hidden=false;suggest.innerHTML='<span class="muted">Buscando etiquetas…</span>';return}
    if(failed){suggest.hidden=false;suggest.innerHTML='<span class="muted">No se pudieron cargar las etiquetas existentes; puedes escribir una.</span>';return}
    const typed=input.value.trim().toLowerCase(),have=current().map(t=>t.toLowerCase());
    const options=known.filter(t=>!have.includes(t.toLowerCase())&&(!typed||t.toLowerCase().includes(typed))).slice(0,40);
    suggest.hidden=false;
    suggest.innerHTML=options.length?options.map(t=>`<button type="button" class="tag tag-option" data-pick-tag="${esc(t)}">${esc(t)}</button>`).join(''):`<span class="muted">${known.length?'Ninguna etiqueta coincide. «Añadir» crea una nueva.':'Esta cuenta todavía no tiene etiquetas.'}</span>`;
    suggest.querySelectorAll('[data-pick-tag]').forEach(b=>b.onclick=()=>{addValue(b.dataset.pickTag);input.focus()});
  };
  const ensure=()=>{
    if(known!==null||!loadTags){if(!loadTags){failed=true}return Promise.resolve()}
    return loadTags().then(items=>{known=items}).catch(()=>{failed=true});
  };
  const open=()=>{paintSuggest();ensure().then(paintSuggest)};
  const rebind=()=>root.querySelectorAll('[data-drop-tag]').forEach(button=>button.onclick=()=>{
    const tag=button.dataset.dropTag;
    state.added=state.added.filter(t=>t!==tag);
    if(asList(contact.tags).includes(tag)&&!state.dropped.includes(tag))state.dropped.push(tag);
    button.parentElement.remove();
    if(!suggest.hidden)paintSuggest();
  });
  const addValue=value=>{
    value=value.trim();if(!value)return;
    if(current().some(t=>t.toLowerCase()===value.toLowerCase())){input.value='';return}
    if(!state.added.includes(value))state.added.push(value);
    state.dropped=state.dropped.filter(t=>t!==value);
    input.value='';
    list.querySelector('.muted')?.remove();
    list.insertAdjacentHTML('beforeend',`<span class="tag">${esc(value)}<button type="button" data-drop-tag="${esc(value)}" aria-label="Quitar etiqueta ${esc(value)}">×</button></span>`);
    rebind();paintSuggest();
  };
  root.querySelector('#live-add-tag').onclick=()=>{if(input.value.trim())addValue(input.value);else{open();input.focus()}};
  input.onfocus=open;
  input.oninput=()=>{if(known!==null||failed)paintSuggest();else open()};
  input.onkeydown=event=>{if(event.key==='Enter'){event.preventDefault();addValue(input.value)}};
  rebind();
  return state;
}
