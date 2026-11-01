import {workspace,friendly} from './workspace.js';
import {skeleton} from './skeleton.js';

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];
const number=value=>new Intl.NumberFormat('es-ES').format(Number(value)||0);
// La cuenta no publica moneda, así que la cifra va sin símbolo.
const money=value=>Number.isFinite(Number(value))?new Intl.NumberFormat('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value)):null;
const MONTHS=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const pad2=n=>String(n).padStart(2,'0');
const ymd=d=>`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
const addDays=(d,n)=>{const x=new Date(d);x.setDate(x.getDate()+n);return x};
const PRESETS=[['today','Hoy'],['yesterday','Ayer'],['week','7 días'],['d30','30 días'],['month','Este mes'],['prev','Mes anterior'],['monthpick','Otro mes'],['custom','Rango']];
/** Periodo elegido: días para los leads y mes para el resumen mensual (el resumen es siempre de un mes natural). */
export function periodOf(sel,now=new Date()){
  const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
  const cur=ymd(today).slice(0,7);
  const monthly=(y,m)=>{const last=new Date(y,m+1,0);return {from:ymd(new Date(y,m,1)),to:ymd(last<today?last:today),month:`${y}-${pad2(m+1)}`}};
  switch(sel?.kind){
    case 'yesterday':{const d=addDays(today,-1);return {from:ymd(d),to:ymd(d),month:cur,label:'ayer'}}
    case 'week':return {from:ymd(addDays(today,-6)),to:ymd(today),month:cur,label:'últimos 7 días'};
    case 'd30':return {from:ymd(addDays(today,-29)),to:ymd(today),month:cur,label:'últimos 30 días'};
    case 'month':return {...monthly(today.getFullYear(),today.getMonth()),label:'este mes'};
    case 'prev':{const p=new Date(today.getFullYear(),today.getMonth()-1,1);return {...monthly(p.getFullYear(),p.getMonth()),label:'mes anterior'}}
    case 'monthpick':{const m=/^(\d{4})-(\d{2})$/.exec(sel.month||'');if(m){const r=monthly(Number(m[1]),Number(m[2])-1);return {...r,label:monthNameShort(r.month)}}return {...monthly(today.getFullYear(),today.getMonth()),label:'este mes'}}
    case 'custom':{const ok=v=>/^\d{4}-\d{2}-\d{2}$/.test(v||'');if(ok(sel.from)&&ok(sel.to)&&sel.from<=sel.to)return {from:sel.from,to:sel.to,month:sel.to.slice(0,7)>cur?cur:sel.to.slice(0,7),label:`${dayLabel(sel.from)} – ${dayLabel(sel.to)}`};return {from:ymd(today),to:ymd(today),month:cur,label:'hoy'}}
    default:return {from:ymd(today),to:ymd(today),month:cur,label:'hoy'};
  }
}
const dayLabel=v=>{const [y,m,d]=v.split('-').map(Number);return `${d} ${MONTHS[m-1].slice(0,3)}${y!==new Date().getFullYear()?' '+y:''}`};
const monthNameShort=p=>{const [y,m]=String(p).split('-');return MONTHS[Number(m)-1]?`${MONTHS[Number(m)-1]} ${y}`:p};
const monthName=period=>{const [y,m]=String(period||'').split('-');return MONTHS[Number(m)-1]?`${MONTHS[Number(m)-1]} de ${y}`:period};

async function request(path,method='GET',csrf='',body=null){
  const r=await fetch('/api/'+path,{method,cache:'no-store',...(body!==null?{body:JSON.stringify(body)}:{}),headers:{'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})}});
  let data;try{data=await r.json()}catch{throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`),{status:r.status})}
  if(!r.ok)throw Object.assign(Error((data.error||'Error de conexión')+(data.retryAfter?` Espera ${data.retryAfter} segundos.`:'')),{status:r.status,code:data.code});
  return data;
}

