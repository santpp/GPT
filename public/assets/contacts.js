import {workspace,friendly} from './workspace.js';
import {contactForm,readContactForm,bindTagEditor} from './contact-editor.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const initials=name=>String(name||'·').split(/\s+/).slice(0,2).map(part=>[...part][0]||'').join('').toUpperCase()||'··';
const asList=value=>Array.isArray(value)?value:[];

async function request(path,method='GET',body=null){
  // Todo lo que cambia algo lleva el token de la sesión; sin él el servidor lo rechaza.
  const csrf=method!=='GET'?(workspace.me?.csrf||''):'';
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

const DETAILS=[['email','Correo'],['phone','Teléfono'],['companyName','Empresa'],['website','Sitio web'],['address','Dirección'],['country','País'],['timezone','Zona horaria'],['source','Fuente'],['dateOfBirth','Fecha de nacimiento'],['assignedName','Responsable']];

export async function renderContacts(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='contacts';
  const connection=workspace.current();
  if(!connection){container.innerHTML='<p class="empty">Añade una cuenta desde Conexiones para ver contactos.</p>';return}
  container.innerHTML=`<div class="records-layout" id="records-layout">
   <div class="records-list">
    <div class="live-toolbar"><label class="search"><span aria-hidden="true">⌕</span><input id="contact-search" type="search" placeholder="Buscar por nombre, correo o teléfono" aria-label="Buscar contactos"></label></div>
    <p id="contact-feedback" class="live-feedback" role="status">Cargando contactos…</p>
    <div id="contact-results" role="list"></div>
   </div>
   <section class="records-record" id="contact-record" aria-live="polite"><p class="empty">Elige una persona de la lista para ver y editar su ficha.</p></section>
  </div>`;
  const $=s=>container.querySelector(s);
  const layout=$('#records-layout');
  let timer=null,generation=0,selected=null;

  function rows(items){
    return items.map(c=>`<button class="record-row${c.id===selected?' selected':''}" data-contact="${esc(c.id)}" role="listitem">
      <span class="avatar olive">${esc(initials(c.name))}</span>
      <span class="record-row-text"><strong>${esc(c.name)}</strong><small>${esc(c.email||c.phone||c.companyName||'Sin datos de contacto')}</small>${asList(c.tags).length?`<span class="record-row-tags">${c.tags.slice(0,3).map(t=>`<span class="tag">${esc(t)}</span>`).join('')}</span>`:''}</span>
    </button>`).join('');
  }

  async function load(search=''){
    const version=++generation;
    $('#contact-feedback').textContent='Cargando contactos…';
    try{
      const data=await request(`workspace/contacts?connectionId=${encodeURIComponent(connection)}${search?'&query='+encodeURIComponent(search):''}`);
      if(!active()||version!==generation)return;
      $('#contact-feedback').textContent=data.total
        ? `${data.total} contacto${data.total===1?'':'s'}${data.restricted?' asignados a tu usuario':''}${data.partial?' · se muestran los más recientes':''}`
        : (search?'Ningún contacto coincide con esa búsqueda.':'Esta cuenta todavía no tiene contactos.');
      $('#contact-results').innerHTML=rows(asList(data.items));
      bindRows();
    }catch(error){
      if(!active()||version!==generation)return;
      $('#contact-feedback').textContent=friendly(error);
      $('#contact-results').innerHTML='';
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  function bindRows(){
    container.querySelectorAll('[data-contact]').forEach(button=>button.onclick=()=>open(button.dataset.contact));
  }

  let record=null,editing=false,tags={added:[],dropped:[]};
  const canWrite=()=>{const c=workspace.me?.user?.capabilities||{};return c.viewConversations!==false&&c.sendMessages!==false};

  async function open(contactId){
    selected=contactId;editing=false;record=null;
    layout.classList.add('record-open');
    container.querySelectorAll('[data-contact]').forEach(b=>b.classList.toggle('selected',b.dataset.contact===contactId));
    $('#contact-record').innerHTML='<p class="empty">Abriendo la ficha…</p>';
    try{
      const data=await request(`workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(contactId)}`);
      if(!active()||selected!==contactId)return;
      record=data;show();
    }catch(error){
      if(!active()||selected!==contactId)return;
      $('#contact-record').innerHTML=`<p class="empty">${esc(friendly(error))}</p>`;
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  function show(){
    const panel=$('#contact-record');
    const contact=record.contact;
    if(editing){
      panel.innerHTML=contactForm(contact,asList(record.fields),asList(record.assignees));
      tags=bindTagEditor(panel,contact);
      panel.querySelector('#live-edit-cancel').onclick=()=>{editing=false;show()};
      panel.querySelector('#live-contact-form').onsubmit=save;
      return;
    }
    const details=DETAILS.map(([key,label])=>contact[key]?`<div><span>${esc(label)}</span><strong>${esc(contact[key])}</strong></div>`:'').join('')
      ||'<div><span>Sin datos</span><strong>Esta ficha está vacía.</strong></div>';
    const custom=asList(contact.customFields).filter(field=>field.value!==''&&field.value!==null&&field.value!==undefined)
      .map(field=>`<div><span>${esc(field.name||'Campo personalizado')}</span><strong>${esc(Array.isArray(field.values)&&field.values.length?field.values.join(', '):field.value)}</strong></div>`).join('')
      ||'<div><span>Campos personalizados</span><strong>Sin valores todavía.</strong></div>';
    panel.innerHTML=`<header class="record-header">
      <button class="mobile-back" id="record-back" aria-label="Volver a la lista">←</button>
      <span class="avatar olive">${esc(initials(contact.name))}</span>
      <div><h2>${esc(contact.name)}</h2><p>${esc(contact.email||contact.phone||'Sin datos de contacto')}</p></div>
      <div class="record-actions">${canWrite()?'<button class="primary" id="record-message">Enviar mensaje</button>':''}${record.canEdit?'<button class="secondary" id="record-edit">Editar ficha</button>':''}</div>
     </header>
     ${contact.dnd?'<p class="record-flag">Marcado como no contactar.</p>':''}
     <div class="record-columns">
      <div><div class="section-label">DATOS</div><div class="live-profile-details">${details}</div></div>
      <div><div class="section-label">CAMPOS PERSONALIZADOS</div><div class="live-profile-fields">${custom}</div>
       <div class="section-label">ETIQUETAS</div><div class="live-profile-tags">${asList(contact.tags).length?contact.tags.map(t=>`<span class="tag">${esc(t)}</span>`).join(''):'<span class="muted">Sin etiquetas</span>'}</div></div>
     </div>
     <p id="record-status" class="live-feedback" role="status"></p>`;
    panel.querySelector('#record-back').onclick=()=>{layout.classList.remove('record-open')};
    const edit=panel.querySelector('#record-edit');
    if(edit)edit.onclick=()=>{editing=true;show()};
    const write=panel.querySelector('#record-message');
    if(write)write.onclick=async()=>{
      write.disabled=true;const status=panel.querySelector('#record-status');status.textContent='Abriendo la conversación…';
      try{
        const {conversation}=await request(`workspace/contact-conversation?connectionId=${encodeURIComponent(connection)}`,'POST',{contactId:selected});
        if(!active())return;
        // La bandeja la abre al cargar, aunque no esté entre las últimas.
        window.dispatchEvent(new CustomEvent('agora:open-conversation',{detail:conversation}));
      }catch(error){status.textContent=friendly(error);write.disabled=false}
    };
  }

  async function save(event){
    event.preventDefault();
    const form=event.target,status=form.querySelector('#live-contact-status'),button=form.querySelector('#live-save-contact');
    const fields=readContactForm(form,record.contact);
    if(Object.keys(fields).length===0&&!tags.added.length&&!tags.dropped.length){status.textContent='No has cambiado nada todavía.';return}
    button.disabled=true;status.textContent='Guardando la ficha…';
    try{
      await request(`workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(selected)}`,'POST',
        {fields,addTags:tags.added,removeTags:tags.dropped,requestId:crypto.randomUUID().replace(/-/g,'')});
      const fresh=await request(`workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(selected)}&refresh=1`);
      if(!active())return;
      record=fresh;editing=false;show();
      const row=container.querySelector(`[data-contact="${CSS.escape(selected)}"] strong`);
      if(row)row.textContent=fresh.contact.name;
    }catch(error){
      if(!active())return;
      status.textContent=friendly(error);
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }finally{if(active())button.disabled=false}
  }

  // The term is only sent once typing settles, so a search costs one read.
  $('#contact-search').oninput=e=>{clearTimeout(timer);const value=e.target.value.trim();timer=setTimeout(()=>load(value),450)};
  container.agoraLiveDispose=()=>{clearTimeout(timer);container.agoraViewInstance=null};
  await load();
}
