/* ---- 00-base.js ---- */
/* 2.1.17 · capas de la demo */

/* ---- 04h-excel-tablas.js ---- */
/* 2.1.17 · 04h-excel-tablas · sin rutas propias: la librería Excel y las tablas dinámicas trabajan en el navegador. */

/* ---- 04i-migracion.js ---- */
/* 2.1.17 · 04i-migracion · rutas de la demo: campos personalizables y carga/descarga masiva.
   Copia en JS de los campos de fábrica de servidor/app/src/Controllers/Migracion.php (mantenerlos iguales).
   Funciona en las dos subcuentas: la 1 escribe en los datos de ejemplo (Contactos, Oportunidades, Tareas,
   Productos); la 2 guarda lo importado en memoria. */
(function () {
  const D = window.__agoraDemo; if (!D) return;
  const L = (o, c) => ({o, c});
  const COBROS = L(['Recurrente', 'Pago único', 'Venta única'], ['recurrente', 'unico', 'venta']);
  const FRECS = L(['Semanal', 'Quincenal', 'Mensual', 'Trimestral', 'Semestral', 'Anual'], ['semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual']);
  const MON = L(['USD', 'VES', 'EUR'], ['USD', 'VES', 'EUR']);
  const BASE = {
    contactos: ['Contactos', 'users', true, 'Las fichas de tus clientes y prospectos.', [['id', 'ID', 'texto', 0, null, 'Déjalo vacío para crear. Con el ID (o el correo) se actualiza la ficha.'], ['nombre', 'Nombre completo', 'texto', 1], ['correo', 'Correo', 'correo', 0], ['telefono', 'Teléfono', 'telefono', 0, null, 'Con código de país, por ejemplo +58 414 1234567.'], ['empresa', 'Empresa', 'texto', 0], ['direccion', 'Dirección', 'texto', 0], ['ciudad', 'Ciudad', 'texto', 0], ['estado', 'Estado o provincia', 'texto', 0], ['pais', 'País', 'texto', 0], ['sitio', 'Sitio web', 'texto', 0], ['etiquetas', 'Etiquetas', 'texto', 0, null, 'Sepáralas con coma.'], ['fuente', 'Origen', 'texto', 0, null, 'Por ejemplo Instagram, Referido.'], ['responsable', 'Responsable', 'correo', 0, null, 'Correo de un usuario de la subcuenta.']]],
    oportunidades: ['Oportunidades', 'trend', true, 'Los negocios de tus embudos.', [['id', 'ID', 'texto', 0], ['nombre', 'Nombre de la oportunidad', 'texto', 1], ['contacto', 'Contacto (correo o ID)', 'texto', 1, null, 'El contacto debe existir: carga primero los contactos.'], ['embudo', 'Embudo', 'texto', 0, null, 'Nombre del embudo. Vacío = el primero.'], ['etapa', 'Etapa', 'texto', 0, null, 'Nombre de la etapa. Vacío = la primera.'], ['valor', 'Valor', 'numero', 0], ['estado', 'Estado', 'lista', 0, L(['Abierta', 'Ganada', 'Perdida', 'Abandonada'], ['open', 'won', 'lost', 'abandoned'])], ['fecha', 'Fecha de creación', 'fecha', 0], ['responsable', 'Responsable', 'correo', 0]]],
    productos: ['Productos y servicios', 'wallet', true, 'Tu catálogo de rubros y productos.', [['id', 'ID', 'texto', 0], ['nombre', 'Nombre', 'texto', 1], ['codigo', 'Código', 'texto', 0], ['rubro', 'Rubro', 'texto', 0, null, 'Si no existe, se crea.'], ['tipo', 'Tipo', 'lista', 0, L(['Servicio', 'Producto'], ['servicio', 'producto'])], ['cobro', 'Forma de cobro', 'lista', 1, COBROS], ['precio', 'Precio', 'numero', 1], ['moneda', 'Moneda', 'lista', 0, MON], ['frecuencia', 'Frecuencia', 'lista', 0, FRECS, 'Solo si es recurrente.'], ['duracion', 'Número de cobros', 'numero', 0], ['existencia', 'Existencia', 'numero', 0], ['descripcion', 'Descripción', 'texto', 0], ['activo', 'Activo', 'si_no', 0]]],
    ventas: ['Ventas', 'coins', true, 'Las ventas ganadas, con su cobro.', [['id', 'ID', 'texto', 0], ['fecha', 'Fecha', 'fecha', 1], ['cliente', 'Cliente', 'texto', 1], ['documento', 'Cédula o RIF', 'texto', 0], ['correo', 'Correo', 'correo', 0], ['telefono', 'Teléfono', 'telefono', 0], ['producto', 'Producto', 'texto', 0], ['monto', 'Monto', 'numero', 1], ['moneda', 'Moneda', 'lista', 0, MON], ['cobro', 'Forma de cobro', 'lista', 0, COBROS], ['frecuencia', 'Frecuencia', 'lista', 0, FRECS], ['cuotas', 'Cuotas', 'numero', 0], ['fechaInicio', 'Fecha de inicio', 'fecha', 0], ['vendedor', 'Vendedor', 'texto', 0], ['oportunidadId', 'ID de la oportunidad', 'texto', 0]]],
    tareas: ['Tareas', 'check', true, 'Los pendientes del equipo.', [['id', 'ID', 'texto', 0], ['titulo', 'Tarea', 'texto', 1], ['detalle', 'Detalle', 'texto', 0], ['vence', 'Fecha límite', 'fecha', 0], ['hora', 'Hora', 'texto', 0, null, 'Formato 24 h, por ejemplo 14:30.'], ['estado', 'Estado', 'lista', 0, L(['Pendiente', 'Hecha'], ['pendiente', 'hecha'])], ['responsable', 'Responsable', 'correo', 0], ['contacto', 'Contacto (correo o ID)', 'texto', 0]]],
    usuarios: ['Usuarios', 'team', false, 'Solo descarga: los usuarios se crean desde Usuarios y roles.', [['nombre', 'Nombre', 'texto', 1], ['correo', 'Correo', 'correo', 1], ['rol', 'Rol', 'texto', 0], ['estado', 'Estado', 'texto', 0], ['ultimoAcceso', 'Último acceso', 'texto', 0]]],
  };
  const guardados = {}, propios = {}, nid = p => p + Math.random().toString(36).slice(2, 10);
  const t = () => D.tenant() || 1;
  const puede = () => ['MASTER', 'SUPERADMIN', 'ADMIN'].includes(D.yo()?.rol);
  const norm = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
  const def = m => BASE[m];
  const base = m => def(m)[4].map(([clave, titulo, tipo, req, l, ayuda], i) => ({clave, titulo, tituloBase: titulo, tipo, opciones: l ? l.o : null, orden: i, visible: true, requerido: !!req, bloqueado: !!req, personalizado: false, ayuda: ayuda || ''}));
  const codigos = m => Object.fromEntries(def(m)[4].filter(x => x[4]).map(x => [x[0], x[4]]));
  function campos(m) {
    const g = Object.fromEntries((guardados[t() + m] || []).map(c => [c.clave, c]));
    const out = base(m).map(c => { const x = g[c.clave]; if (!x) return c; return {...c, titulo: x.titulo || c.titulo, orden: x.orden ?? c.orden, visible: c.bloqueado ? true : x.visible !== false, requerido: c.bloqueado ? true : !!x.requerido}; });
    for (const x of Object.values(g)) if (x.personalizado) out.push({...x, tituloBase: null, bloqueado: false, ayuda: ''});
    return out.sort((a, b) => a.orden - b.orden).map((c, i) => ({...c, orden: i}));
  }
  const info = m => ({modulo: m, nombre: def(m)[0], descripcion: def(m)[3], importa: def(m)[2], campos: campos(m), canEdit: puede(), actualizado: guardados['f' + t() + m] || null, por: null});
  const cols = m => campos(m).filter(c => c.visible).map(c => ({clave: c.clave, titulo: c.titulo, tipo: c.tipo, requerido: c.requerido, opciones: c.opciones, ayuda: c.ayuda}));
  /* Almacenes por subcuenta */
  const mem = {};
  const store = (k, init) => (mem[t() + k] ||= init());
  const contactos = () => t() === 1 ? D.contactosAg?.() || [] : store('c', () => []);
  const oportunidades = () => t() === 1 ? D.oportunidades() : store('o', () => []);
  const tareas = () => t() === 1 ? D.tareas() : store('t', () => []);
  const ventas = () => (typeof D.ventas === 'function' && t() === 1 ? D.ventas() : null) || store('v', () => []);
  const catalogo = () => { const p = D.productos(); return (p[t()] ||= {rubros: [], productos: []}); };
  const embudos = () => t() === 1 ? D.embudos() : [{id: 'pl_demo2', name: 'Ventas', stages: [{id: 'st_a', name: 'Nuevo'}, {id: 'st_b', name: 'En conversación'}, {id: 'st_c', name: 'Cierre'}]}];
  const equipo = () => (D.usuariosSistema?.() || []).map(u => ({id: u.id, name: u.nombre, email: u.correo}));
  const cfMapa = cf => Array.isArray(cf) ? Object.fromEntries(cf.map(f => [f.id ?? f.clave, f.value ?? f.valor])) : (cf || {});
  function fila(m, v, cf) {
    const cod = codigos(m), c2 = cfMapa(cf), o = {};
    for (const c of campos(m)) {
      if (!c.visible) continue; let x = c.personalizado ? c2[c.clave] : v[c.clave];
      if (cod[c.clave] && typeof x === 'string') { const i = cod[c.clave].c.indexOf(x); if (i >= 0) x = cod[c.clave].o[i]; }
      if (typeof x === 'boolean') x = x ? 'Sí' : 'No'; if (Array.isArray(x)) x = x.join(', ');
      o[c.clave] = x ?? '';
    }
    return o;
  }
  const correoContacto = id => { const c = contactos().find(x => x.id === id); return c ? c.email || c.name : id || ''; };
  function exportar(m) {
    if (m === 'contactos') return contactos().map(x => fila(m, {id: x.id, nombre: x.name, correo: x.email, telefono: x.phone, empresa: x.companyName, direccion: x.address1, ciudad: x.city, estado: x.state, pais: x.country, sitio: x.website, etiquetas: x.tags, fuente: x.source, responsable: equipo().find(u => u.id === x.assignedTo)?.email || ''}, x.customFields));
    if (m === 'oportunidades') return oportunidades().map(x => { const p = embudos().find(e => e.id === x.pipelineId); return fila(m, {id: x.id, nombre: x.name, contacto: x.contactId ? correoContacto(x.contactId) : x.contactName, embudo: p?.name || '', etapa: p?.stages.find(s => s.id === x.pipelineStageId)?.name || '', valor: x.monetaryValue, estado: x.status, fecha: String(x.dateAdded || x.wonAt || '').slice(0, 10), responsable: equipo().find(u => u.id === x.assignedTo)?.email || ''}, x.customFields); });
    if (m === 'tareas') return tareas().map(x => fila(m, {...x, contacto: x.contactId ? correoContacto(x.contactId) : x.contacto || '', responsable: equipo().find(u => u.name === x.responsable)?.email || x.responsable || ''}, x.customFields));
    if (m === 'ventas') return ventas().map(x => fila(m, {...x, cliente: typeof x.cliente === 'object' && x.cliente ? x.cliente.nombre || '' : x.cliente}, x.customFields));
    if (m === 'productos') { const c = catalogo(); return c.productos.map(p => fila(m, {...p, rubro: c.rubros.find(r => r.id === p.rubroId)?.nombre || '', existencia: p.stock}, p.customFields)); }
    if (m === 'usuarios') return (t() === 1 ? D.usuariosSistema?.() || [] : []).map(u => fila(m, {nombre: u.nombre, correo: u.correo, rol: u.rol, estado: u.estado === 'activo' ? 'Activo' : 'Inactivo', ultimoAcceso: u.ultimoAcceso || ''}, null));
    return [];
  }
  function importar(m, filas, modo, desde) {
    const V = window.AgoraMigracion; if (!V) return D.err('La demo aún no terminó de cargar.', 503);
    const cs = campos(m), cod = codigos(m), res = {creados: 0, actualizados: 0, errores: [], filas: filas.length, simulado: modo === 'simular'};
    const sim = modo === 'simular', falla = (n, campo, mensaje) => res.errores.push({fila: n, campo, mensaje});
    const tit = Object.fromEntries(cs.map(c => [c.clave, c.titulo]));
    filas.forEach((f, i) => {
      const n = desde + i, v = {}, cf = []; let mal = false;
      for (const c of cs) {
        if (!(c.clave in f)) { if (c.requerido && c.visible) { falla(n, c.titulo, 'Es obligatorio.'); mal = true; } continue; }
        const c2 = cod[c.clave] ? {...c, opciones: c.opciones} : c;
        let [val, e] = V.celda(c2, f[c.clave]);
        if (!e && val != null && cod[c.clave]) { let k = cod[c.clave].o.indexOf(val); if (k < 0) k = cod[c.clave].c.findIndex(x => norm(x) === norm(f[c.clave])); val = cod[c.clave].c[k]; }
        if (e && cod[c.clave]) { const k = cod[c.clave].c.findIndex(x => norm(x) === norm(f[c.clave])); if (k >= 0) { val = cod[c.clave].c[k]; e = null; } }
        if (e) { falla(n, c.titulo, e); mal = true; continue; }
        if (val == null) continue;
        if (c.personalizado) cf.push({id: c.clave, name: c.titulo, value: val}); else v[c.clave] = val;
      }
      if (v.id && !/^[A-Za-z0-9_-]{1,80}$/.test(v.id)) { falla(n, 'ID', 'Usa solo letras, números, guion y guion bajo (hasta 80).'); mal = true; }
      if (mal) return;
      const mezclar = (ant, nuevos) => { const o = Object.fromEntries((Array.isArray(ant) ? ant : []).map(x => [x.id, x])); nuevos.forEach(x => { o[x.id] = x; }); return Object.values(o); };
      const quien = v.responsable ? equipo().find(u => (u.email || '').toLowerCase() === v.responsable) : null;
      if (v.responsable && !quien) { falla(n, tit.responsable, 'No hay un usuario activo con ese correo en esta subcuenta.'); return; }
      const buscarC = x => contactos().find(c => c.id === x || (c.email || '').toLowerCase() === String(x).toLowerCase());
      const lista = {contactos, oportunidades, tareas, ventas}[m];
      if (m === 'productos') {
        const cat = catalogo(); let p = (v.id && cat.productos.find(x => x.id === v.id)) || (modo !== 'crear' && ((v.codigo && cat.productos.find(x => (x.codigo || '').toUpperCase() === String(v.codigo).toUpperCase())) || (v.nombre && cat.productos.find(x => norm(x.nombre) === norm(v.nombre)))));
        if (p && modo === 'crear') return falla(n, tit.id, 'Ya existe un producto con este ID. Elige «Crear y actualizar».');
        const nuevo = {id: v.id || nid('p-'), rubroId: '', tipo: 'servicio', moneda: 'USD', activo: true, descripcion: '', codigo: '', ...(p || {})};
        for (const k of ['nombre', 'tipo', 'cobro', 'precio', 'moneda', 'frecuencia', 'descripcion', 'activo']) if (k in v) nuevo[k] = v[k];
        if ('codigo' in v) { nuevo.codigo = String(v.codigo).toUpperCase(); if (!/^[A-Z0-9._-]{1,30}$/.test(nuevo.codigo)) return falla(n, tit.codigo, 'Solo letras, números, punto, guion y guion bajo (hasta 30).'); }
        if (!nuevo.cobro) return falla(n, tit.cobro, 'Es obligatorio.');
        if ('duracion' in v) nuevo.duracion = v.duracion; if ('existencia' in v) nuevo.stock = v.existencia;
        nuevo.frecuencia = nuevo.cobro === 'recurrente' ? nuevo.frecuencia || 'mensual' : null;
        if (v.rubro) { let r = cat.rubros.find(x => norm(x.nombre) === norm(v.rubro)); if (!r && !sim) { r = {id: nid('r-'), nombre: v.rubro, descripcion: ''}; cat.rubros.push(r); } if (r) nuevo.rubroId = r.id; }
        if (cf.length) nuevo.customFields = mezclar(nuevo.customFields, cf);
        p ? res.actualizados++ : res.creados++;
        if (!sim) { if (p) Object.assign(p, nuevo); else cat.productos.push(nuevo); }
        return;
      }
      let x = v.id ? lista().find(r => r.id === v.id) : null;
      if (!x && m === 'contactos' && modo !== 'crear' && v.correo) x = buscarC(v.correo);
      if (x && modo === 'crear') return falla(n, tit.id || 'ID', 'Ya existe un registro con este ID. Elige «Crear y actualizar» para actualizarlo.');
      const d = {...(x || {}), id: x?.id || v.id || nid(m.slice(0, 2) + '_')};
      if (m === 'contactos') {
        const mp = {nombre: 'name', correo: 'email', telefono: 'phone', empresa: 'companyName', direccion: 'address1', ciudad: 'city', estado: 'state', pais: 'country', sitio: 'website', fuente: 'source'};
        for (const [k, r] of Object.entries(mp)) if (k in v) d[r] = v[k];
        if ('etiquetas' in v) d.tags = [...new Set(String(v.etiquetas).split(',').map(s => s.trim()).filter(Boolean))];
        d.tags ||= []; if (quien) { d.assignedTo = quien.id; d.assignedName = quien.name; }
      } else if (m === 'oportunidades') {
        if ('contacto' in v) { const c = buscarC(v.contacto); if (!c) return falla(n, tit.contacto, 'No existe un contacto con ese correo o ID. Carga primero los contactos.'); d.contactId = c.id; d.contactName = c.name; }
        const ps = embudos(); let p = 'embudo' in v ? ps.find(e => e.id === v.embudo || norm(e.name) === norm(v.embudo)) : ps.find(e => e.id === d.pipelineId) || ps[0];
        if (!p) return falla(n, tit.embudo, 'No existe un embudo con ese nombre.');
        let st = 'etapa' in v ? p.stages.find(s => s.id === v.etapa || norm(s.name) === norm(v.etapa))?.id : (p.stages.some(s => s.id === d.pipelineStageId) ? d.pipelineStageId : p.stages[0]?.id);
        if (!st) return falla(n, tit.etapa, `Esa etapa no existe en el embudo «${p.name}».`);
        Object.assign(d, {pipelineId: p.id, pipelineStageId: st});
        if ('nombre' in v) d.name = v.nombre; if ('valor' in v) d.monetaryValue = Math.round(v.valor * 100) / 100; d.monetaryValue ??= 0;
        if ('estado' in v) d.status = v.estado; d.status ||= 'open'; if ('fecha' in v) d.dateAdded = v.fecha + 'T12:00:00Z';
        if (d.status === 'won' && !d.wonAt) d.wonAt = new Date().toISOString();
        if (quien) { d.assignedTo = quien.id; d.assignedName = quien.name; }
      } else if (m === 'tareas') {
        if ('hora' in v && !/^([01]?\d|2[0-3]):[0-5]\d$/.test(v.hora)) return falla(n, tit.hora, 'Usa el formato 24 h, por ejemplo 14:30.');
        for (const k of ['titulo', 'detalle', 'vence', 'hora', 'estado']) if (k in v) d[k] = v[k];
        if ('contacto' in v) { const c = buscarC(v.contacto); if (!c) return falla(n, tit.contacto, 'No existe un contacto con ese correo o ID.'); d.contactId = c.id; d.contacto = c.name; }
        d.estado ||= 'pendiente'; d.origen ||= 'migracion'; d.responsable = quien ? quien.name : d.responsable || D.yo()?.nombre || '';
      } else {
        for (const [k, val] of Object.entries(v)) if (k !== 'id') d[k] = val; d.moneda ||= 'USD'; d.origen ||= 'migracion';
        // 2.1.17: la venta ganada guarda el cliente como objeto; se conserva esa forma.
        if (typeof d.cliente === 'string') d.cliente = {...(x && typeof x.cliente === 'object' ? x.cliente : {documento: d.documento || '', correo: d.correo || '', telefono: d.telefono || '', direccion: '', empresa: ''}), nombre: d.cliente};
        d.estado ||= 'pagada'; d.plan ||= [];
      }
      if (cf.length) d.customFields = mezclar(d.customFields, cf);
      x ? res.actualizados++ : res.creados++;
      if (!sim) { if (x) Object.assign(x, d); else lista().push(d); }
    });
    if (!sim && (res.creados || res.actualizados)) D.auditoria?.()?.unshift?.({id: nid('a'), at: new Date().toISOString(), actor: D.yo()?.nombre, rol: 'SuperAdmin', accion: 'migracion.importada', accionTexto: 'Importó datos desde Excel', modulo: 'migracion', objeto: `${def(m)[0]} · ${res.creados} creados, ${res.actualizados} actualizados`, dispositivo: 'Navegador', ip: '127.0.0.1', origen: 'sistema'});
    return D.ok(res);
  }
  D.RUTAS.unshift(
    ['GET', /^workspace\/campos$/, (h, b, q) => {
      const m = q.get('modulo');
      if (!m) return D.ok({modulos: Object.entries(BASE).map(([id, d]) => ({id, nombre: d[0], icono: d[1], descripcion: d[3], importa: d[2]})), canEdit: puede()});
      return def(m) ? D.ok(info(m)) : D.err('Módulo de datos desconocido.', 404);
    }],
    ['PUT', /^workspace\/campos$/, (h, b) => {
      if (!puede()) return D.err('Requiere administración.', 403);
      const m = b.modulo; if (!def(m)) return D.err('Módulo de datos desconocido.', 404);
      if (b.restaurar) { delete guardados[t() + m]; guardados['f' + t() + m] = new Date().toISOString(); return D.ok({...info(m), ok: true}); }
      if (!Array.isArray(b.campos) || b.campos.length > 80) return D.err('Lista de campos inválida (hasta 80).');
      const vistos = {}; for (const c of b.campos) { const k = norm(c.titulo); if (!k) return D.err('Cada campo necesita un nombre con letras o números.'); if (vistos[k]) return D.err(`Dos campos se llaman «${c.titulo}». Usa nombres distintos para poder reconocer las columnas del Excel.`); vistos[k] = 1; if (c.personalizado && !/^cf_[a-z0-9_]{1,36}$/.test(c.clave)) return D.err('Campo inválido.'); if (c.tipo === 'lista' && c.personalizado && !(c.opciones || []).length) return D.err(`Escribe entre 1 y 50 opciones para «${c.titulo}».`); }
      guardados[t() + m] = b.campos.map((c, i) => ({...c, titulo: String(c.titulo).trim().slice(0, 60), orden: c.orden ?? i}));
      guardados['f' + t() + m] = new Date().toISOString();
      return D.ok({...info(m), ok: true});
    }],
    ['GET', /^workspace\/migracion\/plantilla$/, (h, b, q) => {
      const m = q.get('modulo'); if (!def(m)) return D.err('Módulo de datos desconocido.', 404);
      const ej = {texto: 'Texto', numero: 100, fecha: new Date().toISOString().slice(0, 10), correo: 'nombre@ejemplo.com', telefono: '+58 414 1234567', si_no: 'Sí'};
      return D.ok({modulo: m, nombre: def(m)[0], importa: def(m)[2], columnas: cols(m), ejemplo: [Object.fromEntries(cols(m).map(c => [c.clave, c.clave === 'id' ? '' : c.tipo === 'lista' ? c.opciones?.[0] || '' : ej[c.tipo] ?? '']))]});
    }],
    ['GET', /^workspace\/migracion\/exportar$/, (h, b, q) => {
      const m = q.get('modulo'); if (!def(m)) return D.err('Módulo de datos desconocido.', 404);
      if (!puede()) return D.err('Requiere administración.', 403);
      const filas = exportar(m); return D.ok({modulo: m, nombre: def(m)[0], columnas: cols(m), filas, total: filas.length, siguiente: null});
    }],
    ['POST', /^workspace\/migracion\/importar$/, (h, b) => {
      /* 2.1.19: «Agregar manual» (una fila, modo crear) basta con poder editar; la carga masiva es de administración. */
      const manual = b.manual === true && b.modo === 'crear' && Array.isArray(b.filas) && b.filas.length === 1 && ['contactos', 'oportunidades', 'ventas', 'tareas'].includes(b.modulo);
      if (!manual && !puede()) return D.err('Requiere administración.', 403);
      const m = b.modulo; if (!def(m)) return D.err('Módulo de datos desconocido.', 404); if (!def(m)[2]) return D.err('Este módulo es solo de descarga.');
      if (!['crear', 'actualizar', 'simular'].includes(b.modo)) return D.err('Modo de importación inválido.');
      if (!Array.isArray(b.filas) || b.filas.length > 500) return D.err('Envía hasta 500 filas por lote.');
      return importar(m, b.filas, b.modo, Number(b.desde) || 2);
    }],
  );
  D.libres.push(/^workspace\/(campos|migracion)/);
})();