/** La regla de comisión en palabras: «10 %» o «5,00 por cierre». */
function commissionText(rule,word='cierres'){
  if(!rule)return '10 %';
  const one=String(word).replace(/s$/,'');
  return rule.kind==='per_closing'?`${money(rule.amount)} por ${one}`:`${number(rule.amount)} %`;
}
/** De dónde sale cada cifra, a un toque, para quien se pregunte «¿y esto de dónde?». */
function origin(month,word){
  const one=String(word).replace(/s$/,'');
  const rule=month.commission||{kind:'percent',amount:10};
  const commission=rule.kind==='per_closing'?`el monto fijo por ${one} (${money(rule.amount)}) por el número de ${word}`:`el ${number(rule.amount)} % de las ventas`;
  const row=(name,text)=>`<li><b>${esc(name)}</b>${esc(text)}</li>`;
  return `<details class="board-origin"><summary>¿De dónde salen estas cifras?</summary><ul>
    ${row('Ventas y '+word+': ','lo ganado en el mes en tu cuenta, más lo que se marca como «¿Venta ganada?» desde Ágora. Cada venta cuenta en el mes en que se cierra.')}
    ${row('Publicidad: ','lo gastado en la cuenta publicitaria de Meta conectada durante el mes, más lo que escribas en «Cifras del mes» como otros gastos.')}
    ${row('Comisiones: ','calculadas con '+commission+'. La regla la fija el administrador en Conexiones y vale desde el mes en que se cambia.')}
    ${row('Ganancia: ','ventas menos publicidad y comisiones.')}
    ${row('Ganancia real del mes: ','la ganancia menos los costos fijos que escribes en «Cifras del mes».')}
    ${row('Costo por '+one+': ','publicidad entre el número de '+word+'.')}
    ${row('Ventas por cada 100 invertido: ','ventas entre publicidad, por cien.')}
    ${row('Vendedores: ','el equipo de tu cuenta. Quienes administran no aparecen aquí, pero lo que cierran sí cuenta en el total.')}
    ${row('Metas y costos fijos: ','los escribes tú en «Cifras del mes»; se guardan por subcuenta y por mes.')}
  </ul></details>`;
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
  if(!connection){container.innerHTML=`<p class="empty">${esc(workspace.noAccountText('el tablero'))}</p>`;return}
  let sel={kind:'today'};
  try{const saved=JSON.parse(localStorage.getItem('agora-board-period')||'null');if(saved&&PRESETS.some(p=>p[0]===saved.kind))sel=saved}catch{}
  container.innerHTML=`<div id="period-bar" class="period-bar"></div><p id="dashboard-feedback" class="updated-at" role="status" hidden></p><div id="dashboard-body">${skeleton('tiles',4)}</div>`;
  const $=s=>container.querySelector(s);
  let csrf='',month=null,period=periodOf(sel).month,range=periodOf(sel);
  const today=ymd(new Date());
  function paintBar(){
    const extra=sel.kind==='monthpick'?`<label class="period-field">Mes<input type="month" id="period-month" value="${esc(sel.month||today.slice(0,7))}" max="${today.slice(0,7)}"></label>`
      :sel.kind==='custom'?`<label class="period-field">Desde<input type="date" id="period-from" value="${esc(sel.from||today)}" max="${today}"></label><label class="period-field">Hasta<input type="date" id="period-to" value="${esc(sel.to||today)}" max="${today}"></label>`:'';
    $('#period-bar').innerHTML=`<div class="period-chips" role="group" aria-label="Periodo">${PRESETS.map(([k,l])=>`<button type="button" class="chip${sel.kind===k?' on':''}" data-period="${k}" aria-pressed="${sel.kind===k}">${l}</button>`).join('')}</div>${extra?`<div class="period-extra">${extra}</div>`:''}`;
    $('#period-bar').querySelectorAll('[data-period]').forEach(b=>b.onclick=()=>choose({kind:b.dataset.period,month:sel.month,from:sel.from,to:sel.to}));
    const m=$('#period-month');if(m)m.onchange=()=>{if(/^\d{4}-\d{2}$/.test(m.value))choose({kind:'monthpick',month:m.value})};
    const f=$('#period-from'),t=$('#period-to');
    const range=()=>{if(f.value&&t.value&&f.value<=t.value)choose({kind:'custom',from:f.value,to:t.value})};
    if(f)f.onchange=range;if(t)t.onchange=range;
  }
  function choose(next){
    sel=next;try{localStorage.setItem('agora-board-period',JSON.stringify(sel))}catch{}
    range=periodOf(sel);period=range.month;paintBar();
    // «Otro mes» y «Rango» esperan a que se elija la fecha; el resto carga de inmediato.
    if((sel.kind==='monthpick'&&!sel.month)||(sel.kind==='custom'&&!(sel.from&&sel.to)))return;
    $('#dashboard-body').innerHTML=skeleton('tiles',4);load();
  }
  paintBar();
  try{csrf=(await request('me')).csrf||''}catch{}

  async function load(){
    if(!$('#dashboard-body').children.length||$('#dashboard-body .sk-wrap'))$('#dashboard-body').innerHTML=skeleton('tiles',4);
    try{
      const zone=(()=>{try{return Intl.DateTimeFormat().resolvedOptions().timeZone||''}catch{return ''}})();
      const now=await request(`workspace/dashboard?connectionId=${encodeURIComponent(connection)}${zone?'&tz='+encodeURIComponent(zone):''}&from=${range.from}&to=${range.to}`);
      if(!active())return;
      paint(now,null);
      try{month=await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`)}
      catch(error){month={error:friendly(error)}}
      if(!active())return;
      paint(now,month);
    }catch(error){
      if(!active())return;
      const feedback=$('#dashboard-feedback');feedback.hidden=false;feedback.textContent=friendly(error);
      $('#dashboard-body').innerHTML='';
      if(error.status===401)window.dispatchEvent(new Event('agora:logout'));
    }
  }

  function paint(data,month){
    const read=data.readAt?new Date(data.readAt).toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit'}):'';
    const feedback=$('#dashboard-feedback');feedback.hidden=false;feedback.innerHTML=`<span>${data.restricted?'Tus cifras':'Cifras de la cuenta'}${read?` · actualizado a las ${read}`:''}</span>`;
    const unread=data.conversations?.unread??0;
    const tiles=[
      tile('Conversaciones',number(data.conversations?.total??0)),
      tile('Sin leer',number(unread)),
      ...(data.contacts!==null&&data.contacts!==undefined?[tile('Contactos',number(data.contacts))]:[]),
      ...(data.opportunities?[tile('Oportunidades abiertas',number(data.opportunities.count),`${money(data.opportunities.value)} en juego`)]:[]),
    ].join('');
    // Lo del día y lo de cada quien. Si falta un dato, el recuadro dice por qué.
    const leads=data.leads||null;const sales=month&&!month.error?month.sales:null;const mine=month&&!month.error?month.mine:null;
    const unlinked='Usa en Ágora el mismo correo que en la cuenta';
    const personal=[
      ...(leads?[tile(sel.kind==='today'?'Leads de hoy':sel.kind==='yesterday'?'Leads de ayer':'Leads nuevos',leads.today===null||leads.today===undefined?null:number(leads.today),leads.today===null||leads.today===undefined?'La cuenta no devolvió este dato':(['today','yesterday'].includes(sel.kind)?'':range.label))]:[]),
      ...(leads?[tile('Leads asignados a mí',leads.mine===null||leads.mine===undefined?null:number(leads.mine),leads.linked?(leads.mine===null||leads.mine===undefined?'La cuenta no devolvió este dato':''):unlinked)]:[]),
      ...(sales&&!data.restricted?[tile('Ventas ganadas del mes',number(sales.closings??0),`${money(sales.total??0)} en total`)]:[]),
      ...(month&&!month.error?[tile('Mis ventas del mes',mine?number(mine.closings):null,mine?`${money(mine.sold)} ganados`:unlinked)]:[]),    ].join('');
    const stages=data.opportunities?asList(data.opportunities.stages):[];
    const stageList=stages.length
      ? `<div class="dashboard-stages">${stages.map(stage=>`<article class="board-stage"><h3>${esc(stage.name)}</h3><p>${number(stage.count)} oportunidad${stage.count===1?'':'es'}</p><strong>${esc(money(stage.value))}</strong></article>`).join('')}</div>`
      : '<p class="board-empty">Todavía no hay oportunidades abiertas en esta cuenta.</p>';
    $('#dashboard-body').innerHTML=`<section class="board-block">
        ${personal?`<div class="dashboard-tiles personal-tiles">${personal}</div>`:''}
        <div class="dashboard-tiles">${tiles}</div>
        <div class="section-label">Embudo por etapa</div>${stageList}
        ${data.opportunities?.sampled?'<p class="board-empty">Se resumen las oportunidades abiertas más recientes.</p>':''}
      </section>
      <section class="board-block" id="board-month">${monthBlock(month)}</section>
`;
    bindMonth();
  }

  function monthBlock(month){
    if(!month)return '<div class="section-label">Rentabilidad del mes</div>'+skeleton('lines',3);
    if(month.error)return `<div class="section-label">Rentabilidad del mes</div><p class="board-empty">${esc(month.error)}</p>`;
    // Un resumen con otra forma no puede llevarse por delante el resto del tablero.
    if(!month.profitability||!month.settings||!month.goal)return '<div class="section-label">Rentabilidad del mes</div><p class="board-empty">El resumen del mes no llegó completo. Vuelve a entrar en un momento.</p>';
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
    const picker=`<div class="board-head"><div><div class="section-label">Rentabilidad de ${esc(monthName(month.period))}</div>
        <p class="board-lead">${p.profit===null?'Indica lo invertido para ver la ganancia.':`Ganancia después de publicidad y comisiones<strong>${esc(money(p.profit))}</strong>${p.marginPerUnit!==null?`<small>De cada 1,00 vendido quedan ${esc(money(p.marginPerUnit))}.</small>`:''}`}</p></div>
      <div class="board-actions"><label class="board-month-pick">Mes<input type="month" id="board-period" value="${esc(month.period)}" max="${esc(new Date().toISOString().slice(0,7))}"></label>
      ${month.canEdit?'<button id="board-settings">Cifras del mes</button>':''}</div></div>`;
    const tiles=[
      tile('Ganancia real del mes',money(p.net),p.net===null?'Falta indicar los costos fijos':`Después de ${money(p.fixedCosts)} en costos fijos`),
      tile(`Costo por ${word.replace(/s$/,'')}`,money(p.costPerClosing),p.costPerClosing===null?'Falta lo invertido en publicidad':`${money(p.ads)} entre ${number(month.sales.closings)} ${word}`),
      tile('Ventas por cada 100 invertido',money(p.returnPerHundred),''),
      tile('Costos de mercadeo y ventas',money(p.marketingCost),p.marketingCost===null?'Faltan datos':`Fijos ${money(p.fixedCosts??0)} · variables ${money(p.ads??0)}`),
      tile(`Comisiones del equipo (${commissionText(month.commission,word)})`,money(p.commissions),month.commission?.kind==='per_closing'?`${number(month.sales.closings)} ${word}`:`Sobre ${money(p.sales)} vendidos`),
    ].join('');
    const goal=month.goal;
    const progress=Math.max(0,Math.min(100,Number(goal.progress)||0));
    const counts=asList(month.team).reduce((acc,row)=>{acc[row.state]=(acc[row.state]||0)+1;return acc},{});
    return `${picker}${adsLine}${warn}
      <div class="board-split">${waterfall(p)}<div class="dashboard-tiles">${tiles}</div></div>
      ${origin(month,word)}
      <div class="section-label">Desempeño del equipo de ventas</div>
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
    if(pick)pick.onchange=()=>{if(/^\d{4}-\d{2}$/.test(pick.value))choose({kind:'monthpick',month:pick.value})};
    const settings=$('#board-settings');
    if(settings)settings.onclick=()=>openSettings();
  }

  /** Las cifras que la cuenta no conoce las escribe el propietario, por mes. */
  function openSettings(){
    const dialog=document.querySelector('#action-dialog');
    const s=month.settings||{};
    const rows=asList(month.team).filter(row=>row.id).map(row=>
      `<label>${esc(row.name)}<input type="number" min="0" step="0.01" data-goal="${esc(row.id)}" value="${row.goal??''}" placeholder="Meta del mes"></label>`).join('');
    document.querySelector('#dialog-content').innerHTML=`<span class="eyebrow">${esc(monthName(month.period))}</span>
      <h2 style="margin-top:12px">Cifras del mes</h2>
      <p class="dialog-copy">Lo que la cuenta no sabe: lo que invertiste, lo que cuesta mantener la operación y las metas. Se guarda sólo para este mes y esta subcuenta.</p>
      <form id="board-form" class="dialog-form">
        <p class="dialog-copy">Lo invertido en Meta lo informa la cuenta publicitaria conectada. Aquí sólo lo que no pasa por ella.</p>
        <label>Otros gastos de publicidad (fuera de Meta)<input type="number" min="0" step="0.01" name="otherAds" value="${s.otherAds??''}"></label>
        <label>Costos fijos del mes<input type="number" min="0" step="0.01" name="fixedCosts" value="${s.fixedCosts??''}"></label>
        <label>Meta de ventas del equipo<input type="number" min="0" step="0.01" name="teamGoal" value="${s.teamGoal??''}"></label>
        <label>¿Cómo llamas a cada cierre?<input name="closingWord" maxlength="24" value="${esc(s.closingWord||'cierres')}" placeholder="estudiantes, clientes, pólizas…"></label>
        <div class="section-label">Umbrales del semáforo</div>
        <label>Verde desde (%)<input type="number" min="0" max="100" step="1" name="green" value="${s.green??100}"></label>
        <label>Ámbar desde (%)<input type="number" min="0" max="100" step="1" name="amber" value="${s.amber??70}"></label>
        ${rows?`<div class="section-label">Metas por asesora</div>${rows}`:''}
        <button class="primary">Guardar cifras</button><p id="board-form-status" role="status"></p>
      </form>`;
    dialog.showModal();
    const form=document.querySelector('#board-form');
    form.onsubmit=async event=>{
      event.preventDefault();
      const status=form.querySelector('#board-form-status'),button=form.querySelector('button.primary');
      button.disabled=true;status.textContent='Guardando…';
      const payload=Object.fromEntries(['otherAds','fixedCosts','teamGoal','closingWord','green','amber'].map(k=>[k,form.elements[k].value]));
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
