// «Consumos»: lo que cada subcuenta gasta en la cuenta de origen este mes y su plan activo. Sólo la persona propietaria.
// El token de agencia se escribe aquí, se guarda cifrado en el servidor y no vuelve nunca a esta pantalla.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const MONTHS=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const usd=(v,d=2)=>v===null||v===undefined||!Number.isFinite(Number(v))?'—':new Intl.NumberFormat('es-ES',{style:'currency',currency:'USD',currencyDisplay:'narrowSymbol',minimumFractionDigits:d,maximumFractionDigits:Math.max(d,Number(v)!==0&&Math.abs(Number(v))<1?4:d)}).format(Number(v));
const monthList=()=>{const now=new Date(),out=[];for(let i=0;i<18;i++){const d=new Date(now.getFullYear(),now.getMonth()-i,1),v=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;out.push(`<option value="${v}">${MONTHS[d.getMonth()][0].toUpperCase()+MONTHS[d.getMonth()].slice(1)} ${d.getFullYear()}</option>`)}return out.join('')};
let csrf='';
async function api(path,method='GET',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',headers:{'Content-Type':'application/json',...(method!=='GET'?{'X-CSRF-Token':csrf}:{})},...(body!==null?{body:JSON.stringify(body)}:{})});
  let d;try{d=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error(d.error||'No se pudo completar.'),{status:r.status,code:d.code,detail:d.detail});
  return d;
}

const fileSafe=t=>String(t).normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^A-Za-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)||'subcuenta';
const monthName=v=>{const [y,m]=v.split('-');return `${MONTHS[Number(m)-1][0].toUpperCase()+MONTHS[Number(m)-1].slice(1)} ${y}`};
const planText=(p,l)=>l?.tipo==='agencia'?'Cuenta de la agencia':l?.tipo==='otra'?'No está conectada a Ágora':!p||(!p.plan&&!p.estado)?'Sin plan de SaaS':`${p.plan||'Sin plan'}${p.estado?` (${p.estado})`:''}${p.precio&&p.precio.monto!=null?` · ${usd(p.precio.monto)}${p.precio.cada?' / '+p.precio.cada:''}`:''}`;
const when=v=>(v||'').slice(0,16).replace('T',' ');

/** El reporte en texto plano, para guardarlo o pegarlo donde haga falta. */
function toText(r,list){
  const pad=(t,n)=>String(t).padEnd(n),rpad=(t,n)=>String(t).padStart(n),line='='.repeat(64);
  const out=[line,'ÁGORA · REPORTE DE CONSUMOS',`${monthName(r.month)}`,`Generado: ${new Date().toLocaleString('es-ES')}`,line,'',
   `Gasto total:     ${usd(list.reduce((a,l)=>a+l.gasto,0),2)}`,`Recargas totales: ${usd(list.reduce((a,l)=>a+l.recargas,0),2)}`,`Subcuentas:      ${r.subcuentas??list.length}`,''];
  for(const l of list){
    out.push('-'.repeat(64),l.locationName||l.locationId,`Plan: ${planText(l.plan,l)}`,'',`  Gasto del mes:  ${usd(l.gasto,4)}`,`  Recargas:       ${usd(l.recargas,4)}`,`  Movimientos:    ${l.movimientos}`);
    if(l.categorias.length){out.push('','  En qué se gasta:');for(const c of l.categorias)out.push(`    ${pad(c.tipo,36)}${rpad(usd(c.monto,4),14)}`)}
    if(l.dias.length){out.push('','  Día por día:');for(const d of l.dias)out.push(`    ${pad(d.dia,36)}${rpad(usd(d.monto,4),14)}`)}
    if(l.ultimos.length){out.push('','  Movimientos:');for(const m of l.ultimos)out.push(`    ${pad(when(m.at),17)}${pad(m.tipo+(m.direccion==='recarga'?' (recarga)':''),28)}${rpad(usd(m.monto,4),14)}`)}
    out.push('');
  }
  return out.join('\n');
}
function download(name,text){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob(['﻿'+text],{type:'text/plain;charset=utf-8'}));a.download=name;document.body.append(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},500)}

