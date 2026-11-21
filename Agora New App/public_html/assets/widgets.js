/* Agora New App 2.15.0–2.16.0 · Widgets del equipo: cifras y gráficos por módulo, editor de tableros, editor de fórmulas,
   detalle de cada cifra con descarga (2.16.0) y «quién ve» cada tablero (2.16.0). Los gráficos van al final del módulo.
   Vive aparte de agora-app.js (decisión del usuario); usa lo que la app expone en window.AgoraHost.
   Solo administración (Administración, Supervisión y dueños) edita; el resto ve los valores. El servidor lo exige. */
(() => {
  'use strict';
  const H = () => window.AgoraHost;
  const MODULOS = ['centro', 'inicio', 'comercial', 'oportunidades', 'tareas', 'equipo', 'publicidad', 'ventas', 'facturacion', 'costos', 'catalogo', 'tendencias', 'calidad', 'facturas', 'redes', 'productos'];
  const GRAF = ['linea', 'barras', 'area', 'dona', 'tabla'], SERIE = ['linea', 'barras', 'area'];
  const TIPOS = [['cifra', 'Cifra'], ['linea', 'Gráfico de línea'], ['barras', 'Gráfico de barras'], ['area', 'Gráfico de área'], ['dona', 'Dona (partes de un total)'], ['tabla', 'Tabla de los primeros']];
  /* Paleta categórica en orden fijo (validada para daltonismo, claro y oscuro). Más de 5 partes se juntan en «Otros». */
  const PAL = ['#AD103C', '#3D5AA8', '#B8860F', '#7A4FA8', '#C25478'], OTROS = '#8A8A93';
  const esGraf = w => GRAF.includes(w.tipo);
  const st = {cat: null, catP: null, tab: {}, vals: {}, gen: 0};
  const esc = s => H().esc(String(s ?? ''));
  const ic = n => H().icon(n);
  const api = (p, o) => H().api(p, o);
  const enc = encodeURIComponent;
  const NF = d => new Intl.NumberFormat('es-VE', {minimumFractionDigits: d, maximumFractionDigits: d});
  const SIM = {USD: 'US$', EUR: '€', VES: 'Bs.'};
  const mes = (p, n) => { const [y, m] = p.split('-').map(Number); const t = y * 12 + m - 1 + n; return `${Math.floor(t / 12)}-${String(t % 12 + 1).padStart(2, '0')}`; };
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const nombreMes = p => { const [y, m] = p.split('-').map(Number); return `${MESES[m - 1]} de ${y}`; };
  const hoy = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
  const uid = () => Math.random().toString(36).slice(2, 10).padEnd(8, '0');

  /* ---------- formato de valores ---------- */
  function fmt(v, u, dec = 2) {
    if (v === null || v === undefined) return '—';
    if (u === 'dinero') return `${SIM[H().moneda()] || H().moneda()} ${NF(2).format(v)}`;
    if (u === 'porcentaje') return `${NF(Math.min(dec, 2)).format(v)} %`;
    if (u === 'fecha') { const d = new Date(Math.round(v) * 86400000 + 43200000); return `${String(d.getUTCDate()).padStart(2, '0')}/${String(d.getUTCMonth() + 1).padStart(2, '0')}/${d.getUTCFullYear()}`; }
    if (u === 'bool') return v ? 'Sí' : 'No';
    return NF(Number.isInteger(v) ? 0 : dec).format(v);
  }
  async function catalogo(fuerza) {
    if (st.cat && !fuerza) return st.cat;
    if (!st.catP || fuerza) st.catP = api('workspace/metricas/catalogo').then(c => (st.cat = c)).finally(() => { st.catP = null; });
    return st.catP;
  }
  const nombreRef = (w, cat) => w.fuente === 'formula' ? (cat?.formulas || []).find(f => f.id === +w.ref)?.nombre || 'Fórmula borrada' : (cat?.metricas || []).find(m => m.id === w.ref)?.nombre || w.ref;
  const clave = w => w.fuente === 'formula' ? `f:${w.ref}` : w.ref;
  const periodoDe = w => w.periodo === 'actual' ? H().mesActual() : w.periodo === 'anterior' ? mes(H().mesActual(), -1) : /^\d{4}-\d{2}$/.test(w.periodo) ? w.periodo : H().periodo();
  const compDe = (w, p) => w.comparar === 'mes_anterior' ? mes(p, -1) : w.comparar === 'ano_pasado' ? mes(p, -12) : null;

  /* ---------- tablero del módulo ---------- */
  async function valores(widgets) {
    const porP = new Map();
    const pedir = (p, k) => { if (!porP.has(p)) porP.set(p, new Set()); porP.get(p).add(k); };
    widgets.forEach(w => { const p = periodoDe(w); pedir(p, clave(w)); const c = compDe(w, p); if (c) pedir(c, clave(w)); });
    const out = {};
    await Promise.all([...porP].map(async ([p, set]) => {
      const ids = [...set];
      for (let i = 0; i < ids.length; i += 40) {
        try { const r = await api(`workspace/metricas?periodo=${p}&hoy=${hoy()}${H().zona()}&ids=${enc(ids.slice(i, i + 40).join(','))}`); Object.entries(r.valores || {}).forEach(([k, v]) => { out[p + '|' + k] = v; }); }
        catch (e) { ids.slice(i, i + 40).forEach(k => { out[p + '|' + k] = {valor: null, unidad: 'numero', motivo: e.message}; }); }
      }
    }));
    return out;
  }
  function semaforo(w, v) {
    const s = w.semaforo; if (!s?.activo || v === null || v === undefined) return '';
    const bien = s.mejor === 'menos' ? v <= s.verde : v >= s.verde, cerca = s.mejor === 'menos' ? v <= s.ambar : v >= s.ambar;
    return bien ? '<span class="sc sc-ok wg-sem">En meta</span>' : cerca ? '<span class="sc sc-warn wg-sem">Cerca</span>' : '<span class="sc sc-bad wg-sem">Bajo</span>';
  }
  function celda(w, vals, cat, edita) {
    const p = periodoDe(w), r = vals[p + '|' + clave(w)] || {valor: null, motivo: 'Cargando…'};
    const u = w.formato && w.formato !== 'auto' ? w.formato : r.unidad;
    let comp = '';
    const c = compDe(w, p);
    if (c) {
      const b = vals[c + '|' + clave(w)];
      if (r.valor === null || !b || b.valor === null || b.valor === 0) comp = `<span class="ag-resumen-d">Sin dato para comparar con ${w.comparar === 'ano_pasado' ? 'el año pasado' : 'el mes anterior'}</span>`;
      else { const d = (r.valor - b.valor) / Math.abs(b.valor) * 100; comp = `<span class="ag-resumen-d wg-comp ${d >= 0 ? 'is-up' : 'is-down'}">${d >= 0 ? '▲' : '▼'} ${NF(1).format(Math.abs(d))} % frente a ${w.comparar === 'ano_pasado' ? nombreMes(c) : 'el mes anterior'}</span>`; }
    }
    const motivo = r.valor === null && r.motivo ? `<span class="ag-resumen-d wg-motivo">${esc(r.motivo)}</span>` : '';
    const per = w.periodo !== 'modulo' ? `<span class="ag-resumen-d">${esc(nombreMes(p))}</span>` : '';
    const ed = edita ? `<span class="cel-acc"><button class="cel-ed" type="button" data-wg-ed="${esc(w.id)}" title="Editar ${esc(w.titulo || nombreRef(w, cat))}">Editar</button></span>` : '';
    return `<div class="ag-resumen cel wg-cel ${w.tamano === 'ancho' ? 'wg-ancho' : ''}" data-wg-id="${esc(w.id)}"><span class="ag-resumen-t">${esc(w.titulo || nombreRef(w, cat))}</span>${w.nota ? `<span class="ag-resumen-s">${esc(w.nota)}</span>` : ''}<b class="ag-resumen-v">${esc(fmt(r.valor, u, w.decimales ?? 2))}</b>${semaforo(w, r.valor)}${comp}${per}${motivo}${ed}</div>`;
  }
  function rellenar(grid) {
    const cols = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
    const cel = [...grid.children]; cel.forEach(c => c.style.removeProperty('grid-column'));
    let usados = 0; cel.forEach(c => { const a = Math.min(cols, c.classList.contains('wg-ancho') ? 2 : 1); if (usados % cols + a > cols) usados += cols - usados % cols; c.style.setProperty('grid-column', `span ${a}`); usados += a; });
    const resto = usados % cols; if (resto && cel.length) { const u = cel[cel.length - 1]; const a = parseInt(u.style.gridColumn.replace('span ', '')) || 1; u.style.setProperty('grid-column', `span ${a + cols - resto}`); }
  }
  async function pintar(sec, mod) {
    const gen = ++st.gen, edita = H().puedeEditar(), gsec = sec.ownerDocument.querySelector(`section.wg-g[data-wg-mod="${mod}"]`);
    let t;
    try { t = await api(`workspace/tableros?modulo=${enc(mod)}`); } catch { sec.remove(); gsec?.remove(); return; }
    if (gen !== st.gen || !sec.isConnected) return;
    st.tab[mod] = t;
    const cifras = t.widgets.filter(w => !esGraf(w)), grafs = t.widgets.filter(esGraf);
    const boton = edita ? `<button class="btn" type="button" data-wg-abrir>${ic('edit')}${t.widgets.length ? 'Editar widgets' : 'Agregar widgets'}</button>` : '';
    const soloAdm = t.visible === 'administracion' ? '<span class="sc wg-priv">Solo la administración</span>' : '';
    sec.hidden = !cifras.length && !(edita && !grafs.length);
    const cab = `<div class="wg-h"><h2 class="t-sec">Widgets del equipo${soloAdm}</h2>${boton}</div>`;
    const cat = t.widgets.length ? await catalogo().catch(() => null) : null;
    if (gen !== st.gen || !sec.isConnected) return;
    if (!t.widgets.length) sec.innerHTML = cab + '<p class="wg-vacio">Arma cifras y gráficos propios para este módulo: elige una cifra del sistema o crea una fórmula con tus datos.</p>';
    else if (cifras.length) sec.innerHTML = cab + `<div class="ag-resumen-grid wg-grid" data-wg>${cifras.map(w => celda(w, {}, cat, edita)).join('')}</div>`;
    else sec.innerHTML = '';
    if (gsec) {
      gsec.hidden = !grafs.length;
      gsec.innerHTML = grafs.length ? `<div class="wg-h"><h2 class="t-sec">Gráficos del equipo${soloAdm}</h2>${cifras.length ? (edita ? `<button class="btn" type="button" data-wg-abrir>${ic('edit')}Editar gráficos</button>` : '') : boton}</div><div class="wg-ggrid" data-wg>${grafs.map(w => tarjetaGraf(w, cat, edita)).join('')}</div>` : '';
      enlazar(gsec, mod, t, cat); grafs.forEach(w => cargarGraf(gsec.querySelector(`[data-wg-id="${w.id}"]`), w, cat));
    }
    enlazar(sec, mod, t, cat); const g0 = sec.querySelector('[data-wg]'); if (g0) rellenar(g0);
    if (!cifras.length) return;
    const vals = await valores(cifras);
    if (gen !== st.gen || !sec.isConnected) return;
    st.vals[mod] = vals;
    const grid = sec.querySelector('[data-wg]');
    grid.innerHTML = cifras.map(w => celda(w, vals, cat, edita)).join('');
    enlazar(sec, mod, t, cat); rellenar(grid);
  }
  function enlazar(sec, mod, t, cat) {
    sec.querySelectorAll('[data-wg-abrir]').forEach(b => b.onclick = () => editorTablero(mod));
    sec.querySelectorAll('[data-wg-ed]').forEach(b => b.onclick = e => { e.stopPropagation(); editorTablero(mod, b.dataset.wgEd); });
    sec.querySelectorAll('.wg-cel[data-wg-id]').forEach(c => { const w = t?.widgets.find(x => x.id === c.dataset.wgId); if (!w) return;
      c.setAttribute('role', 'button'); c.tabIndex = 0; c.title = 'Ver el detalle';
      const abrir = e => { if (e.target.closest('.cel-acc')) return; detalle(w, cat); };
      c.onclick = abrir; c.onkeydown = e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(e); } }; });
    sec.querySelectorAll('[data-wg-det]').forEach(b => b.onclick = () => { const w = t?.widgets.find(x => x.id === b.dataset.wgDet); if (w) detalle(w, cat); });
  }
  let vigia = null;
  function montar(app) {
    const mod = H().modulo();
    vigia?.disconnect(); vigia = null;
    if (!MODULOS.includes(mod)) return;
    const ancla = () => { const c = [...app.querySelectorAll('.cel')].find(x => !x.closest('section.wg, .modal, .scrim')); return c?.parentElement || app.querySelector('.kpi-inicio, .mod-h'); };
    const fondo = () => app.querySelector('.view-enter') || app;
    const poner = () => {
      const a = ancla(); let sec = app.querySelector('section.wg'), gsec = app.querySelector('section.wg-g');
      // los gráficos van al final del módulo (decisión del usuario)
      if (!gsec && a) { gsec = document.createElement('section'); gsec.className = 'wg-g'; gsec.hidden = true; gsec.dataset.wgMod = mod; fondo().append(gsec); }
      else if (gsec && gsec !== fondo().lastElementChild) fondo().append(gsec);
      if (sec) { if (a && a.nextElementSibling !== sec) a.after(sec); return true; }
      if (!a) return false;
      sec = document.createElement('section'); sec.className = 'wg'; sec.hidden = true; sec.dataset.wgMod = mod;
      a.after(sec); pintar(sec, mod); return true;
    };
    poner();
    let t = 0;
    vigia = new MutationObserver(() => { clearTimeout(t); t = setTimeout(() => { if (H().modulo() === mod) poner(); }, 200); });
    vigia.observe(app, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-cel']});
  }
  const repintar = mod => { const sec = document.querySelector(`section.wg[data-wg-mod="${mod}"]`); if (sec) pintar(sec, mod); };

  /* ---------- gráficos (2.16.0 · fase 4): línea, barras, área, dona y tabla de los primeros ---------- */
  const MCORTO = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const mesCorto = p => { const [y, m] = p.split('-').map(Number); return `${MCORTO[m - 1]} ${String(y).slice(2)}`; };
  const CORTO = new Intl.NumberFormat('es-VE', {notation: 'compact', maximumFractionDigits: 1});
  const fmtEje = (v, u) => u === 'dinero' ? `${SIM[H().moneda()] || ''} ${CORTO.format(v)}`.trim() : u === 'porcentaje' ? `${CORTO.format(v)} %` : CORTO.format(v);
  const refsDe = w => [{fuente: w.fuente, ref: w.ref}, ...(w.mas || [])];
  function tarjetaGraf(w, cat, edita) {
    const ed = edita ? `<span class="cel-acc"><button class="cel-ed" type="button" data-wg-ed="${esc(w.id)}" title="Editar ${esc(w.titulo || nombreRef(w, cat))}">Editar</button></span>` : '';
    return `<figure class="cel wg-graf ${w.tamano === 'chico' ? '' : 'wg-ancho'}" data-wg-id="${esc(w.id)}"><figcaption><span class="ag-resumen-t">${esc(w.titulo || nombreRef(w, cat))}</span>${w.nota ? `<span class="ag-resumen-s">${esc(w.nota)}</span>` : ''}</figcaption>
      <div class="wg-lienzo" aria-busy="true"><p class="wg-motivo">Cargando…</p></div><button class="wg-det-btn" type="button" data-wg-det="${esc(w.id)}">Ver detalle y descargar</button>${ed}</figure>`;
  }
  async function cargarGraf(fig, w, cat) {
    if (!fig) return;
    const caja = fig.querySelector('.wg-lienzo'), ids = refsDe(w).map(clave), p = periodoDe(w);
    const nombres = refsDe(w).map(r => nombreRef(r, cat));
    try {
      let datos;
      if (SERIE.includes(w.tipo)) {
        const r = await api(`workspace/metricas/serie?hasta=${p}&meses=${w.meses || 6}&hoy=${hoy()}${H().zona()}&ids=${enc(ids.join(','))}`);
        datos = {tipo: w.tipo, meses: r.meses, series: ids.map((id, i) => ({nombre: nombres[i], puntos: r.series[id] || []}))};
      } else {
        const r = await api(`workspace/metricas?periodo=${p}&hoy=${hoy()}${H().zona()}&ids=${enc(ids.join(','))}`);
        datos = {tipo: w.tipo, periodo: p, partes: ids.map((id, i) => ({nombre: nombres[i], ...(r.valores[id] || {valor: null, unidad: 'numero', motivo: ''})}))};
      }
      fig._datos = datos; fig._w = w; dibujar(fig);
    } catch (e) { caja.innerHTML = `<p class="wg-motivo">${esc(e.message)}</p>`; }
    caja.removeAttribute('aria-busy');
  }
  function dibujar(fig) {
    const d = fig._datos, w = fig._w, caja = fig.querySelector('.wg-lienzo'); if (!d || !caja) return;
    if (SERIE.includes(d.tipo) && !fig._ro && window.ResizeObserver) { let ultimo = caja.clientWidth; fig._ro = new ResizeObserver(() => { const a = caja.clientWidth; if (Math.abs(a - ultimo) > 8) { ultimo = a; dibujar(fig); } }); fig._ro.observe(caja); }
    const fmtU = u => w.formato && w.formato !== 'auto' ? w.formato : u;
    if (SERIE.includes(d.tipo)) caja.innerHTML = svgSerie(d, caja.clientWidth || 600, fmtU, w);
    else if (d.tipo === 'dona') caja.innerHTML = svgDona(d, fmtU, w);
    else caja.innerHTML = tablaTop(d, fmtU, w);
    tooltips(caja);
  }
  function svgSerie(d, ancho, fmtU, w) {
    const u0 = d.series[0].puntos.find(x => x.valor !== null)?.unidad || d.series[0].puntos[0]?.unidad || 'numero';
    const usar = d.series.filter((s, i) => i === 0 || (s.puntos.find(x => x.valor !== null)?.unidad || u0) === u0).slice(0, 4);
    const nota = usar.length < d.series.length ? '<p class="wg-motivo">Algunas series miden otra cosa (por ejemplo, dinero y cantidad) y no se dibujan en el mismo eje: usa un gráfico para cada una.</p>' : '';
    const u = fmtU(u0), vals = usar.flatMap(s => s.puntos.map(x => x.valor)).filter(v => v !== null);
    if (!vals.length) { const m = d.series[0].puntos.find(x => x.motivo)?.motivo; return `<p class="wg-motivo">${esc(m || 'Sin datos para estos meses.')}</p>`; }
    const W = Math.max(260, Math.round(ancho)), Hh = 200, L = 64, Rr = 12, T = 12, B = 26, iw = W - L - Rr, ih = Hh - T - B;
    let lo = Math.min(0, ...vals), hi = Math.max(0, ...vals); if (hi === lo) hi = lo + 1;
    const paso = (() => { const r = (hi - lo) / 4, e = 10 ** Math.floor(Math.log10(r)), f = r / e; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * e; })();
    lo = Math.floor(lo / paso) * paso; hi = Math.ceil(hi / paso) * paso;
    const n = d.meses.length, x = i => L + (SERIE.includes(d.tipo) && d.tipo !== 'barras' ? (n === 1 ? iw / 2 : i * iw / (n - 1)) : (i + .5) * iw / n), y = v => T + ih - (v - lo) / (hi - lo) * ih;
    let g = '';
    for (let v = lo; v <= hi + paso / 2; v += paso) g += `<line class="wg-grid-l" x1="${L}" x2="${W - Rr}" y1="${y(v)}" y2="${y(v)}"/><text class="wg-eje" x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${esc(fmtEje(v, u))}</text>`;
    const cada = Math.ceil(n / Math.max(2, Math.floor(iw / 56)));
    d.meses.forEach((p, i) => { if (i % cada === 0 || i === n - 1) g += `<text class="wg-eje" x="${x(i)}" y="${Hh - 6}" text-anchor="middle">${esc(mesCorto(p))}</text>`; });
    let marcas = '';
    if (d.tipo === 'barras') {
      const banda = iw / n, k = usar.length, bw = Math.max(3, Math.min(28, (banda * .7 - (k - 1) * 2) / k));
      usar.forEach((s, j) => s.puntos.forEach((pt, i) => { if (pt.valor === null) return;
        const x0 = L + i * banda + (banda - (bw * k + 2 * (k - 1))) / 2 + j * (bw + 2), y0 = y(Math.max(0, pt.valor)), h = Math.max(1, Math.abs(y(pt.valor) - y(0))), r = Math.min(4, bw / 2, h);
        const path = pt.valor >= 0 ? `M${x0},${y0 + h}V${y0 + r}Q${x0},${y0} ${x0 + r},${y0}H${x0 + bw - r}Q${x0 + bw},${y0} ${x0 + bw},${y0 + r}V${y0 + h}Z` : `M${x0},${y(0)}H${x0 + bw}V${y(0) + h - r}Q${x0 + bw},${y(0) + h} ${x0 + bw - r},${y(0) + h}H${x0 + r}Q${x0},${y(0) + h} ${x0},${y(0) + h - r}Z`;
        marcas += `<path d="${path}" fill="${PAL[j]}"/>`; }));
    } else {
      usar.forEach((s, j) => {
        const tramos = []; let act = [];
        s.puntos.forEach((pt, i) => { if (pt.valor === null) { if (act.length) tramos.push(act); act = []; } else act.push([x(i), y(pt.valor)]); }); if (act.length) tramos.push(act);
        tramos.forEach(t => {
          if (d.tipo === 'area') marcas += `<path d="M${t[0][0]},${y(Math.max(lo, 0))}L${t.map(q => q.join(',')).join('L')}L${t.at(-1)[0]},${y(Math.max(lo, 0))}Z" fill="${PAL[j]}" fill-opacity="${usar.length > 1 ? .12 : .18}"/>`;
          marcas += `<polyline points="${t.map(q => q.join(',')).join(' ')}" fill="none" stroke="${PAL[j]}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`;
          if (t.length === 1 || n <= 12) t.forEach(q => { marcas += `<circle cx="${q[0]}" cy="${q[1]}" r="4" fill="${PAL[j]}" class="wg-punto"/>`; });
        });
      });
    }
    // capa de hover: una franja por mes con su texto para el tooltip
    let hov = '';
    d.meses.forEach((p, i) => { const bw = iw / n, x0 = d.tipo === 'barras' ? L + i * bw : Math.max(L, x(i) - bw / 2);
      const txt = `${nombreMes(p)}|` + usar.map((s, j) => `${j}~${s.nombre}~${fmt(s.puntos[i]?.valor ?? null, fmtU(s.puntos[i]?.unidad || u0), w.decimales ?? 2)}`).join('|');
      hov += `<rect class="wg-hov" x="${x0}" y="${T}" width="${Math.min(bw, W - Rr - x0)}" height="${ih}" data-tip="${esc(txt)}" data-cx="${d.tipo === 'barras' ? x0 + bw / 2 : x(i)}"/>`; });
    const ley = usar.length > 1 ? `<div class="wg-ley">${usar.map((s, j) => `<span><i style="background:${PAL[j]}"></i>${esc(s.nombre)}</span>`).join('')}</div>` : '';
    const ult = usar[0].puntos.map(q => q.valor).filter(v => v !== null).at(-1);
    const aria = `${usar.map(s => s.nombre).join(', ')}, de ${nombreMes(d.meses[0])} a ${nombreMes(d.meses.at(-1))}. Último valor: ${fmt(ult ?? null, u, w.decimales ?? 2)}.`;
    return `${ley}<div class="wg-svgw"><svg class="wg-svg" viewBox="0 0 ${W} ${Hh}" width="${W}" height="${Hh}" role="img" aria-label="${esc(aria)}">${g}<line class="wg-cruz" x1="0" x2="0" y1="${T}" y2="${T + ih}" visibility="hidden"/>${marcas}${hov}</svg><div class="wg-tip" hidden></div></div>${nota}`;
  }
  function svgDona(d, fmtU, w) {
    const ok = d.partes.filter(x => x.valor !== null && x.valor > 0), u0 = ok[0]?.unidad || 'numero';
    if (!ok.length) return `<p class="wg-motivo">${esc(d.partes.find(x => x.motivo)?.motivo || 'Sin valores positivos para repartir en este mes.')}</p>`;
    if (ok.some(x => x.unidad !== u0)) return '<p class="wg-motivo">Las partes de una dona deben medir lo mismo (todas en dinero o todas en cantidad).</p>';
    let partes = [...ok].sort((a, b) => b.valor - a.valor);
    if (partes.length > 5) { const resto = partes.slice(4); partes = [...partes.slice(0, 4), {nombre: `Otros (${resto.length})`, valor: resto.reduce((a, b) => a + b.valor, 0), unidad: u0, otros: 1}]; }
    const total = partes.reduce((a, b) => a + b.valor, 0), u = fmtU(u0), R0 = 70, r0 = 44, c = 80;
    let a0 = -Math.PI / 2, arcos = '';
    partes.forEach((pt, i) => { const ang = pt.valor / total * Math.PI * 2, a1 = a0 + ang, big = ang > Math.PI ? 1 : 0, col = pt.otros ? OTROS : PAL[i];
      const P = (r, a) => `${c + r * Math.cos(a)},${c + r * Math.sin(a)}`;
      const path = partes.length === 1 ? `M${c},${c - R0}A${R0},${R0} 0 1 1 ${c - .01},${c - R0}L${c - .01},${c - r0}A${r0},${r0} 0 1 0 ${c},${c - r0}Z` : `M${P(R0, a0)}A${R0},${R0} 0 ${big} 1 ${P(R0, a1)}L${P(r0, a1)}A${r0},${r0} 0 ${big} 0 ${P(r0, a0)}Z`;
      arcos += `<path d="${path}" fill="${col}" class="wg-arco" data-tip="${esc(`${pt.nombre}|0~${fmt(pt.valor, u, w.decimales ?? 2)}~${NF(1).format(pt.valor / total * 100)} % del total`)}"/>`; a0 = a1; });
    const ley = partes.map((pt, i) => `<li><i style="background:${pt.otros ? OTROS : PAL[i]}"></i><span>${esc(pt.nombre)}</span><b>${esc(fmt(pt.valor, u, w.decimales ?? 2))}</b><small>${NF(1).format(pt.valor / total * 100)} %</small></li>`).join('');
    const fuera = d.partes.length - ok.length;
    return `<div class="wg-dona"><div class="wg-svgw"><svg class="wg-svg" viewBox="0 0 160 160" width="160" height="160" role="img" aria-label="${esc(partes.map(pt => `${pt.nombre}: ${fmt(pt.valor, u)}`).join('; '))}">${arcos}<text x="80" y="76" text-anchor="middle" class="wg-eje">Total</text><text x="80" y="96" text-anchor="middle" class="wg-dona-t">${esc(fmtEje(total, u))}</text></svg><div class="wg-tip" hidden></div></div><ul class="wg-ley wg-ley-v">${ley}</ul></div>${fuera ? `<p class="wg-motivo">${fuera} ${fuera === 1 ? 'parte queda fuera' : 'partes quedan fuera'}: sin dato o en cero.</p>` : ''}<p class="wg-motivo">${esc(nombreMes(d.periodo))}</p>`;
  }
  function tablaTop(d, fmtU, w) {
    const filas = [...d.partes].sort((a, b) => (b.valor ?? -Infinity) - (a.valor ?? -Infinity)).slice(0, w.top || 5);
    const u0 = filas.find(x => x.valor !== null)?.unidad, mismas = filas.every(x => x.valor === null || x.unidad === u0), max = Math.max(...filas.map(x => Math.abs(x.valor || 0)), 0);
    return `<table class="wg-tabla"><thead><tr><th>#</th><th>Cifra</th><th>Valor</th></tr></thead><tbody>${filas.map((x, i) => `<tr><td>${i + 1}</td><td>${esc(x.nombre)}${mismas && max && x.valor !== null ? `<span class="wg-barra"><i style="width:${Math.abs(x.valor) / max * 100}%"></i></span>` : ''}</td><td>${esc(fmt(x.valor, fmtU(x.unidad), w.decimales ?? 2))}${x.valor === null && x.motivo ? `<small>${esc(x.motivo)}</small>` : ''}</td></tr>`).join('')}</tbody></table><p class="wg-motivo">${esc(nombreMes(d.periodo))}</p>`;
  }
  function tooltips(caja) {
    const tip = caja.querySelector('.wg-tip'), cruz = caja.querySelector('.wg-cruz'), wrap = caja.querySelector('.wg-svgw'); if (!tip) return;
    const mostrar = (el, ev) => {
      const [tit, ...ls] = el.dataset.tip.split('|');
      tip.innerHTML = `<b>${esc(tit)}</b>${ls.map(l => { const [j, n, v] = l.split('~'); return `<span>${n && v !== undefined && el.classList.contains('wg-hov') ? `<i style="background:${PAL[+j]}"></i>${esc(n)}: ` : ''}<b>${esc(v !== undefined && el.classList.contains('wg-hov') ? v : n)}</b>${v !== undefined && !el.classList.contains('wg-hov') ? ` · ${esc(v)}` : ''}</span>`; }).join('')}`;
      tip.hidden = false;
      const rw = wrap.getBoundingClientRect(), sv = wrap.querySelector('svg'), k = sv.getBoundingClientRect().width / sv.viewBox.baseVal.width;
      let cx = el.dataset.cx ? +el.dataset.cx * k : ev.clientX - rw.left;
      if (cruz && el.dataset.cx) { cruz.setAttribute('x1', el.dataset.cx); cruz.setAttribute('x2', el.dataset.cx); cruz.setAttribute('visibility', 'visible'); }
      tip.style.left = `${Math.max(0, Math.min(cx + 12, rw.width - tip.offsetWidth))}px`; tip.style.top = '4px';
    };
    const ocultar = () => { tip.hidden = true; cruz?.setAttribute('visibility', 'hidden'); };
    caja.querySelectorAll('[data-tip]').forEach(el => { el.addEventListener('pointerenter', e => mostrar(el, e)); el.addEventListener('pointermove', e => { if (!el.dataset.cx) mostrar(el, e); }); el.addEventListener('pointerleave', ocultar); });
  }

  /* ---------- detalle de una cifra (2.16.0 · fase 5): qué mide, de qué está hecha, sus últimos meses y descarga ---------- */
  async function detalle(w, cat) {
    const h = H(), id = clave(w), p = periodoDe(w), titulo = w.titulo || nombreRef(w, cat);
    const m = h.openModal(h.modalHead(esc(titulo), 'Detalle de la cifra') + '<div class="wg-detalle" aria-busy="true"><p class="wg-motivo">Cargando…</p></div>', {wide: true});
    const caja = m.querySelector('.wg-detalle');
    try {
      const det = await api(`workspace/metricas/detalle?id=${enc(id)}&periodo=${p}&hoy=${hoy()}${H().zona()}`);
      const ser = det.deHoy ? null : await api(`workspace/metricas/serie?ids=${enc(id)}&hasta=${p}&meses=6&hoy=${hoy()}${H().zona()}`).catch(() => null);
      const u = r => w.formato && w.formato !== 'auto' ? w.formato : r.unidad;
      const filas = ser ? ser.meses.map((mm, i) => [mm, ser.series[id][i]]) : [];
      caja.innerHTML = `<p class="wg-det-v"><span>${esc(det.deHoy ? 'Hoy' : nombreMes(det.periodo))}</span><b>${esc(fmt(det.valor.valor, u(det.valor), w.decimales ?? 2))}</b>${det.valor.valor === null && det.valor.motivo ? `<small>${esc(det.valor.motivo)}</small>` : ''}</p>
        <dl class="wg-det-dl"><dt>Qué mide</dt><dd>${esc(det.descripcion || det.nombre)}</dd><dt>De dónde sale</dt><dd>${esc(det.grupo)}${det.tipo === 'formula' ? '' : ' (la misma cifra que muestra Ágora en su tarjeta)'}</dd>
        ${det.tipo === 'formula' ? `<dt>Fórmula</dt><dd><code class="wg-fx-ver">${colores(det.expresion)}</code></dd>` : ''}</dl>
        ${det.partes.length ? `<h3>Cada parte en ${esc(nombreMes(det.periodo))}</h3><table class="wg-tabla"><thead><tr><th>Parte</th><th>Valor</th></tr></thead><tbody>${det.partes.map(x => `<tr><td>${esc(x.nombre)}</td><td>${esc(fmt(x.valor, x.unidad))}${x.valor === null && x.motivo ? `<small>${esc(x.motivo)}</small>` : ''}</td></tr>`).join('')}</tbody></table>` : ''}
        ${filas.length ? `<h3>Últimos 6 meses</h3><table class="wg-tabla"><thead><tr><th>Mes</th><th>Valor</th></tr></thead><tbody>${filas.map(([mm, r]) => `<tr><td>${esc(nombreMes(mm))}</td><td>${esc(fmt(r.valor, u(r), w.decimales ?? 2))}${r.valor === null && r.motivo ? `<small>${esc(r.motivo)}</small>` : ''}</td></tr>`).join('')}</tbody></table>` : det.deHoy ? '<p class="wg-motivo">Es una cifra de hoy: no guarda historia por mes.</p>' : ''}
        <div class="form-ft"><span class="grow"></span><button class="btn" type="button" data-close>Cerrar</button><button class="btn btn-cta" type="button" data-wg-csv>${ic('download')}Descargar</button></div>`;
      caja.removeAttribute('aria-busy');
      caja.querySelector('[data-wg-csv]').onclick = async () => {
        const n2 = new Intl.NumberFormat('es-VE', {minimumFractionDigits: 0, maximumFractionDigits: 4, useGrouping: false});
        const q = v => `"${String(v ?? '').replace(/"/g, '""')}"`, num = v => v === null || v === undefined ? '' : n2.format(v);
        const lineas = [[titulo], ['Qué mide', det.descripcion || det.nombre], ...(det.tipo === 'formula' ? [['Fórmula', det.expresion]] : []), [],
          ['Mes', 'Valor', 'Unidad', 'Nota'], ...(filas.length ? filas.map(([mm, r]) => [nombreMes(mm), num(r.valor), u(r), r.valor === null ? r.motivo : '']) : [[det.deHoy ? 'Hoy' : nombreMes(det.periodo), num(det.valor.valor), u(det.valor), det.valor.motivo || '']]),
          ...(det.partes.length ? [[], [`Partes en ${nombreMes(det.periodo)}`, 'Valor', 'Unidad', 'Nota'], ...det.partes.map(x => [x.nombre, num(x.valor), x.unidad, x.valor === null ? x.motivo : ''])] : [])];
        const blob = new Blob(['﻿' + lineas.map(l => l.map(q).join(';')).join('\r\n')], {type: 'text/csv;charset=utf-8'});
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `${titulo.replace(/[^\p{L}\p{N} _-]/gu, '').trim() || 'cifra'} - ${p}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        api('workspace/auditoria', {method: 'POST', body: {accion: 'descargo.excel', detalle: `Widget «${titulo}» · ${filas.length || 1} filas`}}).catch(() => {});
      };
    } catch (e) { caja.innerHTML = `<p class="wg-motivo">${esc(e.message)}</p><div class="form-ft"><span class="grow"></span><button class="btn" type="button" data-close>Cerrar</button></div>`; }
  }

  /* ---------- editor de tableros ---------- */
  function opcionesRef(cat, w) {
    const grupos = {};
    (cat.metricas || []).forEach(m => { (grupos[m.grupo] ||= []).push(m); });
    const sel = w ? clave(w) : '';
    return `<option value="">Elige una cifra o una fórmula…</option>${Object.entries(grupos).map(([g, ms]) => `<optgroup label="${esc(g)}">${ms.map(m => `<option value="${esc(m.id)}" ${sel === m.id ? 'selected' : ''}>${esc(m.nombre)}</option>`).join('')}</optgroup>`).join('')}
      ${(cat.formulas || []).length ? `<optgroup label="Fórmulas del equipo">${cat.formulas.map(f => `<option value="f:${f.id}" ${sel === 'f:' + f.id ? 'selected' : ''}>${esc(f.nombre)}</option>`).join('')}</optgroup>` : ''}`;
  }
  async function editorTablero(mod, foco, estado) {
    const h = H();
    let cat; try { cat = await catalogo(true); } catch (e) { h.toast(e.message); return; }
    const t = st.tab[mod] || {widgets: [], version: 0};
    const s = estado || {ws: JSON.parse(JSON.stringify(t.widgets)), version: t.version, abierto: foco || null, visible: t.visible || 'todos'};
    const otros = MODULOS.filter(x => x !== mod && h.nombreDe(x));
    const m = h.openModal(h.modalHead('Editar widgets', `Cifras y gráficos del equipo en ${esc(h.nombreModulo())}. Solo la administración los cambia; los gráficos van al final del módulo.`) + `<div class="wg-ed">
      <div class="wg-top"><label class="fld">Quién los ve<select data-wg-visible><option value="todos" ${s.visible !== 'administracion' ? 'selected' : ''}>Todo el equipo</option><option value="administracion" ${s.visible === 'administracion' ? 'selected' : ''}>Solo la administración</option></select></label>
        <label class="fld">Copiar los widgets de otro módulo<select data-wg-copiar><option value="">Elige un módulo…</option>${otros.map(x => `<option value="${x}">${esc(h.nombreDe(x))}</option>`).join('')}</select></label></div>
      <ol class="pz-list wg-lista" data-wg-lista></ol>
      <div class="wg-add"><label class="fld grow"><span>Agregar una cifra o un gráfico</span><select data-wg-nuevo>${opcionesRef(cat)}</select></label>
        <button class="btn" type="button" data-wg-formula>${ic('plus')}Nueva fórmula</button><button class="btn" type="button" data-wg-formulas>${ic('edit')}Fórmulas y variables</button></div>
      <p class="fld-err" role="alert"></p>
      <div class="form-ft"><button class="btn" type="button" data-wg-restablecer>${ic('refresh')}Restablecer</button><span class="grow"></span><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="button" data-wg-guardar>Guardar</button></div></div>`, {wide: true});
    const lista = m.querySelector('[data-wg-lista]'), err = m.querySelector('.fld-err');
    const campo = (w, k, v) => { w[k] = v; };
    const pintarLista = () => {
      lista.innerHTML = s.ws.length ? s.ws.map((w, i) => {
        const abierto = s.abierto === w.id, p = /^\d{4}-\d{2}$/.test(w.periodo) ? w.periodo : '';
        const tipo = w.tipo || 'cifra', graf = GRAF.includes(tipo), lim = SERIE.includes(tipo) ? 3 : 9, mas = w.mas || [];
        return `<li class="pz-item is-on wg-item ${abierto ? 'is-open' : ''}" data-i="${i}"><div class="wg-item-h"><span><b>${esc(w.titulo || nombreRef(w, cat))}</b><small>${esc(TIPOS.find(x => x[0] === tipo)[1])} · ${esc(nombreRef(w, cat))}${mas.length ? ` y ${mas.length} más` : ''}${w.tamano === 'ancho' ? ' · ancho' : ''}</small></span>
          <span class="wg-item-acc"><button class="icon-btn" type="button" data-mv="-1" ${i ? '' : 'disabled'} aria-label="Subir">${ic('chev')}</button><button class="icon-btn wg-baja" type="button" data-mv="1" ${i < s.ws.length - 1 ? '' : 'disabled'} aria-label="Bajar">${ic('chev')}</button>
          <button class="btn btn-sm" type="button" data-cfg>${abierto ? 'Listo' : 'Configurar'}</button><button class="btn btn-sm" type="button" data-dup>Duplicar</button><button class="btn btn-sm" type="button" data-quitar>Quitar</button></span></div>
          ${abierto ? `<div class="wg-cfg">
            <label class="fld">Tipo<select data-k="tipo">${TIPOS.map(([v, l]) => `<option value="${v}" ${tipo === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            <label class="fld">Título<input data-k="titulo" maxlength="60" value="${esc(w.titulo)}" placeholder="${esc(nombreRef(w, cat))}"></label>
            <label class="fld">Nota (opcional)<input data-k="nota" maxlength="120" value="${esc(w.nota)}"></label>
            <label class="fld">${graf ? (SERIE.includes(tipo) ? 'Serie 1' : 'Parte 1') : 'Mide'}<select data-k="ref">${opcionesRef(cat, w)}</select></label>
            ${graf ? mas.map((x, j) => `<label class="fld wg-mas">${SERIE.includes(tipo) ? 'Serie' : 'Parte'} ${j + 2}<span class="wg-mas-f"><select data-mas="${j}">${opcionesRef(cat, x)}</select><button class="btn btn-sm" type="button" data-mas-quitar="${j}" aria-label="Quitar">Quitar</button></span></label>`).join('') + (mas.length < lim ? `<label class="fld">${SERIE.includes(tipo) ? 'Agregar una serie' : 'Agregar una parte'}<select data-mas-nueva>${opcionesRef(cat)}</select></label>` : '') : ''}
            ${SERIE.includes(tipo) ? `<label class="fld">Meses<select data-k="meses">${[3, 6, 9, 12].map(n => `<option value="${n}" ${(w.meses || 6) === n ? 'selected' : ''}>Últimos ${n}</option>`).join('')}</select></label>` : ''}
            ${tipo === 'tabla' ? `<label class="fld">Mostrar<select data-k="top">${[3, 5, 7, 10].map(n => `<option value="${n}" ${(w.top || 5) === n ? 'selected' : ''}>Los primeros ${n}</option>`).join('')}</select></label>` : ''}
            <label class="fld">Tamaño<select data-k="tamano"><option value="chico" ${w.tamano !== 'ancho' ? 'selected' : ''}>Chico (1 columna)</option><option value="ancho" ${w.tamano === 'ancho' ? 'selected' : ''}>Ancho (2 columnas)</option></select></label>
            <label class="fld">${SERIE.includes(tipo) ? 'Hasta' : 'Período'}<select data-k="periodo"><option value="modulo" ${w.periodo === 'modulo' ? 'selected' : ''}>El elegido en el módulo</option><option value="actual" ${w.periodo === 'actual' ? 'selected' : ''}>Siempre el mes en curso</option><option value="anterior" ${w.periodo === 'anterior' ? 'selected' : ''}>Siempre el mes anterior</option><option value="fijo" ${p ? 'selected' : ''}>Un mes fijo</option></select></label>
            ${p ? `<label class="fld">Mes<input type="month" data-k="mes" value="${p}"></label>` : ''}
            ${graf ? '' : `<label class="fld">Comparar con<select data-k="comparar"><option value="" ${!w.comparar ? 'selected' : ''}>Sin comparación</option><option value="mes_anterior" ${w.comparar === 'mes_anterior' ? 'selected' : ''}>El mes anterior</option><option value="ano_pasado" ${w.comparar === 'ano_pasado' ? 'selected' : ''}>El mismo mes del año pasado</option></select></label>`}
            <label class="fld">Formato<select data-k="formato">${[['auto', 'Automático'], ['dinero', 'Dinero'], ['numero', 'Número'], ['porcentaje', 'Porcentaje']].map(([v, l]) => `<option value="${v}" ${w.formato === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            <label class="fld">Decimales<select data-k="decimales">${[0, 1, 2, 3, 4].map(d => `<option ${(w.decimales ?? 2) === d ? 'selected' : ''}>${d}</option>`).join('')}</select></label>
            ${graf ? '' : `<fieldset class="wg-semf"><label class="bx-check"><input type="checkbox" data-k="sem" ${w.semaforo?.activo ? 'checked' : ''}> Semáforo</label>
              ${w.semaforo?.activo ? `<label class="fld">En meta desde<input type="number" step="any" data-k="verde" value="${w.semaforo.verde ?? ''}"></label><label class="fld">Cerca desde<input type="number" step="any" data-k="ambar" value="${w.semaforo.ambar ?? ''}"></label>
              <label class="fld">Mejor si es<select data-k="mejor"><option value="mas" ${w.semaforo.mejor !== 'menos' ? 'selected' : ''}>Más alto</option><option value="menos" ${w.semaforo.mejor === 'menos' ? 'selected' : ''}>Más bajo</option></select></label>` : ''}</fieldset>`}
          </div>` : ''}</li>`;
      }).join('') : '<li class="pz-vacio">Todavía no hay widgets. Agrega el primero abajo.</li>';
      lista.querySelectorAll('[data-i]').forEach(li => {
        const i = +li.dataset.i, w = s.ws[i];
        li.querySelectorAll('[data-mv]').forEach(b => b.onclick = () => { const j = i + +b.dataset.mv; [s.ws[i], s.ws[j]] = [s.ws[j], s.ws[i]]; pintarLista(); });
        li.querySelector('[data-cfg]').onclick = () => { s.abierto = s.abierto === w.id ? null : w.id; pintarLista(); };
        li.querySelector('[data-dup]').onclick = () => { if (s.ws.length >= 24) { err.textContent = 'Un tablero lleva hasta 24 widgets.'; return; } s.ws.splice(i + 1, 0, {...JSON.parse(JSON.stringify(w)), id: uid()}); pintarLista(); };
        li.querySelector('[data-quitar]').onclick = () => { s.ws.splice(i, 1); pintarLista(); };
        const aRef = v => v.startsWith('f:') ? {fuente: 'formula', ref: +v.slice(2)} : {fuente: 'metrica', ref: v};
        li.querySelectorAll('[data-mas]').forEach(el => el.onchange = () => { if (el.value) w.mas[+el.dataset.mas] = aRef(el.value); pintarLista(); });
        li.querySelectorAll('[data-mas-quitar]').forEach(b => b.onclick = () => { w.mas.splice(+b.dataset.masQuitar, 1); pintarLista(); });
        li.querySelector('[data-mas-nueva]')?.addEventListener('change', e => { if (e.target.value) { (w.mas ||= []).push(aRef(e.target.value)); pintarLista(); } });
        li.querySelectorAll('[data-k]').forEach(el => el.onchange = el.oninput = e => {
          const k = el.dataset.k, v = el.type === 'checkbox' ? el.checked : el.value;
          if (k === 'ref') { if (v.startsWith('f:')) { w.fuente = 'formula'; w.ref = +v.slice(2); } else if (v) { w.fuente = 'metrica'; w.ref = v; } pintarLista(); return; }
          if (k === 'periodo') { w.periodo = v === 'fijo' ? H().periodo() : v; pintarLista(); return; }
          if (k === 'mes') { if (/^\d{4}-\d{2}$/.test(v)) w.periodo = v; return; }
          if (k === 'decimales') { w.decimales = +v; return; }
          if (k === 'tipo') { const antes = GRAF.includes(w.tipo); w.tipo = v; w.mas = (w.mas || []).slice(0, SERIE.includes(v) ? 3 : 9); if (GRAF.includes(v)) { w.comparar = ''; w.semaforo = {activo: false}; if (!antes) w.tamano = 'ancho'; } pintarLista(); return; }
          if (k === 'meses' || k === 'top') { w[k] = +v; return; }
          if (k === 'sem') { w.semaforo = v ? {activo: true, verde: w.semaforo?.verde ?? 100, ambar: w.semaforo?.ambar ?? 70, mejor: 'mas'} : {activo: false}; pintarLista(); return; }
          if (['verde', 'ambar'].includes(k)) { w.semaforo[k] = v === '' ? null : +v; return; }
          if (k === 'mejor') { w.semaforo.mejor = v; return; }
          if (e.type === 'input' || el.tagName === 'INPUT') campo(w, k, v); else { campo(w, k, v); }
        });
      });
      if (foco) { lista.querySelector('.wg-item.is-open')?.scrollIntoView({block: 'center'}); foco = null; }
    };
    pintarLista();
    m.querySelector('[data-wg-nuevo]').onchange = e => {
      const v = e.target.value; if (!v) return; if (s.ws.length >= 24) { err.textContent = 'Un tablero lleva hasta 24 widgets.'; e.target.value = ''; return; }
      const w = {id: uid(), tipo: 'cifra', fuente: v.startsWith('f:') ? 'formula' : 'metrica', ref: v.startsWith('f:') ? +v.slice(2) : v, titulo: '', nota: '', tamano: 'chico', periodo: 'modulo', comparar: '', formato: 'auto', decimales: 2, semaforo: {activo: false}, mas: [], meses: 6, top: 5};
      s.ws.push(w); s.abierto = w.id; e.target.value = ''; pintarLista(); lista.lastElementChild?.scrollIntoView({block: 'nearest'});
    };
    m.querySelector('[data-wg-formula]').onclick = () => editorFormula(null, () => editorTablero(mod, null, s), f => { s.ws.push({id: uid(), fuente: 'formula', ref: f.id, titulo: '', nota: '', tamano: 'chico', periodo: 'modulo', comparar: '', formato: 'auto', decimales: 2, semaforo: {activo: false}}); s.abierto = s.ws.at(-1).id; });
    m.querySelector('[data-wg-formulas]').onclick = () => gestor(() => editorTablero(mod, null, s));
    m.querySelector('[data-wg-visible]').onchange = e => { s.visible = e.target.value; };
    m.querySelector('[data-wg-copiar]').onchange = async e => {
      const x = e.target.value; if (!x) return; err.textContent = '';
      try { const o = await api(`workspace/tableros?modulo=${enc(x)}`); if (!o.widgets.length) { err.textContent = `«${h.nombreDe(x)}» no tiene widgets para copiar.`; }
        else { const cabe = 24 - s.ws.length; o.widgets.slice(0, cabe).forEach(w => s.ws.push({...w, id: uid()})); if (o.widgets.length > cabe) err.textContent = `Se copiaron ${Math.max(0, cabe)} de ${o.widgets.length}: un tablero lleva hasta 24 widgets.`; else h.toast(`Se copiaron ${o.widgets.length} widgets de «${h.nombreDe(x)}». Revisa y guarda.`); pintarLista(); } }
      catch (y) { err.textContent = y.message; }
      e.target.value = '';
    };
    m.querySelector('[data-wg-restablecer]').onclick = () => h.confirmar('Quitar los widgets', 'Se quitan todos los widgets de este módulo para todo el equipo. Las fórmulas y variables no se borran.', 'Quitar widgets', async () => { await api(`workspace/tableros?modulo=${enc(mod)}`, {method: 'DELETE', body: {modulo: mod}}); h.toast('Widgets quitados.'); repintar(mod); }, true);
    m.querySelector('[data-wg-guardar]').onclick = async e => {
      const b = e.currentTarget; h.busy(b, true, 'Guardando…'); err.textContent = '';
      try { const r = await api('workspace/tableros', {method: 'PUT', body: {modulo: mod, widgets: s.ws, version: s.version, visible: s.visible}}); st.tab[mod] = {...st.tab[mod], ...r}; h.closeModal(); h.toast('Widgets guardados.'); repintar(mod); }
      catch (x) { h.busy(b, false); err.textContent = x.message; }
    };
  }

  /* ---------- fórmulas: gestor, editor con autocompletar, colores y vista previa ---------- */
  const EJEMPLOS = [
    ['Margen', '{Vendido del mes} − {Costos fijos}', 'dinero'],
    ['Margen %', 'PORCENTAJE({Vendido del mes} − {Costos fijos}; {Vendido del mes})', 'porcentaje'],
    ['Ticket promedio', '{Vendido del mes} ÷ {Cierres del mes}', 'dinero'],
    ['Ventas que faltan para la meta', 'REDONDEAR({Falta para la meta} ÷ ({Vendido del mes} ÷ {Cierres del mes}); 0)', 'numero'],
    ['Crecimiento frente al mes anterior', 'CRECIMIENTO({Vendido del mes}; {Vendido del mes · mes anterior})', 'porcentaje'],
    ['Días hábiles que quedan', 'DIAS_HABILES(HOY(); FIN_MES())', 'numero'],
  ];
  function colores(src, errPos = -1) {
    let out = '', k = 0;
    const re = /(\{[^}]*\}?)|([A-Za-zÁÉÍÓÚÑáéíóúñ_][\wÁÉÍÓÚÑáéíóúñ]*(?=\s*\())|(\d+(?:[.,]\d+)?%?)|([-+−×÷*/^()=<>≤≥≠;])|(\s+)|(.)/g;
    let m;
    while ((m = re.exec(src))) {
      const [t] = m, cls = m[1] ? 'fx-ref' : m[2] ? 'fx-fn' : m[3] ? 'fx-num' : m[4] ? 'fx-op' : m[5] ? '' : 'fx-otro';
      const mal = errPos >= k && errPos < k + t.length;
      out += `<span class="${cls}${mal ? ' fx-err' : ''}">${esc(t)}</span>`; k += t.length;
    }
    if (errPos >= src.length && errPos >= 0) out += '<span class="fx-err fx-fin"> </span>';
    return out || '<span class="fx-vacio">Escribe la fórmula arriba.</span>';
  }
  async function editorFormula(f, volver, alGuardar) {
    const h = H(); let cat; try { cat = await catalogo(true); } catch (e) { h.toast(e.message); return; }
    const nombres = [...(cat.metricas || []).map(x => ({n: x.nombre, t: x.grupo})), ...(cat.formulas || []).filter(x => !f || x.id !== f.id).map(x => ({n: x.nombre, t: 'Fórmula'})), ...[...new Set((cat.variables || []).map(v => v.nombre))].map(n => ({n, t: 'Variable'}))];
    const m = h.openModal(h.modalHead(f ? 'Editar fórmula' : 'Nueva fórmula', 'Usa cifras entre llaves, por ejemplo {Vendido del mes}, números, + − × ÷ ^ y funciones. Separa los datos de una función con «;».') + `<form class="form wg-fx" novalidate>
      ${f ? '' : `<div class="wg-ej"><span>Ejemplos:</span>${EJEMPLOS.map((e, i) => `<button type="button" class="btn btn-sm" data-ej="${i}">${esc(e[0])}</button>`).join('')}</div>`}
      <label class="fld">Nombre<input name="nombre" maxlength="60" required value="${esc(f?.nombre || '')}" placeholder="Por ejemplo: Margen"></label>
      <div class="fld wg-fx-campo"><span>Fórmula</span><textarea name="expresion" rows="3" maxlength="500" spellcheck="false" autocomplete="off" placeholder="{Vendido del mes} − {Costos fijos}">${esc(f?.expresion || '')}</textarea>
        <ul class="wg-ac" role="listbox" hidden></ul><div class="wg-fx-ver" aria-live="polite"></div></div>
      <div class="wg-fx-fila"><label class="fld">Insertar función<select data-fn><option value="">Elige…</option>${(cat.funciones || []).map(x => `<option value="${esc(x.nombre)}">${esc(x.ayuda)}</option>`).join('')}</select></label>
        <label class="fld">Formato<select name="formato">${[['auto', 'Automático'], ['dinero', 'Dinero'], ['numero', 'Número'], ['porcentaje', 'Porcentaje'], ['fecha', 'Fecha']].map(([v, l]) => `<option value="${v}" ${(f?.formato || 'auto') === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
        <label class="fld">Decimales<select name="decimales">${[0, 1, 2, 3, 4].map(d => `<option ${(f?.decimales ?? 2) === d ? 'selected' : ''}>${d}</option>`).join('')}</select></label></div>
      <label class="fld">Descripción (opcional)<input name="descripcion" maxlength="190" value="${esc(f?.descripcion || '')}"></label>
      <p class="wg-prev" aria-live="polite">Escribe la fórmula para ver el resultado.</p>
      <p class="fld-err" role="alert"></p>
      <div class="form-ft">${f ? '<button class="btn" type="button" data-borrar>Borrar</button>' : ''}<span class="grow"></span><button class="btn" type="button" data-volver>Cancelar</button><button class="btn btn-cta" type="submit">Guardar</button></div></form>`, {wide: true});
    const form = m.querySelector('form'), ta = form.expresion, ver = m.querySelector('.wg-fx-ver'), prev = m.querySelector('.wg-prev'), err = m.querySelector('.fld-err'), ac = m.querySelector('.wg-ac');
    let t = 0, n = 0, errPos = -1;
    const pintarVer = () => { ver.innerHTML = colores(ta.value, errPos); };
    const probar = async () => {
      const gen = ++n, expr = ta.value.trim(); errPos = -1;
      if (!expr) { prev.textContent = 'Escribe la fórmula para ver el resultado.'; prev.className = 'wg-prev'; pintarVer(); return; }
      try {
        const r = await api('workspace/formulas/probar', {method: 'POST', body: {expresion: expr, periodo: H().periodo(), nombre: form.nombre.value || undefined}});
        if (gen !== n) return;
        if (!r.ok) { errPos = r.pos; prev.className = 'wg-prev is-err'; prev.textContent = r.error; }
        else { const u = form.formato.value !== 'auto' ? form.formato.value : r.unidad; prev.className = 'wg-prev is-ok'; prev.innerHTML = `Resultado en ${esc(nombreMes(r.periodo))}: <b>${esc(fmt(r.valor, u, +form.decimales.value))}</b>${r.valor === null && r.motivo ? ` · ${esc(r.motivo)}` : ''}`; }
      } catch (x) { if (gen === n) { prev.className = 'wg-prev is-err'; prev.textContent = x.message; } }
      pintarVer();
    };
    const tocar = () => { pintarVer(); clearTimeout(t); t = setTimeout(probar, 350); };
    /* autocompletar dentro de { } */
    let sel = 0, cand = [];
    const abierta = () => { const a = ta.value.slice(0, ta.selectionStart), i = a.lastIndexOf('{'); return i >= 0 && a.lastIndexOf('}') < i ? {i, q: a.slice(i + 1)} : null; };
    const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const mostrar = () => {
      const o = abierta(); if (!o) { ac.hidden = true; return; }
      const q = norm(o.q.split('·')[0].trim()); cand = nombres.filter(x => norm(x.n).includes(q)).slice(0, 8); sel = 0;
      if (!cand.length) { ac.hidden = true; return; }
      ac.innerHTML = cand.map((x, i) => `<li role="option" data-ac="${i}" class="${i === sel ? 'is-on' : ''}"><b>${esc(x.n)}</b><small>${esc(x.t)}</small></li>`).join(''); ac.hidden = false;
      ac.querySelectorAll('[data-ac]').forEach(li => li.onmousedown = e => { e.preventDefault(); elegir(+li.dataset.ac); });
    };
    const elegir = i => { const o = abierta(); if (!o || !cand[i]) return; const fin = ta.value.indexOf('}', ta.selectionStart); const tras = fin >= 0 && !ta.value.slice(ta.selectionStart, fin).includes('{') ? fin + 1 : ta.selectionStart;
      ta.value = ta.value.slice(0, o.i) + '{' + cand[i].n + '}' + ta.value.slice(tras); const p = o.i + cand[i].n.length + 2; ta.setSelectionRange(p, p); ac.hidden = true; ta.focus(); tocar(); };
    ta.addEventListener('input', () => { mostrar(); tocar(); });
    ta.addEventListener('keydown', e => {
      if (ac.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + cand.length) % cand.length; ac.querySelectorAll('li').forEach((li, i) => li.classList.toggle('is-on', i === sel)); }
      else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); elegir(sel); }
      else if (e.key === 'Escape') { e.stopPropagation(); ac.hidden = true; }
    });
    ta.addEventListener('blur', () => setTimeout(() => { ac.hidden = true; }, 150));
    m.querySelector('[data-fn]').onchange = e => { const v = e.target.value; if (!v) return; const a = ta.selectionStart, ins = v + '()'; ta.value = ta.value.slice(0, a) + ins + ta.value.slice(ta.selectionEnd); ta.focus(); ta.setSelectionRange(a + ins.length - 1, a + ins.length - 1); e.target.value = ''; tocar(); };
    form.formato.onchange = form.decimales.onchange = () => probar();
    m.querySelectorAll('[data-ej]').forEach(b => b.onclick = () => { const [nom, ex, fo] = EJEMPLOS[+b.dataset.ej]; if (!form.nombre.value) form.nombre.value = nom; ta.value = ex; form.formato.value = fo; tocar(); });
    m.querySelector('[data-volver]').onclick = () => { volver ? volver() : H().closeModal(); };
    m.querySelector('[data-borrar]')?.addEventListener('click', () => H().confirmar('Borrar fórmula', `Se borra «${esc(f.nombre)}». Si un tablero la usa, primero quítala de ese tablero.`, 'Borrar', async () => { await api('workspace/formulas', {method: 'DELETE', body: {id: f.id}}); H().toast('Fórmula borrada.'); st.cat = null; setTimeout(() => volver?.(), 0); }, true));
    form.onsubmit = async e => {
      e.preventDefault(); const b = form.querySelector('[type=submit]'); err.textContent = '';
      if (form.nombre.value.trim().length < 2) { err.textContent = 'Escribe un nombre de al menos 2 letras.'; form.nombre.focus(); return; }
      H().busy(b, true, 'Guardando…');
      try {
        const body = {nombre: form.nombre.value.trim(), expresion: ta.value.trim(), formato: form.formato.value, decimales: +form.decimales.value, descripcion: form.descripcion.value.trim(), ...(f ? {id: f.id} : {})};
        const r = await api('workspace/formulas', {method: f ? 'PUT' : 'POST', body}); st.cat = null; H().toast(f ? 'Fórmula guardada.' : 'Fórmula creada.');
        alGuardar?.(r.formula); volver ? volver() : H().closeModal(); document.querySelectorAll('section.wg').forEach(sec => pintar(sec, sec.dataset.wgMod));
      } catch (x) { H().busy(b, false); err.textContent = x.message; }
    };
    pintarVer(); if (ta.value) probar(); (f ? ta : form.nombre).focus();
  }
  async function gestor(volver) {
    const h = H(); let cat; try { cat = await catalogo(true); } catch (e) { h.toast(e.message); return; }
    const vars = {}; (cat.variables || []).forEach(v => { (vars[v.nombre] ||= []).push(v); });
    const m = h.openModal(h.modalHead('Fórmulas y variables', 'Las fórmulas calculan con las cifras del sistema. Las variables son valores propios (una meta, un sueldo) que se pueden cambiar mes a mes.') + `<div class="wg-gestor">
      <h3>Fórmulas</h3><ul class="pz-list">${(cat.formulas || []).map(f => `<li class="pz-item wg-g-item"><span><b>${esc(f.nombre)}</b><small class="wg-code">${esc(f.expresion)}</small></span><button class="btn btn-sm" type="button" data-fx="${f.id}">Editar</button></li>`).join('') || '<li class="pz-vacio">Todavía no hay fórmulas.</li>'}</ul>
      <button class="btn" type="button" data-fx-nueva>${ic('plus')}Nueva fórmula</button>
      <h3>Variables</h3><ul class="pz-list">${Object.entries(vars).map(([n, vs]) => `<li class="pz-item wg-g-item"><span><b>${esc(n)}</b><small>${vs.map(v => `${v.periodo ? esc(nombreMes(v.periodo)) : 'Todos los meses'}: ${esc(fmt(v.valor, v.unidad))}`).join(' · ')}</small></span>${vs.map(v => `<button class="btn btn-sm" type="button" data-var-borrar="${esc(n)}|${esc(v.periodo)}" title="Quitar ${esc(v.periodo ? nombreMes(v.periodo) : 'el valor general')}">Quitar ${esc(v.periodo ? v.periodo : 'general')}</button>`).join('')}</li>`).join('') || '<li class="pz-vacio">Todavía no hay variables.</li>'}</ul>
      <form class="wg-var" novalidate><label class="fld">Nombre<input name="nombre" maxlength="60" required placeholder="Sueldo base"></label><label class="fld">Tipo<select name="unidad"><option value="dinero">Dinero</option><option value="numero">Número</option><option value="porcentaje">Porcentaje</option></select></label>
        <label class="fld">Valor<input name="valor" type="number" step="any" required></label><label class="fld">Mes<select name="periodo"><option value="">Todos los meses</option><option value="${H().periodo()}">Solo ${esc(nombreMes(H().periodo()))}</option></select></label>
        <button class="btn" type="submit">Guardar variable</button></form>
      <p class="fld-err" role="alert"></p>
      <div class="form-ft"><span class="grow"></span><button class="btn" type="button" data-volver>${volver ? 'Volver' : 'Cerrar'}</button></div></div>`, {wide: true});
    const err = m.querySelector('.fld-err'), otra = () => gestor(volver);
    m.querySelector('[data-volver]').onclick = () => { volver ? volver() : H().closeModal(); };
    m.querySelector('[data-fx-nueva]').onclick = () => editorFormula(null, otra);
    m.querySelectorAll('[data-fx]').forEach(b => b.onclick = () => editorFormula((cat.formulas || []).find(f => f.id === +b.dataset.fx), otra));
    m.querySelectorAll('[data-var-borrar]').forEach(b => b.onclick = async () => { const [nombre, periodo] = b.dataset.varBorrar.split('|'); try { await api('workspace/variables', {method: 'DELETE', body: {nombre, periodo}}); st.cat = null; otra(); } catch (x) { err.textContent = x.message; } });
    m.querySelector('.wg-var').onsubmit = async e => {
      e.preventDefault(); const f = e.target; err.textContent = '';
      try { await api('workspace/variables', {method: 'PUT', body: {nombre: f.nombre.value.trim(), unidad: f.unidad.value, valor: f.valor.value, periodo: f.periodo.value}}); H().toast('Variable guardada.'); st.cat = null; otra(); }
      catch (x) { err.textContent = x.message; }
    };
  }

  window.AgoraWidgets = {montar, editorTablero, editorFormula, gestor, _fmt: fmt};
})();
