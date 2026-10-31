import {workspace,friendly} from './workspace.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];
const number=value=>new Intl.NumberFormat('es-ES').format(Number(value)||0);
// La cuenta no publica moneda, así que la cifra va sin símbolo.
const money=value=>Number.isFinite(Number(value))?new Intl.NumberFormat('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value)):null;
const MONTHS=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const monthName=period=>{const [y,m]=String(period||'').split('-');return MONTHS[Number(m)-1]?`${MONTHS[Number(m)-1]} de ${y}`:period};

async function request(path,method='GET',csrf='',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

function tile(label,value,note){
  return `<div class="board-tile"><span>${esc(label)}</span><strong>${value===null||value===undefined?'—':esc(value)}</strong>${note?`<small>${esc(note)}</small>`:''}</div>`;
}

/**
 * De dónde sale la ganancia: ventas, lo que se va en publicidad y comisiones,
 * y lo que queda. Las barras se dibujan a escala sobre las ventas del mes.
 */
function waterfall(p){
  const sales=Number(p.sales)||0;
  if(sales<=0)return '<p class="board-empty">Todavía no hay nada cerrado este mes.</p>';
  const steps=[
    {label:'Ventas',value:sales,kind:'up'},
    {label:'Publicidad y gastos',value:p.ads===null?null:-p.ads,kind:'down'},
    {label:'Comisiones',value:-p.commissions,kind:'down'},
    {label:'Ganancia',value:p.profit,kind:'total'},
  ];
  const top=Math.max(sales,Math.abs(Number(p.profit)||0))||1;
  let running=0;
  return `<div class="waterfall" role="img" aria-label="De las ventas del mes a la ganancia">${steps.map(step=>{
    if(step.value===null)return `<div class="waterfall-step missing"><span class="waterfall-value">Falta el dato</span><i style="height:6px"></i><span class="waterfall-label">${esc(step.label)}</span></div>`;
    const value=Number(step.value);
    const height=Math.max(4,Math.round(Math.abs(value)/top*140));
    const base=step.kind==='down'?Math.round((top-running)/top*140):0;
    if(step.kind==='up')running=value;else if(step.kind==='down')running+=value;
    const offset=step.kind==='down'?Math.max(0,base-height):0;
    return `<div class="waterfall-step ${esc(step.kind)}"><span class="waterfall-value">${value<0?'−':''}${esc(money(Math.abs(value)))}</span><i style="height:${height}px;margin-bottom:${offset}px"></i><span class="waterfall-label">${esc(step.label)}</span></div>`;
  }).join('')}</div>`;
}

const STATES={lograda:['Meta lograda','ok'],cerca:['Cerca de la meta','near'],apoyo:['Necesita apoyo','behind'],'sin-meta':['Sin meta fijada','none']};

function teamTable(data){
  const rows=asList(data.team);
  if(!rows.length)return '<p class="board-empty">Todavía no hay equipo importado en esta cuenta.</p>';
  const word=data.settings?.closingWord||'cierres';
  const cell=value=>value===null||value===undefined?'—':esc(money(value));
  return `<div class="board-table-wrap"><table class="board-table"><thead><tr>
    <th scope="col">Asesora</th><th scope="col">${esc(word[0].toUpperCase()+word.slice(1))}</th><th scope="col">Meta</th><th scope="col">Vendido</th><th scope="col">Diferencia</th><th scope="col">Avance</th><th scope="col">Estado</th>
  </tr></thead><tbody>${rows.map(row=>{
    const [label,kind]=STATES[row.state]||STATES['sin-meta'];
    const width=Math.max(0,Math.min(100,Number(row.progress)||0));
    // Cada celda lleva su rótulo: en un teléfono la fila se lee como tarjeta.
    const heading=esc(word[0].toUpperCase()+word.slice(1));
    return `<tr><th scope="row">${esc(row.name)}</th><td data-label="${heading}">${number(row.closings)}</td><td data-label="Meta">${cell(row.goal)}</td><td data-label="Vendido">${cell(row.sold)}</td>
      <td data-label="Diferencia" class="${Number(row.difference)<0?'down':'up'}">${row.difference===null?'—':(Number(row.difference)>=0?'+':'−')+esc(money(Math.abs(row.difference)))}</td>
      <td data-label="Avance" class="board-progress"><span class="board-progress-line"><span class="board-bar ${esc(kind)}"><i style="width:${width}%"></i></span><b>${row.progress===null?'—':number(Math.round(row.progress))+' %'}</b></span></td>
      <td data-label="Estado"><span class="board-state ${esc(kind)}">${esc(label)}</span></td></tr>`;
  }).join('')}</tbody></table></div>`;
}

export async function renderDashboard(container){
  const instance=Symbol();container.agoraViewInstance=instance;
  const active=()=>container.agoraViewInstance===instance&&container.dataset.view==='dashboard';
  const connection=workspace.current();
  if(!connection){container.innerHTML='<p class="empty">Añade una cuenta desde Conexiones para ver el tablero.</p>';return}
  container.innerHTML='<p id="dashboard-feedback" class="live-feedback" role="status">Leyendo la cuenta…</p><div id="dashboard-body"></div>';
  const $=s=>container.querySelector(s);
  let csrf='',month=null,period=new Date().toISOString().slice(0,7);
  try{csrf=(await request('me')).csrf||''}catch{}

  async function load(){
    $('#dashboard-feedback').textContent='Leyendo la cuenta…';
    try{
      const zone=(()=>{try{return Intl.DateTimeFormat().resolvedOptions().timeZone||''}catch{return ''}})();
      const now=await request(`workspace/dashboard?connectionId=${encodeURIComponent(connection)}${zone?'&tz='+encodeURIComponent(zone):''}`);
      if(!active())return;
      paint(now,null);
      try{month=await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`)}
      catch(error){month={error:friendly(error)}}
      if(!active())return;
      paint(now,month);
    }catch(error){
      if(!active())return;
      $('#dashboard-feedback').textContent=friendly(error);
      $('#dashboard-body').innerHTML='';
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  function paint(data,month){
    const read=data.readAt?new Date(data.readAt).toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit'}):'';
    $('#dashboard-feedback').textContent=`${data.restricted?'Tus cifras en esta cuenta':'Cifras de la cuenta'}${read?` · leídas a las ${read}`:''}`;
    const unread=data.conversations?.unread??0;
    const tiles=[
      tile('Conversaciones',number(data.conversations?.total??0),'Abiertas en la cuenta'),
      tile('Sin leer',number(unread),unread?'Esperan respuesta':'Nada pendiente'),
      ...(data.contacts!==null&&data.contacts!==undefined?[tile('Contactos',number(data.contacts),'Personas en la cuenta')]:[]),
      ...(data.opportunities?[tile('Oportunidades abiertas',number(data.opportunities.count),`${money(data.opportunities.value)} en juego`)]:[]),
    ].join('');
    // Lo del día y lo de cada quien. Si falta un dato, el recuadro dice por qué.
    const leads=data.leads||null;const sales=month&&!month.error?month.sales:null;const mine=month&&!month.error?month.mine:null;
    const unlinked='Usa en Ágora el mismo correo que en la cuenta';
    const personal=[
      ...(leads?[tile('Leads de hoy',leads.today===null||leads.today===undefined?null:number(leads.today),leads.today===null||leads.today===undefined?'La cuenta no devolvió este dato':(data.restricted?'Nuevos a tu nombre':'Contactos nuevos desde las 00:00'))]:[]),
      ...(leads?[tile('Leads asignados a mí',leads.mine===null||leads.mine===undefined?null:number(leads.mine),leads.linked?(leads.mine===null||leads.mine===undefined?'La cuenta no devolvió este dato':'Contactos a tu nombre'):unlinked)]:[]),
      ...(sales&&!data.restricted?[tile('Ventas ganadas del mes',number(sales.closings??0),`${money(sales.total??0)} en total`)]:[]),
      ...(month&&!month.error?[tile('Mis ventas del mes',mine?number(mine.closings):null,mine?`${money(mine.sold)} ganados`:unlinked)]:[]),    ].join('');
    const stages=data.opportunities?asList(data.opportunities.stages):[];
    const stageList=stages.length
      ? `<div class="dashboard-stages">${stages.map(stage=>`<article class="board-stage"><h3>${esc(stage.name)}</h3><p>${number(stage.count)} oportunidad${stage.count===1?'':'es'}</p><strong>${esc(money(stage.value))}</strong></article>`).join('')}</div>`
      : '<p class="board-empty">Todavía no hay oportunidades abiertas en esta cuenta.</p>';
    $('#dashboard-body').innerHTML=`<section class="board-block">
        ${personal?`<div class="dashboard-tiles personal-tiles">${personal}</div>`:''}
        <div class="dashboard-tiles">${tiles}</div>
        <div class="section-label">EMBUDO POR ETAPA</div>${stageList}
        ${data.opportunities?.sampled?'<p class="board-empty">Se resumen las oportunidades abiertas más recientes.</p>':''}
      </section>
      <section class="board-block" id="board-month">${monthBlock(month)}</section>
`;
    bindMonth();
  }

  function monthBlock(month){
    if(!month)return '<div class="section-label">RENTABILIDAD DEL MES</div><p class="board-empty">Calculando el mes…</p>';
    if(month.error)return `<div class="section-label">RENTABILIDAD DEL MES</div><p class="board-empty">${esc(month.error)}</p>`;
    // Un resumen con otra forma no puede llevarse por delante el resto del tablero.
    if(!month.profitability||!month.settings||!month.goal)return '<div class="section-label">RENTABILIDAD DEL MES</div><p class="board-empty">El resumen del mes no llegó completo. Vuelve a entrar en un momento.</p>';
    const p=month.profitability,word=month.settings?.closingWord||'cierres';
    const missing=asList(month.missing);
    const NAMES={ads:'lo invertido en publicidad',fixedCosts:'los costos fijos',teamGoal:'la meta del equipo'};
    // De dónde sale la publicidad, o por qué la cuenta no la ha dado.
    const WHY={permission:'La clave de la cuenta no tiene permiso para leer los anuncios. Pídele a quien administra Ágora que lo añada.',
      not_connected:'La cuenta no tiene una cuenta publicitaria de Meta conectada.',
      no_spend:'La cuenta publicitaria no informa gasto para este mes.',
      unreadable:'La cuenta publicitaria respondió con un formato que no se pudo leer.'};
    const adsLine=p.adsFromAccount!==null&&p.adsFromAccount!==undefined
      ? `<p class="board-source">Publicidad: ${esc(money(p.adsFromAccount))} según la cuenta publicitaria de Meta conectada${p.adsOther?` + ${esc(money(p.adsOther))} de otros gastos indicados aparte`:''}.</p>`
      : (p.adsReason&&p.adsReason!=='restricted'?`<p class="board-source warn">${esc(WHY[p.adsReason]||'La cuenta no ha informado lo invertido en publicidad.')}${p.adsOther?` Se usan ${esc(money(p.adsOther))} indicados aparte.`:''}</p>`:'');
    const warn=missing.length
      ? `<p class="board-warning">Faltan por indicar ${esc(missing.map(k=>NAMES[k]||k).join(', '))}. ${month.canEdit?'Pulsa «Cifras del mes» para completarlos.':'Pídeselo a quien administra Ágora.'}</p>`
      : '';
    const picker=`<div class="board-head"><div><div class="section-label">RENTABILIDAD DE ${esc(monthName(month.period).toUpperCase())}</div>
        <p class="board-lead">${p.profit===null?'Indica lo invertido para ver la ganancia.':`Ganancia después de publicidad y comisiones<strong>${esc(money(p.profit))}</strong>${p.marginPerUnit!==null?`<small>De cada 1,00 vendido quedan ${esc(money(p.marginPerUnit))}.</small>`:''}`}</p></div>
      <div class="board-actions"><label class="board-month-pick">Mes<input type="month" id="board-period" value="${esc(month.period)}" max="${esc(new Date().toISOString().slice(0,7))}"></label>
      ${month.canEdit?'<button id="board-settings">Cifras del mes</button>':''}</div></div>`;
    const tiles=[
      tile('Ganancia real del mes',money(p.net),p.net===null?'Falta indicar los costos fijos':`Después de ${money(p.fixedCosts)} en costos fijos`),
      tile(`Costo por ${word.replace(/s$/,'')}`,money(p.costPerClosing),p.costPerClosing===null?'Falta lo invertido en publicidad':`${money(p.ads)} entre ${number(month.sales.closings)} ${word}`),
      tile('Ventas por cada 100 invertido',money(p.returnPerHundred),'Retorno del gasto publicitario'),
      tile('Costos de mercadeo y ventas',money(p.marketingCost),p.marketingCost===null?'Faltan datos':`Fijos ${money(p.fixedCosts??0)} · variables ${money(p.ads??0)}`),
      tile(`Comisiones del equipo (${number(month.settings.commissionRate)} %)`,money(p.commissions),`Sobre ${money(p.sales)} vendidos`),
    ].join('');
    const goal=month.goal;
    const progress=Math.max(0,Math.min(100,Number(goal.progress)||0));
    const counts=asList(month.team).reduce((acc,row)=>{acc[row.state]=(acc[row.state]||0)+1;return acc},{});
    return `${picker}${adsLine}${warn}
      <div class="board-split">${waterfall(p)}<div class="dashboard-tiles">${tiles}</div></div>
      <div class="section-label">DESEMPEÑO DEL EQUIPO DE VENTAS</div>
      <div class="board-goal">
        <div class="board-goal-main"><span>Avance del equipo frente a su meta</span><strong>${goal.progress===null?'—':number(Math.round(goal.progress))+' %'}</strong>
          <small>${goal.target===null?'Indica la meta del mes para ver el avance.':goal.missing>0?`Faltan ${esc(money(goal.missing))} para la meta de ${esc(money(goal.target))}.`:`Meta de ${esc(money(goal.target))} superada.`}</small></div>
        <div class="board-goal-counts">
          <span class="board-state ok">${number(counts.lograda||0)} con la meta lograda</span>
          <span class="board-state near">${number(counts.cerca||0)} cerca de la meta</span>
          <span class="board-state behind">${number(counts.apoyo||0)} necesitan apoyo</span>
        </div>
        <span class="board-bar ${goal.progress>=month.settings.green?'ok':goal.progress>=month.settings.amber?'near':'behind'}"><i style="width:${progress}%"></i></span>
      </div>
      ${teamTable(month)}
      <p class="board-note">Verde desde ${number(month.settings.green)} %, ámbar desde ${number(month.settings.amber)} %, roja por debajo.${month.sales.partial?' Se resumen los primeros cierres del mes.':''}${month.sales.counted!==null?` ${number(month.sales.counted)} ${esc(word)} en el mes.`:''}</p>`;
  }

  function bindMonth(){
    const pick=$('#board-period');
    if(pick)pick.onchange=async()=>{
      if(!/^\d{4}-\d{2}$/.test(pick.value))return;
      period=pick.value;
      $('#board-month').innerHTML=monthBlock(null);
      try{month=await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`)}
      catch(error){month={error:friendly(error)}}
      if(!active())return;
      $('#board-month').innerHTML=monthBlock(month);bindMonth();
    };
    const settings=$('#board-settings');
    if(settings)settings.onclick=()=>openSettings();
  }

  /** Las cifras que la cuenta no conoce las escribe el propietario, por mes. */
  function openSettings(){
    const dialog=document.querySelector('#action-dialog');
    const s=month.settings||{};
    const rows=asList(month.team).filter(row=>row.id).map(row=>
      `<label>${esc(row.name)}<input type="number" min="0" step="0.01" data-goal="${esc(row.id)}" value="${row.goal??''}" placeholder="Meta del mes"></label>`).join('');
    document.querySelector('#dialog-content').innerHTML=`<span class="eyebrow">${esc(monthName(month.period).toUpperCase())}</span>
      <h2 style="margin-top:12px">Cifras del mes</h2>
      <p class="dialog-copy">Lo que la cuenta no sabe: lo que invertiste, lo que cuesta mantener la operación y las metas. Se guarda sólo para este mes y esta subcuenta.</p>
      <form id="board-form" class="dialog-form">
        <p class="dialog-copy">Lo invertido en Meta lo informa la cuenta publicitaria conectada. Aquí sólo lo que no pasa por ella.</p>
        <label>Otros gastos de publicidad (fuera de Meta)<input type="number" min="0" step="0.01" name="otherAds" value="${s.otherAds??''}"></label>
        <label>Costos fijos del mes<input type="number" min="0" step="0.01" name="fixedCosts" value="${s.fixedCosts??''}"></label>
        <label>Comisión del equipo (%)<input type="number" min="0" max="100" step="0.1" name="commissionRate" value="${s.commissionRate??10}"></label>
        <label>Meta de ventas del equipo<input type="number" min="0" step="0.01" name="teamGoal" value="${s.teamGoal??''}"></label>
        <label>¿Cómo llamas a cada cierre?<input name="closingWord" maxlength="24" value="${esc(s.closingWord||'cierres')}" placeholder="estudiantes, clientes, pólizas…"></label>
        <div class="section-label">UMBRALES DEL SEMÁFORO</div>
        <label>Verde desde (%)<input type="number" min="0" max="100" step="1" name="green" value="${s.green??100}"></label>
        <label>Ámbar desde (%)<input type="number" min="0" max="100" step="1" name="amber" value="${s.amber??70}"></label>
        ${rows?`<div class="section-label">METAS POR ASESORA</div>${rows}`:''}
        <button class="primary">Guardar cifras</button><p id="board-form-status" role="status"></p>
      </form>`;
    dialog.showModal();
    const form=document.querySelector('#board-form');
    form.onsubmit=async event=>{
      event.preventDefault();
      const status=form.querySelector('#board-form-status'),button=form.querySelector('button.primary');
      button.disabled=true;status.textContent='Guardando…';
      const payload=Object.fromEntries(['otherAds','fixedCosts','commissionRate','teamGoal','closingWord','green','amber'].map(k=>[k,form.elements[k].value]));
      payload.goals=Object.fromEntries([...form.querySelectorAll('[data-goal]')].map(input=>[input.dataset.goal,input.value]));
      try{
        await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`,'POST',csrf,payload);
        month=await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`);
        if(!active())return;
        dialog.close();
        $('#board-month').innerHTML=monthBlock(month);bindMonth();
      }catch(error){status.textContent=friendly(error);button.disabled=false}
    };
  }

  container.agoraLiveDispose=()=>{container.agoraViewInstance=null};
  await load();
}
