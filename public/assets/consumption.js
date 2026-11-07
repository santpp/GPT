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

export async function renderConsumption(container){
  const instance=Symbol();container.agoraConsumptionInstance=instance;
  const active=()=>container.agoraConsumptionInstance===instance&&container.dataset.view==='consumption';
  container.innerHTML='<p class="empty">Abriendo…</p>';
  let settings;
  try{const me=await (await fetch('/api/me',{cache:'no-store'})).json();csrf=me.csrf||'';if(me.user?.role!=='administrator'){container.innerHTML='<p class="settings-notice">Esta pestaña es sólo de la persona propietaria.</p>';return}settings=await api('admin/consumption')}
  catch(e){if(active())container.innerHTML=`<p class="settings-notice">${esc(e.message)}</p>`;return}
  if(!active())return;
  const connections=settings.connections||[];
  const subOptions=(all)=>`${all?'<option value="">Todas las subcuentas</option>':'<option value="">Elige una subcuenta</option>'}${connections.map(c=>`<option value="${esc(c.location_id)}">${esc(c.name)}</option>`).join('')}`;
  container.innerHTML=`<div class="cons">
   <section class="cons-block" id="cons-conn"></section>
   <section class="cons-block"><h3>Consumos del mes</h3>
    <div class="cons-bar">
     <label>Subcuenta<select id="cons-sub">${subOptions(true)}</select></label>
     <label>Mes<select id="cons-month">${monthList()}</select></label>
     <label>Consulta<select id="cons-method"><option value="POST">POST (versión v3)</option><option value="GET">GET</option></select></label>
     <button type="button" class="primary" id="cons-run">Actualizar</button>
    </div>
    <p class="cons-note" id="cons-status" role="status"></p>
    <div id="cons-out"></div>
   </section>
   <section class="cons-block"><h3>Probar y depurar</h3>
    <p class="cons-note">Hace una llamada y enseña la respuesta tal cual la devuelve la cuenta (con el token tachado). Úsalo para ver cuál de las dos formas contesta y qué campos trae.</p>
    <div class="cons-bar">
     <label>Subcuenta<select id="dbg-sub">${subOptions(false)}</select></label>
     <label>Mes<select id="dbg-month">${monthList()}</select></label>
     <label>Versión<select id="dbg-version"><option value="">La de cada consulta</option><option value="v3">v3</option><option value="2021-07-28">2021-07-28</option><option value="2021-04-15">2021-04-15</option></select></label>
     <label>Filas<select id="dbg-limit"><option>20</option><option selected>50</option><option>200</option><option>1000</option></select></label>
    </div>
    <div class="cons-actions">
     <button type="button" data-dbg="transactions:POST">Probar transacciones · POST</button>
     <button type="button" data-dbg="transactions:GET">Probar transacciones · GET</button>
     <button type="button" data-dbg="balance:GET">Probar saldo de la subcuenta</button>
     <button type="button" data-dbg="subscription:GET">Probar plan de la subcuenta</button>
    </div>
    <div id="dbg-out" aria-live="polite"></div>
   </section></div>`;
  const $=s=>container.querySelector(s);
  const now=new Date();const current=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  $('#cons-month').value=current;$('#dbg-month').value=current;

  function paintConn(){
    $('#cons-conn').innerHTML=`<h3>Conexión de agencia</h3>
     <p class="cons-note">Con el token de agencia (Agency View → Settings → Private Integrations, con el permiso <b>saas/company.read</b>) Ágora lee el gasto y el plan de cada subcuenta. Se guarda cifrado en el servidor y no se vuelve a mostrar.</p>
     <form id="cons-form" class="cons-form" autocomplete="off">
      <label>Company ID<input name="companyId" value="${esc(settings.companyId)}" placeholder="Agency View → Settings → Company" maxlength="80" spellcheck="false"></label>
      <label>Token de agencia<input name="token" type="password" autocomplete="new-password" placeholder="${settings.tokenSaved?`guardado · termina en ${esc(settings.tokenHint)} · escribe otro para cambiarlo`:'pega aquí el token (pit-…)'}" spellcheck="false"></label>
      <div class="cons-actions"><button class="primary">Guardar</button>${settings.tokenSaved?'<button type="button" id="cons-forget">Quitar el token</button>':''}<span class="cons-state ${settings.tokenSaved&&settings.companyId?'':'off'}">${settings.tokenSaved&&settings.companyId?'Listo para consultar':'Falta '+(settings.companyId?'el token':'el Company ID'+(settings.tokenSaved?'':' y el token'))}</span></div>
      <p class="cons-note" id="cons-saved" role="status"></p>
     </form>`;
    $('#cons-form').onsubmit=async e=>{e.preventDefault();const f=e.target;const body={companyId:f.companyId.value};if(f.token.value.trim())body.token=f.token.value;
      try{settings={...settings,...await api('admin/consumption/settings','POST',body)};paintConn();$('#cons-saved').textContent='Guardado.'}catch(x){$('#cons-saved').textContent=x.message}};
    $('#cons-forget')?.addEventListener('click',async()=>{if(!confirm('¿Quitar el token de agencia? Tendrás que pegarlo otra vez para consultar.'))return;try{settings={...settings,...await api('admin/consumption/token','DELETE')};paintConn()}catch(x){$('#cons-saved').textContent=x.message}});
  }
  paintConn();

  const bars=days=>{const max=Math.max(...days.map(d=>d.monto),0.0001);return `<div class="cons-days" role="img" aria-label="Gasto por día">${days.map(d=>`<span title="${esc(d.dia)}: ${esc(usd(d.monto,4))}" style="height:${Math.max(4,Math.round(d.monto/max*100))}%"></span>`).join('')}</div>`};
  function paintReport(r){
    const planLine=p=>!p?'':p.plan||p.estado?`<span class="cons-plan"><b>${esc(p.plan||'Sin plan')}</b>${p.estado?` · ${esc(p.estado)}`:''}${p.precio&&p.precio.monto!=null?` · ${esc(usd(p.precio.monto))}${p.precio.cada?' / '+esc(p.precio.cada):''}`:''}</span>`:`<span class="cons-plan muted">Sin plan de SaaS${p.errores?.length?' ('+esc(p.errores.join(', '))+')':''}</span>`;
    $('#cons-out').innerHTML=`<div class="cons-totals"><div><small>Gasto del mes</small><strong>${usd(r.totales.gasto)}</strong></div><div><small>Recargas del mes</small><strong>${usd(r.totales.recargas)}</strong></div><div><small>Subcuentas</small><strong>${r.locations.length}</strong></div></div>
     ${r.partial?'<p class="cons-note warn">Hay más de '+(r.pages*1000)+' movimientos: se muestran los primeros.</p>':''}
     <div class="cons-list">${r.locations.map(l=>`<article class="cons-card">
       <header><div><h4>${esc(l.locationName||l.locationId)}</h4>${planLine(l.plan)}</div><div class="cons-money"><strong>${usd(l.gasto)}</strong><small>gasto</small></div></header>
       <dl><div><dt>Recargas</dt><dd>${usd(l.recargas)}</dd></div><div><dt>Saldo actual</dt><dd>${usd(l.plan?.saldo)}</dd></div><div><dt>Movimientos</dt><dd>${l.movimientos}</dd></div>${l.sinClasificar?`<div><dt>Sin clasificar</dt><dd>${l.sinClasificar}</dd></div>`:''}</dl>
       ${l.categorias.length?`<details><summary>En qué se gasta</summary><table><thead><tr><th>Tipo</th><th class="n">Monto</th></tr></thead><tbody>${l.categorias.map(c=>`<tr><td>${esc(c.tipo)}</td><td class="n">${usd(c.monto,4)}</td></tr>`).join('')}</tbody></table></details>`:''}
       ${l.dias.length?`<details><summary>Día por día</summary>${bars(l.dias)}</details>`:''}
       ${l.ultimos.length?`<details><summary>Últimos movimientos</summary><table><thead><tr><th>Fecha</th><th>Tipo</th><th class="n">Monto</th></tr></thead><tbody>${l.ultimos.map(m=>`<tr><td>${esc((m.at||'').slice(0,16).replace('T',' '))}</td><td>${esc(m.tipo)}${m.direccion==='recarga'?' <em>(recarga)</em>':''}</td><td class="n">${usd(m.monto,4)}</td></tr>`).join('')}</tbody></table></details>`:''}
     </article>`).join('')}</div>
     ${r.otros?.length?`<p class="cons-note">Movimientos de subcuentas que no están conectadas a Ágora: ${r.otros.length} (gasto ${usd(r.otros.reduce((a,o)=>a+o.gasto,0))}).</p>`:''}
     <p class="cons-note">Consultado con ${esc(r.method)} · actualizado ${esc(new Date(r.readAt).toLocaleString('es-ES'))}</p>`;
  }
  async function run(){
    const b=$('#cons-run');b.disabled=true;$('#cons-status').textContent='Consultando…';$('#cons-out').innerHTML='';
    try{const q=new URLSearchParams({month:$('#cons-month').value,method:$('#cons-method').value});if($('#cons-sub').value)q.set('locationId',$('#cons-sub').value);
      const r=await api('admin/consumption/report?'+q);if(!active())return;$('#cons-status').textContent='';paintReport(r)}
    catch(e){$('#cons-status').textContent=e.message+(e.detail?.message?` — ${e.detail.message}`:'')}
    finally{b.disabled=false}
  }
  $('#cons-run').onclick=run;

  container.querySelectorAll('[data-dbg]').forEach(btn=>btn.onclick=async()=>{
    const [target,method]=btn.dataset.dbg.split(':');const out=$('#dbg-out');
    out.innerHTML='<p class="cons-note">Llamando…</p>';btn.disabled=true;
    try{const r=await api('admin/consumption/test','POST',{target,method,month:$('#dbg-month').value,locationId:$('#dbg-sub').value||undefined,version:$('#dbg-version').value||undefined,limit:Number($('#dbg-limit').value)});
      const ok=r.status>=200&&r.status<300,a=r.analysis;
      out.innerHTML=`<div class="dbg-head"><span class="dbg-status ${ok?'ok':'bad'}">HTTP ${r.status||'sin respuesta'}</span><code>${esc(r.request.method)} ${esc(r.request.url)}</code><small>Versión ${esc(r.request.version)} · ${r.ms} ms</small></div>
       ${r.error?`<p class="cons-note warn">${esc(r.error)}</p>`:''}
       ${a?`<ul class="dbg-facts"><li>Filas: <b>${a.rows}</b> · con subcuenta: <b>${a.withLocation}</b> · con monto: <b>${a.withAmount}</b></li><li>Clasificadas: gasto <b>${a.classified.gasto}</b> · recarga <b>${a.classified.recarga}</b> · sin clasificar <b>${a.classified.unknown}</b></li><li>Campos de la respuesta: <code>${esc(a.topLevelKeys.join(', ')||'—')}</code></li><li>Campos de cada fila: <code>${esc(a.firstRowKeys.join(', ')||'—')}</code></li></ul>`:''}
       ${Object.keys(r.headers).length?`<details><summary>Cabeceras</summary><pre>${esc(Object.entries(r.headers).map(([k,v])=>k+': '+v).join('\n'))}</pre></details>`:''}
       <details open><summary>Respuesta</summary><button type="button" class="cons-copy">Copiar</button><pre class="dbg-body">${esc(r.body||'(vacía)')}</pre></details>`;
      out.querySelector('.cons-copy')?.addEventListener('click',()=>navigator.clipboard?.writeText(r.body||''));
    }catch(e){out.innerHTML=`<p class="cons-note warn">${esc(e.message)}</p>`}
    finally{btn.disabled=false}
  });
}
