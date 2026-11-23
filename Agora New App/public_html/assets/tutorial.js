/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
/* 2.21.0 · Tutoriales de bienvenida (pedido del usuario): un recorrido paso a paso por los módulos de atención (Bandeja, Contactos,
   Oportunidades, Tareas) y los comerciales (Dashboard, Gestión Comercial, Ventas). Se abre solo la primera vez que una persona entra
   a cada módulo en este equipo, y se repite desde el menú de la cuenta («Ver el tutorial de esta pantalla»).
   Cada paso ilumina una parte de la pantalla; si esa parte no está (por permisos o porque aún no cargó), el paso se salta.
   No cambia nada de la pantalla: solo mira y explica. agora-app.js llama a AgoraTutorial.alPintar(mod, usuario) desde paint(). */
(function () {
  'use strict';
  if (window.AgoraTutorial) return;
  const boton = t => ({texto: t});
  const PASOS = {
    bandeja: [
      {t: 'Bandeja de conversaciones', x: 'Aquí llegan juntos los mensajes de tus clientes: WhatsApp, SMS, correo, Instagram y Facebook. Respondes desde aquí y queda todo en la cuenta.'},
      {sel: '#live-search', t: 'Buscar', x: 'Busca por nombre, teléfono o algo que haya escrito el cliente.'},
      {sel: '#view button', ...boton('No leídas'), t: 'Filtros rápidos', x: '«No leídas» muestra lo que nadie ha abierto. «Sin respuesta», las conversaciones donde el cliente escribió último y espera tu respuesta.'},
      {sel: '#live-channel-filters', t: 'Canales', x: 'Filtra por canal para ver solo WhatsApp, solo SMS o solo redes.'},
      {sel: '#live-list', t: 'Tus conversaciones', x: 'Toca una para abrirla. Las que tienen mensajes sin leer llevan un número.'},
      {abrir: '#live-list .live-conversation', sel: '#live-won', t: '¿Venta ganada?', x: 'Cuando el cliente compra, márcalo aquí: eliges la oportunidad y se abre el formulario de la venta, el mismo de Ventas.'},
      {sel: '#live-lost', dep: true, t: 'Lead perdido', x: 'Si el cliente no va a comprar, dalo por perdido. Siempre te pide confirmar y puedes anotar el motivo.'},
      {sel: '#live-recordar', dep: true, t: 'Recordatorio', x: 'Programa un recordatorio de seguimiento para no olvidar escribirle.'},
      {sel: '#live-contact-toggle', dep: true, t: 'Ficha del contacto', x: 'Sus datos, etiquetas, oportunidades, observaciones y tareas. Desde la ficha también asignas la conversación a otra persona.'},
      {sel: '#live-reply', dep: true, t: 'Responder', x: 'Escribe tu respuesta y elige el canal. Cada mensaje que sale muestra quién lo envió: tus iniciales o el ícono de automatización.'},
    ],
    contactos: [
      {t: 'Contactos', x: 'Las fichas de todas las personas de la cuenta. Lo que cambies aquí se guarda en la cuenta.'},
      {sel: '.mod-h button', ...boton('Nuevo contacto'), t: 'Nuevo contacto', x: 'Crea una ficha nueva con nombre, teléfono y correo.'},
      {sel: '#contact-search', t: 'Buscar', x: 'Busca por nombre, correo o teléfono.'},
      {sel: '.live-toolbar ~ * .agency-chip-btn, #records-layout .agency-chip-btn', t: 'Por fecha de entrada', x: 'Mira solo los que entraron hoy, en los últimos 7 o en los últimos 30 días.'},
      {sel: '#contact-results', t: 'La lista', x: 'Toca una persona para ver su ficha completa al lado.'},
      {sel: '#contact-record', t: 'La ficha', x: 'Datos, etiquetas, conversación, oportunidades y tareas. Desde aquí le escribes, la editas o marcas «¿Venta ganada?».'},
    ],
    oportunidades: [
      {t: 'Oportunidades', x: 'Cada posible venta, en su etapa del embudo. Lo que muevas aquí se mueve en la cuenta.'},
      {sel: '.opp-tabs', t: 'Embudos y Ganadas', x: '«Embudos» muestra lo que está en curso. «Ganadas», las ventas del mes; las que no tienen monto se completan ahí.'},
      {sel: '#board-pipeline', t: 'Embudo y etapa', x: 'Elige el embudo y, si quieres, una sola etapa.'},
      {sel: '#board-search', t: 'Buscar', x: 'Busca una oportunidad por nombre o por contacto.'},
      {sel: '.kanban', t: 'El tablero', x: 'Cada columna es una etapa, con cuántas hay y cuánto suman.'},
      {sel: '.kanban .board-ic', t: 'Acciones de cada oportunidad', x: 'Abre la conversación, la ficha del contacto o muévela a otra etapa.'},
    ],
    tareas: [
      {t: 'Tareas', x: 'Lo que cada persona tiene que hacer: llamadas, seguimientos, envíos. Se guardan en la cuenta.'},
      {sel: '.mod-h button', ...boton('Nueva tarea'), t: 'Nueva tarea', x: 'Crea una tarea con fecha, responsable y, si quieres, el contacto.'},
      {sel: 'main#app .cel', t: 'Cifras que filtran', x: 'Toca «Para hoy», «Vencidas», «Abiertas» o «Hechas» para ver solo esas.'},
      {sel: 'main#app select', t: 'Por responsable', x: 'Mira las tareas de una persona o de todo el equipo.'},
      {sel: 'main#app .tw', t: 'La lista', x: 'Ordena por columna, marca como hecha o abre el contacto.'},
      {sel: '.mod-h button', ...boton('Descargar'), t: 'Descargar', x: 'Bájala a Excel con los filtros que tengas puestos.'},
    ],
    comercial: [
      {t: 'Dashboard', x: 'Cómo va tu cuenta hoy: leads, conversaciones, tiempos de respuesta y ventas. Se actualiza solo.'},
      {sel: '#period-bar', t: 'Periodo', x: 'Ayer, Hoy u «Otro periodo» para elegir fechas.'},
      {sel: 'main#app .cel', t: 'Cada cifra', x: 'Si la cifra tiene una flecha, al tocarla te lleva a la lista que la forma. «Editar» cambia o esconde esa tarjeta.'},
      {sel: '.mod-h button, #period-bar ~ button', ...boton('Editar Dashboard'), t: 'Editar Dashboard', x: 'Elige qué cifras ver y en qué orden. Administración puede guardarlo para todo el equipo y agregar widgets propios.'},
      {sel: '#arp-dia', t: 'Operación del día', x: 'Lo que espera respuesta ahora mismo y de dónde vienen los leads.'},
      {sel: '.rep-sel', t: 'Dona, barras o cifras', x: 'Cambia cómo ves cada reparto. Administración y supervisión pueden tocar un nombre para ver el detalle.'},
      {sel: '#b-notif', fuera: true, t: 'Notificaciones', x: 'Aquí llegan los mensajes nuevos, los convenios firmados y lo importante de tu espacio.'},
    ],
    inicio: [
      {t: 'Gestión Comercial', x: 'El reporte del mes: ventas, cobros, metas y tendencia del año.'},
      {sel: '.mod-h button', ...boton('Descargar informe'), t: 'Descargar informe', x: 'El reporte completo listo para compartir.'},
      {sel: '.mod-h button', ...boton('Editar Dashboard'), t: 'Editar Dashboard', x: 'Elige qué cifras ver y en qué orden.'},
      {sel: 'main#app .cel', t: 'Las cifras del mes', x: 'Cada tarjeta dice qué mide. «Editar» cambia o esconde esa tarjeta.'},
      {sel: 'main#app button', ...boton('Editar gráficos'), t: 'Gráficos', x: 'Al final del módulo: elige qué gráficos ver.'},
    ],
    ventas: [
      {t: 'Ventas', x: 'Las ventas del mes, con sus cobros y lo pendiente.'},
      {sel: '.vg-tabs', t: 'Vistas', x: '«Ventas registradas» son las que se cargan en Ágora (por ejemplo, desde «¿Venta ganada?»). «Reporte del mes» viene del reporte importado.'},
      {sel: 'main#app .cel', t: 'Resumen', x: 'Cuántas ventas, cuánto suman y cuánto falta por cobrar.'},
      {sel: 'main#app button', ...boton('Agregar manual'), t: 'Agregar', x: 'Registra una venta a mano.'},
      {sel: 'main#app button', ...boton('Columnas'), t: 'Columnas', x: 'Elige qué columnas ver y en qué orden.'},
      {sel: 'main#app .tw', t: 'La tabla', x: 'Ordena y filtra por columna. Con «Descargar» la bajas a Excel.'},
    ],
  };
  const clave = (u, mod) => `agora.tutorial.${u || 'x'}.${mod}`;
  const visto = (u, mod) => { try { return localStorage.getItem(clave(u, mod)) === '1'; } catch { return true; } };
  const marcar = (u, mod) => { try { localStorage.setItem(clave(u, mod), '1'); } catch {} };
  const visible = e => !!e && (e.offsetParent !== null || getComputedStyle(e).position === 'fixed') && e.getBoundingClientRect().width > 0;
  function buscar(p) {
    if (!p.sel) return null;
    const todos = [...document.querySelectorAll(p.sel)].filter(visible);
    if (!p.texto) return todos[0] || null;
    return todos.find(e => (e.textContent || '').trim().replace(/\s+/g, ' ').includes(p.texto)) || null;
  }
  let actual = null;
  function cerrar(terminado) {
    if (!actual) return;
    const {raiz, mod, usuario, alTeclado, alMover} = actual;
    raiz.remove(); document.removeEventListener('keydown', alTeclado, true); removeEventListener('resize', alMover); removeEventListener('scroll', alMover, true);
    marcar(usuario, mod); actual = null;
    if (terminado) try { window.dispatchEvent(new CustomEvent('agora:toast', {detail: 'Listo. Puedes repetirlo desde el menú de tu cuenta.'})); } catch {}
  }
  function iniciar(mod, usuario) {
    const pasos = PASOS[mod]; if (!pasos || actual) return false;
    const raiz = document.createElement('div'); raiz.id = 'ag-tour'; raiz.setAttribute('role', 'dialog'); raiz.setAttribute('aria-modal', 'true'); raiz.setAttribute('aria-label', 'Tutorial');
    raiz.innerHTML = '<div class="tour-hueco"></div><div class="tour-card" tabindex="-1"><p class="tour-n"></p><h3></h3><p class="tour-x"></p><div class="tour-acc"><button type="button" class="tour-saltar">Saltar</button><span></span><button type="button" class="tour-atras">Atrás</button><button type="button" class="tour-sig">Siguiente</button></div></div>';
    document.body.appendChild(raiz);
    const hueco = raiz.querySelector('.tour-hueco'), card = raiz.querySelector('.tour-card');
    let i = 0, objetivo = null;
    // La lista se fija al empezar: los pasos que dependen de abrir algo (dep) se cuentan y, si al llegar no están, se saltan.
    const ps = pasos.filter(p => !p.sel || p.abrir || p.dep || buscar(p));
    const colocar = () => {
      const tel = innerWidth < 640;
      if (!objetivo) { hueco.style.cssText = 'left:50%;top:50%;width:0;height:0'; card.style.cssText = tel ? '' : 'left:50%;top:50%;transform:translate(-50%,-50%)'; card.classList.toggle('tour-centro', true); return; }
      card.classList.toggle('tour-centro', false);
      const r = objetivo.getBoundingClientRect(), m = 6;
      hueco.style.cssText = `left:${r.left - m}px;top:${r.top - m}px;width:${r.width + 2 * m}px;height:${r.height + 2 * m}px`;
      if (tel) { card.style.cssText = ''; return; }
      const cw = card.offsetWidth, ch = card.offsetHeight;
      let top = r.bottom + 14; if (top + ch > innerHeight - 12) top = Math.max(12, r.top - ch - 14);
      let left = Math.min(Math.max(12, r.left + r.width / 2 - cw / 2), innerWidth - cw - 12);
      card.style.cssText = `left:${left}px;top:${top}px`;
    };
    const ir = async (n, paso = 1) => {
      if (n < 0) { n = 0; paso = 1; } if (n >= ps.length) { cerrar(true); return; }
      i = n; const p = ps[i];
      if (p.abrir && !buscar(p)) { const a = [...document.querySelectorAll(p.abrir)].find(visible); if (a) { a.click(); for (let k = 0; k < 20 && !buscar(p); k++) await new Promise(r => setTimeout(r, 150)); } }
      objetivo = buscar(p);
      if (p.sel && !objetivo) { ir(n + paso, paso); return; }
      if (objetivo && !p.fuera) objetivo.scrollIntoView({block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
      card.querySelector('.tour-n').textContent = `Paso ${i + 1} de ${ps.length}`;
      card.querySelector('h3').textContent = p.t; card.querySelector('.tour-x').textContent = p.x;
      card.querySelector('.tour-atras').hidden = i === 0;
      card.querySelector('.tour-sig').textContent = i === ps.length - 1 ? 'Terminar' : 'Siguiente';
      setTimeout(colocar, 260); colocar(); card.focus({preventScroll: true});
    };
    card.querySelector('.tour-sig').onclick = () => ir(i + 1);
    card.querySelector('.tour-atras').onclick = () => ir(i - 1, -1);
    card.querySelector('.tour-saltar').onclick = () => cerrar(false);
    const alTeclado = e => {
      if (e.key === 'Escape') { e.preventDefault(); cerrar(false); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); ir(i + 1); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); ir(i - 1, -1); }
    };
    const alMover = () => colocar();
    document.addEventListener('keydown', alTeclado, true); addEventListener('resize', alMover); addEventListener('scroll', alMover, true);
    actual = {raiz, mod, usuario, alTeclado, alMover};
    ir(0);
    return true;
  }
  /* Primera visita: espera a que la pantalla termine de cargar (sin entrada, sin esqueletos ni ventanas abiertas) y abre el recorrido. */
  let espera = 0;
  function alPintar(mod, usuario) {
    clearTimeout(espera);
    if (!PASOS[mod] || !usuario || visto(usuario, mod) || actual) return;
    // Las pruebas automáticas no lo ven salvo que lo pidan (agora.tutorial.auto=1): así no tapa las demás pantallas.
    try { if (navigator.webdriver && localStorage.getItem('agora.tutorial.auto') !== '1') return; } catch { return; }
    let intentos = 0;
    const listo = () => !document.getElementById('ag-entrada') && !document.querySelector('main#app .sk, main#app [data-placeholder], #layer .scrim');
    const probar = () => { if (++intentos > 40) return; if (!listo()) { espera = setTimeout(probar, 500); return; } espera = setTimeout(() => { if (listo() && !visto(usuario, mod)) iniciar(mod, usuario); }, 900); };
    espera = setTimeout(probar, 600);
  }
  window.AgoraTutorial = {iniciar, alPintar, hay: mod => !!PASOS[mod], activo: () => !!actual, cerrar: () => cerrar(false)};
})();