/** La hoja del reporte: la misma para verla en pantalla y para guardarla como PDF. */
function sheet(r,list){
  const total=list.reduce((a,l)=>a+l.gasto,0),recs=list.reduce((a,l)=>a+l.recargas,0);
  const bars=days=>{const max=Math.max(...days.map(d=>d.monto),0.0001);return `<div class="cons-days" role="img" aria-label="Gasto por día">${days.map(d=>`<span title="${esc(d.dia)}: ${esc(usd(d.monto,4))}" style="height:${Math.max(4,Math.round(d.monto/max*100))}%"></span>`).join('')}</div>`};
  return `<article class="cons-sheet">
   <header class="cons-sheet-h"><img src="/assets/agora-logo.png" alt="Ágora"><div><h2>Reporte de consumos</h2><p>${esc(monthName(r.month))}${list.length===1?' · '+esc(list[0].locationName||''):''}</p></div></header>
   <div class="cons-kpis"><div><small>Gasto del mes</small><strong>${usd(total)}</strong></div><div><small>Recargas</small><strong>${usd(recs)}</strong></div><div><small>${list.length===1?'Movimientos':'Subcuentas'}</small><strong>${list.length===1?list[0].movimientos:(r.subcuentas??list.length)}</strong></div></div>
   ${list.map(l=>{const top=Math.max(...l.categorias.map(c=>c.monto),0.0001);return `<section class="cons-sheet-sub">
     <div class="cons-sheet-title"><h3>${esc(l.locationName||l.locationId)}</h3><span>${esc(planText(l.plan,l))}</span></div>
     <div class="cons-kpis small"><div><small>Gasto</small><strong>${usd(l.gasto,4)}</strong></div><div><small>Recargas</small><strong>${usd(l.recargas,4)}</strong></div><div><small>Movimientos</small><strong>${l.movimientos}</strong></div></div>
     ${l.categorias.length?`<h4>En qué se gasta</h4><table class="cons-split"><tbody>${l.categorias.map(c=>`<tr><td>${esc(c.tipo)}</td><td class="bar"><i style="width:${Math.round(c.monto/top*100)}%"></i></td><td class="n">${usd(c.monto,4)}</td></tr>`).join('')}</tbody></table>`:''}
     ${l.dias.length?`<h4>Día por día</h4>${bars(l.dias)}`:''}
     ${l.ultimos.length?`<h4>Movimientos${l.ultimos.length<l.movimientos?` (los ${l.ultimos.length} más recientes de ${l.movimientos})`:''}</h4><table class="cons-moves"><thead><tr><th>Fecha</th><th>Tipo</th><th class="n">Monto</th></tr></thead><tbody>${l.ultimos.map(m=>`<tr><td>${esc(when(m.at))}</td><td>${esc(m.tipo)}${m.direccion==='recarga'?' <em>recarga</em>':''}</td><td class="n">${usd(m.monto,4)}</td></tr>`).join('')}</tbody></table>`:'<p class="cons-note">Sin movimientos este mes.</p>'}
    </section>`}).join('')}
   <footer class="cons-sheet-f">Generado por Ágora · ${esc(new Date().toLocaleString('es-ES'))}</footer>
  </article>`;
}

