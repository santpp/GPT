/* =====================================================================================
   Agora Digital App · datos de prueba
   -------------------------------------------------------------------------------------
   Esto NO va en el servidor. Sirve para recorrer todos los módulos sin servidor ninguno:
   se pone delante de fetch y responde las rutas de los dos sistemas con datos inventados.

   Las respuestas tienen la misma forma que las de verdad, así que lo que se ve aquí es
   lo que se verá en producción. Lo que se escribe queda en memoria: al recargar vuelve
   al punto de partida.

   Para probar contra los servidores de verdad, basta con no cargar este archivo.
   ===================================================================================== */
(() => {
  const nativeFetch = window.fetch.bind(window);
  const DAY = 86400000;
  const ahora = Date.now();
  /* El servidor de Contratos guarda la hora en UTC, con espacio en vez de «T». */
  const utcStamp = ms => new Date(ms).toISOString().replace('T', ' ').slice(0, 19);
  const isoStamp = ms => new Date(ms).toISOString();
  const ymd = ms => new Date(ms).toISOString().slice(0, 10);
  const mesActual = new Date().toISOString().slice(0, 7);

  /* =====================================================================================
     Datos de Contratos (ASOCIM)
     ===================================================================================== */
  let yo = {id: 1, nombre: 'Administrador de Agencia', correo: 'demo.agencia@agora.test', rol: 'SUPERADMIN'};
  let demoAutenticado = false;
  const DEMO_CORREO = 'demo.agencia@agora.test';
  const DEMO_CLAVE = 'AgoraDemo!2026';
  // Identidades de demostración; nunca usar estas contraseñas en producción.
  const directory = [
    {id:1,nombre:'Administrador de pruebas',correo:'admin@agora.test',telefono:'',rol:'MASTER',agency_id:1,tenants:[],activo:true,clave:'123456789',foto:null},
    {id:2,nombre:'Supervisor de Agencia',correo:'super.agora@agora.test',telefono:'',rol:'SUPERADMIN',agency_id:1,tenants:[1],activo:true,clave:'SuperDemo!2026',foto:null},
    {id:101,nombre:'Administrador ASOCIM',correo:'admin.asocim@agora.test',telefono:'',rol:'ADMIN',agency_id:1,tenants:[1],activo:true,clave:'AsocimDemo!2026',foto:null},
    {id:102,nombre:'Usuario ASOCIM',correo:'usuario.asocim@agora.test',telefono:'',rol:'USUARIO',agency_id:1,tenants:[1],activo:true,clave:'UsuarioDemo!2026',foto:null}
  ];
  const textOverrides = {}, studioJobs = [], governanceAudit = [];
  const publicIdentity = u => { const {clave,...publicUser}=u; return structuredClone(publicUser); };
  const master = () => yo.rol === 'MASTER';
  const agencyRole = () => ['MASTER','SUPERADMIN'].includes(yo.rol);
  const allowedTenant = id => master() || (yo.tenants || []).includes(Number(id));
  const actor = () => directory.find(u=>u.id===yo.id);
  function refreshIdentity(user) {
    yo=publicIdentity(user);
    agoraMe={...yo,user:{id:yo.id,name:yo.nombre,email:yo.correo,role:agencyRole()?'administrator':'member',capabilities:{viewConversations:true,sendMessages:yo.rol!=='USUARIO',assignedDataOnly:yo.rol==='USUARIO'}},csrf:'demo-csrf-token'};
  }
  const auditGov = (action,target) => governanceAudit.push({at:new Date().toISOString(),actorId:yo.id,action,target});


  const uid = n => String(n).padStart(24, 'a1b2c3d4e5f60718293a4b5c').slice(-24);
  let seq = 1;
  const nuevoUid = () => uid('f0e1d2c3b4a5' + String(++seq).padStart(12, '0'));

  const CLIENTES = [
    ['Mariana Pérez Alvarado', 'mariana.perez@gmail.com', 'V-18456321', '+58 412 1122334'],
    ['Luis Alberto Ramírez', 'luis.ramirez@hotmail.com', 'V-14223877', '+58 414 5566778'],
    ['Carla Sofía Montes', 'carla.montes@gmail.com', 'V-21998455', '+58 424 9988776'],
    ['José Gregorio Díaz', 'jose.diaz@outlook.com', 'V-11234567', '+58 416 2233445'],
    ['Andreína Castillo', 'andreina.castillo@gmail.com', 'V-23445566', '+58 412 6677889'],
    ['Pedro Luis Hernández', 'pedro.hernandez@gmail.com', 'V-16778899', '+58 426 1234567'],
    ['Yoselin Guerrero', 'yoselin.guerrero@gmail.com', 'V-25667788', '+58 414 7654321'],
    ['Rafael Ángel Sánchez', 'rafael.sanchez@gmail.com', 'V-12998877', '+58 424 3344556'],
  ];
  const ESTADOS = ['FIRMADO', 'ENVIADO', 'BORRADOR', 'FIRMADO', 'ENVIADO', 'FIRMADO', 'BORRADOR', 'REVOCADA', 'FIRMADO', 'ENVIADO', 'BORRADOR', 'FIRMADO'];

  let contratos = ESTADOS.map((estado, i) => {
    const [cliente, correo] = CLIENTES[i % CLIENTES.length];
    return {
      uid: uid('c0ffee' + String(i + 1).padStart(18, '0')),
      cliente, correo, estado,
      version: i === 7 ? 2 : 1,
      creado_en: utcStamp(ahora - (i * 2.4 + 0.3) * DAY),
    };
  });

  /* El detalle se calcula a partir de la fila, para que cuadre con lo que se ve en la lista. */
  const cuotasDe = (cuota, meses, desde, porMes) => {
    const out = [];
    let n = 0;
    for (let m = 1; m <= meses; m++) {
      const base = new Date(desde.getFullYear(), desde.getMonth() + m - 1, 1);
      const dias = porMes === 1 ? [30] : porMes === 2 ? [15, 30] : [7, 14, 21, 28];
      for (const d of dias) {
        n++;
        const ultimo = new Date(base.getFullYear(), base.getMonth() + 1, 0).getDate();
        out.push({numero: n, mes: m, vencimiento: ymd(new Date(base.getFullYear(), base.getMonth(), Math.min(d, ultimo)).getTime()), monto: (cuota / porMes).toFixed(2)});
      }
    }
    return out;
  };
  function detalleDe(row) {
    const i = contratos.findIndex(c => c.uid === row.uid);
    const persona = CLIENTES[i % CLIENTES.length];
    const cuota = [150, 180, 120, 200, 165][i % 5];
    const meses = [12, 10, 12, 6, 12][i % 5];
    const porMes = [1, 1, 2, 1, 1][i % 5];
    const desde = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 1);
    const cuotas = cuotasDe(cuota, meses, desde, porMes);
    const precio = cuota * meses;
    const inscripcion = 60;
    return {
      uid: row.uid, version: row.version, estado: row.estado, descuento: '5',
      cliente: {nombre: persona[0], cedula: persona[2], telefono: persona[3], correo: persona[1]},
      calculo: {
        precio_curso: precio.toFixed(2),
        cargos: [{concepto: 'Inscripción', monto: inscripcion.toFixed(2)}],
        total_a_pagar: (precio + inscripcion).toFixed(2),
        cuotas,
      },
      enlace: row.estado === 'ENVIADO' ? 'https://contratos.asocim.org/firmar/9f2b7c4e1a8d3056bb11' : null,
      enlace_guardable: true,
      pdfs: {enviado: row.estado !== 'BORRADOR', firmado: row.estado === 'FIRMADO'},
    };
  }

  let usuarios = [
    {id: 1, nombre: 'Eduardo Bocaranda', correo: 'info@agoradigital.app', rol: 'ADMIN', activo: 1},
    {id: 2, nombre: 'Rosa Natalia Ortega', correo: 'rortega@asocim.org', rol: 'REMITENTE', activo: 1},
    {id: 3, nombre: 'Daniel Figueroa', correo: 'dfigueroa@asocim.org', rol: 'REMITENTE', activo: 1},
    {id: 4, nombre: 'Marisol Vera', correo: 'mvera@asocim.org', rol: 'VISOR', activo: 1},
    {id: 5, nombre: 'Jhonny Arteaga', correo: 'jarteaga@asocim.org', rol: 'REMITENTE', activo: 0},
  ];

  let eventos = [
    {tipo: 'FIRMADO', cliente: 'Mariana Pérez Alvarado', uid: contratos[0].uid, creado_en: utcStamp(ahora - 0.1 * DAY), detalle: {version: 1}},
    {tipo: 'ENVIADO', cliente: 'Luis Alberto Ramírez', uid: contratos[1].uid, creado_en: utcStamp(ahora - 0.4 * DAY), detalle: {version: 1, vence: ymd(ahora + 7 * DAY)}},
    {tipo: 'CREADO', cliente: 'Carla Sofía Montes', uid: contratos[2].uid, creado_en: utcStamp(ahora - 0.8 * DAY), detalle: {origen: 'ghl'}},
    {tipo: 'FIRMADO', cliente: 'José Gregorio Díaz', uid: contratos[3].uid, creado_en: utcStamp(ahora - 1.2 * DAY), detalle: {version: 1}},
    {tipo: 'ENLACE_NUEVO', cliente: 'Andreína Castillo', uid: contratos[4].uid, creado_en: utcStamp(ahora - 1.6 * DAY), detalle: {motivo: 'a_peticion'}},
    {tipo: 'NUEVA_VERSION', cliente: 'Rafael Ángel Sánchez', uid: contratos[7].uid, creado_en: utcStamp(ahora - 2.1 * DAY), detalle: {version: 2, anterior: 'ENVIADO'}},
    {tipo: 'REVOCADO', cliente: 'Rafael Ángel Sánchez', uid: contratos[7].uid, creado_en: utcStamp(ahora - 2.2 * DAY), detalle: {motivo: 'datos_incorrectos'}},
    {tipo: 'CLIENTE_EDITADO', cliente: 'Pedro Luis Hernández', uid: contratos[5].uid, creado_en: utcStamp(ahora - 3 * DAY), detalle: {}},
    {tipo: 'FIRMADO', cliente: 'Yoselin Guerrero', uid: contratos[8].uid, creado_en: utcStamp(ahora - 4.4 * DAY), detalle: {version: 1}},
    {tipo: 'ENVIADO', cliente: 'Andreína Castillo', uid: contratos[9].uid, creado_en: utcStamp(ahora - 5.2 * DAY), detalle: {version: 1}},
    {tipo: 'CREADO', cliente: 'Pedro Luis Hernández', uid: contratos[10].uid, creado_en: utcStamp(ahora - 6.1 * DAY), detalle: {origen: 'ghl'}},
    {tipo: 'ENLACES_REVOCADOS', cliente: 'Carla Sofía Montes', uid: contratos[2].uid, creado_en: utcStamp(ahora - 7.3 * DAY), detalle: {}},
  ];

  const CONTACTOS_GHL = [
    {id: 'ct_8812', nombre: 'Génesis Carolina Mora', correo: 'genesis.mora@gmail.com', telefono: '+58 412 8812233', cedula: 'V-27881122'},
    {id: 'ct_4471', nombre: 'Héctor Javier Pino', correo: 'hector.pino@gmail.com', telefono: '+58 424 4471188', cedula: 'V-19447118'},
    {id: 'ct_9032', nombre: 'Dayana Michelle Rojas', correo: 'dayana.rojas@hotmail.com', telefono: '+58 414 9032255', cedula: ''},
    {id: 'ct_1150', nombre: 'Mariana Pérez Alvarado', correo: 'mariana.perez@gmail.com', telefono: '+58 412 1122334', cedula: 'V-18456321'},
    {id: 'ct_7765', nombre: 'Wilmer Antonio Salas', correo: 'wilmer.salas@gmail.com', telefono: '+58 426 7765099', cedula: 'V-13776509'},
  ];

  /* =====================================================================================
     Datos de Ágora (CRM)
     ===================================================================================== */
  let agoraMe = {
    user: {id: 1, email: 'demo.agencia@agora.test', role: 'administrator', identity_type: 'native', capabilities: {viewConversations: true, sendMessages: true, assignedDataOnly: false}},
    csrf: 'demo-csrf-token', locked: false, passkeys: 0, lockMinutes: null,
  };
  let demoActiveTenant = null; // null = agencia (no cliente operativo)
  let nextAgencyId = 3;
  const isoDia = d => { const t = new Date(Date.now() + d * 86400000); return new Date(t.getTime() - t.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
  const diasHasta = f => f ? Math.round((Date.parse(f + 'T00:00:00') - Date.parse(isoDia(0) + 'T00:00:00')) / 86400000) : null;
  const agencyAccounts = [
    {id: 1, nombre:'ASOCIM', subcuenta:'ASOCIM · Admisiones', codigo:'ASO-ADM', cobros:{plan:{monto:285,frecuencia:'mensual'},servicios:[{id:'svpauta01',nombre:'Gestión de pauta',monto:600,frecuencia:'anual',activo:true},{id:'svweb0001',nombre:'Landing de admisiones',monto:350,frecuencia:'unico',activo:true}],actualizado:isoDia(-20)+' 14:00:00'}, estado:'activa', rubro:'Educación', moneda:'USD', plan:'Operación anual', usuarios:5, vence: isoDia(120), fechaCreacion: isoDia(-300), ultimoAcceso: isoDia(0)},
    {id: 2, nombre:'StreamingNet', subcuenta:'StreamingNet · Ventas', codigo:'STR-VNT', cobros:{plan:{monto:180,frecuencia:'mensual'},servicios:[{id:'svbot0001',nombre:'Bot de WhatsApp',monto:45,frecuencia:'mensual',activo:true}],actualizado:isoDia(-60)+' 10:30:00'}, estado:'activa', rubro:'Telecomunicaciones / ISP', moneda:'USD', plan:'Comercial mensual', usuarios:0, vence: isoDia(5), fechaCreacion: isoDia(-200), ultimoAcceso: isoDia(-1)},
  ];
  const agencyUsers = {1:[], 2:[]};
  const conexiones = [
    {id: 1, name: 'ASOCIM · Admisiones', currency: 'USD', status: 'connected', location_id: 'loc_A7K2M9', sync_interval_seconds: 300, last_checked_at: utcStamp(ahora - 0.02 * DAY), sync_enabled: 1, last_success_at: utcStamp(ahora - 0.01 * DAY), externalUsers: 6, users_last_success_at: utcStamp(ahora - 0.5 * DAY), whatsapp_number_id: '10928371', accessChannel: true, currency_code: 'USD', unread_reminder_minutes: 10, won_alert_days: 7},
    {id: 2, name: 'StreamingNet · Ventas', currency: 'USD', status: 'connected', location_id: 'loc_S4N8P1', sync_interval_seconds: 300, last_checked_at: utcStamp(ahora - 0.05 * DAY), sync_enabled: 1, last_success_at: utcStamp(ahora - 0.03 * DAY), externalUsers: 4, users_last_success_at: utcStamp(ahora - 0.7 * DAY), whatsapp_number_id: null, unread_reminder_minutes: 15, won_alert_days: 7},
  ];
  const EQUIPO = [
    {id: 'us_01', name: 'Rosa Natalia Ortega', email: 'rortega@asocim.org'},
    {id: 'us_02', name: 'Daniel Figueroa', email: 'dfigueroa@asocim.org'},
    {id: 'us_03', name: 'Yarelis Mendoza', email: 'ymendoza@asocim.org'},
    {id: 'us_04', name: 'Jhonny Arteaga', email: 'jarteaga@asocim.org'},
  ];

  const CANALES = ['TYPE_WHATSAPP', 'TYPE_INSTAGRAM', 'TYPE_EMAIL', 'TYPE_SMS', 'TYPE_FACEBOOK'];
  const PREVIAS = [
    'Buenas tardes, quisiera saber si todavía hay cupos para el diplomado.',
    '¿El pago se puede hacer en dos partes? Me interesa empezar en enero.',
    'Listo, ya hice la transferencia. Les envío el comprobante.',
    'Gracias por la información, lo converso en casa y le escribo.',
    '¿Las clases son en línea o presenciales los sábados?',
    'Buenos días, ¿me pueden reenviar el enlace del convenio?',
    'Perfecto, quedo pendiente del correo con el convenio.',
    '¿Cuánto cuesta la inscripción aparte de la cuota mensual?',
  ];
  let conversaciones = PREVIAS.map((body, i) => ({
    id: 'cv_' + (1001 + i),
    contactId: 'ct_' + (7001 + i),
    contactName: CLIENTES[i % CLIENTES.length][0],
    lastMessageBody: body,
    lastMessageType: CANALES[i % CANALES.length],
    lastMessageDirection: i % 3 === 2 ? 'outbound' : 'inbound',
    lastMessageDate: isoStamp(ahora - (i * 37 + 8) * 60000),
    unreadCount: i < 3 ? [2, 1, 3][i] : 0,
    assignedTo: i % 2 ? 'us_01' : 'us_02',
  }));

  const mensajesDe = id => {
    const i = conversaciones.findIndex(c => c.id === id);
    const c = conversaciones[i] || conversaciones[0];
    const t = Date.parse(c.lastMessageDate);
    const guion = [
      ['inbound', 'Buenas, vi la publicación del diplomado en Instagram y quiero más información.'],
      ['outbound', '¡Hola! Con gusto. El diplomado dura 12 meses, con clases los sábados y acompañamiento en línea. La cuota mensual es de 150,00 y la inscripción, 60,00.'],
      ['inbound', '¿El pago se puede hacer en dos partes?'],
      ['outbound', 'Sí: podemos dividir la cuota en dos pagos al mes, el 15 y el 30. Te preparo el convenio con ese plan y te llega por correo para firmarlo.'],
      ['inbound', c.lastMessageBody],
    ];
    return guion.map(([direction, body], n) => ({
      id: `${c.id}_m${n + 1}`,
      body,
      direction,
      messageType: c.lastMessageType,
      status: direction === 'outbound' ? 'delivered' : 'received',
      dateAdded: isoStamp(t - (guion.length - n - 1) * 26 * 60000),
    }));
  };

  const CONTACTOS_AG = CLIENTES.map(([name, email, cedula, phone], i) => ({
    id: 'ct_' + (7001 + i), name, email, phone,
    companyName: i % 3 === 0 ? 'Particular' : '',
    tags: [['interesado', 'diplomado'], ['convenio', 'firmado'], ['seguimiento'], ['instagram'], ['convenio'], ['frío'], ['referido'], ['interesado']][i] || [],
    assignedTo: i % 2 ? 'us_01' : 'us_02',
    assignedName: i % 2 ? 'Rosa Natalia Ortega' : 'Daniel Figueroa',
    country: 'Venezuela', timezone: 'America/Caracas',
    source: ['Instagram', 'Facebook', 'Referido', 'Sitio web'][i % 4],
    dnd: false,
    customFields: [{id: 'cf_ced', name: 'Cédula', value: cedula}, {id: 'cf_pro', name: 'Programa de interés', value: 'Diplomado en Componente Docente'}],
  }));

  const ETAPAS = [
    {id: 'st_1', name: 'Nuevo interesado'},
    {id: 'st_2', name: 'En conversación'},
    {id: 'st_3', name: 'Convenio enviado'},
    {id: 'st_4', name: 'Cierre'},
  ];
  const EMBUDOS = [
    {id: 'pl_1', name: 'Admisiones 2026', stages: ETAPAS},
    {id: 'pl_2', name: 'Reinscripciones', stages: ETAPAS.slice(0, 3)},
  ];
  let oportunidades = CLIENTES.map(([name], i) => ({
    id: 'op_' + (3001 + i),
    name: 'Diplomado · ' + name.split(' ')[0] + ' ' + name.split(' ')[1],
    contactName: name,
    monetaryValue: [1860, 0, 1440, 2160, 1860, 1320, 1860, 0][i],
    pipelineId: 'pl_1',
    pipelineStageId: ETAPAS[i % 4].id,
    assignedTo: i % 2 ? 'us_01' : 'us_02',
    assignedName: i % 2 ? 'Rosa Natalia Ortega' : 'Daniel Figueroa',
    status: i % 4 === 3 ? 'won' : 'open',
    wonAt: i % 4 === 3 ? isoStamp(ahora - (i + 1) * 1.5 * DAY) : null,
  }));

  const tablero = (from, to) => ({
    restricted: false,
    readAt: isoStamp(ahora),
    conversations: {total: conversaciones.length, unread: conversaciones.reduce((s, c) => s + c.unreadCount, 0), waiting: conversaciones.filter(c => c.lastMessageDirection !== 'outbound').length},
    contacts: CONTACTOS_AG.length + 136,
    leads: {today: 7, mine: 3, linked: true},
    opportunities: {
      count: oportunidades.filter(o => o.status === 'open').length,
      value: oportunidades.filter(o => o.status === 'open').reduce((s, o) => s + o.monetaryValue, 0),
      sampled: false,
      stages: ETAPAS.map((s, i) => ({
        name: s.name,
        count: oportunidades.filter(o => o.pipelineStageId === s.id && o.status === 'open').length,
        value: oportunidades.filter(o => o.pipelineStageId === s.id && o.status === 'open').reduce((a, o) => a + o.monetaryValue, 0),
      })),
    },
    from, to,
  });

  let cifrasMes = {otherAds: 120, fixedCosts: 1800, teamGoal: 14000, closingWord: 'cierres', green: 100, amber: 70, goals: {us_01: 5000, us_02: 4500, us_03: 3000, us_04: 1500}};
  const mesBloque = period => {
    const ventas = {closings: 9, total: 13260, counted: 9, partial: false};
    const ads = 2340 + Number(cifrasMes.otherAds || 0);
    const comisiones = ventas.total * 0.1;
    const ganancia = ventas.total - ads - comisiones;
    const equipo = EQUIPO.map((u, i) => {
      const vendido = [5200, 4100, 2960, 1000][i];
      const meta = Number(cifrasMes.goals[u.id]) || null;
      const avance = meta ? (vendido / meta) * 100 : null;
      return {
        id: u.id, name: u.name, closings: [3, 3, 2, 1][i], goal: meta, sold: vendido,
        /* Los leads que la cuenta le asignó en el mes: de ahí sale la conversión. */
        leads: [14, 16, 11, 9][i],
        difference: meta ? vendido - meta : null, progress: avance,
        state: avance === null ? 'sin-meta' : avance >= cifrasMes.green ? 'lograda' : avance >= cifrasMes.amber ? 'cerca' : 'apoyo',
      };
    });
    return {
      period,
      canEdit: true,
      commission: {kind: 'percent', amount: 10},
      missing: [],
      sales: ventas,
      mine: {closings: 2, sold: 3720},
      goal: {target: Number(cifrasMes.teamGoal) || null, progress: cifrasMes.teamGoal ? (ventas.total / cifrasMes.teamGoal) * 100 : null, missing: Math.max(0, Number(cifrasMes.teamGoal || 0) - ventas.total)},
      settings: {...cifrasMes},
      team: equipo,
      profitability: {
        sales: ventas.total, ads, adsFromAccount: 2340, adsOther: Number(cifrasMes.otherAds || 0), adsReason: null,
        commissions: comisiones, profit: ganancia, fixedCosts: Number(cifrasMes.fixedCosts || 0),
        net: ganancia - Number(cifrasMes.fixedCosts || 0),
        costPerClosing: ads / ventas.closings,
        returnPerHundred: (ventas.total / ads) * 100,
        marketingCost: ads + Number(cifrasMes.fixedCosts || 0),
        marginPerUnit: ganancia / ventas.total,
      },
    };
  };

  const MANUAL = '<header class="manual-cover"><h1>Guía de uso · Ágora Digital App</h1><p>Versión 1.4.3 · Inicio, operación y administración</p></header>\n<p>El HTML de prueba contiene datos ficticios. Los cambios de la demo se reinician al recargar, excepto los archivos y datos del Estudio guardados en este navegador. En hosting, las operaciones dependen de las APIs configuradas.</p>\n<h2>1. Acceso y selección de empresa</h2><p>Ingresa con correo y contraseña. Master y Superadmin acceden a Agencia; elige Subcuentas y pulsa «Ir a subcuenta». Los usuarios locales acceden a su empresa. Para cambiar de empresa, vuelve a Agencia y selecciona la subcuenta autorizada.</p>\n<h2>2. Inicio de módulos</h2><p>El centro muestra los módulos agrupados por proceso. Usa sus tarjetas o el menú superior para abrirlos; los accesos restringidos indican que tu rol no tiene permiso. El historial permite recuperar módulos visitados. El selector de mes gobierna los reportes que muestran periodo.</p>\n<h2>3. Gestión comercial</h2><p>Gestión Comercial resume el periodo. Equipo, Embudo y publicidad, Ventas, Facturación, Costos, Catálogo y Tendencias muestran el detalle. Revisa Calidad de datos antes de cerrar cifras; las metas y umbrales se editan en Configuración mensual. Un registro facturado no confirma por sí mismo un cobro.</p>\n<h2>4. Atención y seguimiento</h2><p>En Bandeja abre una conversación y revisa su canal antes de responder. Contactos reúne fichas y etiquetas; Oportunidades organiza el embudo. Tareas permite crear seguimientos y revisar pendientes, vencidas y completadas. ARP Comercial muestra los indicadores operativos.</p>\n<h2>5. Convenios</h2><p>Contratos permite consultar y crear convenios. Abre el detalle para revisar las acciones disponibles según estado y permisos. Con convenio agrupa personas con convenios y Registro permite consultar movimientos. Firma, documentos y envíos reales requieren el servidor.</p>\n<h2>6. Usuarios y perfil</h2><p>La gestión de usuarios de Agencia contiene personal de agencia. Para administrar usuarios locales, entra primero a la subcuenta y abre Usuarios y roles. El acceso de perfil permite modificar tus datos y foto; no modifica tu rol. Cierra sesión desde el menú de cuenta cuando termines.</p>\n<h2>7. Ajustes y auditoría</h2><p>Ajustes reúne conexiones e identidad del espacio. Auditoría presenta movimientos y exportación del periodo. Los permisos deben validarse también en el servidor. La demo no conecta GoHighLevel, correo, Meta ni servicios de firma reales.</p>\n<h2>8. Estudio</h2><p>El Estudio permite trabajar con archivos de texto y datos CSV/JSON. Guardarlos no publica cambios en hosting ni modifica los módulos operativos. Las funciones de IA que requieren backend permanecen identificadas como desconectadas. No guardes contraseñas o información sensible en el almacenamiento del navegador.</p>\n<h2>9. Ayuda y recuperación</h2><p>Si una carga falla, utiliza Reintentar o vuelve al Inicio. Comprueba la sesión, el acceso a la subcuenta y la conexión. El botón «Imprimir / guardar PDF» abre el diálogo de impresión de tu navegador; selecciona Guardar como PDF para conservar esta guía.</p>';

  /* =====================================================================================
     Resumen del negocio, operación del día, anuncios y eficacia
     ===================================================================================== */
  const hoyYmd = ymd(ahora);
  const LOGO_ASOCIM = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 56"><rect width="220" height="56" rx="10" fill="%23282E49"/><text x="110" y="35" text-anchor="middle" font-family="Helvetica,Arial" font-size="22" font-weight="700" fill="%23FFFFFF" letter-spacing="3">ASOCIM</text></svg>'.replace(/%23/g, '#'));

  let espacio = {id: 1, nombre: 'ASOCIM', subcuenta: 'ASOCIM · Admisiones', logo: LOGO_ASOCIM, bloqueado: false};

  /* Las cifras de noviembre son las del tablero real; los meses de atrás, su historia. */
  const MRR_MES = {'2026-06': 0, '2026-07': 0, '2026-08': 0, '2026-09': 0, '2026-10': 285, '2026-11': 285, '2026-12': 285};
  const mesesAtras = (ym, n) => { const [y, m] = ym.split('-').map(Number); const out = []; for (let i = n - 1; i >= 0; i--) { const d = new Date(y, m - 1 - i, 1); out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`); } return out; };
  const mrrDe = ym => MRR_MES[ym] ?? (ym > '2026-12' ? 285 : 0);
  function resumenDe(period) {
    const mrr = mrrDe(period), antes = mrrDe(mesesAtras(period, 2)[0]);
    const activos = mrr > 0 ? 1 : 0;
    return {
      period, mrr, mrrAnterior: antes, clientesActivos: activos, enPausa: 0,
      recaudado: period === mesActual ? 0 : mrr,
      arr: mrr * 12,
      caja: period === mesActual ? -783 : -763,
      churn: 0, churnPct: 0,
      egresos: 20, inversion: 0,
      serie: mesesAtras(period, 6).map(ym => ({ym, v: mrrDe(ym)})),
      espacios: [{id: 1, nombre: espacio.nombre, subcuenta: espacio.subcuenta, logo: espacio.logo, plan: 'Plan Operación', precio: 285, mrr, estado: 'activo'}],
      superadmins: [{id: 1, nombre: 'Eduardo Bocaranda', correo: 'info@agoradigital.app'}],
      porVencer: [{cliente: 'ASOCIM', monto: mrr, vence: `${period}-30`, estado: 'pendiente'}],
    };
  }

  /* Las cifras del día salen de los tableros de la operación. */
  const diaOperacion = {
    conversacionesDia: 14, atendidas: 9,
    sinLeer: 5, sinLeerAyer: 14,
    sinLeerPorAsesor: [{nombre: 'Lisbella Montilla', n: 3}, {nombre: 'María Carvajal', n: 1}, {nombre: 'Rosangélica Peña', n: 1}],
    slaIncumplido: 4, slaIncumplidoAyer: 43, slaTolerancia: 3,
    slaPromedio: '3 min 12 s', slaPromedioSeg: 192, slaPromedioSegAyer: 420, slaMetaTexto: '5 min',
    peorRespuesta: '18 min', peorAsesor: 'María Carvajal',
    leads: 4, leadsAyer: 27,
    leadsPorFuente: [{fuente: 'Redes sociales', n: 4}],
    leadsPorUsuario: [{nombre: 'Lisbella Montilla', n: 2}, {nombre: 'María Carvajal', n: 1}, {nombre: 'Rosangélica Peña', n: 1}],
  };

  const adsDe = period => ({
    estado: 'ok', period,
    dia: 18.4, diaAyer: 23.15, mes: 412.75, presupuestoMes: 600,
    impresiones: 12480, clics: 311, conversiones: 9,
    costePorConversion: 45.86, costePorLead: 4.6, retornoPorCien: 69.1,
    actualizado: 'hace 20 minutos',
    campanas: [
      {nombre: 'Diplomado · Captación', gasto: 210.4, conversiones: 5},
      {nombre: 'Retargeting del sitio', gasto: 118.25, conversiones: 3},
      {nombre: 'Mensajes a WhatsApp', gasto: 84.1, conversiones: 1},
    ],
  });

  const eficaciaDe = userId => {
    const todos = [
      {nombre: 'Lisbella Montilla', cerradas: 4, valor: 6240, dias: 8},
      {nombre: 'María Carvajal', cerradas: 3, valor: 4320, dias: 11},
      {nombre: 'Rosangélica Peña', cerradas: 2, valor: 2700, dias: 16},
    ];
    const lista = userId ? todos.filter(u => u.nombre === userId) : todos;
    const cerradas = lista.reduce((a, u) => a + u.cerradas, 0);
    const valor = lista.reduce((a, u) => a + u.valor, 0);
    const dias = Math.round(lista.reduce((a, u) => a + u.dias * u.cerradas, 0) / Math.max(1, cerradas));
    return {
      pipelineId: '', userId,
      pipelines: [{id: 'pl_1', nombre: 'Admisiones 2026'}, {id: 'pl_2', nombre: 'Reinscripciones'}],
      usuarios: todos.map(u => ({id: u.nombre, nombre: u.nombre})),
      duracionPromedioDias: dias, duracionTexto: `${dias} días`, metaDias: 10,
      valorTotal: valor, cerradas, ticket: cerradas ? Math.round(valor / cerradas) : 0,
      velocidad: Math.round((valor / Math.max(1, dias)) * 30), tasaCierre: 27,
      porUsuario: lista,
    };
  };

  /* ---------- Tareas y recordatorios ---------- */
  const masDias = n => ymd(ahora + n * DAY);
  let recordatorios = {cv_1002: {at: masDias(1) + 'T09:00', nota: 'Quedó en confirmar con su esposo'}};
  let tareas = [
    {id: 'tk_rec_cv_1002', titulo: 'Escribirle a Luis Alberto Ramírez', detalle: 'Quedó en confirmar con su esposo', responsable: 'Lisbella Montilla', vence: masDias(1), hora: '09:00', estado: 'pendiente', origen: 'recordatorio', contacto: 'Luis Alberto Ramírez', contactId: 'ct_7002', conversationId: 'cv_1002'},
    {id: 'tk_101', titulo: 'Llamar a Mariana para cerrar la inscripción', detalle: 'Pidió el convenio por WhatsApp', responsable: 'Lisbella Montilla', vence: hoyYmd, hora: '14:30', estado: 'pendiente', origen: 'recordatorio', contacto: 'Mariana Pérez Alvarado', contactId: 'ct_7001', conversationId: 'cv_1001'},
    {id: 'tk_102', titulo: 'Enviar el convenio a Carla Sofía', detalle: 'Ya pasó el comprobante', responsable: 'María Carvajal', vence: hoyYmd, hora: '16:00', estado: 'pendiente', origen: 'convenio', contacto: 'Carla Sofía Montes', contactId: 'ct_7003', conversationId: 'cv_1003'},
    {id: 'tk_103', titulo: 'Revisar los leads sin asignar de ayer', detalle: '', responsable: 'Rosangélica Peña', vence: ymd(ahora - DAY), hora: '09:00', estado: 'pendiente', origen: 'sistema', contacto: null},
    {id: 'tk_104', titulo: 'Completar el monto de la venta de José Gregorio', detalle: 'Quedó ganada sin monto', responsable: 'María Carvajal', vence: ymd(ahora - 2 * DAY), hora: '10:00', estado: 'pendiente', origen: 'sistema', contacto: 'José Gregorio Díaz', contactId: 'ct_7004'},
    {id: 'tk_105', titulo: 'Seguimiento a Andreína sobre horarios', detalle: 'Preguntó por los sábados', responsable: 'Lisbella Montilla', vence: masDias(3), hora: '11:00', estado: 'pendiente', origen: 'recordatorio', contacto: 'Andreína Castillo', contactId: 'ct_7005', conversationId: 'cv_1005'},
    {id: 'tk_106', titulo: 'Confirmar el pago de Pedro Luis', detalle: '', responsable: 'María Carvajal', vence: ymd(ahora - DAY), hora: '15:00', estado: 'hecha', origen: 'manual', contacto: 'Pedro Luis Hernández', contactId: 'ct_7006'},
    {id: 'tk_107', titulo: 'Cargar las fotos nuevas del manual', detalle: '', responsable: 'Eduardo Bocaranda', vence: masDias(5), hora: '08:00', estado: 'pendiente', origen: 'manual', contacto: null},
  ];

  /* ---------- Auditoría ---------- */
  const auditoria = [
    {id: 'a1', at: isoStamp(ahora - 12 * 60000), actor: 'Lisbella Montilla', rol: 'Ejecutivo', accion: 'edito', accionTexto: 'Editó la ficha', modulo: 'contacto', objeto: 'Ficha del contacto', contacto: 'Mariana Pérez Alvarado', contactId: 'ct_7001', dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema', antes: {telefono: '+58 412 1122334'}, despues: {telefono: '+58 412 1122999'}},
    {id: 'a2', at: isoStamp(ahora - 48 * 60000), actor: 'María Carvajal', rol: 'Ejecutivo', accion: 'envio', accionTexto: 'Envió un mensaje', modulo: 'conversacion', objeto: 'WhatsApp', contacto: 'Carla Sofía Montes', contactId: 'ct_7003', dispositivo: 'App Android', ip: '190.202.14.21', origen: 'sistema'},
    {id: 'a3', at: isoStamp(ahora - 95 * 60000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'creo', accionTexto: 'Creó un convenio', modulo: 'convenio', objeto: 'Convenio v1', contacto: 'Andreína Castillo', contactId: 'ct_7005', dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema'},
    {id: 'a4', at: isoStamp(ahora - 3 * 3600000), actor: 'Rosangélica Peña', rol: 'Ejecutivo', accion: 'asigno', accionTexto: 'Reasignó la conversación', modulo: 'conversacion', objeto: 'De Rosangélica a Lisbella', contacto: 'José Gregorio Díaz', contactId: 'ct_7004', dispositivo: 'Chrome · macOS', ip: '190.202.14.33', origen: 'sistema'},
    {id: 'a5', at: isoStamp(ahora - 5 * 3600000), actor: 'Sincronización', rol: 'Sistema', accion: 'creo', accionTexto: 'Importó un contacto', modulo: 'contacto', objeto: 'Alta automática', contacto: 'Yoselin Guerrero', contactId: 'ct_7007', dispositivo: 'Servidor', ip: '', origen: 'ghl'},
    {id: 'a6', at: isoStamp(ahora - 8 * 3600000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'edito', accionTexto: 'Cambió la comisión', modulo: 'ajuste', objeto: 'ASOCIM · Admisiones', contacto: null, dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema', antes: {comision: '8 %'}, despues: {comision: '10 %'}},
    {id: 'a7', at: isoStamp(ahora - 26 * 3600000), actor: 'Lisbella Montilla', rol: 'Ejecutivo', accion: 'entro', accionTexto: 'Entró al sistema', modulo: 'sesion', objeto: 'Código por correo', contacto: null, dispositivo: 'App Android', ip: '190.202.14.21', origen: 'sistema'},
    {id: 'a8', at: isoStamp(ahora - 28 * 3600000), actor: 'María Carvajal', rol: 'Ejecutivo', accion: 'exporto', accionTexto: 'Exportó contactos', modulo: 'contacto', objeto: '48 fichas', contacto: null, dispositivo: 'Chrome · Windows', ip: '190.202.14.44', origen: 'sistema'},
    {id: 'a9', at: isoStamp(ahora - 32 * 3600000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'firmo', accionTexto: 'Registró una firma', modulo: 'convenio', objeto: 'Convenio v1 firmado', contacto: 'Mariana Pérez Alvarado', contactId: 'ct_7001', dispositivo: 'Servidor', ip: '', origen: 'sistema'},
    {id: 'a10', at: isoStamp(ahora - 50 * 3600000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'elimino', accionTexto: 'Eliminó un borrador', modulo: 'convenio', objeto: 'Borrador v1', contacto: 'Wilmer Antonio Salas', contactId: 'ct_7009', dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema'},
    {id: 'a11', at: isoStamp(ahora - 72 * 3600000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'creo', accionTexto: 'Agregó una persona', modulo: 'usuario', objeto: 'Rosangélica Peña · Ejecutivo', contacto: null, dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema'},
    {id: 'a12', at: isoStamp(ahora - 96 * 3600000), actor: 'Eduardo Bocaranda', rol: 'SuperAdmin', accion: 'bloqueo', accionTexto: 'Bloqueó el espacio', modulo: 'ajuste', objeto: 'Mantenimiento de 20 minutos', contacto: null, dispositivo: 'Chrome · Windows', ip: '190.202.14.8', origen: 'sistema'},
  ];

  /* ---------- Usuarios del sistema ---------- */
  let usuariosSistema = [
    {id: 'us_1', nombre: 'Eduardo Bocaranda', correo: 'info@agoradigital.app', rol: 'SUPERADMIN', estado: 'activo', origen: 'sistema', ultimoAcceso: isoStamp(ahora - 9 * 60000), yo: true},
    {id: 'us_2', nombre: 'Rosa Natalia Ortega', correo: 'rortega@asocim.org', rol: 'ADMIN', estado: 'activo', origen: 'contratos', ultimoAcceso: isoStamp(ahora - 2 * 3600000)},
    {id: 'us_3', nombre: 'Lisbella Montilla', correo: 'lmontilla@asocim.org', rol: 'EJECUTIVO', estado: 'activo', origen: 'ghl', ultimoAcceso: isoStamp(ahora - 20 * 60000)},
    {id: 'us_4', nombre: 'María Carvajal', correo: 'mcarvajal@asocim.org', rol: 'EJECUTIVO', estado: 'activo', origen: 'ghl', ultimoAcceso: isoStamp(ahora - 55 * 60000)},
    {id: 'us_5', nombre: 'Rosangélica Peña', correo: 'rpena@asocim.org', rol: 'EJECUTIVO', estado: 'activo', origen: 'ghl', ultimoAcceso: isoStamp(ahora - 4 * 3600000)},
    {id: 'us_6', nombre: 'Daniel Figueroa', correo: 'dfigueroa@asocim.org', rol: 'SUPERVISOR', estado: 'activo', origen: 'contratos', ultimoAcceso: isoStamp(ahora - 26 * 3600000)},
    {id: 'us_7', nombre: 'Marisol Vera', correo: 'mvera@asocim.org', rol: 'CONSULTA', estado: 'inactivo', origen: 'contratos', ultimoAcceso: isoStamp(ahora - 40 * DAY)},
  ];

  /* =====================================================================================
     Las rutas
     ===================================================================================== */
  /* =====================================================================================
     Reporte de gestión comercial
     -------------------------------------------------------------------------------------
     Misma forma que entrega el extractor del tablero comercial: un reporte completo por
     período en «reports», el libro de facturación del año en «inv» y, aparte, lo que el
     sistema guarda (configuración mensual y observaciones resueltas).

     Para probar el sistema con información de verdad se arman los últimos 48 meses. Las
     ventas de 2025 y 2026 son las de referencia; los años anteriores salen de una curva
     con la estacionalidad del negocio (picos en marzo y septiembre, valle en junio y
     diciembre), de modo que el histórico, las tendencias y las comparaciones interanuales
     tengan siempre con qué responder.
     ===================================================================================== */
  const cts = d => Math.round(d * 100);
  const MESESGC = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const ESTACION = [1.06, 1.04, 0.86, 0.85, 0.93, 0.54, 0.93, 1.26, 1.53, 1.20, 0.75, 0.35];
  const BASE_ANIO = {2021: 5200, 2022: 6100, 2023: 6900, 2024: 7900};
  /* Ventas de cada mes, en dólares. Lo cerrado va escrito; lo anterior se calcula. */
  const VENTAS_MES = {
    2025: [6800, 4300, 4900, 2400, 4597, 4100, 4250, 5650, 11230, 10900, 6800, 3200],
    2026: [9620, 9480, 7830, 7690, 8410, 4900, 8480, 11490, 13895.25, 12480, 10260, null],
  };
  const ALUMNOS_MES = {
    2025: [38, 32, 34, 22, 30, 28, 31, 34, 157, 148, 96, 44],
    2026: [58, 54, 46, 44, 52, 38, 56, 101, 170, 149, 122, null],
  };
  for (const anio of Object.keys(BASE_ANIO).map(Number)) {
    VENTAS_MES[anio] = ESTACION.map((f, m) => Math.round(BASE_ANIO[anio] * f * (1 + ((anio * 13 + m * 7) % 9) / 60) * 100) / 100);
    ALUMNOS_MES[anio] = VENTAS_MES[anio].map((v, m) => Math.round(v / (58 + (m % 5) * 4)));
  }
  const ventaDe = (y, m) => (VENTAS_MES[y] || [])[m] ?? null;
  const alumnosDe = (y, m) => (ALUMNOS_MES[y] || [])[m] ?? null;

  const CODIGOS = ['DGP', 'DAT', 'DCO', 'MCE', 'CIN', 'CFO'];
  const PROGRAMAS = [
    ['DGP', 'Diplomado en Gerencia de Proyectos', 85, '6 meses'],
    ['DAT', 'Diplomado en Analítica de Datos', 95, '6 meses'],
    ['DCO', 'Diplomado en Comunicación Organizacional', 80, '5 meses'],
    ['MCE', 'Maestría en Comunicación Corporativa y Empresarial', 160, '24 meses'],
    ['CIN', 'Curso de Inglés Instrumental', 45, '4 meses'],
    ['CFO', 'Curso de Formación de Facilitadores', 55, '3 meses'],
  ];
  /* El equipo del departamento y el peso de cada quien en las ventas y en los inscritos. */
  const ASESORAS = [
    {name: 'Lcda. Lisbella Moreno', venta: 0.3375, alumnos: 0.329, mezcla: [0.25, 0.20, 0.16, 0.14, 0.14, 0.11]},
    {name: 'Lcda. Yurbis Ramírez', venta: 0.2538, alumnos: 0.259, mezcla: [0.23, 0.20, 0.18, 0.16, 0.14, 0.09]},
    {name: 'Br. Karina Díaz', venta: 0.2188, alumnos: 0.224, mezcla: [0.24, 0.18, 0.18, 0.16, 0.13, 0.11]},
    {name: 'Lcda. Andreína Sosa', venta: 0.1899, alumnos: 0.188, mezcla: [0.25, 0.19, 0.19, 0.16, 0.12, 0.09]},
  ];
  const CAMPANAS = [
    ['Captación de nuevos estudiantes', 'Mercadeo', 0.344, 'Mensajes', 'Aragua y Carabobo 25-45', 412, 28],
    ['Maestría · tráfico a formulario', 'Mercadeo', 0.267, 'Tráfico', 'Profesionales 28-50', 268, 19],
    ['Cursos cortos · alcance local', 'Mercadeo', 0.224, 'Alcance', 'Maracay 20-40', 196, 12],
    ['Remarketing · formularios abiertos', 'Mercadeo', 0.165, 'Conversiones', 'Visitantes de 30 días', 124, 9],
  ];

  /* Reparte un total entre pesos sin perder ni un céntimo: el último se lleva el resto. */
  const repartir = (total, pesos) => {
    const out = pesos.map(w => Math.round(total * w));
    out[out.length - 1] = total - out.slice(0, -1).reduce((n, v) => n + v, 0);
    return out;
  };

  function reporteGC(anio, mIdx) {
    const venta = ventaDe(anio, mIdx), alumnos = alumnosDe(anio, mIdx);
    if (venta == null) return null;
    const vendidos = repartir(cts(venta), ASESORAS.map(a => a.venta));
    const inscritos = repartir(alumnos, ASESORAS.map(a => a.alumnos));
    const ADV = ASESORAS.map((a, i) => {
      const v = vendidos[i], cuota1 = Math.round(v * 0.22);
      const counts = repartir(inscritos[i], a.mezcla);
      return {name: a.name, counts, inscritos: inscritos[i], vendido: v,
        recurrencia: Math.round(v * 0.55), cuota1, entradas: Math.round(v * 0.09),
        pendiente: Math.round(v * 0.6462), com10: Math.round(cuota1 * 0.10),
        com7: Math.round(cuota1 * 0.07), com3: Math.round(cuota1 * 0.03),
        contactos: Math.round(inscritos[i] / 0.1647), canales: repartir(Math.round(inscritos[i] / 0.1647), [0.15, 0.32, 0.23, 0.30])};
    });
    const suma = k => ADV.reduce((n, a) => n + a[k], 0);
    const total = {name: 'TOTAL', counts: CODIGOS.map((_, i) => ADV.reduce((n, a) => n + a.counts[i], 0)),
      inscritos: suma('inscritos'), vendido: suma('vendido'), recurrencia: suma('recurrencia'), cuota1: suma('cuota1'),
      entradas: suma('entradas'), pendiente: suma('pendiente'), com10: suma('com10'), com7: suma('com7'), com3: suma('com3')};
    const metaReal = Math.round(venta / 0.82 / 500) * 500, metaIdeal = Math.round(metaReal * 1.25);
    const metas = repartir(cts(metaReal), ASESORAS.map(a => a.venta));
    const cupos = Math.round(alumnos / 0.794 / 2) * 2;
    const pubMes = Math.round(venta * 0.205 / 10) * 10;
    const gastoOp = Math.round(venta * 0.102 / 10) * 10;

    const hist = {cols: ['Mes', `Monto ${anio - 1}`, `P ${anio - 1}`, `Monto ${anio}`, `P ${anio}`, 'Variación $', 'Variación P'],
      rows: MESESGC.map((mes, i) => {
        const a1 = ventaDe(anio - 1, i), a2 = ventaDe(anio, i), p1 = alumnosDe(anio - 1, i), p2 = alumnosDe(anio, i);
        return [mes, a1 == null ? null : cts(a1), p1, a2 == null ? null : cts(a2), p2,
          a1 && a2 ? a2 / a1 - 1 : null, p1 && p2 ? p2 / p1 - 1 : null];
      }), formulas: {}};
    const sumaSerie = (y, f) => MESESGC.reduce((n, _, i) => n + (f(y, i) || 0), 0);
    hist.total = ['TOTAL', cts(sumaSerie(anio - 1, ventaDe)), sumaSerie(anio - 1, alumnosDe),
      cts(sumaSerie(anio, ventaDe)), sumaSerie(anio, alumnosDe),
      sumaSerie(anio, ventaDe) / sumaSerie(anio - 1, ventaDe) - 1,
      sumaSerie(anio, alumnosDe) / sumaSerie(anio - 1, alumnosDe) - 1];

    const ventas = [];
    ADV.forEach((a, fi) => {
      for (let i = 0; i < 6; i++) {
        const prog = PROGRAMAS[(fi + i) % PROGRAMAS.length], monto = Math.round(a.vendido / 6);
        ventas.push([`${anio}${mIdx}-${fi}-${i}`, 'CV ' + a.name.replace(/^(Lcda\.|Br\.)\s*/, ''), i + 1,
          `${anio}-${String(mIdx + 1).padStart(2, '0')}-${String(3 + i * 4).padStart(2, '0')}`, '',
          ['María Rangel', 'José Pérez', 'Andrés Gil', 'Luisa Fermín', 'Carla Ojeda', 'Pedro Silva'][i] + ` ${fi + 1}`,
          `V-1*.***.${String(100 + i)}`, prog[0], monto, Math.round(monto * 0.55), Math.round(monto * 0.22),
          Math.round(monto * 0.06), Math.round(monto * 0.09), 0, '', Math.round(a.pendiente / 6),
          `${String(anio).slice(2)}${String(mIdx + 1).padStart(2, '0')}${fi}${i}`,
          Math.round(monto * 0.022), Math.round(monto * 0.0154), Math.round(monto * 0.0066), 10 + i]);
      }
    });

    const camp = CAMPANAS.map(([nombre, resp, peso, obj, target, res, vts]) => {
      const inv = Math.round(cts(pubMes) * peso), dias = 30 - (mIdx % 5);
      return [`Campaña ${nombre}`, resp, inv, Math.round(inv / dias),
        `${anio}-${String(mIdx + 1).padStart(2, '0')}-01`, `${anio}-${String(mIdx + 1).padStart(2, '0')}-${dias}`,
        dias, obj, target, res, vts, Math.round(inv / res), Math.round(inv / vts)];
    });

    return {
      gc: {title: `REPORTE DE GESTIÓN COMERCIAL | ${MESESGC[mIdx]} ${anio}`,
        codes: CODIGOS,
        sedes: [['Sede Maracay', 3], ['Sede Valencia', 3]],
        cols: ['Asesora', 'Inscritos', 'Vendido', 'Recurrencia', '1.ª cuota', 'Entradas', 'Pendiente', '10 %', '7 %', '3 %'],
        advisors: ADV, total},
      metas: {
        recaudacion: {title: 'Metas de recaudación', items: [['Meta Real', metaReal], ['Meta Ideal', metaIdeal]]},
        pupitres: {title: 'Metas de pupitres', items: [['Total de Pupitres:', cupos], ['Pupitres Ocupados:', Math.max(0, cupos - alumnos - 12)],
          ['Pupitres Vendidos:', alumnos], ['Pupitres Disponibles:', 12], ['De Ocupación %:', (cupos - 12) / cupos]]},
        ocupacion: {title: 'Metas de ocupación', items: [['Meta Real:', cupos], ['Total Inscrito:', alumnos],
          ['Meta Ideal:', Math.round(cupos * 1.1)], ['Logro:', alumnos / cupos]]},
      },
      valor: {title: 'Metas por asesora', cols: ['Asesora', 'Meta', 'Logrado', '%'],
        rows: [...ADV.map((a, i) => [a.name, metas[i], a.vendido, a.vendido / metas[i]]),
          ['TOTAL', cts(metaReal), total.vendido, total.vendido / cts(metaReal)]]},
      cierre: {title: 'Cierre de ventas', cols: ['Asesora', 'Contactos', 'Inscritos', 'Conversión'],
        rows: [...ADV.map(a => [a.name, a.contactos, a.inscritos, a.inscritos / a.contactos]),
          ['TOTAL', suma('contactos'), total.inscritos, total.inscritos / suma('contactos')]]},
      catalog: {title: 'LEYENDA DE PROGRAMAS', cols: ['Acrónimo', 'Programa', 'Mensualidad', 'Duración'], rows: PROGRAMAS},
      hist,
      ads: {
        title: `GESTIÓN META ADS | ${MESESGC[mIdx]} ${anio}`,
        rows: camp,
        total: {label: 'TOTAL DE CAMPAÑAS', inversion: cts(pubMes), gastoDiario: Math.round(cts(pubMes) / 30),
          resultados: CAMPANAS.reduce((n, c) => n + c[5], 0), ventas: CAMPANAS.reduce((n, c) => n + c[6], 0),
          cpl: Math.round(cts(pubMes) / CAMPANAS.reduce((n, c) => n + c[5], 0)),
          cpc: Math.round(cts(pubMes) / CAMPANAS.reduce((n, c) => n + c[6], 0))},
        legend: {title: 'Cómo leer la tabla', items: [['Resultados', 'Contactos que dejó la campaña en Meta.'],
          ['Ventas', 'Inscripciones atribuidas a la campaña.'], ['Costo por contacto', 'Inversión entre resultados.'],
          ['Costo por venta', 'Inversión entre ventas cerradas.']]},
        eff: {title: `Contactos por canal · ${MESESGC[mIdx].toLowerCase()} ${anio}`,
          cols: ['Asesora', 'Meta', 'CRM', 'Formulario', 'Presencial', 'Total', 'Cierres', 'Logro'],
          rows: [...ADV.map(a => [a.name, ...a.canales, a.contactos, a.inscritos, a.inscritos / a.contactos]),
            ['TOTAL', ...[0, 1, 2, 3].map(i => ADV.reduce((n, a) => n + a.canales[i], 0)),
              suma('contactos'), total.inscritos, total.inscritos / suma('contactos')]]},
      },
      rent: {title: 'RENTABILIDAD DEL MES', cols: ['Indicador', 'Valor', 'Indicador (%)'],
        rows: [['Ingresos Brutos', venta, null], ['Inversión Meta ADS', pubMes, pubMes / venta],
          ['Gastos Operativos', gastoOp, null],
          ['Margen de Utilidad', Math.round((venta - pubMes - gastoOp - total.com10 / 100) * 100) / 100, null],
          ['CPA', Math.round(pubMes / alumnos * 100) / 100, null],
          ['ROAS', Math.round(venta / pubMes * 100) / 100, null]]},
      varAnual: {
        ventas: {title: 'Variación anual de ventas', items: [[`${MESESGC[mIdx]} ${anio - 1}`, ventaDe(anio - 1, mIdx) || 0],
          [`${MESESGC[mIdx]} ${anio}`, venta], ['Variación', ventaDe(anio - 1, mIdx) ? venta / ventaDe(anio - 1, mIdx) - 1 : 0]]},
        inventario: {title: 'Variación anual de inscritos', items: [[`${MESESGC[mIdx]} ${anio - 1}`, alumnosDe(anio - 1, mIdx) || 0],
          [`${MESESGC[mIdx]} ${anio}`, alumnos], ['Variación', alumnosDe(anio - 1, mIdx) ? alumnos / alumnosDe(anio - 1, mIdx) - 1 : 0]]},
      },
      sales: {cols: ['id', 'hoja', 'n', 'fecha', 'fechaOrigen', 'cliente', 'cedula', 'producto', 'montoTotal', 'recurrencia',
        'cuota1', 'inscripcion', 'entradas', 'solvencia', 'mesCulminacion', 'pendiente', 'factura', 'com10', 'com7', 'com3', 'fila'], rows: ventas},
      cvSheets: ADV.map(a => ({sheet: 'CV ' + a.name.replace(/^(Lcda\.|Br\.)\s*/, ''),
        totals: {'Monto Total Vendido': a.vendido, 'Comisiones del Departamento 10%': a.com10, 'Pendiente por Cobrar': a.pendiente}})),
      costs: (() => {
        const nomina = [['Dirección comercial', 'Fijo', cts(600)], ['Asesoras (4)', 'Fijo', cts(1200)], ['Mercadeo', 'Fijo', cts(450)], ['Administración', 'Fijo', cts(380)]];
        const herr = [['CRM Go High Level', 'Mensual', cts(97)], ['Meta Business', 'Mensual', cts(0)], ['Correo corporativo', 'Mensual', cts(36)],
          ['Hospedaje y dominio', 'Mensual', cts(28)], ['Digitel datos', 'Mensual', cts(18), '18,00 $'], ['Digitel voz', 'Mensual', cts(14), '14,00 $']];
        const sumR = r => r.reduce((n, x) => n + x[2], 0);
        const herrTotalReal = sumR(herr), herrTotal = herrTotalReal - cts(32);
        const nominaTotal = sumR(nomina), fijos = nominaTotal + herrTotal;
        const inversion = [['Publicidad Meta ADS', 'Variable', cts(pubMes)], ['Interfaz de WhatsApp', 'Variable', cts(45)]];
        const comisiones = [['Comisiones del departamento', 'Variable', total.com10 + cts(30)]];
        const variables = sumR(inversion) + sumR(comisiones);
        const proximos = [['Mejora del CRM', 'Previsto', cts(350)], ['Material POP', 'Previsto', cts(180), '180 $'], ['Feria educativa', 'Previsto', cts(420), '420 $']];
        return {title: `ESTRUCTURA DE COSTO | ${MESESGC[mIdx]} ${anio}`, fixedTitle: 'Gastos fijos', varTitle: 'Gastos variables',
          nomina: {title: 'Nómina del departamento', cols: ['Cargo', 'Tipo', 'Monto'], rows: nomina, total: nominaTotal, totalLabel: 'Total de nómina'},
          herr: {title: 'Herramientas y servicios', cols: ['Servicio', 'Tipo', 'Monto'], rows: herr, total: herrTotal, totalReal: herrTotalReal, formula: '=SUMA(D18:D29)'},
          inversion: {title: 'Inversión del mes', cols: ['Concepto', 'Tipo', 'Monto'], rows: inversion},
          comisiones: {title: 'Comisiones', cols: ['Concepto', 'Tipo', 'Monto'], rows: comisiones},
          resumen: {title: 'Resumen de costos', items: [['Gastos fijos', fijos], ['Gastos variables', variables], ['Costo de mercadeo y ventas', fijos + variables]]},
          proximos: {title: 'Próximas inversiones', label: 'Total previsto', cols: ['Concepto', 'Tipo', 'Monto'], rows: proximos,
            total: cts(350), totalReal: sumR(proximos)},
          fijos: {label: 'Gastos fijos', total: fijos}, variables: {label: 'Gastos variables', total: variables}};
      })(),
      issues: [
        {id: `com-base-${anio}-${mIdx}`, sev: 'Alta', title: 'La base de las comisiones no coincide', area: 'Comisiones',
          detail: 'El resumen de gestión calcula el 10 % sobre la primera cuota y la estructura de costos usa otra base. Falta definir cuál manda y cuándo se devenga.', src: 'GESTIÓN COMERCIAL · V11:Y16'},
        {id: `herr-suma-${anio}-${mIdx}`, sev: 'Alta', title: 'La suma de herramientas deja fuera dos líneas', area: 'Costos',
          detail: 'La fórmula del total de herramientas no alcanza las dos últimas líneas de Digitel, así que el gasto fijo sale por debajo.', src: 'Estructura de Costo · D18:D31'},
        {id: `cpl-camp-${anio}-${mIdx}`, sev: 'Media', title: 'Campañas sin resultados por día', area: 'Publicidad',
          detail: 'Las campañas traen el resultado del mes completo, de modo que el costo por contacto no se puede seguir día a día.', src: 'GESTION META ADS · J9:J22'},
        {id: `fact-prog-${anio}-${mIdx}`, sev: 'Baja', title: 'Programas que no están en la leyenda', area: 'Facturación',
          detail: 'Los libros de facturas usan acrónimos que la leyenda del reporte no recoge; conviene agregarlos para que los informes cuadren.', src: 'FACTURAS'},
      ],
      sources: [
        {file: `Reporte de Gestión Comercial ${MESESGC[mIdx]} ${anio}.xlsx`, sha256: 'b7c41f9ad0e25a3c8f16d4b0e9a7c2538d41f0b6ac93e7d5128fa460b3c97e1d',
          sheets: [{sheet: 'GESTIÓN COMERCIAL', cells: 1842, captured: 1842, filler: 214, unmapped: []},
            {sheet: 'GESTION META ADS', cells: 386, captured: 386, filler: 41, unmapped: []},
            ...ADV.map(a => ({sheet: 'CV ' + a.name.replace(/^(Lcda\.|Br\.)\s*/, ''), cells: 512, captured: 512, filler: 96, unmapped: []}))]},
        {file: `Estructura de Costo ${anio}.xlsx`, sha256: '3f8a1c6d59b204e7fa8c0d31b5e9726408fd1a7c3b26e590d4af81c097b3e2a5',
          sheets: [{sheet: 'Estructura de Costo', cells: 268, captured: 268, filler: 32, unmapped: []}]},
      ],
      meta: {generated: `${anio}-${String(mIdx + 1).padStart(2, '0')}-02T09:14:00`},
    };
  }

  /* Los últimos 48 meses, hasta el mes en curso. */
  const REPORTES_GC = (() => {
    const hoy = new Date(ahora), out = {};
    for (let i = 47; i >= 0; i--) {
      const d = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1);
      const r = reporteGC(d.getFullYear(), d.getMonth());
      if (r) out[`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`] = r;
    }
    return out;
  })();
  const ANIO_FACT = Number(Object.keys(REPORTES_GC).at(-1).slice(0, 4));

  /* El libro de facturación del año del último reporte. */
  const FACTURAS_GC = (() => {
    const asesores = ASESORAS.map(a => a.name.replace(/^(Lcda\.|Br\.)\s*/, '').toLowerCase());
    const rows = [], hojas = [];
    let n = 0;
    for (let mes = 1; mes <= 12; mes++) {
      const alumnos = alumnosDe(ANIO_FACT, mes - 1);
      if (alumnos == null) break;
      hojas.push(MESESGC[mes - 1].toUpperCase());
      const cuantos = Math.max(6, Math.round(alumnos / 6));
      for (let i = 0; i < cuantos; i++, n++) {
        const prog = PROGRAMAS[n % PROGRAMAS.length];
        rows.push([mes, i + 1, asesores[n % asesores.length], `${ANIO_FACT}-${String(mes).padStart(2, '0')}-${String(2 + (i % 26)).padStart(2, '0')}`, '',
          ['Ana Pérez', 'Luis Mora', 'Rosa Niño', 'Iván Peña', 'Sara Lugo', 'Hugo Ruiz'][n % 6] + ' ' + (n + 1),
          `V-1*.***.${String(200 + (n % 700))}`, `0414-***${String(10 + (n % 80))}`, n % 7 === 0 ? '' : `0000${1200 + n}`,
          cts(prog[2] * (3 + (n % 4))), '', prog[0], prog[0], `${ANIO_FACT} - ${mes <= 6 ? 'I' : 'II'}`, n % 2 ? 'Nocturno' : 'Diurno',
          `cliente${n + 1}@correo.com`, 12 + i]);
      }
    }
    return {cols: ['mes', 'n', 'asesor', 'fecha', 'fechaOrigen', 'cliente', 'cedula', 'telefono', 'factura', 'monto', 'montoOrigen',
      'programa', 'familia', 'lapso', 'horario', 'correo', 'fila'], rows, sheets: hojas};
  })();

  const REPORTE_GC = {reports: REPORTES_GC, inv: FACTURAS_GC};
  let configMensual = {};
  let observacionesGC = {};

  const ok = d => ({status: 200, body: d});
  const err = (m, status = 400, code) => ({status, body: {error: m, code}});

  /* Indicadores de Agencia: los mismos que calcula el servidor. Solo ASOCIM (id 1) tiene
     oportunidades y convenios de prueba; las demás subcuentas empiezan en cero. */
/* 2.1.15 · Paleta por cuenta y logos solo PNG (igual que el servidor). */
const demoPaletas = {};
/* 2.1.16 · Tipo de cuenta, productos y personalización por subcuenta (igual que el servidor). */
const demoPerfiles = {
  1: {tipoCliente: 'servicios', negocio: 'servicios', cobro: 'recurrente', frecuencia: 'mensual', diaCobro: 5, factura: true, notas: ''},
  2: {tipoCliente: 'producto', negocio: 'suscripciones', cobro: 'recurrente', frecuencia: 'mensual', diaCobro: 1, factura: true, notas: ''},
};
const demoProductos = {
  1: {rubros: [{id: 'r-dip', nombre: 'Diplomados', descripcion: 'Programas de varios meses'}, {id: 'r-cur', nombre: 'Cursos cortos', descripcion: ''}, {id: 'r-adm', nombre: 'Trámites', descripcion: 'Constancias y documentos'}],
    productos: [
      {id: 'p-1', rubroId: 'r-dip', nombre: 'Diplomado en Gerencia', codigo: 'DIP-GER', tipo: 'servicio', cobro: 'recurrente', precio: 120, moneda: 'USD', frecuencia: 'mensual', duracion: 6, stock: null, descripcion: 'Seis mensualidades.', activo: true},
      {id: 'p-2', rubroId: 'r-dip', nombre: 'Diplomado en Mercadeo Digital', codigo: 'DIP-MKT', tipo: 'servicio', cobro: 'recurrente', precio: 95, moneda: 'USD', frecuencia: 'mensual', duracion: 4, stock: null, descripcion: '', activo: true},
      {id: 'p-3', rubroId: 'r-cur', nombre: 'Curso de Oratoria', codigo: 'CUR-ORA', tipo: 'servicio', cobro: 'unico', precio: 80, moneda: 'USD', frecuencia: null, duracion: null, stock: null, descripcion: 'Un solo pago al inscribirse.', activo: true},
      {id: 'p-4', rubroId: 'r-adm', nombre: 'Constancia de estudios', codigo: 'CON-EST', tipo: 'producto', cobro: 'venta', precio: 15, moneda: 'USD', frecuencia: null, duracion: null, stock: null, descripcion: '', activo: true},
      {id: 'p-5', rubroId: 'r-adm', nombre: 'Inscripción', codigo: 'INS', tipo: 'servicio', cobro: 'unico', precio: 30, moneda: 'USD', frecuencia: null, duracion: null, stock: null, descripcion: '', activo: true},
    ]},
  2: {rubros: [{id: 'r-pla', nombre: 'Planes de internet', descripcion: ''}, {id: 'r-equ', nombre: 'Equipos', descripcion: ''}, {id: 'r-ser', nombre: 'Servicios técnicos', descripcion: ''}],
    productos: [
      {id: 'p-1', rubroId: 'r-pla', nombre: 'Fibra 100 Mbps', codigo: 'FIB-100', tipo: 'servicio', cobro: 'recurrente', precio: 25, moneda: 'USD', frecuencia: 'mensual', duracion: null, stock: null, descripcion: '', activo: true},
      {id: 'p-2', rubroId: 'r-pla', nombre: 'Fibra 300 Mbps', codigo: 'FIB-300', tipo: 'servicio', cobro: 'recurrente', precio: 40, moneda: 'USD', frecuencia: 'mensual', duracion: null, stock: null, descripcion: '', activo: true},
      {id: 'p-3', rubroId: 'r-ser', nombre: 'Instalación', codigo: 'INST', tipo: 'servicio', cobro: 'unico', precio: 50, moneda: 'USD', frecuencia: null, duracion: null, stock: null, descripcion: '', activo: true},
      {id: 'p-4', rubroId: 'r-equ', nombre: 'Router WiFi 6', codigo: 'RTR-W6', tipo: 'producto', cobro: 'venta', precio: 60, moneda: 'USD', frecuencia: null, duracion: null, stock: 12, descripcion: '', activo: true},
    ]},
};
const demoPersonal = {};
const demoEtiquetas = {};
const DEMO_PERFIL = {tipoCliente: ['servicios', 'producto', 'mixto', 'interna'], negocio: ['servicios', 'productos', 'mixto', 'suscripciones'], cobro: ['recurrente', 'unico', 'venta', 'mixto']};
function demoPerfil(p) {
  if (p === null || p === '' || p === undefined) return null;
  if (typeof p !== 'object') return false;
  for (const [k, ok] of Object.entries(DEMO_PERFIL)) if (!ok.includes(p[k])) return false;
  const frec = ['recurrente', 'mixto'].includes(p.cobro) ? (p.frecuencia || 'mensual') : null;
  if (frec && !['semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual'].includes(frec)) return false;
  const dia = p.diaCobro === null || p.diaCobro === '' || p.diaCobro === undefined ? null : Number(p.diaCobro);
  if (dia !== null && !(Number.isInteger(dia) && dia >= 1 && dia <= 28)) return false;
  return {tipoCliente: p.tipoCliente, negocio: p.negocio, cobro: p.cobro, frecuencia: frec, diaCobro: dia, factura: p.factura === true, notas: String(p.notas || '').slice(0, 300)};
}
const demoLogoOk = v => typeof v === 'string' && (/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(v) || /^\/assets\/[\w./-]+\.png$/.test(v));
const demoPaletaOk = p => p && typeof p === 'object' && /^#[0-9a-f]{6}$/i.test(p.principal || '') && /^#[0-9a-f]{6}$/i.test(p.acento || '');
  function demoCobroMensual(c) {
    if (!c) return null;
    const mes = {mensual: 1, trimestral: 3, semestral: 6, anual: 12, unico: 0}, m = (v, f) => mes[f] ? (Number(v) || 0) / mes[f] : 0;
    return Math.round((m(c.plan?.monto, c.plan?.frecuencia) + (c.servicios || []).filter(s => s.activo).reduce((t, s) => t + m(s.monto, s.frecuencia), 0)) * 100) / 100;
  }
  function demoIndicadores(id) {
    if (Number(id) !== 1) return {ingresos30d: 0, conveniosPorFirmar: 0};
    const desde = ahora - 30 * DAY;
    return {
      ingresos30d: oportunidades.filter(o => o.status === 'won' && o.wonAt && Date.parse(o.wonAt) >= desde).reduce((t, o) => t + (Number(o.monetaryValue) || 0), 0),
      conveniosPorFirmar: contratos.filter(c => c.estado === 'ENVIADO').length,
    };
  }
  const RUTAS = [
    /* ---------- Contratos ---------- */
    ['GET', /^yo$/, () => demoAutenticado ? ok({usuario: yo}) : err('Debes iniciar sesión.', 401)],
    ['POST', /^login$/, (m, body) => {
      let email = String(body.correo || body.email || '').trim().toLowerCase();
      let password = String(body.clave || body.password || '');
      // 2.1.16: «admin» sin @ es admin@agora.test, igual que en el modo pruebas del servidor.
      if (email && !email.includes('@')) email += '@agora.test';
      // Cuenta anterior de la demo: se sigue aceptando para no romper pruebas viejas.
      if (email === 'demo.agencia@agora.test' && password === 'AgoraDemo!2026') { email = 'admin@agora.test'; password = '123456789'; }
      const user=directory.find(u=>u.activo && u.correo===email && u.clave===password);
      if (!user) return err('Correo o contraseña incorrectos.',401);
      refreshIdentity(user);
      demoActiveTenant=agencyRole()?null:user.tenants[0];
      demoAutenticado=true;return ok({usuario:yo});
    }],
    ['POST', /^logout$/, () => { demoAutenticado = false; demoActiveTenant=null; return ok({ok: true}); }],
    ['POST', /^clave$/, () => ok({ok: true})],
    ['GET', /^contratos$/, () => ok({contratos:demoActiveTenant===1?contratos:[]})],
    ['GET', /^contratos\/([a-f0-9]{24})$/, m => {
      const row = contratos.find(c => c.uid === m[1]);
      return row && demoActiveTenant===1 ? ok(detalleDe(row)) : err('Ese convenio no existe en esta subcuenta.', 404);
    }],
    ['POST', /^contratos\/calculo$/, (m, body) => {
      const cuota = Number(String(body.cuota).replace(',', '.')) || 0, meses = Number(body.meses) || 0, porMes = Number(body.cuotas_por_mes) || 1;
      const desde = new Date(Number(body.anio), Number(body.mes) - 1, 1);
      const cuotas = cuotasDe(cuota, meses, desde, porMes);
      const precio = cuota * meses;
      return ok({precio_curso: precio.toFixed(2), cargos: [], total_a_pagar: precio.toFixed(2), cuotas});
    }],
    ['POST', /^contratos$/, (m, body) => {
      const nuevo = {uid: nuevoUid(), cliente: body.cliente?.nombre || 'Sin nombre', correo: body.cliente?.correo || '', estado: 'BORRADOR', version: 1, creado_en: utcStamp(Date.now())};
      contratos = [nuevo, ...contratos];
      eventos = [{tipo: 'CREADO', cliente: nuevo.cliente, uid: nuevo.uid, creado_en: nuevo.creado_en, detalle: {origen: 'ghl'}}, ...eventos];
      return ok({uid: nuevo.uid});
    }],
    ['POST', /^contratos\/([a-f0-9]{24})\/enviar$/, m => {
      const row = contratos.find(c => c.uid === m[1]); if (row) row.estado = 'ENVIADO';
      eventos = [{tipo: 'ENVIADO', cliente: row?.cliente, uid: m[1], creado_en: utcStamp(Date.now()), detalle: {version: row?.version}}, ...eventos];
      return ok({enlace: 'https://contratos.asocim.org/firmar/' + m[1].slice(0, 20), vence: utcStamp(Date.now() + 7 * DAY)});
    }],
    ['POST', /^contratos\/([a-f0-9]{24})\/enlace$/, m => ok({enlace: 'https://contratos.asocim.org/firmar/' + m[1].slice(4, 24), vence: utcStamp(Date.now() + 7 * DAY)})],
    ['POST', /^contratos\/([a-f0-9]{24})\/revocar$/, m => {
      const row = contratos.find(c => c.uid === m[1]); if (row) row.estado = 'REVOCADA';
      return ok({ok: true});
    }],
    ['POST', /^contratos\/([a-f0-9]{24})\/editar$/, m => {
      const row = contratos.find(c => c.uid === m[1]);
      if (row && row.estado !== 'BORRADOR') { row.version += 1; row.estado = 'BORRADOR'; }
      return ok({version: row?.version || 1});
    }],
    ['POST', /^contratos\/([a-f0-9]{24})\/cliente$/, (m, body) => {
      const row = contratos.find(c => c.uid === m[1]);
      if (row) { row.cliente = body.nombre || row.cliente; row.correo = body.correo || row.correo; }
      return ok({ok: true});
    }],
    ['DELETE', /^contratos\/([a-f0-9]{24})$/, m => { contratos = contratos.filter(c => c.uid !== m[1]); return ok({ok: true}); }],
    ['GET', /^usuarios$/, () => ok({usuarios:demoActiveTenant===1?usuarios:(agencyUsers[demoActiveTenant]||[])})],
    ['POST', /^usuarios$/, (m, body) => { usuarios = [...usuarios, {id: usuarios.length + 1, nombre: body.nombre, correo: body.correo, rol: body.rol, activo: 1}]; return ok({ok: true}); }],
    ['POST', /^usuarios\/rol$/, (m, body) => { const u = usuarios.find(x => x.id === body.id); if (u) u.rol = body.rol; return ok({ok: true}); }],
    ['POST', /^usuarios\/clave$/, () => ok({ok: true})],
    ['POST', /^usuarios\/activar$/, (m, body) => { const u = usuarios.find(x => x.id === body.id); if (u) u.activo = 1; return ok({ok: true}); }],
    ['POST', /^usuarios\/desactivar$/, (m, body) => { const u = usuarios.find(x => x.id === body.id); if (u) u.activo = 0; return ok({ok: true}); }],
    ['GET', /^registro$/, () => ok({eventos:demoActiveTenant===1?eventos:[]})],
    ['POST', /^ghl\/contactos\/buscar$/, (m, body) => {
      const q = String(body.consulta || '').toLowerCase();
      const r = CONTACTOS_GHL.filter(c => (c.nombre + ' ' + c.correo + ' ' + c.telefono).toLowerCase().includes(q));
      return ok({contactos: r, aviso: r.length ? '' : 'No hay contactos con esa búsqueda en la subcuenta.'});
    }],

    /* ---------- Ágora ---------- */
    ['GET', /^ui\/texts$/, () => ok({global:textOverrides.global||{},tenant:demoAutenticado&&demoActiveTenant?textOverrides[String(demoActiveTenant)]||{}:{}})],
    ['GET', /^status$/, () => ok({maintenance: false})],
    ['GET', /^health$/, () => ok({status: 'ok'})],
    ['GET', /^me$/, () => demoAutenticado ? ok(agoraMe) : err('Debes iniciar sesión.', 401)],
    ['POST', /^team-login\/request$/, () => ok({sent: true})],
    ['POST', /^team-login\/verify$/, () => ok({ok: true})],
    ['GET', /^workspace\/connections$/, () => ok({items: conexiones.filter(c => allowedTenant(c.id)).map(c => ({id: c.id, name: c.name, currency: c.currency})), reason: null})],
    /* Agencia: no depende del cliente activo. Privilegios de agencia comprobados. */
    ['GET', /^agency\/accounts$/, () => ok({items: agencyAccounts.filter(a=>allowedTenant(a.id)).map(a => ({...a,venceDias:diasHasta(a.vence),usuarios:agencyUsers[a.id]?.length || (a.id===1 ? a.usuarios : 0),...demoIndicadores(a.id),cobroMensual:demoCobroMensual(a.cobros),paleta:demoPaletas[a.id]||null,perfil:demoPerfiles[a.id]||null}))})],
    ['PUT', /^agency\/billing$/, (m,b) => {
      if(!master()) return err('Solo el dueño Master.',403);
      const a=agencyAccounts.find(a=>String(a.id)===String(b.id));if(!a)return err('Subcuenta no encontrada.',404);
      const monto=v=>/^\d{1,9}(\.\d{1,2})?$/.test(String(v??''))?Number(v):null, frec=['mensual','trimestral','semestral','anual','unico'];
      const plan={monto:monto(b.plan?.monto??'0'),frecuencia:b.plan?.frecuencia||'mensual'};
      if(plan.monto===null||!frec.includes(plan.frecuencia)) return err('Monto o frecuencia del plan inválidos.',422);
      const servicios=[];for(const x of (Array.isArray(b.servicios)?b.servicios:[]).slice(0,30)){const mm=monto(x.monto);if(!String(x.nombre||'').trim()||mm===null||!frec.includes(x.frecuencia))return err('Servicio inválido.',422);servicios.push({id:x.id||Math.random().toString(36).slice(2,12),nombre:String(x.nombre).trim().slice(0,120),monto:mm,frecuencia:x.frecuencia,activo:x.activo!==false});}
      const antes=demoCobroMensual(a.cobros);a.cobros={plan,servicios,actualizado:isoStamp(Date.now()).replace('T',' ').slice(0,19)};if(typeof b.nombrePlan==='string')a.plan=b.nombrePlan.trim().slice(0,80);
      if('corte' in b){if(b.corte!==null&&b.corte!==''&&!/^\d{4}-\d{2}-\d{2}$/.test(String(b.corte)))return err('Fecha de vencimiento inválida.',422);a.vence=b.corte||null;}
      return ok({ok:true,id:a.id,cobroMensual:demoCobroMensual(a.cobros),antes,vence:a.vence,venceDias:diasHasta(a.vence)});
    }],
    ['POST', /^agency\/accounts$/, (m,b) => {
      if(!b.nombre?.trim() || !b.subcuenta?.trim()) return err('Nombre y subcuenta requeridos.',422);
      if(b.logo && !demoLogoOk(b.logo)) return err('El logo debe ser un PNG.',422);
      if(b.paleta && !demoPaletaOk(b.paleta)) return err('Paleta inválida.',422);
      const perfilN = demoPerfil(b.perfil); if (perfilN === false) return err('Revisa el tipo de cuenta.',422);
      const codigo = (b.codigo || b.subcuenta.slice(0,3).toUpperCase() + '-01').trim().toUpperCase();
      const id=nextAgencyId++;const acc={id,nombre:b.nombre.trim(),subcuenta:b.subcuenta.trim(),codigo,rubro:b.rubro||'Otro',moneda:b.moneda||'USD',estado:b.estado||'activa',plan:b.plan||'',vence:b.vence||null,fechaCreacion:isoDia(0),ultimoAcceso:null,usuarios:0,notas:b.notas||'',logo:b.logo||null};
      agencyAccounts.push(acc);agencyUsers[id]=[];demoPaletas[id]=b.paleta||null;demoPerfiles[id]=perfilN;conexiones.push({id,name:acc.subcuenta,currency:'USD'});return ok({id});
    }],
    ['PUT', /^agency\/accounts$/, (m,b) => {
      const a=agencyAccounts.find(a=>String(a.id)===String(b.id));if(!a)return err('Subcuenta no encontrada.',404);
      if(!b.nombre?.trim()||!b.subcuenta?.trim()) return err('Ambos nombres son obligatorios.',422);
      if(b.logo && !demoLogoOk(b.logo)) return err('El logo debe ser un PNG.',422);
      if(b.paleta && !demoPaletaOk(b.paleta)) return err('Paleta inválida.',422);
      if(b.paleta!==undefined)demoPaletas[a.id]=b.paleta||null;
      if(b.perfil!==undefined){const pf=demoPerfil(b.perfil);if(pf===false)return err('Revisa el tipo de cuenta.',422);demoPerfiles[a.id]=pf;}
      a.nombre=b.nombre.trim();a.subcuenta=b.subcuenta.trim();if(b.codigo)a.codigo=b.codigo.trim().toUpperCase();if(b.rubro)a.rubro=b.rubro;if(b.moneda)a.moneda=b.moneda;if(b.estado)a.estado=b.estado;if(b.notas!=null)a.notas=b.notas;if(b.plan!==undefined)a.plan=b.plan;if(b.vence!==undefined)a.vence=b.vence||null;if(b.logo!==undefined)a.logo=b.logo;const c=conexiones.find(c=>String(c.id)===String(a.id));if(c)c.name=a.subcuenta;return ok(a);
    }],
    ['GET', /^agency\/users$/, (m,b,q) => {
      const id=q.get('accountId');if(!agencyAccounts.some(a=>String(a.id)===String(id)))return err('Subcuenta inexistente.',404);
      return ok({items:agencyUsers[id] || (id==='1' ? usuariosSistema : [])});
    }],
    ['POST', /^agency\/users$/, (m,b) => {
      if(!agencyAccounts.some(a=>String(a.id)===String(b.accountId)))return err('Subcuenta inexistente.',404);
      if(!b.nombre?.trim()||!b.correo?.trim())return err('Nombre y correo requeridos.',422);
      const r={id:'ag_'+Date.now(),nombre:b.nombre.trim(),correo:b.correo.trim(),rol:b.rol||'CONSULTA',estado:'activo',activo:true};
      if(r.rol==='SUPERADMIN') return err('El privilegio de Agencia no es delegable.',403);
      (agencyUsers[b.accountId] ||= []).push(r);return ok({ok:true});
    }],
    ['DELETE', /^agency\/users$/, (m,b,q) => {
      const id=q.get('accountId'),uid=q.get('userId');const list=agencyUsers[id]?.length?agencyUsers[id]:(id==='1'?usuariosSistema:[]);
      const u=list.find(x=>String(x.id)===String(uid));if(!u)return err('Usuario no encontrado en esta subcuenta.',404);
      if(u.rol==='MASTER')return err('Este acceso está protegido.',403);u.activo=false;u.estado='inactivo';return ok({ok:true,id:uid});
    }],
    ['POST', /^workspace\/active$/, (m,b) => {
      const id=Number(b.connectionId);if(!agencyRole() || !allowedTenant(id) || !agencyAccounts.some(a=>a.id===id))return err('Subcuenta no autorizada.',403);
      demoActiveTenant=id;return ok({ok:true,accountId:id});
    }],
    ['GET', /^workspace\/dashboard$/, (m, b, q) => ok(tablero(q.get('from'), q.get('to')))],
    ['GET', /^workspace\/board-month$/, (m, b, q) => ok(mesBloque(q.get('period') || mesActual))],
    ['POST', /^workspace\/board-month$/, (m, body) => { cifrasMes = {...cifrasMes, ...body, goals: {...cifrasMes.goals, ...(body.goals || {})}}; return ok({saved: true}); }],
    ['GET', /^workspace\/self$/, () => ok({options: EQUIPO, linked: 'us_01', detected: 'us_01'})],
    ['PUT', /^workspace\/self$/, () => ok({ok: true})],
    ['GET', /^workspace\/won-alert$/, () => ok({count: 0, escalate: false, canEdit: true, team: {missing: 0, bySeller: []}})],
    ['GET', /^workspace\/pulse$/, () => ok({token: 'demo-token-1', unread: conversaciones.reduce((s, c) => s + c.unreadCount, 0), reason: null, retryAfter: 0})],
    ['GET', /^workspace\/inbox$/, (m, b, q) => {
      let items = conversaciones;
      if (q.get('status') === 'unread') items = items.filter(c => c.unreadCount > 0);
      if (q.get('direction') === 'inbound') items = items.filter(c => c.lastMessageDirection !== 'outbound');
      const query = (q.get('query') || '').toLowerCase();
      if (query) items = items.filter(c => (c.contactName + ' ' + c.lastMessageBody).toLowerCase().includes(query));
      return ok({
        items, total: conversaciones.length, unread: conversaciones.reduce((s, c) => s + c.unreadCount, 0),
        limit: Number(q.get('limit')) || 20, hasMore: false, nextCursor: null,
        channels: {TYPE_WHATSAPP: 'WhatsApp', TYPE_INSTAGRAM: 'Instagram', TYPE_EMAIL: 'Email', TYPE_SMS: 'SMS', TYPE_FACEBOOK: 'Messenger'},
        reminders: recordatorios,
        restricted: false, partial: false, syncIntervalSeconds: 300, syncedAt: isoStamp(ahora), state: {},
      });
    }],
    ['GET', /^workspace\/conversation$/, (m, b, q) => {
      const c = conversaciones.find(x => x.id === q.get('conversationId'));
      return c ? ok({conversation: c}) : err('Esa conversación ya no está disponible.', 404);
    }],
    ['GET', /^workspace\/messages$/, (m, b, q) => ok({
      items: mensajesDe(q.get('conversationId')), hasMore: false, lastMessageId: null,
      channels: [{type: 'WhatsApp', label: 'WhatsApp', available: true, needsSubject: false}, {type: 'Email', label: 'Email', available: true, needsSubject: true}, {type: 'SMS', label: 'SMS', available: false, reason: 'needs_phone'}],
    })],
    ['POST', /^workspace\/send$/, (m, body, q) => {
      const c = conversaciones.find(x => x.id === q.get('conversationId'));
      if (c) { c.lastMessageBody = body.message || body.body || ''; c.lastMessageDirection = 'outbound'; c.lastMessageDate = isoStamp(Date.now()); }
      return ok({message: {id: 'nuevo_' + Date.now(), body: body.message || body.body || '', direction: 'outbound', messageType: 'TYPE_WHATSAPP', status: 'delivered', dateAdded: isoStamp(Date.now())}});
    }],
    ['POST', /^workspace\/read$/, (m, b, q) => { const c = conversaciones.find(x => x.id === q.get('conversationId')); if (c) c.unreadCount = 0; return ok({ok: true}); }],
    ['GET', /^workspace\/templates$/, () => ok({configured: false, items: [], error: ''})],
    ['GET', /^workspace\/assign$/, () => ok({assignees: EQUIPO, assignedTo: 'us_01'})],
    ['POST', /^workspace\/assign$/, (m, body) => ok({assignedTo: body.assignedTo || null, assignedName: EQUIPO.find(u => u.id === body.assignedTo)?.name || null, moved: 0})],
    ['GET', /^workspace\/contact$/, (m, b, q) => {
      const c = conversaciones.find(x => x.id === q.get('conversationId')) || conversaciones[0];
      const contacto = CONTACTOS_AG.find(x => x.id === c.contactId) || CONTACTOS_AG[0];
      return ok({contact: contacto, fields: [], assignees: EQUIPO, canEdit: true, notes: [], tasks: [], appointments: []});
    }],
    ['GET', /^workspace\/activity(\/.*)?$/, () => ok({items: [], notes: [], tasks: [], appointments: []})],
    ['POST', /^workspace\/activity(\/.*)?$/, () => ok({ok: true})],
    ['GET', /^workspace\/contacts$/, (m, b, q) => {
      const query = (q.get('query') || '').toLowerCase();
      const items = query ? CONTACTOS_AG.filter(c => (c.name + ' ' + c.email + ' ' + c.phone).toLowerCase().includes(query)) : CONTACTOS_AG;
      return ok({items, total: items.length, restricted: false, partial: false});
    }],
    ['GET', /^workspace\/record$/, (m, b, q) => {
      const c = CONTACTOS_AG.find(x => x.id === q.get('contactId')) || CONTACTOS_AG[0];
      return ok({contact: c, fields: [{id: 'cf_ced', name: 'Cédula', folder: 'Datos personales'}, {id: 'cf_pro', name: 'Programa de interés', folder: 'Admisión'}], assignees: EQUIPO, canEdit: true});
    }],
    ['POST', /^workspace\/record$/, () => ok({ok: true})],
    ['GET', /^workspace\/tags$/, () => ok({items: demoEtiquetas[demoActiveTenant] || ['interesado', 'diplomado', 'convenio', 'firmado', 'seguimiento', 'instagram', 'referido', 'frío']})],
    ['GET', /^workspace\/contact-fields$/, () => ok({items: [], folders: []})],
    ['GET', /^workspace\/pipelines$/, () => ok({pipelines: EMBUDOS, assignees: EQUIPO})],
    ['GET', /^workspace\/board$/, (m, b, q) => {
      const pid = q.get('pipelineId') || 'pl_1';
      const embudo = EMBUDOS.find(p => p.id === pid) || EMBUDOS[0];
      const abiertas = oportunidades.filter(o => o.status === 'open' && o.pipelineId === 'pl_1');
      return ok({
        pipelines: EMBUDOS.map(p => ({id: p.id, name: p.name})), pipelineId: embudo.id,
        stages: embudo.stages.map(s => {
          const items = abiertas.filter(o => o.pipelineStageId === s.id);
          return {id: s.id, name: s.name, total: items.length, value: items.reduce((a, o) => a + o.monetaryValue, 0), items};
        }),
        total: abiertas.length, canEdit: true, restricted: false, partial: false,
      });
    }],
    ['GET', /^workspace\/opportunities$/, () => ok({items: oportunidades.slice(0, 2), pipelines: EMBUDOS, assignees: EQUIPO})],
    ['POST', /^workspace\/opportunity\/(new|edit|status)$/, (m, body, q) => {
      const o = oportunidades.find(x => x.id === q.get('opportunityId'));
      if (o) Object.assign(o, body);
      return ok({ok: true, opportunity: o || null});
    }],
    ['GET', /^workspace\/won$/, (m, b, q) => {
      const ganadas = oportunidades.filter(o => o.status === 'won');
      const items = ganadas.map(o => ({...o, value: o.monetaryValue, missing: !o.monetaryValue}));
      return ok({
        totals: {count: items.length, sum: items.reduce((s, o) => s + (o.value || 0), 0), missing: items.filter(o => o.missing).length},
        items, bySeller: EQUIPO.slice(0, 2).map((u, i) => ({id: u.id, name: u.name, count: [1, 1][i], missing: 0})),
        canSeeAll: true, canEdit: true, partial: false,
      });
    }],
    ['GET', /^workspace\/sync$/, () => ok({ok: true})],

    /* ---------- Conexiones (administración de Ágora) ---------- */
    ['GET', /^admin\/diagnostics$/, () => ok({ready: true})],
    ['GET', /^admin\/integrations$/, () => ok({
      items: conexiones,
      accessChannel: {connectionId: 1, ready: true},
      backup: {at: isoStamp(ahora - 0.6 * DAY), ok: true, encrypted: true},
    })],
    ['GET', /^admin\/announcements$/, () => ok({items: [{id: 1, title: 'Nuevos módulos disponibles', at: isoStamp(ahora - 2 * DAY), seen: 4, expiresAt: null}]})],
    ['POST', /^admin\/announcements$/, () => ok({id: 2})],
    ['DELETE', /^admin\/announcements\/\d+$/, () => ok({ok: true})],
    ['GET', /^devices$/, () => ok({
      items: [
        {label: 'Chrome · Windows', platform: 'web', created_at: utcStamp(ahora - 9 * DAY), last_seen_at: utcStamp(ahora - 0.02 * DAY), last_sent_at: utcStamp(ahora - 0.3 * DAY)},
        {label: 'Pixel 10a', platform: 'android', created_at: utcStamp(ahora - 4 * DAY), last_seen_at: utcStamp(ahora - 0.05 * DAY), last_sent_at: utcStamp(ahora - 0.4 * DAY)},
      ],
      browser: true, android: true,
      discovery: {at: isoStamp(ahora - 40000), via: 'cron'},
      delivery: {at: isoStamp(ahora - 38000), sent: 3, configured: true},
      queue: {sent: 42, pending: 0, failed: 0},
      externalCheck: null, externalCheckUse: null,
    })],
    ['POST', /^devices\/test$/, () => ok({results: [{label: 'Chrome · Windows', platform: 'web', result: 'sent'}]})],

    /* ---------- Resumen del negocio ---------- */
    ['GET', /^workspace\/resumen$/, (m, b, q) => ok(resumenDe(q.get('period') || mesActual))],

    /* ---------- Operación del día, anuncios y eficacia de ventas ---------- */
    ['GET', /^workspace\/dia$/, () => ok(diaOperacion)],
    ['GET', /^workspace\/ads$/, (m, b, q) => ok(adsDe(q.get('period') || mesActual))],
    ['GET', /^workspace\/eficacia$/, (m, b, q) => ok(eficaciaDe(q.get('userId') || ''))],

    /* ---------- Tareas ---------- */
    ['GET', /^workspace\/tareas$/, () => ok({items: tareas, resumen: {pendientes: tareas.filter(t => t.estado !== 'hecha').length, hechas: tareas.filter(t => t.estado === 'hecha').length}})],
    ['POST', /^workspace\/tareas$/, (m, body) => {
      tareas = [{id: 'tk_' + (900 + tareas.length), titulo: body.titulo, detalle: body.detalle || '', responsable: body.responsable || yo.nombre,
        vence: body.vence || hoyYmd, hora: body.hora || '', estado: 'pendiente', origen: body.origen || 'manual',
        contacto: body.contacto || null, contactId: body.contactId || null, conversationId: body.conversationId || null}, ...tareas];
      return ok({ok: true});
    }],
    ['POST', /^workspace\/tareas\/([a-z0-9_]+)\/estado$/, (m, body) => {
      const t = tareas.find(x => x.id === m[1]); if (t) t.estado = body.estado || 'hecha';
      return ok({ok: true});
    }],

    /* ---------- Recordatorios de seguimiento ---------- */
    ['POST', /^workspace\/reminders$/, (m, body, q) => {
      const id = q.get('conversationId');
      recordatorios[id] = {at: body.at, nota: body.nota || ''};
      const c = conversaciones.find(x => x.id === id);
      tareas = [{id: 'tk_rec_' + id, titulo: `Escribirle a ${c ? c.contactName : 'el contacto'}`, detalle: body.nota || 'Recordatorio puesto desde el chat',
        responsable: yo.nombre, vence: String(body.at).slice(0, 10), hora: String(body.at).slice(11, 16), estado: 'pendiente',
        origen: 'recordatorio', contacto: c ? c.contactName : null, contactId: body.contactId || null, conversationId: id},
        ...tareas.filter(t => t.id !== 'tk_rec_' + id)];
      return ok({at: body.at});
    }],
    ['DELETE', /^workspace\/reminders$/, (m, b, q) => {
      const id = q.get('conversationId');
      delete recordatorios[id];
      tareas = tareas.filter(t => t.id !== 'tk_rec_' + id);
      return ok({ok: true});
    }],

    /* ---------- Auditoría ---------- */
    ['GET', /^workspace\/reporte$/, () => ok({...REPORTE_GC, configuracion: configMensual, observaciones: observacionesGC})],
    ['PUT', /^workspace\/config-mensual$/, (m, body) => {
      if (body && body.periodo) { if (body.config && Object.keys(body.config).length) configMensual[body.periodo] = body.config; else delete configMensual[body.periodo]; }
      return ok({config: configMensual});
    }],
    ['POST', /^workspace\/observaciones$/, (m, body) => {
      if (body && body.id) {
        if (body.resuelta) observacionesGC[body.id] = {resuelta: true, por: yo.nombre, ts: isoStamp(Date.now())};
        else delete observacionesGC[body.id];
      }
      return ok({observaciones: observacionesGC});
    }],
    ['POST', /^workspace\/auditoria$/, () => ok({ok: true})],
    ['GET', /^workspace\/auditoria$/, () => ok({items: auditoria, resumen: {total: auditoria.length}})],

    /* ---------- Usuarios y roles ---------- */
    ['GET', /^workspace\/usuarios$/, () => ok({items:demoActiveTenant===1?usuariosSistema:(agencyUsers[demoActiveTenant]||[])})],
    ['POST', /^workspace\/usuarios$/, (m, body) => {
      const scoped=demoActiveTenant===1?usuariosSistema:(agencyUsers[demoActiveTenant] ||= []); const nuevo={id:'us_n'+Date.now(),nombre:body.nombre,correo:body.correo,rol:body.rol||'EJECUTIVO',estado:'activo',origen:'sistema',ultimoAcceso:null};scoped.push(nuevo);
      return ok({ok: true});
    }],
    ['POST', /^workspace\/usuarios\/rol$/, (m, body) => { const u = (demoActiveTenant===1?usuariosSistema:(agencyUsers[demoActiveTenant]||[])).find(x => String(x.id) === String(body.id)); if (u) u.rol = body.rol; return ok({ok: true}); }],
    ['POST', /^workspace\/usuarios\/estado$/, (m, body) => { const u = (demoActiveTenant===1?usuariosSistema:(agencyUsers[demoActiveTenant]||[])).find(x => String(x.id) === String(body.id)); if (u) u.estado = body.estado; return ok({ok: true}); }],

    /* ---------- Identidad del espacio ---------- */
    ['GET', /^workspace\/productos$/, () => ok({...(demoProductos[demoActiveTenant] || {rubros: [], productos: []}), canEdit: ['MASTER', 'SUPERADMIN', 'ADMIN'].includes(yo.rol)})],
    ['PUT', /^workspace\/productos$/, (m, b) => {
      if (!['MASTER', 'SUPERADMIN', 'ADMIN'].includes(yo.rol)) return err('Requiere administración.', 403);
      const rubros = Array.isArray(b.rubros) ? b.rubros : [], productos = Array.isArray(b.productos) ? b.productos : [];
      const ids = new Set(rubros.map(r => r.id));
      for (const r of rubros) if (!String(r.nombre || '').trim()) return err('Cada rubro necesita un nombre.', 422);
      for (const x of productos) {
        if (!String(x.nombre || '').trim()) return err('Cada producto necesita un nombre.', 422);
        if (!['recurrente', 'unico', 'venta'].includes(x.cobro)) return err(`Elige cómo se cobra «${x.nombre}».`, 422);
        if (x.rubroId && !ids.has(x.rubroId)) return err(`El rubro de «${x.nombre}» no existe.`, 422);
        if (!(Number(x.precio) >= 0)) return err(`Precio inválido en «${x.nombre}».`, 422);
      }
      demoProductos[demoActiveTenant] = {rubros, productos, actualizado: isoStamp(Date.now())};
      return ok({...demoProductos[demoActiveTenant], ok: true, canEdit: true});
    }],
    ['POST', /^workspace\/import$/, (m, b) => {
      if (!['MASTER', 'SUPERADMIN', 'ADMIN'].includes(yo.rol)) return err('Requiere administración.', 403);
      if (b.kind !== 'pipeline') return err('En la demo solo se importan embudos.', 422);
      for (const r of (Array.isArray(b.items) ? b.items : [])) {
        if (!String(r.name || '').trim() || !Array.isArray(r.stages) || !r.stages.length) return err('Cada embudo necesita nombre y etapas.', 422);
        const i = EMBUDOS.findIndex(e => e.id === r.id), row = {id: r.id || 'pl_' + Math.random().toString(36).slice(2, 8), name: r.name.trim(), stages: r.stages.map(x => ({id: x.id, name: String(x.name).trim()}))};
        if (i >= 0) EMBUDOS[i] = row; else EMBUDOS.push(row);
      }
      return ok({ok: true, imported: (b.items || []).length});
    }],
    ['GET', /^workspace\/personalizar$/, () => ok({config: demoPersonal[demoActiveTenant] || null, perfil: demoPerfiles[demoActiveTenant] || null})],
    ['PUT', /^workspace\/personalizar$/, (m, b) => {
      if (!['MASTER', 'SUPERADMIN', 'ADMIN'].includes(yo.rol)) return err('Requiere administración.', 403);
      if (!b.config || typeof b.config !== 'object') return err('Personalización inválida.', 422);
      demoPersonal[demoActiveTenant] = {...JSON.parse(JSON.stringify(b.config)), actualizado: isoStamp(Date.now())};
      return ok({ok: true, config: demoPersonal[demoActiveTenant]});
    }],
    ['PUT', /^workspace\/etiquetas$/, (m, b) => {
      if (!['MASTER', 'SUPERADMIN', 'ADMIN'].includes(yo.rol)) return err('Requiere administración.', 403);
      const items = [...new Set((Array.isArray(b.items) ? b.items : []).map(x => String(x).trim().toLowerCase()).filter(Boolean))];
      if (items.some(x => x.length > 40)) return err('Cada etiqueta debe tener hasta 40 caracteres.', 422);
      demoEtiquetas[demoActiveTenant] = items; return ok({ok: true, items});
    }],
    ['GET', /^workspace\/branding$/, () => ok(demoActiveTenant===1?{...espacio,paleta:demoPaletas[1]||null,perfil:demoPerfiles[1]||null,personal:demoPersonal[1]||null}:{perfil:demoPerfiles[demoActiveTenant]||null,personal:demoPersonal[demoActiveTenant]||null,id:demoActiveTenant,nombre:agencyAccounts.find(a=>a.id===demoActiveTenant)?.nombre||'',subcuenta:agencyAccounts.find(a=>a.id===demoActiveTenant)?.subcuenta||'',logo:agencyAccounts.find(a=>a.id===demoActiveTenant)?.logo||null,paleta:demoPaletas[demoActiveTenant]||null,bloqueado:false})],
    ['POST', /^workspace\/branding$/, (m, body) => {
      if (body.logo && !demoLogoOk(body.logo)) return err('El logo debe ser un PNG.', 422);
      if (body.paleta !== undefined) { if (body.paleta && !demoPaletaOk(body.paleta)) return err('Paleta inválida.', 422); demoPaletas[demoActiveTenant] = body.paleta || null; }
      if (demoActiveTenant !== 1) { const a = agencyAccounts.find(a => a.id === demoActiveTenant); if (a) { if (body.nombre) a.nombre = body.nombre; if (body.subcuenta) a.subcuenta = body.subcuenta; if (body.logo !== undefined) a.logo = body.logo; } return ok({id: demoActiveTenant, nombre: a?.nombre, subcuenta: a?.subcuenta, logo: a?.logo || null, paleta: demoPaletas[demoActiveTenant] || null}); }
      espacio = {...espacio, nombre: body.nombre ?? espacio.nombre, subcuenta: body.subcuenta ?? espacio.subcuenta, logo: body.logo !== undefined ? body.logo : espacio.logo}; return ok({...espacio, paleta: demoPaletas[1] || null}); }],
    ['POST', /^admin\/maintenance$/, (m, body) => { espacio.bloqueado = body.on === true; return ok({maintenance: espacio.bloqueado}); }],

    ['GET', /^admin\/integrations\/\d+\/commission$/, () => ok({current: {kind: 'percent', amount: 10, since: mesActual}, history: [{kind: 'percent', amount: 8, since: '2026-01'}]})],
  ];

  function governanceDemo(route,method,b,q) {
    if(route==='profile') {
      if(method==='GET')return ok({usuario:yo});
      if(method!=='PUT')return err('Método no permitido.',405);
      if(Object.keys(b).some(k=>!['nombre','correo','telefono','foto'].includes(k)))return err('Solo puedes modificar tus datos personales.',422);
      const user=actor(), next={...user,...b};
      if(!next.nombre?.trim()||next.nombre.length>120||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(next.correo)||next.correo.length>254||String(next.telefono||'').length>40)return err('Revisa nombre, correo y teléfono.',422);
      next.correo=next.correo.trim().toLowerCase();
      if(directory.some(u=>u.id!==user.id&&u.correo===next.correo))return err('Ese correo ya está registrado.',409);
      if(next.foto!=null&&(!/^data:image\/jpeg;base64,[a-zA-Z0-9+/=]+$/.test(next.foto)||next.foto.length>500000))return err('Foto no válida.',422);
      Object.assign(user,next);refreshIdentity(user);auditGov('perfil.actualizado',user.id);return ok({usuario:yo});
    }
    if(route==='agency/team') {
      if(!agencyRole())return err('No tienes acceso a la Agencia.',403);
      const visible=u=>master()||u.id===yo.id||(!['MASTER','SUPERADMIN'].includes(u.rol)&&u.tenants.length&&u.tenants.every(allowedTenant));
      if(method==='GET')return ok({items:directory.filter(visible).map(publicIdentity)});
      if(!['POST','PUT'].includes(method))return err('Método no permitido.',405);
      const current=method==='PUT'?directory.find(u=>u.id===Number(b.id)):null;
      if(method==='PUT'&&!current)return err('Usuario inexistente.',404);
      if(current?.rol==='MASTER')return err('El dueño no puede reasignarse ni desactivarse desde esta pantalla. Usa su perfil.',403);
      if(!master()&&(current?.rol==='SUPERADMIN'||!['ADMIN','USUARIO'].includes(b.rol)))return err('Solo el Master administra Superadmin.',403);
      if(current&&!visible(current))return err('Usuario fuera de tu alcance.',403);
      if(!['SUPERADMIN','ADMIN','USUARIO'].includes(b.rol))return err('Rol no asignable.',422);
      const ids=[...new Set((Array.isArray(b.tenants)?b.tenants:[]).map(Number))];
      if(!ids.length||ids.some(id=>!agencyAccounts.some(a=>a.id===id)||!allowedTenant(id)))return err('Selecciona subcuentas autorizadas.',403);
      if(b.rol!=='SUPERADMIN'&&ids.length!==1)return err('El usuario local debe tener una única subcuenta.',422);
      const email=String(b.correo||'').trim().toLowerCase(), name=String(b.nombre||'').trim();
      if(!name||name.length>120||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))return err('Nombre o correo no válido.',422);
      if(directory.some(u=>u.id!==current?.id&&u.correo===email))return err('El correo ya existe.',409);
      if(!current&&String(b.clave||'').length<8)return err('La contraseña demo debe tener al menos 8 caracteres.',422);
      const user=current||{id:Math.max(...directory.map(u=>u.id))+1,agency_id:1,telefono:'',foto:null,clave:b.clave};
      Object.assign(user,{nombre:name,correo:email,rol:b.rol,tenants:ids,activo:b.activo!==false});
      if(!current)directory.push(user);
      auditGov(current?'usuario.editado':'usuario.creado',user.id);return ok({usuario:publicIdentity(user)});
    }
    if(route==='ui/texts') {
      if(method==='GET')return ok({global:textOverrides.global||{},tenant:demoActiveTenant?textOverrides[String(demoActiveTenant)]||{}:{}});
      if(!master())return err('Solo el dueño puede editar textos.',403);
      const scope=String(b.scope||'global');
      if(scope!=='global'&&!agencyAccounts.some(a=>String(a.id)===scope))return err('Ámbito inexistente.',422);
      if(!b.original||String(b.original).length>500||String(b.replacement||'').length>2000)return err('Texto fuera del límite permitido.',422);
      textOverrides[scope] ||= Object.create(null);
      if(method==='DELETE')delete textOverrides[scope][b.original];
      else if(method==='PUT')textOverrides[scope][b.original]=String(b.replacement||'');else return err('Método no permitido.',405);
      auditGov('texto.editado',scope);return ok({ok:true});
    }
    if(route==='agency/studio') {
      if(!agencyRole())return err('Acceso de Agencia requerido.',403);
      if(method==='GET')return ok({configured:false,items:studioJobs.filter(j=>allowedTenant(j.tenantId)),audit:governanceAudit.filter(e=>master()||e.actorId===yo.id).slice(-30)});
      if(method!=='POST')return err('Método no permitido.',405);
      const tenantId=Number(b.tenantId);
      if(!allowedTenant(tenantId)||!agencyAccounts.some(a=>a.id===tenantId))return err('Subcuenta no autorizada.',403);
      if(typeof b.prompt!=='string'||b.prompt.trim().length<10||b.prompt.length>8000)return err('Describe el cambio entre 10 y 8000 caracteres.',422);
      const job={id:'draft_'+(studioJobs.length+1),tenantId,prompt:b.prompt.trim(),status:'BORRADOR_SIN_CONEXION',actorId:yo.id,createdAt:new Date().toISOString()};
      studioJobs.unshift(job);auditGov('ia.borrador',tenantId);return ok({job,configured:false});
    }
    return null;
  }

  /* =====================================================================================
     El interceptor
     ===================================================================================== */
  const responder = (body, status = 200) => new Response(JSON.stringify(body), {status, headers: {'Content-Type': 'application/json'}});

  const demoFetch = async function (input, init = {}) {
    const url = typeof input === 'string' ? input : (input && input.url) || '';
    const metodo = (init.method || (input && input.method) || 'GET').toUpperCase();

    /* El manual de Ágora. */
    if (/manual\/vendedores\.html$/.test(url)) return new Response(MANUAL, {status: 200, headers: {'Content-Type': 'text/html'}});

    const m = String(url).match(/\/api\/(.*)$/);
    if (!m) return nativeFetch(input, init);

    const [ruta, consulta = ''] = m[1].split('?');
    const q = new URLSearchParams(consulta);
    if (!demoAutenticado && !['login','yo','me','status','health','ui/texts'].includes(ruta)) return responder({error: 'Sesión requerida.'}, 401);
    let body = {};
    try { body = init.body ? JSON.parse(init.body) : {}; } catch { body = {}; }
    if (demoAutenticado) {
      const live=actor();
      if (!live?.activo) return responder({error:'La cuenta está desactivada.'},401);
      refreshIdentity(live);
      if (ruta.startsWith('agency/') && !agencyRole()) return responder({error:'Acceso exclusivo de Agencia.'},403);
      if (ruta.startsWith('admin/') && !master()) return responder({error:'Acceso exclusivo del dueño Master.'},403);
      if (ruta==='workspace/active' && !agencyRole()) return responder({error:'No puedes cambiar de subcuenta.'},403);
      if (ruta==='agency/accounts' && metodo==='POST' && !master()) return responder({error:'Solo el Master crea subcuentas.'},403);
      for (const target of [q.get('connectionId'),q.get('accountId'),body.connectionId,body.accountId,...(ruta==='agency/accounts'&&metodo!=='GET'?[body.id]:[])]) {
        if(target!=null && !allowedTenant(target)) return responder({error:'Subcuenta fuera de tu asignación.'},403);
      }
      if (demoActiveTenant!=null && !allowedTenant(demoActiveTenant)) demoActiveTenant=null;
      const result=governanceDemo(ruta,metodo,body,q);
      if(result) return responder(result.body,result.status);
      if (['MASTER','SUPERADMIN'].includes(body.rol) || ['MASTER','SUPERADMIN'].includes(body.role)) return responder({error:'Usa Gestión de usuarios de Agencia para los roles globales.'},403);
      if (yo.rol==='USUARIO' && !['GET','HEAD'].includes(metodo) && !['logout','clave','login'].includes(ruta)) return responder({error:'Tu rol es de consulta.'},403);
    }
    if (demoActiveTenant == null && !/^(agency\/|profile$|ui\/texts$|workspace\/connections$|workspace\/active$|me$|yo$|login$|logout$|status$|health$)/.test(ruta)) return responder({error:'Selecciona primero una subcuenta desde Agencia.'},403);
    // La demo NO reutiliza datos de ASOCIM para otros clientes. Hasta implementar
    // datos simulados completos del segundo tenant, solo muestra conjuntos vacíos.
    if (demoActiveTenant !== 1 && !(window.__agoraDemo?.libres || []).some(rx => rx.test(ruta)) && !/^(agency\/|workspace\/connections$|workspace\/active$|me$|yo$|login$|logout$|workspace\/branding$|workspace\/productos$|workspace\/personalizar$|workspace\/etiquetas$|workspace\/usuarios$|usuarios$|contratos$|registro$|status$|health$)/.test(ruta)) {
      if (metodo !== 'GET') return responder({error:'La operación de este cliente requiere su backend independiente; no se modificaron datos de ASOCIM.'},501);
      return responder({items:[],contratos:[],usuarios:[],eventos:[],conversations:[],contacts:[],opportunities:[],data:[],total:0,unread:0,ok:true,notice:'Sin datos de ejemplo para esta subcuenta.'});
    }

    for (const [verbo, patron, fn] of RUTAS) {
      if (verbo !== metodo) continue;
      const hit = ruta.match(patron);
      if (!hit) continue;
      await new Promise(r => setTimeout(r, 90 + Math.random() * 160));   // un retardo pequeño, para ver los esqueletos
      const {status, body: salida} = fn(hit, body, q);
      return responder(salida, status);
    }
    /* Una ruta que los datos de prueba no cubren: se contesta vacío, para que el módulo
       siga su camino en vez de romperse. */
    return responder({items: [], total: 0, ok: true, demo: 'ruta no cubierta: ' + ruta}, 200);
  };

  /* 2.1.17 · punto de extensión: las capas de construccion/demo/*.js (demo-capas.js) agregan
     rutas con window.__agoraDemo.RUTAS.unshift([...]) y leen el estado con estas funciones. */
  window.__agoraDemo = {
    RUTAS, ok, err, libres: [],
    tenant: () => demoActiveTenant, yo: () => yo,
    contactos: () => CONTACTOS_AG, equipo: () => EQUIPO, perfiles: () => (typeof demoPerfiles !== 'undefined' ? demoPerfiles : {}),   // 2.1.17 venta: contactos reales de la demo
    contactosAg: () => CONTACTOS_AG, // 2.1.17 · datos: fichas de Contactos (se pueden empujar filas nuevas)
    oportunidades: () => oportunidades, setOportunidades: v => { oportunidades = v; },
    embudos: () => EMBUDOS, etapas: () => ETAPAS, tareas: () => tareas, setTareas: v => { tareas = v; },
    usuarios: () => usuarios, usuariosSistema: () => usuariosSistema, directorio: () => directory,
    cuentas: () => agencyAccounts, auditoria: () => auditoria,
    agencyUsers: () => agencyUsers, // 2.1.17 · equipo embudos: usuarios agregados desde Agencia por subcuenta
    productos: () => (typeof demoProductos !== 'undefined' ? demoProductos : {}),
    personal: () => (typeof demoPersonal !== 'undefined' ? demoPersonal : {}),
  };

  try {
    window.fetch = demoFetch;
  } catch (e) {
    try {
      Object.defineProperty(window, 'fetch', {
        configurable: true,
        enumerable: true,
        writable: true,
        value: demoFetch
      });
    } catch (err) {}
  }

  window.__DEMO__ = true;
  console.info('[demo] Datos de prueba activos. Ninguna llamada sale a la red.');
})();
