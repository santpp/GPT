import {workspace,friendly} from './workspace.js';
import {skeleton} from './skeleton.js';
import {number,money as fmtMoney} from './format.js';
import {wonAlertState} from './won-alert.js';
const money=value=>fmtMoney(value);

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const asList=value=>Array.isArray(value)?value:[];
// La cuenta no publica moneda, así que la cifra va sin símbolo.
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
const cap=t=>t.charAt(0).toUpperCase()+t.slice(1);
/** Opciones de mes en español («Noviembre 2026»), del actual hacia atrás; incluye el elegido aunque sea más antiguo. */
const monthOptions=(selected,count=24)=>{
  const now=new Date(),seen=[];
  for(let i=0;i<count;i++){const d=new Date(now.getFullYear(),now.getMonth()-i,1);seen.push(`${d.getFullYear()}-${pad2(d.getMonth()+1)}`)}
  if(selected&&/^\d{4}-\d{2}$/.test(selected)&&!seen.includes(selected))seen.push(selected);
  return seen.map(v=>{const [y,m]=v.split('-');return `<option value="${v}"${v===selected?' selected':''}>${cap(MONTHS[Number(m)-1])} ${y}</option>`}).join('');
};
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
  return `<div class="board-tile"><span>${esc(label)}</span><strong>${value===null||value===undefined?'—':esc(value)}</strong>${note?`<small>${typeof note==='object'?note.html:esc(note)}</small>`:''}</div>`;
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
  const ICON={cal:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>',
    down:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'};
  const QUICK=[['today','Hoy'],['yesterday','Ayer'],['week','Últimos 7 días'],['d30','Últimos 30 días'],['month','Este mes'],['prev','Mes anterior']];
  const nowLabel=()=>{const r=periodOf(sel);
    if(sel.kind==='today')return `Hoy · ${dayLabel(r.from)}`;
    if(sel.kind==='yesterday')return `Ayer · ${dayLabel(r.from)}`;
    if(sel.kind==='week')return 'Últimos 7 días';if(sel.kind==='d30')return 'Últimos 30 días';
    if(sel.kind==='month'||sel.kind==='prev'||sel.kind==='monthpick')return r.month?monthName(r.month):r.label;
    return r.label};
  let menuOpen=false;
  const closeMenu=()=>{if(!menuOpen)return;menuOpen=false;const m=$('#period-menu'),b=$('#period-btn');if(m){m.hidden=true;b?.setAttribute('aria-expanded','false')}};
  function paintBar(){
    const bar=$('#period-bar');
    bar.innerHTML=`<div class="period"><button type="button" id="period-btn" class="period-btn" aria-haspopup="true" aria-expanded="${menuOpen}">${ICON.cal}<span>${esc(nowLabel())}</span>${ICON.down}</button>
     <div id="period-menu" class="period-menu" role="menu"${menuOpen?'':' hidden'}>
      <div class="period-list">${QUICK.map(([k,l])=>`<button type="button" role="menuitemradio" aria-checked="${sel.kind===k}" class="${sel.kind===k?'on':''}" data-period="${k}">${l}</button>`).join('')}</div>
      <div class="period-custom">
       <label>Otro mes<select id="period-month"><option value="">Elegir mes…</option>${monthOptions(sel.kind==='monthpick'?sel.month:'')}</select></label>
       <div class="period-range"><label>Desde<input type="date" id="period-from" value="${esc(sel.kind==='custom'?sel.from:'')}" max="${today}"></label><label>Hasta<input type="date" id="period-to" value="${esc(sel.kind==='custom'?sel.to:'')}" max="${today}"></label></div>
       <button type="button" id="period-apply" class="primary" disabled>Aplicar rango</button>
      </div></div></div>`;
    $('#period-btn').onclick=e=>{e.stopPropagation();menuOpen=!menuOpen;$('#period-menu').hidden=!menuOpen;$('#period-btn').setAttribute('aria-expanded',String(menuOpen))};
    bar.querySelectorAll('[data-period]').forEach(b=>b.onclick=()=>{menuOpen=false;choose({kind:b.dataset.period})});
    const m=$('#period-month'),f=$('#period-from'),t=$('#period-to'),apply=$('#period-apply');
    m.onchange=()=>{if(/^\d{4}-\d{2}$/.test(m.value)){menuOpen=false;choose({kind:'monthpick',month:m.value})}};
    const ready=()=>{apply.disabled=!(f.value&&t.value&&f.value<=t.value)};
    // Un periodo se elige o con los botones o con el mes/rango, nunca los dos a la vez: al tocar uno, el otro se apaga.
    const clearPresets=()=>bar.querySelectorAll('.period-list button').forEach(b=>{b.classList.remove('on');b.setAttribute('aria-checked','false')});
    m.oninput=()=>{f.value='';t.value='';ready();clearPresets()};
    f.oninput=t.oninput=()=>{m.value='';ready();clearPresets()};
    f.onchange=t.onchange=ready;ready();
    apply.onclick=()=>{if(!apply.disabled){menuOpen=false;choose({kind:'custom',from:f.value,to:t.value})}};
  }
  // Se cierra al pulsar fuera o con Escape.
  const outside=e=>{if(menuOpen&&!e.target.closest('.period'))closeMenu()};
  document.addEventListener('click',outside);
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menuOpen){closeMenu();$('#period-btn')?.focus()}});
  const stopOutside=new MutationObserver(()=>{if(!container.isConnected||container.dataset.view!=='dashboard'){document.removeEventListener('click',outside);stopOutside.disconnect()}});
  stopOutside.observe(container,{attributes:true,attributeFilter:['data-view']});
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
      ...(data.conversations?.waiting!==undefined?[tile('Esperando respuesta',number(data.conversations.waiting),{html:'<button type="button" class="link-button" data-go-waiting>Ver la lista</button>'})]:[]),
      ...(data.contacts!==null&&data.contacts!==undefined?[tile('Contactos',number(data.contacts))]:[]),
      ...(data.opportunities?[tile('Oportunidades abiertas',number(data.opportunities.count),`${money(data.opportunities.value)} en juego`)]:[]),
    ].join('');
    // Lo del día y lo de cada quien. Si falta un dato, el recuadro dice por qué.
    const leads=data.leads||null;const sales=month&&!month.error?month.sales:null;const mine=month&&!month.error?month.mine:null;
    // Quien administra Ágora elige quién es entre los usuarios de la subcuenta; los demás se reconocen por su correo.
    const isOwner=workspace.me?.user?.role==='administrator'&&workspace.me?.user?.identity_type==='native';
    const unlinked=isOwner?{html:'<button type="button" class="link-button" data-self>Elegir quién soy</button>'}:'Usa en Ágora el mismo correo que en la cuenta';
    const personal=[
      ...(leads?[tile(sel.kind==='today'?'Leads de hoy':sel.kind==='yesterday'?'Leads de ayer':'Leads nuevos',leads.today===null||leads.today===undefined?null:number(leads.today),leads.today===null||leads.today===undefined?'La cuenta no devolvió este dato':(['today','yesterday'].includes(sel.kind)?'':range.label))]:[]),
      ...(leads?[tile(sel.kind==='today'?'Mis leads de hoy':sel.kind==='yesterday'?'Mis leads de ayer':'Mis leads nuevos',leads.mine===null||leads.mine===undefined?null:number(leads.mine),leads.linked?(leads.mine===null||leads.mine===undefined?'La cuenta no devolvió este dato':(['today','yesterday'].includes(sel.kind)?'Asignados a ti':'Asignados a ti · '+range.label)):unlinked)]:[]),
      ...(sales&&!data.restricted?[tile('Ventas ganadas del mes',number(sales.closings??0),`${money(sales.total??0)} en total`)]:[]),
      ...(month&&!month.error?[tile('Mis ventas del mes',mine?number(mine.closings):null,mine?`${money(mine.sold)} ganados`:unlinked)]:[]),    ].join('');
    const stages=data.opportunities?asList(data.opportunities.stages):[];
    const stageList=stages.length
      ? `<div class="dashboard-stages">${stages.map(stage=>`<article class="board-stage"><h3>${esc(stage.name)}</h3><p>${number(stage.count)} oportunidad${stage.count===1?'':'es'}</p><strong>${esc(money(stage.value))}</strong></article>`).join('')}</div>`
      : '<p class="board-empty">Todavía no hay oportunidades abiertas en esta cuenta.</p>';
    $('#dashboard-body').innerHTML=`<section class="board-block">
        ${personal?`<div class="dashboard-tiles personal-tiles">${personal}</div>`:''}
        <div class="dashboard-tiles">${tiles}</div>
        <section class="won-team" id="won-team" hidden></section>
        <div class="section-label">Embudo por etapa</div>${stageList}
        ${data.opportunities?.sampled?'<p class="board-empty">Se resumen las oportunidades abiertas más recientes.</p>':''}
      </section>
      <section class="board-block" id="board-month">${monthBlock(month)}</section>
`;
    paintWonTeam();
    bindMonth();
    container.querySelectorAll('[data-self]').forEach(b=>b.onclick=selfDialog);
    container.querySelectorAll('[data-go-waiting]').forEach(b=>b.onclick=()=>window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'inbox',filter:'waiting'}})));
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
      <div class="board-actions"><label class="board-month-pick">Mes<select id="board-period">${monthOptions(month.period)}</select></label>
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

  // Para quien ve todo: cuántas ventas ganadas siguen sin monto y de quién son. Sale de la misma consulta de la alerta.
  function paintWonTeam(){
    const slot=container.querySelector('#won-team');if(!slot)return;
    const team=wonAlertState()?.team;
    if(!team||!team.missing){slot.hidden=true;slot.innerHTML='';return}
    slot.hidden=false;
    slot.innerHTML=`<div class="section-label">Ventas ganadas sin monto</div>
      <div class="won-team-head"><strong>${number(team.missing)}</strong><span>${team.missing===1?'venta sin monto este mes':'ventas sin monto este mes'}</span></div>
      <ul class="won-by">${team.bySeller.map(b=>`<li><span>${esc(b.name)}</span><b>${number(b.missing)}</b></li>`).join('')}</ul>
      <button type="button" class="link-button" data-go-won>Ver y completar en Ganadas</button>`;
    slot.querySelector('[data-go-won]').onclick=()=>{workspace.pendingOppTab='won';window.dispatchEvent(new CustomEvent('agora:goto',{detail:{view:'board'}}))};
  }
  const onWon=()=>{if(!container.isConnected||container.dataset.view!=='dashboard'){window.removeEventListener('agora:won-alert',onWon);return}paintWonTeam()};
  window.addEventListener('agora:won-alert',onWon);
  async function selfDialog(){
    const dialog=document.querySelector('#action-dialog'),content=document.querySelector('#dialog-content');
    content.innerHTML='<h2>¿Quién eres en esta subcuenta?</h2><p class="dialog-copy">Leyendo los usuarios…</p>';
    if(!dialog.open)dialog.showModal();
    try{
      const data=await request(`workspace/self?connectionId=${encodeURIComponent(connection)}`);
      const rows=Array.isArray(data.options)?data.options:[];
      content.innerHTML=`<h2>¿Quién eres en esta subcuenta?</h2>
       <p class="dialog-copy">Con esto, «Mis leads» y «Mis ventas del mes» cuentan lo que tiene asignado ese usuario. Se guarda sólo para ti en esta subcuenta.</p>
       <form id="self-form" class="dialog-form"><label>Soy<select name="who"><option value="">Ninguno (no mostrar mis cifras)</option>${rows.map(r=>`<option value="${esc(r.id)}"${r.id===(data.linked||data.detected)?' selected':''}>${esc(r.name)}${r.email?` · ${esc(r.email)}`:''}</option>`).join('')}</select></label>
       <p id="self-result" role="status" class="dialog-copy"></p><div class="dialog-actions"><button class="primary">Guardar</button><button type="button" class="secondary" data-close-dialog>Cerrar</button></div></form>`;
      content.querySelector('[data-close-dialog]').onclick=()=>dialog.close();
      content.querySelector('#self-form').onsubmit=async e=>{
        e.preventDefault();const result=content.querySelector('#self-result');result.textContent='Guardando…';
        try{await request(`workspace/self?connectionId=${encodeURIComponent(connection)}`,'PUT',csrf,{externalUserId:e.target.elements.who.value||null});dialog.close();$('#dashboard-body').innerHTML=skeleton('tiles',4);load()}
        catch(error){result.textContent=friendly(error)}
      };
    }catch(error){content.innerHTML=`<h2>¿Quién eres en esta subcuenta?</h2><p class="dialog-copy">${esc(friendly(error))}</p>`}
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
