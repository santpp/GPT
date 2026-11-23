/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
/* 2.19.0 · Diagnóstico (pedido del usuario). Se carga antes que todo lo demás y anota, solo en este navegador:
   - cada consulta a nuestro servidor (ruta sin datos de búsqueda, estado, tiempo), las últimas 40;
   - cada error con su código del diccionario (AG-ÁREA-NNN) y su referencia, los últimos 20;
   - los fallos de la pantalla (AG-NAV-900) y de conexión (AG-RED-000).
   Con eso, «Reportar un problema» (agora-app.js) manda lo que pasó, y «?debug=1» muestra a los dueños un panel con los tiempos.
   Nunca guarda claves, tokens, códigos de acceso ni el contenido de las respuestas. */
(function () {
  'use strict';
  if (window.AgoraDiag) return;
  const D = window.AgoraDiag = {llamadas: [], errores: [], puede: false, debug: false};
  const corto = (s, n) => String(s == null ? '' : s).replace(/\s+/g, ' ').trim().slice(0, n);
  /* Misma división en áreas que servidor/app/src/Core/Errores.php (para las respuestas que no traen código). */
  D.area = ruta => {
    const r = String(ruta || '').toLowerCase().replace(/^\/+|\/+$/g, '');
    const es = (...p) => p.some(x => r === x || r.startsWith(x));
    if (es('login', 'clave/', 'session', 'logout', 'yo', 'me')) return 'ACC';
    if (es('agency/', 'admin/consumption', 'admin/access-channel', 'admin/integrations')) return 'AGE';
    if (es('workspace/fiscal', 'workspace/facturas', 'workspace/libro-ventas', 'workspace/cobros', 'workspace/tasas', 'facturas', 'cobros')) return 'FAC';
    if (es('workspace/metricas', 'workspace/formulas', 'workspace/variables', 'workspace/tableros', 'workspace/alertas', 'workspace/plantillas')) return 'WID';
    if (es('workspace/ventas', 'ventas', 'workspace/ganadas', 'workspace/won')) return 'VEN';
    if (es('workspace/usuarios', 'usuarios', 'perfil', 'workspace/perfil', 'workspace/permisos', 'workspace/enlaces')) return 'USR';
    if (es('contracts', 'contratos', 'sign', 'registro', 'workspace/contratos')) return 'CON';
    if (es('ghl/', 'workspace/contact', 'workspace/oportunidad', 'workspace/opportunit', 'workspace/tareas', 'workspace/bandeja', 'workspace/conversation', 'workspace/inbox', 'workspace/messages', 'workspace/send', 'workspace/assign', 'workspace/read', 'workspace/record', 'workspace/sync', 'workspace/tags', 'workspace/templates', 'workspace/activity', 'workspace/pipelines', 'workspace/embudos', 'workspace/redes', 'workspace/campanas', 'workspace/self', 'workspace/connections', 'conversations', 'contacts', 'opportunities', 'inbox', 'tasks')) return 'CTA';
    if (es('workspace/', 'admin/')) return 'GES';
    return 'SIS';
  };
  const TIPOS = ['400', '401', '403', '404', '405', '408', '409', '410', '413', '422', '424', '429', '500', '501', '502', '503'];
  D.codigo = (ruta, status) => `AG-${D.area(ruta)}-${TIPOS.includes(String(status)) ? status : status >= 500 ? 500 : 400}`;
  D.error = e => {
    D.errores.push({hora: new Date().toISOString(), codigo: e.codigo, referencia: e.referencia || '', mensaje: corto(e.mensaje, 300), ruta: corto(e.ruta || location.pathname, 160), status: e.status || 0});
    if (D.errores.length > 20) D.errores.shift();
    pintar();
    try { window.dispatchEvent(new Event('agora:error')); } catch {} // 2.23.0: la pantalla pone «Enviar reporte» junto al mensaje
  };
  const rutaDe = url => { try { const u = new URL(url, location.href); return u.origin === location.origin && u.pathname.startsWith('/api/') ? u.pathname.slice(5) : null; } catch { return null; } };
  const original = window.fetch;
  /* 2.19.0 · Las cifras de la cuenta que dos partes de la pantalla piden a la vez se piden una sola vez: se comparte la respuesta
     (cada una recibe su copia) durante la consulta y 2 s después. Menos espera y menos cuota de la cuenta. */
  const PESADAS = /^workspace\/(dashboard|board-month|dia|ads|eficacia|campanas|indicadores)(\?|$)/, enCurso = new Map();
  window.fetch = function (input, init) {
    const ruta = rutaDe(typeof input === 'string' ? input : input && input.url);
    if (ruta === null) return original.apply(this, arguments);
    const metodo0 = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
    if (metodo0 !== 'GET') enCurso.clear();
    const clave = metodo0 === 'GET' && PESADAS.test(ruta) ? canonica(typeof input === 'string' ? input : input.url) : '';
    if (clave) {
      const v = enCurso.get(clave);
      if (v && (v.pendiente || performance.now() - v.fin < 2000)) return v.p.then(r => r.clone());
      const nueva = {pendiente: true, fin: 0, p: null};
      nueva.p = medir.call(this, arguments, ruta, metodo0).then(r => { nueva.pendiente = false; nueva.fin = performance.now(); if (!r.ok) enCurso.delete(clave); return r; }, x => { enCurso.delete(clave); throw x; });
      enCurso.set(clave, nueva);
      return nueva.p.then(r => r.clone());
    }
    return medir.call(this, arguments, ruta, metodo0);
  };
  /* La misma consulta aunque cambie el orden de los datos, falten los vacíos o venga la subcuenta activa (la decide la sesión). */
  function canonica(url) {
    try { const u = new URL(url, location.href); const p = [...u.searchParams].filter(([k, v]) => v !== '' && k !== 'connectionId').sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0);
      return u.pathname + '?' + p.map(([k, v]) => k + '=' + v).join('&'); } catch { return String(url); }
  }
  function medir(args, ruta, metodo) {
    const t0 = performance.now();
    return original.apply(this, args).then(r => {
      const ms = Math.round(performance.now() - t0);
      D.llamadas.push({ruta, metodo, status: r.status, ms, hora: new Date().toISOString()});
      if (D.llamadas.length > 40) D.llamadas.shift();
      if (!r.ok && r.status !== 401) {
        r.clone().json().then(d => D.error({codigo: d && d.codigo || D.codigo(ruta, r.status), referencia: d && d.requestId, mensaje: d && d.error, ruta, status: r.status}))
          .catch(() => D.error({codigo: D.codigo(ruta, r.status), mensaje: 'Respuesta sin detalle', ruta, status: r.status}));
      } else pintar();
      return r;
    }, x => {
      const ms = Math.round(performance.now() - t0);
      D.llamadas.push({ruta, metodo, status: 0, ms, hora: new Date().toISOString()});
      if (D.llamadas.length > 40) D.llamadas.shift();
      if (!(x && x.name === 'AbortError' && ms < 19000)) D.error({codigo: x && x.name === 'AbortError' ? `AG-${D.area(ruta)}-408` : 'AG-RED-000', mensaje: navigator.onLine ? 'No se pudo conectar con el servidor' : 'Sin conexión a internet', ruta, status: 0});
      throw x;
    });
  }
  window.addEventListener('error', e => { if (e && e.message) D.error({codigo: 'AG-NAV-900', mensaje: `${e.message}${e.filename ? ' · ' + String(e.filename).split('/').pop() + ':' + e.lineno : ''}`}); });
  window.addEventListener('unhandledrejection', e => { const m = e && e.reason && (e.reason.message || String(e.reason)); if (m && !/contexto|stale|abort/i.test(m)) D.error({codigo: 'AG-NAV-900', mensaje: m}); });
  /* Lo que va en un reporte: nada de datos de clientes. */
  D.paraReporte = () => ({
    errores: D.errores.slice(-20),
    lentas: D.llamadas.filter(l => l.ms >= 3000 || l.status === 0).slice(-15).map(l => ({ruta: l.ruta, ms: l.ms, status: l.status})),
    pantalla: corto(location.pathname, 160),
    dispositivo: corto(`${innerWidth}×${innerHeight} · ${(navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || ''} · ${Intl.DateTimeFormat().resolvedOptions().timeZone || ''}`, 160),
  });
  /* ?debug=1 lo enciende (queda en esta pestaña), ?debug=0 lo apaga. El panel solo se ve si la app dice que es un dueño (D.puede). */
  try {
    const q = new URLSearchParams(location.search);
    if (q.has('debug')) { if (q.get('debug') === '0') sessionStorage.removeItem('agora.debug'); else sessionStorage.setItem('agora.debug', '1'); }
    D.debug = sessionStorage.getItem('agora.debug') === '1';
  } catch { D.debug = false; }
  let panel = null, cola = 0;
  function pintar() {
    if (!D.debug || !D.puede) { if (panel) { panel.remove(); panel = null; } return; }
    cancelAnimationFrame(cola);
    cola = requestAnimationFrame(() => {
      if (!panel) {
        panel = document.createElement('aside'); panel.id = 'ag-debug'; panel.setAttribute('aria-label', 'Depuración');
        panel.innerHTML = '<header><b>Depuración</b><span></span><button type="button" data-x aria-label="Cerrar depuración">×</button></header><ol></ol><p class="agd-e"></p>';
        panel.querySelector('[data-x]').onclick = () => { try { sessionStorage.removeItem('agora.debug'); } catch {} D.debug = false; pintar(); };
        document.body.appendChild(panel);
      }
      const ult = D.llamadas.slice(-12).reverse();
      const total = D.llamadas.length ? Math.max(...D.llamadas.map(l => l.ms)) : 0;
      panel.querySelector('header span').textContent = `${D.llamadas.length} consultas · la más lenta ${total} ms`;
      const ol = panel.querySelector('ol'); ol.textContent = '';
      ult.forEach(l => {
        const li = document.createElement('li');
        li.className = l.status === 0 || l.status >= 400 ? 'agd-mal' : l.ms >= 3000 ? 'agd-lenta' : l.ms >= 1000 ? 'agd-media' : '';
        const ms = document.createElement('b'); ms.textContent = `${l.ms} ms`;
        const st = document.createElement('i'); st.textContent = l.status || 'sin respuesta';
        const ru = document.createElement('span'); ru.textContent = `${l.metodo} ${l.ruta}`;
        li.append(ms, st, ru); ol.appendChild(li);
      });
      const e = D.errores[D.errores.length - 1];
      panel.querySelector('.agd-e').textContent = e ? `Último error: ${e.codigo}${e.referencia ? ' · ref. ' + e.referencia : ''} · ${e.mensaje}` : 'Sin errores en esta pestaña.';
    });
  }
  D.pintar = pintar;
})();
