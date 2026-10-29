import {workspace} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const initials=name=>String(name||'·').split(/\s+/).slice(0,2).map(part=>[...part][0]||'').join('').toUpperCase()||'··';

async function request(path){
  const r=await fetch('/api/'+path,{cache:'no-store',headers:{'Content-Type':'application/json'}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

export async function renderContacts(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='contacts';
  const connection=workspace.current();
  if(!connection){container.innerHTML='<p class="empty">Añade una cuenta desde Conexiones para ver contactos.</p>';return}
  container.innerHTML=`<div class="live-toolbar"><label class="search"><span aria-hidden="true">⌕</span><input id="contact-search" type="search" placeholder="Buscar por nombre, correo o teléfono" aria-label="Buscar contactos"></label></div><p id="contact-feedback" class="live-feedback" role="status">Cargando contactos…</p><div id="contact-results"></div>`;
  const $=s=>container.querySelector(s);
  let timer=null,generation=0;

  async function load(search=''){
    const version=++generation;
    $('#contact-feedback').textContent='Cargando contactos…';
    try{
      const data=await request(`workspace/contacts?connectionId=${encodeURIComponent(connection)}${search?'&query='+encodeURIComponent(search):''}`);
      if(!active()||version!==generation)return;
      $('#contact-feedback').textContent=data.total
        ? `${data.total} contacto${data.total===1?'':'s'}${data.restricted?' asignados a tu usuario':''}${data.partial?' · se muestran los más recientes':''}`
        : (search?'Ningún contacto coincide con esa búsqueda.':'Esta cuenta todavía no tiene contactos.');
      $('#contact-results').innerHTML=data.items.map(c=>{
        const rows=[['Correo',c.email],['Teléfono',c.phone],['Empresa',c.companyName],['Responsable',c.assignedName]].filter(([,v])=>v);
        return `<article class="card contact-card"><header><span class="avatar olive">${esc(initials(c.name))}</span><h3>${esc(c.name)}</h3></header>${rows.map(([label,value])=>`<div class="detail-row"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}${c.tags.length?`<div class="live-profile-tags">${c.tags.map(t=>`<span class="tag">${esc(t)}</span>`).join('')}</div>`:''}</article>`;
      }).join('');
    }catch(error){
      if(!active()||version!==generation)return;
      $('#contact-feedback').textContent=error.message;
      $('#contact-results').innerHTML='';
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  // The term is only sent once typing settles, so a search costs one read.
  $('#contact-search').oninput=e=>{clearTimeout(timer);const value=e.target.value.trim();timer=setTimeout(()=>load(value),450)};
  container.agoraLiveDispose=()=>{clearTimeout(timer);container.agoraViewInstance=null};
  await load();
}