export async function renderConsumption(container){
  const instance=Symbol();container.agoraConsumptionInstance=instance;
  const active=()=>container.agoraConsumptionInstance===instance&&container.dataset.view==='consumption';
  container.innerHTML='<p class="empty">Abriendo…</p>';
  let settings;
  try{const me=await (await fetch('/api/me',{cache:'no-store'})).json();csrf=me.csrf||'';if(me.user?.role!=='administrator'){container.innerHTML='<p class="settings-notice">Esta pestaña es sólo de la persona propietaria.</p>';return}settings=await api('admin/consumption')}
  catch(e){if(active())container.innerHTML=`<p class="settings-notice">${esc(e.message)}</p>`;return}
  if(!active())return;
  const connections=settings.connections||[];
  container.innerHTML=`<div class="cons">
   <section class="cons-block" id="cons-conn"></section>
   <section class="cons-block" id="cons-main"><h3>Consumos del mes</h3>
    <div class="cons-bar">
     <label>Subcuenta<select id="cons-sub"><option value="">Todas (subcuentas y agencia)</option><option value="__agency">Sólo la agencia (movimientos propios)</option>${connections.map(c=>`<option value="${esc(c.location_id)}">${esc(c.name)}</option>`).join('')}</select></label>
     <label>Mes<select id="cons-month">${monthList()}</select></label>
     <button type="button" class="primary" id="cons-run">Actualizar</button>
    </div>
    <p class="cons-note" id="cons-status" role="status"></p>
    <div id="cons-out"></div>
   </section>
   <div id="cons-view" hidden></div>
   <div id="cons-print" aria-hidden="true"></div></div>`;
  const $=s=>container.querySelector(s);
  const now=new Date();$('#cons-month').value=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  let last=null;

  function paintConn(){
    const ready=settings.tokenSaved&&settings.companyId;
    $('#cons-conn').innerHTML=`<h3>Conexión de agencia</h3>
     ${ready?'<p class="cons-note">Conectada con el token de agencia. No se vuelve a mostrar.</p>':'<p class="cons-note">Con el token de agencia (Agency View → Settings → Private Integrations, permiso <b>saas/company.read</b>) Ágora lee el gasto y el plan de cada subcuenta. Se guarda cifrado en el servidor y no se vuelve a mostrar.</p>'}
     <details ${ready?'':'open'}><summary>${ready?'Cambiar el Company ID o el token':'Conectar la agencia'}</summary>
     <form id="cons-form" class="cons-form" autocomplete="off">
      <label>Company ID<input name="companyId" value="${esc(settings.companyId)}" placeholder="Agency View → Settings → Company" maxlength="80" spellcheck="false"></label>
      <label>Token de agencia<input name="token" type="password" autocomplete="new-password" placeholder="${settings.tokenSaved?`guardado · termina en ${esc(settings.tokenHint)} · escribe otro para cambiarlo`:'pega aquí el token (pit-…)'}" spellcheck="false"></label>
      <div class="cons-actions"><button class="primary">Guardar</button>${settings.tokenSaved?'<button type="button" id="cons-forget">Quitar el token</button>':''}</div>
      <p class="cons-note" id="cons-saved" role="status"></p>
     </form></details>`;
    $('#cons-form').onsubmit=async e=>{e.preventDefault();const f=e.target;const body={companyId:f.companyId.value};if(f.token.value.trim())body.token=f.token.value;
      try{settings={...settings,...await api('admin/consumption/settings','POST',body)};paintConn()}catch(x){$('#cons-saved').textContent=x.message}};
    $('#cons-forget')?.addEventListener('click',async()=>{if(!confirm('¿Quitar el token de agencia? Tendrás que pegarlo otra vez para consultar.'))return;try{settings={...settings,...await api('admin/consumption/token','DELETE')};paintConn()}catch(x){$('#cons-saved').textContent=x.message}});
  }
  paintConn();

  const exportBar=(list,label)=>`<div class="cons-actions"><button type="button" data-txt>Descargar .txt</button><button type="button" data-pdf>Guardar como PDF</button>${label}</div>`;
  function bindExport(root,list){
    const name=`Consumos-${last.month}${list.length===1?'-'+fileSafe(list[0].locationName||list[0].locationId):''}`;
    root.querySelector('[data-txt]')?.addEventListener('click',()=>download(name+'.txt',toText(last,list)));
    root.querySelector('[data-pdf]')?.addEventListener('click',()=>{$('#cons-print').innerHTML=sheet(last,list);const t=document.title;document.title=name;const theme=document.documentElement.dataset.theme;document.documentElement.dataset.theme='light';document.body.classList.add('cons-printing');
      const done=()=>{document.body.classList.remove('cons-printing');if(theme)document.documentElement.dataset.theme=theme;document.title=t;$('#cons-print').innerHTML='';window.removeEventListener('afterprint',done)};window.addEventListener('afterprint',done);setTimeout(()=>window.print(),60)});
  }
  function openSheet(l){
    $('#cons-main').hidden=true;$('#cons-conn').hidden=true;const v=$('#cons-view');v.hidden=false;
    v.innerHTML=`<div class="cons-actions cons-top"><button type="button" data-back>← Volver</button></div>${exportBar([l],'')}${sheet(last,[l])}`;
    v.querySelector('[data-back]').onclick=()=>{v.hidden=true;v.innerHTML='';$('#cons-main').hidden=false;$('#cons-conn').hidden=false;window.scrollTo(0,0)};
    bindExport(v,[l]);window.scrollTo(0,0);
  }
  function paintReport(r){
    last=r;const list=r.locations;
    $('#cons-out').innerHTML=`<div class="cons-totals"><div><small>Gasto del mes</small><strong>${usd(r.totales.gasto)}</strong></div><div><small>Recargas del mes</small><strong>${usd(r.totales.recargas)}</strong></div><div><small>Subcuentas</small><strong>${r.subcuentas??list.length}</strong></div></div>
     ${exportBar(list,'')}
     ${r.partial?`<p class="cons-note warn">Hay más de ${r.pages*1000} movimientos: se muestran los primeros.</p>`:''}
     <div class="cons-list">${list.map((l,i)=>`<article class="cons-card">
       <header><div><h4>${esc(l.locationName||l.locationId)}</h4><span class="cons-plan${l.plan?.plan||l.plan?.estado?'':' muted'}">${esc(planText(l.plan,l))}</span></div><div class="cons-money"><strong>${usd(l.gasto)}</strong><small>gasto</small></div></header>
       <dl><div><dt>Recargas</dt><dd>${usd(l.recargas)}</dd></div><div><dt>Movimientos</dt><dd>${l.movimientos}</dd></div>${l.sinClasificar?`<div><dt>Sin clasificar</dt><dd>${l.sinClasificar}</dd></div>`:''}</dl>
       ${l.categorias.length?`<div class="cons-mini">${l.categorias.slice(0,4).map(c=>`<span>${esc(c.tipo)} <b>${usd(c.monto,2)}</b></span>`).join('')}</div>`:''}
       <button type="button" class="cons-open" data-i="${i}">Ver el reporte</button></article>`).join('')}</div>
          <p class="cons-note">Actualizado ${esc(new Date(r.readAt).toLocaleString('es-ES'))}</p>`;
    $('#cons-out').querySelectorAll('.cons-open').forEach(b=>b.onclick=()=>openSheet(list[Number(b.dataset.i)]));
    bindExport($('#cons-out'),list);
  }
  async function run(){
    const b=$('#cons-run');b.disabled=true;$('#cons-status').textContent='Consultando…';$('#cons-out').innerHTML='';
    try{const q=new URLSearchParams({month:$('#cons-month').value});if($('#cons-sub').value)q.set('locationId',$('#cons-sub').value);
      const r=await api('admin/consumption/report?'+q);if(!active())return;$('#cons-status').textContent='';paintReport(r)}
    catch(e){$('#cons-status').textContent=e.message+(e.detail?.message?` — ${e.detail.message}`:'')}
    finally{b.disabled=false}
  }
  $('#cons-run').onclick=run;
}