/* ---- 04j-venta-ganada.js ---- */
/* 2.1.17 · 04j-venta-ganada · rutas de la demo (en memoria), equivalentes a Controllers/Ventas.php.
   Flujo completo en prueba.html: ganar oportunidad → formulario → la venta aparece en Ventas,
   en la ficha del contacto, en Tareas, en Oportunidades → Ganadas y en Facturación. */
(() => {
  const D = window.__agoraDemo; if (!D) return;
  const ventas = {};                                  // por subcuenta: lista de registros venta
  const lista = () => (ventas[D.tenant()] ??= []);
  D.ventas = () => lista();   // 2.1.17: Migración de datos lee y escribe estas mismas ventas
  const hoy = () => new Date().toISOString().slice(0, 10);
  const nid = p => p + Math.random().toString(36).slice(2, 10);
  const fail = (m, s = 422) => D.err ? D.err(m, s) : {status: s, body: {error: m}};
  /* Las oportunidades de la demo no traen contactId: se enlazan por nombre con su contacto. */
  const enlazar = () => { const cs = D.contactos(); for (const o of D.oportunidades()) if (!o.contactId) { const c = cs.find(x => x.name === o.contactName); if (c) o.contactId = c.id; } return D.oportunidades(); };
  const plan = (cobro, monto, frec, cuotas, inicio, dia) => {
    const n = cobro === 'venta' ? 1 : Math.max(1, cuotas || 12);
    let m = Array(n).fill(Math.round(monto * 100) / 100);
    if (cobro === 'unico' && n > 1) { const b = Math.floor(monto / n * 100) / 100; m = Array(n).fill(b); m[n - 1] = Math.round((monto - b * (n - 1)) * 100) / 100; }
    const meses = {mensual: 1, trimestral: 3, semestral: 6, anual: 12}[frec], dias = {semanal: 7, quincenal: 15}[frec];
    const [y, mo, d] = inicio.split('-').map(Number), out = [];
    for (let i = 0; i < n; i++) {
      let f = inicio;
      if (i && dias) f = new Date(Date.UTC(y, mo - 1, d + dias * i)).toISOString().slice(0, 10);
      else if (i) { const t = mo - 1 + (meses || 1) * i, yy = y + Math.floor(t / 12), mm = t % 12 + 1, fin = new Date(Date.UTC(yy, mm, 0)).getUTCDate(); f = `${yy}-${String(mm).padStart(2, '0')}-${String(Math.min(dia || d, fin)).padStart(2, '0')}`; }
      out.push({n: i + 1, fecha: f, monto: m[i], estado: 'pendiente'});
    }
    return out;
  };
  const estado = v => { const p = (v.plan || []).filter(c => c.estado !== 'pagado'); if (!p.length && !v.sinFin) return 'pagada'; return p.some(c => c.fecha < hoy()) ? 'vencida' : 'al_dia'; };
  const catalogo = () => D.productos()[D.tenant()] || {rubros: [], productos: []};
  const equipo = () => (D.equipo ? D.equipo() : []);
  const puedeEscribir = () => !['USUARIO', 'CONSULTA', 'VISOR'].includes(D.yo().rol);
  const administra = () => ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR'].includes(D.yo().rol);
  const nombreVend = id => equipo().find(u => String(u.id) === String(id))?.name || D.yo().nombre;

  function leer(b, antes, ownerActual) {
    const c = b.cliente || {};
    if (!c.nombre || String(c.nombre).trim().length < 1) return {error: 'Revisa el campo nombre.'};
    if (c.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.correo)) return {error: 'Revisa el correo del cliente.'};
    const cat = catalogo(), p = cat.productos.find(x => x.id === b.productoId);
    if (!p) return {error: 'Ese producto no está en el catálogo de esta subcuenta.'};
    const cobro = b.cobro || p.cobro; if (!['recurrente', 'unico', 'venta'].includes(cobro)) return {error: 'Elige el sistema de cobro.'};
    const monto = Number(b.monto); if (!(monto > 0) || monto > 1e8) return {error: 'El monto debe ser mayor que cero.'};
    if (!/^\d{4}-\d{2}-\d{2}$/.test(b.fechaInicio || '')) return {error: 'Revisa la fecha: fecha de inicio.'};
    const cuotas = cobro === 'venta' ? 1 : cobro === 'unico' ? Math.max(1, Number(b.cuotas) || 1) : (b.cuotas == null || b.cuotas === '' ? null : Number(b.cuotas));
    const frecuencia = cobro === 'recurrente' || (cobro === 'unico' && cuotas > 1) ? (b.frecuencia || 'mensual') : null;
    const cantidad = cobro === 'venta' ? Math.max(1, Number(b.cantidad) || 1) : 1;
    const vid = b.vendedorId || ownerActual;
    if (String(vid) !== String(ownerActual) && !administra()) return {error: 'Solo supervisión o administración puede cambiar el vendedor.', status: 403};
    const pl = plan(cobro, monto, frecuencia, cobro === 'recurrente' ? (cuotas ?? 12) : cuotas, b.fechaInicio, b.diaCobro ? Number(b.diaCobro) : null);
    return {p, cat, pl, datos: {cliente: {nombre: c.nombre, documento: c.documento || '', correo: c.correo || '', telefono: c.telefono || '', direccion: c.direccion || '', empresa: c.empresa || ''},
      documento: c.documento || '', correo: c.correo || '', telefono: c.telefono || '', productoId: p.id, producto: p.nombre, codigo: p.codigo || '',
      rubro: cat.rubros.find(r => r.id === p.rubroId)?.nombre || '', cantidad, monto: Math.round(monto * 100) / 100, moneda: b.moneda || p.moneda || 'USD', cobro, frecuencia, cuotas,
      fechaInicio: b.fechaInicio, diaCobro: b.diaCobro ? Number(b.diaCobro) : null, vendedorId: vid, vendedor: nombreVend(vid), notas: b.notas || '', customFields: Array.isArray(b.customFields) ? b.customFields : [],
      fecha: b.fecha || antes?.fecha || hoy(), plan: pl, total: Math.round(pl.reduce((t, x) => t + x.monto, 0) * 100) / 100, sinFin: cobro === 'recurrente' && cuotas == null}};
  }
  function stock(cat, pid, delta) {
    const p = cat.productos.find(x => x.id === pid);
    if (!p || p.cobro !== 'venta' || p.stock == null || !delta) return null;
    if (p.stock + delta < 0) return `No hay existencia suficiente de «${p.nombre}» (quedan ${p.stock}).`;
    p.stock += delta; return null;
  }
  function ecosistema(v, op) {
    Object.assign(op, {status: 'won', monetaryValue: v.monto, wonAt: op.wonAt || new Date().toISOString(), ventaId: v.id, ventaPendiente: false});
    if (String(v.vendedorId) !== String(op.assignedTo)) { op.assignedTo = v.vendedorId; op.assignedName = v.vendedor; }
    const ct = D.contactos().find(x => x.id === v.contactoId);
    if (ct) {
      const c = v.cliente; if (c.nombre) ct.name = c.nombre;
      for (const [k, s] of [['email', 'correo'], ['phone', 'telefono'], ['companyName', 'empresa'], ['address1', 'direccion'], ['documento', 'documento']]) if (c[s]) ct[k] = c[s];
      ct.tags = [...new Set([...(ct.tags || []), 'cliente'])];
      const cf = Array.isArray(ct.customFields) ? ct.customFields : [];
      for (const x of [{clave: 'producto', nombre: 'Producto contratado', valor: v.producto}, ...(c.documento ? [{clave: 'documento', nombre: 'Cédula o RIF', valor: c.documento}] : []), ...v.customFields]) { const id = 'venta.' + x.clave, i = cf.findIndex(f => f.id === id), item = {id, name: x.nombre, value: x.valor, folder: 'Venta'}; if (i >= 0) cf[i] = item; else cf.push(item); }
      ct.customFields = cf; ct.clienteDesde ??= v.fecha;
    }
  }
  function tareas(v) {
    const nueva = (titulo, detalle, vence, tipo, n) => { const t = {id: nid('tk_v'), titulo, detalle, responsable: v.vendedor, vence, hora: '', estado: 'pendiente', origen: 'venta', contacto: v.cliente.nombre, contactId: v.contactoId, conversationId: null, ventaId: v.id, oportunidadId: v.oportunidadId}; D.setTareas([t, ...D.tareas()]); return {id: t.id, tipo, n}; };
    const out = [nueva('Dar la bienvenida a ' + v.cliente.nombre, `Venta de ${v.producto}. Confirma datos, accesos y fecha de inicio.`, hoy(), 'bienvenida', null)];
    const lim = new Date(Date.now() + 31 * 864e5).toISOString().slice(0, 10);
    v.plan.forEach((c, i) => { if (i > 0 && c.fecha > lim) return; out.push(nueva(`Cobrar ${v.producto} a ${v.cliente.nombre}${v.plan.length > 1 ? ` (cobro ${c.n}${v.sinFin ? '' : ' de ' + v.plan.length})` : ''}`, `${c.monto} ${v.moneda}`, c.fecha < hoy() ? hoy() : c.fecha, 'cobro', c.n)); });
    return out;
  }
  const audit = (accion, objeto) => { try { D.auditoria().unshift({id: 'av' + Date.now(), at: new Date().toISOString(), actor: D.yo().nombre, rol: D.yo().rol, accion, accionTexto: {'venta.ganada': 'Registró una venta', 'venta.editada': 'Editó una venta', 'venta.cobro.pagado': 'Marcó un cobro pagado', 'venta.cobro.pendiente': 'Marcó un cobro pendiente', 'venta.pendiente': 'Dejó una venta por completar'}[accion] || accion, modulo: 'ventas', objeto}); } catch { /* opcional */ } };

  D.RUTAS.unshift(
    /* Ganar desde la Bandeja creando una oportunidad nueva: la demo de base no la guardaba. */
    ['POST', /^workspace\/opportunity\/new$/, (m, b, q) => {
      const o = {id: nid('op_'), name: b.name || 'Venta', contactId: q.get('contactId') || '', contactName: D.contactos().find(c => c.id === q.get('contactId'))?.name || '', monetaryValue: Number(b.monetaryValue) || 0, pipelineId: b.pipelineId || 'pl_1', pipelineStageId: b.pipelineStageId || 'st_1', assignedTo: 'us_01', assignedName: nombreVend('us_01'), status: b.status || 'open', wonAt: b.status === 'won' ? new Date().toISOString() : null};
      D.setOportunidades([o, ...D.oportunidades()]);
      return D.ok({ok: true, opportunity: o});
    }],
    ['GET', /^workspace\/ventas\/contexto$/, (m, b, q) => {
      const ops = enlazar(); let venta = q.get('ventaId') ? lista().find(v => v.id === q.get('ventaId')) : null;
      if (q.get('ventaId') && !venta) return fail('Registro no encontrado en este espacio.', 404);
      const op = ops.find(o => o.id === (venta ? venta.oportunidadId : q.get('oportunidadId')));
      if (!op) return fail('Registro no encontrado en este espacio.', 404);
      venta ??= op.ventaId ? lista().find(v => v.id === op.ventaId) : null;
      const yo = D.yo();
      return D.ok({oportunidad: {...op, ownerId: op.assignedTo}, contacto: D.contactos().find(c => c.id === op.contactId) || null, venta: venta || null, catalogo: catalogo(),
        perfil: (D.perfiles ? D.perfiles()[D.tenant()] : null) || null, vendedores: equipo().map(u => ({id: u.id, name: u.name})), yo: yo.id, canEdit: puedeEscribir(), puedeCambiarVendedor: administra()});
    }],
    ['GET', /^workspace\/ventas$/, (m, b, q) => {
      const mes = q.get('mes') || '', est = q.get('estado') || '', ct = q.get('contactoId') || '', opq = q.get('oportunidadId') || '';
      const todas = lista().map(v => ({...v, estado: estado(v)}));
      const items = todas.filter(v => (!mes || v.fecha.slice(0, 7) === mes) && (!est || v.estado === est) && (!ct || v.contactoId === ct) && (!opq || v.oportunidadId === opq));
      const ym = mes || hoy().slice(0, 7), lim = new Date(Date.now() + 31 * 864e5).toISOString().slice(0, 10);
      const res = {mes: ym, cantidad: 0, vendido: {}, cobrado: {}, porCobrar: {}, vencido: {}}, prox = [];
      const suma = (o, k, n) => { o[k] = Math.round(((o[k] || 0) + n) * 100) / 100; };
      for (const v of todas) {
        if (v.fecha.slice(0, 7) === ym) { res.cantidad++; suma(res.vendido, v.moneda, v.monto); }
        for (const c of v.plan) {
          const pag = c.estado === 'pagado';
          if (c.fecha.slice(0, 7) === ym) suma(pag ? res.cobrado : res.porCobrar, v.moneda, c.monto);
          if (!pag && c.fecha < hoy()) suma(res.vencido, v.moneda, c.monto);
          if (!pag && c.fecha <= lim) prox.push({ventaId: v.id, cliente: v.cliente.nombre, producto: v.producto, n: c.n, de: v.plan.length, sinFin: v.sinFin, fecha: c.fecha, monto: c.monto, moneda: v.moneda, vencido: c.fecha < hoy(), vendedor: v.vendedor});
        }
      }
      prox.sort((a, b) => a.fecha.localeCompare(b.fecha));
      const pendientes = D.tenant() === 1 ? enlazar().filter(o => o.status === 'won' && o.ventaPendiente && !o.ventaId).map(o => ({id: o.id, name: o.name, contactId: o.contactId, monetaryValue: o.monetaryValue, wonAt: o.wonAt})) : [];
      return D.ok({items, total: items.length, resumen: res, proximos: prox.slice(0, 60), vinculos: todas.map(v => ({ventaId: v.id, oportunidadId: v.oportunidadId, contactoId: v.contactoId, estado: v.estado})), pendientes, canEdit: puedeEscribir(), restricted: false});
    }],
    ['POST', /^workspace\/ventas$/, (m, b) => {
      if (!puedeEscribir()) return fail('Tu rol es de consulta.', 403);
      const op = enlazar().find(o => o.id === b.oportunidadId); if (!op) return fail('Registro no encontrado en este espacio.', 404);
      if (op.ventaId) return fail('Esta oportunidad ya tiene una venta registrada. Edítala desde Ventas.', 409);
      const r = leer(b, null, op.assignedTo); if (r.error) return fail(r.error, r.status || 422);
      const e = r.datos.cobro === 'venta' ? stock(r.cat, r.p.id, -r.datos.cantidad) : null; if (e) return fail(e);
      const v = {id: nid('vt_'), creada: new Date().toISOString(), oportunidadId: op.id, oportunidad: op.name, contactoId: op.contactId || '', registradaPor: D.yo().nombre, ...r.datos};
      v.tareas = tareas(v); v.estado = estado(v); v.version = 1;
      lista().unshift(v); ecosistema(v, op); audit('venta.ganada', v.id);
      return D.ok({ok: true, venta: v});
    }],
    ['PUT', /^workspace\/ventas$/, (m, b) => {
      if (!puedeEscribir()) return fail('Tu rol es de consulta.', 403);
      const v = lista().find(x => x.id === b.id); if (!v) return fail('Registro no encontrado en este espacio.', 404);
      const r = leer(b, v, v.vendedorId); if (r.error) return fail(r.error, r.status || 422);
      if (v.cobro === 'venta') stock(r.cat, v.productoId, v.cantidad);
      const e = r.datos.cobro === 'venta' ? stock(r.cat, r.p.id, -r.datos.cantidad) : null; if (e) { if (v.cobro === 'venta') stock(r.cat, v.productoId, -v.cantidad); return fail(e); }
      const pagados = Object.fromEntries(v.plan.filter(c => c.estado === 'pagado').map(c => [c.n, c]));
      Object.assign(v, r.datos, {editada: new Date().toISOString(), editadaPor: D.yo().nombre, version: (v.version || 1) + 1});
      v.plan = v.plan.map(c => pagados[c.n] ? {...c, estado: 'pagado', pagadoEn: pagados[c.n].pagadoEn} : c); v.estado = estado(v);
      const op = enlazar().find(o => o.id === v.oportunidadId); if (op) ecosistema(v, op);
      audit('venta.editada', v.id);
      return D.ok({ok: true, venta: v});
    }],
    ['POST', /^workspace\/ventas\/cobro$/, (m, b) => {
      if (!puedeEscribir()) return fail('Tu rol es de consulta.', 403);
      const v = lista().find(x => x.id === b.ventaId); if (!v) return fail('Registro no encontrado en este espacio.', 404);
      const c = v.plan.find(x => x.n === Number(b.n)); if (!c) return fail('Ese cobro no existe en la venta.', 404);
      const pag = b.pagado !== false; c.estado = pag ? 'pagado' : 'pendiente'; c.pagadoEn = pag ? new Date().toISOString() : null;
      for (const t of v.tareas || []) if (t.tipo === 'cobro' && t.n === c.n) { const tk = D.tareas().find(x => x.id === t.id); if (tk) tk.estado = pag ? 'hecha' : 'pendiente'; }
      v.estado = estado(v); audit(pag ? 'venta.cobro.pagado' : 'venta.cobro.pendiente', v.id);
      return D.ok({ok: true, venta: v});
    }],
    ['POST', /^workspace\/ventas\/pendiente$/, (m, b) => {
      if (!puedeEscribir()) return fail('Tu rol es de consulta.', 403);
      const op = enlazar().find(o => o.id === b.oportunidadId); if (!op) return fail('Registro no encontrado en este espacio.', 404);
      if (op.ventaId) return D.ok({ok: true, yaRegistrada: true});
      if (op.ventaPendiente) return D.ok({ok: true, pendiente: true});
      op.ventaPendiente = true;
      const ct = D.contactos().find(c => c.id === op.contactId);
      D.setTareas([{id: nid('tk_vp'), titulo: 'Completar los datos de la venta' + (ct ? ' de ' + ct.name : ''), detalle: 'Quedó ganada sin el formulario de venta: ' + op.name, responsable: op.assignedName || D.yo().nombre, vence: hoy(), hora: '', estado: 'pendiente', origen: 'venta', contacto: ct?.name || null, contactId: op.contactId || null, conversationId: null, oportunidadId: op.id}, ...D.tareas()]);
      audit('venta.pendiente', op.id);
      return D.ok({ok: true, pendiente: true});
    }],
  );
  /* La subcuenta 2 tiene catálogo propio: sus ventas también funcionan en la demo. */
  D.libres.push(/^workspace\/ventas/);
})();

/* ---- 04k-embudos-capi.js ---- */
/* 2.1.17 · 04k-embudos-capi · demo de embudos, indicadores (KPI) y gestión unificada de usuarios */
(() => {
  const D = window.__agoraDemo; if (!D) return;
  const {ok, err} = D;
  const clon = v => JSON.parse(JSON.stringify(v));
  const admin = () => ['MASTER', 'SUPERADMIN', 'ADMIN'].includes(D.yo().rol);
  const COLORES = ['#6B7A99', '#2F6FDE', '#0E9F8F', '#D98E04', '#7C4DDB', '#D2337A', '#1F9D55', '#C2410C'];
  const TIPOS = ['abierta', 'ganada', 'perdida'], KPIS = ['', 'lead', 'cita', 'cierre'];
  const hoy = () => new Date().toISOString();
  const auditar = (accion, texto, objeto) => { try { D.auditoria().unshift({id: 'a_' + Date.now(), at: hoy(), actor: D.yo().nombre, rol: D.yo().rol, accion, accionTexto: texto, modulo: 'ajuste', objeto, contacto: null, dispositivo: 'Demo'}); } catch {} };

  /* ---------- Embudos ---------- */
  const otros = {2: [{id: 'pl_s1', name: 'Ventas de planes', stages: [{id: 'sv_1', name: 'Prospecto'}, {id: 'sv_2', name: 'Demostración'}, {id: 'sv_3', name: 'Contratado'}]}]};
  const embudosDe = t => t === 1 ? D.embudos() : (otros[t] ||= []);
  const etapa = (s, i, n) => {
    const tipo = TIPOS.includes(s.tipo) ? s.tipo : (/cierre|ganad|contratad/i.test(s.name) && i === n - 1 ? 'ganada' : 'abierta');
    return {id: s.id, name: s.name, color: /^#[0-9A-F]{6}$/i.test(s.color || '') ? s.color.toUpperCase() : COLORES[i % COLORES.length],
      probabilidad: Number.isFinite(Number(s.probabilidad)) && s.probabilidad !== null && s.probabilidad !== '' ? Math.max(0, Math.min(100, Math.round(s.probabilidad))) : (tipo === 'ganada' ? 100 : tipo === 'perdida' ? 0 : Math.round((i + 1) * 100 / (n + 1))),
      tipo, sla: s.sla ? Math.max(1, Math.min(365, Math.round(s.sla))) : null, kpi: KPIS.includes(s.kpi) ? s.kpi : (i === 0 ? 'lead' : tipo === 'ganada' ? 'cierre' : '')};
  };
  const conteos = t => {
    const o = {}; if (t !== 1) return o;
    for (const op of D.oportunidades()) { const k = op.pipelineId + '|' + op.pipelineStageId; o[k] ||= {n: 0, abiertas: 0, valor: 0}; o[k].n++; o[k].valor += Number(op.monetaryValue) || 0; if (op.status === 'open') o[k].abiertas++; }
    return o;
  };
  const respuesta = t => ({pipelines: embudosDe(t).map(p => ({id: p.id, name: p.name, stages: p.stages.map((s, i) => etapa(s, i, p.stages.length))})), conteos: conteos(t), colores: COLORES, canEdit: true});
  D.RUTAS.unshift(['GET', /^workspace\/embudos$/, () => admin() ? ok(respuesta(D.tenant())) : err('Requiere administración.', 403)]);
  D.RUTAS.unshift(['PUT', /^workspace\/embudos$/, (h, b) => {
    if (!admin()) return err('Requiere administración.', 403);
    const t = D.tenant(), lista = b.pipelines;
    if (!Array.isArray(lista) || !lista.length) return err('Debe quedar al menos un embudo.', 422);
    const nombres = new Set(), nuevos = [];
    for (const p of lista) {
      const name = String(p.name || '').trim(); if (!name) return err('Cada embudo necesita un nombre.', 422);
      if (nombres.has(name.toLowerCase())) return err(`Ya hay un embudo llamado «${name}».`, 422); nombres.add(name.toLowerCase());
      if (!Array.isArray(p.stages) || !p.stages.length) return err(`«${name}» necesita al menos una etapa.`, 422);
      const et = new Set(), ids = new Set();
      for (const s of p.stages) { const sn = String(s.name || '').trim(); if (!sn) return err(`Hay una etapa sin nombre en «${name}».`, 422); if (et.has(sn.toLowerCase())) return err(`La etapa «${sn}» está repetida en «${name}».`, 422); et.add(sn.toLowerCase()); if (s.id && ids.has(s.id)) return err('Etapas repetidas.', 422); ids.add(s.id); }
      const id = p.id || 'pl_' + Math.random().toString(36).slice(2, 8);
      nuevos.push({id, name, stages: p.stages.map((s, i) => etapa({...s, id: s.id || 'st_' + Math.random().toString(36).slice(2, 8), name: String(s.name).trim()}, i, p.stages.length))});
    }
    const existe = (p, s) => nuevos.some(q => q.id === p && q.stages.some(x => x.id === s));
    const c = conteos(t), traslados = [];
    for (const p of embudosDe(t)) for (const s of p.stages) {
      if (existe(p.id, s.id)) continue; const n = c[p.id + '|' + s.id]?.n || 0; if (!n) continue;
      const [dp, ds] = String(b.mover?.[p.id + '|' + s.id] || '').split('|');
      if (!existe(dp, ds)) return err(`La etapa «${s.name}» tiene ${n} ${n === 1 ? 'oportunidad' : 'oportunidades'}. Elige a qué etapa moverlas antes de borrarla.`, 409);
      traslados.push([p.id, s.id, dp, ds]);
    }
    let movidas = 0;
    if (t === 1) for (const op of D.oportunidades()) { const tr = traslados.find(([p, s]) => op.pipelineId === p && op.pipelineStageId === s); if (tr) { op.pipelineId = tr[2]; op.pipelineStageId = tr[3]; movidas++; } }
    const arr = embudosDe(t); arr.splice(0, arr.length, ...nuevos);
    if (t === 1) { const pl1 = nuevos.find(p => p.id === 'pl_1'); if (pl1) D.etapas().splice(0, D.etapas().length, ...pl1.stages); }
    auditar('embudos.actualizados', 'Actualizó los embudos', `${nuevos.length} embudos`);
    return ok({...respuesta(t), ok: true, movidas});
  }]);

  /* ---------- Indicadores (KPI) ---------- */
  const FUENTES = [['ventasMes', 'Ventas del mes', 'monto', 'mas'], ['cierresMes', 'Cierres del mes', 'numero', 'mas'], ['ingresos', 'Ingresos por ventas ganadas', 'monto', 'mas'], ['gananciaMes', 'Ganancia del mes', 'monto', 'mas'], ['leadsMes', 'Leads del mes', 'numero', 'mas'], ['leadsDia', 'Leads de hoy', 'numero', 'mas'], ['conversion', 'Conversión de leads', 'porcentaje', 'mas'], ['tasaCierre', 'Tasa de cierre', 'porcentaje', 'mas'], ['costoLead', 'Costo por lead', 'monto', 'menos'], ['costoCierre', 'Costo por cierre', 'monto', 'menos'], ['inversionAds', 'Inversión en anuncios', 'monto', 'menos'], ['retornoAds', 'Retorno por cada 100 invertidos', 'numero', 'mas'], ['ticketPromedio', 'Ticket promedio', 'monto', 'mas'], ['cicloVenta', 'Días para cerrar una venta', 'numero', 'menos'], ['tiempoRespuesta', 'Tiempo de respuesta (minutos)', 'numero', 'menos'], ['sinLeer', 'Conversaciones sin leer', 'numero', 'menos'], ['conversaciones', 'Conversaciones', 'numero', 'mas'], ['contactos', 'Contactos', 'numero', 'mas'], ['oportunidadesAbiertas', 'Oportunidades abiertas', 'numero', 'mas'], ['valorAbierto', 'Dinero en juego', 'monto', 'mas'], ['leadsEmbudo', 'Oportunidades en etapas de lead', 'numero', 'mas'], ['citas', 'Oportunidades en etapas de cita', 'numero', 'mas'], ['cierresEmbudo', 'Oportunidades en etapas de cierre', 'numero', 'mas'], ['tareasPendientes', 'Tareas pendientes', 'numero', 'menos'], ['tareasVencidas', 'Tareas vencidas', 'numero', 'menos'], ['conveniosPorFirmar', 'Convenios por firmar', 'numero', 'menos'], ['cobrosPendientes', 'Cobros pendientes', 'monto', 'menos']]
    .map(([id, nombre, formato, sentido]) => ({id, nombre, formato, sentido}));
  const porDefecto = () => ['ventasMes', 'leadsMes', 'conversion', 'costoLead', 'ticketPromedio', 'tiempoRespuesta', 'oportunidadesAbiertas', 'tareasVencidas'].map(f => {
    const x = FUENTES.find(y => y.id === f);
    return {id: f, fuente: f, nombre: x.nombre, activo: true, inicio: ['ventasMes', 'leadsMes', 'conversion', 'tareasVencidas'].includes(f), meta: {leadsMes: 60, conversion: 15, costoLead: 6, ticketPromedio: 1500, tiempoRespuesta: 5, oportunidadesAbiertas: 8, tareasVencidas: 0}[f] ?? null, periodo: ['tiempoRespuesta'].includes(f) ? 'dia' : 'mes', formato: x.formato, moneda: null, verde: 100, ambar: 70, sentido: x.sentido, formula: null};
  });
  const ind = {}, meses = {};
  const ruta = (verbo, patron) => D.RUTAS.find(([v, p]) => v === verbo && String(p) === String(patron));
  const mesDe = (t, period) => {
    if (t === 1) { const g = ruta('GET', /^workspace\/board-month$/); const m = g ? g[2](null, {}, new URLSearchParams('period=' + period)).body : {}; const s = m.settings || {}; return {period, teamGoal: s.teamGoal ?? null, green: s.green ?? 100, amber: s.amber ?? 70, closingWord: s.closingWord || 'cierres', goals: {...(s.goals || {})}, otherAds: s.otherAds ?? null, fixedCosts: s.fixedCosts ?? null, guardado: true, _team: m.team || []}; }
    return {period, teamGoal: null, green: 100, amber: 70, closingWord: 'cierres', goals: {}, otherAds: null, fixedCosts: null, guardado: false, ...(meses[t + ':' + period] || {})};
  };
  const respInd = (t, period) => {
    const mes = mesDe(t, period), team = mes._team || []; delete mes._team;
    const c = ind[t], kpis = clon(c?.kpis || porDefecto());
    for (const k of kpis) if (k.fuente === 'ventasMes') { k.meta = mes.teamGoal; k.verde = mes.green; k.ambar = mes.amber; }
    return {config: {kpis, actualizado: c?.actualizado || null, por: c?.por || null}, porDefecto: !c, fuentes: FUENTES, mes, asesores: team.map(u => ({id: u.id, name: u.name})), canEdit: admin()};
  };
  const mesActual = () => { const h = new Date(); return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`; };
  D.RUTAS.unshift(['GET', /^workspace\/indicadores$/, (h, b, q) => ok(respInd(D.tenant(), q.get('period') || mesActual()))]);
  D.RUTAS.unshift(['PUT', /^workspace\/indicadores$/, (h, b) => {
    if (!admin()) return err('Requiere administración.', 403);
    const t = D.tenant(), lista = b.kpis;
    if (!Array.isArray(lista) || lista.length > 40) return err('Hasta 40 indicadores.', 422);
    const ids = new Set(), kpis = [];
    for (const k of lista) {
      if (k.fuente !== 'personalizado' && !FUENTES.some(f => f.id === k.fuente)) return err('Fuente de indicador inválida.', 422);
      const nombre = String(k.nombre || '').trim().slice(0, 60); if (!nombre) return err('Cada indicador necesita un nombre.', 422);
      if (!['monto', 'numero', 'porcentaje'].includes(k.formato)) return err(`Elige el formato de «${nombre}».`, 422);
      if (!['mas', 'menos'].includes(k.sentido)) return err(`Indica si en «${nombre}» más es mejor o menos es mejor.`, 422);
      const verde = Number(k.verde ?? 100), ambar = Number(k.ambar ?? 70); if (!(verde >= 0) || !(ambar >= 0) || ambar > verde) return err(`En «${nombre}» el ámbar no puede ser mayor que el verde.`, 422);
      if (k.meta != null && k.meta !== '' && !(Number(k.meta) >= 0)) return err(`Revisa la meta de «${nombre}».`, 422);
      if (k.fuente === 'personalizado' && (!FUENTES.some(f => f.id === k.formula?.a) || !FUENTES.some(f => f.id === k.formula?.b) || !['/', '-'].includes(k.formula?.op))) return err(`La fórmula de «${nombre}» debe ser métrica A ÷ métrica B o A − B.`, 422);
      if (ids.has(k.id)) return err('Hay indicadores repetidos.', 422); ids.add(k.id);
      kpis.push({...clon(k), nombre, verde, ambar, meta: k.meta == null || k.meta === '' ? null : Number(k.meta), activo: k.activo !== false, inicio: k.inicio === true});
    }
    const m = b.mes || {}, period = m.period || mesActual();
    if (Number(m.amber ?? 70) > Number(m.green ?? 100)) return err('El ámbar del mes no puede ser mayor que el verde.', 422);
    const cambios = {teamGoal: m.teamGoal ?? null, green: Number(m.green ?? 100), amber: Number(m.amber ?? 70), closingWord: String(m.closingWord || 'cierres').slice(0, 30), goals: {...(m.goals || {})}, otherAds: m.otherAds ?? null, fixedCosts: m.fixedCosts ?? null};
    if (t === 1) { const p = ruta('POST', /^workspace\/board-month$/); p?.[2](null, cambios, new URLSearchParams()); } else meses[t + ':' + period] = cambios;
    ind[t] = {kpis: kpis.map(k => k.fuente === 'ventasMes' ? {...k, meta: null} : k), actualizado: hoy(), por: D.yo().nombre};
    auditar('indicadores.actualizados', 'Actualizó los indicadores (KPI)', `${kpis.length} indicadores`);
    return ok({...respInd(t, period), ok: true});
  }]);
  D.libres.push(/^workspace\/(embudos|indicadores)$/);

  /* ---------- Gestión unificada de usuarios ---------- */
  const LOCALES = ['ADMIN', 'SUPERVISOR', 'EJECUTIVO', 'REMITENTE', 'USUARIO', 'CONSULTA', 'VISOR'];
  const agenciaRol = r => ['MASTER', 'SUPERADMIN'].includes(r);
  const cuentaNombre = id => D.cuentas().find(a => a.id === Number(id))?.subcuenta || 'Subcuenta ' + id;
  const esMaster = () => D.yo().rol === 'MASTER';
  const alcance = () => esMaster() ? D.cuentas().map(a => a.id) : (D.yo().tenants || []).map(Number);
  /* Reúne las tres listas de la demo con un id único: d:<directorio>, s:<usuarios de ASOCIM>, a<cuenta>:<agregados desde agencia>. */
  function todos() {
    const out = [];
    for (const u of D.directorio()) out.push({ref: u, lista: 'd', id: 'd:' + u.id, nombre: u.nombre, correo: u.correo, rol: u.rol, activo: u.activo !== false, sub: agenciaRol(u.rol) ? null : Number(u.tenants?.[0] || 1), tenants: agenciaRol(u.rol) ? (u.tenants || []).map(Number) : [Number(u.tenants?.[0] || 1)], ultimo: null, yo: u.id === D.yo().id});
    for (const u of D.usuariosSistema()) if (!agenciaRol(u.rol)) out.push({ref: u, lista: 's', id: 's:' + u.id, nombre: u.nombre, correo: u.correo, rol: u.rol, activo: u.estado !== 'inactivo' && u.activo !== false, sub: 1, tenants: [1], ultimo: u.ultimoAcceso || null, yo: false});
    for (const a of D.cuentas()) for (const u of ((D.agencyUsers?.() || {})[a.id] || [])) out.push({ref: u, lista: 'a', id: `a${a.id}:` + u.id, nombre: u.nombre, correo: u.correo, rol: u.rol, activo: u.activo !== false && u.estado !== 'inactivo', sub: a.id, tenants: [a.id], ultimo: null, yo: false});
    return out;
  }
  const visible = u => esMaster() || agenciaRol(u.rol) || alcance().includes(u.sub);
  const protegido = u => u.rol === 'MASTER' && !esMaster() ? 'Solo el Master edita su propio acceso.' : !visible(u) ? 'Usuario fuera de tus subcuentas.' : null;
  const publico = u => ({id: u.id, nombre: u.nombre, correo: u.correo, rol: u.rol, activo: u.activo, ambito: agenciaRol(u.rol) ? 'agencia' : 'subcuenta', subcuentaId: agenciaRol(u.rol) ? null : u.sub, subcuenta: agenciaRol(u.rol) ? null : cuentaNombre(u.sub), tenants: u.tenants, ultimoAcceso: u.ultimo, yo: u.yo, editable: !protegido(u), protegido: protegido(u)});
  D.RUTAS.unshift(['GET', /^agency\/usuarios$/, () => {
    const items = todos().filter(visible).map(publico);
    return ok({items, total: items.length, parcial: false, subcuentas: alcance().map(id => ({id, nombre: cuentaNombre(id)})), roles: {agencia: esMaster() ? ['SUPERADMIN'] : [], subcuenta: LOCALES}, esMaster: esMaster()});
  }]);
  D.RUTAS.unshift(['PUT', /^agency\/usuarios$/, (h, b) => {
    const u = todos().find(x => x.id === String(b.id)); if (!u) return err('Usuario no encontrado.', 404);
    const p = protegido(u); if (p) return err(p, 403);
    const nombre = b.nombre != null ? String(b.nombre).trim() : u.nombre, correo = b.correo != null ? String(b.correo).trim().toLowerCase() : u.correo;
    if (!nombre || nombre.length > 120) return err('Revisa el nombre.', 422);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return err('Correo inválido.', 422);
    if (todos().some(x => x.id !== u.id && String(x.correo).toLowerCase() === correo)) return err('Ese correo ya lo usa otra persona.', 409);
    const rol = b.rolNuevo || u.rol, activo = b.activo === undefined ? u.activo : b.activo === true;
    if (rol === 'MASTER' && u.rol !== 'MASTER') return err('El rol Master no se puede asignar.', 403);
    if (u.yo && rol !== u.rol) return err('No puedes cambiar tu propio rol.', 403);
    if (u.yo && !activo) return err('No puedes desactivarte a ti mismo.', 403);
    if (u.rol === 'MASTER' && (rol !== 'MASTER' || !activo)) return err('El Master no se puede desactivar ni cambiar de rol.', 403);
    if (!agenciaRol(rol) && !LOCALES.includes(rol)) return err('Rol no asignable.', 422);
    if (agenciaRol(u.rol) !== agenciaRol(rol) && !esMaster()) return err('Solo el Master convierte un usuario de subcuenta en Superadmin o al revés.', 403);
    if (agenciaRol(rol) && u.lista !== 'd') return err('En la demo, solo las personas del directorio pueden pasar a la agencia.', 422);
    let tenants = u.tenants, sub = u.sub;
    if (rol === 'SUPERADMIN') {
      const pedidas = [...new Set((b.tenants || u.tenants || []).map(Number))];
      if (pedidas.some(t => !alcance().includes(t))) return err('Solo asignas subcuentas que tú administras.', 403);
      tenants = [...new Set([...pedidas, ...u.tenants.filter(t => !alcance().includes(t))])]; if (!tenants.length) return err('Asigna al menos una subcuenta.', 422);
    } else if (!agenciaRol(rol)) {
      sub = Number(b.subcuentaId ?? u.sub); if (!alcance().includes(sub)) return err('Solo mueves usuarios a subcuentas que tú administras.', 403); tenants = [sub];
    }
    const r = u.ref; r.nombre = nombre; r.correo = correo; r.rol = rol;
    if (u.lista === 'd') { r.activo = activo; r.tenants = tenants; }
    else {
      r.estado = activo ? 'activo' : 'inactivo'; r.activo = activo;
      if (sub !== u.sub) { // se muda de subcuenta
        const origen = u.lista === 's' ? D.usuariosSistema() : D.agencyUsers()[u.sub];
        origen.splice(origen.indexOf(r), 1);
        if (sub === 1) D.usuariosSistema().push(r); else (D.agencyUsers()[sub] ||= []).push(r);
      }
    }
    auditar('usuario.editado', 'Editó un usuario desde la agencia', correo);
    const nuevo = todos().find(x => x.ref === r);
    return ok({ok: true, usuario: publico(nuevo)});
  }]);
  D.RUTAS.unshift(['POST', /^agency\/usuarios\/clave$/, (h, b) => {
    const u = todos().find(x => x.id === String(b.id)); if (!u) return err('Usuario no encontrado.', 404);
    const p = protegido(u); if (p) return err(p, 403);
    if (u.yo) return err('Para tu propia contraseña usa Mi perfil.', 403);
    let temporal = null, clave = b.clave;
    if (b.generar === true) { const abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; temporal = Array.from({length: 14}, () => abc[Math.floor(Math.random() * abc.length)]).join(''); clave = temporal; }
    else if (typeof clave !== 'string' || clave.length < 8 || clave.length > 72) return err('La contraseña debe tener entre 8 y 72 caracteres.', 422);
    if (u.lista === 'd') u.ref.clave = clave;
    auditar('usuario.clave_restablecida', 'Restableció una contraseña', u.correo);
    return ok({ok: true, temporal});
  }]);
})();

/* ---- 04l-experiencia.js ---- */
/* 2.1.17 · 04l-experiencia · demo: editar una tarea (PUT workspace/tareas/{id}). */
(function () {
  const D = window.__agoraDemo; if (!D) return;
  const FECHA = /^\d{4}-\d{2}-\d{2}$/, HORA = /^([01]\d|2[0-3]):[0-5]\d$/;
  D.RUTAS.unshift(['PUT', /^workspace\/tareas\/([a-z0-9_]{1,80})$/, (m, body) => {
    const b = body || {}, t = D.tareas().find(x => x.id === m[1]);
    if (!t) return D.err('Registro no encontrado en este espacio.', 404);
    const titulo = String(b.titulo || '').trim();
    if (!titulo) return D.err('El campo titulo es obligatorio.', 422);
    if (b.vence && !FECHA.test(b.vence)) return D.err('Fecha inválida.', 422);
    if (b.hora && !HORA.test(b.hora)) return D.err('Hora inválida.', 422);
    if (b.estado && !['pendiente', 'hecha'].includes(b.estado)) return D.err('Estado de tarea inválido.', 422);
    Object.assign(t, {titulo: titulo.slice(0, 250), detalle: String(b.detalle || '').slice(0, 2000), vence: b.vence || null, hora: b.hora || '', estado: b.estado || t.estado});
    return D.ok({ok: true, tarea: t});
  }]);
})();

/* ---- 04m-tablas.js ---- */
/* 2.1.19 · 04m-tablas · rutas de la demo para las tablas editables (columnas, filas a mano, carga masiva,
   valores de columnas propias). Copia en JS de servidor/app/src/Controllers/Tablas.php.
   En el archivo de prueba se guarda en este navegador (localStorage) para que se pueda probar recargando. */
(function () {
  const D = window.__agoraDemo; if (!D) return;
  const LS = 'agora.demo.tablas.v1';
  let S; try { S = JSON.parse(localStorage.getItem(LS) || 'null'); } catch { S = null; }
  S = S && typeof S === 'object' ? S : {};
  const guardar = () => { try { localStorage.setItem(LS, JSON.stringify(S)); } catch { /* sin almacenamiento */ } };
  const T = () => { const k = 't' + (D.tenant() || 0); return (S[k] ||= {tablas: {}, filas: {}, valores: {}}); };
  const CLAVE = /^[a-zA-Z]{2,16}\.[a-z0-9]{6,12}$/, COL = /^(b:[a-z0-9]{1,60}|cf_[a-z0-9_]{1,36})$/;
  const TIPOS = ['texto', 'numero', 'fecha', 'correo', 'telefono', 'lista', 'si_no'];
  const norm = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
  const admin = () => ['MASTER', 'SUPERADMIN', 'ADMIN'].includes(D.yo()?.rol);
  const celda = (c, v) => (window.AgoraMigracion?.celda || ((x, y) => [y === '' ? null : y, null]))({tipo: c.tipo || 'texto', requerido: false, opciones: c.opciones}, v);
  const validar = (clave, datos) => {
    if (!datos || typeof datos !== 'object' || Array.isArray(datos)) return [{}, [{campo: '', mensaje: 'Fila inválida.'}]];
    const cols = T().tablas[clave]?.columnas || [], por = Object.fromEntries(cols.map(c => [c.id, c]));
    const out = {}, err = [];
    for (const [k, v] of Object.entries(datos)) {
      if (!COL.test(k)) continue;
      const c = por[k] || {titulo: k, tipo: 'texto'};
      const [val, e] = celda(c, v); if (e) { err.push({campo: c.titulo, mensaje: e}); continue; }
      if (val != null && val !== '') out[k] = val;
    }
    cols.forEach(c => { if (c.requerido && c.visible && !(c.id in out)) err.push({campo: c.titulo, mensaje: 'Es obligatorio.'}); });
    if (!Object.keys(out).length && !err.length) err.push({campo: '', mensaje: 'La fila está vacía.'});
    return [out, err];
  };
  const msg = err => err.map(e => (e.campo ? e.campo + ': ' : '') + e.mensaje).join(' · ');
  const nuevaFila = (clave, periodo, datos) => { const f = {id: Math.random().toString(36).slice(2, 12) + Date.now().toString(36), periodo: periodo || '', datos, creado: new Date().toISOString(), por: D.yo()?.nombre || 'Demo', dueno: D.yo()?.id}; (T().filas[clave] ||= []).push(f); return f; };
  D.libres.push(/^workspace\/tablas/);
  D.RUTAS.unshift(
    ['GET', /^workspace\/tablas$/, (m, b, q) => {
      const mod = q.get('modulo') || '', per = q.get('periodo') || '', t = T();
      const pref = mod + '.', tablas = {}, filas = {}, valores = {};
      Object.keys(t.tablas).filter(k => k.startsWith(pref)).forEach(k => { tablas[k] = t.tablas[k]; });
      Object.keys(t.valores).filter(k => k.startsWith(pref)).forEach(k => { valores[k] = t.valores[k]; });
      Object.keys(t.filas).filter(k => k.startsWith(pref)).forEach(k => { const fs = t.filas[k].filter(f => !per || !f.periodo || f.periodo === per); if (fs.length) filas[k] = fs; });
      return D.ok({modulo: mod, periodo: per, tablas, filas, valores, canEdit: admin()});
    }],
    ['PUT', /^workspace\/tablas$/, (m, b) => {
      if (!admin()) return D.err('Requiere administración.', 403);
      if (!CLAVE.test(b.clave || '')) return D.err('Tabla desconocida.', 422);
      if (b.restaurar) { delete T().tablas[b.clave]; guardar(); return D.ok({ok: true, clave: b.clave, tabla: null}); }
      const l = b.columnas; if (!Array.isArray(l) || !l.length || l.length > 80) return D.err('Lista de columnas inválida (de 1 a 80).', 422);
      const nombres = {}, out = [];
      for (const [i, c] of l.entries()) {
        if (!COL.test(c.id || '')) return D.err('Columna inválida.', 422);
        const t = String(c.titulo || '').trim().replace(/\s+/g, ' '); if (!norm(t) || t.length > 60) return D.err('Cada columna necesita un nombre con letras o números.', 422);
        if (nombres[norm(t)]) return D.err(`Dos columnas se llaman «${t}». Usa nombres distintos: así Ágora reconoce cada columna del Excel.`, 422); nombres[norm(t)] = 1;
        const tipo = TIPOS.includes(c.tipo) ? c.tipo : 'texto';
        if (tipo === 'lista' && (!Array.isArray(c.opciones) || !c.opciones.length)) return D.err(`Escribe entre 1 y 50 opciones para «${t}».`, 422);
        out.push({id: c.id, base: c.id.startsWith('cf_') ? null : (c.base || t), titulo: t, tipo, opciones: tipo === 'lista' ? c.opciones : null, visible: c.visible !== false, requerido: !!c.requerido, orden: i});
      }
      if (!out.some(c => c.visible)) return D.err('Deja al menos una columna visible.', 422);
      const tabla = {columnas: out, actualizado: new Date().toISOString(), por: D.yo()?.nombre || ''};
      T().tablas[b.clave] = tabla; guardar();
      return D.ok({ok: true, clave: b.clave, tabla});
    }],
    ['POST', /^workspace\/tablas\/filas$/, (m, b) => {
      if (!CLAVE.test(b.clave || '')) return D.err('Tabla desconocida.', 422);
      const [datos, err] = validar(b.clave, b.datos); if (err.length) return D.err(msg(err), 422);
      const fila = nuevaFila(b.clave, b.periodo, datos); guardar();
      return D.ok({ok: true, fila});
    }],
    ['PUT', /^workspace\/tablas\/filas$/, (m, b) => {
      const fs = T().filas[b.clave] || [], f = fs.find(x => x.id === b.id); if (!f) return D.err('La fila ya no existe.', 404);
      const [datos, err] = validar(b.clave, b.datos); if (err.length) return D.err(msg(err), 422);
      f.datos = datos; f.editado = new Date().toISOString(); guardar();
      return D.ok({ok: true, fila: f});
    }],
    ['DELETE', /^workspace\/tablas\/filas$/, (m, b, q) => {
      const clave = q.get('clave') || b.clave, id = q.get('id') || b.id, fs = T().filas[clave] || [];
      if (!fs.some(x => x.id === id)) return D.err('La fila ya no existe.', 404);
      T().filas[clave] = fs.filter(x => x.id !== id); guardar();
      return D.ok({ok: true});
    }],
    ['POST', /^workspace\/tablas\/importar$/, (m, b) => {
      if (!admin()) return D.err('Requiere administración.', 403);
      if (!CLAVE.test(b.clave || '')) return D.err('Tabla desconocida.', 422);
      if (!Array.isArray(b.filas) || b.filas.length > 500) return D.err('Envía hasta 500 filas por lote.', 422);
      const res = {filas: b.filas.length, creados: 0, errores: [], simulado: !!b.simular}, desde = +b.desde || 2;
      b.filas.forEach((x, i) => { const [datos, err] = validar(b.clave, x); if (err.length) { err.forEach(e => res.errores.push({fila: desde + i, ...e})); return; } if (!b.simular) nuevaFila(b.clave, b.periodo, datos); res.creados++; });
      guardar(); return D.ok(res);
    }],
    ['PUT', /^workspace\/tablas\/valor$/, (m, b) => {
      const c = (T().tablas[b.clave]?.columnas || []).find(x => x.id === b.columna); if (!c) return D.err('Esa columna ya no existe.', 422);
      const [val, e] = celda(c, b.valor); if (e) return D.err(c.titulo + ': ' + e, 422);
      const vs = (T().valores[b.clave] ||= {}); vs[b.firma] ||= {};
      if (val == null || val === '') delete vs[b.firma][c.id]; else vs[b.firma][c.id] = val;
      guardar(); return D.ok({ok: true, valor: val});
    }],
  );
})();

/* ---- 04n-integraciones.js ---- */
/* 2.1.19 · 04n-integraciones · rutas de la demo para Ajustes → Cuentas conectadas (copia de Controllers/Integraciones.php).
   Las preferencias se guardan en memoria; lo que necesita la cuenta externa responde qué falta. */
(function () {
  const D = window.__agoraDemo; if (!D) return;
  const NO = 'Archivo de prueba: aquí no hay una cuenta externa conectada. En el servidor se configura con su clave de acceso.';
  const cfg = {}; const de = id => (cfg[id] ||= {comisiones: []});
  const com = c => ({current: c.comisiones.length ? c.comisiones[c.comisiones.length - 1] : {kind: 'percent', amount: 10, since: null}, history: c.comisiones.slice(0, -1).reverse()});
  D.libres.push(/^admin\//);
  D.RUTAS.unshift(
    ['POST', /^admin\/access-channel\/test$/, () => D.err('El envío de correos no está configurado en el archivo de prueba. En el servidor se configura el correo de salida (SMTP).', 503)],
    ['PUT', /^admin\/access-channel$/, (m, b) => D.ok({ok: true, connectionId: b.connectionId ?? null})],
    ['POST', /^admin\/integrations\/(\d+)\/test$/, () => D.err(NO, 503)],
    ['POST', /^admin\/integrations\/(\d+)\/users\/sync$/, () => D.err(NO + ' Los usuarios de Ágora se administran en «Usuarios y roles».', 503)],
    ['GET', /^admin\/integrations\/(\d+)\/users$/, () => D.ok({items: (D.usuarios?.() || []).map(u => ({id: u.id, name: u.nombre || u.name, email: u.correo || u.email, role: u.rol || u.role, active: u.activo !== false})), syncedAt: null, source: 'agora'})],
    ['POST', /^admin\/integrations\/(\d+)\/schedule$/, (m, b) => { de(m[1]).sync_enabled = b.enabled === true; return D.ok({ok: true, enabled: b.enabled === true}); }],
    ['GET', /^admin\/integrations\/(\d+)\/commission$/, m => D.ok(com(de(m[1])))],
    ['PUT', /^admin\/integrations\/(\d+)\/commission$/, (m, b) => {
      const kind = ['percent', 'per_closing'].includes(b.kind) ? b.kind : null, amount = Number(b.amount);
      if (!kind) return D.err('Elige cómo se paga la comisión.', 422);
      if (!(amount >= 0) || (kind === 'percent' && amount > 100)) return D.err(kind === 'percent' ? 'El porcentaje va de 0 a 100.' : 'Revisa el monto por cierre.', 422);
      const c = de(m[1]), mes = new Date().toISOString().slice(0, 7); c.comisiones = c.comisiones.filter(h => h.since !== mes); c.comisiones.push({kind, amount, since: mes});
      return D.ok(com(c));
    }],
    ['PUT', /^admin\/integrations\/(\d+)\/won-alert$/, (m, b) => { const d = Math.max(0, Math.min(90, parseInt(b.days, 10) || 0)); de(m[1]).won_alert_days = d; return D.ok({ok: true, days: d}); }],
    ['PUT', /^admin\/integrations\/(\d+)\/reminder$/, (m, b) => { const n = Math.max(0, Math.min(1440, parseInt(b.minutes, 10) || 0)); de(m[1]).unread_reminder_minutes = n; return D.ok({ok: true, minutes: n}); }],
    ['PUT', /^admin\/integrations\/(\d+)\/currency$/, (m, b) => ['USD', 'EUR', 'VES'].includes(b.currency) ? D.ok({ok: true, currency: b.currency}) : D.err('Moneda inválida.', 422)],
    ['GET', /^admin\/integrations\/(\d+)\/fields\/folders$/, m => D.ok({items: (de(m[1]).carpetas || []).map(name => ({name})), names: de(m[1]).carpetas || []})],
    ['PUT', /^admin\/integrations\/(\d+)\/fields\/folders$/, (m, b) => { de(m[1]).carpetas = (b.names || []).map(x => String(x).trim()).filter(Boolean).slice(0, 50); return D.ok({ok: true, names: de(m[1]).carpetas}); }],
    ['PUT', /^admin\/integrations\/(\d+)\/whatsapp$/, (m, b) => { de(m[1]).whatsapp = {numberId: b.numberId || null, accountId: b.accountId || null}; return D.ok({ok: true}); }],
    ['POST', /^admin\/integrations\/(\d+)\/whatsapp\/check$/, () => D.ok({diagnosis: NO})],
    ['DELETE', /^admin\/integrations\/(\d+)$/, () => D.err('Las subcuentas no se borran desde aquí: se pausan o archivan desde Agencia → Centro de mando.', 409)],
    ['PUT', /^admin\/integrations\/(\d+)$/, () => D.ok({ok: true})],
    ['POST', /^admin\/integrations$/, () => D.err('Las subcuentas nuevas se crean desde Agencia → «Nueva subcuenta».', 503)],
    ['POST', /^admin\/cron-url$/, () => D.err('En CyberPanel la revisión periódica se programa con una tarea cron del servidor (ver INSTALAR-CYBERPANEL.md).', 503)],
    ['DELETE', /^admin\/cron-url$/, () => D.ok({ok: true})],
    ['POST', /^admin\/installation\/encryption$/, () => D.err('El cifrado se configura en servidor/config/config.php del servidor.', 503)],
  );
})();

