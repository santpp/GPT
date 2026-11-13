"use strict";
/* =====================================================================================
   Agora Digital App
   -------------------------------------------------------------------------------------
   Un solo sistema para toda la operación comercial:

     Operación       Resumen · ARP Comercial · Bandeja de conversaciones · Contactos ·
                     Oportunidades · Tareas
     Contratos       Contratos · Con convenio · Registro
     Administración  Auditoría · Usuarios y roles · Ajustes · Ayuda

   La barra de arriba lleva la marca, el módulo en uso con su menú, el periodo, los
   accesos a Inicio, Ajustes y Auditoría, el estado del espacio, el historial y Athena.

   Cada módulo habla con la API de su sistema; las dos bases se configuran en
   assets/config.js. Go High Level entra por el servidor, no desde aquí.
   ===================================================================================== */
(() => {
const IC = {"home": "<path d=\"M3 11l9-7 9 7\"/><path d=\"M5 10v10h14V10\"/>", "trend": "<path d=\"M3 17l6-6 4 4 8-8\"/><path d=\"M15 7h6v6\"/>", "receipt": "<path d=\"M6 3h12v18l-3-2-3 2-3-2-3 2z\"/><path d=\"M9 8h6M9 12h6\"/>", "invoice": "<path d=\"M7 3h7l5 5v13H7z\"/><path d=\"M14 3v5h5\"/><path d=\"M10 13h6M10 17h4\"/>", "pie": "<path d=\"M12 3v9h9\"/><path d=\"M20.5 15A9 9 0 1 1 9 3.5\"/>", "wallet": "<rect x=\"3\" y=\"6\" width=\"18\" height=\"13\" rx=\"2.5\"/><path d=\"M16 12.5h2\"/><path d=\"M3 9h18\"/>", "users": "<circle cx=\"9\" cy=\"8\" r=\"3.5\"/><path d=\"M2.5 20a6.5 6.5 0 0 1 13 0\"/><path d=\"M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6\"/>", "calendar": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"16\" rx=\"2.5\"/><path d=\"M3 10h18M8 3v4M16 3v4\"/>", "pause": "<rect x=\"6\" y=\"5\" width=\"4\" height=\"14\" rx=\"1\"/><rect x=\"14\" y=\"5\" width=\"4\" height=\"14\" rx=\"1\"/>", "rocket": "<path d=\"M5 15c-1.5 1.5-2 4-2 6 2 0 4.5-.5 6-2\"/><path d=\"M9 15l-3-3 9-9c2 0 4 .5 6 2 0 2-.5 4-2 6z\"/><circle cx=\"15\" cy=\"9\" r=\"1.5\"/>", "team": "<circle cx=\"12\" cy=\"7\" r=\"3\"/><path d=\"M6 21v-1a6 6 0 0 1 12 0v1\"/>", "check": "<rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"4\"/><path d=\"M8 12.5l3 3 5-6\"/>", "gear": "<circle cx=\"12\" cy=\"12\" r=\"3\"/><path d=\"M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1\"/>", "info": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 11v6M12 7.5v.01\"/>", "upload": "<path d=\"M12 16V4M7 9l5-5 5 5\"/><path d=\"M4 16v4h16v-4\"/>", "lock": "<rect x=\"5\" y=\"11\" width=\"14\" height=\"10\" rx=\"2\"/><path d=\"M8 11V8a4 4 0 0 1 8 0v3\"/>", "unlock": "<rect x=\"5\" y=\"11\" width=\"14\" height=\"10\" rx=\"2\"/><path d=\"M8 11V8a4 4 0 0 1 7.5-2\"/>", "sync": "<path d=\"M20 11a8 8 0 0 0-14.5-4.5L4 8\"/><path d=\"M4 4v4h4\"/><path d=\"M4 13a8 8 0 0 0 14.5 4.5L20 16\"/><path d=\"M20 20v-4h-4\"/>", "edit": "<path d=\"M4 20h4L19 9l-4-4L4 16z\"/>", "download": "<path d=\"M12 4v11M7 10l5 5 5-5\"/><path d=\"M4 18v2h16v-2\"/>", "coins": "<ellipse cx=\"9\" cy=\"6.5\" rx=\"6\" ry=\"2.5\"/><path d=\"M3 6.5v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4\"/><path d=\"M9 17c-3.3 0-6-1.1-6-2.5v-4\"/><path d=\"M15 11.5c3.3 0 6 1.1 6 2.5v4c0 1.4-2.7 2.5-6 2.5s-6-1.1-6-2.5v-4c0-1.4 2.7-2.5 6-2.5z\"/>", "shield": "<path d=\"M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z\"/><path d=\"M8.5 12l2.5 2.5 4.5-5\"/>", "chev": "<path d=\"M6 9l6 6 6-6\"/>", "plus": "<path d=\"M12 5v14M5 12h14\"/>", "split": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 3v9l6.4 6.4\"/><path d=\"M12 12H3\"/>", "spark": "<path d=\"M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z\"/><path d=\"M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z\"/>", "history": "<path d=\"M3.5 12a8.5 8.5 0 1 0 2.6-6.1\"/><path d=\"M3 4.5v4.5h4.5\"/><path d=\"M12 7.5V12l3 2\"/>", "agenda": "<rect x=\"3\" y=\"4.5\" width=\"18\" height=\"16.5\" rx=\"3.5\"/><path d=\"M3 9.5h18M8 2.5v4M16 2.5v4\"/><path d=\"M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2\"/>", "pulse": "<path d=\"M3 12h4l2.2-5.5L13.5 18l2.2-6H21\"/>", "clock": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7.5V12l3 2\"/>", "pin": "<path d=\"M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z\"/><circle cx=\"12\" cy=\"10\" r=\"2.4\"/>", "chevL": "<path d=\"M15 6l-6 6 6 6\"/>", "chevR": "<path d=\"M9 6l6 6-6 6\"/>", "chat": "<path d=\"M4 18.5V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7a2.5 2.5 0 0 1-2.5 2.5H8z\"/><path d=\"M8.5 9.5h7M8.5 12.5h4\"/>", "mail": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"14\" rx=\"2.5\"/><path d=\"M3.5 7l8.5 6 8.5-6\"/>", "phone": "<path d=\"M5 4h3.5l1.5 4.2-2.2 1.4a11 11 0 0 0 6.6 6.6l1.4-2.2L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5C10.6 20 4 13.4 3.5 5.6A1.5 1.5 0 0 1 5 4z\"/>", "video": "<rect x=\"3\" y=\"6\" width=\"13\" height=\"12\" rx=\"2.5\"/><path d=\"M16 10.5l5-3v9l-5-3\"/>", "warn": "<path d=\"M12 3.5l9.5 16.5h-19z\"/><path d=\"M12 10v4.5M12 17.5v.01\"/>", "search": "<circle cx=\"11\" cy=\"11\" r=\"6.5\"/><path d=\"M20 20l-4.2-4.2\"/>", "sidebar": "<rect x=\"3\" y=\"4.5\" width=\"18\" height=\"15\" rx=\"3\"/><path d=\"M9.5 4.5v15\"/><path d=\"M5.5 8h1.8M5.5 11h1.8\"/>", "bell": "<path d=\"M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15z\"/><path d=\"M10 20.5a2 2 0 0 0 4 0\"/>", "keyboard": "<rect x=\"2.5\" y=\"6\" width=\"19\" height=\"12\" rx=\"2.5\"/><path d=\"M6 9.5h.01M9 9.5h.01M12 9.5h.01M15 9.5h.01M18 9.5h.01M6.5 14.5h11\"/>", "hand": "<path d=\"M8 12V6.5a1.5 1.5 0 0 1 3 0V11\"/><path d=\"M11 10.5V5a1.5 1.5 0 0 1 3 0v6\"/><path d=\"M14 10.5V6.5a1.5 1.5 0 0 1 3 0V14a7 7 0 0 1-7 7h-.5a6 6 0 0 1-5-2.7L3 15.5a1.6 1.6 0 0 1 2.6-1.8L8 16\"/>", "scribble": "<path d=\"M4 17c2.5-6 4.5-9 6-9 2 0-1.5 8 .5 8 1.6 0 3-4 4.5-4s.5 3 2 3 2-1 3-2\"/><path d=\"M15.5 4.5l3 3L10 16l-3.5.5.5-3.5z\" opacity=\".0\"/>", "sun": "<circle cx=\"12\" cy=\"12\" r=\"4\"/><path d=\"M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4\"/>", "moon": "<path d=\"M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z\"/>", "auto": "<circle cx=\"12\" cy=\"12\" r=\"8.5\"/><path d=\"M12 3.5v17a8.5 8.5 0 0 0 0-17z\" fill=\"currentColor\" stroke=\"none\"/>", "mic": "<rect x=\"9\" y=\"3\" width=\"6\" height=\"11\" rx=\"3\"/><path d=\"M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21\"/>", "send": "<path d=\"M4 12l16-8-6 16-2.5-6.5z\"/><path d=\"M11.5 13.5L20 4\"/>", "more": "<circle cx=\"5.5\" cy=\"12\" r=\"1.3\"/><circle cx=\"12\" cy=\"12\" r=\"1.3\"/><circle cx=\"18.5\" cy=\"12\" r=\"1.3\"/>", "logout": "<path d=\"M15 4h4v16h-4\"/><path d=\"M10 17l5-5-5-5\"/><path d=\"M15 12H3\"/>", "user": "<circle cx=\"12\" cy=\"8\" r=\"4\"/><path d=\"M4.5 20.5a7.5 7.5 0 0 1 15 0\"/>", "refresh": "<path d=\"M20 11a8 8 0 0 0-14.5-4.5L4 8\"/><path d=\"M4 4v4h4\"/><path d=\"M4 13a8 8 0 0 0 14.5 4.5L20 16\"/><path d=\"M20 20v-4h-4\"/>", "install": "<rect x=\"6\" y=\"2.5\" width=\"12\" height=\"19\" rx=\"3\"/><path d=\"M12 8v6M9.5 11.5L12 14l2.5-2.5\"/><path d=\"M10.5 18.5h3\"/>", "palette": "<path d=\"M12 3.5a8.5 8.5 0 0 0 0 17c1.2 0 1.8-.8 1.8-1.7 0-1.2-1-1.6-1-2.6 0-1 .8-1.7 1.8-1.7H17a3.5 3.5 0 0 0 3.5-3.5c0-4.2-3.8-7.5-8.5-7.5z\"/><circle cx=\"7.5\" cy=\"11\" r=\"1.1\"/><circle cx=\"10\" cy=\"7.5\" r=\"1.1\"/><circle cx=\"14.5\" cy=\"7.5\" r=\"1.1\"/>", "rate": "<path d=\"M4 7h13l-3-3M20 17H7l3 3\"/>", "athena": "<circle cx=\"12\" cy=\"12\" r=\"8.5\"/><path d=\"M8.5 13.5c1 1.4 2.2 2 3.5 2s2.5-.6 3.5-2\"/><path d=\"M9 9.5v.5M15 9.5v.5\"/>"};
const LOGO = "<svg width=\"62\" height=\"26\" role=\"img\" aria-label=\"Ágora\" xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1427.98 591.9\">\n  \n  <g>\n    <path fill=\"var(--iso,#AD103C)\" d=\"M267.09,0C128.79,0,16.67,111.74,16.67,249.59v92.72c0,137.84,112.12,249.59,250.42,249.59s250.42-111.74,250.42-249.59v-92.72c0-68.92-28.02-131.32-73.34-176.49C398.84,27.93,336.24,0,267.09,0ZM472.38,341.81c0,113.01-91.91,204.61-205.29,204.61S61.8,454.82,61.8,341.81v-91.73c0-112.99,91.91-204.61,205.29-204.61,56.69,0,108.01,22.91,145.17,59.94,37.14,37.02,60.12,88.18,60.12,144.67v91.73Z\"/>\n    <polygon fill=\"var(--iso,#AD103C)\" points=\"107.44 363.83 212.07 363.83 138.08 437.58 170.34 469.73 244.33 395.99 244.33 500.28 289.96 500.28 289.96 395.99 363.95 469.73 396.21 437.58 322.22 363.83 426.85 363.83 426.85 318.36 107.44 318.36 107.44 363.83\"/>\n    <polygon fill=\"var(--iso,#AD103C)\" points=\"396.21 153.66 363.95 121.5 289.96 195.24 289.96 90.96 244.33 90.96 244.33 195.24 170.34 121.5 138.08 153.66 212.07 227.4 107.44 227.4 107.44 272.87 426.85 272.87 426.85 227.4 322.22 227.4 396.21 153.66\"/>\n  </g>\n  <g>\n    <path d=\"M664.69,136.33l-56.12,319.9h45.51l11.5-74.1h66.92l12.58,74.1h46.01l-60.17-319.9h-66.23ZM671.37,344.82l26.18-168.63,28.62,168.63h-54.8Z\"/>\n    <polygon points=\"770.87 45.84 719.8 45.84 678.34 112.79 717.27 112.79 770.87 45.84\"/>\n    <path d=\"M905.84,247c-1.47-4.64-3.74-8.38-6.88-11.17-5.9-5.24-13.76-7.86-23.59-7.86h-17.69c-15.07,0-26.21,3.69-33.42,11.06-7.21,7.37-10.81,18.27-10.81,32.68v137.83c0,14.42,3.6,25.31,10.81,32.68,7.2,7.37,18.34,11.06,33.42,11.06h17.69c9.83,0,17.69-2.62,23.59-7.86,3.14-2.78,5.41-6.53,6.88-11.17v40.65c0,5.57-.98,9.34-2.95,11.3-1.97,1.97-5.9,2.95-11.8,2.95h-69.29v34.89h82.07c16.05,0,27.76-3.69,35.14-11.06,7.37-7.37,11.06-18.11,11.06-32.19v-252.83h-44.23v19.03ZM901.91,413.47c-2.63,2.63-6.72,3.93-12.29,3.93h-17.2c-5.9,0-9.83-.98-11.8-2.95-1.97-1.96-2.95-5.73-2.95-11.3v-125.06c0-5.9.98-9.75,2.95-11.55,1.97-1.8,5.9-2.7,11.8-2.7h17.2c5.57,0,9.66,1.31,12.29,3.93,2.62,2.63,3.93,7.05,3.93,13.27v119.16c0,6.23-1.31,10.65-3.93,13.27Z\"/>\n    <path d=\"M1089.45,227.97h-52.59c-15.72,0-27.2,3.69-34.4,11.06-7.21,7.37-10.81,18.27-10.81,32.68v141.27c0,14.09,3.6,24.82,10.81,32.19,7.2,7.37,18.67,11.06,34.4,11.06h52.59c16.05,0,27.68-3.69,34.89-11.06,7.2-7.37,10.81-18.1,10.81-32.19v-141.27c0-14.41-3.61-25.31-10.81-32.68-7.21-7.37-18.84-11.06-34.89-11.06ZM1091.42,377.6v29c0,5.9-.91,9.83-2.71,11.8-1.8,1.97-5.82,2.95-12.04,2.95h-27.03c-5.57,0-9.34-.98-11.3-2.95-1.97-1.97-2.95-5.9-2.95-11.8v-128.99c0-6.22.98-10.24,2.95-12.04,1.97-1.8,5.73-2.7,11.3-2.7h27.03c6.22,0,10.24.91,12.04,2.7,1.8,1.8,2.71,5.82,2.71,12.04v99.99Z\"/>\n    <path d=\"M1238.04,233.87c-6.07,4.26-10.65,9.75-13.76,16.46-1.1,2.37-1.99,4.75-2.7,7.14v-29.5h-44.23v228.26h44.23v-154.05c0-24.24,10.32-36.37,30.96-36.37h17.69v-38.33h-9.83c-8.85,0-16.3,2.14-22.36,6.39Z\"/>\n    <path d=\"M1383.75,227.97v19.03c-1.47-4.64-3.74-8.38-6.88-11.17-5.9-5.24-13.76-7.86-23.59-7.86h-17.69c-15.07,0-26.21,3.69-33.42,11.06-7.21,7.37-10.81,18.27-10.81,32.68v141.27c0,14.09,3.6,24.82,10.81,32.19,7.2,7.37,18.34,11.06,33.42,11.06h17.69c9.83,0,17.69-2.62,23.59-7.86,3.14-2.79,5.41-6.53,6.88-11.17v19.03h44.23v-228.26h-44.23ZM1379.82,416.67c-2.63,2.46-6.72,3.69-12.29,3.69h-17.2c-5.9,0-9.83-.98-11.8-2.95-1.97-1.97-2.95-5.73-2.95-11.3v-128c0-5.9.98-9.75,2.95-11.55,1.97-1.8,5.9-2.7,11.8-2.7h17.2c5.57,0,9.66,1.31,12.29,3.93,2.62,2.63,3.93,7.05,3.93,13.27v122.11c0,6.56-1.31,11.06-3.93,13.51Z\"/>\n  </g>\n</svg>";
const $ = s => document.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const norm = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const icon = (n, cls = '') => `<svg class="ic ${cls}" viewBox="0 0 24 24" aria-hidden="true">${IC[n] || ''}</svg>`;
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const store = {
  get: (k, d) => { try { const v = localStorage.getItem('agora_' + k); return v ? JSON.parse(v) : d; } catch { return d; } },
  set: (k, v) => { try { localStorage.setItem('agora_' + k, JSON.stringify(v)); } catch { /* modo privado */ } },
};
const IS_MAC = /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent);
const MODK = IS_MAC ? '⌘' : 'Ctrl';

/* ---------- Formatos ---------- */
const NF = new Intl.NumberFormat('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const NI = new Intl.NumberFormat('es-VE', {maximumFractionDigits: 0});
const num = s => { const n = Number(String(s ?? '').replace(',', '.')); return isFinite(n) ? n : 0; };
const money = s => s == null || s === '' ? '—' : '$' + NF.format(num(s));
const int = n => n == null ? '—' : NI.format(n);
const pct = x => x == null || !isFinite(x) ? '—' : NI.format(Math.round(x * 100)) + ' %';
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const cap = t => t.charAt(0).toUpperCase() + t.slice(1);
/* El servidor guarda la hora en UTC («2026-11-02 14:10:00»); se muestra en la hora del equipo. */
const utc = s => s ? new Date(String(s).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(s) ? '' : 'Z')) : null;
const fday = ymd => { if (!ymd) return '—'; const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number); return `${d} ${MESES[m - 1].slice(0, 3)} ${y}`; };
const fstamp = s => { const d = utc(s); return d && !isNaN(d) ? d.toLocaleString('es-VE', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : '—'; };
const fdate = s => { const d = utc(s); return d && !isNaN(d) ? d.toLocaleDateString('es-VE', {day: 'numeric', month: 'short', year: 'numeric'}) : '—'; };
const when = s => {
  const d = utc(s); if (!d || isNaN(d)) return '—';
  const sec = (Date.now() - d) / 1000;
  if (sec < 60) return 'hace un momento'; if (sec < 3600) return `hace ${Math.round(sec / 60)} min`; if (sec < 86400) return `hace ${Math.round(sec / 3600)} h`;
  if (sec < 86400 * 7) return `hace ${Math.round(sec / 86400)} d`;
  return fdate(s);
};
const initials = n => String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase();

/* ---------- Roles (espejo de Seguridad/Roles.php) ---------- */
const PERMISOS = {MASTER: ['ver', 'editar', 'enviar', 'revocar', 'usuarios'], SUPERADMIN: ['ver', 'editar', 'enviar', 'revocar', 'usuarios'], ADMIN: ['ver', 'editar', 'enviar', 'revocar', 'usuarios'], SUPERVISOR: ['ver', 'editar'], EJECUTIVO: ['ver', 'editar', 'enviar', 'revocar'], CONSULTA: ['ver'], REMITENTE: ['ver', 'editar', 'enviar', 'revocar'], VISOR: ['ver']};
const ROLES = {
  MASTER:{l:'Master · Dueño'}, SUPERADMIN:{l:'Superadmin de Agencia'}, USUARIO:{l:'Usuario de subcuenta'},
  ADMIN: {l: 'Administración', d: 'Todo, incluidas las personas y el registro. Ve todos los convenios.'},
  REMITENTE: {l: 'Remitente', d: 'Crea, edita, envía y revoca los convenios que tiene asignados.'},
  VISOR: {l: 'Consulta', d: 'Solo consulta los convenios que tiene asignados.'},
};
let ME = null;
let NAV_EPOCH = 0;
let SWITCHING = false;
const NAV_REQUESTS = new Set();
async function navigationFetch(url, options) {
  const controller = new AbortController();
  NAV_REQUESTS.add(controller);
  const timeout = setTimeout(() => controller.abort(), 20000);
  try { return await fetch(url, {...options, signal:controller.signal}); }
  finally { clearTimeout(timeout); NAV_REQUESTS.delete(controller); }
}
const staleContext = () => Object.assign(new Error('La subcuenta cambió. Vuelve a abrir el módulo.'), {staleContext: true});
function checkContext(epoch) { if (epoch !== NAV_EPOCH) throw staleContext(); }

const can = p => !!ME && (PERMISOS[ME.rol] || (ME.rol === 'USUARIO' ? ['ver'] : [])).includes(p);

const ESTADO = {
  BORRADOR: {l: 'Borrador', c: 'sc-gray', plural: 'Borradores'},
  ENVIADO: {l: 'Enviado', c: 'sc-info', plural: 'Enviados'},
  FIRMADO: {l: 'Firmado', c: 'sc-ok', plural: 'Firmados'},
  REVOCADA: {l: 'Revocado', c: 'sc-bad', plural: 'Revocados'},
};
const chip = e => { const m = ESTADO[e] || {l: e, c: 'sc-gray'}; return `<span class="sc ${m.c}">${esc(m.l)}</span>`; };
const EVENTOS = {CREADO: 'Convenio creado', ENVIADO: 'Convenio enviado', FIRMADO: 'Convenio firmado', REVOCADO: 'Enlace revocado', ENLACE_NUEVO: 'Enlace de firma nuevo', NUEVA_VERSION: 'Nueva versión', CLIENTE_EDITADO: 'Datos del cliente corregidos', ENLACES_REVOCADOS: 'Enlaces anteriores anulados'};

/* =====================================================================================
   Las dos APIs
   -------------------------------------------------------------------------------------
   Cada sistema conserva sus rutas. Las bases salen de assets/config.js, así el mismo
   paquete sirve si los dos viven en el mismo dominio o en dominios distintos.
   ===================================================================================== */
const CONTRATOS_API = (window.CONTRATOS_API || '/api/');
const AGORA_API = (window.AGORA_API || '/api/');

/* ---------- API de Contratos (las mismas rutas de siempre) ---------- */
async function api(path, {method = 'GET', body} = {}) {
  const epoch = NAV_EPOCH;
  if (SWITCHING) throw staleContext();
  let r;
  try {
    r = await navigationFetch(CONTRATOS_API + path, {method, credentials: 'same-origin', cache: 'no-store', headers: body !== undefined ? {'Content-Type': 'application/json'} : {}, body: body !== undefined ? JSON.stringify(body) : undefined});
  } catch {
    throw new Error(navigator.onLine ? 'No se pudo conectar con el servidor. Inténtalo de nuevo.' : 'Sin conexión. Revisa tu internet.');
  }
  let d = {}; try { d = await r.json(); } catch { if (r.ok && r.status !== 204) throw new Error('El servidor no devolvió una respuesta válida. Revisa la conexión de la API.'); }
  checkContext(epoch);
  // Si caduca la sesión de Contratos pero sigue abierta la de Ágora, no se echa a nadie.
  if (r.status === 401 && path !== 'login' && path !== 'yo') {
    ME = null; DATA.reset();
    AGORA_ME = null; go('/entrar', {replace: true});
  }
  if (!r.ok) throw Object.assign(new Error(d.error || `No se pudo completar (código ${r.status}).`), {status: r.status});
  return d;
}

/* ---------- Datos en memoria ---------- */
const DATA = {
  contratos: null, detalle: {}, usuarios: null, eventos: null,
  reset() { this.contratos = null; this.detalle = {}; this.usuarios = null; this.eventos = null; },
};
const loadContratos = async (force) => { if (force || !DATA.contratos) DATA.contratos = (await api('contratos')).contratos || []; return DATA.contratos; };
const loadDetalle = async (uid, force) => { if (force || !DATA.detalle[uid]) DATA.detalle[uid] = await api('contratos/' + uid); return DATA.detalle[uid]; };
const loadUsuarios = async (force) => { if (force || !DATA.usuarios) DATA.usuarios = (await api('usuarios')).usuarios || []; return DATA.usuarios; };
const loadEventos = async (force) => { if (force || !DATA.eventos) DATA.eventos = (await api('registro')).eventos || []; return DATA.eventos; };
/* Personas con convenio: los contratos agrupados por correo */
function clientes() {
  const by = new Map();
  for (const c of DATA.contratos || []) {
    const k = norm(c.correo) || norm(c.cliente);
    const g = by.get(k) || {k, nombre: c.cliente, correo: c.correo, total: 0, n: {BORRADOR: 0, ENVIADO: 0, FIRMADO: 0, REVOCADA: 0}, ultimo: '', estado: '', uid: '', lista: []};
    g.total++; g.n[c.estado] = (g.n[c.estado] || 0) + 1; g.lista.push(c);
    if (!g.ultimo || String(c.creado_en) > g.ultimo) { g.ultimo = String(c.creado_en); g.estado = c.estado; g.nombre = c.cliente; g.uid = c.uid; }
    by.set(k, g);
  }
  return [...by.values()].sort((a, b) => b.ultimo.localeCompare(a.ultimo));
}
const convPorCorreo = correo => (DATA.contratos || []).filter(c => correo && norm(c.correo) === norm(correo));

/* =====================================================================================
   Rutas: /contratos, /contratos/nuevo, /contratos/:uid, /contactos, /usuarios, /registro, /entrar
   En un archivo abierto desde el disco se usa #/ruta (no hay servidor que atienda las rutas).
   ===================================================================================== */
const HASH = location.protocol === 'file:' || window.__CONTRATOS_HASH === true;
const path = () => (HASH ? location.hash.slice(1) : location.pathname).replace(/\/+$/, '') || '/';
const href = p => (HASH ? '#' : '') + p;
function go(p, {replace} = {}) {
  if (SWITCHING) return;
  if (HASH && path() === p) { render(true); return; }
  if (HASH) { if (replace) history.replaceState(null, '', location.pathname + location.search + '#' + p); else location.hash = p; if (replace) render(true); return; }
  if (replace) history.replaceState(null, '', p); else history.pushState(null, '', p);
  render(true);
}
window.addEventListener(HASH ? 'hashchange' : 'popstate', () => render(true));
document.addEventListener('click', e => {
  const a = e.target.closest('a[href]');
  if (!a || a.target || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
  const h = a.getAttribute('href');
  if (HASH ? h.startsWith('#/') : (h.startsWith('/') && !h.startsWith('/api/'))) { e.preventDefault(); closeAll(); go(HASH ? h.slice(1) : h); }
});

/* =====================================================================================
   Los módulos, en tres grupos
   -------------------------------------------------------------------------------------
   Las direcciones de Ágora siguen valiendo (/bandeja, /contactos, /oportunidades,
   /conversations/{id}); /tablero y /conexiones quedan como alias de las nuevas.
   ===================================================================================== */
const GRUPOS = {
  general: {name: 'General', slug: 'general', icon: 'pie', c: 'var(--mod-vino)', desc: 'Las cifras del día, los anuncios y la rentabilidad.'},
  ingresos: {name: 'Ventas y facturación', slug: 'ventas', icon: 'coins', c: 'var(--brand)', desc: 'El reporte del mes, el ARP Comercial, las ventas, las facturas y el catálogo.'},
  clientes: {name: 'Clientes y atención', slug: 'clientes', icon: 'chat', c: 'var(--mod-azul)', desc: 'Conversaciones, contactos, oportunidades y tareas del equipo.'},
  gestion: {name: 'Gestión y resultados', slug: 'gestion', icon: 'pulse', c: 'var(--mod-oro)', desc: 'Equipo, publicidad, costos, tendencias y calidad de los datos.'},
  contratos: {name: 'Contratos', slug: 'contratos', icon: 'invoice', c: 'var(--ok-solid)', desc: 'Convenios, alumnos con convenio y su registro.'},
  administracion: {name: 'Administración', slug: 'administracion', icon: 'gear', c: 'var(--deep, #282E49)', desc: 'Auditoría, configuración, usuarios, ajustes y ayuda.'},
};
const MODS = {
  centro: {g: 'general', fijo: true, view: 'centro', name: 'Inicio', icon: 'home', path: '/centro', c: 'var(--brand)', desc: 'Todos los módulos de tu subcuenta, en un solo lugar.', ok: () => !!ME || agoraReady()},
  inicio: {g: 'ingresos', view: 'tc_resumen', name: 'Gestión Comercial', icon: 'trend', path: '/inicio', c: 'var(--brand)', periodo: true,
    desc: 'El reporte del mes: identidad, ventas y tendencia del año.', ok: () => tableroHay('resumen')},
  comercial: {g: 'ingresos', view: 'ag_dashboard', ag: 'dashboard', name: 'ARP Comercial', icon: 'pie', path: '/comercial', c: 'var(--mod-vino)',
    desc: 'Las cifras del día, los anuncios y la rentabilidad.', ok: () => agoraReady()},
  bandeja: {g: 'clientes', view: 'ag_inbox', ag: 'inbox', name: 'Bandeja de conversaciones', icon: 'chat', path: '/bandeja', c: 'var(--mod-azul)',
    desc: 'Todos los canales en una sola lista.', ok: () => agoraReady(), n: () => unread || null},
  contactos: {g: 'clientes', view: 'ag_contacts', ag: 'contacts', name: 'Contactos', icon: 'users', path: '/contactos', c: 'var(--mod-azul)',
    desc: 'Las fichas de la subcuenta y su historial.', ok: () => agoraReady()},
  oportunidades: {g: 'clientes', view: 'ag_board', ag: 'board', name: 'Oportunidades', icon: 'trend', path: '/oportunidades', c: 'var(--mod-oro)',
    desc: 'Los embudos por etapa y las ventas ganadas.', ok: () => agoraReady(), n: () => wonPendientes || null},
  tareas: {g: 'clientes', view: 'tareas', name: 'Tareas', icon: 'check', path: '/tareas', c: 'var(--ok-solid)',
    desc: 'Lo que cada ejecutivo tiene pendiente hoy.', ok: () => !!ME || agoraReady(), n: () => tareasPendientes()},
  equipo: {g: 'gestion', view: 'tc_equipo', name: 'Equipo', icon: 'team', path: '/equipo', c: 'var(--mod-vino)', periodo: true,
    desc: 'Ventas, cobranza y comisiones de cada asesora.', ok: () => tableroHay('equipo')},
  publicidad: {g: 'gestion', view: 'tc_marketing', name: 'Embudo y publicidad', icon: 'rocket', path: '/publicidad', c: 'var(--mod-azul)', periodo: true,
    desc: 'Campañas, contactos por canal y ocupación de cupos.', ok: () => tableroHay('marketing')},
  ventas: {g: 'ingresos', view: 'tc_ventas', name: 'Ventas', icon: 'trend', path: '/ventas', c: 'var(--mod-vino)', periodo: true,
    desc: 'El detalle de cada venta con todos sus montos.', ok: () => tableroHay('ventas')},
  facturacion: {g: 'ingresos', view: 'tc_facturas', name: 'Facturación', icon: 'invoice', path: '/facturacion', c: 'var(--mod-oro)', periodo: true,
    desc: 'Los registros de los libros mensuales de facturas.', ok: () => tableroHay('facturas')},
  costos: {g: 'gestion', view: 'tc_costos', name: 'Costos', icon: 'coins', path: '/costos', c: 'var(--mod-azul)', periodo: true,
    desc: 'Gastos fijos, variables y la inversión prevista.', ok: () => tableroHay('costos')},
  catalogo: {g: 'ingresos', view: 'tc_catalogo', name: 'Catálogo', icon: 'receipt', path: '/catalogo', c: 'var(--mod-azul)', periodo: true,
    desc: 'Programas, mensualidad, duración y lo inscrito.', ok: () => tableroHay('catalogo')},
  tendencias: {g: 'gestion', view: 'tc_tendencias', name: 'Tendencias', icon: 'pulse', path: '/tendencias', c: 'var(--mod-oro)', periodo: true,
    desc: 'Cómo se mueve el negocio mes a mes y año a año.', ok: () => tableroHay('tendencias')},
  calidad: {g: 'gestion', view: 'tc_calidad', name: 'Calidad de datos', icon: 'warn', path: '/calidad', c: 'var(--mod-seguridad)',
    desc: 'Lo que conviene corregir antes de cerrar números.', ok: () => tableroHay('calidad')},
  convenios: {g: 'contratos', view: 'contratos', name: 'Contratos', icon: 'invoice', path: '/contratos', c: 'var(--mod-oro)',
    desc: 'Convenios de pago: estado, firma y documentos.', ok: () => can('ver'), n: () => DATA.contratos?.length},
  conConvenio: {g: 'contratos', view: 'contactos', name: 'Con convenio', icon: 'user', path: '/con-convenio', c: 'var(--mod-oro)',
    desc: 'Personas con convenio y búsqueda en la cartera.', ok: () => can('ver'), n: () => DATA.contratos ? clientes().length : null},
  registro: {g: 'contratos', view: 'registro', name: 'Registro', icon: 'history', path: '/registro', c: 'var(--mod-oro)',
    desc: 'Todo lo que pasa con los convenios, con su hora.', ok: () => can('usuarios')},
  auditoria: {g: 'administracion', view: 'auditoria', name: 'Auditoría', icon: 'shield', path: '/auditoria', c: 'var(--mod-seguridad)', periodo: true,
    desc: 'Quién tocó qué contacto, cuándo y desde dónde.', ok: () => puedeAuditar()},
  configuracion: {g: 'administracion', view: 'tc_configuracion', name: 'Configuración mensual', icon: 'edit', path: '/configuracion', c: 'var(--mod-azul)', periodo: true,
    desc: 'Metas, umbrales y costos manuales de cada mes.', ok: () => tableroHay('configuracion')},
  usuarios: {g: 'administracion', view: 'usuarios', name: 'Usuarios y roles', icon: 'team', path: '/usuarios', c: 'var(--mod-seguridad)',
    desc: 'Quién entra, con qué rol y la identidad del espacio.', ok: () => esAdmin()},
  conexiones: {g: 'administracion', view: 'ag_settings', ag: 'settings', name: 'Ajustes', icon: 'gear', path: '/ajustes', c: 'var(--mod-seguridad)',
    desc: 'Cuentas conectadas, equipo importado y avisos.', ok: () => agoraAdmin()},
  ayuda: {g: 'administracion', view: 'ag_help', ag: 'help', name: 'Ayuda', icon: 'info', path: '/ayuda', c: 'var(--mod-azul)',
    desc: 'El manual de uso del sistema.', ok: () => agoraReady() || !!ME},
};
/** Los módulos a los que llega quien entró, en el orden de los grupos. */
const ALL = () => Object.keys(GRUPOS).flatMap(g => Object.keys(MODS).filter(k => MODS[k].g === g && !MODS[k].dentro && !moduloOculto(k)));
/* 2.1.17: un módulo que vive dentro de otro (Catálogo dentro de Productos y servicios) marca a su padre en el menú. */
const modMenu = () => MODS[R?.mod]?.dentro || R?.mod;
/** Los 6 módulos principales con sus submódulos (Inicio va aparte: es fijo). */
const gruposMenu = () => { const available = ALL().filter(k => !MODS[k].fijo); return Object.keys(GRUPOS).map(g => [g, available.filter(k => MODS[k].g === g)]).filter(([, ids]) => ids.length); };
const moduleAllowed = id => !!MODS[id]?.ok();
const moduleCaption = id => moduleAllowed(id) ? MODS[id].desc : 'Acceso restringido para tu rol · Consulta al administrador.';
const homeMod = () => ALL()[0] || null;
/* Nombre del módulo de agencia que antes se llamaba "Gobierno de agencia" (2.1.7). Cambiarlo aquí lo cambia en todo el panel. */
const AG_GOB_NOMBRE = 'Centro de mando';
const homePath = () => { if (esSuperAdmin() && AGENCY_CONTEXT) return '/agencia'; const m = homeMod(); return m ? MODS[m].path : '/entrar'; };
const homeRoute = () => { const m = homeMod() || 'convenios'; return {view: MODS[m].view, mod: m}; };
/** La clave de filtro de cada vista, para las tarjetas que filtran al pulsarlas. */
const FKEY = {contratos: 'estado', tareas: 'estado', usuarios: 'rol'};

function route() {
  const p = path();
  if (p === '/entrar') return {view: 'entrar'};
  if (p === '/agencia/control') return {view:'agencyControl'};
  if (/^\/agencia\/subcuenta\/\d+$/.test(p)) return {view:'agencyAccount', id: p.split('/').pop()};
  if (p === '/agencia/usuarios') return {view:'agencyTeam'};
  if (p === '/agencia/actividad') return {view:'agencyActivity'};
  if (p === '/agencia/textos') return {view:'agencyTexts'};
  if (p === '/agencia/estudio-ia') return {view:'agencyStudio'};
  if (p === '/perfil') return {view:'perfil'};
  if (p === '/agencia') return {view: 'agencia'};
  for (const id of Object.keys(MODS)) if (p === MODS[id].path) return {view: MODS[id].view, mod: id};
  if (p === '/tablero') return {view: 'ag_dashboard', mod: 'comercial'};
  if (p === '/personas') return {view: 'usuarios', mod: 'usuarios'};
  if (p === '/conexiones') return {view: 'ag_settings', mod: 'conexiones'};
  if (p === '/contratos/nuevo') return {view: 'nuevo', mod: 'convenios'};
  let m = p.match(/^\/contratos\/([a-f0-9]{24})$/); if (m) return {view: 'detalle', mod: 'convenios', uid: m[1]};
  m = p.match(/^\/conversations\/([A-Za-z0-9_-]{1,80})$/);
  if (m) return {view: 'ag_inbox', mod: 'bandeja', conversation: m[1]};
  m = p.match(/^\/modulos\/([a-z-]{2,30})$/);
  if (m) { const g = Object.keys(GRUPOS).find(k => GRUPOS[k].slug === m[1]); if (g) return {view: 'grupo', mod: 'centro', grupo: g}; }
  if (p === '/') return homeRoute();
  return {...homeRoute(), unknown: true};
}

/* =====================================================================================
   Componentes (los del v3)
   ===================================================================================== */
/* Esqueleto común de la agencia (2.1.9): cabecera de sección con icono, título y una línea de ayuda. */
const secH = (ic, titulo, desc = '', acts = '') => `<header class="pf-sec-h"><span class="pf-sec-ic" aria-hidden="true">${icon(ic)}</span><div class="pf-sec-tx"><h2>${titulo}</h2>${desc ? `<p>${desc}</p>` : ''}</div>${acts ? `<div class="pf-sec-acts">${acts}</div>` : ''}</header>`;
const head = (eyebrow, title, lede = '', acts = '') => `<div class="mod-h"><div><p class="t-label">${eyebrow}</p><h1 class="t-module">${title}</h1>${lede ? `<p class="lede">${lede}</p>` : ''}</div>${acts ? `<div class="acts">${acts}</div>` : ''}</div>`;
const panel = (title, sub, body, extra = '', cls = '') => `<section class="panel ${cls}"><div class="panel-h"><div><h2 class="t-panel">${title}</h2>${sub ? `<p>${sub}</p>` : ''}</div>${extra}</div>${body}</section>`;
const metric = (l, v, f = '', {filter, on, cls = ''} = {}) => filter !== undefined
  ? `<button type="button" class="micro ${on ? 'is-on' : ''} ${cls}" data-filter="${esc(filter)}" aria-pressed="${!!on}"><h3>${l}</h3><p class="t-metric" style="margin:0">${v}</p>${f ? `<span class="f">${f}</span>` : ''}</button>`
  : `<div class="micro ${cls}"><h3>${l}</h3><p class="t-metric" style="margin:0">${v}</p>${f ? `<span class="f">${f}</span>` : ''}</div>`;
function seg(key, options, value, label, cls = '') {
  return `<div class="seg ${cls}" role="radiogroup" aria-label="${esc(label)}" data-seg="${key}"><span class="seg-thumb"></span>${options.map(([v, l, n]) => `<button type="button" class="seg-b ${String(v) === String(value) ? 'is-on' : ''}" role="radio" aria-checked="${String(v) === String(value)}" data-v="${esc(v)}">${l}${n != null ? ` <span class="n">${int(n)}</span>` : ''}</button>`).join('')}</div>`;
}
function placeThumbs(root = document, animate = false) {
  $$('.seg', root).forEach(s => {
    const on = s.querySelector('.seg-b.is-on'), th = s.querySelector('.seg-thumb'); if (!th) return;
    if (!on || !on.offsetWidth) { th.style.opacity = 0; return; }
    if (!animate) th.style.transition = 'none';
    th.style.opacity = 1; th.style.width = on.offsetWidth + 'px'; th.style.transform = `translateX(${on.offsetLeft}px)`;
    if (!animate) { void th.offsetWidth; th.style.transition = ''; }
  });
}
function table(cols, rows, {sortKey, sortDir, rowHref, empty, foot} = {}) {
  const th = cols.map(c => `<th class="${c.n ? 'n' : ''}" ${c.sort ? `aria-sort="${sortKey === c.k ? (sortDir > 0 ? 'ascending' : 'descending') : 'none'}"` : ''}>${c.sort ? `<button type="button" data-sort="${c.k}">${esc(c.l)}${sortKey === c.k ? (sortDir > 0 ? ' ↑' : ' ↓') : ''}</button>` : esc(c.l)}</th>`).join('');
  const body = rows.length ? rows.map(r => `<tr${rowHref ? ` data-href="${esc(rowHref(r))}" tabindex="0"` : ''}>${cols.map(c => `<td class="${c.n ? 'n' : ''} ${c.cls || ''}">${c.f ? c.f(r[c.k], r) : esc(r[c.k] ?? '—')}</td>`).join('')}</tr>`).join('')
    : `<tr><td colspan="${cols.length}" class="empty">${empty || '<b>No hay registros con estos filtros</b>Prueba con otra búsqueda o quita los filtros.<br><button class="btn btn-sm" type="button" data-clear>Quitar filtros</button>'}</td></tr>`;
  return `<div class="tw full"><table><thead><tr>${th}</tr></thead><tbody>${body}</tbody>${foot || ''}</table></div>`;
}
function sortRows(rows, st) { if (!st.sk) return rows; const k = st.sk, d = st.sd; return [...rows].sort((a, b) => { const x = a[k] ?? '', y = b[k] ?? ''; return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), 'es')) * d; }); }
function pager(st, total, per) { const pages = Math.max(1, Math.ceil(total / per)); st.p = Math.min(st.p, pages - 1); return pages < 2 ? `<div class="pager"><span>${int(total)} registro${total === 1 ? '' : 's'}</span></div>` : `<div class="pager"><span>${int(total)} registros · página ${st.p + 1} de ${pages}</span><div><button class="btn btn-sm" data-page="-1" ${st.p ? '' : 'disabled'}>Anterior</button><button class="btn btn-sm" data-page="1" ${st.p >= pages - 1 ? 'disabled' : ''}>Siguiente</button></div></div>`; }
const searchFld = (val, ph, key = 'q') => `<label class="fld grow">Buscar<span class="search-in">${icon('search')}<input type="search" data-f="${key}" value="${esc(val)}" placeholder="${esc(ph)}" autocomplete="off" enterkeyhint="search"></span></label>`;
const loading = (title = '') => `${title ? head(title, '<span class="sk w40" style="height:30px"></span>') : ''}<div class="grid g4">${'<div class="micro"><span class="sk w60"></span><span class="sk big"></span></div>'.repeat(4)}</div><div class="panel mt"><span class="sk w40"></span><span class="sk w80"></span><span class="sk w60"></span><span class="sk w80"></span></div>`;
const failed = (msg) => `<div class="panel"><p class="empty"><b>No se pudo cargar</b>${esc(msg)}<br><button class="btn btn-sm" type="button" data-retry>Intentar de nuevo</button></p></div>`;
const noAccess = () => `<section class="panel"><p class="empty"><b>${esc(MODS[R.mod]?.name || 'Módulo')} · Acceso restringido</b>Esta opción permanece visible. Tu rol no tiene permiso para abrirla en ${esc(ws?.name() || 'esta subcuenta')}. Consulta al administrador.<br><a class="btn btn-cta" href="${href(homePath())}">${icon('home')}Volver al inicio</a><button class="btn" type="button" data-open-modules>${icon('sidebar')}Ver todos los módulos</button></p></section>`;
const kv = pairs => `<dl class="kv">${pairs.map(([k, v]) => `<dt>${k}</dt><dd>${v}</dd>`).join('')}</dl>`;

/* =====================================================================================
   Armazón: barra superior con los módulos, pestañas en celular y pie
   ===================================================================================== */
let R = route(), GEN = 0;
const V = {};
function render(focus) {
  closeTip?.();
  R = route();
  if (SWITCHING) { topbar(); paint(loading()); return; }
  const root = document.documentElement;
  if (!ME || !AGORA_ME) { root.classList.add('is-auth'); return V.entrar.render(); }
  if (R.view === 'entrar') { go(homePath(), {replace: true}); return; }
  if (esSuperAdmin() && AGENCY_CONTEXT && !['agencia','perfil','agencyControl','agencyAccount','agencyTeam','agencyTexts','agencyStudio','agencyActivity'].includes(R.view)) { toast('Selecciona una subcuenta para abrir sus módulos.'); go('/agencia', {replace:true}); return; }
  if (R.view === 'agencia' && !esSuperAdmin()) { go(homePath(), {replace:true}); return; }
  if (R.view === 'agencia' && esSuperAdmin() && !AGENCY_CONTEXT) { resetAccountData(); AGENCY_CONTEXT = true; }
  root.classList.remove('is-auth'); $('#lg')?.remove();
  // Un enlace a una conversación concreta: la bandeja la abre al cargar.
  if (R.conversation && ws) {
    ws.pendingConversation = {id: R.conversation, explicit: false};
    history.replaceState(null, '', HASH ? location.pathname + '#/bandeja' : '/bandeja');
    R = {view: 'ag_inbox', mod: 'bandeja'};
  }
  if (R.unknown) { toast('Esa dirección no existe. Te llevamos al inicio.'); go(homePath(), {replace:true}); return; }
  if (['agencyControl','agencyAccount','agencyTeam','agencyTexts','agencyStudio','agencyActivity'].includes(R.view) && !esSuperAdmin()) { topbar();paint(agencyRouteDenied());return; }
  topbar();
  const gen = ++GEN;
  const v = V[R.view];
  if (!v) { paint(failed('Esta pantalla no existe.')); return; }
  if (R.mod && !MODS[R.mod].ok()) { paint(noAccess()); return; }
  if (R.mod && moduloOculto(R.mod)) { paint(moduloApagado()); return; }
  try { paint(v.render(), true); } catch (err) { paint(failed('No se pudo mostrar el módulo. Vuelve a intentarlo.')); return; }
  if (v.need) {
    v.need().then(() => { if (gen === GEN && !SWITCHING) { topbar(); paint(R.mod && !moduleAllowed(R.mod) ? noAccess() : v.render()); } })
      .catch(err => { if (gen === GEN && !err.staleContext) { paint(failed(err.message || 'No se pudo cargar el módulo.')); } });
  }
  if (R.mod) anotarHistorial();
  document.title = `${R.view === 'nuevo' ? 'Nuevo convenio' : MODS[R.mod]?.name || (R.view === 'perfil' ? 'Perfil de cuenta' : 'Inicio')} · Agora Digital App`;
  if (focus) $('#app').focus({preventScroll: true});
  if (focus) scrollTo({top: 0});
}
function paint(html, enter) {
  const app = $('#app');
  // Si lo que había era un módulo de Ágora, se suelta antes de reemplazarlo: así para
  // sus relojes, sus consultas en vivo y sus escuchas, como hacía en su propia cáscara.
  disposeAgora();
  // El lienzo cálido del reporte de gestión solo se enciende en la pantalla de inicio.
  document.body.classList.toggle('nv-on', R.view === 'tc_resumen' && typeof TB !== 'undefined' && TB.estado === 'listo');
  // En la barra no cabe el periodo cuando la pantalla es estrecha: baja al lienzo.
  const per = MODS[R.mod]?.periodo ? `<div class="per-m">${periodBar()}</div>` : '';
  app.innerHTML = `${per}<div class="${enter ? 'view-enter' : ''}">${html}</div>`;
  placeThumbs(document);
  V[R.view]?.mounted?.(app);
  applyUiTexts();
  aplicarPaleta(); arpVigilar();
  syncAgencyRail();
}
const repaint = () => { if (V[R.view]) { topbar(); paint(V[R.view].render()); } };
window.addEventListener('resize', () => placeThumbs(document));

/* ---------- Capas: menús, hojas, avisos (las del v3) ---------- */
let pop = null, popAnchor = null, modal = null, lastFocus = null, toastT = 0;
function closePop() { if (!pop) return; const p = pop; pop = null; popAnchor?.setAttribute('aria-expanded', 'false'); popAnchor = null; if (reduced()) p.remove(); else { p.classList.add('out'); setTimeout(() => p.remove(), 180); } }
function openPop(anchor, html, cls, {align = 'left'} = {}) {
  const same = popAnchor && popAnchor === anchor; closePop(); if (same) return null;
  const el = document.createElement('div'); el.className = 'pop ' + cls; el.innerHTML = html; $('#layer').append(el);
  const r = anchor.getBoundingClientRect(), w = el.offsetWidth, h = el.offsetHeight;
  let left = align === 'right' ? r.right - w : r.left; left = Math.max(12, Math.min(innerWidth - w - 12, left));
  let top = r.bottom + 8; if (top + h > innerHeight - 12) top = Math.max(12, r.top - h - 8);
  el.style.left = left + 'px'; el.style.top = top + 'px'; el.style.setProperty('--ox', Math.max(16, (r.left + r.width / 2) - left) + 'px');
  pop = el; popAnchor = anchor; anchor.setAttribute('aria-expanded', 'true');
  return el;
}
function openModal(html, {cls = 'modal', wide} = {}) {
  closeModal(true); closePop(); lastFocus = document.activeElement;
  const s = document.createElement('div'); s.className = 'scrim';
  s.innerHTML = `<div class="${cls} ${wide ? 'wide' : ''}" role="dialog" aria-modal="true">${html}</div>`;
  $('#layer').append(s); modal = s; placeThumbs(s);
  (s.querySelector('[autofocus]') || s.querySelector('input, select, button:not(.x), a'))?.focus({preventScroll: true});
  return s;
}
function closeModal(instant) { if (!modal) return; const m = modal; modal = null; if (instant || reduced()) m.remove(); else { m.classList.add('out'); setTimeout(() => m.remove(), 160); } if (!instant) lastFocus?.focus?.({preventScroll: true}); }
const modalHead = (t, sub = '') => `<div class="modal-h"><div><h2>${t}</h2>${sub ? `<p>${sub}</p>` : ''}</div><button class="x" type="button" data-close aria-label="Cerrar">${icon('plus')}</button></div>`;
function closeAll() { closePop(); closeModal(); }
function toast(msg) { const t = $('#toast'); t.textContent = msg; t.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('on'), 2800); }
const busy = (btn, on, label) => { if (!btn) return; if (on) { btn.dataset.l = btn.innerHTML; btn.disabled = true; btn.innerHTML = `<span class="spin" aria-hidden="true"></span>${esc(label || 'Un momento…')}`; } else { btn.disabled = false; if (btn.dataset.l) btn.innerHTML = btn.dataset.l; } };
function confirmModal(title, text, label, fn, danger) {
  const s = openModal(modalHead(esc(title)) + `<p style="margin:0">${text}</p><p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn ${danger ? 'btn-danger' : 'btn-cta'}" type="button" data-ok>${esc(label)}</button></div>`);
  s.querySelector('[data-ok]').onclick = async e => {
    const b = e.currentTarget; busy(b, true);
    try { await fn(); closeModal(); } catch (x) { busy(b, false); s.querySelector('.fld-err').textContent = x.message; }
  };
}
async function copy(text, msg = 'Copiado') {
  try { await navigator.clipboard.writeText(text); toast(msg); }
  catch { const t = document.createElement('textarea'); t.value = text; document.body.append(t); t.select(); try { document.execCommand('copy'); toast(msg); } catch { toast('Selecciona el texto y cópialo a mano.'); } t.remove(); }
}

/* ---------- Tooltip para métricas que lo usan ---------- */
const tipEl = () => $('#tip');
function closeTip() { tipEl()?.classList.remove('on'); }

/* =====================================================================================
   Vistas
   ===================================================================================== */
const state = {
  contratos: {q: '', estado: '', p: 0, sk: 'creado_en', sd: -1},
  contactos: {tab: 'clientes', q: '', p: 0, sk: 'ultimo', sd: -1, consulta: '', resultados: null, aviso: '', buscando: false, error: ''},
  nuevo: null,
  detalle: {verPdf: false},
  personas: {q: '', p: 0},
  registro: {q: '', tipo: '', p: 0},
  tareas: {q: '', estado: 'abiertas', resp: '', p: 0, sk: 'vence', sd: 1},
  auditoria: {q: '', modulo: '', actor: '', p: 0},
  usuarios: {q: '', rol: '', p: 0},
};
const INITIAL_VIEW_STATE = JSON.parse(JSON.stringify(state));
const PER = 25;

/* ---------- Contratos ---------- */
V.contratos = {
  need: () => DATA.contratos ? Promise.resolve() : loadContratos(),
  render() {
    if (this.error) return head('Convenios de pago', 'Contratos') + failed(this.error);
    if (!DATA.contratos) return loading('Convenios de pago');
    const st = state.contratos, all = DATA.contratos;
    const n = e => all.filter(c => c.estado === e).length;
    const enviados = n('ENVIADO') + n('FIRMADO') + n('REVOCADA');
    const mes = new Date().toISOString().slice(0, 7);
    const firmMes = all.filter(c => c.estado === 'FIRMADO' && String(c.creado_en).slice(0, 7) === mes).length;
    const q = norm(st.q);
    let rows = all.filter(c => (!st.estado || c.estado === st.estado) && (!q || norm(c.cliente + ' ' + c.correo).includes(q)));
    rows = sortRows(rows, st);
    const page = rows.slice(st.p * PER, st.p * PER + PER);
    const acts = can('editar') ? `<a class="btn btn-cta" href="${href('/contratos/nuevo')}">${icon('plus')}Nuevo convenio</a>` : '';
    const lede = ME.rol === 'ADMIN' ? 'Todos los convenios de la subcuenta, del más reciente al más antiguo.' : 'Ves solo los convenios que tienes asignados.';
    if (!all.length) return head('Convenios de pago', 'Contratos', lede, acts) + panel('Aún no hay contratos', '', `<p class="empty"><b>Todavía no hay convenios</b>${can('editar') ? 'Crea el primero con «Nuevo convenio» y elige un contacto de AGORA.' : 'Cuando te asignen uno, aparecerá aquí.'}${can('editar') ? `<br><a class="btn btn-cta" href="${href('/contratos/nuevo')}">${icon('plus')}Nuevo convenio</a>` : ''}</p>`);
    const cols = [
      {k: 'cliente', l: 'Cliente', sort: 1, f: (v, r) => `<span class="who-cell"><b>${esc(v)}</b><small>${esc(r.correo)}</small></span>`},
      {k: 'estado', l: 'Estado', sort: 1, f: v => chip(v)},
      {k: 'version', l: 'Versión', n: 1, sort: 1, f: v => int(v)},
      {k: 'creado_en', l: 'Creado', sort: 1, f: v => `<span title="${esc(fstamp(v))}">${esc(fdate(v))}</span>`},
      {k: 'uid', l: '', cls: 'act', f: () => icon('chevR')},
    ];
    return head('Convenios de pago', 'Contratos', lede, acts)
      + `<div class="grid g4">
        ${metric('Todos', int(all.length), `${int(clientes().length)} cliente${clientes().length === 1 ? '' : 's'}`, {filter: '', on: !st.estado})}
        ${metric('Por firmar', int(n('ENVIADO')), 'Enviados, con el enlace del cliente', {filter: 'ENVIADO', on: st.estado === 'ENVIADO'})}
        ${metric('Firmados', int(n('FIRMADO')), `${pct(enviados ? n('FIRMADO') / enviados : null)} de lo enviado · ${int(firmMes)} de este mes`, {filter: 'FIRMADO', on: st.estado === 'FIRMADO'})}
        ${metric('Borradores', int(n('BORRADOR')), n('REVOCADA') ? `${int(n('REVOCADA'))} revocado${n('REVOCADA') === 1 ? '' : 's'}` : 'Sin enviar todavía', {filter: 'BORRADOR', on: st.estado === 'BORRADOR'})}
      </div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Nombre o correo del cliente')}
          ${seg('estado', [['', 'Todos', all.length], ...Object.keys(ESTADO).map(e => [e, ESTADO[e].plural, n(e)])], st.estado, 'Filtrar por estado')}
          ${st.q || st.estado ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
        <div id="region">${table(cols, page, {sortKey: st.sk, sortDir: st.sd, rowHref: r => '/contratos/' + r.uid, empty: '<b>Ningún contrato coincide con la búsqueda o el filtro</b>Prueba con otro nombre o quita los filtros.<br><button class="btn btn-sm" type="button" data-clear>Quitar filtros</button>'})}${pager(st, rows.length, PER)}</div></section>`;
  },
};

/* ---------- Contactos (nuevo) ---------- */
V.contactos = {
  need: () => DATA.contratos ? Promise.resolve() : loadContratos(),
  render() {
    const st = state.contactos, puedeBuscar = can('editar');
    if (!puedeBuscar && st.tab === 'agora') st.tab = 'clientes';
    const lede = 'Las personas con convenio y los contactos de AGORA. Desde aquí abres sus convenios o creas uno nuevo.';
    const tabs = puedeBuscar ? `<div class="mb">${seg('ctab', [['clientes', 'Con convenio'], ['agora', 'Buscar en AGORA']], st.tab, 'Vista de contactos')}</div>` : '';
    if (this.error) return head('Directorio', 'Contactos', lede) + failed(this.error);
    if (!DATA.contratos) return loading('Directorio');
    return head('Directorio', 'Contactos', lede) + tabs + (st.tab === 'agora' ? this.agora() : this.clientes());
  },
  clientes() {
    const st = state.contactos, list = clientes(), q = norm(st.q);
    const firm = list.filter(c => c.n.FIRMADO).length, pend = list.filter(c => c.n.ENVIADO).length, solo = list.filter(c => c.total === c.n.BORRADOR).length;
    let rows = list.filter(c => !q || norm(c.nombre + ' ' + c.correo).includes(q)).map(c => ({...c, firmados: c.n.FIRMADO, pendientes: c.n.ENVIADO}));
    rows = sortRows(rows, st);
    const page = rows.slice(st.p * PER, st.p * PER + PER);
    const cols = [
      {k: 'nombre', l: 'Cliente', sort: 1, f: (v, r) => `<span class="who-cell"><b>${esc(v)}</b><small>${esc(r.correo)}</small></span>`},
      {k: 'total', l: 'Convenios', n: 1, sort: 1, f: int},
      {k: 'firmados', l: 'Firmados', n: 1, sort: 1, f: int},
      {k: 'pendientes', l: 'Por firmar', n: 1, sort: 1, f: v => v ? `<b class="strong">${int(v)}</b>` : '0'},
      {k: 'estado', l: 'Último estado', f: v => chip(v)},
      {k: 'ultimo', l: 'Último convenio', sort: 1, f: v => `<span title="${esc(fstamp(v))}">${esc(when(v))}</span>`},
      {k: 'k', l: '', cls: 'act', f: (v, r) => `<button class="btn btn-sm" type="button" data-ficha="${esc(r.k)}">${icon('user')}<span>Ficha</span></button>${can('editar') ? `<button class="btn btn-sm btn-tint" type="button" data-nuevo-correo="${esc(r.correo)}" data-nuevo-nombre="${esc(r.nombre)}">${icon('plus')}<span>Convenio</span></button>` : ''}`},
    ];
    if (!list.length) return panel('Personas con convenio', '', `<p class="empty"><b>Todavía no hay personas con convenio</b>${can('editar') ? `Busca un contacto en AGORA y crea su primer convenio.<br><button class="btn btn-cta" type="button" data-tab="agora">${icon('search')}Buscar en AGORA</button>` : ''}</p>`);
    return `<div class="grid g4">
        ${metric('Personas con convenio', int(list.length), `${int((DATA.contratos || []).length)} convenios en total`)}
        ${metric('Con convenio firmado', int(firm), pct(list.length ? firm / list.length : null) + ' de las personas')}
        ${metric('Con firma pendiente', int(pend), 'Tienen un enlace por firmar')}
        ${metric('Solo borradores', int(solo), 'Aún no se les envía nada')}
      </div>
      <section class="panel mt"><div class="tools">${searchFld(st.q, 'Nombre o correo')}${st.q ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
      <div id="region">${table(cols, page, {sortKey: st.sk, sortDir: st.sd, empty: '<b>Nadie coincide con la búsqueda</b>Prueba con otro nombre o correo.<br><button class="btn btn-sm" type="button" data-clear>Quitar filtros</button>'})}${pager(st, rows.length, PER)}</div>
      <p class="src">Sale de tus convenios${ME.rol === 'ADMIN' ? '' : ' asignados'}, agrupados por correo. Para ver a alguien sin convenio, búscalo en AGORA.</p></section>`;
  },
  agora() {
    const st = state.contactos, res = st.resultados;
    const cols = [
      {k: 'nombre', l: 'Nombre', f: (v, r) => `<span class="who-cell"><b>${esc(v || 'Sin nombre')}</b><small>${esc(r.correo || 'Sin correo')}</small></span>`},
      {k: 'telefono', l: 'Teléfono', f: v => v ? esc(v) : '<span class="muted">—</span>'},
      {k: 'cedula', l: 'Cédula', f: v => v ? esc(v) : '<span class="muted">Sin cédula</span>'},
      {k: 'correo', l: 'Convenios', n: 1, f: v => { const n = convPorCorreo(v).length; return n ? `<button class="lnk-btn" type="button" data-ver-correo="${esc(v)}">${int(n)}</button>` : '0'; }},
      {k: 'id', l: '', cls: 'act', f: (v, r) => `<button class="btn btn-sm btn-cta" type="button" data-crear="${esc(v)}">${icon('plus')}<span>Crear convenio</span></button>`},
    ];
    let body = '';
    if (st.error) body = `<div class="note-gold">${esc(st.error)}</div>`;
    else if (st.buscando) body = `<div style="margin-top:14px"><span class="sk w80"></span><span class="sk w60"></span><span class="sk w80"></span></div>`;
    else if (res) body = (st.aviso ? `<div class="note-gold">${esc(st.aviso)}</div>` : '') + (res.length || !st.aviso ? `<div class="mt">${table(cols, res, {empty: '<b>No hay contactos con esa búsqueda</b>Revisa el nombre o agrégalo en AGORA y vuelve a buscar.'})}</div>` : '');
    else body = '<p class="src">Escribe al menos dos letras del nombre, el correo o el teléfono.</p>';
    return panel('Buscar en AGORA', ME.rol === 'ADMIN' ? 'Todos los contactos de la subcuenta.' : 'Solo los contactos que tienes asignados en AGORA.',
      `<form class="search-row" data-form="buscar-agora" novalidate><label class="fld">Contacto<span class="search-in">${icon('search')}<input type="search" name="consulta" value="${esc(st.consulta)}" placeholder="Nombre, correo o teléfono" autocomplete="off" enterkeyhint="search" autofocus></span></label><button class="btn btn-cta" type="submit">Buscar</button></form>${body}`);
  },
};
async function buscarAgora(consulta) {
  const st = state.contactos;
  st.consulta = consulta.trim();
  if (st.consulta.length < 2) { st.error = 'Escribe al menos dos letras.'; repaint(); return; }
  st.buscando = true; st.error = ''; repaint();
  try { const d = await api('ghl/contactos/buscar', {method: 'POST', body: {consulta: st.consulta}}); st.resultados = d.contactos || []; st.aviso = d.aviso || ''; }
  catch (x) { st.error = x.message; st.resultados = null; }
  st.buscando = false;
  if (R.view === 'contactos') repaint();
}
function fichaCliente(k) {
  const c = clientes().find(x => x.k === k); if (!c) return;
  const lista = [...c.lista].sort((a, b) => String(b.creado_en).localeCompare(String(a.creado_en)));
  const s = openModal(modalHead(esc(c.nombre), esc(c.correo)) + `<div class="grid g3" style="gap:10px">${metric('Convenios', int(c.total))}${metric('Firmados', int(c.n.FIRMADO))}${metric('Por firmar', int(c.n.ENVIADO))}</div>
    <h4 class="h4">Sus convenios</h4><ul class="mini-list">${lista.map(x => `<li><a href="${href('/contratos/' + x.uid)}"><span><b>Versión ${int(x.version)}</b><small>Creado ${esc(fdate(x.creado_en))}</small></span>${chip(x.estado)}</a></li>`).join('')}</ul>
    <div class="form-ft"><button class="btn" type="button" data-close>Cerrar</button>${can('editar') ? `<button class="btn btn-cta" type="button" data-nuevo-correo="${esc(c.correo)}" data-nuevo-nombre="${esc(c.nombre)}">${icon('plus')}Nuevo convenio</button>` : ''}</div>`);
  s.querySelectorAll('a').forEach(a => a.addEventListener('click', () => closeModal(true)));
}
/* Abrir Nuevo convenio con el contacto ya elegido (desde AGORA) o con la búsqueda lista (desde un cliente con convenio) */
function nuevoCon({contacto, buscar}) {
  state.nuevo = nuevoInicial();
  if (contacto) elegirContacto(contacto);
  if (buscar) { state.nuevo.consulta = buscar; state.nuevo.autoBuscar = true; }
  closeAll(); go('/contratos/nuevo');
}

/* ---------- Nuevo convenio ---------- */
function nuevoInicial() {
  const d = new Date(), next = new Date(d.getFullYear(), d.getMonth() + 1, 1);
  return {consulta: '', resultados: null, aviso: '', buscando: false, error: '', elegido: null, autoBuscar: false,
    f: {nombre: '', cedula: '', correo: '', cuota: '', meses: '12', anio: String(next.getFullYear()), mes: String(next.getMonth() + 1), cuotasPorMes: '1'},
    calc: null, calcErr: '', guardando: false, errorForm: ''};
}
function elegirContacto(c) {
  const s = state.nuevo; s.elegido = c; s.f.nombre = c.nombre || ''; s.f.cedula = c.cedula || ''; s.f.correo = c.correo || ''; s.errorForm = '';
}
const anios = () => { const y = new Date().getFullYear(); return [y, y + 1]; };
V.nuevo = {
  render() {
    if (!can('editar')) return noAccess();
    const s = state.nuevo ||= nuevoInicial(), c = s.elegido, f = s.f;
    const back = `<a class="back" href="${href('/contratos')}">${icon('chevL')}Contratos</a>`;
    const step1 = c
      ? `<div class="chosen"><span class="ini">${esc(initials(c.nombre))}</span><span class="tx"><b>${esc(c.nombre || 'Sin nombre')}</b><small>${esc([c.correo, c.telefono, c.cedula].filter(Boolean).join(' · ') || 'Sin datos de contacto')}</small></span><button class="btn btn-sm" type="button" data-cambiar>Cambiar</button></div>`
      : `<p class="src" style="margin:0 0 12px">Busca al alumno o representante entre los contactos de AGORA. Si no aparece, agrégalo en AGORA y vuelve a buscar.</p>
        <form class="search-row" data-form="buscar-nuevo" novalidate><label class="fld">Contacto<span class="search-in">${icon('search')}<input type="search" name="consulta" value="${esc(s.consulta)}" placeholder="Nombre, correo o teléfono" autocomplete="off" enterkeyhint="search" autofocus></span></label><button class="btn btn-cta" type="submit">Buscar</button></form>
        ${s.error ? `<div class="note-gold">${esc(s.error)}</div>` : ''}${s.aviso ? `<div class="note-gold">${esc(s.aviso)}</div>` : ''}
        ${s.buscando ? '<div style="margin-top:14px"><span class="sk w80"></span><span class="sk w60"></span></div>' : s.resultados ? (s.resultados.length ? `<div class="pick" role="list">${s.resultados.map((r, i) => `<button type="button" role="listitem" data-pick="${i}"><span class="ini">${esc(initials(r.nombre))}</span><span class="tx"><b>${esc(r.nombre || 'Sin nombre')}</b><small>${esc([r.correo, r.telefono, r.cedula].filter(Boolean).join(' · ') || 'Sin datos de contacto')}</small></span>${convPorCorreo(r.correo).length ? `<span class="sc sc-gray">${int(convPorCorreo(r.correo).length)} convenio${convPorCorreo(r.correo).length === 1 ? '' : 's'}</span>` : ''}${icon('chevR')}</button>`).join('')}</div>` : (s.aviso ? '' : '<p class="empty"><b>No hay contactos con esa búsqueda</b>Revisa el nombre o agrégalo en AGORA.</p>')) : ''}`;
    const fld = (k, l, {type = 'text', hint = '', mode = '', ph = '', req} = {}) => `<label class="fld">${l}${req ? '' : ''}<input name="${k}" type="${type}" value="${esc(f[k])}" ${mode ? `inputmode="${mode}"` : ''} placeholder="${esc(ph)}" autocomplete="off">${hint ? `<small class="hint">${hint}</small>` : ''}</label>`;
    const sel = (k, l, opts) => `<label class="fld">${l}<select name="${k}">${opts.map(([v, t]) => `<option value="${v}" ${String(v) === String(f[k]) ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>`;
    const step2 = `<form class="form" data-form="nuevo" novalidate>
        <div class="grid g2">${fld('nombre', 'Nombre completo')}${fld('cedula', 'Cédula', {ph: 'V-12345678'})}</div>
        ${fld('correo', 'Correo', {type: 'email', hint: 'Si el contacto no tiene correo, escríbelo aquí. Se guarda también en AGORA.'})}
        <p class="h4" style="margin:6px 0 0">Plan de pago</p>
        <div class="grid g2">${fld('cuota', 'Cuota mensual ($)', {mode: 'decimal', ph: '150,00'})}${fld('meses', 'Meses', {type: 'number', mode: 'numeric', ph: '12', hint: 'De 1 a 20 meses.'})}</div>
        <div class="grid g2">${sel('anio', 'Año de inicio', anios().map(y => [y, y]))}${sel('mes', 'Mes de inicio', MESES.map((m, i) => [i + 1, cap(m)]))}</div>
        <div class="seg-fld">Cuotas por mes${seg('cpm', [['1', '1 cuota'], ['2', '2 cuotas'], ['4', '4 cuotas']], f.cuotasPorMes, 'Cuotas por mes')}<small class="hint" style="font-weight:400">1 cuota vence el 30; 2 cuotas, el 15 y el 30; 4 cuotas, los viernes.</small></div>
        <p class="fld-err" role="alert">${esc(s.errorForm)}</p>
        <div class="form-ft"><a class="btn" href="${href('/contratos')}">Cancelar</a><button class="btn btn-cta" type="submit" ${s.guardando ? 'disabled' : ''}>${s.guardando ? '<span class="spin" aria-hidden="true"></span>Creando…' : 'Crear convenio'}</button></div>
      </form>`;
    return back + head('Contratos', 'Nuevo convenio', 'Elige el contacto de AGORA, completa los datos y revisa el cálculo antes de crearlo. Queda como borrador: nada se envía al cliente todavía.')
      + `<div class="grid g-side"><div class="grid">
          ${panel(`<span class="step ${c ? 'done' : ''}">${c ? '✓' : '1'}</span>Busca el contacto`, '', step1)}
          ${panel('<span class="step">2</span>Datos del convenio', c ? '' : 'Primero elige el contacto.', step2, '', c ? '' : 'is-off')}
        </div><aside class="sticky" id="nv-sum">${this.summary()}</aside></div>`;
  },
  summary() {
    const s = state.nuevo, f = s.f, k = s.calc, cuotas = num(f.cuota), meses = parseInt(f.meses, 10) || 0;
    const ini = f.mes && f.anio ? `${cap(MESES[f.mes - 1])} ${f.anio}` : '—';
    return `<section class="hero"><div><p class="t-label">Total a pagar</p><p class="t-hero" style="margin:10px 0 0">${k ? esc(money(k.total_a_pagar)) : '—'}</p>
        <p class="f">${k ? `${int(k.cuotas)} cuota${k.cuotas === 1 ? '' : 's'} desde ${esc(ini.toLowerCase())}` : s.calcErr ? esc(s.calcErr) : 'Escribe la cuota mensual y los meses para ver el cálculo.'}</p></div>
      <div class="goal">${kv([['Cuota mensual', cuotas ? esc(money(cuotas)) : '—'], ['Meses', meses ? int(meses) : '—'], ['Cuotas por mes', esc(f.cuotasPorMes)], ['Precio del curso', k ? esc(money(k.precio_curso)) : '—']])}
      <p class="f" style="margin:0">El cálculo es el mismo del convenio: precio = cuota × meses. Los cargos como la inscripción se agregan después, en el detalle.</p></div></section>`;
  },
  mounted(app) {
    const s = state.nuevo;
    if (s?.autoBuscar) { s.autoBuscar = false; buscarNuevo(s.consulta); return; }
    if (s?.elegido && !s.calc && !s.calcErr) calcular();
  },
};
async function buscarNuevo(consulta) {
  const s = state.nuevo; s.consulta = consulta.trim();
  if (s.consulta.length < 2) { s.error = 'Escribe al menos dos letras.'; repaint(); return; }
  s.buscando = true; s.error = ''; s.aviso = ''; repaint();
  try { const d = await api('ghl/contactos/buscar', {method: 'POST', body: {consulta: s.consulta}}); s.resultados = d.contactos || []; s.aviso = d.aviso || ''; }
  catch (x) { s.error = x.message; s.resultados = null; }
  s.buscando = false; if (R.view === 'nuevo') { repaint(); $('[data-pick]')?.focus({preventScroll: true}); }
}
let calcT = 0, calcSeq = 0;
function calcular() {
  clearTimeout(calcT);
  calcT = setTimeout(async () => {
    const s = state.nuevo; if (!s) return; const f = s.f, seq = ++calcSeq;
    const cuota = String(f.cuota).trim().replace(/\./g, '').replace(',', '.'), meses = parseInt(f.meses, 10);
    if (!cuota || !(num(cuota) > 0) || !meses) { s.calc = null; s.calcErr = ''; paintSum(); return; }
    try {
      const k = await api('contratos/calculo', {method: 'POST', body: {cuota, meses, anio: Number(f.anio), mes: Number(f.mes), cuotas_por_mes: Number(f.cuotasPorMes), cargos: []}});
      if (seq !== calcSeq) return; s.calc = k; s.calcErr = '';
    } catch (x) { if (seq !== calcSeq) return; s.calc = null; s.calcErr = x.message; }
    paintSum();
  }, 280);
}
const paintSum = () => { const el = $('#nv-sum'); if (el && R.view === 'nuevo') el.innerHTML = V.nuevo.summary(); };
async function crearConvenio(btn) {
  const s = state.nuevo, f = s.f, c = s.elegido; if (!c || s.guardando) return;
  const err = m => { s.errorForm = m; const e = $('[data-form="nuevo"] .fld-err'); if (e) e.textContent = m; };
  if (!f.nombre.trim() || !f.cedula.trim()) return err('El nombre y la cédula son obligatorios.');
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.correo.trim())) return err('Escribe un correo válido: por ahí le llega el enlace para firmar.');
  const cuota = String(f.cuota).trim().replace(/\./g, '').replace(',', '.');
  if (!/^\d+(\.\d{1,2})?$/.test(cuota) || !(num(cuota) > 0)) return err('La cuota mensual debe ser un monto mayor que cero, con hasta dos decimales.');
  const meses = parseInt(f.meses, 10); if (!(meses >= 1 && meses <= 20)) return err('Los meses deben estar entre 1 y 20.');
  s.guardando = true; busy(btn, true, 'Creando…'); err('');
  try {
    const {uid} = await api('contratos', {method: 'POST', body: {
      cliente: {contact_id: c.id, nombre: f.nombre.trim(), cedula: f.cedula.trim(), telefono: c.telefono || '', correo: f.correo.trim(), correo_agora: c.correo || ''},
      curso: {cuota_mensual: cuota, meses, mes_inicio: `${f.anio}-${String(f.mes).padStart(2, '0')}`, cuotas_por_mes: Number(f.cuotasPorMes), cargos: []},
      responsable: {nombre: ME.nombre || '', correo: ME.correo || ''},
    }});
    DATA.contratos = null; state.nuevo = null; toast('Convenio creado como borrador.');
    go('/contratos/' + uid);
  } catch (x) { s.guardando = false; busy(btn, false); err(x.message); }
}

/* ---------- Detalle del convenio ---------- */
/* Los términos no vienen en la respuesta: se reconstruyen desde las cuotas (precio = cuota × meses). */
function terminosDe(d) {
  const q = d.calculo.cuotas || [], meses = q.length ? Math.max(...q.map(x => x.mes)) : 12;
  const [y, m] = q.length ? q[0].vencimiento.split('-').map(Number) : [new Date().getFullYear(), new Date().getMonth() + 1];
  const cuota = meses ? (num(d.calculo.precio_curso) / meses) : 0;
  const ins = (d.calculo.cargos || []).find(c => /^inscripci/i.test(norm(c.concepto)));
  return {cuota: cuota ? cuota.toFixed(2).replace('.', ',') : '', meses, anio: y, mes: m, cuotas_por_mes: q.length && meses ? Math.round(q.length / meses) : 1, inscripcion: ins ? String(ins.monto).replace('.', ',') : '0', descuento: d.descuento || '0'};
}
V.detalle = {
  need: () => DATA.detalle[R.uid] ? Promise.resolve() : loadDetalle(R.uid),
  render() {
    const back = `<a class="back" href="${href('/contratos')}">${icon('chevL')}Contratos</a>`;
    if (this.error) return back + failed(this.error);
    const d = DATA.detalle[R.uid]; if (!d) return back + loading();
    const k = d.calculo, q = k.cuotas || [], t = terminosDe(d), e = d.estado;
    const meta = ESTADO[e] || {l: e};
    const acts = [];
    if (e === 'BORRADOR' && can('enviar')) acts.push(`<button class="btn btn-cta" type="button" data-act="enviar">${icon('send')}Preparar enlace para el cliente</button>`);
    if (e === 'ENVIADO' && d.enlace) acts.push(`<button class="btn btn-tint" type="button" data-copy="${esc(d.enlace)}">${icon('split')}Copiar enlace</button>`);
    if (can('editar') && e !== 'FIRMADO') acts.push(`<button class="btn" type="button" data-act="editar">${icon('edit')}Editar cálculo</button>`);
    if (e === 'BORRADOR' && d.version === 1 && can('editar')) acts.push(`<button class="btn btn-danger" type="button" data-act="eliminar">Eliminar borrador</button>`);
    const est = {
      BORRADOR: ['gray', 'edit', 'Revisa el cálculo y el convenio antes de enviar. Al enviar, el cliente recibe el enlace por correo.'],
      ENVIADO: ['info', 'clock', 'El enlace está activo y el cliente todavía no ha firmado.'],
      FIRMADO: ['ok', 'check', 'El convenio está firmado. El cliente y el personal responsable reciben la copia por correo.'],
      REVOCADA: ['bad', 'warn', 'Este enlace fue revocado. Edita el cálculo para crear una versión nueva y enviarla otra vez.'],
    }[e] || ['gray', 'info', ''];
    let enlace = `<div class="state-line ${est[0]}">${icon(est[1])}<span>${esc(est[2])}</span></div>`;
    if (e === 'ENVIADO') {
      enlace += d.enlace
        ? `<div class="secret link mt"><small>Enlace para el cliente. Puedes copiarlo cuando quieras mientras siga vigente.</small><code>${esc(d.enlace)}</code><div class="row"><button class="btn btn-sm btn-tint" type="button" data-copy="${esc(d.enlace)}">${icon('split')}Copiar enlace</button></div></div>`
        : `<p class="src">${d.enlace_guardable ? 'No hay un enlace guardado para mostrar. Genera uno nuevo para compartirlo por otra vía; el anterior dejará de servir.' : 'El servidor no puede guardar el enlace (no puede escribir en la carpeta storage/): se verá solo al generarlo.'}</p>`;
      enlace += `<div class="form-ft" style="justify-content:flex-start">${can('enviar') ? `<button class="btn btn-sm" type="button" data-act="enlace">${icon('refresh')}Generar enlace nuevo</button>` : ''}${can('revocar') ? `<button class="btn btn-sm btn-danger" type="button" data-act="revocar">Revocar enlace</button>` : ''}</div>`;
    }
    const pdfBtns = [];
    if (e === 'BORRADOR') pdfBtns.push(`<a class="btn btn-sm btn-tint" href="/api/contratos/${d.uid}/pdf/previa?workspace=${encodeURIComponent(ws.current())}" target="_blank" rel="noopener">${icon('download')}Vista previa del PDF</a>`);
    if (d.pdfs?.enviado) pdfBtns.push(`<a class="btn btn-sm" href="/api/contratos/${d.uid}/pdf/enviado?workspace=${encodeURIComponent(ws.current())}" target="_blank" rel="noopener">${icon('download')}PDF enviado</a>`);
    if (d.pdfs?.firmado) pdfBtns.push(`<a class="btn btn-sm btn-tint" href="/api/contratos/${d.uid}/pdf/firmado?workspace=${encodeURIComponent(ws.current())}" target="_blank" rel="noopener">${icon('download')}PDF firmado</a>`);
    const pdfSrc = e === 'BORRADOR' ? 'previa' : d.pdfs?.firmado ? 'firmado' : d.pdfs?.enviado ? 'enviado' : '';
    const conv = convPorCorreo(d.cliente.correo).filter(x => x.uid !== d.uid);
    return back + head(`Convenio de pago · versión ${int(d.version)}`, esc(d.cliente.nombre), `${esc(d.cliente.correo)} · ${chip(e)}`, acts.join(''))
      + `<div class="grid g-hero">
        <section class="hero"><div><p class="t-label">Total a pagar</p><p class="t-hero" style="margin:10px 0 0">${esc(money(k.total_a_pagar))}</p>
          <p class="f">${int(q.length)} cuota${q.length === 1 ? '' : 's'} · ${esc(money(t.cuota.replace(',', '.')))} al mes durante ${int(t.meses)} mes${t.meses === 1 ? '' : 'es'}</p></div>
          <div class="goal"><dl class="kv sum"><dt>Precio del curso</dt><dd>${esc(money(k.precio_curso))}</dd>${(k.cargos || []).map(c => `<dt>${esc(c.concepto)}</dt><dd>${esc(money(c.monto))}</dd>`).join('')}<dt class="tot">Total a pagar</dt><dd class="tot">${esc(money(k.total_a_pagar))}</dd></dl></div></section>
        <div class="grid g2">
          ${metric('Estado', chip(e), esc(meta.l === 'Enviado' ? 'Esperando la firma' : meta.l === 'Firmado' ? 'Listo' : meta.l === 'Borrador' ? 'Sin enviar' : 'Necesita versión nueva'))}
          ${metric('Versión', int(d.version), d.version > 1 ? 'Las anteriores quedan en el registro' : 'Primera versión')}
          ${metric('Primera cuota', q[0] ? esc(money(q[0].monto)) : '—', q[0] ? 'Vence el ' + esc(fday(q[0].vencimiento)) : '')}
          ${metric('Última cuota', q.length ? esc(fday(q[q.length - 1].vencimiento)) : '—', `Descuento 1.ª cuota: ${esc(d.descuento || '0')} %`)}
        </div></div>
      <div class="grid g2 mt">
        ${panel('Cliente', e === 'BORRADOR' ? 'Puedes corregirlo mientras sea borrador.' : 'El PDF enviado no cambia.', kv([['Nombre', esc(d.cliente.nombre)], ['Cédula', esc(d.cliente.cedula || '—')], ['Teléfono', esc(d.cliente.telefono || '—')], ['Correo', esc(d.cliente.correo)]]) + (conv.length ? `<p class="f-note">${int(conv.length)} convenio${conv.length === 1 ? '' : 's'} más con este correo · <a href="${href('/con-convenio')}" data-ficha-correo="${esc(d.cliente.correo)}">Ver ficha</a></p>` : ''), e === 'BORRADOR' && can('editar') ? `<button class="btn btn-sm" type="button" data-act="cliente">${icon('edit')}Corregir datos</button>` : '')}
        ${panel('Enlace de firma', '', enlace)}
      </div>
      <div class="mt">${panel('Convenio', e === 'BORRADOR' ? 'Así quedará el convenio con los datos de este borrador. Se conserva la versión al preparar su enlace.' : 'El documento de esta versión.', (pdfBtns.length ? `<div class="acts">${pdfBtns.join('')}${pdfSrc ? `<button class="btn btn-sm" type="button" data-pdf-toggle>${state.detalle.verPdf ? 'Ocultar' : 'Verlo aquí'}</button>` : ''}</div>` : '<p class="muted" style="margin:0">Todavía no hay un PDF para este convenio.</p>') + (state.detalle.verPdf && pdfSrc ? `<iframe class="pdf-frame" title="Convenio en PDF" src="/api/contratos/${d.uid}/pdf/${pdfSrc}?workspace=${encodeURIComponent(ws.current())}"></iframe>` : ''))}</div>
      <div class="mt">${panel('Cuotas', `${int(q.length)} pago${q.length === 1 ? '' : 's'} · ${t.cuotas_por_mes === 1 ? 'una al mes' : t.cuotas_por_mes + ' al mes'}`, table([{k: 'numero', l: 'Cuota', n: 1, f: int}, {k: 'vencimiento', l: 'Vence', f: v => esc(fday(v))}, {k: 'mes', l: 'Mes del curso', n: 1, f: int}, {k: 'monto', l: 'Monto', n: 1, f: v => esc(money(v))}], q, {empty: 'Sin cuotas.'}))}</div>`;
  },
};
function editarCalculo(d) {
  const t = terminosDe(d), nueva = d.estado !== 'BORRADOR';
  const s = openModal(modalHead('Editar cálculo', nueva ? 'Este convenio ya se envió: al guardar se crea una versión nueva y el enlace anterior deja de servir.' : 'Los cambios se guardan en este borrador.') + `<form class="form" data-form="editar" novalidate>
    <div class="grid g2"><label class="fld">Cuota mensual ($)<input name="cuota" inputmode="decimal" value="${esc(t.cuota)}" autofocus></label><label class="fld">Meses<input name="meses" type="number" inputmode="numeric" min="1" max="20" value="${t.meses}"></label></div>
    <div class="grid g2"><label class="fld">Año de inicio<select name="anio">${[...new Set([...anios(), t.anio])].sort().map(y => `<option ${y === t.anio ? 'selected' : ''}>${y}</option>`).join('')}</select></label><label class="fld">Mes de inicio<select name="mes">${MESES.map((m, i) => `<option value="${i + 1}" ${i + 1 === t.mes ? 'selected' : ''}>${cap(m)}</option>`).join('')}</select></label></div>
    <div class="seg-fld">Cuotas por mes${seg('cpm-e', [['1', '1 cuota'], ['2', '2 cuotas'], ['4', '4 cuotas']], String(t.cuotas_por_mes), 'Cuotas por mes')}</div>
    <div class="grid g2"><label class="fld">Valor de la inscripción ($)<input name="inscripcion" inputmode="decimal" value="${esc(t.inscripcion)}"></label><label class="fld">Descuento 1.ª cuota (%)<input name="descuento" inputmode="decimal" value="${esc(t.descuento)}"></label></div>
    <p class="fld-err" role="alert"></p>
    <div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${nueva ? 'Crear versión nueva' : 'Guardar cálculo'}</button></div></form>`);
  const form = s.querySelector('form');
  form.onsubmit = async ev => {
    ev.preventDefault();
    const fd = new FormData(form), m = x => String(x || '').trim().replace(/\./g, '').replace(',', '.');
    const cuota = m(fd.get('cuota')), ins = m(fd.get('inscripcion')) || '0';
    const err = form.querySelector('.fld-err');
    if (!/^\d+(\.\d{1,2})?$/.test(cuota) || !(num(cuota) > 0)) { err.textContent = 'La cuota mensual debe ser un monto mayor que cero.'; return; }
    if (!/^\d+(\.\d{1,2})?$/.test(ins)) { err.textContent = 'La inscripción debe ser un monto, o 0.'; return; }
    const otros = (d.calculo.cargos || []).filter(c => !/^inscripci/i.test(norm(c.concepto))).map(c => ({concepto: c.concepto, monto: String(c.monto)}));
    const cpm = form.querySelector('[data-seg="cpm-e"] .is-on')?.dataset.v || '1';
    const b = form.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      const r = await api(`contratos/${d.uid}/editar`, {method: 'POST', body: {cuota, meses: Number(fd.get('meses')), anio: Number(fd.get('anio')), mes: Number(fd.get('mes')), cuotas_por_mes: Number(cpm), cargos: [{concepto: 'Inscripción', monto: ins}, ...otros], descuento: m(fd.get('descuento')) || '0'}});
      closeModal(); DATA.contratos = null; await loadDetalle(d.uid, true); loadContratos(true).catch(() => {});
      toast(nueva ? `Versión ${r.version} creada como borrador.` : 'Cálculo guardado.'); repaint();
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
function corregirCliente(d) {
  const c = d.cliente;
  const s = openModal(modalHead('Corregir datos', 'Solo mientras el convenio es borrador. Si cambias el correo, se guarda también en AGORA.') + `<form class="form" data-form="cliente" novalidate>
    <label class="fld">Nombre completo<input name="nombre" value="${esc(c.nombre)}" autofocus></label>
    <div class="grid g2"><label class="fld">Cédula<input name="cedula" value="${esc(c.cedula)}"></label><label class="fld">Teléfono<input name="telefono" value="${esc(c.telefono)}" inputmode="tel"></label></div>
    <label class="fld">Correo<input name="correo" type="email" value="${esc(c.correo)}"></label>
    <p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Guardar datos</button></div></form>`);
  const form = s.querySelector('form');
  form.onsubmit = async ev => {
    ev.preventDefault(); const fd = Object.fromEntries(new FormData(form)); const err = form.querySelector('.fld-err');
    if (!fd.nombre.trim() || !fd.cedula.trim()) { err.textContent = 'El nombre y la cédula son obligatorios.'; return; }
    const b = form.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try { await api(`contratos/${d.uid}/cliente`, {method: 'POST', body: fd}); closeModal(); DATA.contratos = null; await loadDetalle(d.uid, true); toast('Datos del cliente corregidos.'); repaint(); }
    catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
function enlaceModal(titulo, enlace, vence, extra = '') {
  const s = openModal(modalHead(esc(titulo), extra) + `<div class="secret link"><small>Enlace para que el cliente firme${vence ? ` · vence el ${esc(fstamp(vence))}` : ''}.</small><code>${esc(enlace)}</code><div class="row"><button class="btn btn-sm btn-cta" type="button" data-copy="${esc(enlace)}">${icon('split')}Copiar enlace</button></div></div><div class="form-ft"><button class="btn" type="button" data-close>Listo</button></div>`);
  return s;
}
async function accion(act, d) {
  const uid = d.uid, refresh = async () => { DATA.contratos = null; await loadDetalle(uid, true); loadContratos(true).catch(() => {}); repaint(); };
  if (act === 'editar') return editarCalculo(d);
  if (act === 'cliente') return corregirCliente(d);
  if (act === 'enviar') return confirmModal('Preparar enlace para el cliente', `Se congela la versión ${int(d.version)}, se genera el documento y <b>${esc(d.cliente.nombre)}</b> tiene este correo de contacto: <b>${esc(d.cliente.correo)}</b>. Revisa el cálculo y el convenio antes de enviar.`, 'Preparar convenio', async () => {
    const r = await api(`contratos/${uid}/enviar`, {method: 'POST'}); await refresh();
    setTimeout(() => enlaceModal('Convenio enviado', r.enlace, r.vence, 'El cliente ya lo recibió por correo. Si prefieres, compártelo también por WhatsApp.'), 200);
  });
  if (act === 'enlace') return confirmModal('Generar enlace nuevo', 'El enlace anterior dejará de servir y el cliente tendrá que usar el nuevo.', 'Generar enlace', async () => {
    const r = await api(`contratos/${uid}/enlace`, {method: 'POST'}); await refresh();
    setTimeout(() => enlaceModal('Enlace nuevo', r.enlace, r.vence), 200);
  });
  if (act === 'revocar') return confirmModal('Revocar enlace', 'El cliente no podrá firmar con el enlace actual. Para volver a enviarlo, edita el cálculo y crea una versión nueva.', 'Revocar enlace', async () => { await api(`contratos/${uid}/revocar`, {method: 'POST'}); await refresh(); toast('Enlace revocado.'); }, true);
  if (act === 'eliminar') return confirmModal('Eliminar borrador', 'Se borrará el borrador y su historial. No se puede deshacer.', 'Eliminar', async () => { await api(`contratos/${uid}`, {method: 'DELETE'}); delete DATA.detalle[uid]; DATA.contratos = null; toast('Borrador eliminado.'); go('/contratos'); }, true);
}

/* ---------- Personas ---------- */
V.personas = {
  need: () => DATA.usuarios ? Promise.resolve() : loadUsuarios(),
  render() {
    const acts = `<button class="btn btn-cta" type="button" data-act="persona">${icon('plus')}Agregar persona</button>`;
    const lede = 'Quién entra al panel y qué puede hacer. El acceso se da por invitación.';
    if (this.error) return head('Equipo', 'Personas', lede) + failed(this.error);
    if (!DATA.usuarios) return loading('Equipo');
    const st = state.personas, all = DATA.usuarios, q = norm(st.q), act = all.filter(u => u.activo);
    const rows = all.filter(u => !q || norm(u.nombre + ' ' + u.correo).includes(q));
    const cols = [
      {k: 'nombre', l: 'Persona', f: (v, r) => `<span class="who-cell"><b>${esc(v)}${r.id === ME.id ? ' <span class="sc sc-gray">Tú</span>' : ''}</b><small>${esc(r.correo)}</small></span>`},
      {k: 'rol', l: 'Rol', f: v => esc(ROLES[v]?.l || v)},
      {k: 'activo', l: 'Estado', f: v => v ? '<span class="lt lt-ok"><i aria-hidden="true"></i>Activa</span>' : '<span class="lt lt-bad"><i aria-hidden="true"></i>Desactivada</span>'},
      {k: 'id', l: '', cls: 'act', f: v => `<button class="btn btn-sm" type="button" data-user="${v}" aria-haspopup="true">${icon('more')}<span>Opciones</span></button>`},
    ];
    return head('Equipo', 'Personas', lede, acts)
      + `<div class="grid g4">${metric('Activas', int(act.length), `${int(all.length - act.length)} desactivada${all.length - act.length === 1 ? '' : 's'}`)}${['ADMIN', 'REMITENTE', 'VISOR'].map(r => metric(ROLES[r].l, int(act.filter(u => u.rol === r).length), ROLES[r].d)).join('')}</div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Nombre o correo')}</div>${table(cols, rows)}</section>`;
  },
};
const rolPicker = (val, name = 'rol') => `<div class="seg-fld">Rol${seg(name, Object.keys(ROLES).map(r => [r, ROLES[r].l]), val, 'Rol')}</div><ul class="role-help">${Object.keys(ROLES).map(r => `<li>${icon(r === 'ADMIN' ? 'shield' : r === 'REMITENTE' ? 'send' : 'search')}<span><b class="strong">${ROLES[r].l}:</b> ${ROLES[r].d}</span></li>`).join('')}</ul>`;
const claveAzar = () => { const a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', v = new Uint32Array(12); crypto.getRandomValues(v); return [...v].map((x, i) => (i && i % 4 === 0 ? '-' : '') + a[x % a.length]).join(''); };
function personaModal() {
  const s = openModal(modalHead('Agregar persona', 'Entra con su correo y la clave temporal que le compartas.') + `<form class="form" novalidate>
    <label class="fld">Nombre completo<input name="nombre" autofocus></label><label class="fld">Correo<input name="correo" type="email"></label>
    <label class="fld">Clave temporal (mínimo 12 caracteres)<span class="pw-in"><input name="clave" value="${claveAzar()}" autocomplete="off"><button class="pw-eye" type="button" data-regen>Otra</button></span></label>
    ${rolPicker('REMITENTE')}<p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Agregar persona</button></div></form>`);
  const form = s.querySelector('form');
  s.querySelector('[data-regen]').onclick = () => { form.clave.value = claveAzar(); };
  form.onsubmit = async ev => {
    ev.preventDefault(); const fd = Object.fromEntries(new FormData(form)), err = form.querySelector('.fld-err');
    fd.rol = form.querySelector('[data-seg="rol"] .is-on')?.dataset.v || 'REMITENTE';
    if (!fd.nombre.trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(fd.correo.trim())) { err.textContent = 'Escribe el nombre y un correo válido.'; return; }
    if (fd.clave.length < 12) { err.textContent = 'La clave temporal debe tener al menos 12 caracteres.'; return; }
    const b = form.querySelector('[type=submit]'); busy(b, true, 'Agregando…');
    try {
      await api('usuarios', {method: 'POST', body: fd}); await loadUsuarios(true); repaint();
      openModal(modalHead('Persona agregada', `Comparte la clave con ${esc(fd.nombre)} por un canal seguro. Puede cambiarla desde su perfil.`) + `<div class="secret"><code>${esc(fd.clave)}</code><button class="btn btn-sm btn-cta" type="button" data-copy="${esc(fd.clave)}">Copiar</button></div><p class="src">Correo para entrar: ${esc(fd.correo)}</p><div class="form-ft"><button class="btn" type="button" data-close>Listo</button></div>`);
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
function userMenu(id, anchor) {
  const u = DATA.usuarios.find(x => x.id === id); if (!u) return;
  const yo = u.id === ME.id;
  const el = openPop(anchor, `<button type="button" data-u="nombre">${icon('edit')}Cambiar nombre</button><button type="button" data-u="rol">${icon('shield')}Cambiar rol</button><button type="button" data-u="permisos">${icon('check')}Permisos individuales</button><button type="button" data-u="clave">${icon('lock')}Restablecer clave</button>${yo ? '' : u.activo ? `<button type="button" data-u="off">${icon('pause')}Desactivar</button>` : `<button type="button" data-u="on">${icon('check')}Reactivar</button>`}`, 'ctx', {align: 'right'});
  if (!el) return;
  el.setAttribute('role', 'menu');
  el.onclick = e => {
    const a = e.target.closest('[data-u]')?.dataset.u; if (!a) return; closePop();
    if (a === 'rol') {
      const s = openModal(modalHead('Cambiar rol', esc(u.nombre)) + `<div class="form">${rolPicker(u.rol, 'rol-c')}<p class="fld-err" role="alert"></p></div><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="button" data-ok>Guardar rol</button></div>`);
      s.querySelector('[data-ok]').onclick = async ev => { const b = ev.currentTarget; busy(b, true); try { await api('usuarios/rol', {method: 'POST', body: {id: u.id, rol: s.querySelector('[data-seg="rol-c"] .is-on').dataset.v}}); closeModal(); await loadUsuarios(true); repaint(); toast('Rol actualizado.'); } catch (x) { busy(b, false); s.querySelector('.fld-err').textContent = x.message; } };
    }
    if (a === 'clave') {
      const nueva = claveAzar();
      const s = openModal(modalHead('Restablecer clave', `${esc(u.nombre)} entrará con esta clave temporal.`) + `<label class="fld">Nueva clave (mínimo 12 caracteres)<input name="clave" value="${nueva}" autocomplete="off"></label><p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="button" data-ok>Cambiar clave</button></div>`);
      s.querySelector('[data-ok]').onclick = async ev => { const v = s.querySelector('input').value, b = ev.currentTarget; if (v.length < 12) { s.querySelector('.fld-err').textContent = 'Mínimo 10 caracteres.'; return; } busy(b, true); try { await api('usuarios/clave', {method: 'POST', body: {id: u.id, clave: v}}); closeModal(); copy(v, 'Clave cambiada y copiada.'); } catch (x) { busy(b, false); s.querySelector('.fld-err').textContent = x.message; } };
    }
    if (a === 'off') confirmModal('Desactivar persona', `${esc(u.nombre)} ya no podrá entrar al panel. Puedes reactivarla cuando quieras.`, 'Desactivar', async () => { await api('usuarios/desactivar', {method: 'POST', body: {id: u.id}}); await loadUsuarios(true); repaint(); toast('Persona desactivada.'); }, true);
    if (a === 'on') confirmModal('Reactivar persona', `${esc(u.nombre)} podrá entrar otra vez con su clave.`, 'Reactivar', async () => { await api('usuarios/activar', {method: 'POST', body: {id: u.id}}); await loadUsuarios(true); repaint(); toast('Persona reactivada.'); });
  };
}

/* ---------- Registro ---------- */
V.registro = {
  need: () => DATA.eventos ? Promise.resolve() : loadEventos(),
  render() {
    const lede = 'Lo último que pasó con los convenios: quién lo creó, cuándo se envió, cuándo se firmó.';
    const acts = `<button class="btn" type="button" data-reload-registro>${icon('refresh')}Actualizar</button>`;
    if (this.error) return head('Actividad', 'Registro', lede) + failed(this.error);
    if (!DATA.eventos) return loading('Actividad');
    const st = state.registro, all = DATA.eventos, q = norm(st.q);
    const tipos = [...new Set(all.map(e => e.tipo))];
    const det = e => { const x = e.detalle || {}; return [x.numero ? `versión ${x.numero}` : '', x.version ? `versión ${x.version}` : '', x.origen ? `desde ${x.origen === 'ghl' ? 'AGORA' : x.origen}` : '', x.motivo ? String(x.motivo).replace(/_/g, ' ') : '', x.vence ? `vence ${fdate(x.vence)}` : '', x.anterior ? `antes ${(ESTADO[x.anterior]?.l || x.anterior).toLowerCase()}` : ''].filter(Boolean).join(' · '); };
    const rows = all.filter(e => (!st.tipo || e.tipo === st.tipo) && (!q || norm((e.cliente || '') + ' ' + (EVENTOS[e.tipo] || e.tipo) + ' ' + det(e)).includes(q)));
    const page = rows.slice(st.p * 50, st.p * 50 + 50);
    const hoy = new Date().toDateString(), dHoy = all.filter(e => utc(e.creado_en)?.toDateString() === hoy).length;
    const cols = [
      {k: 'creado_en', l: 'Cuándo', f: v => `<span title="${esc(fstamp(v))}">${esc(when(v))}</span><small>${esc(fstamp(v))}</small>`},
      {k: 'tipo', l: 'Acción', f: v => `<b class="strong">${esc(EVENTOS[v] || v)}</b>`},
      {k: 'cliente', l: 'Cliente', f: (v, r) => r.uid ? `<a href="${href('/contratos/' + r.uid)}">${esc(v || 'Sin nombre')}</a>` : '<span class="muted">Sin contrato</span>'},
      {k: 'detalle', l: 'Detalle', f: (v, r) => esc(det(r)) || '<span class="muted">—</span>'},
    ];
    return head('Actividad', 'Registro', lede, acts)
      + `<div class="grid g4">${metric('Movimientos', int(all.length), 'Los más recientes')}${metric('Hoy', int(dHoy), 'Desde la medianoche')}${metric('Firmas', int(all.filter(e => e.tipo === 'FIRMADO').length), 'En este registro')}${metric('Envíos', int(all.filter(e => e.tipo === 'ENVIADO').length), 'En este registro')}</div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Cliente, acción o dato')}<label class="fld">Acción<select data-f="tipo"><option value="">Todas</option>${tipos.map(t => `<option value="${t}" ${st.tipo === t ? 'selected' : ''}>${esc(EVENTOS[t] || t)}</option>`).join('')}</select></label>${st.q || st.tipo ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
        ${table(cols, page, {empty: '<b>No hay movimientos que coincidan</b>Prueba con otra búsqueda.'})}${pager(st, rows.length, 50)}</section>`;
  },
};

/* ---------- Acceso ---------- */
/* Escena de Athena en la entrada: se mueve con el puntero (paralaje), saluda al pasar
   por encima y, al tocarla, cambia el consejo de la burbuja. Todo es decorativo. */
const ATHENA_CONSEJOS = [
  '<b>Hola, soy Athena.</b> Tus datos están protegidos en el servidor: clave cifrada, sesión segura y registro de auditoría.',
  '<b>¿Equipo compartido?</b> Deja apagado "Recuérdame" y tu sesión se cierra al salir del navegador.',
  '<b>Dentro de la app</b> abre los módulos desde Inicio o desde la barra lateral; cada uno agrupa sus herramientas.',
  '<b>¿Olvidaste tu clave?</b> Quien administra tu empresa la restablece desde Usuarios y roles.',
];
function athenaEscena(stage) {
  if (!stage) return;
  const reducir = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let i = 0, frame = 0;
  if (!reducir) stage.addEventListener('pointermove', e => {
    const r = stage.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
    cancelAnimationFrame(frame); frame = requestAnimationFrame(() => { stage.style.setProperty('--px', x.toFixed(3)); stage.style.setProperty('--py', y.toFixed(3)); });
  });
  stage.addEventListener('pointerleave', () => { stage.style.setProperty('--px', 0); stage.style.setProperty('--py', 0); });
  const wrap = stage.querySelector('.lg-athena-wrap'), msg = stage.querySelector('[data-athena-msg]'), bubble = stage.querySelector('.lg-bubble');
  // El brazo que saluda es la misma imagen recortada: se reutiliza la fuente para no descargarla dos veces.
  const base = stage.querySelector('.lg-athena'), brazo = stage.querySelector('.lg-athena-arm');
  if (base && brazo) brazo.src = base.getAttribute('src');
  // Al pasar el puntero, el saludo se acelera con suavidad (sin reiniciar la animación, para que no salte).
  wrap?.addEventListener('pointerenter', () => wrap.classList.add('is-hi'));
  wrap?.addEventListener('pointerleave', () => wrap.classList.remove('is-hi'));
  const siguiente = () => { i = (i + 1) % ATHENA_CONSEJOS.length; msg.innerHTML = ATHENA_CONSEJOS[i]; bubble.classList.remove('is-new'); void bubble.offsetWidth; bubble.classList.add('is-new'); };
  wrap?.addEventListener('click', siguiente); bubble?.addEventListener('click', siguiente);
}
V.entrar = {
  render() {
    document.documentElement.classList.add('is-auth');
    let s = $('#lg');
    if (!s) { s = document.createElement('div'); s.id = 'lg'; s.className = 'lg'; document.body.append(s); }
    s.innerHTML = `<div class="lg-wrap lg-v2" role="main">
      <div class="lg-stage" aria-hidden="true">
        <div class="home-brand lg-brand"><span class="home-brand-logo">${LOGO}</span><span class="home-brand-sep"></span><span class="home-brand-tx"><b>Digital App</b><small>Cada conversación, una oportunidad</small></span></div>
        <div class="lg-scene">
          <div class="lg-glow"></div>
          <svg class="lg-orbits" viewBox="0 0 600 600"><circle cx="300" cy="300" r="210"/><circle cx="300" cy="300" r="280"/><circle class="lg-orb-dot" cx="300" cy="20" r="7"/><circle class="lg-orb-dot is-gold" cx="300" cy="510" r="5"/></svg>
          <span class="lg-spark" style="--x:8%;--y:62%;--d:0s"></span><span class="lg-spark is-gold" style="--x:90%;--y:40%;--d:1.2s"></span><span class="lg-spark" style="--x:84%;--y:78%;--d:2.1s"></span><span class="lg-spark is-gold" style="--x:14%;--y:86%;--d:.6s"></span><span class="lg-spark" style="--x:94%;--y:14%;--d:1.7s"></span>
          <div class="lg-bubble"><p class="lg-bubble-h"><span class="lg-hi" aria-hidden="true">👋</span>Athena<i></i></p><p data-athena-msg><b>¡Hola! Soy Athena.</b> Tus datos están protegidos en el servidor: clave cifrada, sesión segura y registro de auditoría.</p><small class="lg-bubble-tip">Tócame para otro consejo</small></div>
          <div class="lg-athena-wrap"><div class="lg-athena-fig"><img class="lg-athena" src="/assets/athena/athena-saludo.webp" alt="" width="1100" height="1160" decoding="async" onerror="this.closest('.lg-stage').classList.add('no-img')"><img class="lg-athena-arm" alt="" width="1100" height="1160" decoding="async"></div><span class="lg-floor"></span></div>
        </div>
        <div class="lg-chips"><span class="lg-chip" style="--d:.2s">${icon('invoice')}Convenios</span><span class="lg-chip" style="--d:.5s">${icon('trend')}Ventas</span><span class="lg-chip" style="--d:.8s">${icon('shield')}Auditoría</span></div>
      </div>
      <section class="lg-card lg-panel" aria-labelledby="lg-title">
        <div class="lg-logo">${LOGO}<small>Digital App</small></div>
        <p class="lg-eyebrow">Operación comercial y convenios</p>
        <h1 id="lg-title">Inicia sesión</h1>
        <p class="lg-sub">Entra a la operación comercial y los convenios de tu empresa.</p>
        <form novalidate>
          <div class="lg-fields">
            <label class="lg-f"><span class="sr-only">Usuario o correo</span><input id="lg-mail" type="text" placeholder="Usuario o correo" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus></label>
            <label class="lg-f"><span class="sr-only">Clave</span><input id="lg-pw" type="password" placeholder="Clave" autocomplete="current-password" required><button class="lg-eye" type="button" aria-pressed="false" aria-label="Mostrar clave">${icon('eye')}</button></label>
          </div>
          <label class="lg-remember"><input type="checkbox" id="lg-rec" role="switch"><span class="lg-sw" aria-hidden="true"></span><span>Recuérdame en este equipo (7 días)</span></label>
          <p class="fld-err" role="alert"></p>
          <button class="btn btn-cta lg-go" type="submit"><span>Continuar</span><span class="lg-go-ic" aria-hidden="true">${icon('arrowR')}</span></button>
        </form>
        <button class="lg-link lg-forgot" type="button">¿Olvidaste tu clave?</button>
        <p class="lg-help" id="lg-forgot-help" hidden>Pide a quien administra tu empresa que restablezca tu clave desde Usuarios y roles. Por seguridad no se envían claves por correo.</p>
        ${window.__DEMO__ ? '<p class="lg-help lg-demo"><b>Cuenta de prueba:</b> usuario <b>admin</b> · clave <b>123456789</b><br><small>Solo funciona en prueba.html; no es una cuenta real.</small></p>' : ''}
        <p class="lg-foot">Tu acceso lo crea el administrador de tu empresa con una invitación.</p>
      </section>
    </div>`;
    const entrado = async () => { await Promise.all([bootContratos(), bootAgora()]); if (!esSuperAdmin()) await bootEspacio(); await loadUiTexts(); const back = store.get('volver', ''); store.set('volver', ''); go(esSuperAdmin() ? '/agencia' : (back && back !== '/entrar' ? back : homePath()), {replace: true}); };
    const card = s.querySelector('.lg-card');
    athenaEscena(s.querySelector('.lg-stage'));
    const eye = s.querySelector('.lg-eye'), pw = s.querySelector('#lg-pw');
    eye.onclick = () => { const v = pw.type === 'password'; pw.type = v ? 'text' : 'password'; eye.innerHTML = icon(v ? 'eyeOff' : 'eye'); eye.setAttribute('aria-label', v ? 'Ocultar clave' : 'Mostrar clave'); eye.setAttribute('aria-pressed', String(v)); };
    s.querySelector('.lg-forgot').onclick = () => { const h = s.querySelector('#lg-forgot-help'); h.hidden = !h.hidden; };
    s.querySelector('form').onsubmit = async e => {
      e.preventDefault();
      const correo = s.querySelector('#lg-mail').value.trim(), clave = pw.value, err = card.querySelector('.fld-err'), b = s.querySelector('.lg-go');
      const shake = () => { card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake'); };
      if (!correo || !clave) { err.textContent = 'Escribe tu usuario o correo y tu clave.'; shake(); (correo ? pw : s.querySelector('#lg-mail')).focus(); return; }
      busy(b, true, 'Entrando…'); err.textContent = '';
      try { const d = await api('login', {method: 'POST', body: {correo, clave, recordar: s.querySelector('#lg-rec').checked}}); if (!d.usuario) throw new Error('El servidor no devolvió la identidad de usuario.'); ME = d.usuario; DATA.reset(); await entrado(); if (!AGORA_ME) throw new Error('El servidor debe habilitar la sesión unificada para Operación Comercial.'); }
      catch (x) { busy(b, false); err.textContent = x.message; shake(); pw.select(); }
    };
    s.querySelector(document.activeElement?.id === 'lg-pw' ? '#lg-pw' : '#lg-mail')?.focus();
    modoPruebas().then(m => {
      if (!m || window.__DEMO__ || card.querySelector('.lg-pruebas')) return;
      card.querySelector('.lg-foot')?.insertAdjacentHTML('beforebegin', `<p class="lg-help lg-demo lg-pruebas"><b>Ambiente de pruebas:</b> usuario <b>${esc(m.usuario || 'admin')}</b> · clave <b>${esc(m.clave || '')}</b><br><small>Datos sintéticos. Los demás usuarios (por ejemplo admin.asocim) usan la misma clave.</small></p>`);
    });
    return '';
  },
};
/* Modo pruebas del servidor (SQLite + datos sintéticos): se consulta una vez en /api/status. */
let MODO_PRUEBAS_P = null;
function modoPruebas() {
  if (window.__DEMO__) return Promise.resolve(null);
  MODO_PRUEBAS_P ??= fetch((window.AGORA_API || '/api/') + 'status', {cache: 'no-store'}).then(r => r.ok ? r.json() : null).then(d => {
    if (d?.modo !== 'pruebas') return null;
    document.documentElement.classList.add('modo-pruebas');
    if (!document.getElementById('pruebas-flag')) document.body.insertAdjacentHTML('beforeend', '<div id="pruebas-flag" role="status">Ambiente de pruebas · datos sintéticos</div>');
    return d;
  }).catch(() => null);
  return MODO_PRUEBAS_P;
}
modoPruebas();
async function salir() {
  closeAll(); cerrarAthena();
  if (ME) { try { await api('logout', {method: 'POST'}); } catch { /* igual se sale */ } }
  // Un solo cierre de sesión. El backend invalida todos los módulos en una operación.
  window.dispatchEvent(new Event('agora:logout'));
  resetAccountData(); ME = null; AGORA_ME = null; AGORA_CONN = []; ws?.reset(); AGENCY_CONTEXT = true; AGENCY_ACCOUNTS = null;
  DATOS.resumen = DATOS.tareas = DATOS.auditoria = DATOS.usuarios = null;
  go('/entrar', {replace: true});
}

/* ---------- Cuenta, tema y clave ---------- */
const ACENTOS = [['vino', 'Vino de marca', '#AD103C'], ['azul', 'Azul profundo', '#282E49'], ['oro', 'Oro', '#C9971F'], ['apple', 'Azul sistema', '#0071E3'], ['grafito', 'Grafito', '#6E6E73']];
function applyTheme() {
  const t = store.get('tema', 'auto'), dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
  const root = document.documentElement;
  root.dataset.theme = dark ? 'dark' : 'light';
  const acento = store.get('acento', 'vino');
  root.dataset.accent = ACENTOS.some(a => a[0] === acento) ? acento : 'vino';
  root.classList.toggle('nav-side', store.get('menu', 'side') === 'side' && !!ME);
  const b = $('#b-theme'); if (b) b.innerHTML = icon(dark ? 'sun' : 'moon');
  document.querySelector('meta[name=theme-color]')?.setAttribute('content', dark ? '#000000' : '#F5F5F7');
  try { aplicarPaleta(); } catch { /* aún no hay sesión */ }
}
matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', applyTheme);
function toggleTheme() { store.set('tema', document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'); applyTheme(); toast(document.documentElement.dataset.theme === 'dark' ? 'Tema oscuro' : 'Tema claro'); }
function openAccount() {
  const bloqueada = ESPACIO?.bloqueado === true, puedeBloquear = esSuperAdmin();
  const fila = (nombre, color, icono, detalle, accion) => `<div class="acct-row"><span class="lg-ic" style="--c:${color}">${icon(icono)}</span><span class="tx"><b>${nombre}</b><small>${detalle}</small></span>${accion}</div>`;
  const opcion = (attrs, icono, rotulo, atajo = '') => `<button class="acct-op" type="button" ${attrs}>${icon(icono)}<span>${rotulo}</span>${atajo ? `<kbd>${atajo}</kbd>` : ''}</button>`;
  const el = openPop($('#b-acct'), `<div class="acct-sys">
      ${fila('Sesión unificada', 'var(--mod-vino)', 'user', esc(ME?.nombre || AGORA_ME?.user?.email || 'Mi cuenta'), '<span class="lt lt-ok"><i aria-hidden="true"></i>Activa</span>')}
    </div>
    ${aparienciaHtml()}
    <div class="acct-ops">
      ${opcion(`data-ir="${href('/perfil')}"`, 'user', 'Perfil de cuenta')}
      ${opcion('data-buscar', 'search', 'Buscar o ir a…', IS_MAC ? '⌘K' : 'Ctrl K')}
      ${opcion(`data-ir="${href(homePath())}"`, 'home', 'Ir al inicio')}
      ${esSuperAdmin() && !AGENCY_CONTEXT ? opcion('data-agency-back', 'briefcase', 'Volver a Agencia') : ''}
      ${opcion(`data-ir="${href(MODS.conexiones.path)}"`, 'gear', 'Ajustes')}
      ${ME ? opcion('data-clave', 'lock', 'Cambiar mi clave') : ''}
      ${opcion('data-keys', 'keyboard', 'Atajos del teclado', '?')}
    </div>
    ${ESPACIO ? `<div class="acct-est">
      <h4>Estado del espacio</h4>
      <div class="est-h"><span class="lt ${bloqueada ? 'lt-bad' : 'lt-ok'}"><i aria-hidden="true"></i>${bloqueada ? 'Bloqueada' : 'Activa'}</span></div>
      <p>${bloqueada
        ? 'El equipo ve la pantalla de mantenimiento. Quien administra sigue entrando con normalidad.'
        : 'El equipo entra con normalidad. Al bloquearla, solo quien administra sigue trabajando.'}</p>
      ${puedeBloquear ? `<button class="btn btn-sm ${bloqueada ? 'btn-cta' : 'btn-danger'}" type="button" data-bloqueo>${icon(bloqueada ? 'unlock' : 'lock')}${bloqueada ? 'Reabrir el espacio' : 'Bloquear el espacio'}</button>` : '<p class="src">Solo quien administra lo cambia.</p>'}
    </div>` : ''}
    <div class="acct-ft"><button class="acct-op acct-out" type="button" data-logout>${icon('logout')}<span>Cerrar sesión</span></button></div>`, 'acct', {align: 'right'});
  if (!el) return;
  placeThumbs(el);
  aparienciaMounted(el);
  el.querySelector('[data-agency-back]')?.addEventListener('click', volverAgencia);
  el.querySelector('[data-buscar]').onclick = () => { closePop(); openCmd(); };
  el.querySelectorAll('[data-ir]').forEach(b => b.addEventListener('click', e => { closePop(); go(e.currentTarget.dataset.ir.replace(/^#/, '')); }));
  const bl = el.querySelector('[data-bloqueo]');
  if (bl) bl.onclick = async e => {
    const b = e.currentTarget; busy(b, true, 'Guardando…');
    try {
      await agoraApi('admin/maintenance', {method: 'POST', body: {on: !bloqueada}});
      ESPACIO = {...(ESPACIO || {}), bloqueado: !bloqueada};
      closePop(); topbar(); toast(bloqueada ? 'El espacio quedó abierto.' : 'El espacio quedó bloqueado.');
    } catch (x) { busy(b, false); toast(x.message); }
  };
}
function aparienciaHtml() {
  const tema = store.get('tema', 'auto'), menu = store.get('menu', 'side'), acento = store.get('acento', 'vino');
  const vista = (v, rotulo, ico) => `<button type="button" class="ap-theme ${tema === v ? 'is-on' : ''}" data-tema="${v}" role="radio" aria-checked="${tema === v}"><span class="ap-prev ap-prev-${v}" aria-hidden="true"><i></i><b></b></span><span class="ap-l">${icon(ico)}${rotulo}</span></button>`;
  return `<section class="ap" aria-label="Apariencia"><h4>Apariencia</h4>
    <div class="ap-themes" role="radiogroup" aria-label="Tema">${vista('auto', 'Automático', 'auto')}${vista('light', 'Claro', 'sun')}${vista('dark', 'Oscuro', 'moon')}</div>
    <h4>Menú de módulos</h4>${seg('menu', [['side', 'Barra lateral'], ['top', 'Arriba']], menu, 'Menú de módulos', 'ap-seg')}
    <h4>Color de acento</h4><div class="ap-accents" role="radiogroup" aria-label="Color de acento">${ACENTOS.map(([v, l, c]) => `<button type="button" class="ap-dot ${acento === v ? 'is-on' : ''}" data-acento="${v}" style="--dot:${c}" role="radio" aria-checked="${acento === v}" aria-label="${l}" title="${l}"></button>`).join('')}</div></section>`;
}
function aparienciaMounted(el) {
  el.querySelectorAll('[data-tema]').forEach(b => b.onclick = () => {
    store.set('tema', b.dataset.tema); applyTheme();
    el.querySelectorAll('[data-tema]').forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-checked', String(on)); });
  });
  el.querySelectorAll('[data-acento]').forEach(b => b.onclick = () => {
    store.set('acento', b.dataset.acento); applyTheme();
    el.querySelectorAll('[data-acento]').forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-checked', String(on)); });
  });
}
function claveModal() {
  const s = openModal(modalHead('Cambiar mi clave', 'Al cambiarla se cierran tus otras sesiones.') + `<form class="form" novalidate><label class="fld">Clave actual<input name="a" type="password" autocomplete="current-password" autofocus></label><label class="fld">Clave nueva (mínimo 12 caracteres)<input name="b" type="password" autocomplete="new-password"></label><label class="fld">Repite la clave nueva<input name="c" type="password" autocomplete="new-password"></label><p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Cambiar clave</button></div></form>`);
  const f = s.querySelector('form');
  f.onsubmit = async e => {
    e.preventDefault(); const err = f.querySelector('.fld-err');
    if (f.b.value.length < 12) { err.textContent = 'La clave nueva debe tener al menos 12 caracteres.'; return; }
    if (f.b.value !== f.c.value) { err.textContent = 'Las claves nuevas no coinciden.'; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Cambiando…');
    try { await api('clave', {method: 'POST', body: {clave_actual: f.a.value, clave_nueva: f.b.value}}); closeModal(); ME=null; AGORA_ME=null; go('/entrar'); toast('Clave cambiada. Inicia sesión de nuevo.'); } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
function keysModal() {
  const mods = ALL().slice(0, 10);
  openModal(modalHead('Atajos de teclado', 'Funcionan cuando no estás escribiendo en un campo.') + `<div class="keys"><div><h3>Ir a</h3><dl>${mods.map((id, i) => `<dt><kbd>${(i + 1) % 10}</kbd></dt><dd>${esc(MODS[id].name)}</dd>`).join('')}</dl></div>
    <div><h3>Acciones</h3><dl><dt><kbd>${IS_MAC ? '⌘K' : 'Ctrl K'}</kbd></dt><dd>Buscar o ir a…</dd><dt><kbd>M</kbd></dt><dd>Abrir el menú de módulos</dd><dt><kbd>J</kbd></dt><dd>Preguntarle a Athena</dd>${can('editar') ? '<dt><kbd>N</kbd></dt><dd>Nuevo convenio</dd>' : ''}<dt><kbd>/</kbd></dt><dd>Buscar en la vista</dd><dt><kbd>D</kbd></dt><dd>Tema claro u oscuro</dd><dt><kbd>Esc</kbd></dt><dd>Cerrar</dd><dt><kbd>?</kbd></dt><dd>Esta ayuda</dd></dl></div></div>`);
}

/* ---------- Paleta ⌘K: módulos, acciones y convenios ---------- */
/* ---------- Paleta ⌘K: módulos, acciones y convenios ---------- */
function openCmd(initial = '') {
  const s = openModal(`<div class="cmdk-in">${icon('search')}<input type="search" placeholder="Busca un módulo, un cliente o una acción…" value="${esc(initial)}" aria-label="Buscar o ir a" autocomplete="off"></div><div class="cmdk-list" role="listbox"></div><div class="cmdk-ft"><span><kbd>↑</kbd><kbd>↓</kbd> moverte</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Esc</kbd> cerrar</span></div>`, {cls: 'cmdk'});
  const inp = s.querySelector('input'), list = s.querySelector('.cmdk-list'); let sel = 0, items = [];
  const paintList = () => {
    const q = norm(inp.value.trim());
    const mods = ALL().map(id => ({g: GRUPOS[MODS[id].g].name, i: MODS[id].icon, c: MODS[id].c, t: MODS[id].name, d: moduleCaption(id), go: MODS[id].path}));
    const acc = [
      ...(can('editar') ? [{g: 'Acciones', i: 'plus', t: 'Nuevo convenio', d: 'Elige el contacto y arma el plan de pago', go: '/contratos/nuevo'},
        {g: 'Acciones', i: 'search', t: 'Buscar en la cartera', d: 'Contactos de la subcuenta, para crear un convenio', fn: () => { state.contactos.tab = 'agora'; go('/con-convenio'); }}] : []),
      ...(MODS.tareas.ok() ? [{g: 'Acciones', i: 'check', t: 'Nueva tarea', d: 'Para ti o para alguien del equipo', fn: () => { go('/tareas'); setTimeout(() => tareaModal(), 260); }}] : []),
      {g: 'Acciones', i: 'athena', t: 'Preguntarle a Athena', d: 'Resume tus cifras del sistema', fn: () => abrirAthena()},
      ...(agoraReady() ? [{g: 'Acciones', i: 'coins', t: 'Ventas ganadas', d: 'Completar las que están sin monto', fn: () => { if (ws) ws.pendingOppTab = 'won'; go('/oportunidades'); }}] : []),

      ...(ME ? [{g: 'Acciones', i: 'lock', t: 'Cambiar mi clave', d: 'La del panel de Contratos', fn: claveModal}] : []),
      {g: 'Acciones', i: document.documentElement.dataset.theme === 'dark' ? 'sun' : 'moon', t: 'Cambiar tema', fn: toggleTheme},
      {g: 'Acciones', i: 'keyboard', t: 'Atajos de teclado', fn: keysModal},
    ];
    const conv = (DATA.contratos || []).map(c => ({g: 'Convenios', i: 'invoice', c: 'var(--mod-oro)', t: c.cliente, d: `${c.correo} · ${(ESTADO[c.estado]?.l || c.estado).toLowerCase()} · v${c.version}`, go: '/contratos/' + c.uid}));
    const tar = (DATOS.tareas || []).filter(t => t.estado !== 'hecha').map(t => ({g: 'Tareas', i: 'check', c: 'var(--ok-solid)', t: t.titulo, d: `${t.responsable || 'Sin asignar'}${t.vence ? ' · vence ' + fday(t.vence) : ''}`, go: '/tareas'}));
    items = [...mods, ...acc, ...conv, ...tar].filter(x => !q || norm(x.t + ' ' + (x.d || '') + ' ' + x.g).includes(q)).slice(0, 40);
    sel = Math.min(sel, Math.max(0, items.length - 1));
    let g = '';
    list.innerHTML = items.length ? items.map((x, i) => `${x.g !== g ? `<p class="mdm-gt">${(g = x.g)}</p>` : ''}<div class="mdm-i ${i === sel ? 'is-kb' : ''}" role="option" aria-selected="${i === sel}" data-i="${i}"><span class="mdm-ic" style="--c:${x.c || 'var(--brand)'}">${icon(x.i)}</span><span class="tx"><b>${esc(x.t)}</b>${x.d ? `<small>${esc(x.d)}</small>` : ''}</span></div>`).join('')
      : `<p class="empty"><b>Nada coincide</b>${can('editar') && q.length > 1 ? `<button class="btn btn-sm" type="button" data-agora>Buscar «${esc(inp.value.trim())}» en la cartera</button>` : 'Prueba con otro nombre.'}</p>`;
    list.querySelector('.is-kb')?.scrollIntoView({block: 'nearest'});
  };
  const run = i => { const x = items[i]; if (!x) return; closeModal(true); if (x.fn) x.fn(); else go(x.go); };
  inp.oninput = () => { sel = 0; paintList(); };
  inp.onkeydown = e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); paintList(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); paintList(); }
    if (e.key === 'Enter') { e.preventDefault(); run(sel); }
  };
  list.onclick = e => {
    const it = e.target.closest('[data-i]'); if (it) return run(+it.dataset.i);
    if (e.target.closest('[data-agora]')) { const q = inp.value.trim(); closeModal(true); state.contactos.tab = 'agora'; go('/con-convenio'); buscarAgora(q); }
  };
  paintList(); inp.focus(); inp.select();
  if (!DATA.contratos && can('ver')) loadContratos().then(paintList).catch(() => {});
}

/* =====================================================================================
   Eventos (delegados: sirven para todas las vistas)
   ===================================================================================== */
let qT = 0;
document.addEventListener('input', e => {
  const t = e.target;
  if (t.matches('[data-form="nuevo"] input, [data-form="nuevo"] select')) {
    const s = state.nuevo; s.f[t.name] = t.value; if (['cuota', 'meses', 'anio', 'mes'].includes(t.name)) calcular(); return;
  }
  if (t.matches('#app [data-f]')) {
    const st = state[R.view]; if (!st) return;
    st[t.dataset.f] = t.value; st.p = 0;
    clearTimeout(qT); qT = setTimeout(() => { const pos = t.selectionStart; repaint(); const n = $(`#app [data-f="${t.dataset.f}"]`); if (n && n.tagName === 'INPUT') { n.focus(); try { n.setSelectionRange(pos, pos); } catch { /* tipo search */ } } }, t.tagName === 'SELECT' ? 0 : 160);
  }
});
document.addEventListener('submit', e => {
  const f = e.target.closest('[data-form]'); if (!f) return;
  e.preventDefault();
  if (f.dataset.form === 'buscar-agora') buscarAgora(f.consulta.value);
  if (f.dataset.form === 'buscar-nuevo') buscarNuevo(f.consulta.value);
  if (f.dataset.form === 'nuevo') crearConvenio(f.querySelector('[type=submit]'));
});
document.addEventListener('click', e => {
  const t = e.target, c = s => t.closest(s);
  if (c('[data-close]')) { closeModal(); return; }
  if (t.classList?.contains('scrim')) { closeModal(); return; }
  let el;
  if ((el = c('[data-copy]'))) { copy(el.dataset.copy, 'Enlace copiado'); return; }
  if ((el = c('.seg-b'))) {
    const sg = el.closest('.seg'), key = sg.dataset.seg, v = el.dataset.v;
    sg.querySelectorAll('.seg-b').forEach(b => { const on = b === el; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', String(on)); });
    placeThumbs(sg.parentElement, true);
    if (key === 'rol-sys' || key === 'cpm-e') return;
    if (key === 'estado') { Object.assign(state.contratos, {estado: v, p: 0}); repaint(); return; }
    if (key === 'ctab') { state.contactos.tab = v; state.contactos.p = 0; setTimeout(repaint, 220); return; }
    if (key === 'cpm') { state.nuevo.f.cuotasPorMes = v; calcular(); paintSum(); return; }
    if (key === 'tema') { store.set('tema', v); applyTheme(); return; }
    if (key === 'menu') { store.set('menu', v); applyTheme(); topbar(); return; }
    return;
  }
  if ((el = c('[data-filter]'))) {
    const st = state[R.view], k = FKEY[R.view];
    if (st && k) { st[k] = st[k] === el.dataset.filter ? '' : el.dataset.filter; st.p = 0; repaint(); }
    return;
  }
  if (c('#b-search')) { openCmd(); return; }
  if (c('#b-theme')) { toggleTheme(); return; }
  if (c('#b-acct')) { openAccount(); return; }
  if (c('[data-logout]')) { salir(); return; }
  if (c('[data-clave]')) { closePop(); claveModal(); return; }
  if (c('[data-keys]')) { closePop(); keysModal(); return; }
  if (c('[data-retry]')) { render(); return; }
  if (c('[data-reload-registro]')) { DATA.eventos = null; render(); return; }
  if (c('[data-clear]')) { const st = state[R.view]; if (st) { st.q = ''; st.estado = ''; st.tipo = ''; st.p = 0; } repaint(); return; }
  if ((el = c('[data-page]'))) { const st = state[R.view]; st.p += +el.dataset.page; repaint(); $('#app .tools')?.scrollIntoView({block: 'nearest'}); return; }
  if ((el = c('[data-sort]'))) { const st = state[R.view], k = el.dataset.sort; st.sd = st.sk === k ? -st.sd : 1; st.sk = k; repaint(); return; }
  if ((el = c('[data-tab]'))) { state.contactos.tab = el.dataset.tab; repaint(); return; }
  if ((el = c('[data-ficha]'))) { fichaCliente(el.dataset.ficha); return; }
  if ((el = c('[data-ficha-correo]'))) { e.preventDefault(); const k = norm(el.dataset.fichaCorreo); fichaCliente(k); return; }
  if ((el = c('[data-ver-correo]'))) { Object.assign(state.contratos, {q: el.dataset.verCorreo, estado: '', p: 0}); go('/contratos'); return; }
  if ((el = c('[data-nuevo-correo]'))) { nuevoCon({buscar: el.dataset.nuevoCorreo || el.dataset.nuevoNombre}); return; }
  if ((el = c('[data-crear]'))) { const r = state.contactos.resultados?.find(x => x.id === el.dataset.crear); if (r) nuevoCon({contacto: r}); return; }
  if ((el = c('[data-pick]'))) { elegirContacto(state.nuevo.resultados[+el.dataset.pick]); repaint(); calcular(); $('[data-form="nuevo"] [name="cedula"]')?.focus({preventScroll: true}); return; }
  if (c('[data-cambiar]')) { state.nuevo.elegido = null; state.nuevo.calc = null; repaint(); return; }
  if (c('[data-pdf-toggle]')) { state.detalle.verPdf = !state.detalle.verPdf; repaint(); return; }
  if ((el = c('[data-act]'))) {
    const a = el.dataset.act;
    if (a === 'persona') return personaModal();
    const d = DATA.detalle[R.uid]; if (d) accion(a, d); return;
  }
  if ((el = c('[data-user]'))) { userMenu(+el.dataset.user, el); return; }
  if ((el = c('tr[data-href]')) && !c('a, button')) { go(el.dataset.href); return; }
  /* 2.1.19: el clic que abrió un menú (su botón) no lo cierra en el mismo momento. Antes, los menús
     «Columnas» y «Excel» de las tablas se abrían y este escucha los cerraba en el mismo clic. */
  if (pop && !c('.pop') && !(popAnchor && popAnchor.contains(e.target))) closePop();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && e.target.matches?.('tr[data-href]')) { go(e.target.dataset.href); return; }
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { if (!ME && !AGORA_ME) return; e.preventDefault(); if (modal?.querySelector('.cmdk')) closeModal(); else openCmd(); return; }
  if (e.key === 'Escape') { if (pop) closePop(); else if (modal) closeModal(); return; }
  const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
  if (typing || e.metaKey || e.ctrlKey || e.altKey || (!ME && !AGORA_ME) || modal) return;
  const k = e.key.toLowerCase(), mods = ALL();
  const n = k === '0' ? 9 : (/^[1-9]$/.test(k) ? +k - 1 : -1);
  if (n >= 0 && mods[n]) { closePop(); go(MODS[mods[n]].path); }
  else if (k === 'm') { closePop(); moduleMenu(); }
  else if (k === 'j') { closePop(); abrirAthena(); }
  else if (k === 'n' && can('editar')) go('/contratos/nuevo');
  else if (k === 'd') toggleTheme();
  else if (k === '/') { const f = $('#app input[type=search]'); if (f) { e.preventDefault(); f.focus(); } }
  else if (e.key === '?') keysModal();
});

/* =====================================================================================
   Armazón de Agora Digital App
   -------------------------------------------------------------------------------------
   La barra de arriba, el menú de módulos, el periodo, los accesos de Inicio, Ajustes y
   Auditoría, el estado del espacio, el historial y Athena. Todo con las piezas que ya
   trae el sistema de diseño: .tb con el vidrio esmerilado, .crumb, .mdm, .ath.

   Aquí vive también el puente con los módulos de Ágora: se montan tal como vienen, en un
   <section id="view" data-view="…">, que es el nodo que su propio código espera.
   ===================================================================================== */

var AG = (window.Agora || {});
var AG_READY = typeof AG.renderDashboard === 'function';
var ws = AG.workspace || null;

/* Se declaran con var a propósito: la primera ruta se calcula antes de que este bloque
   corra, y al preguntar «¿hay sesión?» tiene que responder «todavía no», no fallar. */
var AGORA_ME = null;          // sesión de Ágora
var AGORA_CONN = [];          // subcuentas a las que llega esta persona
var AGORA_REASON = null;
var unread = 0;               // conversaciones sin leer
var wonPendientes = 0;        // ventas ganadas sin monto
var ESPACIO = null;           // identidad del espacio: nombre y logo del cliente

function agoraReady() { return AG_READY && !!AGORA_ME; }
function agoraAdmin() { return agoraReady() && AGORA_ME.user?.role === 'administrator'; }
/** Quien manda en el sistema: el propietario de Ágora o la administración del panel. */
function esAdmin() { return agoraAdmin() || (!!ME && ME.rol === 'ADMIN'); }
function esMaster() { return ME?.rol === 'MASTER'; }
function esSuperAdmin() { return !!ME && ['MASTER','SUPERADMIN'].includes(ME.rol); }
function puedeAuditar() { return esAdmin(); }

/* ---------- API de Ágora (su propia base; ver assets/config.js) ---------- */
async function agoraApi(path, {method = 'GET', body} = {}) {
  const epoch = NAV_EPOCH;
  if (SWITCHING && !['workspace/active', 'workspace/branding'].includes(path)) throw staleContext();
  let r;
  try {
    r = await navigationFetch(AGORA_API + path, {
      method, credentials: 'same-origin', cache: 'no-store',
      headers: {...(body !== undefined ? {'Content-Type': 'application/json'} : {}), ...(method !== 'GET' && AGORA_ME?.csrf ? {'X-CSRF-Token': AGORA_ME.csrf} : {})},
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new Error(navigator.onLine ? 'No se pudo conectar con el servidor. Inténtalo de nuevo.' : 'Sin conexión. Revisa tu internet.');
  }
  let d = {}; try { d = await r.json(); } catch { if (r.ok && r.status !== 204) throw new Error('El servidor no devolvió una respuesta válida. Revisa la conexión de la API.'); }
  checkContext(epoch);
  if (!r.ok) throw Object.assign(new Error(d.error || `No se pudo completar (código ${r.status}).`), {status: r.status, code: d.code});
  return d;
}

/* =====================================================================================
   El periodo: un mes, el mismo para el Resumen y para ARP Comercial
   ===================================================================================== */
var PERIODO = (() => {
  const guardado = store.get('periodo', '');
  if (/^\d{4}-\d{2}$/.test(guardado)) return guardado;
  const h = new Date();
  return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`;
})();
const mesActualYM = () => { const h = new Date(); return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`; };
const mesNombre = ym => { const [y, m] = String(ym).split('-'); return `${cap(MESES[Number(m) - 1] || '')} ${y}`; };
const mesCorto = ym => { const [, m] = String(ym).split('-'); return cap((MESES[Number(m) - 1] || '').slice(0, 3)); };
const mesSuma = (ym, n) => { const [y, m] = ym.split('-').map(Number); const d = new Date(y, m - 1 + n, 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; };
/** En curso el mes corriente; por delante, el que todavía no empieza. */
const mesEstado = ym => ym === mesActualYM() ? 'curso' : (ym > mesActualYM() ? 'proximo' : 'cerrado');
function ponerPeriodo(ym) {
  if (ym === PERIODO) return;
  PERIODO = ym; store.set('periodo', ym);
  if (typeof TB !== 'undefined' && TB.estado === 'listo') TB.irAlMes(ym);
  render();
}

/* =====================================================================================
   Montaje de un módulo de Ágora dentro del lienzo
   ===================================================================================== */
var agHost = null;
function disposeAgora() {
  if (!agHost) return;
  try { agHost.agoraLiveDispose?.(); } catch { /* soltar nunca debe trabar la vista */ }
  agHost = null;
}
const agoraGate = what => `<section class="panel"><p class="empty"><b>No se puede abrir ${esc(what)}</b>Tu sesión no tiene acceso a este módulo o el servidor de Operación Comercial no reconoce la sesión unificada. Contacta al administrador.</p></section>`;
const AG_RENDER = {dashboard: 'renderDashboard', inbox: 'renderLiveInbox', contacts: 'renderContacts', board: 'renderBoard', settings: 'renderIntegrations', help: 'renderHelp'};

function agoraView(id, {eyebrow, title, lede, what, own = false, acts = () => ''}) {
  V['ag_' + id] = {
    render() {
      if (!AG_READY) return head(eyebrow, title, lede) + `<section class="panel"><p class="empty"><b>Falta el paquete de módulos</b>No se cargó <code>assets/agora-modulos.js</code>. Revisa que esté junto a esta página.</p></section>`;
      if (!AGORA_ME) return head(eyebrow, title, lede) + agoraGate(what);
      return (own ? '' : head(eyebrow, title, lede, acts())) + `<section id="view" class="ag" data-view="${esc(id)}"></section>`;
    },
    mounted(app) {
      if (!agoraReady()) return;
      const host = app.querySelector('#view');
      if (!host) return;
      agHost = host;
      host.dataset.periodo = PERIODO;
      try { AG[AG_RENDER[id]](host); }
      catch (x) { host.innerHTML = `<p class="empty"><b>No se pudo abrir el módulo</b>${esc(x.message || 'Error inesperado')}</p>`; }
    },
  };
}

agoraView('dashboard', {eyebrow: 'Operación', title: 'ARP Comercial', lede: 'Las cifras del día, la inversión en anuncios y la rentabilidad del mes.', what: 'el tablero comercial', own: true});
agoraView('inbox', {eyebrow: 'Atención', title: 'Bandeja de conversaciones', lede: 'Todos los canales en una sola lista, con recordatorios de seguimiento.', what: 'las conversaciones'});
agoraView('contacts', {eyebrow: 'Cartera', title: 'Contactos', lede: 'Las fichas de la subcuenta, con su historial y sus etiquetas.', what: 'los contactos'});
agoraView('board', {eyebrow: 'Embudo', title: 'Oportunidades', lede: 'De la conexión al cierre: cada oportunidad abierta, a la vista.', what: 'las oportunidades'});
agoraView('settings', {eyebrow: 'Administración', title: 'Ajustes', lede: 'Las cuentas conectadas, el equipo importado y los avisos.', what: 'los ajustes'});
agoraView('help', {
  eyebrow: 'Manual', title: 'Ayuda', lede: 'Lo básico para trabajar en el sistema.', what: 'el manual',
  acts: () => `<button type="button" class="btn btn-tint" onclick="window.print()">${icon('download')}Imprimir / guardar PDF</button>`,
});

/* Vista de configuración: los paneles editoriales viven aquí, no en Inicio. */
const renderAjustesOriginal = V.ag_settings.render;
V.ag_settings.render = function () {
  const base = renderAjustesOriginal.call(this);
  if (!AGORA_ME) return base;
  return base + `<div class="settings-branding mt">
    ${identidadEspacio()}
    ${esSuperAdmin() ? `<div class="mt">${editorIdentidadEspacio()}</div>` : ''}
  </div>`;
};
const needAjustesOriginal = V.ag_settings.need;
V.ag_settings.need = () => Promise.all([
  needAjustesOriginal ? needAjustesOriginal() : Promise.resolve(),
  cargarResumen().catch(() => null),
]);

/* =====================================================================================
   La barra de arriba
   ===================================================================================== */
const LOGO_ESPACIO = () => ESPACIO?.logo
  ? `<img class="esp-logo" src="${esc(ESPACIO.logo)}" alt="${esc(ESPACIO.nombre || 'Logo del cliente')}">`
  : '';

/** La migaja: marca, separador y el módulo en uso con su contador. */
function crumb() {
  const m = R.grupo && GRUPOS[R.grupo] ? {...GRUPOS[R.grupo], n: null} : MODS[R.mod];
  if (!m && R.view === 'perfil') return `<span class="crumb" style="cursor:default">${icon('user')} Mi perfil</span>`;
  if (!m && R.view === 'agencyAccount') return `<a class="crumb" href="${href('/agencia')}">${icon('briefcase')} Subcuentas</a>`;
  if (!m && R.view === 'agencyControl') return `<span class="crumb" style="cursor:default">${icon('pie')} ${esc(AG_GOB_NOMBRE)}</span>`;
  if (!m) return R.view === 'agencia' ? `<span class="crumb" style="cursor:default">${icon('briefcase')} Agencia</span>` : '';
  const n = moduleAllowed(R.mod) && m.n ? m.n() : null;
  return `<button class="crumb" id="b-crumb" type="button" aria-haspopup="menu" aria-expanded="false" title="Cambiar de módulo">
    <span class="mdm-ic" style="--c:${m.c || 'var(--brand)'}">${icon(m.icon)}</span>
    <span>${esc(m.name)}</span>
    ${n ? `<b class="nav-b">${int(n)}</b>` : ''}
    ${icon('chev', 'chev')}</button>`;
}

/** Los meses: el anterior, el de ahora y el que viene, con su estado. */
function periodBar() {
  if (!MODS[R.mod]?.periodo) return '';
  const meses = [mesSuma(PERIODO, -1), PERIODO, mesSuma(PERIODO, 1)];
  const punto = ym => mesEstado(ym) === 'curso' ? '<i class="pt pt-curso" aria-hidden="true"></i>' : mesEstado(ym) === 'proximo' ? '<i class="pt pt-prox" aria-hidden="true"></i>' : '';
  return `<nav class="per" aria-label="Periodo">
    <button class="per-b" type="button" data-mes="${mesSuma(PERIODO, -12)}" title="Un año atrás" aria-label="Un año atrás">${icon('chevL')}</button>
    ${meses.map(ym => `<button class="per-b ${ym === PERIODO ? 'is-on' : ''}" type="button" data-mes="${ym}" aria-current="${ym === PERIODO}">${mesCorto(ym)}${punto(ym)}</button>`).join('')}
    <button class="per-b" type="button" data-mes="${mesActualYM()}" title="Volver al mes en curso" aria-label="Mes en curso">${icon('home')}</button>
  </nav>`;
}

/** Los accesos fijos de la derecha, como en el ecosistema. */
/* El retrato de Athena. Va embebido para que el archivo de pruebas de una sola pieza
   lo lleve consigo; en el paquete también está suelto en assets/athena.webp. */
const ATHENA_IMG = 'data:image/webp;base64,UklGRvAXAABXRUJQVlA4WAoAAAAQAAAAfwAAfwAAQUxQSIoDAAABDjra/qeRpLKrosZ4KGOeOcPM8kZT0eItZi/AnPIBmDljZmaKGKLmdul5Si6y9VvIfn//9ZcjgoEjqW24Q64hvdnwAVaKrNujx9Kx/rk7n3nzp1+23BDRpH3mznP9Y0v0SN2MYULMSKb9l936wo8rbio0bRLb3yPZDbEiTySXLrntvTXnMdhOBUXKIkKmVN6ItbYxv5peHpZ0lZy/9L4f3RDFFkV0KogwUodo7dKmw7wbUI7k0Ru+9Ls2bNMZIYyiqs0NRzukBZPLLnh41deIVoKRDVxlmwWRyZU8+6JT3Sq0UjCqqpz12133vUa2L/nLXLRijGzqSy1pWb1LnXrcaUGtCaRq29S4kBgXr111g6Q1IonGa62uypw8/yOnW1ozomoyMq+l29ztpQNXOySpRnashm5H35piJTWuQ45W3UuEV/8xfqn6F/rDKFKtjRucJg0ISZVVmlAu3T/RZhAm728rY5e7XhrXLaReLxm7FemeT7yGSGHHqtFPvYbJT6tg7jVpoEie+cy6o6QBU2Zkposvj7aG2miLucz0tJUH3aYLGpvOpDsDjTeW82FnG9oMeqXX8NlMzZyH/h4U4Qvl7zilgUzn3nFDBaDGuekMGO8o1yFUJdpUet5gWzBEkpxHm6Ju4bNyHUaVxVwmp9e5pDBIahOTnEdXiwGOCOPRSQaNj/kUKbEJifH0oFAoUE6PZ9573aGJvp7ysWnfKxr745Isfw1RmjGGjWeKocKRzWiS85FRK1hGmhHDykNrAwcISbFM4w0+RUw4Upz7RgtEobZz5fRir5iMPjHe67YwJeq9NGZc/MkNMEX0J2M2LHtFZaT1eJsvoxYbJtp7vidqx9Y63LuOXGgjeZlPcRPr8FbfgtsgzF7QbVxJ+oIsl546uA0/LR9bwZbmWF8cNKR/jW9BbrA70cWe0QJZqO2b6NKWbEGb+hldfo4OHFH/9wO/C74h/Oce/nMf/72H/97H/+7B/+7D/+7F/+7H/++B/+/D/+/9B/jvxx/3wB/3wR/3wh/3gx/3xB/3hR/3xh/3x5/3gJ/3wZ/3gp/3w5/3hJ/3xZ/3hp/3x/d7gPf7wPd7gff7gfd7wvf7gvd7g/f7g/d7xPf7hPd7hff7hfd7hvf7hvd7h/f7h497gI/7gI97gY/7QY97go/7go97Q4/7g497RI/7RI97RY/7RY97Ro/7/seMe4eP+ydWUDggQBQAALBJAJ0BKoAAgAA+PRqJQ6IhoRULnxQgA8SygGagDDZjvZRN10xfdonXHmTPlelPb7eZbzhvTNvLG9AWiTwT+8/jx+0/rT+N/Qv4T8r+VHux9Ef5L98vyn9s/bv2g/4fh7+T/vn/D9Qj8Z/l3+R/Lv0Ld8LqH+a9BH2w+p/6L8z/796e+sH4E/43uCfzj+ef6b81/Wk/uXkWeWewF/OP61/r/8D+7P+W+mD+f/6n+Y/db/te4z86/wX/Y/0H7xf5j7Bv5L/Rf9H/ff3d/wX///6/3h+0D9lfZW/W/5/3d7kzO3TJbA8Tv3WcPECVoApgc/Y9WLb9Bh+aXP8tEWuxr/3PrtUhAOkpWCr2dBprNHJi9lxKYzCrPK+GoyvGqTihBKcl5e8zikDVHicFXgFWaAp6CiX1suFw2RjBMJOKf93w+RIJi73BlGM0pPfUU/v+qbqyu8TR0yMZnvMDdtiUeSS9ljUeUfidOA32HRmms5OwYPSIoKeko+kgl1jJ5jNC7QjTePrYAle2NFmIjcivrTLkp6+Txcj1hFxOAJWPtSHDhVxa4acnr3AUZDnB1tvMETpl5HZuL/a+hSDc/n5YWV2v/Ut7BKi13Tsdyeu0f84m8ewimfN2GBp5VWflge5w2v4yFfs0h3ihtcsJTpbRt9LIJr+g75S6k6MJWR4Bw6m3ZvDrloB5Vqn3qySqPwcA3S5Pxsvxlzcux2OfyS0q9W5jpRZbmoXdrrVXy8wRMq5XuMcPbZFmldPvzuT+GAKOZbBI8/dGLAf9XpZ3IiCoHhyL/+PcIgAA/v9gHSWp52SQKhZ7kFvXWk////vtoWx/q6Df7Id9sGFSx0DHeSzifaoDaRs2Bwnh8JPVDyWmHuT0oP9NBSzhYhzD8kLaIpzyCmMJYSB4+fJIJeVz8Iav9d8c/x7qHqu3odTllCDrueOUNLz5kfqdvHu/oB8tQFTp4WL55VhLucJil7yDXuk9MY/KM3f+FzMMzViVbMjjio7LZlOqYHpy+s5XF27Dkop1l4owiFbKDax5LAXsRKdWxYokugcv9+Q/vNZzA1tlsEJR3Qjr58Qa+OmYYpqNj34vBtTNuvBUzyOyJ61g1FCYI3ENinSlIvYyBCAou1oQvbXYYQgpZsgd7kTOt38IdCXIXE3b6pqUO8NN/CZt2Rbk7o7pA17Z17b1f/ZsvWPJT5ov/A3HlZONyRNM2Szqujb9+kUEDTbQjyHUECSfCZaCSf2nQlGJwmZV0aorxOAm17rHyzzVjY7kgcMpl12hTLch353iAi2fJvg0nfYnedl4MIjI6sE0gPkz7PYsma7gAqkI+Gx4y4SH8mVxsS89pYfOqFSsuEYIIKoC5Bp7GjHiYl+L33oTTcFJjAMG+nlOFeBwfOaMFYXLbNrrPHw6prGmyff8gPP/ac+g//8b0ZfvKdU73ZHLcLSCwfzahGqgXBCf//4N8HNdwuANe5usvxllf5LFwsxwNJWGayDwo6TZ0EuEkczTCvJV11L4wheJNYjkHqyK7cOmAIqCiv8lBDSV2PhQzMwMW1f5UBfpRrKlCDPcJHY3WlqXaJLfWsNmag/pU3JT4xwg7azKLRtz+O0NGF4lXHHnBt8fJDb0raHc3Jiyb69nmR9dgOfIk1DRiAY/hJnOY7BULdJX7tui53BpMD7emeYHv2tJ88R2nmmYnkvamDTqe1Kvs25RYeGpFHt2GyVY/sZD1Y74MZh2bUGGb6d7/W+DCJuO5QmvtN8YUvOG1rQvirM/CbRtCSfkU6PFrfpprgj0yN+IvKY/6+9eBejpH9JE+9JrFq9P1z+/xPzD337TBX9TusS0b+HrCM9IMMOj+MllAR0IHDA9Ie3lUR4Fjmm/BtaVnxNU041yALYTGBeTgfukwQiZ3z7+20GNbiI9tbwb4lf1iwQOu01mWQpNQah4j1Lwbmw1+KzDV1xjnn5iUAO+6vyCIud2xSfS8Kn2zO6IN0QnIqPeSr6/k9vDdA9OAWaQBa8cKNbXjJyLBnuyVdvJ1/yW59gsugGPGAAideSDJ1NkHfvAdrDirMIp1h2l/V3Xh9gMP+jAkwhxcMaK2OfIe8egqkwlAt+fcYgj1Jau9cph7fHeM9fS+UJpKMNPH6pkiS+Dd/YORD00mlgpP5wNVr5CQRyg3p1WhCUJSZCnu/lOwnx0cuAtpJzAQ4+b9wG2yjA+NZZfSjrjpu/1ZboJcRU9ktXQfVCT0KbfY6LQmLyd08kw8odWIc6n9tkQ+9RCBfmJcC3xJMq3vgVfbQt6ehFEmtio7fmnztaIXySxbElzy8432BV2um+v9hvaHRv0OG6sLOKmygjhD9z/o7MdgnLCzAZRaPc57PfysUXO+j90YUDpg0/xCvzarpRTYFIXBO8rrf6bMM1sH7/saHn+o3hZi+qEx/WyTRHwFn3qIrFxPvW8M3xM4PxmWfLv77OLjd4L4vQ/YiYGP01kZA92iHf0Hwev1I815T3zz1lNhBEsJ5HLnCXq2LrY29P0k4zZEiWGzLum0m3cU5mwbtyZ7TyY8wyDdD8JN132p5Z4Z1EvDTWya7DJR0GKA11Trm/9INFCUr6ukzMK1D+6EVywWphv4FzJyQVwRj0dC5/UqfZBoKMNz5jaHx/KELDzestKv5cESSDpriPAgzYcMMriBkxc1fujG/7J6ucSdCw+hs2jIgPhQI0bcyTmLYcZ1jYTrBk6+d/RFsg8YV8Uh5xUDQRAVRZDZdC6zJLMFZIhBX3qEyoWuq16d4Ktgyb6g/iuxtRfZtGga3pNPijbCOi+qtvyOWeUMwGJeQideT+fDaj2s5udkmMHoaev8xTOoEe2UvG0LaVGSGASOMQQ1L+Gx2njVVHoz+up1MyZcnKD5CdsLAIZzSi/V7aPhQyCtr8lA4ZAgDTibPnWEJLJr2jDekjZxOMSxDTmV5KLXSkt7KaIMu7UhC3SgfufyaMVq8/cpZSiJHdLxJWyyC4eydTqsQWFiLFVlh0xuCwxk3zCQILA9uD+JyBvcxjsh5taZ1K51UxqoB3rXD1paSmDpWNMfZIld3r/rZD538WS+obe4QrCf+WtMEyLHs7cv47vtV2+5NyZnO1JzMiAaOPnc1PmZYHnwgM3W8DSHZiffsaG+eTtR8B0OHjX76bcPfU1D97WfI7LzlRfssbUv50mb/sangxM3jlru2hhmRgMsgCSV6j9Ui+CusS4QhWLgzqhRUkRmmdqJHFdelr832WiSCboJXFFLgR0L0jD24E49QwnLw8DdQMN6l/m75k9m9AU2pTEQNE+cPt0uEyo8coFUiVVTU6ETn09+5gwc/TDRvh29wNidcztWGstXA6lulLY86wzBbI8cYoJs2o/KrxsVkXJC6oPjBXz3wjmLgOA8sS8Vq3LDz0vkJkM3bttgpwxM/mJVAH/PZB3TJA0mAYENpuHPbYdffwd8fNSrUj1ObukhM5U6KWDc1ZKIDSLBFWTs5boSVjjH+G41Zjqx64AGoInIMlDTnHOGKZ6QLCM1KgP2OIwGJGKCoEr2nuSAXT/XMgxZVmMCb7ScEKB2bgSI0GkzcQq8D88WN6xHsaLxkkUt4KEGRJAj4+nep3v8mXWv749v9nRQwugdhb8CbZT+w2q6AFflXphTCF3eOPiwg+BY3aC1pI1S36zJ1vi/gj/3v98y3UjJAcnD9f4gjE3KoCx5WRH8EaeuHNcOfV5IaQLsreGh2XCu+GU7WWLJntAqJh4fKo72Ec8/gXlrlAtBkWVs1n90urjsXV8r5/pdx0WvXyARZ3ml4efpk3K4rT1t0yyEjsK21gHIi3+l9YPd8Q8r6rtDliA4Qgy8LEDDFyewN/FPoQoA8ZazUfofjNZJTnm2+A/SahNUgD5wN9tIiokWwHSy78gXecTTU4JRrvqQ4Rml4K7oOmWYIiu+QrRE8LZfarsoEkXNF6yx/0t9Hmzfz5ScJSMbBSUEzBr8rTjaEcZESvv6y0VQwze/CxLc59UjIWzwHR9AkF5rrf4ISvpx4cQIx+Qan2Wha2+piYjY0CbilFv5eRHiBjxDOcchWrXrppYaH2+yOcid21km+WY3ZynV/yZ+mqdcdblI+BNSMLKBHFDDmyN3zNtJGUxPUxBc945sEScua+T2BZFfWHv6D6sH6cCB/rk3cIqunwGa15OSRAfz1qCv7+9iCHKoP2glH2yU8MsEYdP350EWYrrYLE197PYS8Epvihfg6qWauAkI1dwsjQlK5i7A4i0z3UuPgjKMV4Drve8EOk19V2/5niASekCQ/4Ww2evd6jnHLNcHZcWEt8KMKlSmmG9NnNti7n7iE9GyiYQrFOAMb9PK9D32Q5xMthb+BT+WS8SHxHVbDyq2R4zfVmUsB3COHTEj10ea2a2jmyQ5rlFg/6PycGU+w+QkTZb0eK+tng3udwG5hpdShjhonTJCpyOkK6TV+SPNHd9NURaAAhcQS+Zs9dcI8apD3i4PEfQCyDZL9al5AHS4RixdQB0CLRabtyo7QeSMW18atl1mnr7MnyEnlcGyD27NdWFHnt4S3zVUUHrdYnd0ZrlQ86xpy8aZft5Y3zj6M0EnJ5Rpp29eEH+C9uF6Ct0xasuew9m+dE04/CqmFcaPVh5tWHHSwTK5Yog4TrI3DXV/BZH7nau+FpFK+dhtrZmKfpJzkYDkyAJsy0hplSgd8b7UdYn9uQ27ssrBc/T3fRdaQYWF3tozqoYUdEapQrw/BVoJbN0V7L82cUKVKqhuIMShlh7foL0vfvt69xSXRDV8JxsTE4T9vYjI2mVYjE7ffiji+h1Jc9elMuOHw2mzAeKLiQdLOna/klYH4Kb+QLCWeR66IuPGklyg3ae/2w8qywUj49HFKWjS4oZtlzxdPvNPa7h7s+b6MVSLpsn6IA6GR5ETYg8MwSOnAm/9XB90PYx44aqk9C3l5xUax+l8Wia4DYDImKEqDDP0Nt9OrlB8eFVrf8dJFSPo/PpQe0+0HIUoUO6BSBFWNoQlIRcGZNJRJxNA4KXZOTqkXd+yEbMGcASrg/5gPm2W+sm/ESkL+gXMck0lZ/tduX5RPXc6WX1lRh/HiicyXQT/SpSi/OW2ij0cAdI/7yBURjhGtJT1MX/34tw7XpX/hPTXiK+ApTLi7juzObbtmKR6C5pzp0qU+AENuvxj5w5RzHZ1/pTABwVNnQb/EAeGdrof/MHkxGHzA/XmJ257/n93JZY2jhOpE2/2RXKU8uLFzRPBRLfFP/q9JKNOiqiSco0AkKtBvtb68EwB4E/fVbHIHAYsBXxGizw0lQA+m3YqxLQ0wx0y/wA6mfa9+oPZRZg9qFno9jQfRZzUzlADMTHafYjVXn//vJPgCYFNN1VaS7LaVKTjXqj9jUJFLqcWTz0+o1e0PLGqU706QnmWIVqOLKJiN8pCIlzR3Xh+1GOJWh7XHLTyln6nZmvL6t6se+Z+r8TP4FwoHUdHeNfgctrHAnZioOU5rDJCqzdhYSTP7Dsxpeo0kkEsRyL6+2j+8yovKvlfPysCzgn9Bpue9E+f2ifiFt+VgyJmapdKODCWcxBPF2UK0KRawYAX//+Jr7WE3quIMMCQau6eShUqC/WYMzNp2GCH9uSFqAZcHY81fj1OfuWjXYyFkuDZuZUufwD5FFn1eN0BmYCqsrUcCaBJHwW+ljmET1lM4nGd4gU6GAwDKERPmbeaX3AiI4xj/EREG6+5mAigvp+Apuf7Srf9p8TM1J3deD6cdynNWfkYaV7KmyckN9TsWAFA68ltHR1sfe1+8dgRsEDKEnEHZ7JFqjGtTF+C0UocIZJuLDmcaUOw7uRtATy9TFBFaTcB7iSXlf4Yy7lvlaRm1OyDwuUJoAh77plL9GenqDOPw+FgSkrKFJYv9KyYPeyqUYGpH5Gd+Nn3mpRUG0AbED99VoazLs8K5kVlAYYjm1Yjta3myQWXVAQR5vhqhvfLG6jCiVpgPAETn4A40W7/SdC+bvpTT/X0RLs6RsLRJMRGnUEksHcTORB9fqGRAXOL0a4stCG2MuGT5eTqWjwkuvTB/vxqt4PpOq3l9rLScUWTVJWSEnWo6DqRy++jRpkX7BnDyAIz8MVyegD5HLj6OzErM4M4AZxNy9m5QcetI4hP68Jr9t1OcBfQrg6KPU6Sy9lOfFlACJRsyYdKcKzw9OwPn606Ny0MgmZwW/9UEp+Fckf0YshjX60lcovXoAXHxjb1quuqVboFzvlyyYBq/+u+grO6MFan1DMGXXitWEo2DZ1djfbwE99eKBODtLDXvGIfgw4iLo/5oqFhNHcoQpK6h/su0R2sTSrHP21gEL57PraL8s7lOHY/FSnAQs/n27AQnAmA6MpRpPJkDs7PU579m2b4HKDB3mYrik7EUeLhtsVHUHPdO3xZy3EkCFnuKkZgSowL1vowOpZWAs1Kml8spvCu2wt8dmiZUKBpb09Ej2J2Hbmc07gM4uYFCnw5GTHD1084KduI8ix1ndPiePoeMeEKy4XH6uir2y0+MRZlmH+b/D4NC+EPF/ETOz54rjnoI/eEF/mQvyZelQY8GfslayySkFvkLmT3RuCfY2VkWbX2ODjmi8wmUc/TpOwmJ9X+bIAhCLJj358/F85voLRGCjGUNbchRGHw1hc6wbCkQHYeWhnNMkejqJfc4xgM1Ca4Z6QkM85YY8KTDTZLAKKSlB4CEHHJ7h+Dy2XPXAs73TCmyKGj6VuqsEEj1OvlkQ6Hr0WZ1LovUAiqUwsgjuqnzRXm+9vX0U2eCyQakk8gU72UzDkiicYvhmXtceK7c80Q906Tp8/ebrlMmuEj0rNGYuH/wJi9bQz/sVaLs5trInQHzOdRPzY/ZpwWoM0j94sjRMiEEy4L6iC1mYDzqK64ROFgkcUb+h3m7/owFfeXfTcXFdEMNBsvDEZlXGlCeUoAAFv/9RvAAAAAA==';
const athenaAvatar = (cls = '') => `<img class="ath-av ${cls}" src="${ATHENA_IMG}" alt="" width="28" height="28">`;

function rightCluster() {
  const atajo = (id, ico, rotulo, titulo) => `<a class="tb-sc" href="${href(MODS[id].path)}" ${R.mod === id ? 'aria-current="page"' : ''} title="${esc(titulo)}">${icon(ico)}<span>${esc(rotulo)}</span></a>`;
  const bloqueada = ESPACIO?.bloqueado === true;
  return `
    <button class="tb-ic" id="b-hist" type="button" aria-haspopup="true" aria-expanded="false" title="Historial de lo que has visto">${icon('history')}</button>
    <button class="tb-ath" id="b-ath" type="button" aria-haspopup="dialog" aria-expanded="false" title="Athena, tu agente">${athenaAvatar()}<span>Athena</span></button>
    ${atajo('centro', 'home', 'Inicio', 'Centro de operaciones')}
    ${atajo('auditoria', 'shield', 'Auditoría', 'Trazabilidad de contactos y cambios')}
    <button class="tb-ic" id="b-theme" type="button" title="Cambiar tema (D)" aria-label="Cambiar tema">${icon(document.documentElement.dataset.theme === 'dark' ? 'sun' : 'moon')}</button>
    <button class="avatar avatar-ic ${bloqueada ? 'is-off' : ''}" id="b-acct" type="button" aria-haspopup="true" aria-expanded="false" title="Perfil de cuenta y opciones" aria-label="Perfil de cuenta y opciones">${personalAvatar()}</button>`;
}

const quienSoy = () => ME ? ME.nombre : (AGORA_ME?.user?.email || '');

function topbar() {
  $('#tb').innerHTML = `<a class="brand" href="${href(homePath())}" title="Ir al inicio" aria-label="Agora Digital App, ir al inicio">${LOGO}<small>Digital App</small></a>
    <span class="crumb-sep" aria-hidden="true">/</span>
    ${crumb()}
    ${''/* 2.1.11: la barra superior solo lleva la marca Ágora; el logo del cliente vive en su Inicio. */}
    <div class="tb-mid">${periodBar()}</div>
    <div class="tb-r">${rightCluster()}</div>`;
  $('#tabbar').style.setProperty('--tabs', 4);
  $('#tabbar').innerHTML = `<a href="${href(homePath())}">${icon('home')}Inicio</a><button type="button" data-open-modules>${icon('sidebar')}Módulos</button><button type="button" data-nav-search>${icon('search')}Buscar</button><button type="button" data-nav-account>${icon('user')}Cuenta</button>`;
  $('#navigation-context')?.remove();
  sideNav();
  const abiertas = [
    ME ? `Contratos · ${ROLES[ME.rol]?.l || ME.rol}` : '',
    AGORA_ME ? `Ágora · ${AGORA_ME.user?.role === 'administrator' ? 'administración' : 'equipo'}` : '',
  ].filter(Boolean);
  $('#foot').textContent = abiertas.join('   ·   ') || 'Sin sesión';
}

/* ---------- Barra lateral opcional (Cuenta → Apariencia → Menú de módulos) ---------- */
/* Barra lateral de la subcuenta: Inicio fijo arriba y los 6 módulos principales en
   acordeón. Solo un módulo abierto a la vez; al entrar se abre el que contiene la
   pantalla actual. El grupo abierto sobrevive al repintado (SIDE_ABIERTO). */
let SIDE_ABIERTO = null;
function sideNav() {
  const root = document.documentElement, side = $('#side');
  root.classList.toggle('nav-side', store.get('menu', 'side') === 'side' && !!ME);
  const visible = root.classList.contains('nav-side') && !!MODS[R.mod] && R.view !== 'agencia';
  root.classList.toggle('side-closed', !visible);
  if (!side) return;
  if (!visible) { side.innerHTML = ''; side.hidden = true; return; }
  side.hidden = false;
  const grupos = gruposMenu();
  const actual = R.grupo || (MODS[R.mod]?.fijo ? null : MODS[R.mod]?.g), lugar = R.grupo ? 'g:' + R.grupo : R.mod;
  if (SIDE_ABIERTO === null || (actual && side.dataset.mod !== lugar)) SIDE_ABIERTO = actual || (SIDE_ABIERTO ?? '');
  side.dataset.mod = lugar;
  const item = id => {
    const m = MODS[id], allowed = moduleAllowed(id), n = allowed ? m.n?.() : null;
    const on = id === modMenu() && !R.grupo;
    return `<a class="mdm-i ${on ? 'is-on' : ''}" href="${href(m.path)}" ${on ? 'aria-current="page"' : ''}><span class="mdm-ic" style="--c:${m.c}">${icon(m.icon)}</span><span class="tx"><b>${esc(m.name)}</b>${!allowed ? '<small>Acceso restringido</small>' : ''}</span>${n ? `<b class="nav-b">${int(n)}</b>` : ''}</a>`;
  };
  const home = MODS.centro && moduleAllowed('centro') ? `<nav class="side-home" aria-label="Inicio">${item('centro')}</nav>` : '';
  side.innerHTML = home + `<nav class="side-acc" aria-label="Módulos">${grupos.map(([g, ids]) => {
    const open = SIDE_ABIERTO === g, tiene = ids.includes(modMenu());
    const badge = ids.reduce((t, id) => t + (moduleAllowed(id) ? Number(MODS[id].n?.() || 0) : 0), 0);
    return `<section class="acc-g ${open ? 'is-open' : ''} ${tiene ? 'has-current' : ''}">
      <h2 class="acc-h"><a class="acc-b ${R.grupo === g ? 'is-on' : ''}" href="${href('/modulos/' + GRUPOS[g].slug)}" ${R.grupo === g ? 'aria-current="page"' : ''}>
        <span class="mdm-ic" style="--c:${GRUPOS[g].c}">${icon(GRUPOS[g].icon)}</span><span class="acc-t">${esc(GRUPOS[g].name)}</span>${badge ? `<b class="nav-b" aria-label="${int(badge)} pendientes">${int(badge)}</b>` : ''}</a><button type="button" class="acc-tg" id="acc-b-${g}" aria-expanded="${open}" aria-controls="acc-p-${g}" data-acc="${g}" aria-label="${open ? 'Plegar' : 'Desplegar'} ${esc(GRUPOS[g].name)} (${ids.length} submódulos)"><span class="acc-n" aria-hidden="true">${ids.length}</span><span class="acc-chev" aria-hidden="true">${icon('chev')}</span></button></h2>
      <div class="acc-p" id="acc-p-${g}" role="region" aria-labelledby="acc-b-${g}" ${open ? '' : 'inert'}><div class="acc-in">${ids.map(item).join('')}</div></div>
    </section>`;
  }).join('')}</nav>`;
  side.querySelectorAll('[data-acc]').forEach(b => b.onclick = () => {
    const g = b.dataset.acc, abrir = SIDE_ABIERTO !== g;
    SIDE_ABIERTO = abrir ? g : '';
    side.querySelectorAll('.acc-g').forEach(sec => {
      const btn = sec.querySelector('[data-acc]'), on = abrir && btn.dataset.acc === g, panel = sec.querySelector('.acc-p');
      sec.classList.toggle('is-open', on); btn.setAttribute('aria-expanded', on); panel.inert = !on;
      btn.setAttribute('aria-label', btn.getAttribute('aria-label').replace(/^(Plegar|Desplegar)/, on ? 'Plegar' : 'Desplegar'));
    });
  });
}

/* ---------- El menú de módulos ---------- */
function moduleMenu() {
  if (SWITCHING) return;
  const available = ALL().filter(k => !MODS[k].fijo);
  const groups = Object.keys(GRUPOS).filter(g => available.some(k => MODS[k].g === g));
  const html = groups.map(g => `<section class="module-section" aria-label="${esc(GRUPOS[g].name)}"><h2 class="mdm-gt">${esc(GRUPOS[g].name)}</h2><div class="module-row">${available.filter(k => MODS[k].g === g).map(id => {
    const m = MODS[id], allowed = moduleAllowed(id), n = allowed ? m.n?.() : null;
    return `<a class="mdm-i ${id === modMenu() ? 'is-on' : ''}" href="${href(m.path)}" data-mod="${id}" ${id === modMenu() ? 'aria-current="page"' : ''}><span class="mdm-ic" style="--c:${m.c}">${icon(m.icon)}</span><span class="tx"><b>${esc(m.name)}</b><small title="${esc(moduleCaption(id))}">${esc(moduleCaption(id))}</small>${!allowed ? '<span class="module-access">Acceso restringido</span>' : ''}</span>${n ? `<b class="nav-b">${int(n)}</b>` : ''}${id === modMenu() ? icon('tick', 'ok') : ''}</a>`;
  }).join('')}</div></section>`).join('');
  const c = MODS.centro, inicio = c && moduleAllowed('centro') ? `<a class="mdm-i mdm-home ${R.mod === 'centro' ? 'is-on' : ''}" href="${href(c.path)}" data-mod="centro" ${R.mod === 'centro' ? 'aria-current="page"' : ''}><span class="mdm-ic" style="--c:${c.c}">${icon(c.icon)}</span><span class="tx"><b>${esc(c.name)}</b><small>${esc(c.desc)}</small></span></a>` : '';
  const el = openPop($('#b-crumb'), `<nav class="module-sections" aria-label="Menú principal">${inicio}${html}</nav><div class="mdm-ft"><span>Flechas para moverte · Enter para abrir</span><span><kbd>M</kbd> abre · <kbd>Esc</kbd> cierra</span></div>`, 'mdm grouped-menu');
  if (!el) return;
  $('#b-crumb').setAttribute('aria-haspopup', 'true');
  el.style.top = Math.min($('#b-crumb').getBoundingClientRect().bottom + 8, innerHeight - 180) + 'px';
  el.style.maxHeight = (innerHeight - parseFloat(el.style.top) - 12) + 'px';
  const links = [...el.querySelectorAll('a[data-mod]')];
  (el.querySelector('[aria-current="page"]') || links[0])?.focus({preventScroll: true});
  el.addEventListener('keydown', e => {
    if(e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closePop(); $('#b-crumb')?.focus(); return; }
    const index = links.indexOf(document.activeElement);
    const cols = getComputedStyle(el.querySelector('.module-row')).gridTemplateColumns.split(' ').length;
    const delta = {ArrowRight: 1, ArrowLeft: -1, ArrowDown: cols, ArrowUp: -cols}[e.key];
    if(delta !== undefined || e.key === 'Home' || e.key === 'End') {
      e.preventDefault(); links[e.key === 'Home' ? 0 : e.key === 'End' ? links.length - 1 : Math.max(0, Math.min(links.length - 1, index + delta))]?.focus();
    }
  });
}

function launcherRecent(agency = false) {
  const title = agency ? 'Subcuentas recientes' : 'Acceso reciente';
  let chips = '';
  if (agency) {
    const key = 'agency-recent:' + String(ME?.id || '');
    const saved = store.get(key, []);
    const ids = Array.isArray(saved) ? saved : [];
    chips = ids.map(id => AGENCY_ACCOUNTS?.find(a => String(a.id) === String(id))).filter(Boolean).slice(0,4).map(a => `<button class="launch-chip" type="button" data-agency-open="${esc(a.id)}">${icon('users')}${esc(a.nombre || a.name)}${icon('chevR')}</button>`).join('');
  } else {
    const saved = store.get(navigationHistoryKey(), []);
    const items = Array.isArray(saved) ? saved : [];
    const seen = new Set();
    chips = items.map(item => Object.entries(MODS).find(([id,m]) => id !== 'centro' && m.path === item.p)).filter(item => item && !seen.has(item[0]) && seen.add(item[0])).slice(0,4).map(([id,m]) => `<a class="launch-chip" href="${href(m.path)}" style="--module-color:${m.c}">${icon(m.icon)}${esc(m.name)}${icon(moduleAllowed(id) ? 'chevR' : 'lock')}</a>`).join('');
  }
  return `<section class="launch-recent" aria-label="${title}"><h2>${title}</h2><div class="launch-chips">${chips || `<p class="launch-empty">${agency ? 'Las empresas que abras aparecerán aquí.' : 'Tus últimos módulos aparecerán aquí al comenzar a trabajar.'}</p>`}</div></section>`;
}
function launcherFrame(mode, content) {
  const agency = mode === 'agencia';
  if (!agency) return `<div class="launch-layout launch-no-rail"><div class="launch-content">${content}</div></div>`;
  const name = ME?.nombre || AGORA_ME?.user?.name || 'Mi cuenta';
  const groupIcons = {general:'home',ingresos:'invoice',clientes:'users',gestion:'pie',contratos:'receipt',administracion:'gear'};
  return `<div class="launch-layout"><aside class="launch-rail" aria-label="Navegación de ${agency ? 'Agencia' : 'subcuenta'}"><div class="launch-brand launch-brand-logo" aria-label="Ágora Digital App">${LOGO}</div><p class="launch-rail-label">${agency ? 'Agencia' : esc(ws?.name() || 'Mi subcuenta')}</p><nav class="launch-primary"><a class="${R.view==='agencia'?'is-current':''}" href="${href(agency ? '/agencia' : '/centro')}" ${R.view==='agencia'?'aria-current="page"':''}>${icon(agency ? 'users' : 'home')}${agency ? 'Subcuentas' : 'Inicio de subcuenta'}</a>${!agency && esSuperAdmin() ? `<button type="button" data-agency-return>${icon('users')}Ir a subcuentas</button>` : ''}${agency ? '' : Object.entries(GRUPOS).map(([id,g]) => `<button type="button" data-launch-group="group-${id}">${icon(groupIcons[id])}${esc(g.name)}</button>`).join('')}</nav><div class="launch-system"><p class="launch-rail-label">${AG_GOB_NOMBRE}</p><a href="${href('/agencia/control')}" ${R.view==='agencyControl'?'aria-current="page"':''}>${icon('pie')}Resumen</a><a href="${href('/agencia/usuarios')}" ${R.view==='agencyTeam'?'aria-current="page"':''}>${icon('team')}Gestión de usuarios</a><a href="${href('/agencia/actividad')}" ${R.view==='agencyActivity'?'aria-current="page"':''}>${icon('shield')}Registro de actividad</a><a href="${href('/agencia/estudio-ia')}" ${R.view==='agencyStudio'?'aria-current="page"':''}>${icon('spark')}Estudio</a>${!agency ? `<p class="launch-rail-label">Mi espacio</p><a href="${href('/ajustes')}">${icon('gear')}Ajustes</a><a href="${href('/ayuda')}">${icon('info')}Ayuda</a>` : ''}</div><a class="launch-person" href="${href('/perfil')}" ${R.view==='perfil'?'aria-current="page"':''}><span class="launch-avatar">${personalAvatar()}</span><span><b>${esc(name)}</b><small>${esSuperAdmin() ? (esMaster() ? 'Master · Dueño' : 'Superadmin de Agencia') : 'Mi perfil y cuenta'}</small></span>${icon('chevR')}</a></aside><div class="launch-content">${content}</div></div>`;
}
document.addEventListener('click', e => {
  const button = e.target.closest('[data-launch-group]');
  if (!button) return;
  const target = document.getElementById(button.dataset.launchGroup);
  if (target) { target.setAttribute('tabindex','-1'); target.focus({preventScroll:true}); target.scrollIntoView({behavior:reduced() ? 'instant' : 'smooth',block:'start'}); }
});
/* Inicio de la subcuenta: la marca, el acceso reciente y los 6 módulos principales.
   Cada módulo es un icono que abre su pantalla con los submódulos adentro. */
function moduloTile(g, ids, i) {
  const G = GRUPOS[g], habil = ids.filter(moduleAllowed).length;
  const badge = ids.reduce((t, id) => t + (moduleAllowed(id) ? Number(MODS[id].n?.() || 0) : 0), 0);
  return `<a class="mod-tile" href="${href('/modulos/' + G.slug)}" style="--module-color:${G.c};--card-order:${i}">
    <span class="mod-tile-ic">${icon(G.icon)}</span>${badge ? `<span class="operation-badge">${int(badge)}</span>` : ''}
    <span class="mod-tile-tx"><h3>${esc(G.name)}</h3><p>${esc(G.desc)}</p></span>
    <span class="mod-tile-subs">${ids.map(id => `<span>${esc(MODS[id].name)}</span>`).join('')}</span>
    <span class="mod-tile-ft"><span>${ids.length} ${ids.length === 1 ? 'submódulo' : 'submódulos'}${habil < ids.length ? ` · ${habil} habilitados` : ''}</span><span class="mod-tile-go">Entrar ${icon('chevR')}</span></span>
  </a>`;
}
/* Marca del Inicio de subcuenta (2.1.10): logo de Ágora + logo de la empresa dueña de la subcuenta, lado a lado
   y a la misma altura óptica. El logo sale solo de Identidad del espacio (ESPACIO.logo); mientras no haya uno,
   se muestra la inicial y el nombre de la empresa en su lugar. */
function marcaInicio(account) {
  const nombre = ESPACIO?.nombre || 'la empresa';
  const logo = typeof ESPACIO?.logo === 'string' && /^(data:image\/|\/assets\/|https:\/\/)/.test(ESPACIO.logo) ? ESPACIO.logo : '';
  /* Sin logo cargado solo se ve la marca Ágora: el nombre o el código de la subcuenta nunca acompañan a la marca. */
  if (!logo) return `<div class="home-brand home-brand-duo is-solo" aria-label="Ágora Digital App"><span class="home-brand-logo">${LOGO}</span></div>`;
  return `<div class="home-brand home-brand-duo" aria-label="Ágora Digital App">
    <span class="home-brand-logo">${LOGO}</span><span class="home-brand-sep" aria-hidden="true"></span><span class="home-brand-client"><img src="${esc(logo)}" alt="Logo de ${esc(nombre)}" decoding="async"></span>
  </div>`;
}
V.centro = {
  render() {
    const grupos = gruposMenu(), ids = grupos.flatMap(([, x]) => x);
    const name = String(AGORA_ME?.user?.name || ME?.nombre || 'equipo').split(' ')[0];
    const account = ws?.name() || ESPACIO?.nombre || 'Tu subcuenta';
    try { localStorage.removeItem('agora_ultima_cuenta'); } catch { /* modo privado */ }
    const content = `<div class="operations-home home-v2"><header class="operations-heading">${marcaInicio(account)}<h1>Hola, <em>${esc(name)}</em></h1><p class="operations-lede">Elige un módulo para ver sus herramientas.</p><p class="launch-summary"><strong>${grupos.length} módulos</strong> · ${ids.length} herramientas, ${ids.filter(moduleAllowed).length} habilitadas para tu rol.</p></header>${tableroInicioHtml()}${launcherRecent()}
      <section class="operations-group" aria-labelledby="group-modulos"><h2 id="group-modulos">Módulos<span>${grupos.length}</span></h2><div class="mod-tiles">${grupos.map(([g, x], i) => moduloTile(g, x, i)).join('')}</div></section></div>`;
    return launcherFrame('centro', content);
  }
};
/* Pantalla de un módulo principal: sus submódulos como tarjetas. */
V.grupo = {
  render() {
    const g = R.grupo, G = GRUPOS[g];
    if (!G) return launcherFrame('centro', '<p class="empty">Ese módulo no existe.</p>');
    const ids = gruposMenu().find(([k]) => k === g)?.[1] || [];
    const otros = gruposMenu().filter(([k]) => k !== g);
    const content = `<div class="operations-home grupo-v"><nav class="grupo-crumbs" aria-label="Ruta"><a href="${href('/centro')}">${icon('home')}Inicio</a><span aria-hidden="true">${icon('chevR')}</span><b>${esc(G.name)}</b></nav>
      <header class="grupo-head" style="--module-color:${G.c}"><span class="grupo-ic">${icon(G.icon)}</span><div><p class="operations-eyebrow"><span></span>Módulo</p><h1>${esc(G.name)}</h1><p class="operations-lede">${esc(G.desc)}</p></div></header>
      <section class="operations-group" aria-label="Submódulos de ${esc(G.name)}"><h2>Submódulos<span>${ids.length}</span></h2><div class="operations-grid">${ids.map((id, i) => {
        const m = MODS[id], allowed = moduleAllowed(id), n = allowed ? m.n?.() : null;
        return `<a class="operation-card" href="${href(m.path)}" style="--module-color:${m.c};--card-order:${i}"><span class="operation-icon">${icon(m.icon)}</span>${n ? `<span class="operation-badge">${int(n)}</span>` : ''}<h3>${esc(m.name)}</h3><p>${esc(m.desc)}</p><span class="operation-open">${allowed ? 'Abrir' : 'Acceso restringido'} ${icon(allowed ? 'chevR' : 'lock')}</span></a>`;
      }).join('')}</div></section>
      <section class="grupo-otros" aria-label="Otros módulos"><h2>Otros módulos</h2><div>${otros.map(([k]) => `<a href="${href('/modulos/' + GRUPOS[k].slug)}" style="--module-color:${GRUPOS[k].c}"><span class="mdm-ic" style="--c:${GRUPOS[k].c}">${icon(GRUPOS[k].icon)}</span>${esc(GRUPOS[k].name)}</a>`).join('')}</div></section></div>`;
    return launcherFrame('centro', content);
  }
};

/* Perfil personal: foto por identidad; nunca por subcuenta compartida. */
function profilePhotoKey() {
  const email = String(AGORA_ME?.user?.email || ME?.correo || '').trim().toLowerCase();
  return 'agora_personal_photo_v2:' + String(AGORA_ME?.user?.id || ME?.id || email);
}
function profilePhoto() {
  if (!ME && !AGORA_ME) return '';
  try { const value = ME?.foto ?? (localStorage.getItem(profilePhotoKey()) || ''); return /^data:image\/jpeg;base64,[a-zA-Z0-9+/=]+$/.test(value) && value.length < 500000 ? value : ''; } catch { return ''; }
}
function personalAvatar() {
  const photo = profilePhoto();
  return photo ? `<img class="personal-avatar" src="${photo}" alt="Tu foto de perfil">` : icon('user');
}
async function prepareProfilePhoto(file) {
  if (!file || !['image/jpeg','image/png','image/webp'].includes(file.type)) throw new Error('Selecciona una imagen JPG, PNG o WebP.');
  if (file.size > 5 * 1024 * 1024) throw new Error('La foto debe pesar como máximo 5 MB.');
  const url = URL.createObjectURL(file);
  try {
    const img = new Image();
    await new Promise((resolve,reject) => { img.onload=resolve; img.onerror=()=>reject(new Error('No se pudo leer esta imagen. Prueba con otra foto.')); img.src=url; });
    if (!img.naturalWidth || !img.naturalHeight || img.naturalWidth * img.naturalHeight > 20000000) throw new Error('Usa una imagen de hasta 20 megapíxeles.');
    const canvas = document.createElement('canvas'); canvas.width = canvas.height = 256;
    const ctx = canvas.getContext('2d'); if (!ctx) throw new Error('Tu navegador no pudo preparar la imagen.');
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,256,256);
    const side = Math.min(img.naturalWidth,img.naturalHeight);
    ctx.drawImage(img,(img.naturalWidth-side)/2,(img.naturalHeight-side)/2,side,side,0,0,256,256);
    return canvas.toDataURL('image/jpeg',0.88);
  } finally { URL.revokeObjectURL(url); }
}
/* Perfil (2.1.8): tarjeta de identidad a la izquierda y secciones ordenadas a la derecha.
   En la agencia se muestra dentro del marco con riel, para no perder la orientación. */
function perfilRolTexto() {
  if (esMaster()) return 'Tienes el acceso completo: creas subcuentas, anotas sus cobros y defines los roles del equipo.';
  if (esSuperAdmin()) return 'Administras las subcuentas que te asignaron. Los cobros y los roles clave los maneja el Master.';
  if (ME?.rol === 'ADMIN') return 'Administras tu empresa: su equipo, sus módulos y su configuración.';
  return 'Usas los módulos que te asignó quien administra tu empresa.';
}
V.perfil = {
  render() {
    const name = ME?.nombre || AGORA_ME?.user?.name || 'Mi cuenta';
    const email = AGORA_ME?.user?.email || ME?.correo || '';
    const role = esMaster() ? 'Master · Dueño' : esSuperAdmin() ? 'Superadmin de Agencia' : ME?.rol === 'ADMIN' ? 'Administrador de subcuenta' : ROLES[ME?.rol]?.l || 'Equipo';
    const agencia = esSuperAdmin() && AGENCY_CONTEXT;
    const foto = profilePhoto();
    const espacio = agencia ? `Agencia${Array.isArray(AGENCY_ACCOUNTS) ? ` · ${AGENCY_ACCOUNTS.length} ${AGENCY_ACCOUNTS.length === 1 ? 'subcuenta' : 'subcuentas'}` : ''}` : (ws?.name?.() || 'Mi subcuenta');
    const seccion = (ic, titulo, desc, cuerpo, extra = '') => `<section class="panel pf-sec" ${extra}><header class="pf-sec-h"><span class="pf-sec-ic" aria-hidden="true">${icon(ic)}</span><div><h2>${titulo}</h2><p>${desc}</p></div></header>${cuerpo}</section>`;
    const fila = (titulo, desc, accion) => `<div class="pf-row"><div class="pf-row-tx"><b>${titulo}</b><small>${desc}</small></div>${accion}</div>`;
    const accesos = agencia ? `<div class="pf-links"><a class="pf-link" href="${href('/agencia/control')}">${icon('pie')}${esc(AG_GOB_NOMBRE)}${icon('chevR')}</a><a class="pf-link" href="${href('/agencia/usuarios')}">${icon('team')}Gestión de usuarios${icon('chevR')}</a><a class="pf-link" href="${href('/agencia/actividad')}">${icon('shield')}Registro de actividad${icon('chevR')}</a></div>` : '';
    const contenido = `<div class="pf">${head('Mi cuenta', 'Mi perfil', 'Tus datos, tu foto y la seguridad de tu acceso, en un solo lugar.', `<a class="btn" href="${href(homePath())}">${icon('home')}Volver al inicio</a>`)}
      <div class="pf-grid">
        <aside class="panel pf-card" aria-label="Tu identidad">
          <div class="pf-avatar">${personalAvatar()}<label class="pf-cam" for="personal-photo-input" title="${foto ? 'Cambiar foto' : 'Agregar foto'}"><span class="sr-only">${foto ? 'Cambiar foto' : 'Agregar foto'}</span>${icon('upload')}</label></div>
          <input id="personal-photo-input" class="profile-file sr-only" type="file" accept="image/jpeg,image/png,image/webp">
          <h2 class="pf-name">${esc(name)}</h2>
          <p class="pf-mail">${icon('mail')}${esc(email)}</p>
          <span class="sc sc-info pf-role">${esc(role)}</span>
          <div class="pf-photo-acts"><label class="btn btn-sm btn-cta" for="personal-photo-input">${icon('upload')}${foto ? 'Cambiar foto' : 'Agregar foto'}</label><button class="btn btn-sm" type="button" data-photo-remove ${foto ? '' : 'disabled'}>Quitar foto</button></div>
          <p class="pf-hint">JPG, PNG o WebP de hasta 5 MB. La recortamos en cuadrado, al centro.${window.__DEMO__ ? ' En la demo se guarda mientras dure la sesión.' : ''}</p>
          <p id="profile-photo-status" class="pf-status" role="status" aria-live="polite"></p>
          <dl class="pf-meta"><div><dt>Espacio</dt><dd>${esc(espacio)}</dd></div><div><dt>Rol</dt><dd>${esc(role)}</dd></div></dl>
        </aside>
        <div class="pf-main">
          ${profileEditForm()}
          ${seccion('lock', 'Seguridad', 'Protege tu acceso a Ágora.', fila('Clave', 'Al cambiarla se cierran tus otras sesiones abiertas.', `<button class="btn btn-sm" type="button" data-pf-clave>${icon('lock')}Cambiar clave</button>`) + fila('Cerrar sesión', 'Sal de Ágora en este equipo.', `<button class="btn btn-sm" type="button" data-pf-salir>${icon('logout')}Cerrar sesión</button>`))}
          ${seccion('briefcase', 'Tu rol', esc(role), `<p class="pf-text">${perfilRolTexto()}</p>${accesos}`)}
        </div>
      </div></div>`;
    return agencia ? launcherFrame('agencia', contenido) : contenido;
  },
  mounted(root) {
    const input = root.querySelector('#personal-photo-input'), status = root.querySelector('#profile-photo-status');
    input.addEventListener('change',async () => {
      const file = input.files?.[0]; if (!file) return;
      const key = profilePhotoKey(), epoch = NAV_EPOCH;
      input.disabled = true; status.textContent = 'Preparando tu foto…';
      try {
        const photo = await prepareProfilePhoto(file);
        if (key !== profilePhotoKey() || epoch !== NAV_EPOCH || (!ME && !AGORA_ME)) return;
        const saved=await agoraApi('profile',{method:'PUT',body:{foto:photo}});
        if(!saved.usuario)throw new Error('El servidor no confirmó la foto.');
        updateProfileIdentity(saved.usuario);
        if (R.view === 'perfil') repaint(); else topbar();
        toast('Foto de perfil actualizada.');
      } catch (err) { if (status.isConnected) status.textContent = err.message; }
      finally { input.disabled = false; input.value = ''; }
    });
    root.querySelector('[data-pf-clave]')?.addEventListener('click', () => claveModal());
    root.querySelector('[data-pf-salir]')?.addEventListener('click', () => salir());
    root.querySelector('[data-photo-remove]').addEventListener('click',async () => {
      try { const saved=await agoraApi('profile',{method:'PUT',body:{foto:null}});if(!saved.usuario)throw new Error('Sin confirmación');updateProfileIdentity(saved.usuario);try{localStorage.removeItem(profilePhotoKey());}catch{}repaint(); toast('Foto de perfil eliminada.'); }
      catch { status.textContent = 'No se pudo quitar la foto del navegador. Inténtalo de nuevo.'; }
    });
  }
};

/* ---------- Historial: lo último que se ha visto ---------- */
function navigationHistoryKey() { return 'historial:' + String(AGORA_ME?.user?.id || ME?.id || 'anon') + ':' + String(ws?.current() || 'none'); }
function anotarHistorial() {
  if (!R.mod) return;
  const m = MODS[R.mod];
  const item = {p: path(), t: m.name, i: m.icon, c: m.c || 'var(--brand)', at: Date.now()};
  const prev = store.get(navigationHistoryKey(), []).filter(x => x.p !== item.p);
  store.set(navigationHistoryKey(), [item, ...prev].slice(0, 12));
}
function historialPop() {
  const items = store.get(navigationHistoryKey(), []).filter(x => x.p !== path() && Object.values(MODS).some(m => m.path === x.p));
  const el = openPop($('#b-hist'), items.length
    ? `<p class="mdm-gt">Lo último que has visto</p>${items.slice(0, 8).map(x => `<a class="mdm-i" href="${href(x.p)}"><span class="mdm-ic" style="--c:${x.c}">${icon(x.i)}</span><span class="tx"><b>${esc(x.t)}</b><small>${esc(when(new Date(x.at).toISOString()))}</small></span></a>`).join('')}`
    : '<p class="empty" style="padding:22px 16px"><b>Todavía no hay historial</b>Aquí queda lo último que abras.</p>', 'ctx hist', {align: 'right'});
  if (el) el.setAttribute('role', 'menu');
}

/* ---------- Estado del espacio ---------- */

/* =====================================================================================
   Athena: el agente, en el panel de la derecha
   -------------------------------------------------------------------------------------
   Lee lo que el sistema ya tiene cargado y responde sobre eso. Cuando el servidor exponga
   el agente de verdad (POST {AGORA_API}athena), basta con cambiar `preguntar`.
   ===================================================================================== */
var athEl = null, athLog = [];
const ATH_SUG = ['¿Cómo vamos este mes?', '¿Quién tiene conversaciones sin leer?', '¿Cuánto llevamos gastado en anuncios?', '¿Qué convenios están por firmar?'];

function abrirAthena(pregunta) {
  if (athEl) { if (!pregunta) return cerrarAthena(); }
  if (!athEl) {
    athEl = document.createElement('div');
    athEl.className = 'ath';
    athEl.setAttribute('role', 'dialog');
    athEl.setAttribute('aria-label', 'Athena');
    athEl.innerHTML = `<div class="ath-h">${athenaAvatar('ath-av-g')}<div><b>Athena</b><small>Tu agente: leo tus cifras y te las resumo</small></div><button class="x" type="button" data-ath-close aria-label="Cerrar">${icon('plus')}</button></div>
      <div class="ath-log" id="ath-log"></div>
      <div class="ath-sug" id="ath-sug">${ATH_SUG.map(s => `<button class="btn btn-sm" type="button" data-ath-sug="${esc(s)}">${esc(s)}</button>`).join('')}</div>
      <form class="ath-f"><label class="sr-only" for="ath-in">Pregúntale a Athena</label><input id="ath-in" autocomplete="off" placeholder="Pregúntale sobre tus cifras…"><button class="btn btn-cta" type="submit" aria-label="Enviar">${icon('send')}</button></form>`;
    $('#layer').append(athEl);
    $('#b-ath')?.setAttribute('aria-expanded', 'true');
    athEl.querySelector('[data-ath-close]').onclick = cerrarAthena;
    athEl.querySelector('form').onsubmit = e => { e.preventDefault(); const i = athEl.querySelector('#ath-in'); const t = i.value.trim(); if (!t) return; i.value = ''; decir(t); };
    athEl.querySelectorAll('[data-ath-sug]').forEach(b => b.onclick = () => decir(b.dataset.athSug));
    if (!athLog.length) pintarAthena([{de: 'bot', html: `<p>Hola${ME?.nombre ? ', ' + esc(ME.nombre.split(' ')[0]) : ''}. Leo las cifras que tienes cargadas y te lo resumo.</p><p>Pregúntame por el mes, por la bandeja, por los anuncios o por los convenios.</p>`}]);
    else pintarAthena();
  }
  if (pregunta) decir(pregunta);
  athEl.querySelector('#ath-in')?.focus({preventScroll: true});
}
function cerrarAthena() {
  if (!athEl) return;
  const e = athEl; athEl = null;
  $('#b-ath')?.setAttribute('aria-expanded', 'false');
  if (reduced()) e.remove(); else { e.classList.add('out'); setTimeout(() => e.remove(), 220); }
}
function pintarAthena(nuevos) {
  if (nuevos) athLog = athLog.concat(nuevos);
  const log = athEl?.querySelector('#ath-log'); if (!log) return;
  log.innerHTML = athLog.map(m => m.de === 'me' ? `<div class="msg me">${esc(m.texto)}</div>` : `<div class="msg bot">${athenaAvatar('ath-av-s')}<div class="msg-tx">${m.html}</div></div>`).join('');
  log.scrollTop = log.scrollHeight;
}
function decir(texto) {
  pintarAthena([{de: 'me', texto}]);
  const log = athEl.querySelector('#ath-log');
  log.insertAdjacentHTML('beforeend', '<div class="msg bot typing" id="ath-typing"><i></i><i></i><i></i></div>');
  log.scrollTop = log.scrollHeight;
  setTimeout(() => {
    athEl?.querySelector('#ath-typing')?.remove();
    pintarAthena([{de: 'bot', html: responderAthena(texto)}]);
  }, 420);
}
/** La respuesta sale de lo que ya está cargado: nada se inventa ni sale a internet. */
function responderAthena(texto) {
  const q = norm(texto);
  const ir = (p, r) => `<div class="go"><a class="btn btn-sm btn-tint" href="${href(p)}" data-ath-go>${esc(r)}</a></div>`;
  if (/mes|vamos|resumen|negocio|mrr|ingreso/.test(q)) {
    const d = DATOS.resumen;
    if (!d) return `<p>Todavía no he leído el resumen del mes. Abre <b>Resumen</b> y vuelve a preguntarme.</p>${ir('/inicio', 'Abrir Resumen')}`;
    return `<p>En ${esc(mesNombre(PERIODO).toLowerCase())} el ingreso recurrente va en <b>${esc(usd(d.mrr))}</b>, con ${int(d.clientesActivos)} cliente${d.clientesActivos === 1 ? '' : 's'} activo${d.clientesActivos === 1 ? '' : 's'}.</p>
      <ul><li>Recaudado: ${esc(usd(d.recaudado))}</li><li>Egresos: ${esc(usd(d.egresos))}</li><li>Saldo de caja: ${esc(usd(d.caja))}</li></ul>
      <p>${Number(d.caja) < 0 ? 'La caja está en negativo: los egresos van por encima de lo cobrado.' : 'La caja está en positivo.'}</p>${ir('/inicio', 'Ver el resumen')}`;
  }
  if (/sin leer|no leida|bandeja|conversacion|sla|respuesta/.test(q)) {
    const d = DATOS.dia;
    if (!d) return `<p>Abre <b>ARP Comercial</b> y vuelvo a mirar las cifras del día.</p>${ir('/comercial', 'Abrir ARP Comercial')}`;
    const peor = (d.sinLeerPorAsesor || []).slice().sort((a, b) => b.n - a.n)[0];
    return `<p>Hoy hay <b>${int(d.sinLeer)}</b> conversaciones sin leer y <b>${int(d.slaIncumplido)}</b> fuera del tiempo de respuesta.</p>
      ${peor ? `<p>Quien más acumula es ${esc(peor.nombre)}, con ${int(peor.n)}.</p>` : ''}
      <p>El tiempo de respuesta promedio va en ${esc(d.slaPromedio)}.</p>${ir('/bandeja', 'Ir a la bandeja')}`;
  }
  if (/anuncio|meta|ads|publicidad|gastado|invers/.test(q)) {
    const a = DATOS.ads;
    if (!a) return `<p>Abre <b>ARP Comercial</b> para que lea la inversión en anuncios.</p>${ir('/comercial', 'Abrir ARP Comercial')}`;
    if (a.estado !== 'ok') return `<p>La conexión con Meta está vencida, así que no puedo darte el gasto de hoy. Hay que volver a conectarla en Ajustes.</p>${ir('/ajustes', 'Ir a Ajustes')}`;
    return `<p>Hoy llevas <b>${esc(usd(a.dia))}</b> invertidos y <b>${esc(usd(a.mes))}</b> en lo que va de ${esc(mesNombre(PERIODO).toLowerCase())}.</p>
      <ul><li>Impresiones: ${int(a.impresiones)}</li><li>Coste por conversión: ${esc(usd(a.costePorConversion))}</li></ul>${ir('/comercial', 'Ver el detalle')}`;
  }
  if (/convenio|contrato|firmar|firmado/.test(q)) {
    const c = DATA.contratos;
    if (!c) return `<p>Abre <b>Contratos</b> y te digo cómo van.</p>${ir('/contratos', 'Abrir Contratos')}`;
    const porFirmar = c.filter(x => x.estado === 'ENVIADO').length, firmados = c.filter(x => x.estado === 'FIRMADO').length;
    return `<p>Hay <b>${int(porFirmar)}</b> convenios por firmar y ${int(firmados)} ya firmados, de ${int(c.length)} en total.</p>${ir('/contratos', 'Ver los convenios')}`;
  }
  if (/tarea|pendiente|recordatorio/.test(q)) {
    const t = (DATOS.tareas || []).filter(x => x.estado !== 'hecha');
    return `<p>Quedan <b>${int(t.length)}</b> tareas abiertas; ${int(t.filter(x => x.vence && x.vence < new Date().toISOString().slice(0, 10)).length)} están vencidas.</p>${ir('/tareas', 'Ver las tareas')}`;
  }
  return `<p>De eso todavía no tengo dato. Puedo con el resumen del mes, la bandeja y el tiempo de respuesta, la inversión en anuncios, los convenios y las tareas.</p>`;
}

/* =====================================================================================
   Puentes de eventos con los módulos de Ágora
   ===================================================================================== */
const AG_TO_MOD = {dashboard: 'comercial', inbox: 'bandeja', contacts: 'contactos', board: 'oportunidades', settings: 'conexiones', help: 'ayuda'};

window.addEventListener('agora:toast', e => toast(String(e.detail || '')));
window.addEventListener('agora:unread', e => { unread = Number(e.detail) || 0; const b = $('#b-crumb .nav-b'); if (b && R.mod === 'bandeja') b.textContent = int(unread); });
window.addEventListener('agora:won-alert', e => { wonPendientes = Number(e.detail?.count) || 0; });
window.addEventListener('agora:goto', e => {
  const d = e.detail || {};
  const mod = AG_TO_MOD[d.view];
  if (!mod || !MODS[mod]?.ok()) return;
  if (ws) ws.pendingFilter = d.filter || null;
  closeAll(); go(MODS[mod].path);
});
window.addEventListener('agora:open-conversation', e => {
  const c = e.detail;
  if (!c?.id || !ws) return;
  ws.pendingConversation = c;
  closeAll(); go('/bandeja');
});
window.addEventListener('agora:logout', () => { AGORA_ME = null; AGORA_CONN = []; unread = 0; if (ws) ws.reset(); });
/* Un recordatorio puesto desde un chat se convierte en tarea: la lista se refresca. */
window.addEventListener('agora:reminder', e => {
  const r = e.detail || {};
  DATOS.tareas = null;
  toast(`Recordatorio puesto para ${esc(r.cuando || 'la fecha elegida')}.`);
});

/* ---------- Controles propios del armazón ---------- */
document.addEventListener('change', e => { if (['profile-sub-account', 'nav-sub-account'].includes(e.target?.id)) { if (!esSuperAdmin() || !e.target.value) { topbar(); return; } abrirCuentaAgencia(e.target.value); } });
document.addEventListener('click', e => { if (e.target.closest('[data-open-modules]')) moduleMenu(); else if (e.target.closest('[data-nav-search]')) openCmd(); else if (e.target.closest('[data-nav-account]')) openAccount(); });
document.addEventListener('click', e => {
  const t = e.target;
  if (t?.closest?.('[data-agora-login]')) { closeAll(); toast('Usa el acceso unificado de Ágora.'); return; }
  if (t?.closest?.('#b-crumb')) { moduleMenu(); return; }
  if (t?.closest?.('#b-hist')) { historialPop(); return; }
  if (t?.closest?.('#b-ath')) { closePop(); abrirAthena(); return; }
  if (t?.closest?.('[data-ath-go]')) { cerrarAthena(); return; }
  const mes = t?.closest?.('[data-mes]');
  if (mes) { ponerPeriodo(mes.dataset.mes); return; }
});

/* =====================================================================================
   Acceso a Ágora
   ===================================================================================== */
/* Los formularios y modales de autenticación separados se retiraron en v1.3.5.
   El único punto de entrada es /entrar, respaldado por /api/login y sesión compartida. */
/* =====================================================================================
   La subcuenta en uso
   ===================================================================================== */
const agAccountViews = ['ag_dashboard', 'ag_inbox', 'ag_contacts', 'ag_board'];
function accountSwitch() { return ''; }

/* =====================================================================================
   Arranque de la sesión de Ágora y de la identidad del espacio
   ===================================================================================== */
async function bootAgora() {
  if (!AG_READY) return;
  try { AGORA_ME = await agoraApi('me'); } catch { AGORA_ME = null; }
  if (!AGORA_ME) { AGORA_CONN = []; ws?.reset(); return; }
  ws.setProfile(AGORA_ME);
  try {
    const {items, reason} = await agoraApi('workspace/connections');
    AGORA_CONN = Array.isArray(items) ? items : [];
    AGORA_REASON = reason || null;
    ws.setConnections(AGORA_CONN, AGORA_REASON);
  } catch { AGORA_CONN = []; ws.setConnections([]); }
  // En Agencia no hay subcuenta activa: la alerta de ventas se arranca al entrar a una.
  if (!(esSuperAdmin() && AGENCY_CONTEXT)) { try { AG.startWonAlert(); } catch { /* la alerta nunca estorba */ } }
}
function resetAccountData() {
  NAV_EPOCH++; GEN++;
  if(typeof GOV !== 'undefined') GOV={team:null,studio:null};
  if(typeof UI_TEXTS !== 'undefined') UI_TEXTS={global:{},tenant:{}};
  for (const controller of NAV_REQUESTS) controller.abort();
  NAV_REQUESTS.clear();
  disposeAgora(); closeAll(); cerrarAthena(); athLog = [];
  window.dispatchEvent(new Event('agora:account-changing'));
  DATA.reset();
  DATOS = {resumen:null,dia:null,ads:null,eficacia:null,tareas:null,auditoria:null,usuarios:null};
  PERMISOS_CONFIG = null; ESPACIO = null;
  unread = 0; wonPendientes = 0;
  if (ws) { ws.pendingFilter = null; ws.pendingConversation = null; ws.pendingOppTab = null; }
  if (TB) Object.assign(TB, {estado:'vacio',error:'',api:null,carga:null,cfg:{},notas:{}});
  Object.keys(state).forEach(k => delete state[k]);
  Object.assign(state, JSON.parse(JSON.stringify(INITIAL_VIEW_STATE)));
}
async function cambiarSubcuenta(id) {
  if (SWITCHING) return false;
  if (!esSuperAdmin() || !ws || !AGORA_CONN.some(c => String(c.id) === String(id))) { toast('Esta subcuenta no está autorizada para tu usuario.'); return false; }
  SWITCHING = true;
  resetAccountData();
  topbar(); paint(loading());
  try {
    const result = await agoraApi('workspace/active', {method:'POST', body:{connectionId:id}});
    if ((!result || typeof result !== 'object') || result.ok === false || (result.accountId != null && String(result.accountId) !== String(id))) throw new Error('El servidor no confirmó la subcuenta solicitada.');
    // Elegir otra vez la cuenta actual es válido; select solo notifica cambios reales.
    if (String(ws.current()) !== String(id) && !ws.select(id)) throw new Error('No se pudo seleccionar la subcuenta.');
    await bootEspacio();
    const recentKey = 'agency-recent:' + String(ME?.id || '');
    const previous = store.get(recentKey, []);
    store.set(recentKey, [String(id), ...(Array.isArray(previous) ? previous : []).filter(value => String(value) !== String(id))].slice(0,4));
    AGENCY_CONTEXT = false;
    SWITCHING = false;
    await loadUiTexts();
    go('/centro');
    try { AG.startWonAlert(); } catch { /* la navegación no depende del aviso */ }
    toast('Subcuenta activa: ' + (ws.name() || id));
    return true;
  } catch (err) {
    // Una respuesta perdida puede haber activado la cuenta en el servidor: se exige
    // una nueva selección confirmada, sin mostrar datos bajo una identidad dudosa.
    resetAccountData(); AGENCY_CONTEXT = true; SWITCHING = false;
    go('/agencia', {replace:true});
    toast('No se pudo confirmar el acceso. Selecciona la subcuenta para reintentar. ' + err.message);
    return false;
  } finally { SWITCHING = false; }
}
/** El nombre y el logo del cliente, que el SuperAdmin sube desde Usuarios y roles. */
async function bootEspacio() {
  try { ESPACIO = await agoraApi('workspace/branding'); }
  catch { ESPACIO = null; }
}
if (ws) ws.onChange(() => {
  if (SWITCHING || (esSuperAdmin() && AGENCY_CONTEXT)) return;
  try { AG.startWonAlert(); } catch { /* nada */ }
  if (agAccountViews.includes(R.view)) render(); else topbar();
});

/* =====================================================================================
   Módulos propios de Agora Digital App
   -------------------------------------------------------------------------------------
   Resumen (el pulso del negocio), Tareas, Auditoría y Usuarios y roles. Escritos con las
   piezas del sistema de diseño: .hero, .micro, .panel, .bars, .tw, .seg, .lt, svg.chart.

   Cada uno lee su propia ruta del servidor. Las formas de respuesta están documentadas
   en construccion/API.md; mientras el servidor no las tenga, el archivo de pruebas las
   responde con datos inventados.
   ===================================================================================== */

var DATOS = {resumen: null, dia: null, ads: null, eficacia: null, tareas: null, auditoria: null, usuarios: null};

/* ---------- Dinero del negocio: «US$ 285», «−US$ 783» ---------- */
const usd = v => {
  if (v === null || v === undefined || v === '' || !isFinite(Number(v))) return '—';
  const n = Number(v), abs = Math.abs(n);
  const cifra = Math.abs(abs % 1) > 0.004 ? NF.format(abs) : NI.format(Math.round(abs));
  return (n < 0 ? '−' : '') + 'US$ ' + cifra;
};
const firmado = v => (Number(v) > 0 ? '+' : '') + usd(v);

/* ---------- Paleta de los gráficos de reparto ---------- */
const PAL = ['var(--brand)', 'var(--gold)', 'var(--teal)', 'var(--violet)', 'var(--info)', 'var(--deep)', 'var(--muted-2)'];

/**
 * Rueda de reparto con su leyenda al lado. `items`: [{l, n}].
 * Se usa para las conversaciones sin leer por asesor, la fuente de los leads
 * y los leads asignados por usuario.
 */
function donut(items, {centro = '', rotulo = ''} = {}) {
  const lista = (items || []).filter(x => Number(x.n) > 0);
  const suma = lista.reduce((a, b) => a + Number(b.n), 0);
  if (!suma) return `<p class="empty"><b>Sin datos todavía</b>${esc(rotulo || 'No hay nada que repartir en este periodo.')}</p>`;
  const R = 52, C = 2 * Math.PI * R;
  let corrido = 0;
  const arcos = lista.map((it, i) => {
    const largo = (Number(it.n) / suma) * C;
    const arco = `<circle cx="70" cy="70" r="${R}" fill="none" stroke="${PAL[i % PAL.length]}" stroke-width="17" stroke-dasharray="${largo.toFixed(2)} ${(C - largo).toFixed(2)}" stroke-dashoffset="${(-corrido).toFixed(2)}" transform="rotate(-90 70 70)"><title>${esc(it.l)}: ${int(it.n)}</title></circle>`;
    corrido += largo;
    return arco;
  }).join('');
  return `<div class="dn">
    <svg class="dn-svg" viewBox="0 0 140 140" role="img" aria-label="${esc(rotulo || 'Reparto')}: ${esc(lista.map(x => `${x.l}, ${x.n}`).join('; '))}">
      <circle cx="70" cy="70" r="${R}" fill="none" stroke="var(--fill-3)" stroke-width="17"></circle>${arcos}
      <text x="70" y="70" class="dn-c" text-anchor="middle" dominant-baseline="central">${esc(centro || int(suma))}</text>
    </svg>
    <ul class="dn-lg">${lista.map((it, i) => `<li><i style="background:${PAL[i % PAL.length]}" aria-hidden="true"></i><span>${esc(it.l)}</span><b>${int(it.n)}</b></li>`).join('')}</ul>
  </div>`;
}

/** Línea con área, para la evolución del ingreso recurrente. `serie`: [{ym, v}]. */
function lineaArea(serie, {formato = usd} = {}) {
  const pts = (serie || []).filter(p => p && isFinite(Number(p.v)));
  if (pts.length < 2) return `<p class="empty"><b>Hace falta más historia</b>Con un solo mes todavía no hay línea que dibujar.</p>`;
  const W = 620, H = 260, L = 64, Rm = 14, T = 18, B = 34;
  const w = W - L - Rm, h = H - T - B;
  const vals = pts.map(p => Number(p.v));
  const tope = Math.max(...vals), piso = Math.min(0, ...vals);
  const esc10 = v => { const p = 10 ** Math.floor(Math.log10(v || 1)); const n = v / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p; };
  const alto = esc10(tope || 1);
  const x = i => L + (pts.length === 1 ? w / 2 : (i * w) / (pts.length - 1));
  const y = v => T + h - ((v - piso) / ((alto - piso) || 1)) * h;
  let rejilla = '';
  for (let k = 0; k <= 3; k++) {
    const v = piso + ((alto - piso) * k) / 3;
    rejilla += `<line class="gl" x1="${L}" x2="${W - Rm}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" stroke-dasharray="3 5"/><text x="${L - 10}" y="${(y(v) + 4).toFixed(1)}" text-anchor="end">${esc(formato(v))}</text>`;
  }
  const linea = pts.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)} ${y(Number(p.v)).toFixed(1)}`).join(' ');
  const area = `${linea} L${x(pts.length - 1).toFixed(1)} ${y(piso).toFixed(1)} L${x(0).toFixed(1)} ${y(piso).toFixed(1)} Z`;
  const puntos = pts.map((p, i) => `<circle class="ln-p" cx="${x(i).toFixed(1)}" cy="${y(Number(p.v)).toFixed(1)}" r="5"><title>${esc(mesNombre(p.ym))}: ${esc(formato(Number(p.v)))}</title></circle>`).join('');
  const rotulos = pts.map((p, i) => `<text x="${x(i).toFixed(1)}" y="${H - 10}" text-anchor="middle">${esc(mesCorto(p.ym))}</text>`).join('');
  return `<svg class="chart ln" viewBox="0 0 ${W} ${H}" role="img" aria-label="Evolución del ingreso recurrente: ${pts.map(p => `${mesCorto(p.ym)}, ${formato(Number(p.v))}`).join('; ')}">
    ${rejilla}<path class="ln-a" d="${area}"/><path class="ln-l" d="${linea}"/>${puntos}${rotulos}</svg>`;
}

/** Barras horizontales con la primitiva del sistema. `items`: [{l, v, texto}]. */
function barras(items) {
  const lista = items || [];
  if (!lista.length) return `<p class="empty"><b>Sin movimiento</b>No hay nada que mostrar en este periodo.</p>`;
  const tope = Math.max(1, ...lista.map(i => Number(i.v) || 0));
  return `<div class="bars">${lista.map(i => `<div class="bar-row"><span class="lab" title="${esc(i.l)}">${esc(i.l)}</span><span class="track"><span class="fill" style="width:${Math.min(100, (100 * (Number(i.v) || 0)) / tope).toFixed(1)}%"></span></span><span class="val">${esc(i.texto)}</span></div>`).join('')}</div>`;
}

/* =====================================================================================
   Resumen · el pulso del negocio
   ===================================================================================== */
const cargarResumen = async (forzar) => {
  if (!forzar && DATOS.resumen && DATOS.resumen.period === PERIODO) return DATOS.resumen;
  const d = await agoraApi(`workspace/resumen?period=${encodeURIComponent(PERIODO)}`);
  DATOS.resumen = {...d, period: PERIODO};
  return DATOS.resumen;
};

/* La pantalla de inicio la arma ahora el módulo Gestión Comercial (tablero.js);
   de aquí solo se conserva cargarResumen(), que trae la identidad del espacio. */

/* =====================================================================================
   Tareas · lo que cada ejecutivo tiene pendiente
   ===================================================================================== */
const cargarTareas = async forzar => {
  if (!forzar && DATOS.tareas) return DATOS.tareas;
  const d = await agoraApi(`workspace/tareas?period=${encodeURIComponent(PERIODO)}`);
  DATOS.tareas = Array.isArray(d.items) ? d.items : [];
  DATOS.tareasResumen = d.resumen || null;
  return DATOS.tareas;
};
const tareasPendientes = () => (DATOS.tareas || []).filter(t => t.estado !== 'hecha').length || null;
const hoyYMD = () => new Date().toISOString().slice(0, 10);
const ORIGEN = {recordatorio: 'Recordatorio de chat', manual: 'Creada a mano', sistema: 'Del sistema', convenio: 'Convenio'};

V.tareas = {
  need: () => cargarTareas(),
  render() {
    const st = state.tareas;
    const lede = 'Lo que cada ejecutivo tiene por delante. Los recordatorios que pones en un chat caen aquí solos.';
    const acts = `<button class="btn btn-cta" type="button" data-tarea-nueva>${icon('plus')}Nueva tarea</button>`;
    const cabeza = head('Seguimiento', 'Tareas', lede, acts);
    if (this.error) return cabeza + failed(this.error);
    if (!DATOS.tareas) return cabeza + loading();

    const todas = DATOS.tareas, hoy = hoyYMD();
    const abiertas = todas.filter(t => t.estado !== 'hecha');
    const vencidas = abiertas.filter(t => t.vence && t.vence < hoy);
    const deHoy = abiertas.filter(t => t.vence === hoy);
    const hechas = todas.filter(t => t.estado === 'hecha');
    const responsables = [...new Set(todas.map(t => t.responsable).filter(Boolean))];

    const q = norm(st.q);
    let filas = todas.filter(t => {
      if (st.estado === 'abiertas' && t.estado === 'hecha') return false;
      if (st.estado === 'vencidas' && !(t.estado !== 'hecha' && t.vence && t.vence < hoy)) return false;
      if (st.estado === 'hoy' && t.vence !== hoy) return false;
      if (st.estado === 'hechas' && t.estado !== 'hecha') return false;
      if (st.resp && t.responsable !== st.resp) return false;
      return !q || norm(`${t.titulo} ${t.contacto || ''} ${t.responsable || ''}`).includes(q);
    });
    filas = sortRows(filas, st);

    const cols = [
      {k: 'titulo', l: 'Tarea', sort: 1, f: (v, r) => `<span class="who-cell"><b>${esc(v)}</b><small>${esc(ORIGEN[r.origen] || 'Tarea')}${r.detalle ? ' · ' + esc(r.detalle) : ''}</small></span>`},
      {k: 'contacto', l: 'Contacto', f: (v, r) => v ? `<button class="lnk-btn" type="button" data-tarea-chat="${esc(r.conversationId || '')}" data-contacto="${esc(r.contactId || '')}">${esc(v)}</button>` : '<span class="muted">—</span>'},
      {k: 'responsable', l: 'Responsable', sort: 1, f: v => esc(v || 'Sin asignar')},
      {k: 'vence', l: 'Vence', sort: 1, f: (v, r) => {
        if (!v) return '<span class="muted">Sin fecha</span>';
        const tarde = r.estado !== 'hecha' && v < hoy;
        return `<span class="${tarde ? 'down' : ''}" title="${esc(v)}">${esc(fday(v))}${r.hora ? ' · ' + esc(r.hora) : ''}</span>`;
      }},
      {k: 'estado', l: 'Estado', f: v => v === 'hecha' ? '<span class="lt lt-ok"><i aria-hidden="true"></i>Hecha</span>' : '<span class="lt lt-warn"><i aria-hidden="true"></i>Pendiente</span>'},
      {k: 'id', l: '', cls: 'act', f: (v, r) => r.estado === 'hecha'
        ? `<button class="btn btn-sm" type="button" data-tarea-abrir="${esc(v)}">Reabrir</button>`
        : `<button class="btn btn-sm btn-tint" type="button" aria-label="Marcar hecha" data-tarea-hecha="${esc(v)}">${icon('check')}<span>Marcar hecha</span></button>`},
    ];

    return cabeza
      + `<div class="grid g4">
        ${metric('Para hoy', int(deHoy.length), deHoy.length ? 'Escribe o llama hoy mismo' : 'Nada vence hoy', {filter: 'hoy', on: st.estado === 'hoy'})}
        ${metric('Vencidas', int(vencidas.length), vencidas.length ? 'Pasó la fecha y siguen abiertas' : 'Ninguna atrasada', {filter: 'vencidas', on: st.estado === 'vencidas'})}
        ${metric('Abiertas', int(abiertas.length), `${int(todas.length)} en total`, {filter: 'abiertas', on: st.estado === 'abiertas'})}
        ${metric('Hechas', int(hechas.length), 'En este periodo', {filter: 'hechas', on: st.estado === 'hechas'})}
      </div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Tarea, contacto o responsable')}
          <label class="fld">Responsable<select data-f="resp"><option value="">Todos</option>${responsables.map(r => `<option value="${esc(r)}" ${st.resp === r ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select></label>
          ${st.q || st.resp || st.estado ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
        ${table(cols, filas, {sortKey: st.sk, sortDir: st.sd, empty: '<b>No hay tareas con estos filtros</b>Prueba con otro responsable o quita los filtros.'})}
        <p class="src">Los recordatorios que pones desde un chat aparecen aquí y dejan la conversación de primera en la bandeja hasta que escribes.</p></section>`;
  },
};

function tareaModal(previa) {
  const hoy = hoyYMD();
  const s = openModal(modalHead(previa?.titulo ? 'Editar tarea' : 'Nueva tarea', 'Queda a nombre de quien la tiene que hacer, con su fecha.') + `<form class="form" novalidate>
    <label class="fld">¿Qué hay que hacer?<input name="titulo" value="${esc(previa?.titulo || '')}" autofocus placeholder="Llamar para confirmar la inscripción"></label>
    <label class="fld">Detalle (opcional)<input name="detalle" value="${esc(previa?.detalle || '')}" placeholder="Quedó en responder después de hablar con su esposo"></label>
    <div class="grid g2">
      <label class="fld">Fecha<input name="vence" type="date" value="${esc(previa?.vence || hoy)}" min="${hoy}"></label>
      <label class="fld">Hora<input name="hora" type="time" value="${esc(previa?.hora || '09:00')}"></label>
    </div>
    <label class="fld">Responsable<input name="responsable" value="${esc(previa?.responsable || quienSoy())}"></label>
    <p class="fld-err" role="alert"></p>
    <div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Guardar tarea</button></div></form>`);
  const f = s.querySelector('form');
  f.onsubmit = async ev => {
    ev.preventDefault();
    const fd = Object.fromEntries(new FormData(f)), err = f.querySelector('.fld-err');
    if (!fd.titulo.trim()) { err.textContent = 'Escribe qué hay que hacer.'; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      await agoraApi('workspace/tareas', {method: 'POST', body: {...fd, origen: previa?.origen || 'manual', contactId: previa?.contactId || null, conversationId: previa?.conversationId || null}});
      closeModal(); await cargarTareas(true); repaint(); toast('Tarea guardada.');
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
async function cambiarTarea(id, estado) {
  try { await agoraApi(`workspace/tareas/${encodeURIComponent(id)}/estado`, {method: 'POST', body: {estado}}); await cargarTareas(true); repaint(); toast(estado === 'hecha' ? 'Tarea marcada como hecha.' : 'Tarea reabierta.'); }
  catch (x) { toast(x.message); }
}

/* =====================================================================================
   Auditoría · la trazabilidad
   ===================================================================================== */
const MODULOS_AUD = {contacto: 'Contactos', conversacion: 'Conversaciones', oportunidad: 'Oportunidades', convenio: 'Contratos', usuario: 'Usuarios', ajuste: 'Ajustes', sesion: 'Acceso'};
const ACCION_TONO = {creo: 'ok', edito: 'info', elimino: 'bad', envio: 'info', firmo: 'ok', entro: 'gray', salio: 'gray', asigno: 'info', exporto: 'warn', bloqueo: 'bad'};
const cargarAuditoria = async forzar => {
  if (!forzar && DATOS.auditoria) return DATOS.auditoria;
  const st = state.auditoria;
  const d = await agoraApi(`workspace/auditoria?period=${encodeURIComponent(PERIODO)}${st.modulo ? '&modulo=' + encodeURIComponent(st.modulo) : ''}`);
  DATOS.auditoria = Array.isArray(d.items) ? d.items : [];
  DATOS.auditoriaResumen = d.resumen || null;
  return DATOS.auditoria;
};

V.auditoria = {
  need: () => cargarAuditoria(),
  render() {
    const st = state.auditoria;
    const lede = 'Quién tocó cada contacto, qué cambió, cuándo y desde dónde. Nada se borra de aquí.';
    const acts = `<button class="btn" type="button" data-aud-exportar>${icon('download')}Exportar</button><button class="btn" type="button" data-aud-recargar>${icon('refresh')}Actualizar</button>`;
    const cabeza = head('Trazabilidad', 'Auditoría', lede, acts);
    if (this.error) return cabeza + failed(this.error);
    if (!DATOS.auditoria) return cabeza + loading();

    const todos = DATOS.auditoria, hoy = new Date().toDateString();
    const deHoy = todos.filter(e => utc(e.at)?.toDateString() === hoy);
    const contactos = new Set(todos.filter(e => e.contactId).map(e => e.contactId));
    const actores = [...new Set(todos.map(e => e.actor).filter(Boolean))];
    const sensibles = todos.filter(e => ['elimino', 'exporto', 'bloqueo'].includes(e.accion));

    const q = norm(st.q);
    const filas = todos.filter(e => (!st.modulo || e.modulo === st.modulo) && (!st.actor || e.actor === st.actor)
      && (!q || norm(`${e.actor} ${e.objeto || ''} ${e.contacto || ''} ${e.detalle || ''}`).includes(q)));
    const pagina = filas.slice(st.p * 50, st.p * 50 + 50);

    const cols = [
      {k: 'at', l: 'Cuándo', f: v => `<span class="who-cell"><b>${esc(when(v))}</b><small>${esc(fstamp(v))}</small></span>`},
      {k: 'actor', l: 'Quién', f: (v, r) => `<span class="who-cell"><b>${esc(v)}</b><small>${esc(r.rol || '')}</small></span>`},
      {k: 'accion', l: 'Qué hizo', f: (v, r) => `<span class="sc sc-${ACCION_TONO[v] || 'gray'}">${esc(r.accionTexto || v)}</span>`},
      {k: 'modulo', l: 'Módulo', f: v => esc(MODULOS_AUD[v] || v)},
      {k: 'objeto', l: 'Sobre qué', f: (v, r) => r.contacto
        ? `<span class="who-cell"><b>${esc(r.contacto)}</b><small>${esc(v || '')}</small></span>`
        : `<span>${esc(v || '—')}</span>`},
      {k: 'id', l: '', cls: 'act', f: (v, r) => `<button class="btn btn-sm" type="button" data-aud="${esc(v)}">Ver</button>`},
    ];

    return cabeza
      + `<div class="grid g4">
        ${metric('Movimientos hoy', int(deHoy.length), `${int(todos.length)} en ${esc(mesNombre(PERIODO).toLowerCase())}`)}
        ${metric('Contactos tocados', int(contactos.size), 'Fichas con algún cambio')}
        ${metric('Personas activas', int(actores.length), 'Dejaron rastro en el periodo')}
        ${metric('Acciones delicadas', int(sensibles.length), 'Borrados, exportaciones y bloqueos')}
      </div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Persona, contacto o detalle')}
          <label class="fld">Módulo<select data-f="modulo"><option value="">Todos</option>${Object.keys(MODULOS_AUD).map(m => `<option value="${m}" ${st.modulo === m ? 'selected' : ''}>${esc(MODULOS_AUD[m])}</option>`).join('')}</select></label>
          <label class="fld">Persona<select data-f="actor"><option value="">Todas</option>${actores.map(a => `<option value="${esc(a)}" ${st.actor === a ? 'selected' : ''}>${esc(a)}</option>`).join('')}</select></label>
          ${st.q || st.modulo || st.actor ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
        ${table(cols, pagina, {empty: '<b>No hay movimientos con estos filtros</b>Prueba con otro módulo o con otra persona.'})}${pager(st, filas.length, 50)}
        <p class="src">El registro se guarda completo en el servidor; aquí se muestran los del periodo elegido.</p></section>`;
  },
};

function auditoriaDetalle(id) {
  const e = (DATOS.auditoria || []).find(x => String(x.id) === String(id));
  if (!e) return;
  const cambios = e.antes || e.despues
    ? `<h4 class="h4">Qué cambió</h4><div class="tw"><table><thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody>${
      Object.keys({...(e.antes || {}), ...(e.despues || {})}).map(k => `<tr><td>${esc(k)}</td><td class="muted">${esc(String((e.antes || {})[k] ?? '—'))}</td><td><b class="strong">${esc(String((e.despues || {})[k] ?? '—'))}</b></td></tr>`).join('')
    }</tbody></table></div>` : '';
  openModal(modalHead(esc(e.accionTexto || e.accion), esc(fstamp(e.at)))
    + kv([
      ['Persona', `${esc(e.actor)}${e.rol ? ' · ' + esc(e.rol) : ''}`],
      ['Módulo', esc(MODULOS_AUD[e.modulo] || e.modulo)],
      ['Sobre qué', esc(e.objeto || '—')],
      ['Contacto', e.contacto ? esc(e.contacto) : '—'],
      ['Desde dónde', `${esc(e.dispositivo || 'Desconocido')}${e.ip ? ' · ' + esc(e.ip) : ''}`],
      ['Origen', esc(e.origen === 'ghl' ? 'Go High Level' : e.origen || 'Sistema')],
    ])
    + cambios
    + `<div class="form-ft">${e.contactId ? `<button class="btn btn-tint" type="button" data-aud-contacto="${esc(e.contactId)}">${icon('user')}Abrir la ficha</button>` : ''}<button class="btn" type="button" data-close>Cerrar</button></div>`);
}

/* =====================================================================================
   Usuarios y roles
   ===================================================================================== */
const ROLES_SISTEMA = {
  SUPERADMIN: {l: 'SuperAdmin', d: 'Todo el sistema: espacios, identidad, usuarios y cifras del negocio.', c: 'var(--mod-seguridad)'},
  ADMIN: {l: 'Administración', d: 'Todo lo de su espacio: conexiones, equipo, convenios y tablero.', c: 'var(--brand)'},
  SUPERVISOR: {l: 'Supervisión', d: 'Ve a todo el equipo, reasigna conversaciones y revisa la auditoría.', c: 'var(--mod-azul)'},
  EJECUTIVO: {l: 'Ejecutivo', d: 'Atiende sus conversaciones, sus contactos y sus convenios.', c: 'var(--mod-oro)'},
  CONSULTA: {l: 'Consulta', d: 'Solo mira lo que tiene asignado. No cambia nada.', c: 'var(--muted-2)'},
};
const PERMISOS_MATRIZ = [
  ['resumen.ver', 'Ver el Resumen del negocio', ['SUPERADMIN']],
  ['espacios.administrar', 'Agregar espacios y subir su logo', ['SUPERADMIN']],
  ['usuarios.administrar', 'Crear usuarios y cambiar roles', ['SUPERADMIN', 'ADMIN']],
  ['ajustes.editar', 'Conectar cuentas y editar ajustes', ['SUPERADMIN', 'ADMIN']],
  ['auditoria.ver', 'Ver la auditoría', ['SUPERADMIN', 'ADMIN', 'SUPERVISOR']],
  ['equipo.metricas', 'Ver las cifras de todo el equipo', ['SUPERADMIN', 'ADMIN', 'SUPERVISOR']],
  ['conversaciones.reasignar', 'Reasignar conversaciones', ['SUPERADMIN', 'ADMIN', 'SUPERVISOR']],
  ['conversaciones.atender', 'Atender conversaciones y contactos', ['SUPERADMIN', 'ADMIN', 'SUPERVISOR', 'EJECUTIVO']],
  ['convenios.enviar', 'Crear y enviar convenios', ['SUPERADMIN', 'ADMIN', 'EJECUTIVO']],
  ['datos.consultar', 'Solo consultar', ['SUPERADMIN', 'ADMIN', 'SUPERVISOR', 'EJECUTIVO', 'CONSULTA']],
  ['subcuentas.cambiar', 'Cambiar entre subcuentas', ['SUPERADMIN']],
];
const PERMISOS_RESERVADOS = new Set(['subcuentas.cambiar']);
var PERMISOS_CONFIG = null;
async function cargarPermisos(forzar = false) {
  if (PERMISOS_CONFIG && !forzar) return PERMISOS_CONFIG;
  const d = await agoraApi('workspace/permisos');
  PERMISOS_CONFIG = d.roles || {};
  return PERMISOS_CONFIG;
}
function permisoRol(rol, clave) {
  if (rol === 'SUPERADMIN') return true;
  if (PERMISOS_RESERVADOS.has(clave)) return false;
  const inicial = PERMISOS_MATRIZ.find(p => p[0] === clave)?.[2] || [];
  return PERMISOS_CONFIG?.[rol]?.[clave] ?? inicial.includes(rol);
}
function permisosUsuarioModal(u) {
  if (!esSuperAdmin() && !esAdmin()) return;
  const rol = u.rol;
  const body = PERMISOS_MATRIZ.map(([clave, titulo]) => {
    const reserved = PERMISOS_RESERVADOS.has(clave) || rol === 'SUPERADMIN';
    const actual = u.permisos?.[clave];
    return `<label class="perm-persona"><span>${esc(titulo)}</span><select data-override="${esc(clave)}" ${reserved ? 'disabled' : ''}>
       <option value="inherit" ${actual === undefined ? 'selected' : ''}>Heredar rol</option>
       <option value="allow" ${!PERMISOS_MATRIZ.find(p=>p[0]===clave)?.[2].includes(rol)?'disabled':''} ${actual === true ? 'selected' : ''}>Permitir</option>
       <option value="deny" ${actual === false ? 'selected' : ''}>Denegar</option></select></label>`;
  }).join('');
  const s = openModal(modalHead('Permisos individuales', esc(u.nombre) + ' · ' + esc(ROLES_SISTEMA[rol]?.l || rol)) +
    `<form class="form"><p class="dialog-copy">Las excepciones se aplican a esta persona y subcuenta dentro del alcance de su rol.</p>${body}<p class="fld-err" role="alert"></p><div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">Guardar permisos</button></div></form>`);
  const f = s.querySelector('form');
  f.onsubmit = async ev => {
    ev.preventDefault(); const permisos = {};
    f.querySelectorAll('[data-override]').forEach(sel => { if (!sel.disabled && sel.value !== 'inherit') permisos[sel.dataset.override] = sel.value === 'allow'; });
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try { await agoraApi('workspace/usuarios/permisos', {method:'POST', body:{id:u.id, permisos}}); await cargarUsuarios(true); closeModal(); repaint(); toast('Permisos individuales actualizados.'); }
    catch(x) { b.disabled = false; f.querySelector('.fld-err').textContent = x.message; }
  };
}
document.addEventListener('change', e => {
  if (e.target.matches?.('#matriz-permisos [data-perm-rol]')) {
    const c = e.target, rol = c.dataset.permRol, clave = c.dataset.permClave;
    if (!esSuperAdmin() || rol === 'SUPERADMIN' || PERMISOS_RESERVADOS.has(clave)) { c.checked = permisoRol(rol, clave); return; }
    PERMISOS_CONFIG ||= {}; PERMISOS_CONFIG[rol] ||= {}; PERMISOS_CONFIG[rol][clave] = c.checked;
    document.querySelector('#permisos-guardar')?.removeAttribute('disabled');
  }
});
document.addEventListener('click', async e => {
  if (e.target.id !== 'permisos-guardar' || !esSuperAdmin()) return;
  const b = e.target; busy(b, true, 'Guardando…');
  try { const roles = Object.fromEntries(Object.entries(PERMISOS_CONFIG || {}).filter(([r]) => r !== 'SUPERADMIN').map(([r,ps]) => [r,Object.fromEntries(Object.entries(ps || {}).filter(([k]) => !PERMISOS_RESERVADOS.has(k)))])); await agoraApi('workspace/permisos', {method:'PUT', body:{roles}}); b.disabled = true; toast('Permisos por rol guardados.'); }
  catch(x) { b.disabled = false; toast('Error: ' + x.message); }
});


let USER_PAGE={q:'',cursor:'',next:null,total:0,tenant:null};
const cargarUsuarios = async forzar => {
  if(USER_PAGE.tenant!==ws.current()){USER_PAGE={q:'',cursor:'',next:null,total:0,tenant:ws.current()};DATOS.usuarios=null;}
  if (!forzar && DATOS.usuarios) return DATOS.usuarios;
  const d = await agoraApi('workspace/usuarios'+(!window.__DEMO__?'?q='+encodeURIComponent(USER_PAGE.q)+'&cursor='+encodeURIComponent(USER_PAGE.cursor):''));
  USER_PAGE.next=d.nextCursor;USER_PAGE.total=d.total??d.items?.length??0;
  DATOS.usuarios = Array.isArray(d.items) ? d.items : [];
  return DATOS.usuarios;
};

V.usuarios = {
  need: () => Promise.all([cargarUsuarios(), cargarPermisos(), ESPACIO ? Promise.resolve() : bootEspacio()]),
  render() {
    const st = state.usuarios;
    const lede = 'Quién entra al sistema, con qué rol y qué puede hacer. La identidad de la empresa se administra desde Ajustes.';
    const acts = `${esSuperAdmin() ? `<button class="btn" type="button" data-agencia-panel>${icon('briefcase')}Cuenta de agencia</button>` : ''}${esAdmin() ? `<button class="btn btn-cta" type="button" data-usuario-nuevo>${icon('plus')}Agregar persona</button>` : ''}`;
    const cabeza = head('Acceso', 'Usuarios y roles', lede, acts);
    if (this.error) return cabeza + failed(this.error);
    if (!DATOS.usuarios) return cabeza + loading();

    const todos = DATOS.usuarios, activos = todos.filter(u => u.estado !== 'inactivo');
    const q = norm(st.q);
    const filas = todos.filter(u => (!st.rol || u.rol === st.rol) && (!q || norm(`${u.nombre} ${u.correo}`).includes(q)));

    const cols = [
      {k: 'nombre', l: 'Persona', f: (v, r) => `<span class="who-cell"><b>${esc(v)}${r.yo ? ' <span class="sc sc-gray">Tú</span>' : ''}</b><small>${esc(r.correo)}</small></span>`},
      {k: 'rol', l: 'Rol', f: v => `<span class="rol-chip" style="--c:${ROLES_SISTEMA[v]?.c || 'var(--muted)'}">${esc(ROLES_SISTEMA[v]?.l || v)}</span>`},
      {k: 'origen', l: 'De dónde', f: v => esc(v === 'ghl' ? 'Equipo de Go High Level' : v === 'contratos' ? 'Panel de Contratos' : 'Del sistema')},
      {k: 'ultimoAcceso', l: 'Último acceso', f: v => v ? `<span title="${esc(fstamp(v))}">${esc(when(v))}</span>` : '<span class="muted">Nunca</span>'},
      {k: 'estado', l: 'Estado', f: v => v === 'inactivo' ? '<span class="lt lt-bad"><i aria-hidden="true"></i>Desactivada</span>' : '<span class="lt lt-ok"><i aria-hidden="true"></i>Activa</span>'},
      {k: 'id', l: '', cls: 'act', f: (v, r) => esAdmin() ? `<button class="btn btn-sm" type="button" data-usuario="${esc(v)}" aria-haspopup="true">${icon('more')}<span>Opciones</span></button>` : ''},
    ];


    const matriz = panel('Qué puede hacer cada rol', 'Puedes restringir permisos del rol. Para ampliar su alcance, cambia el rol del usuario.',
      `<div id="matriz-permisos"><div class="tw full"><table class="matriz"><thead><tr><th>Puede</th>${Object.keys(ROLES_SISTEMA).map(r => `<th class="n">${esc(ROLES_SISTEMA[r].l)}</th>`).join('')}</tr></thead>
        <tbody>${PERMISOS_MATRIZ.map(([clave, titulo]) => `<tr><td>${esc(titulo)}</td>${Object.keys(ROLES_SISTEMA).map(r => {
          const bloqueado = r === 'SUPERADMIN' || PERMISOS_RESERVADOS.has(clave) || !esSuperAdmin();
          return `<td class="n"><input type="checkbox" aria-label="${esc(titulo)} para ${esc(ROLES_SISTEMA[r].l)}" data-perm-rol="${esc(r)}" data-perm-clave="${esc(clave)}" ${permisoRol(r, clave) ? 'checked' : ''} ${bloqueado ? 'disabled' : ''}></td>`;
        }).join('')}</tr>`).join('')}</tbody></table></div>${esSuperAdmin() ? '<div class="form-ft"><button class="btn btn-cta" type="button" id="permisos-guardar" disabled>Guardar cambios de roles</button></div>' : ''}</div>`);

    return cabeza
      + `<div class="grid g4">
        ${metric('Personas activas', int(activos.length), `${int(todos.length - activos.length)} desactivada${todos.length - activos.length === 1 ? '' : 's'}`)}
        ${['SUPERADMIN', 'ADMIN', 'EJECUTIVO'].map(r => metric(ROLES_SISTEMA[r].l, int(activos.filter(u => u.rol === r).length), ROLES_SISTEMA[r].d, {filter: r, on: st.rol === r})).join('')}
      </div>`
      + `<section class="panel mt"><div class="tools">${searchFld(st.q, 'Nombre o correo')}${st.q || st.rol ? '<button class="btn clear" type="button" data-clear>Quitar filtros</button>' : ''}</div>
        ${table(cols, filas, {empty: '<b>Nadie coincide</b>Prueba con otro nombre o quita los filtros.'})}</section>`
      + `<div class="mt">${matriz}</div>`;
  },
};



/* Identidad editorial: se gestiona en Ajustes, no en el tablero ni en Usuarios. */
function editorIdentidadEspacio() {
  if (!esSuperAdmin()) return '';
  const nombre = ESPACIO?.nombre || '';
  const prev = ESPACIO?.logo ? `<img src="${esc(ESPACIO.logo)}" alt="Logo de ${esc(nombre || 'la empresa')}">` : esc((nombre || 'E').slice(0, 1).toUpperCase());
  /* 2.1.11 · Mismo patrón que "Datos de la empresa": cabecera secH + recuadros. */
  return `<section class="panel pf-sec ident-sec">${secH('briefcase', 'Identidad del espacio', 'El logo, el nombre y los colores que verá el cliente en su subcuenta, junto al logo de Ágora.')}
    <form class="bx-form ident-f" novalidate>
      <div class="bx-grid">
        <fieldset class="bx">
          <legend>${icon('upload')}Logo de la empresa</legend>
          <div class="bx-logo">
            <div class="bx-logo-prev ident-prev">${prev}</div>
            <div class="bx-logo-acts">
              <label class="btn btn-sm">${icon('upload')}${ESPACIO?.logo ? 'Cambiar logo' : 'Subir logo'}<input name="logo" type="file" accept="image/png,.png"></label>
              ${ESPACIO?.logo ? '<button class="btn btn-sm" type="button" data-logo-quitar>Quitar</button>' : ''}
              <span class="fld-feedback ident-logo-fb" role="status"></span>
              <p class="bx-help">${LOGO_AYUDA}</p>
            </div>
          </div>
        </fieldset>
        <fieldset class="bx">
          <legend>${icon('edit')}Nombres</legend>
          <label class="fld">Nombre de la empresa<input name="nombre" maxlength="120" required value="${esc(nombre)}" placeholder="Nombre comercial"></label>
          <label class="fld">Nombre de la subcuenta<input name="subcuenta" maxlength="120" required value="${esc(ESPACIO?.subcuenta || DATOS.resumen?.espacios?.[0]?.subcuenta || ws?.name() || '')}" placeholder="Nombre visible de la subcuenta"></label>
        </fieldset>
        ${paletaBx(ESPACIO?.paleta)}
      </div>
      <p class="fld-err" role="alert"></p>
      <div class="bx-foot"><p class="bx-help">Estos datos solo se usan dentro del espacio del cliente; la marca del sistema sigue siendo Ágora.</p><div class="acts"><button class="btn btn-cta" type="submit">Guardar identidad</button></div></div>
    </form>
  </section>`;
}
/* Cuenta de agencia: panel transversal visible solo para SuperAdmin. */
async function agenciaModal() {
  if (!esSuperAdmin()) return;
  let response;
  try { response = await agoraApi('agency/accounts'); } catch(e) { toast('No se pudieron cargar las subcuentas: '+e.message); return; }
  const accounts = response.items || [];
  const rows = accounts.map(a => `<tr><td><b>${esc(a.nombre || a.name || '')}</b><small style="display:block">${esc(a.subcuenta || a.name || '')}</small></td><td>${esc(a.estado || 'activa')}</td><td><button type="button" class="btn btn-sm" data-agency-edit="${esc(a.id)}">Editar</button> <button type="button" class="btn btn-sm" data-agency-users="${esc(a.id)}">Usuarios</button></td></tr>`).join('');
  const s = openModal(modalHead('Cuenta de agencia', 'Administración global de clientes y subcuentas. Solo usuarios SuperAdmin de la agencia.') +
   `<div class="form"><div class="form-ft" style="justify-content:flex-start"><button class="btn btn-cta" type="button" id="agency-new">${icon('plus')}Crear subcuenta</button><button class="btn" type="button" id="agency-new-user">${icon('user')}Crear usuario</button></div>
   <div class="tw full"><table class="matriz"><thead><tr><th>Empresa / Subcuenta</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${rows || '<tr><td colspan="3">Todavía no hay subcuentas.</td></tr>'}</tbody></table></div>
   <p class="src">La gestión de agencia requiere permisos verificados por el servidor y aislamiento entre clientes.</p><div class="form-ft"><button class="btn" type="button" data-close>Cerrar</button></div></div>`);
  s.querySelector('#agency-new').onclick = () => agenciaCuentaEditar(null);
  s.querySelector('#agency-new-user').onclick = () => agenciaUsuarioNuevo(accounts);
  s.querySelectorAll('[data-agency-edit]').forEach(b => b.onclick = () => agenciaCuentaEditar(accounts.find(a => String(a.id)===String(b.dataset.agencyEdit))));
  s.querySelectorAll('[data-agency-users]').forEach(b => b.onclick = () => agenciaUsuarios(b.dataset.agencyUsers, accounts));
}
function agenciaCuentaEditar(a) {
  if (!esSuperAdmin()) return;
  const rubros=['Educación','Telecomunicaciones / ISP','Servicios profesionales','Comercio','Salud','Inmobiliario','Otro'];
  const est = a?.estado || 'activa';
  /* 2.1.11 · Editar datos de la empresa con el patrón de recuadros (.bx): Identidad, Clasificación,
     Licencia (solo al crear; luego vive en Cobros y fecha de corte) y Notas. Todo cabe en una pantalla. */
  const s = openModal(modalHead(a ? 'Datos de la empresa' : 'Nueva empresa y subcuenta', a ? 'Identidad y clasificación de esta subcuenta. Los cobros y la fecha de corte se editan en Cobros.' : 'Cada empresa tiene su propia identidad, módulos, datos y configuración.') +
    `<form class="bx-form" id="agency-identity-form" novalidate>
     <div class="bx-grid">
      <fieldset class="bx">
        <legend>${icon('briefcase')}Identidad</legend>
        <div class="bx-logo">
          <div class="bx-logo-prev" id="agency-logo-preview">${agenciaLogoHtml(a || {nombre:'Empresa'})}</div>
          <div class="bx-logo-acts">
            <label class="btn btn-sm">${icon('upload')}${a?.logo ? 'Cambiar logo' : 'Subir logo'}<input type="file" name="logoFile" accept="image/png,.png"></label>
            ${a?.logo ? `<label class="bx-check"><input type="checkbox" name="quitarLogo"> Quitar logo</label>` : `<input type="checkbox" name="quitarLogo" hidden>`}
            <span class="fld-feedback" id="fb-logo" role="status"></span>
            <p class="bx-help">${LOGO_AYUDA}</p>
          </div>
        </div>
        <label class="fld"><span class="fld-l">Nombre de la empresa <span class="req" aria-hidden="true">*</span></span>
          <input name="nombre" required maxlength="120" autocomplete="off" placeholder="Ej: NovaTech Soluciones" value="${esc(a?.nombre || '')}">
          <span class="fld-feedback" id="fb-nombre" role="status"></span>
        </label>
        <label class="fld"><span class="fld-l">Nombre de la subcuenta <span class="req" aria-hidden="true">*</span></span>
          <input name="subcuenta" required maxlength="120" autocomplete="off" placeholder="Ej: NovaTech · Operaciones" value="${esc(a?.subcuenta || a?.name || '')}">
          <span class="fld-feedback" id="fb-subcuenta" role="status"></span>
        </label>
      </fieldset>
      <fieldset class="bx">
        <legend>${icon('gear')}Clasificación</legend>
        <label class="fld"><span class="fld-l">Código interno <span class="req" aria-hidden="true">*</span></span>
          <input name="codigo" required maxlength="14" autocomplete="off" placeholder="Ej: NOV-OPS" class="mono-in" value="${esc(a?.codigo || (a?.nombre ? a.nombre.replace(/[^A-Za-z0-9]/g,'').slice(0,3).toUpperCase() + '-01' : ''))}">
          <span class="fld-hint">Solo lo ve la agencia. Ej.: NOV-OPS o STR-01.</span>
          <span class="fld-feedback" id="fb-codigo" role="status"></span>
        </label>
        <label class="fld">Rubro<select name="rubro">${rubros.map(x=>`<option value="${esc(x)}" ${x===(a?.rubro||'Otro')?'selected':''}>${esc(x)}</option>`).join('')}</select></label>
        <div class="bx-row">
          <label class="fld">Moneda<select name="moneda">${['USD','VES','EUR'].map(x=>`<option ${x===(a?.moneda||'USD')?'selected':''}>${x}</option>`).join('')}</select></label>
          <label class="fld">Estado<select name="estado"><option value="activa" ${est==='activa'?'selected':''}>Activa</option><option value="inactiva" ${est==='inactiva'?'selected':''}>Inactiva</option><option value="pendiente" ${est==='pendiente'?'selected':''}>Pendiente</option></select></label>
        </div>
      </fieldset>
      ${a ? '' : `<fieldset class="bx">
        <legend>${icon('calendar')}Licencia</legend>
        <div class="bx-row">
          <label class="fld">Plan<input name="plan" maxlength="80" autocomplete="off" placeholder="Ej: Profesional anual"></label>
          <label class="fld">Fecha de corte<input type="date" name="vence"></label>
        </div>
        <p class="bx-help">Opcional. Luego se ajusta en Cobros y fecha de corte.</p>
      </fieldset>`}
      <fieldset class="bx ${a ? 'bx-wide' : ''}">
        <legend>${icon('edit')}Notas internas</legend>
        <label class="fld"><span class="sr-only">Notas internas</span><textarea name="notas" maxlength="500" rows="2" placeholder="Solo las ve la agencia">${esc(a?.notas||'')}</textarea></label>
      </fieldset>
      ${perfilBx(a?.perfil, true)}
      ${paletaBx(a?.paleta)}
     </div>
     <p class="fld-err" id="agency-form-global-err" role="alert"></p>
     <div class="bx-foot"><p class="bx-help">El tipo de cuenta arma el tablero inicial. La subcuenta puede ajustarlo después en Personalizar.</p><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${a ? 'Guardar cambios' : 'Crear subcuenta'}</button></div></div></form>`);

  const f = s.querySelector('#agency-identity-form');
  paletaConectar(f);
  perfilConectar(f);
  const globalErr = f.querySelector('#agency-form-global-err');
  const fbNombre = f.querySelector('#fb-nombre');
  const fbSubcuenta = f.querySelector('#fb-subcuenta');
  const fbCodigo = f.querySelector('#fb-codigo');
  const fbLogo = f.querySelector('#fb-logo');

  const CODIGO_REGEX = /^[A-Z0-9]{2,6}(-[A-Z0-9]{2,6})?$/;

  const setFieldStatus = (input, feedbackEl, isValid, msg) => {
    if (isValid === null) {
      input.classList.remove('is-invalid', 'is-valid');
      if (feedbackEl) { feedbackEl.className = 'fld-feedback'; feedbackEl.textContent = ''; }
      return;
    }
    if (isValid) {
      input.classList.remove('is-invalid');
      input.classList.add('is-valid');
      if (feedbackEl) {
        feedbackEl.className = 'fld-feedback ok';
        feedbackEl.textContent = msg || '✓ Correcto';
      }
    } else {
      input.classList.remove('is-valid');
      input.classList.add('is-invalid');
      if (feedbackEl) {
        feedbackEl.className = 'fld-feedback err';
        feedbackEl.textContent = msg || 'Campo inválido';
      }
    }
  };

  const validateNombre = (touched = false) => {
    const val = f.nombre.value.trim();
    if (!val) {
      if (touched) setFieldStatus(f.nombre, fbNombre, false, 'El nombre de la empresa es obligatorio.');
      return false;
    }
    if (val.length < 3) {
      setFieldStatus(f.nombre, fbNombre, false, `Mínimo 3 caracteres (actual: ${val.length}).`);
      return false;
    }
    setFieldStatus(f.nombre, fbNombre, true, '✓ Nombre de empresa válido');
    return true;
  };

  const validateSubcuenta = (touched = false) => {
    const val = f.subcuenta.value.trim();
    if (!val) {
      if (touched) setFieldStatus(f.subcuenta, fbSubcuenta, false, 'El nombre de la subcuenta es obligatorio.');
      return false;
    }
    if (val.length < 3) {
      setFieldStatus(f.subcuenta, fbSubcuenta, false, `Mínimo 3 caracteres (actual: ${val.length}).`);
      return false;
    }
    setFieldStatus(f.subcuenta, fbSubcuenta, true, '✓ Nombre de subcuenta válido');
    return true;
  };

  const validateCodigo = (touched = false) => {
    f.codigo.value = f.codigo.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    const val = f.codigo.value.trim();
    if (!val) {
      if (touched) setFieldStatus(f.codigo, fbCodigo, false, 'El código corporativo es obligatorio.');
      return false;
    }
    if (!CODIGO_REGEX.test(val)) {
      setFieldStatus(f.codigo, fbCodigo, false, 'Formato inválido. Usa 2 a 6 letras/números, guion opcional y 2 a 6 letras/números (ej: NOV-OPS o STR-01).');
      return false;
    }
    setFieldStatus(f.codigo, fbCodigo, true, `✓ Código corporativo válido (${val})`);
    return true;
  };

  f.nombre.addEventListener('input', () => { validateNombre(false); if (globalErr) globalErr.textContent = ''; });
  f.nombre.addEventListener('blur', () => validateNombre(true));

  f.subcuenta.addEventListener('input', () => { validateSubcuenta(false); if (globalErr) globalErr.textContent = ''; });
  f.subcuenta.addEventListener('blur', () => validateSubcuenta(true));

  f.codigo.addEventListener('input', () => { validateCodigo(false); if (globalErr) globalErr.textContent = ''; });
  f.codigo.addEventListener('blur', () => validateCodigo(true));


  if (a) {
    if (f.nombre.value) validateNombre(false);
    if (f.subcuenta.value) validateSubcuenta(false);
    if (f.codigo.value) validateCodigo(false);
  }

  f.logoFile.addEventListener('change', async () => {
    const file = f.logoFile.files?.[0];
    if (!file) {
      setFieldStatus(f.logoFile, fbLogo, null, '');
      return;
    }
    try {
      const r = await logoPng(file);
      f.__logoPng = {file, data: r.data};
      f.querySelector('#agency-logo-preview').innerHTML = agenciaLogoHtml({ nombre: f.nombre.value || 'Empresa', logo: r.data });
      f.quitarLogo.checked = false;
      setFieldStatus(f.logoFile, fbLogo, true, '✓ ' + logoNota(r));
      if (globalErr) globalErr.textContent = '';
    } catch (x) {
      f.logoFile.value = ''; f.__logoPng = null;
      setFieldStatus(f.logoFile, fbLogo, false, x.message);
    }
  });

  f.quitarLogo.addEventListener('change', () => {
    if (f.quitarLogo.checked) {
      f.querySelector('#agency-logo-preview').innerHTML = agenciaLogoHtml({ nombre: f.nombre.value || 'Empresa' });
      setFieldStatus(f.logoFile, fbLogo, null, '');
    }
  });

  f.onsubmit = async e => {
    e.preventDefault();
    if (globalErr) globalErr.textContent = '';

    const validN = validateNombre(true);
    const validS = validateSubcuenta(true);
    const validC = validateCodigo(true);

    if (!validN || !validS || !validC) {
      if (globalErr) globalErr.textContent = 'Por favor corrige los campos señalados en rojo antes de guardar.';
      f.classList.remove('shake');
      void f.offsetWidth;
      f.classList.add('shake');
      const firstInvalid = f.querySelector('.is-invalid');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    const perfilR = perfilLeer(f);
    if (perfilR.error) { if (globalErr) globalErr.textContent = perfilR.error; return; }
    const nombre = f.nombre.value.trim();
    const subcuenta = f.subcuenta.value.trim();
    const codigo = f.codigo.value.trim().toUpperCase();

    const submitBtn = f.querySelector('[type=submit]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Guardando…';
    }

    try {
      let logo = f.quitarLogo.checked ? null : a?.logo || null;
      if (f.logoFile.files?.[0] && !f.quitarLogo.checked) logo = f.__logoPng?.file === f.logoFile.files[0] ? f.__logoPng.data : (await logoPng(f.logoFile.files[0])).data;
      let paleta = null;
      try { paleta = paletaLimpia(JSON.parse(f.elements.namedItem('paleta')?.value || 'null')); } catch { paleta = null; }
      if (paleta && paleta.principal === PALETAS[0][2] && paleta.acento === PALETAS[0][3]) paleta = null;
      const payload = {
        ...(a ? { id: a.id } : {}),
        nombre,
        subcuenta,
        codigo,
        rubro: f.rubro.value,
        moneda: f.moneda.value,
        estado: f.estado.value,
        notas: f.notas.value.trim(),
        ...(f.plan ? { plan: f.plan.value.trim(), vence: f.vence.value || '' } : {}),
        logo,
        paleta,
        perfil: perfilR.perfil
      };
      await agoraApi('agency/accounts', { method: a ? 'PUT' : 'POST', body: payload });
      closeModal();
      await cargarAgencia();
      repaint();
      toast(a ? `Subcuenta "${subcuenta}" actualizada con éxito.` : `Subcuenta "${subcuenta}" creada exitosamente.`);
    } catch (x) {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = a ? 'Guardar cambios' : 'Crear subcuenta';
      }
      if (globalErr) globalErr.textContent = x.message || 'Error al procesar la solicitud.';
      f.classList.remove('shake');
      void f.offsetWidth;
      f.classList.add('shake');
    }
  };
}
/* 2.1.15 · El logo pasa por logoPng(): solo PNG, proporción revisada, sin pixelado. */
async function agenciaLeerLogo(file) { return (await logoPng(file)).data; }
async function agenciaUsuarios(id, accounts) {
  if (!esSuperAdmin()) return;
  try {
    const r = await agoraApi('agency/users?accountId=' + encodeURIComponent(id));
    const items = (r.items || []).map(u => ({...u, activo: u.activo ?? u.estado !== 'inactivo'}));
    const cuenta = accounts.find(a => String(a.id) === String(id));
    const s = openModal(modalHead('Usuarios de ' + esc(cuenta?.subcuenta || cuenta?.name || 'la subcuenta'), 'Miembros vinculados a esta cuenta') + `<div class="form"><div class="tw full"><table class="matriz"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody>${items.map(u => `<tr><td><b>${esc(u.nombre)}</b></td><td>${esc(u.correo)}</td><td><span class="sc sc-info">${esc(u.rol)}</span></td><td><span class="sc ${u.activo ? 'sc-success' : 'sc-danger'}">${u.activo ? 'Activo' : 'Sin acceso'}</span></td><td>${u.activo ? `<button type="button" class="btn btn-sm btn-danger" data-revoke-user="${esc(u.id)}">${icon('lock')}Revocar</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="5">Sin usuarios asignados</td></tr>'}</tbody></table></div><div class="form-ft"><button type="button" class="btn" data-close>Cerrar</button><button type="button" class="btn btn-cta" id="agency-add-member">Agregar usuario</button></div></div>`, {wide: true});
    s.querySelector('#agency-add-member').onclick = () => agenciaUsuarioNuevo(accounts, id);
    s.querySelectorAll('[data-revoke-user]').forEach(b => b.onclick = () => {
      const u = items.find(x => String(x.id) === b.dataset.revokeUser);
      if (u) agenciaRevocarUsuario(cuenta || {id}, u, async () => { await cargarAgencia(); repaint(); await agenciaUsuarios(id, AGENCY_ACCOUNTS || accounts); });
    });
  } catch (x) { toast(x.message); }
}
function agenciaUsuarioNuevo(accounts,selected='') {
  if (!esSuperAdmin()) return;
  const s=openModal(modalHead('Crear usuario desde Agencia','El usuario se asigna únicamente a la subcuenta seleccionada')+`<form class="form"><label class="fld">Contraseña inicial<input type="password" name="clave" required minlength="12" autocomplete="new-password"></label><label class="fld">Subcuenta<select name="accountId" required>${accounts.map(a=>`<option value="${esc(a.id)}" ${String(selected)===String(a.id)?'selected':''}>${esc(a.subcuenta||a.name||a.nombre)}</option>`).join('')}</select></label><label class="fld">Nombre<input name="nombre" required></label><label class="fld">Correo<input name="correo" type="email" required></label><label class="fld">Rol<select name="rol"><option value="ADMIN">Administración</option><option value="SUPERVISOR">Supervisión</option><option value="EJECUTIVO">Ejecutivo</option><option value="CONSULTA">Consulta</option></select></label><p class="fld-err" role="alert"></p><div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Crear usuario</button></div></form>`);
  s.querySelector('form').onsubmit=async e=>{e.preventDefault();const f=e.currentTarget;const payload=Object.fromEntries(new FormData(f));try{await agoraApi('agency/users',{method:'POST',body:payload});closeModal();await cargarAgencia();go('/agencia');toast('Usuario registrado en la subcuenta.');}catch(x){f.querySelector('.fld-err').textContent=x.message;}};
}
function usuarioNombreModal(u) {
  if (!esAdmin() && !esSuperAdmin()) return;
  const s=openModal(modalHead('Editar nombre de usuario',esc(u.correo))+`<form class="form"><label class="fld">Nombre completo<input name="nombre" maxlength="120" required value="${esc(u.nombre||'')}"></label><p class="fld-err" role="alert"></p><div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">Guardar nombre</button></div></form>`);
  s.querySelector('form').onsubmit=async e=>{e.preventDefault();const f=e.currentTarget,nombre=f.nombre.value.trim();if(!nombre){f.querySelector('.fld-err').textContent='Escribe el nombre.';return;}try{await agoraApi('workspace/usuarios/nombre',{method:'POST',body:{id:u.id,nombre}});await cargarUsuarios(true);closeModal();repaint();toast('Nombre actualizado.');}catch(x){f.querySelector('.fld-err').textContent=x.message;}};
}

function usuarioModal(previo) {
  const roles = Object.keys(ROLES_SISTEMA).filter(r => r !== 'SUPERADMIN');
  const s = openModal(modalHead(previo ? 'Cambiar rol' : 'Agregar persona', previo ? esc(previo.nombre) : 'Entra con su correo y la clave temporal que le compartas.')
    + `<form class="form" novalidate>
      ${previo ? '' : `<label class="fld">Nombre completo<input name="nombre" autofocus></label><label class="fld">Correo<input name="correo" type="email"></label>
      <label class="fld">Clave temporal (mínimo 12 caracteres)<span class="pw-in"><input name="clave" value="${claveAzar()}" autocomplete="off"><button class="pw-eye" type="button" data-regen>Otra</button></span></label>`}
      <div class="seg-fld">Rol${seg('rol-sys', roles.map(r => [r, ROLES_SISTEMA[r].l]), previo?.rol || 'EJECUTIVO', 'Rol')}</div>
      <ul class="role-help">${roles.map(r => `<li><span class="rol-punto" style="--c:${ROLES_SISTEMA[r].c}"></span><span><b class="strong">${esc(ROLES_SISTEMA[r].l)}:</b> ${esc(ROLES_SISTEMA[r].d)}</span></li>`).join('')}</ul>
      <p class="fld-err" role="alert"></p>
      <div class="form-ft"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${previo ? 'Guardar rol' : 'Agregar persona'}</button></div></form>`);
  const f = s.querySelector('form');
  s.querySelector('[data-regen]')?.addEventListener('click', () => { f.clave.value = claveAzar(); });
  f.onsubmit = async ev => {
    ev.preventDefault();
    const fd = Object.fromEntries(new FormData(f)), err = f.querySelector('.fld-err');
    fd.rol = f.querySelector('[data-seg="rol-sys"] .is-on')?.dataset.v || 'EJECUTIVO';
    if (!previo) {
      if (!String(fd.nombre || '').trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(String(fd.correo || '').trim())) { err.textContent = 'Escribe el nombre y un correo válido.'; return; }
      if (String(fd.clave || '').length < 12) { err.textContent = 'La clave temporal debe tener al menos 12 caracteres.'; return; }
    }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      if (previo) await agoraApi('workspace/usuarios/rol', {method: 'POST', body: {id: previo.id, rol: fd.rol}});
      else await agoraApi('workspace/usuarios', {method: 'POST', body: fd});
      closeModal(); await cargarUsuarios(true); repaint();
      if (previo) toast('Rol actualizado.');
      else openModal(modalHead('Persona agregada', `Comparte la clave con ${esc(fd.nombre)} por un canal seguro. Puede cambiarla desde su perfil.`) + `<div class="secret"><code>${esc(fd.clave)}</code><button class="btn btn-sm btn-cta" type="button" data-copy="${esc(fd.clave)}">Copiar</button></div><p class="src">Correo para entrar: ${esc(fd.correo)}</p><div class="form-ft"><button class="btn" type="button" data-close>Listo</button></div>`);
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
function usuarioMenu(id, ancla) {
  const u = (DATOS.usuarios || []).find(x => String(x.id) === String(id));
  if (!u) return;
  const el = openPop(ancla, `<button type="button" data-u="nombre">${icon('edit')}Cambiar nombre</button><button type="button" data-u="rol">${icon('shield')}Cambiar rol</button><button type="button" data-u="permisos">${icon('check')}Permisos individuales</button>${u.estado === 'inactivo' ? `<button type="button" data-u="on">${icon('check')}Reactivar</button>` : `<button type="button" data-u="off">${icon('pause')}Desactivar</button>`}`, 'ctx', {align: 'right'});
  if (!el) return;
  el.setAttribute('role', 'menu');
  el.onclick = async e => {
    const a = e.target.closest('[data-u]')?.dataset.u; if (!a) return; closePop();
    if (a === 'nombre') return usuarioNombreModal(u);
    if (a === 'rol') return usuarioModal(u);
    if (a === 'permisos') return permisosUsuarioModal(u);
    const estado = a === 'off' ? 'inactivo' : 'activo';
    confirmModal(a === 'off' ? 'Desactivar persona' : 'Reactivar persona',
      a === 'off' ? `${esc(u.nombre)} dejará de entrar al sistema. Puedes reactivarla cuando quieras.` : `${esc(u.nombre)} podrá entrar otra vez.`,
      a === 'off' ? 'Desactivar' : 'Reactivar',
      async () => { await agoraApi('workspace/usuarios/estado', {method: 'POST', body: {id: u.id, estado}}); await cargarUsuarios(true); repaint(); toast(a === 'off' ? 'Persona desactivada.' : 'Persona reactivada.'); },
      a === 'off');
  };
}
/** El logo del cliente: se lee en el navegador y se manda al servidor junto al nombre. */
async function guardarIdentidad(form) {
  const err = form.querySelector('.fld-err'), b = form.querySelector('[type=submit]');
  err.textContent = '';
  const nombre = form.elements.namedItem('nombre').value.trim();
  const subcuenta = form.elements.namedItem('subcuenta').value.trim();
  if (!nombre || !subcuenta) { err.textContent = 'Indica el nombre de la empresa y de la subcuenta.'; return; }
  if (nombre.length > 120 || subcuenta.length > 120) { err.textContent = 'Los nombres no deben superar 120 caracteres.'; return; }
  const archivo = form.elements.namedItem('logo').files?.[0];
  let logo = ESPACIO?.logo || null;
  if (archivo) {
    try { logo = form.__logoPng?.file === archivo ? form.__logoPng.data : (await logoPng(archivo)).data; }
    catch (x) { err.textContent = x.message || 'No se pudo leer el logo.'; return; }
  }
  let paleta = null;
  try { paleta = paletaLimpia(JSON.parse(form.elements.namedItem('paleta')?.value || 'null')); } catch { paleta = null; }
  if (paleta && paleta.principal === PALETAS[0][2] && paleta.acento === PALETAS[0][3]) paleta = null; // Ágora = sin paleta propia
  busy(b, true, 'Guardando…');
  try {
    const d = await agoraApi('workspace/branding', {method: 'POST', body: {nombre, subcuenta, logo, paleta}});
    ESPACIO = {...(ESPACIO || {}), ...d, nombre, subcuenta, logo: d?.logo !== undefined ? d.logo : logo, paleta: d?.paleta !== undefined ? d.paleta : paleta};
    if (DATOS.resumen?.espacios) {
      DATOS.resumen.espacios = DATOS.resumen.espacios.map(e =>
        String(e.id) === String(ESPACIO.id ?? DATOS.resumen.espacios[0]?.id)
          ? {...e, nombre, subcuenta, logo: ESPACIO.logo} : e);
    }
    topbar(); repaint(); toast('Identidad de la empresa y subcuenta guardada.');
  } catch (x) { err.textContent = x.message || 'No se pudo guardar la identidad.'; }
  finally { busy(b, false); }
}

/* =====================================================================================
   Lo que el tablero comercial necesita del armazón
   -------------------------------------------------------------------------------------
   Los módulos de Ágora van en otro paquete, así que las piezas de dibujo y el formato
   del dinero se les prestan por aquí. También guardan lo que leen, para que Athena pueda
   responder sobre ello sin volver a preguntarle al servidor.
   ===================================================================================== */
window.AgoraUI = {
  donut, barras, lineaArea, usd, firmado, mesNombre, mesCorto,
  guardarDia: d => { DATOS.dia = d; },
  guardarAds: a => { DATOS.ads = a; },
  guardarEficacia: e => { DATOS.eficacia = e; },
};

/* =====================================================================================
   Eventos de los módulos nuevos
   ===================================================================================== */
document.addEventListener('click', e => {
  const t = e.target, c = s => t.closest?.(s);
  let el;
  if (c('[data-tarea-nueva]')) { tareaModal(); return; }
  if ((el = c('[data-tarea-hecha]'))) { cambiarTarea(el.dataset.tareaHecha, 'hecha'); return; }
  if ((el = c('[data-tarea-abrir]'))) { cambiarTarea(el.dataset.tareaAbrir, 'pendiente'); return; }
  if ((el = c('[data-tarea-chat]'))) {
    const id = el.dataset.tareaChat;
    if (id && ws) { ws.pendingConversation = {id, explicit: true}; go('/bandeja'); }
    else go('/con-convenio');
    return;
  }
  if ((el = c('[data-aud]'))) { auditoriaDetalle(el.dataset.aud); return; }
  if (c('[data-aud-recargar]')) { DATOS.auditoria = null; render(); return; }
  if (c('[data-aud-exportar]')) { exportarAuditoria(); return; }
  if ((el = c('[data-aud-contacto]'))) { closeModal(); go('/con-convenio'); return; }
  if (c('[data-agencia-panel]') || c('[data-agency-return]')) { volverAgencia(); return; }
  if (c('[data-usuario-nuevo]')) { usuarioModal(); return; }
  if ((el = c('[data-usuario]'))) { usuarioMenu(el.dataset.usuario, el); return; }
  if (c('[data-logo-quitar]')) {
    confirmModal('Quitar el logo', 'El espacio volverá a mostrar solo el nombre.', 'Quitar', async () => {
      await agoraApi('workspace/branding', {method: 'POST', body: {nombre: ESPACIO?.nombre || '', logo: null}});
      ESPACIO = {...(ESPACIO || {}), logo: null};
      if (DATOS.resumen?.espacios) DATOS.resumen.espacios = DATOS.resumen.espacios.map(e => String(e.id) === String(ESPACIO.id ?? DATOS.resumen.espacios[0]?.id) ? {...e, logo: null} : e);
      topbar(); repaint(); toast('Logo quitado.');
    }, true);
    return;
  }
});
document.addEventListener('submit', e => {
  const f = e.target.closest('.ident-f');
  if (!f) return;
  e.preventDefault();
  guardarIdentidad(f);
});
/* Vista previa inmediata del logo elegido (se guarda solo al pulsar Guardar identidad). */
document.addEventListener('change', async e => {
  const input = e.target.closest?.('.ident-f input[name=logo]');
  const file = input?.files?.[0];
  if (!file) return;
  const form = input.form, fb = form.querySelector('.ident-logo-fb'), prev = form.querySelector('.ident-prev');
  try {
    const r = await logoPng(file);
    form.__logoPng = {file, data: r.data};
    if (prev) prev.innerHTML = `<img src="${esc(r.data)}" alt="Vista previa del logo" width="${r.w}" height="${r.h}">`;
    if (fb) { fb.textContent = logoNota(r); fb.className = 'fld-feedback ident-logo-fb is-ok'; }
  } catch (x) {
    input.value = ''; form.__logoPng = null;
    if (fb) { fb.textContent = x.message; fb.className = 'fld-feedback ident-logo-fb is-bad'; }
  }
});
/* La paleta se ve en vivo mientras se elige; si no se guarda, al salir de la pantalla vuelve la de la cuenta. */
document.addEventListener('focusin', e => { const f = e.target.closest?.('.ident-f'); if (f && !f.__pal) { f.__pal = true; paletaConectar(f, true); } });

/** El informe del mes, en un archivo que se abre en cualquier hoja de cálculo. */
function exportarAuditoria() {
  const items = DATOS.auditoria || [];
  if (!items.length) { toast('No hay movimientos que exportar.'); return; }
  bajarCsv(`auditoria-${PERIODO}.csv`, [
    ['Fecha', 'Persona', 'Rol', 'Acción', 'Módulo', 'Sobre qué', 'Contacto', 'Desde dónde'],
    ...items.map(e => [fstamp(e.at), e.actor, e.rol || '', e.accionTexto || e.accion, MODULOS_AUD[e.modulo] || e.modulo, e.objeto || '', e.contacto || '', `${e.dispositivo || ''} ${e.ip || ''}`.trim()]),
  ]);
}
function bajarCsv(nombre, filas) {
  const texto = filas.map(f => f.map(c => `"${String(c ?? '').replace(/^[\s\u0000-\u001f]*[=+@-]/, "'$&").replace(/"/g, '""')}"`).join(';')).join('\r\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['﻿' + texto], {type: 'text/csv;charset=utf-8'}));
  a.download = nombre;
  document.body.append(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  toast('Archivo descargado.');
}

/* =====================================================================================
   Tablero comercial · los módulos de gestión del reporte mensual
   -------------------------------------------------------------------------------------
   El Tablero Comercial de Agora se monta aquí tal como vino: su memoria de datos por mes,
   sus componentes, sus gráficos y sus vistas. Lo que cambia es el envoltorio —la barra
   superior, el período, los permisos y la auditoría los pone Agora Digital App— y la
   pantalla de inicio, que se reordena como Reporte de Gestión Comercial.

   Todo vive dentro de montarTablero(D, A) para no chocar con los nombres del panel
   (table, panel, metric, head, seg…), que allí se vuelven a declarar a propósito.
   ===================================================================================== */
const TB_MODS = ['resumen', 'equipo', 'marketing', 'tendencias', 'ventas', 'facturas', 'costos', 'catalogo', 'calidad', 'configuracion'];
const TB_PATH = {resumen: '/inicio', equipo: '/equipo', marketing: '/publicidad', tendencias: '/tendencias', ventas: '/ventas',
  facturas: '/facturacion', costos: '/costos', catalogo: '/catalogo', calidad: '/calidad', configuracion: '/configuracion'};
const tbPath = id => TB_PATH[id] || '/inicio';
const tbRuta = id => href(tbPath(id));
const tbVista = id => 'tc_' + id;

var TB = {
  estado: 'vacio',          // vacio · cargando · listo · error
  error: '',
  api: null,                // lo que devuelve montarTablero
  carga: null,              // promesa en curso
  cfg: {},                  // configuración mensual guardada en el servidor
  notas: {},                // observaciones de calidad marcadas como resueltas
  /* El tablero cambió de mes por su cuenta: se refleja en la barra superior. */
  alMes(ym) { if (ym && ym !== PERIODO) { PERIODO = ym; store.set('periodo', ym); } },
  /* Cambió el mes en la barra superior: se lo lleva al tablero. */
  irAlMes(ym) { if (TB.api) TB.api.irAlMes(ym); },
  repintar() { repaint(); },
  region() {
    const host = $('#region'); if (!host || !TB.api) return;
    const v = TB.api.V[TB.api.vista()];
    if (v && v.region) host.innerHTML = v.region();
  },
};

/* Un módulo del tablero se ofrece si hay sesión; cuando los datos ya están, se
   esconden los que ese reporte no trae (facturación, costos, calidad de datos). */
function tableroHay(id) {
  if (!ME && !AGORA_ME) return false;
  if (id === 'configuracion') return tableroCan('ver.configuracion');
  if (id === 'ventas') return tableroCan('ver.ventas') || tableroCan('ver.ventas.propias');
  return true;
}
const enTablero = () => R && typeof R.view === 'string' && R.view.indexOf('tc_') === 0;

function tableroCan(permiso) {
  const rol = (AGORA_ME && AGORA_ME.rol) || (ME && ME.rol) || 'CONSULTA';
  const T = {
    'ver.ventas': ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR'],
    'ver.ventas.propias': ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR', 'EJECUTIVO'],
    'ver.configuracion': ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR'],
    'editar.configuracion': ['MASTER', 'SUPERADMIN', 'ADMIN'],
    'editar.exportar': ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR'],
    'editar.observaciones': ['MASTER', 'SUPERADMIN', 'ADMIN', 'SUPERVISOR'],
  };
  return (T[permiso] || []).indexOf(rol) >= 0;
}

/* El puente con el sistema: sesión, permisos, configuración, observaciones y auditoría. */
const TB_A = {
  session: () => {
    const u = AGORA_ME || ME || {};
    return {uid: u.id || u.uid || '', nombre: u.nombre || u.name || 'Equipo', correo: u.correo || u.email || '',
      rol: u.rol || 'CONSULTA', asesora: u.asesora || u.nombre || ''};
  },
  can: tableroCan,
  config: () => JSON.parse(JSON.stringify(TB.cfg || {})),
  async setConfig(clave, valor) {
    const r = await agoraApi('workspace/config-mensual', {method: 'PUT', body: {periodo: clave, config: valor}});
    TB.cfg = (r && r.config) || Object.assign({}, TB.cfg, {[clave]: valor});
    TB_A.record('Guardó la configuración del mes', 'Gestión comercial', clave);
    return true;
  },
  notes: () => Object.assign({}, TB.notas),
  async setNote(id, resuelta) {
    const r = await agoraApi('workspace/observaciones', {method: 'POST', body: {id, resuelta}});
    TB.notas = (r && r.observaciones) || Object.assign({}, TB.notas,
      {[id]: resuelta ? {resuelta: true, por: TB_A.session().nombre, ts: new Date().toISOString()} : null});
    if (!resuelta) delete TB.notas[id];
    return true;
  },
  editOpen: () => tableroCan('editar.configuracion'),
  record(accion, modulo, detalle) {
    try { agoraApi('workspace/auditoria', {method: 'POST', body: {accion, modulo, detalle}}).catch(() => {}); } catch (e) { /* la auditoría no detiene el trabajo */ }
  },
};

async function cargarTablero() {
  if (TB.estado === 'listo' || TB.estado === 'sin-datos') return TB.api;
  if (TB.carga) return TB.carga;
  const epoch = NAV_EPOCH;
  TB.estado = 'cargando';
  TB.carga = (async () => {
    try {
      const d = await agoraApi('workspace/reporte');
      checkContext(epoch);
      TB.cfg = d.configuracion || {};
      TB.notas = d.observaciones || {};
      if (!Object.keys(d.reports || {}).length) { TB.estado='sin-datos'; TB.error=''; return null; }
      TB.api = montarTablero(d, TB_A);
      /* Si el mes elegido no tiene reporte de gestión, se abre el más reciente que sí
         lo tiene: la pantalla de inicio debe mostrar siempre un reporte completo. */
      if (TB.api.hayReporte(PERIODO)) TB.api.irAlMes(PERIODO);
      else TB.alMes(TB.api.mesActual());
      TB.estado = 'listo'; TB.error = '';
      tableroEstado();
    } catch (e) {
      if (epoch !== NAV_EPOCH) throw staleContext();
      TB.estado = 'error'; TB.error = e && e.message ? e.message : 'No se pudo cargar el reporte.';
      throw e;
    } finally { if (epoch === NAV_EPOCH) TB.carga = null; }
    return TB.api;
  })();
  return TB.carga;
}

/* Cada módulo del tablero se registra como una vista del panel. Mientras el reporte
   viaja se muestra el esqueleto de carga; si falla, el aviso con su reintento. */
TB_MODS.forEach(id => {
  const inicio = id === 'resumen';
  V[tbVista(id)] = {
    need: () => inicio ? Promise.all([cargarTablero(), cargarResumen()]) : cargarTablero(),
    render() {
      if (TB.estado === 'sin-datos') return head('Gestión comercial', esc(MODS[R.mod]?.name || 'Reporte'))+'<section class="panel"><p class="empty"><b>Esta subcuenta todavía no tiene reportes</b>Administración puede importar un reporte JSON desde Ajustes.</p><a class="btn" href="'+href('/ajustes')+'">Abrir Ajustes</a></section>';
      if (TB.estado === 'error') return failed(TB.error);
      if (TB.estado !== 'listo') return loading();
      if (!TB.api.disponible(id)) return head('Gestión comercial', esc(MODS[R.mod]?.name || 'Módulo')) + '<section class="panel"><p class="empty"><b>No hay datos disponibles para este módulo</b>El módulo sigue visible. Revisa la carga de información de esta subcuenta.<br><a class="btn" href="' + href('/centro') + '">Volver al inicio</a></p></section>';
      TB.api.vista(id);
      const html = TB.api.V[id].render();
      return inicio ? html.replace('<!--identidad-del-espacio-->', '') : html;
    },
    mounted() { if (TB.estado === 'listo') TB.api.acomodar(); },
  };
});

/* La identidad del espacio encabeza el reporte: quién responde por el sistema y qué
   empresas se atienden. Se arma fuera del tablero porque son datos del propio espacio. */
function identidadEspacio() {
  const d = DATOS.resumen;
  if (!d) return '';
  const responsables = panel('Responsables del espacio', 'Quiénes tienen el control total del espacio.',
    `<ul class="mini-list">${(d.superadmins || []).map(u => `<li><span class="who-cell"><b>${esc(u.nombre)}</b><small>${esc(u.correo)}</small></span><span class="sc sc-info">SuperAdmin</span></li>`).join('') || '<li><span class="muted">Todavía no hay nadie asignado.</span></li>'}</ul>`,
    esSuperAdmin() ? `<a class="btn btn-sm btn-tint" href="${href('/usuarios')}">${icon('plus')}Agregar</a>` : '');
  const espacios = (d.espacios || []).length
    ? panel('Espacios atendidos', 'Cada subcuenta con su logo y el plan de servicio que tiene contratado.',
      `<div class="esp-grid">${d.espacios.map(e => {
        const actual = String(e.id) === String(ESPACIO?.id ?? d.espacios?.[0]?.id);
        const empresa = (actual && ESPACIO?.nombre) || e.nombre || 'Empresa sin nombre';
        const sub = (actual && ESPACIO?.subcuenta) || e.subcuenta || (ws && ws.name()) || 'Subcuenta sin nombre';
        const logo = (actual && ESPACIO?.logo !== undefined) ? ESPACIO.logo : e.logo;
        return `<article class="esp-card">
          <span class="esp-mark">${logo ? `<img src="${esc(logo)}" alt="Logo de ${esc(empresa)}" loading="lazy">` : esc(initials(empresa))}</span>
          <div class="tx"><b>${esc(empresa)}</b><span class="esp-subcuenta">${esc(sub)}</span><small>${esc(e.plan || 'Sin plan contratado')}${e.precio != null ? ` · ${esc(usd(e.precio))} al mes` : ''}</small></div>
        </article>`;
      }).join('')}</div>`,
      esSuperAdmin() ? `<a class="btn btn-sm" href="${href('/usuarios')}">${icon('palette')}Identidad y logos</a>` : '')
    : '';
  return `<div class="grid g2 hx-ident">${responsables}${espacios}</div>`;
}

/* Los filtros de cada módulo viven en el mismo registro que los del panel, así los
   botones de orden, página y búsqueda que ya existen siguen funcionando igual. */
function tableroEstado() {
  if (TB.estado !== 'listo') return;
  const st = TB.api.state;
  Object.keys(st).forEach(k => { if (TB_MODS.indexOf(k) >= 0) state[tbVista(k)] = st[k]; });
}

/* ---------- Lo que el panel no cubre: limpiar filtros, CSV, mes, observaciones ---------- */
document.addEventListener('click', e => {
  if (!enTablero() || TB.estado !== 'listo') return;
  const t = e.target, c = s => t.closest(s), para = () => e.stopPropagation();
  let el;
  if ((el = c('[data-period]'))) { para(); TB.api.irAbsoluto(+el.dataset.period); return; }
  if ((el = c('.seg-b')) && el.closest('.seg') && el.closest('.seg').dataset.seg === 'sev') {
    para();
    const sg = el.closest('.seg');
    sg.querySelectorAll('.seg-b').forEach(b => { const on = b === el; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', String(on)); });
    placeThumbs(sg.parentElement, true);
    TB.api.state.calidad.sev = el.dataset.v; TB.region(); return;
  }
  if ((el = c('[data-note]'))) {
    para();
    const id = el.dataset.note, hecha = el.dataset.done === '1';
    busy(el, true);
    TB_A.setNote(id, hecha)
      .then(() => { TB.region(); toast(hecha ? 'Observación marcada como resuelta' : 'Observación reabierta'); })
      .catch(x => { busy(el, false); toast(x.message || 'No se pudo guardar la observación.'); });
    return;
  }
  if ((el = c('[data-csv]'))) { para(); TB.api.exportar(el.dataset.csv); return; }
  if (c('[data-print]')) { para(); print(); return; }
  if (c('[data-cfg-reset]')) {
    para();
    confirmModal('Volver a los valores del archivo',
      `Se borran las metas, los umbrales y los costos manuales de ${TB.api.mesLargo()}. Gestión Comercial vuelve a usar los valores del reporte. Queda en la auditoría.`,
      'Restablecer', async () => { await TB_A.setConfig(TB.api.claveConfig(), {}); repaint(); toast('Configuración restablecida'); }, true);
    return;
  }
  if (c('[data-clear]')) {
    para();
    const st = state[R.view];
    if (st) { Object.keys(st).forEach(k => { if (['p', 'sk', 'sd', 'tab'].indexOf(k) < 0) st[k] = ''; }); st.p = 0; }
    repaint(); return;
  }
}, true);

document.addEventListener('submit', e => {
  const f = e.target.closest('form[data-form="config"]');
  if (!f || !enTablero() || TB.estado !== 'listo') return;
  e.preventDefault(); e.stopPropagation();
  const err = f.querySelector('.fld-err'), btn = f.querySelector('[type=submit]');
  busy(btn, true, 'Guardando…');
  TB.api.guardarConfig(f)
    .then(() => { busy(btn, false); if (err) err.textContent = ''; repaint(); toast('Configuración del mes guardada'); })
    .catch(x => { busy(btn, false); if (err) err.textContent = x.message; else toast(x.message); });
}, true);

document.addEventListener('dblclick', e => {
  if (!enTablero() || TB.estado !== 'listo') return;
  const t = e.target;
  if (t.closest('input, select, button, a')) return;
  const ch = t.closest('[data-chart]'), w = t.closest('[data-what]'), tr = t.closest('tr[data-row]');
  if (!ch && !w && !tr) return;
  e.preventDefault(); getSelection() && getSelection().removeAllRanges();
  if (ch) return TB.api.graficoGrande(ch);
  if (w) return TB.api.explicar(w);
  TB.api.detalleFila(tr.dataset.row);
});

/* =====================================================================================
   El tablero comercial, tal como vino, con el puente del sistema
   ===================================================================================== */
const tbIr = p => go(p);

function montarTablero(D, A) {
/* Dentro del tablero, «render» repinta el módulo, «region» solo la zona de la tabla y
   «go» navega a otro módulo del tablero por su identificador. */
const render = () => TB.repintar();
const region = () => TB.region();
const go = id => tbIr(tbPath(id));

/* ---------- Ayudas de formato y números (tablero comercial, líneas 10-35) ---------- */
/* $, $$, esc, norm e icon vienen del panel. */
const NF = (a, b) => new Intl.NumberFormat('es-VE', {minimumFractionDigits: a, maximumFractionDigits: b});
const f0 = NF(0, 0), f1 = NF(1, 1), f2 = NF(2, 2);
const usd = c => c == null ? '—' : (c < 0 ? '−US$ ' : 'US$ ') + f2.format(Math.abs(c) / 100);
const usdK = c => 'US$ ' + (Math.abs(c) >= 100000 ? f1.format(c / 100000) + ' mil' : f0.format(c / 100));
const int = n => n == null || n === '' ? '—' : f0.format(n);
const pct = (x, d = 1) => x == null || typeof x !== 'number' || !isFinite(x) ? (typeof x === 'string' ? x : '—') : NF(d, d).format(x * 100) + ' %';
const sum = (a, k) => a.reduce((n, r) => n + (k ? (r[k] || 0) : (r || 0)), 0);
const MES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const objs = t => t ? t.rows.map(r => Object.fromEntries(t.cols.map((c, i) => [c, r[i]]))) : [];
const cleanName = s => String(s).replace(/^(Br\.|Lic\.|Lcda\.|Lcdo\.|T\.S\.U)\s*/, '').replace(/\s+/g, ' ').trim();
const titleCase = s => String(s).toLowerCase().replace(/(^|\s)\S/g, c => c.toUpperCase());
const item = (list, label) => (list.find(x => String(x[0]).toLowerCase().startsWith(label.toLowerCase())) || [])[1];
const dmy = s => s ? s.slice(0, 10).split('-').reverse().join('/') : '—';
const first = s => norm(cleanName(s)).split(' ')[0];
const ME = A.session();
const can = id => A.can(id);
/* ---------- Memoria de datos por mes (tablero comercial, líneas 44-134) ---------- */
const PROPIAS = !can('ver.ventas') && can('ver.ventas.propias');
const pad2 = n => String(n).padStart(2, '0');
const keyOf = (y, m) => `${y}-${pad2(m + 1)}`;
const parseKey = k => ({y: +k.slice(0, 4), m: +k.slice(5, 7) - 1});
const yearOfTitle = t => +(String(t).match(/\b(20\d\d)\b/) || [])[1] || new Date().getFullYear();
const monthOfTitle = t => MES.findIndex(m => norm(t).includes(norm(m)));
const REPS = {};
for (const [k, r] of Object.entries(D.reports || {})) {
  if (!r.gc) continue;
  const key = k !== '_' ? k : keyOf(yearOfTitle(r.gc.title), Math.max(0, monthOfTitle(r.gc.title)));
  REPS[key] = r;
}
const REPKEYS = Object.keys(REPS).sort();
if (!REPKEYS.length) throw new Error('Este archivo no trae ningún reporte de gestión.');
/* Histórico por año: {2025: [{v, p} × 12], 2026: [...]}. Los reportes más recientes completan y corrigen a los anteriores. */
const HISTY = {};
for (const key of REPKEYS) {
  const ry = parseKey(key).y;
  REPS[key].hist.rows.forEach((r, i) => {
    for (const [y, v, p] of [[ry - 1, r[1], r[2]], [ry, r[3], r[4]]]) {
      const row = (HISTY[y] ||= Array.from({length: 12}, () => ({v: null, p: null})))[i];
      if (v != null) row.v = v; if (p != null) row.p = p;
    }
  });
}
const hv = (y, m) => HISTY[y]?.[m] || {v: null, p: null};
const INVY = parseKey(REPKEYS.at(-1)).y;              // la facturación viene del reporte más reciente
const INV = objs(D.inv);
const invMonths = [...new Set(INV.map(r => r.mes))].sort((a, b) => a - b);
const invBy = (k, rows = INV) => Object.entries(rows.reduce((m, r) => { const g = m[r[k] || 'Sin dato'] ||= {n: 0, v: 0}; g.n++; g.v += r.monto || 0; return m; }, {})).map(([l, g]) => ({l, ...g}));
const notes = () => A.notes();
const TODAY = new Date();
const TODAYA = TODAY.getFullYear() * 12 + TODAY.getMonth();
const absOf = (y, m) => y * 12 + m;
const MINA = absOf(Math.min(...Object.keys(HISTY).map(Number), ...REPKEYS.map(k => parseKey(k).y)), 0);
const MAXA = Math.max(TODAYA + 1, ...REPKEYS.map(k => { const p = parseKey(k); return absOf(p.y, p.m); }));
const statusOf = a => { const y = Math.floor(a / 12), m = a % 12; return REPS[keyOf(y, m)] ? 'rep' : a > TODAYA ? 'proj' : a === TODAYA ? 'cur' : hv(y, m).v != null ? 'hist' : 'none'; };

/* Estado del período y cifras derivadas (se recalculan en applyPeriod) */
let DY, P, CUT, REPY, REPKEY, CURM, NEXTM;
let HAS, SALES, CAT, hist, lastIdx, GT, ADV, ytd25, ytd26, cur, metaReal, metaIdeal, pup, ocu, rent, C, ISSUES, HOJAS;
const isProj = m => absOf(DY, m) > TODAYA;
const HASREP = () => REPKEY === keyOf(DY, P);
const repLabel = () => `${MES[CUT].toLowerCase()} de ${REPY}`;
const repAbs = () => absOf(REPY, CUT);
const hojaOf = name => HOJAS.find(x => first(x) === first(name));
const openIssues = () => ISSUES.filter(i => !notes()[i.id]?.resuelta);
/* Serie de los últimos n meses hasta el período elegido, cruzando de año si hace falta */
const seriesBack = n => Array.from({length: n}, (_, i) => { const a = absOf(DY, P) - (n - 1 - i), y = Math.floor(a / 12), m = a % 12; return {mes: y !== DY ? `${MES[m].slice(0, 3)} ${String(y).slice(2)}` : MES[m], v26: hv(y, m).v, v25: hv(y - 1, m).v, p26: hv(y, m).p, p25: hv(y - 1, m).p}; });
function applyPeriod() {
  const key = keyOf(DY, P);
  REPKEY = REPS[key] ? key : (REPKEYS.filter(k => k <= key).at(-1) || REPKEYS[0]);
  ({y: REPY, m: CUT} = parseKey(REPKEY));
  const r = REPS[REPKEY];
  Object.assign(D, r);
  HAS = {facturas: !!D.inv && DY === INVY, costos: !!r.costs, calidad: !!r.issues, ventas: r.sales.rows.length > 0};
  SALES = objs(r.sales);
  CAT = Object.fromEntries(r.catalog.rows.map(x => [x[0], x[1]]));
  hist = MES.map((mes, i) => ({mes, v25: hv(DY - 1, i).v, p25: hv(DY - 1, i).p, v26: hv(DY, i).v, p26: hv(DY, i).p}));
  lastIdx = HASREP() ? P : hist.reduce((n, h, i) => h.v26 != null && i <= P ? i : n, -1);
  const upTo = hist.slice(0, lastIdx + 1);
  ytd25 = sum(upTo, 'v25'); ytd26 = sum(upTo, 'v26');
  cur = hist[Math.max(0, lastIdx)];
  GT = r.gc.total; ADV = r.gc.advisors;
  metaReal = item(r.metas.recaudacion.items, 'Meta Real') * 100; metaIdeal = item(r.metas.recaudacion.items, 'Meta Ideal') * 100;
  pup = Object.fromEntries(r.metas.pupitres.items.map(([k, v]) => [k.replace(/[:%]/g, '').trim(), v]));
  ocu = Object.fromEntries(r.metas.ocupacion.items.map(([k, v]) => [k.replace(/[:%]/g, '').trim(), v]));
  rent = r.rent ? Object.fromEntries(r.rent.rows.map(x => [x[0], x])) : null;
  C = r.costs || null;
  ISSUES = r.issues || [];
  HOJAS = [...new Set(SALES.map(x => x.hoja))];
  CURM = DY === TODAY.getFullYear() ? TODAY.getMonth() : DY < TODAY.getFullYear() ? 11 : -1;
  NEXTM = Math.min(11, CURM + 1);
}
/* Arranca en el mes del reporte más reciente */
({y: DY, m: P} = parseKey(REPKEYS.at(-1)));
applyPeriod();

const PERIOD_VIEWS = ['resumen', 'equipo', 'marketing', 'ventas', 'costos', 'catalogo', 'tendencias', 'facturas', 'configuracion'];
/* Ventana de la barra: mes anterior, elegido y siguiente (sin pasar de los límites con datos) */
const periodWindow = () => { const a = absOf(DY, P), w = [a - 1, a, a + 1].filter(x => x >= MINA && x <= MAXA); return w; };
function setAbs(a, announce) {
  a = Math.max(MINA, Math.min(MAXA, a)); if (a === absOf(DY, P)) return;
  DY = Math.floor(a / 12); P = a % 12;
  applyPeriod();
  if (HAS.facturas) { state.facturas.mes = invMonths.includes(P + 1) ? String(P + 1) : (isProj(P) ? String(P + 1) : ''); state.facturas.p = 0; }
  TB.alMes(keyOf(DY, P));
  render();
  if (announce) { const s = statusOf(a); toast(`${MES[P]} ${DY}${s === 'rep' ? ' · reporte completo' : s === 'proj' ? ' · proyección' : s === 'cur' ? ' · mes en curso' : s === 'hist' ? ' · solo histórico' : ' · sin datos'}`); }
}
const setPeriod = (m, announce) => setAbs(absOf(DY, m), announce);
/* ---------- Componentes (tablero comercial, líneas 169-266) ---------- */
/* =====================================================================================
   Componentes
   ===================================================================================== */
const metric = (l, v, f = '', what = '', cls = '') => `<div class="micro ${cls}"${what ? ` data-what="${esc(what)}" data-wl="${esc(l)}"` : ''}><h3>${l}</h3><p class="t-metric" style="margin:0">${v}</p>${f ? `<span class="f">${f}</span>` : ''}</div>`;
const delta = (a, b) => b ? `<span class="${a >= b ? 'up' : 'down'}">${a >= b ? '▲' : '▼'} ${pct(a / b - 1)}</span>` : '';
const src = s => `<p class="src">Fuente: ${esc(s)}</p>`;
const panel = (title, sub, body, extra = '', cls = '') => `<section class="panel ${cls}"><div class="panel-h"><div><h2 class="t-panel">${title}</h2>${sub ? `<p>${sub}</p>` : ''}</div>${extra}</div>${body}</section>`;
const sec = t => `<h2 class="t-sec">${t}</h2>`;
const flag = v => `<span class="sc sc-warn">${esc(v)}</span>`;
const head = (eyebrow, title, lede, acts = '') => `<div class="mod-h"><div><p class="t-label">${eyebrow}</p><h1 class="t-module">${title}</h1>${lede ? `<p class="lede">${lede}</p>` : ''}</div>${acts ? `<div class="acts">${acts}</div>` : ''}</div>`;
const reportNote = () => !HASREP() ? `<div class="note-gold note-row top"><span>Estás viendo el reporte de <b>${repLabel()}</b>, el más cercano con datos completos. ${isProj(P) ? `${MES[P]} es una proyección.` : `${MES[P]} todavía no tiene reporte de gestión.`}</span><button class="btn btn-sm" type="button" data-period="${repAbs()}">Ver ${repLabel()}</button></div>` : '';
function seg(key, options, value, label, cls = '') {
  return `<div class="seg ${cls}" role="radiogroup" aria-label="${esc(label)}" data-seg="${key}"><span class="seg-thumb"></span>${options.map(([v, l, n]) => `<button type="button" class="seg-b ${String(v) === String(value) ? 'is-on' : ''}" role="radio" aria-checked="${String(v) === String(value)}" data-v="${esc(v)}">${l}${n != null ? ` <span class="n">${int(n)}</span>` : ''}</button>`).join('')}</div>`;
}
function placeThumbs(root = document, animate = false) {
  $$('.seg', root).forEach(s => {
    const on = s.querySelector('.seg-b.is-on'), th = s.querySelector('.seg-thumb'); if (!th) return;
    if (!on) { th.style.opacity = 0; return; }
    if (!animate) th.style.transition = 'none';
    th.style.opacity = 1; th.style.width = on.offsetWidth + 'px'; th.style.transform = `translateX(${on.offsetLeft}px)`;
    if (!animate) { th.offsetWidth; th.style.transition = ''; }
  });
}
const monthSeg = cls => seg('period', periodWindow().map(a => { const y = Math.floor(a / 12), m = a % 12; return [a, `${MES[m].slice(0, 3)}${y !== DY ? ` ${String(y).slice(2)}` : ''}${a === TODAYA ? '<i class="dot-now" aria-label="mes en curso"></i>' : a > TODAYA ? '<i class="dot-proj" aria-label="proyección"></i>' : ''}`]; }), absOf(DY, P), 'Mes', cls);
function bars(items, {fmt = usd, max, alt} = {}) {
  const m = max ?? Math.max(1, ...items.map(i => Math.max(i.v || 0, i.t || 0)));
  return `<div class="bars">${items.map(i => `<div class="bar-row" data-tip="${esc(i.tip || `<b>${esc(i.l)}</b><br>${fmt(i.v)}`)}"><span class="lab" title="${esc(i.l)}">${esc(i.l)}</span><span class="track"><span class="fill ${alt ? 'alt' : ''}" style="width:${Math.min(100, 100 * (i.v || 0) / m)}%"></span>${i.t != null ? `<span class="target" style="left:${Math.min(100, 100 * i.t / m)}%"></span>` : ''}</span><span class="val">${i.label ?? fmt(i.v)}</span></div>`).join('')}</div>`;
}
const narrow = () => innerWidth < 760;
function niceMax(v) { const p = 10 ** Math.floor(Math.log10(v || 1)), n = v / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p; }
const CHARTS = {};
let chartSeq = 0;
// Logo de la identidad configurada para el tenant activo; no usa ninguna imagen fija de ASOCIM.
const logoGrafica = (placement = 'chart') => {
  const logo = ESPACIO?.logo || '';
  // Se aceptan archivos rasterizados seguros o rutas locales; no SVG ni URLs externas.
  if (!/^(data:image\/(?:png|jpeg|webp);base64,|\/[^\s"'<>]+\.(?:png|jpe?g|webp)(?:\?[^\s"'<>]*)?$)/i.test(logo)) return '';
  const name = ESPACIO?.nombre || ESPACIO?.subcuenta || 'la empresa';
  const cls = placement === 'hero' ? 'hx-brand-signature' : 'chart-brand-strip';
  return `<div class="${cls}" aria-label="Identidad de ${esc(name)}"><img src="${esc(logo)}" alt="Logo de ${esc(name)}" decoding="async"></div>`;
};
const chartBox = (fn, opts, settings = {}) => { const id = 'ch' + (++chartSeq); CHARTS[id] = [fn, opts]; return `<div class="chart-wrap chart-brand-wrap" data-chart="${id}" title="Doble clic para verlo en grande">${settings.branding === false ? '' : logoGrafica()}${fn(opts)}</div>`; };
function lineChart({labels, series, fmt = usd, tickFmt = usdK, h = 260, big, mark}) {
  const W = big ? 1000 : narrow() ? 420 : 720, L = 78, R = 50, T = 16, B = 28, w = W - L - R, ih = h - T - B;
  const max = niceMax(Math.max(...series.flatMap(s => s.values.filter(v => v != null))));
  const x = i => L + (labels.length === 1 ? w / 2 : i * w / (labels.length - 1)), y = v => T + ih - v / max * ih;
  let g = mark != null ? `<rect x="${x(mark) - w / labels.length / 2}" y="${T}" width="${w / labels.length}" height="${ih}" fill="var(--brand-soft)" rx="6"/>` : '';
  for (let k = 0; k <= 4; k++) { const v = max * k / 4; g += `<line class="gl" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${tickFmt(v)}</text>`; }
  labels.forEach((l, i) => { if (big || !narrow() || i % 2 === 0) g += `<text x="${x(i)}" y="${h - 8}" text-anchor="middle">${esc(l.slice(0, 3))}</text>`; });
  series.forEach(s => {
    const pts = s.values.map((v, i) => v == null ? null : [x(i), y(v)]).filter(Boolean);
    g += `<polyline fill="none" stroke="${s.color}" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" ${s.dash ? 'stroke-dasharray="5 5"' : ''} points="${pts.map(p => p.join(',')).join(' ')}"/>`;
    if (!s.dash) pts.forEach(p => g += `<circle cx="${p[0]}" cy="${p[1]}" r="4" fill="${s.color}" stroke="var(--surface)" stroke-width="2"/>`);
    const lp = pts.at(-1); if (lp) g += `<text class="dl" x="${lp[0] + 8}" y="${lp[1] + 4}">${esc(s.name)}</text>`;
  });
  labels.forEach((l, i) => {
    const tip = `<b>${esc(l)}</b>` + series.map(s => `<br>${esc(s.name)}: ${s.values[i] == null ? 'sin dato' : fmt(s.values[i])}`).join('') + (series[1] && series[0].values[i] != null && series[1].values[i] ? `<br>Variación: ${pct(series[0].values[i] / series[1].values[i] - 1)}` : '');
    g += `<rect class="hit" x="${x(i) - w / labels.length / 2}" y="${T}" width="${w / labels.length}" height="${ih}" data-tip="${esc(tip)}"/>`;
  });
  return `<svg class="chart" viewBox="0 0 ${W} ${h}" role="img" aria-label="${esc(series.map(s => s.name).join(' y '))} por mes">${g}</svg>`;
}
function colChart({labels, values, fmt = usd, tickFmt = usdK, tips, h = 240, big, mark}) {
  const W = big ? 1000 : narrow() ? 420 : 720, L = 78, R = 12, T = 22, B = 28, w = W - L - R, ih = h - T - B, max = niceMax(Math.max(...values));
  const bw = Math.min(46, w / labels.length * .62), x = i => L + (i + .5) * w / labels.length, y = v => T + ih - v / max * ih;
  let g = '';
  for (let k = 0; k <= 4; k++) { const v = max * k / 4; g += `<line class="gl" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${tickFmt(v)}</text>`; }
  const top = values.indexOf(Math.max(...values));
  values.forEach((v, i) => {
    const bh = Math.max(1, ih - (y(v) - T)), yy = y(v), r = Math.min(5, bh);
    g += `<path fill="${mark != null && i !== mark ? 'color-mix(in srgb, var(--c-now) 45%, var(--surface))' : 'var(--c-now)'}" d="M${x(i) - bw / 2},${T + ih} V${yy + r} Q${x(i) - bw / 2},${yy} ${x(i) - bw / 2 + r},${yy} H${x(i) + bw / 2 - r} Q${x(i) + bw / 2},${yy} ${x(i) + bw / 2},${yy + r} V${T + ih} Z"/>`;
    g += `<text x="${x(i)}" y="${h - 8}" text-anchor="middle">${esc(labels[i].slice(0, 3))}</text>`;
    if (big || i === top || i === values.length - 1 || i === mark) g += `<text class="dl" x="${x(i)}" y="${yy - 6}" text-anchor="middle">${tickFmt(v)}</text>`;
    g += `<rect class="hit" x="${x(i) - w / labels.length / 2}" y="${T}" width="${w / labels.length}" height="${ih}" data-tip="${esc(tips ? tips[i] : `<b>${esc(labels[i])}</b><br>${fmt(v)}`)}"/>`;
  });
  return `<svg class="chart" viewBox="0 0 ${W} ${h}" role="img" aria-label="Columnas por mes">${g}</svg>`;
}
const lineLegendHtml = () => `<div class="legend"><span><i class="ln" style="background:var(--c-now)"></i>${DY}</span><span><i class="dash"></i>${DY - 1}</span></div>`;
function table(cols, rows, {foot, sortKey, sortDir, sortable, rowKey, empty} = {}) {
  const th = cols.map(c => `<th class="${c.n ? 'n' : ''}" ${sortable ? `aria-sort="${sortKey === c.k ? (sortDir > 0 ? 'ascending' : 'descending') : 'none'}"` : ''}>${sortable && !c.nosort ? `<button type="button" data-sort="${c.k}">${esc(c.l)}${sortKey === c.k ? (sortDir > 0 ? ' ↑' : ' ↓') : ''}</button>` : esc(c.l)}</th>`).join('');
  const cell = (c, r) => `<td class="${c.n ? 'n' : c.wrap ? 'wrap' : ''}">${c.f ? c.f(r[c.k], r) : esc(r[c.k] ?? '—')}</td>`;
  const body = rows.length ? rows.map(r => `<tr${rowKey ? ` data-row="${esc(rowKey(r))}"` : ''}>${cols.map(c => cell(c, r)).join('')}</tr>`).join('')
    : `<tr><td colspan="${cols.length}" class="empty">${empty || '<b>No hay registros con estos filtros</b>Prueba con otra búsqueda o quita los filtros.<br><button class="btn btn-sm" type="button" data-clear>Quitar filtros</button>'}</td></tr>`;
  return `<div class="tw"><table><thead><tr>${th}</tr></thead><tbody>${body}</tbody>${foot ? `<tfoot><tr>${cols.map(c => `<td class="${c.n ? 'n' : ''}">${foot[c.k] ?? ''}</td>`).join('')}</tr></tfoot>` : ''}</table></div>`;
}
const sel = (key, label, opts, val, all = 'Todos') => `<label class="fld">${label}<select data-f="${key}"><option value="">${all}</option>${opts.map(o => `<option ${String(o[0]) === String(val) ? 'selected' : ''} value="${esc(o[0])}">${esc(o[1])}</option>`).join('')}</select></label>`;
const search = (val, ph) => `<label class="fld grow">Buscar<span class="search-in">${icon('search')}<input type="search" data-f="q" value="${esc(val)}" placeholder="${esc(ph)}" autocomplete="off" enterkeyhint="search"></span></label>`;
const clearBtn = st => Object.keys(st).some(k => !['p', 'sk', 'sd', 'tab'].includes(k) && st[k]) ? `<button class="btn clear" type="button" data-clear>Quitar filtros</button>` : '';
function sortRows(rows, st) { if (!st.sk) return rows; const k = st.sk, d = st.sd; return [...rows].sort((a, b) => { const x = a[k] ?? '', y = b[k] ?? ''; return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), 'es')) * d; }); }
function pager(st, total, per) { const pages = Math.max(1, Math.ceil(total / per)); st.p = Math.min(st.p, pages - 1); return `<div class="pager"><span>${int(total)} registros · página ${st.p + 1} de ${pages}</span><div><button class="btn btn-sm" data-page="-1" ${st.p ? '' : 'disabled'}>Anterior</button><button class="btn btn-sm" data-page="1" ${st.p >= pages - 1 ? 'disabled' : ''}>Siguiente</button></div></div>`; }
function csv(name, cols, rows, modulo) {
  if (!can('editar.exportar')) return toast('Tu rol no puede descargar datos.');
  const q = v => { let s = String(v ?? ''); if (/^[\s\u0000-\u001f]*[=+@-]/.test(s)) s = "'" + s; return /[";\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
  const text = '﻿' + [cols.map(c => q(c.l)).join(';'), ...rows.map(r => cols.map(c => { const v = r[c.k]; return q(c.money ? (v == null ? '' : f2.format(v / 100)) : v); }).join(';'))].join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], {type: 'text/csv;charset=utf-8'})); a.download = name + '.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  A.record('Descargó un CSV', modulo, `${name}.csv · ${rows.length} registros`);
  toast(`Descargaste ${int(rows.length)} registros en ${name}.csv`);
}
const csvBtn = key => can('editar.exportar') ? `<button class="btn" type="button" data-csv="${key}">${icon('download')}Descargar CSV</button>` : '';
const money = (k, l) => ({k, l, n: 1, money: 1, f: v => usd(v)});
const when = iso => {
  if (!iso) return 'Nunca';
  const d = new Date(iso), s = (Date.now() - d) / 1000;
  if (s < 60) return 'hace un momento'; if (s < 3600) return `hace ${Math.round(s / 60)} min`; if (s < 86400) return `hace ${Math.round(s / 3600)} h`;
  return d.toLocaleDateString('es-VE', {day: 'numeric', month: 'short', year: d.getFullYear() !== TODAY.getFullYear() ? 'numeric' : undefined});
};
const stamp = iso => new Date(iso).toLocaleString('es-VE', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});

/* ---------- Estado de los filtros (tablero comercial, líneas 270-271) ---------- */
const state = {ventas: {p: 0, sk: 'fecha', sd: 1}, facturas: {p: 0, sk: 'fecha', sd: 1}, calidad: {sev: ''}, usuarios: {tab: 'personas'}, auditoria: {p: 0, quien: '', modulo: '', q: ''}};
const V = {};
/* ---------- Vista de un mes sin reporte (tablero comercial, líneas 273-300) ---------- */
function monthView() {
  const h = hist[P], rows = HAS.facturas ? INV.filter(r => r.mes === P + 1) : [], mm = MES[P].toLowerCase();
  if (isProj(P)) {
    return head(`Proyección · Gestión Comercial`, `Reporte Gestión Comercial - ${MES[P]} ${DY}`, `Se calcula con los compromisos del reporte de ${repLabel()}. Es una proyección: no se guarda como resultado real.`)
    + `<div class="grid g-hero"><section class="hero" data-wl="Cobro esperado de mensualidades" data-what="Suma de las mensualidades que pagarán los inscritos del último reporte (recurrencia mensual). Es lo que debería entrar cada mes si nadie se retira.">
        <p class="t-label">Cobro esperado de mensualidades · ${mm}</p><p class="t-hero" style="margin:10px 0 0">${usd(GT.recurrencia)}</p>
        <p class="f">Recurrencia comprometida por ${int(GT.inscritos)} inscritos de ${repLabel()}</p></section>
      <div class="grid g2">
        ${metric('Pendiente por cobrar', usd(GT.pendiente), `De lo vendido en ${repLabel()}`, 'Lo vendido en el último reporte que aún no se ha cobrado. Parte puede entrar este mes.')}
        ${metric(`${MES[P]} ${DY - 1}`, h.v25 != null ? usd(h.v25) : '—', 'Ventas del mismo mes el año pasado', 'Referencia del histórico ASOCIM para comparar cuando llegue el reporte.')}
        ${metric('Meta real del último reporte', usd(metaReal), `Se logró ${pct(GT.vendido / metaReal)}`, 'Meta de recaudación del reporte de gestión. Sirve de referencia hasta que se cargue la meta del mes.')}
        ${HAS.facturas ? metric('Registros ya cargados', int(rows.length), rows.length ? usd(sum(rows, 'monto')) : 'Aún no hay facturas del mes') : metric('Inscritos comprometidos', int(GT.inscritos), 'Del último reporte')}
      </div></div>
      <div class="note-gold note-row"><span>Cuando cargues el reporte de gestión de ${mm}, esta pantalla pasa a mostrar las cifras reales.</span><button class="btn btn-sm" type="button" data-period="${repAbs()}">Ver ${repLabel()}</button></div>`;
  }
  const enCurso = P === CURM;
  const famTop = invBy('familia', rows).sort((a, b) => b.v - a.v).slice(0, 8);
  return head(`${enCurso ? 'Mes en curso' : 'Mes cerrado'} · Gestión Comercial`, `Reporte Gestión Comercial - ${MES[P]} ${DY}`, `El reporte de gestión de ${mm} todavía no está cargado. Aquí tienes lo que ya hay: el histórico de ventas${HAS.facturas ? ' y los registros de facturación' : ''}.`)
  + `<div class="grid g4">
    ${metric('Ventas del mes (histórico)', h.v26 != null ? usd(h.v26) : flag('sin dato'), h.v26 != null ? (h.v25 != null ? `${delta(h.v26, h.v25)} frente a ${usd(h.v25)} en ${DY - 1}` : `Sin dato de ${DY - 1} para comparar`) : `En ${DY - 1}: ${h.v25 != null ? usd(h.v25) : 'sin dato'}`, 'Monto mensual del archivo de histórico de ventas ASOCIM.')}
    ${metric('Cantidad de ventas (P)', h.p26 != null ? int(h.p26) : '—', h.p26 != null ? (h.p25 != null ? `${delta(h.p26, h.p25)} frente a ${int(h.p25)} en ${DY - 1}` : `Sin dato de ${DY - 1} para comparar`) : '', 'Unidad «P» del histórico, pendiente de confirmar.')}
    ${HAS.facturas ? metric(enCurso ? 'Facturado hasta hoy' : 'Facturado en el mes', usd(sum(rows, 'monto')), `${int(rows.length)} registros`, 'Suma de la columna MONTO del libro de facturas del mes. Un registro no confirma por sí solo un cobro.') : ''}
    ${HAS.facturas ? metric('Promedio por registro', rows.length ? usd(Math.round(sum(rows, 'monto') / rows.length)) : '—', `${int(rows.filter(r => r.factura).length)} con número de factura`) : ''}
  </div>`
  + (HAS.facturas && rows.length ? `<div class="grid g2 mt">${panel('Facturado por programa', `${MES[P]} ${DY}`, bars(famTop.map(g => ({l: g.l, v: g.v, tip: `<b>${esc(g.l)}</b><br>${usd(g.v)} · ${g.n} registros`}))), `<a class="btn btn-sm" href="${tbRuta('facturas')}">Ver registros</a>`)}
      ${panel('Por asesor', 'Monto facturado', bars(invBy('asesor', rows).sort((a, b) => b.v - a.v).slice(0, 8).map(g => ({l: titleCase(g.l), v: g.v})), {alt: 1}))}</div>` : '')
  + `<div class="note-gold note-row"><span>El reporte de gestión completo más cercano es el de <b>${repLabel()}</b>.</span><button class="btn btn-sm" type="button" data-period="${repAbs()}">Ver ${repLabel()}</button></div>`;
}
/* ---------- Configuración del mes (lectura) (tablero comercial, líneas 302-352) ---------- */
/* ---------- Configuración mensual (lectura): el tablero solo consume estos valores; se editan en su módulo ---------- */
const cfgKey = m => `${DY}-${String(m + 1).padStart(2, '0')}`;
const DEF_UMBRAL = {verde: 100, ambar: 70, pendienteMax: 50, ocupacionMin: 70, cacMax: null, margenMin: null};
const advKey = a => cleanName(typeof a === 'string' ? a : a.name);
const toCents = v => v == null || v === '' ? null : Math.round(v * 100);
function cfgOf(m = P) {
  const all = A.config(), raw = all[cfgKey(m)] || {};
  return {
    guardada: !!all[cfgKey(m)],
    asesoras: raw.asesoras || {},
    umbrales: {...DEF_UMBRAL, ...(raw.umbrales || {})},
    fin: raw.finanzas || {},
  };
}
/* Meta y logro de cada asesora: lo escrito en la configuración manda; si falta, se usa el reporte de gestión */
function advisorRows(c) {
  const vr = D.valor.rows.slice(0, -1);
  return ADV.map(a => {
    const v = vr.find(r => first(r[0]) === first(a.name)), o = c.asesoras[advKey(a)] || {};
    const meta = o.metaMonto != null ? toCents(o.metaMonto) : v ? v[1] : null;
    const logrado = v ? v[2] : a.vendido;
    return {name: a.name, metaEst: o.metaEstudiantes ?? null, est: a.inscritos, meta, logrado, brecha: meta == null ? null : logrado - meta,
      cumple: meta ? logrado / meta : null, acumMeta: toCents(o.metaAcumMonto), acumEst: o.metaAcumEstudiantes ?? null};
  });
}
const light = (ratio, u) => ratio == null ? null : ratio * 100 >= u.verde ? 'ok' : ratio * 100 >= u.ambar ? 'warn' : 'bad';
const LIGHT = {ok: 'Meta lograda', warn: 'Cerca de la meta', bad: 'Necesita apoyo'};
const lamp = k => k ? `<span class="lt lt-${k}"><i aria-hidden="true"></i>${LIGHT[k]}</span>` : '<span class="muted">Sin meta</span>';
/* Cifras financieras: los valores manuales de la configuración reemplazan a los del archivo (vacío = usar el del archivo) */
function finanzas(c) {
  const f = c.fin, archivo = k => rent && rent[k] ? Math.round(rent[k][1] * 100) : null;
  const pub = toCents(f.publicidad) ?? archivo('Inversión Meta ADS') ?? 0;
  const gop = toCents(f.gastosOperativos) ?? archivo('Gastos Operativos') ?? 0;
  const gven = toCents(f.gastosVenta) ?? 0, cad = toCents(f.costoAdquisicion) ?? 0;
  const bruto = archivo('Ingresos Brutos') ?? GT.vendido;
  const margen = bruto - pub - gop - gven - cad - GT.com10;
  const invertido = pub + gop + cad;
  return {
    pub, gop, gven, cad, bruto, margen, invertido, manual: Object.values(f).some(v => v != null),
    margenPct: bruto ? margen / bruto : null,
    utilidad: C ? margen - C.fijos.total : null,
    cac: GT.inscritos ? invertido / GT.inscritos : null,
    roi: invertido ? (bruto - invertido) / invertido : null,
    costoMV: toCents(f.costoMercadeoVentas) ?? (C ? C.resumen.items[2][1] + gven + cad : null),
  };
}
const repHead = (title, pairs, acts = '', hi = '') => `<div class="mod-h rep-h"><div><h1 class="t-module">${title}</h1>${hi ? `<p class="lede">${hi}</p>` : ''}<dl class="rep-meta">${pairs.map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl></div>${acts ? `<div class="acts">${acts}</div>` : ''}</div>`;
const gapFmt = c => c == null ? '<span class="muted">—</span>' : `<span class="${c >= 0 ? 'up' : 'down'}">${c >= 0 ? '+' : '−'}${usd(Math.abs(c))}</span>`;
/* ---------- Gráficos del inicio (tablero comercial, líneas 354-423) ---------- */
/* ---------- Inicio rediseñado (estilo Notion): preguntas claras, cifras en lenguaje de todos los días y gráfico de la métrica principal ---------- */
/* Medidor de la métrica principal: lo vendido frente a la meta real y la meta ideal */
function gaugeChart({value, real, ideal}) {
  const W = 580, H = 270, cx = 235, cy = 214, r = 160, sw = 28, max = Math.max(ideal, real, value) || 1;
  const ang = v => Math.PI * (1 - Math.min(1, Math.max(0, v) / max));
  const pt = (v, rad) => [cx + rad * Math.cos(ang(v)), cy - rad * Math.sin(ang(v))];
  const arc = (v0, v1, rad) => { const [x0, y0] = pt(v0, rad), [x1, y1] = pt(v1, rad); return `M${x0.toFixed(1)},${y0.toFixed(1)} A${rad},${rad} 0 0 1 ${x1.toFixed(1)},${y1.toFixed(1)}`; };
  const tick = (v, label, sub, dy = 0) => {
    const [a, b] = [pt(v, r - sw / 2 - 8), pt(v, r + sw / 2 + 8)], [tx, ty] = pt(v, r + sw / 2 + 22);
    const anchor = tx < cx - 24 ? 'end' : tx > cx + 24 ? 'start' : 'middle';
    return `<line class="nv-tick" x1="${a[0].toFixed(1)}" y1="${a[1].toFixed(1)}" x2="${b[0].toFixed(1)}" y2="${b[1].toFixed(1)}" data-tip="<b>${label} ${usd(v)}</b><br>${sub}"/><text x="${tx.toFixed(1)}" y="${(ty + dy).toFixed(1)}" text-anchor="${anchor}" class="nv-gl">${label}</text><text x="${tx.toFixed(1)}" y="${(ty + dy + 16).toFixed(1)}" text-anchor="${anchor}" class="nv-gs">${usd(v)}</text>`;
  };
  const done = pct(value / real);
  return `<svg class="nv-gauge" viewBox="0 0 ${W} ${H}" role="img" aria-label="Se vendió ${usd(value)}: ${done} de la meta real de ${usd(real)} y ${pct(value / ideal)} de la meta ideal de ${usd(ideal)}">
    <path class="nv-track" d="${arc(0, max, r)}" stroke-width="${sw}" fill="none"/>
    <path class="nv-fill" d="${arc(0, value, r)}" stroke-width="${sw}" fill="none" stroke-linecap="round" data-tip="<b>Vendido ${usd(value)}</b><br>${done} de la meta real"/>
    ${tick(real, 'Meta real', `Faltan ${usd(Math.max(0, real - value))} para llegar`)}${ideal > real ? tick(ideal, 'Meta ideal', `Faltan ${usd(Math.max(0, ideal - value))} para llegar`, 0) : ''}
    <text x="${cx}" y="${cy - 40}" text-anchor="middle" class="nv-gbig">${done}</text>
    <text x="${cx}" y="${cy - 12}" text-anchor="middle" class="nv-gs">de la meta real</text>
    <text x="${cx - r}" y="${cy + 28}" text-anchor="middle" class="nv-gs">$0</text></svg>`;
}
const nvCard = (l, v, f, what, o = {}) => `<div class="nv-c ${o.tone || ''} ${o.cls || ''}" data-wl="${esc(l)}" data-what="${esc(what || '')}"><h3>${l}</h3><p class="nv-v">${v}</p>${f ? `<p class="nv-f">${f}</p>` : ''}${o.extra || ''}</div>`;
const nvSec = (tag, q, sub) => `<div class="nv-sh"><p class="nv-eyebrow">${tag}</p><h2 class="nv-q">${q}</h2>${sub ? `<p class="nv-sub">${sub}</p>` : ''}</div>`;
const nvMore = (a, b, mes) => `${a >= b ? 'más' : 'menos'} que en ${mes}`;

/* Gráfico principal del inicio: ventas de los últimos meses con la meta del mes de corte (línea + área plana, sin degradado) */
function heroChart({labels, values, prev, meta, big}) {
  const W = big ? 1000 : 680, H = big ? 420 : 264, L = 92, R = 24, T = 20, B = 32, w = W - L - R, ih = H - T - B;
  const all = [...values, ...(prev || []), meta || 0].filter(v => v != null);
  const max = niceMax(Math.max(...all, 1));
  const x = i => L + (labels.length === 1 ? w / 2 : i * w / (labels.length - 1)), y = v => T + ih - v / max * ih;
  let g = '';
  for (let k = 0; k <= 4; k++) { const v = max * k / 4; g += `<line class="hx-gl" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text x="${L - 14}" y="${y(v) + 4}" text-anchor="end" class="hx-ax">${usdK(v)}</text>`; }
  labels.forEach((l, i) => g += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle" class="hx-ax">${esc(l.includes(' ') ? l : l.slice(0, 3))}</text>`);
  if (prev) { const pp = prev.map((v, i) => v == null ? null : [x(i), y(v)]).filter(Boolean); g += `<polyline class="hx-prev" fill="none" points="${pp.map(p => p.join(',')).join(' ')}"/>`; }
  const pts = values.map((v, i) => v == null ? null : [x(i), y(v)]).filter(Boolean);
  if (pts.length) {
    g += `<polygon class="hx-area" points="${pts[0][0]},${T + ih} ${pts.map(p => p.join(',')).join(' ')} ${pts.at(-1)[0]},${T + ih}"/>`;
    g += `<polyline class="hx-line" fill="none" points="${pts.map(p => p.join(',')).join(' ')}"/>`;
  }
  if (meta) {
    const lx = pts.at(-1)?.[0] ?? W - R;
    g += `<line class="hx-meta" x1="${L}" x2="${W - R}" y1="${y(meta)}" y2="${y(meta)}"/><text x="${L + 4}" y="${y(meta) - 8}" class="hx-ml">Meta del mes</text>`;
  }
  pts.forEach((p, i) => g += `<circle cx="${p[0]}" cy="${p[1]}" r="${i === pts.length - 1 ? 6 : 5}" class="${i === pts.length - 1 ? 'hx-pt hx-last' : 'hx-pt'}"/>`);
  labels.forEach((l, i) => {
    const tip = `<b>${esc(l.includes(' ') ? l.replace(/ (\d\d)$/, ' 20$1') : `${l} ${DY}`)}</b><br>Vendido: ${values[i] == null ? 'sin dato' : usd(values[i])}` + (prev && prev[i] != null ? `<br>${esc(l)} ${DY - 1}: ${usd(prev[i])}` : '') + (meta && i === labels.length - 1 ? `<br>Meta real: ${usd(meta)}` : '');
    g += `<rect class="hit" x="${x(i) - w / labels.length / 2}" y="${T}" width="${w / labels.length}" height="${ih}" data-tip="${esc(tip)}"/>`;
  });
  return `<svg class="chart hx-chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="Ventas de ${esc(labels[0])} a ${esc(labels.at(-1))} de ${DY}, con la meta del último mes">${g}</svg>`;
}
/* Cascada de lo vendido a la ganancia: ventas, lo que se descuenta y lo que queda (mismo estilo del gráfico principal) */
function flowChart({steps, big}) {
  const W = big ? 1000 : 680, H = big ? 420 : 264, L = 92, R = 16, T = 26, B = 34, w = W - L - R, ih = H - T - B;
  const max = niceMax(Math.max(...steps.map(s => s.v), 1)), y = v => T + ih - v / max * ih;
  const slot = w / steps.length, bw = Math.min(96, slot * .56), cx = i => L + slot * (i + .5);
  let g = '', run = 0;
  for (let k = 0; k <= 4; k++) { const v = max * k / 4; g += `<line class="hx-gl" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text x="${L - 14}" y="${y(v) + 4}" text-anchor="end" class="hx-ax">${usdK(v)}</text>`; }
  steps.forEach((s, i) => {
    let top, bot;
    if (s.t === 'total') { top = s.v; bot = 0; run = s.v; } else if (s.t === 'minus') { top = run; bot = run - s.v; run = bot; } else { top = Math.max(0, s.v); bot = 0; }
    const yt = y(top), h = Math.max(2, y(bot) - yt), x0 = cx(i) - bw / 2;
    g += `<rect class="hx-fl hx-fl-${s.t} ${s.k ? 'hx-fl-' + s.k : ''}" x="${x0}" y="${yt}" width="${bw}" height="${h}" rx="6"/>`;
    if (i < steps.length - 1) { const nxt = steps[i + 1].t === 'result' ? null : y(s.t === 'minus' ? bot : top); if (nxt != null) g += `<line class="hx-con" x1="${x0 + bw}" x2="${cx(i + 1) - bw / 2}" y1="${nxt}" y2="${nxt}"/>`; }
    g += `<text x="${cx(i)}" y="${yt - 8}" text-anchor="middle" class="hx-vl">${s.t === 'minus' ? '−' : ''}${usd(s.v)}</text>`;
    g += `<text x="${cx(i)}" y="${H - 12}" text-anchor="middle" class="hx-ax">${esc(s.l)}</text>`;
    g += `<rect class="hit" x="${cx(i) - slot / 2}" y="${T}" width="${slot}" height="${ih}" data-tip="${esc(`<b>${esc(s.l)}</b><br>${s.t === 'minus' ? '−' : ''}${usd(s.v)}`)}"/>`;
  });
  return `<svg class="chart hx-chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(steps.map(s => `${s.l}: ${usd(s.v)}`).join(', '))}">${g}</svg>`;
}
/* ---------- Inicio · Reporte de Gestión Comercial ----------
   Es la pantalla de inicio del sistema y sigue el orden pedido:
     1. Responsables del espacio y espacios atendidos (la identidad de la empresa).
     2. Cómo va el mes: la gráfica de ventas con sus indicadores.
     3. El balance del histórico: la tendencia del año.
   El resto del reporte (equipo, publicidad, costos, catálogo) vive en sus módulos. */
const IDENTIDAD = '<!--identidad-del-espacio-->';

V.resumen = {render() {
  if (!HASREP()) return IDENTIDAD + monthView();

  const c = cfgOf(CUT), u = c.umbrales;
  const pend = GT.pendiente, logro = GT.vendido / metaReal, falta = metaReal - GT.vendido;
  const ytdP26 = sum(hist.slice(0, lastIdx + 1), 'p26'), ytdP25 = sum(hist.slice(0, lastIdx + 1), 'p25');
  const ocuLogro = ocu['Logro'], mes = cur.mes.toLowerCase();
  const alta = openIssues().filter(i => i.sev === 'Alta').length;
  const acts = (can('ver.configuracion') ? `<a class="btn btn-sm" href="${tbRuta('configuracion')}">${icon('edit')}Configurar el mes</a>` : '')
    + `<button class="btn" type="button" data-print>${icon('download')}Descargar informe</button>`;

  /* ---------- Encabezado del reporte ---------- */
  const cabeza = `<div class="mod-h hx-top">
      <div>
        <h1 class="t-module">Reporte Gestión Comercial - ${MES[CUT]} ${DY}</h1>
        ${PROPIAS ? `<p class="lede">Hola, ${esc(ME.nombre.split(' ')[0])}. Así va el equipo.</p>` : ''}
      </div>
      <div class="acts">${acts}</div>
    </div>`;

  /* ---------- Cómo va el mes ---------- */
  const win = seriesBack(6), dif = cur.v26 - cur.v25;
  const kpi = (l, v, f, what, o = {}) => `<div class="hx-k ${o.tone || ''}" data-wl="${esc(l)}" data-what="${esc(what)}"><h3>${l}</h3><p class="hx-kv">${v}</p><p class="hx-kf">${f}</p>${o.extra || ''}</div>`;
  const pendAlto = pend / GT.vendido * 100 > u.pendienteMax, ocuBaja = typeof ocuLogro === 'number' && ocuLogro * 100 < u.ocupacionMin;
  const elMes = `<section class="hx hx-primary-report">
      <header class="hx-report-top">
        <span class="hx-report-title"><span class="hx-report-mark" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="12" width="4" height="9" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg></span><span><strong>Ventas de ${mes} de ${DY}</strong><small>Rendimiento comercial y comparación interanual</small></span></span>
        ${logoGrafica('hero')}
      </header>
      <div class="hx-main">
        <div class="hx-txt" data-wl="Ventas del mes" data-what="Suma de la columna «Monto total vendido» de las hojas de cada asesora en el mes de corte, comparada con el mismo mes de ${DY - 1} y con la meta real del reporte de gestión.">
          <p class="hx-label">Ventas de ${mes} de ${DY}</p>
          <p class="hx-big">${usd(cur.v26)}</p>
          <p class="hx-sent"><span class="${dif >= 0 ? 'up' : 'down'}">${dif >= 0 ? '+' : '−'}${usd(Math.abs(dif))}</span> respecto a ${mes} de ${DY - 1}. ${falta > 0 ? `Faltan <b>${usd(falta)}</b> para alcanzar la meta del mes.` : `Superamos la meta del mes por <b>${usd(-falta)}</b>.`}</p>
          <dl class="hx-cmp">
            <div><dt><svg class="hx-sw sw-now" viewBox="0 0 24 8" aria-hidden="true"><line x1="1" y1="4" x2="23" y2="4"/></svg>${MES[CUT]} ${DY}</dt><dd class="hx-cmp-t">Ventas de cada mes</dd></div>
            <div><dt><svg class="hx-sw sw-prev" viewBox="0 0 24 8" aria-hidden="true"><line x1="1" y1="4" x2="23" y2="4"/></svg>${MES[CUT]} ${DY - 1}</dt><dd>${usd(cur.v25)}</dd></div>
            <div><dt><svg class="hx-sw sw-meta" viewBox="0 0 24 8" aria-hidden="true"><line x1="1" y1="4" x2="23" y2="4"/></svg>Meta del mes</dt><dd>${usd(metaReal)}</dd></div>
          </dl>
        </div>
        <div class="hx-c">${chartBox(heroChart, {labels: win.map(h => h.mes), values: win.map(h => h.v26), prev: win.map(h => h.v25), meta: metaReal}, {branding:false})}</div>
      </div>
      <div class="hx-kpis">
        ${kpi('Avance de la meta del mes', pct(logro), falta > 0 ? 'Lo vendido frente a la meta' : 'Meta del mes cumplida', 'Lo vendido en el mes entre la meta real del bloque «Metas de recaudación» del reporte de gestión.', {extra: `<div class="nv-meter"><span style="width:${Math.min(100, logro * 100)}%"></span></div>`})}
        ${kpi('Estudiantes nuevos este mes', int(GT.inscritos), `${delta(cur.p26, cur.p25)} respecto a ${DY - 1} (${int(cur.p25)})`, 'Total de nuevos estudiantes matriculados en el mes de corte. La comparación usa la unidad «P» del histórico.')}
        ${kpi('Estudiantes nuevos en el año', int(ytdP26), `${delta(ytdP26, ytdP25)} respecto a ${DY - 1} (${int(ytdP25)})`, 'Estudiantes matriculados de enero al mes de corte, comparados con el mismo período del año anterior.')}
        ${kpi('Recaudación vendida en el año', usdK(ytd26), `${delta(ytd26, ytd25)} respecto a ${DY - 1} (${usdK(ytd25)})`, 'Suma de las ventas mensuales de enero al mes de corte, comparada con el mismo período del año anterior (no con el año completo).')}
        ${kpi('Pendiente por cobrar del mes', `<span class="${pendAlto ? 'down' : ''}">${usd(pend)}</span>`, `${pct(pend / GT.vendido)} de lo vendido`, `Cartera por cobrar: lo vendido que aún no se ha cobrado. Se marca en rojo cuando pasa de ${u.pendienteMax} % de lo vendido (umbral de la configuración mensual).`)}
        ${kpi('Meta de ocupación del mes', `<span class="${ocuBaja ? 'down' : ''}">${pct(ocuLogro)}</span>`, `${int(ocu['Total Inscrito'])} de ${int(ocu['Meta Real'])} cupos`, `Inscritos entre la meta real de cupos del bloque «Metas de ocupación». Se marca en rojo por debajo de ${u.ocupacionMin} % (umbral de la configuración mensual).`)}
      </div>
    </section>`;

  /* ---------- Balance del histórico ---------- */
  const meses = hist.slice(0, lastIdx + 1).filter(h => h.v26 != null);
  const arriba = meses.filter(h => h.v25 != null && h.v26 > h.v25).length;
  const mejor = meses.reduce((a, h) => h.v26 > a.v26 ? h : a, meses[0]), bajo = meses.reduce((a, h) => h.v26 < a.v26 ? h : a, meses[0]);
  const prom = meses.length ? Math.round(sum(meses, 'v26') / meses.length) : 0;
  const tendencia = `<h2 class="hx-sec">Tendencia del año</h2>
    <section class="hx">
      <div class="hx-main">
        <div class="hx-txt" data-wl="Meses por encima del año anterior" data-what="Meses de ${DY} con ventas mayores que el mismo mes de ${DY - 1}, según el histórico de ventas.">
          <p class="hx-label">Meses con ventas por encima de ${DY - 1}</p>
          <p class="hx-big hx-big-2">${arriba} de ${meses.length}</p>
          <p class="hx-sent">El mejor mes fue <b>${mejor.mes.toLowerCase()}</b> y el más bajo, <b>${bajo.mes.toLowerCase()}</b>.</p>
          <dl class="hx-cmp">
            <div><dt>Mejor mes</dt><dd>${usd(mejor.v26)}</dd></div>
            <div><dt>Mes más bajo</dt><dd>${usd(bajo.v26)}</dd></div>
            <div><dt>Promedio mensual</dt><dd>${usd(prom)}</dd></div>
          </dl>
        </div>
        <div class="hx-c">
          ${chartBox(heroChart, {labels: hist.map(h => h.mes), values: hist.map(h => h.v26), prev: hist.map(h => h.v25)})}
          <div class="hx-legend"><span><svg class="hx-sw sw-now" viewBox="0 0 24 8" aria-hidden="true"><line x1="1" y1="4" x2="23" y2="4"/></svg>${DY}</span><span><svg class="hx-sw sw-prev" viewBox="0 0 24 8" aria-hidden="true"><line x1="1" y1="4" x2="23" y2="4"/></svg>${DY - 1}</span><span class="src">Fuente: histórico de ventas</span></div>
        </div>
      </div>
    </section>`;

  const aviso = HAS.calidad && alta
    ? `<div class="nv-alert">Hay <b>${alta} observacion${alta === 1 ? '' : 'es'} de prioridad alta</b> sin resolver en los archivos de origen que pueden cambiar algunas de estas cifras. <a href="${tbRuta('calidad')}">Revisarlas</a></div>`
    : '';

  return cabeza + IDENTIDAD + elMes + tendencia + aviso;
}};

/* ---------- Configuración mensual (tablero comercial, líneas 596-650) ---------- */
/* =====================================================================================
   Configuración mensual: único lugar donde se editan metas, umbrales y costos manuales
   ===================================================================================== */
const fmtIn = v => v == null ? '' : String(v).replace('.', ',');
const parseIn = (s, nombre, {int: ent, max} = {}) => {
  s = String(s ?? '').trim(); if (!s) return null;
  if (!/^\d+([.,]\d{1,2})?$/.test(s)) throw new Error(`«${nombre}»: escribe solo números, con coma para los decimales (por ejemplo 1385,50) y sin puntos de miles.`);
  const n = Number(s.replace(',', '.'));
  if (ent && !Number.isInteger(n)) throw new Error(`«${nombre}» debe ser un número entero.`);
  if (max != null && n > max) throw new Error(`«${nombre}» no puede pasar de ${max}.`);
  return n;
};
V.configuracion = {render() {
  const c = cfgOf(P), u = c.umbrales, canEdit = can('editar.configuracion'), dis = canEdit ? '' : 'disabled';
  const arch = k => rent && rent[k] ? usd(Math.round(rent[k][1] * 100)) : 'sin dato';
  const raw = A.config()[cfgKey(P)] || {}, fin = raw.finanzas || {};
  const AD = advisorRows(c);
  const inp = (name, val, {ph = '', label} = {}) => `<input name="${name}" inputmode="decimal" autocomplete="off" value="${esc(fmtIn(val))}" placeholder="${esc(ph)}" aria-label="${esc(label || name)}" ${dis}>`;
  const row = (i, r) => `<tr><td>${esc(cleanName(r.name))}</td>
      <td class="n">${inp(`a${i}.metaEstudiantes`, c.asesoras[advKey(r.name)]?.metaEstudiantes, {label: 'Meta en estudiantes de ' + cleanName(r.name)})}</td>
      <td class="n">${inp(`a${i}.metaMonto`, c.asesoras[advKey(r.name)]?.metaMonto ?? (r.meta != null ? r.meta / 100 : null), {label: 'Meta en dólares de ' + cleanName(r.name)})}</td>
      <td class="n">${inp(`a${i}.metaAcumEstudiantes`, c.asesoras[advKey(r.name)]?.metaAcumEstudiantes, {label: 'Meta acumulada en estudiantes de ' + cleanName(r.name)})}</td>
      <td class="n">${inp(`a${i}.metaAcumMonto`, c.asesoras[advKey(r.name)]?.metaAcumMonto, {label: 'Meta acumulada en dólares de ' + cleanName(r.name)})}</td></tr>`;
  const fld = (name, label, val, ph, hint) => `<label class="fld">${label}${inp(name, val, {ph, label})}${hint ? `<small class="hint">${hint}</small>` : ''}</label>`;
  return repHead(`Configuración mensual - ${MES[P]} ${DY}`, [['Período', `${MES[P]} ${DY}`], ['Estado', c.guardada ? 'Con valores propios' : 'Usa los valores del archivo'], ['Quién edita', canEdit ? 'Tu rol puede editar' : 'Solo lectura']], '', 'Aquí parametrizas cada mes. Gestión Comercial solo lee estos valores: ninguna cifra maestra se cambia desde el reporte.')
  + (isProj(P) ? `<div class="note-gold note-row top"><span>${MES[P]} es una proyección: puedes dejar listas sus metas y umbrales antes de que llegue el reporte.</span></div>` : '')
  + `<form class="cfg" data-form="config" novalidate>
    <div>
      ${panel('Umbrales de rendimiento', 'Semáforo de las asesoras y alertas de las tarjetas', `<div class="form">
        <div class="grid g3" style="gap:12px">
          ${fld('u.verde', 'Verde desde (%)', u.verde, '100', 'Cumplimiento que se considera «En meta».')}
          ${fld('u.ambar', 'Ámbar desde (%)', u.ambar, '70', 'Por debajo de este valor es «Crítico».')}
          ${fld('u.pendienteMax', 'Pendiente por cobrar máximo (%)', u.pendienteMax, '50', 'Alerta si supera este % de lo vendido.')}
          ${fld('u.ocupacionMin', 'Ocupación mínima (%)', u.ocupacionMin, '70', 'Alerta si el logro queda por debajo.')}
          ${fld('u.cacMax', 'Costo de adquisición máximo ($)', u.cacMax, 'Sin límite', 'Alerta si lo supera. Vacío: sin alerta.')}
          ${fld('u.margenMin', 'Margen mínimo (%)', u.margenMin, 'Sin límite', 'Alerta si baja de este % de los ingresos.')}
        </div></div>`)}
    </div>
    <div class="mt">${panel('Metas por asesora', 'Cuota del mes y acumulada del año. Vacío en la cuota del mes: se usa la meta del reporte de gestión.', `<div class="tw full"><table class="cfg-t"><thead><tr><th>Asesora</th><th class="n">Meta del mes (estudiantes)</th><th class="n">Meta del mes ($)</th><th class="n">Meta acumulada (estudiantes)</th><th class="n">Meta acumulada ($)</th></tr></thead><tbody>${AD.map((r, i) => row(i, r)).join('')}</tbody></table></div>`)}</div>
    <div class="mt">${panel('Costos y gastos manuales', 'Alimentan el margen, el costo de adquisición y el costo de mercadeo y ventas. Vacío: se usa el valor del archivo. Montos en dólares.', `<div class="grid g3" style="gap:14px">
      ${fld('f.publicidad', 'Publicidad y mercadeo ($)', fin.publicidad, arch('Inversión Meta ADS'), 'Archivo: ' + arch('Inversión Meta ADS'))}
      ${fld('f.gastosOperativos', 'Gastos operativos ($)', fin.gastosOperativos, arch('Gastos Operativos'), 'Archivo: ' + arch('Gastos Operativos'))}
      ${fld('f.gastosVenta', 'Gastos de venta ($)', fin.gastosVenta, '0,00', 'Se restan en el margen.')}
      ${fld('f.costoAdquisicion', 'Costos de adquisición directos ($)', fin.costoAdquisicion, '0,00', 'Se suman al costo por cliente.')}
      ${fld('f.costoMercadeoVentas', 'Total de costos de mercadeo y ventas ($)', fin.costoMercadeoVentas, C ? usd(C.resumen.items[2][1]) : 'sin dato', 'Solo si quieres reemplazar el total del archivo.')}
    </div>`)}</div>
    <p class="fld-err" role="alert"></p>
    ${canEdit ? `<div class="form-ft cfg-ft"><button class="btn" type="button" data-cfg-reset>Volver a los valores del archivo</button><button class="btn btn-cta" type="submit">Guardar ${MES[P].toLowerCase()}</button></div>`
      : `<div class="note-gold">Tu rol ve esta configuración, pero no la cambia. Pídele a Dirección o a Administración que la actualice.</div>`}
  </form>`;
}};
/* ---------- Resto de los módulos del tablero (tablero comercial, líneas 652-803) ---------- */
V.equipo = {render() {
  const cols = [{k: 'name', l: 'Asesora', f: v => esc(cleanName(v)) + (PROPIAS && first(v) === first(ME.asesora) ? ' <span class="sc sc-gray">Tú</span>' : '')}, {k: 'inscritos', l: 'Inscritos', n: 1, f: int}, money('vendido', 'Monto vendido'), money('recurrencia', 'Recurrencia'), money('cuota1', '1.ª cuota'), money('entradas', 'Entradas'), money('pendiente', 'Pendiente'), money('com10', 'Comisión 10 %'), money('com7', 'Vendedor 7 %'), money('com3', 'Gerente 3 %')];
  const max = Math.max(...ADV.flatMap(a => a.counts));
  const sedeHead = D.gc.sedes.map(([n, span]) => `<th colspan="${span}" style="text-align:center">${esc(n)}</th>`).join('');
  const heat = `<div class="tw"><table class="heat"><thead><tr><th></th>${sedeHead}<th></th></tr><tr><th>Asesora</th>${D.gc.codes.map(c => `<th class="n" title="${esc(CAT[c] || c)}">${esc(c)}</th>`).join('')}<th class="n">Total</th></tr></thead><tbody>${ADV.map(a => `<tr><td>${esc(cleanName(a.name))}</td>${a.counts.map((n, i) => `<td class="c" style="${n ? `background:color-mix(in oklab,var(--c-now) ${12 + 70 * n / max}%,var(--surface));color:${n / max > .5 ? '#fff' : 'var(--ink)'}` : 'color:var(--muted-2)'}" data-tip="<b>${esc(cleanName(a.name))} · ${esc(D.gc.codes[i])}</b><br>${esc(CAT[D.gc.codes[i]] || '')}<br>${n} inscritos">${n || '·'}</td>`).join('')}<td class="n strong">${a.inscritos}</td></tr>`).join('')}</tbody><tfoot><tr><td>Total</td>${GT.counts.map(n => `<td class="n">${n}</td>`).join('')}<td class="n">${GT.inscritos}</td></tr></tfoot></table></div>`;
  const cierre = D.cierre.rows.slice(0, -1), ct = D.cierre.rows.at(-1);
  const eff = D.ads.eff.rows.slice(0, -1), et = D.ads.eff.rows.at(-1);
  const recon = D.cvSheets.map(s => { const g = ADV.find(a => first(a.name) === first(s.sheet.slice(3))); const t = s.totals; const k10 = 'Comisiones del Departamento 10%'; return {hoja: s.sheet, vendHoja: t['Monto Total Vendido'], vendRes: g?.vendido, comHoja: t[k10], comRes: g?.com10, pend: t['Pendiente por Cobrar'], pendRes: g?.pendiente}; });
  const diff = (a, b) => a === b ? `<span class="muted">${usd(a)}</span>` : `<span class="down">${usd(a)}</span><small>resumen: ${usd(b)}</small>`;
  const sw = (v, r) => typeof v === 'string' ? flag(v) : r;
  return reportNote() + head('Métricas operativas', 'Rendimiento por asesora', 'Ventas, cobranza y comisiones de cada asesora, qué programas vende y qué tan bien convierte sus contactos.' + (HAS.ventas ? ' Doble clic en una fila para ver sus ventas.' : ''))
  + panel('Resultados por asesora', 'Montos del reporte de gestión comercial', table(cols, ADV, {rowKey: a => 'adv:' + a.name, foot: Object.fromEntries(cols.map(c => [c.k, c.k === 'name' ? 'Total' : c.f(GT[c.k], GT)]))}) + src('GESTIÓN COMERCIAL · B11:Y16'))
  + `<div class="grid g2 mt">
    ${panel('Monto vendido', 'Ordenado de mayor a menor', bars([...ADV].sort((a, b) => b.vendido - a.vendido).map(a => ({l: cleanName(a.name), v: a.vendido, tip: `<b>${esc(cleanName(a.name))}</b><br>Vendido ${usd(a.vendido)}<br>Pendiente ${usd(a.pendiente)} (${pct(a.pendiente / a.vendido)})`}))))}
    ${panel('Pendiente por cobrar', 'Qué parte de lo vendido falta cobrar', bars([...ADV].sort((a, b) => b.pendiente - a.pendiente).map(a => ({l: cleanName(a.name), v: a.pendiente, label: `${usd(a.pendiente)} · ${pct(a.pendiente / a.vendido, 0)}`})), {alt: 1}))}
  </div>`
  + sec('Mezcla de programas') + panel('Inscritos por programa y sede', 'Más intenso, más inscritos. Pasa el cursor para ver el nombre del programa.', heat + src('GESTIÓN COMERCIAL · C9:P16'))
  + sec('Conversión de contactos') + `<div class="grid">
    ${panel('Cierre de ventas por asesora', `Total: ${int(ct[2])} inscritos de ${int(ct[1])} contactos · ${pct(ct[3])}`, table([{k: 0, l: 'Asesora', f: v => esc(cleanName(v))}, {k: 1, l: 'Contactos', n: 1, f: int}, {k: 2, l: 'Inscritos', n: 1, f: int}, {k: 3, l: 'Conversión', n: 1, f: v => pct(v)}], cierre) + src('GESTIÓN COMERCIAL · B28:J35'))}
    ${panel('Contactos por canal', esc(D.ads.eff.title.toLowerCase().replace(/^./, c => c.toUpperCase())), table([{k: 0, l: 'Asesora', f: v => esc(cleanName(v))}, {k: 1, l: 'Meta', n: 1, f: int}, {k: 2, l: 'CRM', n: 1, f: int}, {k: 3, l: 'Formulario', n: 1, f: int}, {k: 4, l: 'Presencial', n: 1, f: int}, {k: 5, l: 'Total', n: 1, f: int}, {k: 6, l: 'Cierres', n: 1, f: v => sw(v, int(v))}, {k: 7, l: 'Logro', n: 1, f: v => sw(v, pct(v))}], eff, {foot: {0: 'Total', 1: int(et[1]), 2: int(et[2]), 3: int(et[3]), 4: int(et[4]), 5: int(et[5]), 6: sw(et[6], int(et[6])), 7: sw(et[7], pct(et[7]))}}) + src('GESTION META ADS · B25:I31'))}
  </div>`
  + (recon.length ? sec('Cuadre con las hojas de cada asesora') + panel('Resumen frente a hoja individual', 'En rojo cuando el total de la hoja no coincide con el resumen de gestión', table([{k: 'hoja', l: 'Hoja'}, {k: 'vendHoja', l: 'Vendido (hoja)', n: 1, f: (v, r) => diff(v, r.vendRes)}, {k: 'pend', l: 'Pendiente (hoja)', n: 1, f: (v, r) => diff(v, r.pendRes)}, {k: 'comHoja', l: 'Comisión 10 % (hoja)', n: 1, f: (v, r) => diff(v, r.comRes)}], recon) + src('Hojas CV · fila de TOTAL')) : '');
}};

V.marketing = {render() {
  const Ad = D.ads, rows = Ad.rows.map(r => Object.fromEntries(['campana', 'resp', 'inv', 'diario', 'ini', 'fin', 'dias', 'obj', 'target', 'res', 'ventas', 'cpl', 'cpc'].map((k, i) => [k, r[i]])));
  const fl = v => typeof v === 'string' ? flag(v) : int(v);
  const tot = pup['Total de Pupitres'], segs = [['Ocupados', pup['Pupitres Ocupados'], 'var(--c-deep)'], ['Vendidos este mes', pup['Pupitres Vendidos'], 'var(--c-now)'], ['Disponibles', pup['Pupitres Disponibles'], 'var(--fill)']];
  const ocupacion = panel('Ocupación de cupos', `${pct(pup['De Ocupación'])} de ${int(tot)} pupitres en uso`, `<div class="stack" role="img" aria-label="Ocupación de pupitres">${segs.map(([l, v, c]) => `<span style="width:${100 * v / tot}%;background:${c}" data-tip="<b>${l}</b><br>${int(v)} · ${pct(v / tot)}"></span>`).join('')}</div><div class="legend" style="margin-top:12px">${segs.map(([l, v, c]) => `<span><i style="background:${c};box-shadow:inset 0 0 0 1px var(--hair)"></i>${l}: ${int(v)}</span>`).join('')}</div>
      <dl class="kv" style="margin-top:20px">${D.metas.ocupacion.items.map(([k, v]) => `<dt>${esc(k.replace(':', ''))}</dt><dd>${/Logro/.test(k) ? pct(v) : int(v)}</dd>`).join('')}</dl>` + src('GESTIÓN COMERCIAL · N23:Q35'));
  return reportNote() + head(esc(Ad.title.replace(/^\W+/, '')), 'Embudo y publicidad', `Cuánto se invirtió en campañas, cuántos contactos llegan por canal y cómo se traducen en inscripciones${rent ? ' y rentabilidad' : ''}.`)
  + `<div class="grid g4">
    ${metric('Inversión en campañas', usd(Ad.total.inversion), `${rows.length} campañas · ${int(sum(rows, 'dias'))} días activas`, 'Suma de la inversión de las campañas Meta ADS cargadas en la hoja de gestión de publicidad.')}
    ${metric('Contactos totales', int(Ad.eff.rows.at(-1)[5]), 'Meta ADS + CRM + formulario + presencial', 'Total de contactos (leads) del equipo por todos los canales.')}
    ${metric('Costo por contacto', flag('sin datos'), 'Las campañas no tienen resultados cargados', 'Inversión entre contactos de cada campaña. Sin resultados por campaña no se puede calcular.')}
    ${rent ? metric('Inversión usada en rentabilidad', usd(Math.round(rent['Inversión Meta ADS'][1] * 100)), 'La que usan el retorno y el costo por adquisición', 'Cifra de inversión del bloque de rentabilidad. Cuando no coincide con la suma de campañas, manda la del reporte.') : metric('Inscritos del mes', int(GT.inscritos), `Conversión ${pct(D.cierre.rows.at(-1)[3])}`)}
  </div>`
  + `<div class="mt">${panel('Campañas Meta ADS', 'Inversión, duración y público de cada campaña', table([{k: 'campana', l: 'Campaña', wrap: 1, f: (v, r) => `${esc(v)}<small>${esc(r.obj)} · ${esc(r.target)}</small>`}, {k: 'resp', l: 'Responsable'}, money('inv', 'Inversión'), money('diario', 'Gasto diario'), {k: 'ini', l: 'Período', f: (v, r) => `${dmy(v)} – ${dmy(r.fin)}`}, {k: 'dias', l: 'Días', n: 1, f: int}, {k: 'res', l: 'Resultados', n: 1, f: v => v == null ? '<span class="muted">—</span>' : int(v)}, {k: 'ventas', l: 'Ventas', n: 1, f: v => v == null ? '<span class="muted">—</span>' : int(v)}, {k: 'cpl', l: 'Costo por contacto', n: 1, f: fl}, {k: 'cpc', l: 'Costo por venta', n: 1, f: fl}], rows, {foot: {campana: esc(Ad.total.label), inv: usd(Ad.total.inversion), diario: fl(Ad.total.gastoDiario), res: int(Ad.total.resultados), ventas: int(Ad.total.ventas), cpl: fl(Ad.total.cpl), cpc: fl(Ad.total.cpc)}}) + `<dl class="kv" style="margin-top:16px">${Ad.legend.items.map(([k, v]) => `<dt>${esc(k)}</dt><dd style="font-weight:400">${esc(v)}</dd>`).join('')}</dl>` + src('GESTION META ADS · B9:N22, L24:M27'))}</div>`
  + `<div class="grid g2 mt">
    ${panel('Inversión por campaña', 'Dólares', bars(rows.map((r, i) => ({l: `${i + 1}. ${r.campana.split('|')[0].replace('Campaña ', '').trim()}`, v: r.inv, tip: `<b>${esc(r.campana)}</b><br>${usd(r.inv)} en ${r.dias} días`}))))}
    ${panel('Contactos por canal', 'Suma del equipo', bars(['Meta', 'CRM', 'Formulario', 'Presencial'].map((l, i) => ({l, v: Ad.eff.rows.at(-1)[i + 1], label: int(Ad.eff.rows.at(-1)[i + 1])})), {fmt: int, alt: 1}))}
  </div>`
  + (rent ? sec('Rentabilidad del mes') + `<div class="grid g2">
    ${panel(esc(D.rent.title), 'Valores e indicadores tal como están en el archivo', table([{k: 0, l: 'Indicador'}, {k: 1, l: 'Valor', n: 1, f: (v, r) => /CPA|Ingresos|Inversi|Gastos|Margen/.test(r[0]) ? usd(Math.round(v * 100)) : f2.format(v) + ' veces'}, {k: 2, l: 'Indicador (%)', n: 1, f: v => typeof v === 'number' ? pct(v) : '—'}], D.rent.rows) + `<p class="note-gold">El margen como porcentaje divide entre las comisiones; sobre los ingresos sería <b>${pct(rent['Margen de Utilidad'][1] / rent['Ingresos Brutos'][1])}</b>. El retorno no incluye nómina ni herramientas.</p>` + src('GESTIÓN COMERCIAL · B37:J44'))}
    ${ocupacion}</div>` : `<div class="mt">${ocupacion}</div>`);
}};

V.tendencias = {render() {
  const months = hist.map(h => h.mes);
  const im = invMonths.map(m => ({m, rows: INV.filter(r => r.mes === m)}));
  const va = D.varAnual, mk = im.findIndex(x => x.m === P + 1);
  return head('Tendencias', 'Cómo se mueve el negocio', `Histórico mensual de ventas ${DY - 1}–${DY}${HAS.facturas ? ' y movimiento de la facturación de 2026' : ''}. El mes elegido arriba queda resaltado; doble clic en un gráfico para verlo en grande.`)
  + `<div class="grid g2">
    ${panel('Ventas en dólares', 'Monto mensual', lineLegendHtml() + chartBox(lineChart, {mark: P, labels: months, series: [{name: String(DY), values: hist.map(h => h.v26), color: 'var(--c-now)'}, {name: String(DY - 1), values: hist.map(h => h.v25), color: 'var(--c-prev)', dash: 1}]}))}
    ${panel('Ventas en cantidad (P)', 'Unidad del archivo, pendiente de confirmar', lineLegendHtml() + chartBox(lineChart, {mark: P, labels: months, fmt: int, tickFmt: v => f0.format(v), series: [{name: String(DY), values: hist.map(h => h.p26), color: 'var(--c-now)'}, {name: String(DY - 1), values: hist.map(h => h.p25), color: 'var(--c-prev)', dash: 1}]}))}
  </div>`
  + `<div class="mt">${panel('Histórico de ventas', 'Valores y variaciones del archivo', table([{k: 'mes', l: 'Mes'}, money('v25', `${DY - 1} ($)`), {k: 'p25', l: `${DY - 1} (P)`, n: 1, f: int}, money('v26', `${DY} ($)`), {k: 'p26', l: `${DY} (P)`, n: 1, f: int}, {k: 'dv', l: 'Variación ($)', n: 1, f: v => v == null ? '—' : `<span class="${v >= 0 ? 'up' : 'down'}">${pct(v)}</span>`}, {k: 'dp', l: 'Variación (P)', n: 1, f: v => v == null ? '—' : `<span class="${v >= 0 ? 'up' : 'down'}">${pct(v)}</span>`}], hist, {foot: {mes: esc(D.hist.total[0]), v25: usd(D.hist.total[1]), p25: int(D.hist.total[2]), v26: usd(D.hist.total[3]), p26: int(D.hist.total[4]), dv: pct(D.hist.total[5]), dp: pct(D.hist.total[6])}}) + `<p class="note-gold">El total anual compara el año completo de ${DY - 1} con lo que va de ${DY}. En el mismo período (enero–${cur.mes.toLowerCase()}): ${DY} ${usd(ytd26)} frente a ${DY - 1} ${usd(ytd25)}, <b>${pct(ytd26 / ytd25 - 1)}</b>.</p>` + src('Formatos de Histórico de Ventas'))}</div>`
  + (HAS.facturas ? sec(`Facturación ${INVY}`) + `<div class="grid g2">
    ${panel('Monto facturado por mes', 'Suma de la columna MONTO', chartBox(colChart, {mark: mk >= 0 ? mk : null, labels: im.map(x => MES[x.m - 1]), values: im.map(x => sum(x.rows, 'monto')), tips: im.map(x => `<b>${MES[x.m - 1]}</b><br>${usd(sum(x.rows, 'monto'))}<br>${x.rows.length} registros`)}) + `<p class="src">${MES[invMonths.at(-1) - 1]} está en curso en el archivo.</p>`, `<a class="btn btn-sm" href="${tbRuta('facturas')}">Ver registros</a>`)}
    ${panel('Registros de facturación por mes', 'Cantidad de filas con cliente', chartBox(colChart, {mark: mk >= 0 ? mk : null, labels: im.map(x => MES[x.m - 1]), values: im.map(x => x.rows.length), fmt: int, tickFmt: v => f0.format(v), tips: im.map(x => `<b>${MES[x.m - 1]}</b><br>${x.rows.length} registros<br>Promedio ${usd(Math.round(sum(x.rows, 'monto') / x.rows.length))}`)}))}
  </div>` : '')
  + sec('Variación anual del reporte de gestión') + `<div class="grid g2">${[va.ventas, va.inventario].map((b, j) => panel(esc(b.title.trim()), 'Rótulos tal como están en el archivo', `<dl class="kv">${b.items.map(([k, v], i) => `<dt>${esc(k)}</dt><dd>${i === 2 ? pct(v) : j ? int(v) : usd(Math.round(v * 100))}</dd>`).join('')}</dl><p class="src">Compara ${MES[CUT].toLowerCase()} de ${DY - 1} con ${MES[CUT].toLowerCase()} de ${DY}, tal como lo trae el reporte.</p>` + src('GESTIÓN COMERCIAL · N37:Q45'))).join('')}</div>`;
}};

const dateCell = (v, r) => v ? esc(dmy(v)) : flag(r.fechaOrigen);
const SALE_COLS = [{k: 'fecha', l: 'Fecha', f: dateCell}, {k: 'hoja', l: 'Asesora'}, {k: 'cliente', l: 'Cliente', f: (v, r) => `${esc(v)}<small>C.I. ${esc(r.cedula || '—')}</small>`}, {k: 'producto', l: 'Programa', f: v => `<span title="${esc(CAT[v] || '')}">${esc(v)}</span>`},
  money('montoTotal', 'Monto total'), money('recurrencia', 'Recurrencia'), money('cuota1', '1.ª cuota'), money('inscripcion', 'Inscripción'), money('entradas', 'Entradas'), money('solvencia', 'Solvencia anterior'), {k: 'mesCulminacion', l: 'Culmina servicio anterior'}, money('pendiente', 'Pendiente'), {k: 'factura', l: 'N.º factura'}, money('com10', 'Comisión 10 %'), money('com7', '7 %'), money('com3', '3 %'), {k: 'fila', l: 'Fila', n: 1}];
V.ventas = {count: SALES.length, per: 25, filtered() {
  const s = state.ventas, q = norm(s.q);
  return SALES.filter(r => (!s.hoja || r.hoja === s.hoja) && (!s.producto || r.producto === s.producto) && (!q || norm(`${r.cliente} ${r.producto} ${r.factura} ${r.hoja}`).includes(q)));
}, region() {
  const s = state.ventas, rows = sortRows(this.filtered(), s), per = this.per;
  const foot = {fecha: 'Total filtrado', cliente: int(rows.length) + ' ventas'};
  SALE_COLS.filter(c => c.money).forEach(c => foot[c.k] = rows.some(r => r[c.k] != null) ? usd(sum(rows, c.k)) : '—');
  return `<div class="grid g4 mb">${metric('Ventas', int(rows.length))}${metric('Monto total', usd(sum(rows, 'montoTotal')))}${metric('Pendiente', usd(sum(rows, 'pendiente')))}${metric('Con número de factura', pct(rows.filter(r => r.factura).length / (rows.length || 1), 0))}</div>`
    + table(PROPIAS ? SALE_COLS.filter(c => c.k !== 'hoja') : SALE_COLS, rows.slice(s.p * per, s.p * per + per), {foot, sortable: 1, sortKey: s.sk, sortDir: s.sd, rowKey: r => 'sale:' + r.id}) + pager(s, rows.length, per);
}, render() {
  const s = state.ventas;
  return reportNote() + head(PROPIAS ? `Detalle · hoja de ${esc(ME.asesora)}` : 'Detalle · hojas por asesora', PROPIAS ? `Tus ventas de ${cur.mes.toLowerCase()}` : `Ventas de ${cur.mes.toLowerCase()}`, `${PROPIAS ? `Tus ${SALES.length} ventas registradas` : `Las ${SALES.length} ventas registradas en las hojas de cada asesora`}, con todos sus montos. La cédula se muestra parcial para proteger al cliente. Doble clic o doble toque en una fila para ver el detalle.`, csvBtn('ventas'))
  + `<div class="tools">${PROPIAS ? '' : sel('hoja', 'Asesora', HOJAS.map(v => [v, v]), s.hoja)}${sel('producto', 'Programa', [...new Set(SALES.map(r => r.producto))].sort().map(v => [v, `${v} · ${CAT[v] || ''}`]), s.producto)}${search(s.q || '', 'Cliente, programa o factura')}${clearBtn(s)}</div>
  <div id="region">${this.region()}</div>` + src(`${esc(D.gc.title.split('|')[0].trim())} · hojas CV`);
}};

const INV_COLS = [{k: 'fecha', l: 'Fecha', f: dateCell}, {k: 'mes', l: 'Hoja', f: v => MES[v - 1]}, {k: 'asesor', l: 'Asesor', f: v => esc(titleCase(v))}, {k: 'cliente', l: 'Cliente', f: (v, r) => `${esc(v)}<small>C.I. ${esc(r.cedula || '—')}</small>`},
  {k: 'factura', l: 'N.º factura', f: v => v ? esc(v) : '<span class="muted">—</span>'}, money('monto', 'Monto'), {k: 'programa', l: 'Programa', f: (v, r) => `${esc(v || '—')}${r.familia.toLowerCase() !== String(v).toLowerCase() ? `<small>${esc(r.familia)}</small>` : ''}`}, {k: 'lapso', l: 'Lapso'}, {k: 'horario', l: 'Horario'}, {k: 'telefono', l: 'Teléfono'}, {k: 'correo', l: 'Correo'}, {k: 'fila', l: 'Fila', n: 1}];
INV.forEach((r, i) => r._i = i);
V.facturas = {count: INV.length, per: 25, filtered() {
  const s = state.facturas, q = norm(s.q);
  return INV.filter(r => (!s.mes || r.mes === +s.mes) && (!s.asesor || r.asesor === s.asesor) && (!s.familia || r.familia === s.familia) && (!q || norm(`${r.cliente} ${r.programa} ${r.factura} ${r.lapso}`).includes(q)));
}, region() {
  const s = state.facturas, rows = sortRows(this.filtered(), s), per = this.per, tot = sum(rows, 'monto');
  const fam = invBy('familia', rows).sort((a, b) => b.v - a.v), adv = invBy('asesor', rows).sort((a, b) => b.v - a.v);
  const top = (list, n) => list.length > n ? [...list.slice(0, n - 1), list.slice(n - 1).reduce((o, g) => ({l: `Otros (${list.length - n + 1})`, n: o.n + g.n, v: o.v + g.v}), {n: 0, v: 0})] : list;
  const empty = s.mes && isProj(+s.mes - 1) ? `<b>${MES[+s.mes - 1]} todavía no tiene registros</b>Es el próximo mes. <button class="btn btn-sm" type="button" data-clear>Ver todo el año</button>` : undefined;
  return `<div class="grid g4 mb">${metric('Registros', int(rows.length))}${metric('Monto facturado', usd(tot))}${metric('Promedio por registro', usd(rows.length ? Math.round(tot / rows.length) : null))}${metric('Con número de factura', int(rows.filter(r => r.factura).length), pct(rows.filter(r => r.factura).length / (rows.length || 1)) + ' del total')}</div>
  ${rows.length ? `<div class="grid g2 mb">${panel('Por familia de programa', 'Monto facturado', bars(top(fam, 10).map(g => ({l: g.l, v: g.v, tip: `<b>${esc(g.l)}</b><br>${usd(g.v)} · ${g.n} registros`}))))}${panel('Por asesor', 'Monto facturado', bars(top(adv, 10).map(g => ({l: titleCase(g.l), v: g.v, tip: `<b>${esc(g.l)}</b><br>${usd(g.v)} · ${g.n} registros`})), {alt: 1}))}</div>` : ''}`
  + table(INV_COLS, rows.slice(s.p * per, s.p * per + per), {foot: {fecha: 'Total filtrado', monto: usd(tot)}, sortable: 1, sortKey: s.sk, sortDir: s.sd, rowKey: r => 'inv:' + r._i, empty}) + pager(s, rows.length, per);
}, render() {
  const s = state.facturas;
  return head(`Registro documental · ${MES[invMonths[0] - 1].toLowerCase()} a ${MES[invMonths.at(-1) - 1].toLowerCase()} 2026`, 'Facturación', `Los ${int(INV.length)} registros de los libros mensuales. El mes elegido arriba filtra la tabla. Un registro no confirma por sí solo un cobro ni una factura fiscal. Cédula, teléfono y correo aparecen parciales.`, csvBtn('facturas'))
  + `<div class="tools">${sel('mes', 'Mes', [...invMonths.map(m => [m, MES[m - 1]]), ...(isProj(P) && !invMonths.includes(P + 1) ? [[P + 1, MES[P] + ' (proyección)']] : [])], s.mes, 'Todo el año')}${sel('asesor', 'Asesor', [...new Set(INV.map(r => r.asesor))].sort().map(v => [v, titleCase(v)]), s.asesor)}${sel('familia', 'Programa', [...new Set(INV.map(r => r.familia))].sort().map(v => [v, v]), s.familia)}${search(s.q || '', 'Cliente, programa, lapso o factura')}${clearBtn(s)}</div>
  <div id="region">${this.region()}</div>` + src(`Libros de facturación ${INVY} · ${D.inv.sheets.length} hojas mensuales`);
}};

V.costos = {render() {
  const t3 = (t, extra) => table([{k: 0, l: t.cols[0]}, {k: 1, l: t.cols[1]}, {k: 2, l: t.cols[2], n: 1, f: (v, r) => usd(v) + (r[3] ? `<small>escrito como «${esc(r[3])}»</small>` : '')}], t.rows, extra);
  const fixedReal = C.nomina.total + C.herr.totalReal;
  return reportNote() + head(esc(C.title), 'Costos de mercadeo y ventas', 'Gastos fijos (nómina, herramientas y servicios), gastos variables (publicidad, interfaz de WhatsApp y comisiones) y la inversión prevista. Se muestran los totales del archivo y, cuando no cuadran, la suma real de las líneas.')
  + `<div class="grid g4">
    ${metric(esc(C.resumen.items[2][0]), usd(C.resumen.items[2][1]), 'Total del archivo', 'Total de la estructura de costos tal como lo calcula el archivo.')}
    ${metric(esc(C.fijos.label), usd(C.fijos.total), `Suma real de las líneas: <b class="strong">${usd(fixedReal)}</b>`, 'Nómina más herramientas y servicios. El total de herramientas del archivo deja fuera dos líneas de Digitel.', fixedReal !== C.fijos.total ? 'micro-red' : '')}
    ${metric(esc(C.variables.label), usd(C.variables.total), 'Publicidad, WhatsApp y comisiones', 'Gastos que cambian con el volumen de ventas y campañas.')}
    ${metric(esc(C.proximos.label), usd(C.proximos.total), `Suma real: <b class="strong">${usd(C.proximos.totalReal)}</b>`, 'Gastos previstos. Tres montos están escritos como texto, por eso la fórmula del archivo no los suma.', C.proximos.totalReal !== C.proximos.total ? 'micro-red' : '')}
  </div>`
  + sec(esc(C.fixedTitle)) + `<div class="grid g2">
    ${panel(esc(C.nomina.title.replace('Nomina', 'Nómina')), `${C.nomina.rows.length} cargos`, t3(C.nomina, {foot: {0: esc(C.nomina.totalLabel), 2: usd(C.nomina.total)}}) + src('Estructura de Costo · B7:D17'))}
    ${panel(esc(C.herr.title), `${C.herr.rows.length} servicios · líneas telefónicas parciales`, t3(C.herr, {foot: {0: 'Total del archivo / suma real', 2: `${usd(C.herr.total)}<small>${usd(C.herr.totalReal)}</small>`}}) + `<p class="note-gold"><code>${esc(C.herr.formula)}</code> deja fuera las dos últimas líneas de Digitel (${usd(C.herr.totalReal - C.herr.total)}).</p>` + src('Estructura de Costo · B18:D31'))}
  </div>`
  + sec(esc(C.varTitle)) + `<div class="grid g3">
    ${panel(esc(C.inversion.title), 'Inversión del mes', t3(C.inversion) + src('Estructura de Costo · F7:H10'))}
    ${panel(esc(C.comisiones.title), 'Cifra distinta al resumen de gestión', t3(C.comisiones) + `<p class="src" style="color:var(--muted)">Resumen de gestión: ${usd(GT.com10)}</p>` + src('Estructura de Costo · F11:H14'))}
    ${panel(esc(C.resumen.title), 'Totales del archivo', `<dl class="kv">${C.resumen.items.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${usd(v)}</dd>`).join('')}</dl>` + src('Estructura de Costo · F16:H19'))}
  </div>`
  + `<div class="mt">${panel(esc(C.proximos.title), 'Inversión prevista', t3(C.proximos, {foot: {0: esc(C.proximos.label) + ' / suma real', 2: `${usd(C.proximos.total)}<small>${usd(C.proximos.totalReal)}</small>`}}) + src('Estructura de Costo · F21:H27'))}</div>`;
}};

V.catalogo = {render() {
  const sold = Object.fromEntries(D.gc.codes.map((c, i) => [c, GT.counts[i]]));
  const fam = Object.fromEntries(invBy('familia').map(g => [g.l, g]));
  const rows = D.catalog.rows.map(r => ({code: r[0], name: r[1], mensual: r[2], dur: r[3], sold: sold[r[0]], inv: fam[r[0]]}));
  return reportNote() + head(esc(D.catalog.title.toLowerCase().replace(/^./, c => c.toUpperCase())), 'Catálogo de programas', `Programas, mensualidad y duración según la leyenda del reporte, con lo inscrito en el mes${HAS.facturas ? ' y lo facturado en el año' : ''}.${HAS.ventas ? ' Doble clic en un programa para ver sus ventas.' : ''}`)
  + panel('Programas', `${rows.length} acrónimos`, table([{k: 'code', l: 'Acrónimo', f: v => `<b class="strong">${esc(v)}</b>`}, {k: 'name', l: 'Programa', wrap: 1}, {k: 'mensual', l: 'Mensualidad', n: 1, f: v => typeof v === 'number' ? usd(v * 100) : esc(v)}, {k: 'dur', l: 'Duración'}, {k: 'sold', l: `Inscritos ${cur.mes.slice(0, 4).toLowerCase()}.`, n: 1, f: v => v == null ? '<span class="muted">no se cuenta</span>' : int(v)}, ...(HAS.facturas ? [{k: 'inv', l: 'Registros facturados 2026', n: 1, f: v => v ? `${int(v.n)}<small>${usd(v.v)}</small>` : '<span class="muted">—</span>'}] : [])], rows, {rowKey: r => 'prog:' + r.code}) + src('GESTIÓN COMERCIAL · S18:Y34' + (HAS.facturas ? ' · FACTURAS 2026' : '')))
  ;
}};

V.calidad = {count: ISSUES.length, region() {
  const s = state.calidad, rank = {Alta: 0, Media: 1, Baja: 2}, N = notes(), edit = can('editar.observaciones');
  const list = ISSUES.filter(i => !s.sev || (s.sev === 'resueltas' ? N[i.id]?.resuelta : i.sev === s.sev && !N[i.id]?.resuelta)).sort((a, b) => rank[a.sev] - rank[b.sev]);
  const cls = {Alta: 'sc-bad', Media: 'sc-warn', Baja: 'sc-gray'};
  return `<section class="panel">${list.map(i => { const n = N[i.id]; return `<article class="issue ${n?.resuelta ? 'is-done' : ''}"><span class="sc ${n?.resuelta ? 'sc-ok' : cls[i.sev]}">${n?.resuelta ? 'Resuelta' : i.sev}</span><div><h3>${esc(i.title)} <span class="muted" style="font-weight:400">· ${esc(i.area)}</span></h3><p>${esc(i.detail)}</p><code>${esc(i.src)}</code>${n?.resuelta ? `<p class="src">Marcada como resuelta por ${esc(n.por)} el ${stamp(n.ts)}</p>` : ''}${edit ? `<div class="issue-act"><button class="btn btn-sm ${n?.resuelta ? '' : 'btn-ok'}" type="button" data-note="${esc(i.id)}" data-done="${n?.resuelta ? 0 : 1}">${icon(n?.resuelta ? 'refresh' : 'check')}${n?.resuelta ? 'Reabrir' : 'Marcar como resuelta'}</button></div>` : ''}</div></article>`; }).join('') || '<p class="empty"><b>Sin observaciones en este filtro</b></p>'}</section>`;
}, render() {
  const s = state.calidad, N = notes();
  const srcs = (D.sources || []).flatMap(f => f.sheets.map(x => ({file: f.file, ...x})));
  const T = {cells: sum(srcs, 'cells'), captured: sum(srcs, 'captured'), filler: sum(srcs, 'filler'), un: sum(srcs.map(x => x.unmapped.length))};
  const n = sev => ISSUES.filter(i => i.sev === sev && !N[i.id]?.resuelta).length, done = ISSUES.filter(i => N[i.id]?.resuelta).length;
  return head('Integridad de la información', 'Calidad de datos', `Todo valor de los Excel quedó cargado aquí. Aquí está la prueba celda por celda y lo que conviene corregir en los archivos de origen antes de cerrar números.${can('editar.observaciones') ? ' Cuando corrijas una, márcala como resuelta (necesitas abrir la edición).' : ''}`)
  + `<div class="grid g4">${metric('Celdas con contenido', int(T.cells), `${(D.sources || []).length} libros · ${srcs.length} hojas`)}${metric('Celdas incorporadas', int(T.captured), pct(T.captured / T.cells) + ' del total')}${metric('Observaciones abiertas', int(ISSUES.length - done), `${n('Alta')} de prioridad alta · ${done} resueltas`, '', n('Alta') ? 'micro-red' : '')}${metric('Valores sin incorporar', int(T.un), T.un ? 'Revisar' : 'Ninguno', '', T.un ? 'micro-red' : '')}</div>`
  + sec('Observaciones') + `<div class="tools">${seg('sev', [['', 'Todas', ISSUES.length - done], ['Alta', 'Alta', n('Alta')], ['Media', 'Media', n('Media')], ['Baja', 'Baja', n('Baja')], ['resueltas', 'Resueltas', done]], s.sev, 'Prioridad')}</div>
  <div id="region">${this.region()}</div>`
  + sec('Conciliación por hoja') + panel('Celdas de origen frente a celdas incorporadas', 'Relleno: celdas de plantilla en filas sin cliente (numeración, nombre de la asesora, guiones) y fórmulas que dan 0 o error sin datos', table([{k: 'file', l: 'Libro', f: v => `<span title="${esc(v)}">${esc(v.length > 34 ? v.slice(0, 32) + '…' : v)}</span>`}, {k: 'sheet', l: 'Hoja'}, {k: 'cells', l: 'Celdas', n: 1, f: int}, {k: 'captured', l: 'Incorporadas', n: 1, f: int}, {k: 'filler', l: 'Relleno', n: 1, f: int}, {k: 'unmapped', l: 'Sin incorporar', n: 1, f: v => v.length ? `<span class="down">${v.length}</span>` : '0'}], srcs, {foot: {sheet: 'Total', cells: int(T.cells), captured: int(T.captured), filler: int(T.filler), unmapped: int(T.un)}}))
  + `<div class="note-gold">Huellas SHA-256 de los archivos: ${(D.sources || []).map(f => `${esc(f.file)} <code>${f.sha256.slice(0, 12)}…</code>`).join(' · ')}. Extracción: ${esc(String(D.meta?.generated || '—').replace('T', ' '))}.</div>`;
}};

/* ---------- Puente con Agora Digital App ---------- */
let vistaActual = 'resumen';
const claveMes = ym => { const y = +String(ym).slice(0, 4), m = +String(ym).slice(5, 7) - 1; return isFinite(y) && m >= 0 && m <= 11 ? absOf(y, m) : null; };

return {
  V, state, CHARTS,
  vista(id) { if (id) vistaActual = id; return vistaActual; },
  mesActual: () => keyOf(DY, P),
  /* 2.1.17: los programas del catálogo del reporte, para pasarlos a Productos y servicios. */
  catalogoFilas: () => ((D.catalog && D.catalog.rows) || []).map(r => ({codigo: String(r[0] || ''), nombre: String(r[1] || ''), mensual: typeof r[2] === 'number' ? r[2] : null, duracion: String(r[3] || '')})),
  hayReporte: ym => !!REPS[String(ym)],
  acomodar() { placeThumbs(document); },
  mesLargo: () => `${MES[P].toLowerCase()} de ${DY}`,
  claveConfig: () => cfgKey(P),
  /* El mes lo manda la barra superior: se acomoda sin repintar (el panel ya repinta). */
  irAlMes(ym) {
    const a = claveMes(ym); if (a == null) return;
    const lim = Math.max(MINA, Math.min(MAXA, a));
    if (lim === absOf(DY, P)) return;
    DY = Math.floor(lim / 12); P = lim % 12;
    applyPeriod();
    if (HAS.facturas) { state.facturas.mes = invMonths.includes(P + 1) ? String(P + 1) : ''; state.facturas.p = 0; }
  },
  irAbsoluto(a) { setAbs(a, true); },
  disponible(id) {
    if (id === 'configuracion') return can('ver.configuracion');
    if (id === 'ventas') return HAS.ventas && (can('ver.ventas') || can('ver.ventas.propias'));
    if (id === 'facturas') return !!HAS.facturas;
    if (id === 'costos') return !!HAS.costos;
    if (id === 'calidad') return !!HAS.calidad;
    return true;
  },
  exportar(clave) {
    if (clave === 'ventas') return csv('gestion-ventas-' + cur.mes.toLowerCase(), SALE_COLS, sortRows(V.ventas.filtered(), state.ventas), 'Ventas');
    if (clave === 'facturas') return csv('gestion-facturacion-' + DY, INV_COLS, sortRows(V.facturas.filtered(), state.facturas).map(r => ({...r, mes: MES[r.mes - 1]})), 'Facturación');
  },
  /* 2.1.17 · datos: filas completas y filtradas para el Excel de las tablas dinámicas (04h). */
  puedeExportar: () => can('editar.exportar'),
  filas(vista) {
    const k = {tc_ventas: 'ventas', tc_facturas: 'facturas'}[vista]; if (!k || !can('editar.exportar')) return null;
    const cols = k === 'ventas' ? (PROPIAS ? SALE_COLS.filter(c => c.k !== 'hoja') : SALE_COLS) : INV_COLS;
    return {columnas: cols.map(c => ({clave: c.k, titulo: c.l})), filas: sortRows(V[k].filtered(), state[k]).map(r => Object.fromEntries(cols.map(c => [c.k, c.money ? (r[c.k] == null ? '' : r[c.k] / 100) : k === 'facturas' && c.k === 'mes' ? MES[r.mes - 1] : (r[c.k] ?? '')])))};
  },
  explicar(el) { openModal(modalHead(esc(el.dataset.wl || 'De dónde sale')) + `<p style="margin:0">${esc(el.dataset.what)}</p>`); },
  graficoGrande(el) {
    const par = CHARTS[el.dataset.chart]; if (!par) return;
    const [fn, opts] = par;
    const t = el.closest('.panel') && el.closest('.panel').querySelector('.t-panel');
    openModal(modalHead(esc(t ? t.textContent : 'Gráfico'), 'Pasa el cursor sobre el gráfico para ver cada valor.')
      + `<div class="chart-big">${logoGrafica()}${(opts.series ? lineLegendHtml() : '') + fn({...opts, big: 1, h: 420})}</div>`, {wide: true});
  },
  detalleFila(clave) {
    const tipo = clave.slice(0, clave.indexOf(':')), id = clave.slice(clave.indexOf(':') + 1);
    if (tipo === 'sale') {
      const r = SALES.find(x => x.id === id); if (!r) return;
      openModal(modalHead(esc(r.cliente), `${esc(r.producto)} · ${esc(CAT[r.producto] || '')}`)
        + `<dl class="kv">${SALE_COLS.filter(c => r[c.k] != null && r[c.k] !== '').map(c => `<dt>${esc(c.l)}</dt><dd>${c.money ? usd(r[c.k]) : c.k === 'fecha' ? esc(dmy(r.fecha)) : esc(r[c.k])}</dd>`).join('')}</dl>`);
    } else if (tipo === 'inv') {
      const r = INV[+id]; if (!r) return;
      openModal(modalHead(esc(r.cliente), `${esc(r.programa || 'Sin programa')} · ${esc(r.familia)}`)
        + `<dl class="kv">${INV_COLS.filter(c => r[c.k] != null && r[c.k] !== '').map(c => `<dt>${esc(c.l)}</dt><dd>${c.money ? usd(r[c.k]) : c.k === 'mes' ? MES[r.mes - 1] : c.k === 'fecha' ? esc(dmy(r.fecha)) : esc(r[c.k])}</dd>`).join('')}</dl>`);
    } else if (tipo === 'adv') {
      const h = hojaOf(id);
      if (!h) return toast('No tienes acceso a las ventas de esa persona.');
      Object.assign(state.ventas, {hoja: PROPIAS ? '' : h, producto: '', q: '', p: 0}); go('ventas');
    } else if (tipo === 'prog') {
      const hay = SALES.some(r => r.producto === id);
      Object.assign(state.ventas, {hoja: '', producto: hay ? id : '', q: hay ? '' : id, p: 0}); go('ventas');
    }
  },
  /* Guardar la configuración del mes: mismas validaciones del tablero comercial. */
  async guardarConfig(f) {
    if (!can('editar.configuracion')) throw new Error('Tu rol no puede cambiar la configuración del mes.');
    const get = (n, nombre, o) => parseIn(f.elements[n] ? f.elements[n].value : '', nombre, o);
    const cfg = {
      umbrales: {verde: get('u.verde', 'Verde desde', {max: 1000}), ambar: get('u.ambar', 'Ámbar desde', {max: 1000}),
        pendienteMax: get('u.pendienteMax', 'Pendiente por cobrar máximo', {max: 100}), ocupacionMin: get('u.ocupacionMin', 'Ocupación mínima', {max: 100}),
        cacMax: get('u.cacMax', 'Costo de adquisición máximo'), margenMin: get('u.margenMin', 'Margen mínimo', {max: 100})},
      finanzas: {publicidad: get('f.publicidad', 'Publicidad y mercadeo'), gastosOperativos: get('f.gastosOperativos', 'Gastos operativos'),
        gastosVenta: get('f.gastosVenta', 'Gastos de venta'), costoAdquisicion: get('f.costoAdquisicion', 'Costos de adquisición directos'),
        costoMercadeoVentas: get('f.costoMercadeoVentas', 'Total de costos de mercadeo y ventas')},
      asesoras: {},
    };
    if (cfg.umbrales.verde != null && cfg.umbrales.ambar != null && cfg.umbrales.ambar > cfg.umbrales.verde) throw new Error('El umbral ámbar no puede ser mayor que el verde.');
    ADV.forEach((a, i) => {
      const n = cleanName(a.name);
      const o = {metaEstudiantes: get(`a${i}.metaEstudiantes`, `Meta en estudiantes de ${n}`, {int: true}), metaMonto: get(`a${i}.metaMonto`, `Meta en dólares de ${n}`),
        metaAcumEstudiantes: get(`a${i}.metaAcumEstudiantes`, `Meta acumulada en estudiantes de ${n}`, {int: true}), metaAcumMonto: get(`a${i}.metaAcumMonto`, `Meta acumulada en dólares de ${n}`)};
      if (Object.values(o).some(v => v != null)) cfg.asesoras[advKey(a.name)] = o;
    });
    await A.setConfig(cfgKey(P), cfg);
  },
};
}


/* Gobierno SaaS: las API son la autoridad; la demo aplica las mismas fronteras. */
var GOV = {team:null,studio:null};
var UI_TEXTS = {global:{},tenant:{}};
const originalUiText = new WeakMap();
const originalUiAttributes = new WeakMap();
async function loadUiTexts() {
  try { const d=await agoraApi('ui/texts'); UI_TEXTS={global:d.global||{},tenant:d.tenant||{}}; }
  catch { UI_TEXTS={global:{},tenant:{}}; }
}
function applyUiTexts(root=document.body) {
  if(!root) return;
  const replacements={...UI_TEXTS.global,...UI_TEXTS.tenant};

  for(const el of root.querySelectorAll('[placeholder],[title],[aria-label]')) {
    if(el.closest('[data-no-text-edit]'))continue;
    const saved=originalUiAttributes.get(el)||{};
    for(const attr of ['placeholder','title','aria-label']) {
      if(!el.hasAttribute(attr))continue;
      const current=el.getAttribute(attr), previous=saved[attr];
      const original=previous && previous.rendered===current?previous.original:current;
      const value=Object.prototype.hasOwnProperty.call(replacements,original)?String(replacements[original]):original;
      saved[attr]={original,rendered:value};
      if(current!==value)el.setAttribute(attr,value);
    }
    originalUiAttributes.set(el,saved);
  }
  const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);
  let node;
  while((node=walker.nextNode())) {
    if(node.parentElement?.closest('script,style,textarea,input,code,pre,[data-no-text-edit]'))continue;
    const previous=originalUiText.get(node);
    const original=previous && previous.rendered===node.nodeValue ? previous.original : node.nodeValue;
    const key=original.trim();
    const value=Object.prototype.hasOwnProperty.call(replacements,key)?original.replace(key,()=>String(replacements[key])):original;
    originalUiText.set(node,{original,rendered:value});
    if(node.nodeValue!==value)node.nodeValue=value;
  }
}
function updateProfileIdentity(user) {
  ME={...ME,...user};
  if(AGORA_ME){ Object.assign(AGORA_ME,user); AGORA_ME.user={...AGORA_ME.user,id:user.id,name:user.nombre,email:user.correo}; ws?.setProfile(AGORA_ME); }
}
function profileEditForm() {
  const demo = window.__DEMO__ ? 'En la demo, el correo nuevo sirve para entrar hasta que recargues la página.' : 'Si cambias el correo, el servidor confirma que es tuyo antes de usarlo para entrar.';
  return `<section class="panel pf-sec profile-details" data-no-text-edit><header class="pf-sec-h"><span class="pf-sec-ic" aria-hidden="true">${icon('user')}</span><div><h2>Datos personales</h2><p>Así te ven en la agencia y en los registros.</p></div></header>
    <form id="personal-details" class="pf-form">
      <div class="pf-fields">
        <label class="fld">Nombre y apellido<input name="nombre" required maxlength="120" autocomplete="name" value="${esc(ME?.nombre||'')}"></label>
        <label class="fld"><span>Teléfono <span class="pf-opt">(opcional)</span></span><input name="telefono" type="tel" maxlength="40" autocomplete="tel" placeholder="Ej.: 0414 123 4567" value="${esc(ME?.telefono||'')}"></label>
        <label class="fld pf-wide">Correo electrónico<input name="correo" type="email" required maxlength="254" autocomplete="email" value="${esc(ME?.correo||'')}"></label>
        <div class="pf-confirm pf-wide" data-pf-confirm hidden><label class="fld">Escribe tu clave actual para cambiar el correo<input type="password" name="clave_actual" autocomplete="current-password"></label></div>
      </div>
      <p class="fld-err" role="status"></p>
      <div class="pf-foot"><p class="pf-hint">${demo}</p><button type="submit" class="btn btn-cta" disabled>Guardar cambios</button></div>
    </form></section>`;
}
const originalProfileRender=V.perfil.render, originalProfileMounted=V.perfil.mounted;
V.perfil.render=function(){return originalProfileRender.call(this)};
V.perfil.mounted=function(root){
  originalProfileMounted.call(this,root);
  const form=root.querySelector('#personal-details');
  const correo0=form.correo.value, inicial=new URLSearchParams(new FormData(form)).toString(), guardar=form.querySelector('button[type=submit]'), confirmar=form.querySelector('[data-pf-confirm]');
  form.addEventListener('input',()=>{confirmar.hidden=form.correo.value.trim()===correo0;guardar.disabled=new URLSearchParams(new FormData(form)).toString()===inicial;});
  form.addEventListener('submit',async e=>{
    e.preventDefault();const button=form.querySelector('button[type=submit]'),error=form.querySelector('.fld-err');busy(button,true,'Guardando…');
    try {const d=await agoraApi('profile',{method:'PUT',body:Object.fromEntries(new FormData(form))});if(!d.usuario)throw new Error('El servidor no confirmó tu perfil.');updateProfileIdentity(d.usuario);repaint();toast('Datos personales actualizados.');}
    catch(err){error.textContent=err.message;}finally{busy(button,false);}
  });
};
function agencyRouteDenied(){return `<section class="panel"><p class="empty"><b>Acceso restringido</b>Esta función no está habilitada para tu rol.<br><a class="btn" href="${href(homePath())}">Volver al inicio</a></p></section>`}
V.agencyTeam={
  need:async()=>{if(!esSuperAdmin())return;const d=await agoraApi('agency/team');if(!Array.isArray(d.items))throw new Error('Falta conectar el servicio de usuarios de Agencia.');GOV.team=d.items;},
  render(){
    if(!esSuperAdmin())return agencyRouteDenied();
    const agencyRoles = ['MASTER', 'SUPERADMIN'];
    const rows = GOV.team ? GOV.team.filter(u => agencyRoles.includes(String(u.rol || '').toUpperCase())) : null;
    const table=rows?`<div class="tw"><table><thead><tr><th>Persona de Agencia</th><th>Rol de Agencia</th><th>Subcuentas asignadas</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${rows.map(u=>`<tr><td><b>${esc(u.nombre)}</b><br><small>${esc(u.correo)}</small></td><td><span class="sc ${u.rol==='MASTER'?'sc-brand':'sc-info'}">${esc(u.rol==='MASTER'?'Master · Dueño':'Superadmin de Agencia')}</span></td><td>${u.rol==='MASTER'?'Todas las subcuentas':(u.tenants||[]).map(id=>esc(AGORA_CONN.find(c=>Number(c.id)===Number(id))?.name||String(id))).join('<br>')||'<span class="src">Sin subcuentas</span>'}</td><td><span class="sc ${u.activo?'sc-success':'sc-danger'}">${u.activo?'Activo':'Inactivo'}</span></td><td>${u.rol==='MASTER'||(!esMaster()&&u.rol==='SUPERADMIN')?'<span class="src">Perfil protegido</span>':`<button type="button" class="btn btn-sm" data-team-edit="${esc(u.id)}">Editar acceso</button>${esMaster()&&u.activo?` <button type="button" class="btn btn-sm btn-danger" data-team-revoke="${esc(u.id)}">${icon('lock')}Revocar</button>`:''}`}</td></tr>`).join('')}</tbody></table></div>`:loading();
    return launcherFrame('agencia',head(AG_GOB_NOMBRE,'Gestión de usuarios','Aquí manejas al equipo que administra la agencia (Master y Superadmin). Las personas de cada empresa se manejan dentro de su subcuenta.',`<button class="btn btn-cta" type="button" data-team-new>${icon('plus')}Agregar usuario de agencia</button>`)+`<section class="panel pf-sec">${secH('team','Equipo de la agencia',rows?`${rows.length} ${rows.length===1?'persona':'personas'} con rol Master o Superadmin. Edita su rol o las subcuentas a las que entran.`:'Cargando el equipo…')}${table}</section>`);
  },
  mounted(root){root.querySelector('[data-team-new]')?.addEventListener('click',()=>agencyTeamForm());root.querySelectorAll('[data-team-edit]').forEach(b=>b.onclick=()=>agencyTeamForm(GOV.team.find(u=>String(u.id)===b.dataset.teamEdit)));root.querySelectorAll('[data-team-revoke]').forEach(b=>b.onclick=()=>{const u=GOV.team?.find(x=>String(x.id)===b.dataset.teamRevoke);if(u)agenciaRevocarEquipo(u);});}
};
function agencyTeamForm(user=null){
  const roles=['SUPERADMIN'];
  const formHost=openModal(modalHead(user?'Editar usuario de Agencia':'Agregar usuario de Agencia')+`<form id="agency-team-form" data-no-text-edit><div class="grid g2"><label class="fld">Nombre<input name="nombre" required maxlength="120" value="${esc(user?.nombre||'')}"></label><label class="fld">Correo institucional<input name="correo" required type="email" value="${esc(user?.correo||'')}"></label><label class="fld">Rol de Agencia<select name="rol">${roles.map(r=>`<option value="${r}" ${r===user?.rol?'selected':''}>${r==='SUPERADMIN'?'Superadmin de Agencia':r}</option>`).join('')}</select></label>${!user?`<label class="fld">${window.__DEMO__?'Contraseña de prueba':'Contraseña temporal'}<input name="clave" type="password" required minlength="12" autocomplete="new-password"></label>`:''}</div><fieldset class="tenant-choices"><legend>Subcuentas asignadas</legend>${AGORA_CONN.map(c=>`<label><input type="checkbox" name="tenant" value="${esc(c.id)}" ${(user?.tenants||[]).map(String).includes(String(c.id))?'checked':''}>${esc(c.name)}</label>`).join('')}</fieldset><p class="src">Los Superadmins pertenecen al equipo central de la Agencia y supervisan las subcuentas seleccionadas. Los administradores y usuarios de clientes se gestionan en sus respectivas subcuentas.</p><label class="fld"><span><input type="checkbox" name="activo" ${user?.activo===false?'':'checked'}> Usuario activo</span></label><p class="fld-err" role="status"></p><div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">Guardar usuario de agencia</button></div></form>`);
  const form=formHost.querySelector('form');form.onsubmit=async e=>{
    e.preventDefault();const f=new FormData(form),ids=f.getAll('tenant').map(Number),role=f.get('rol'),error=form.querySelector('.fld-err'),button=form.querySelector('[type=submit]');
    if(!ids.length){error.textContent='Selecciona al menos una subcuenta asignada.';return;}
    busy(button,true,'Guardando…');
    try{await agoraApi('agency/team',{method:user?'PUT':'POST',body:{id:user?.id,nombre:f.get('nombre'),correo:f.get('correo'),rol:role,tenants:ids,activo:f.has('activo'),...(!user?{clave:f.get('clave')}:{})}});GOV.team=null;closeModal();render();toast('Usuario de agencia guardado.');}
    catch(err){error.textContent=err.message;}finally{busy(button,false);}
  };
}
V.agencyTexts={
  render(){
    if(!esMaster())return agencyRouteDenied();
    return launcherFrame('agencia',`<div data-no-text-edit>${head('Master · Dueño','Editor de textos','Cambia el texto visible de la interfaz, sin modificar registros de clientes ni código.')}<section class="panel"><form id="text-editor"><label class="fld">Aplicar a<select name="scope"><option value="global">Todo el ecosistema</option>${AGORA_CONN.map(c=>`<option value="${esc(c.id)}">Solo ${esc(c.name)}</option>`).join('')}</select></label><label class="fld">Texto original exacto<textarea name="original" required maxlength="500" rows="2" placeholder="Por ejemplo: Todas las subcuentas"></textarea></label><label class="fld">Texto nuevo<textarea name="replacement" maxlength="2000" rows="3"></textarea></label><p class="src">Se modifica texto visible, nunca HTML ni scripts. La coincidencia debe ser exacta; puedes restablecer el texto original. Los cambios de una subcuenta prevalecen sobre los globales.</p><p class="fld-err" role="status"></p><div class="form-ft"><button class="btn" name="restore" type="submit" value="1">Restablecer original</button><button class="btn btn-cta" type="submit">Guardar texto</button></div></form></section></div>`);
  },mounted(root){const form=root.querySelector('#text-editor');if(!form)return;form.onsubmit=async e=>{e.preventDefault();const button=e.submitter;busy(button,true);try{await agoraApi('ui/texts',{method:button?.name==='restore'?'DELETE':'PUT',body:Object.fromEntries(new FormData(form))});await loadUiTexts();toast('Texto actualizado.');}catch(err){form.querySelector('.fld-err').textContent=err.message;}finally{busy(button,false)}};}
};
var AGENCY_AUDIT_LOGS = window.__DEMO__ ? [
  {id:'log_1',fecha:'2026-11-07 10:45:12',admin:'Dueño de Ágora',correo:'demo.agencia@agora.test',rolAdmin:'MASTER',accion:'subcuenta.aprobada',tipo:'Aprobación',entidad:'Nova Logistics SpA',codigo:'NOV-OPS',detalle:'Aprobación formal de alta y habilitación operativa.',anterior:'Pendiente',nuevo:'Activa',ip:'190.24.112.45'},
  {id:'log_2',fecha:'2026-11-07 09:20:05',admin:'Dueño de Ágora',correo:'demo.agencia@agora.test',rolAdmin:'MASTER',accion:'rol.cambiado',tipo:'Cambio de Rol',entidad:'Supervisor de Agencia',codigo:'super.agora@agora.test',detalle:'Promoción de privilegios directivos a nivel de Agencia.',anterior:'ADMIN',nuevo:'SUPERADMIN',ip:'190.24.112.45'},
  {id:'log_3',fecha:'2026-11-06 18:32:40',admin:'Supervisor de Agencia',correo:'super.agora@agora.test',rolAdmin:'SUPERADMIN',accion:'subcuenta.estado',tipo:'Estado',entidad:'Innova Market',codigo:'INN-RET',detalle:'Pausa preventiva de subcuenta por ajuste de facturación.',anterior:'Activa',nuevo:'Inactiva',ip:'186.91.43.12'},
  {id:'log_4',fecha:'2026-11-06 15:10:18',admin:'Dueño de Ágora',correo:'demo.agencia@agora.test',rolAdmin:'MASTER',accion:'subcuenta.creada',tipo:'Alta',entidad:'Salud Vital SpA',codigo:'SAL-VIT',detalle:'Creación de subcuenta con plan Enterprise (8 licencias).',anterior:'—',nuevo:'Pendiente',ip:'190.24.112.45'},
  {id:'log_5',fecha:'2026-11-05 16:44:00',admin:'Dueño de Ágora',correo:'demo.agencia@agora.test',rolAdmin:'MASTER',accion:'plan.asignado',tipo:'Plan',entidad:'StreamingNet',codigo:'STR-VEN',detalle:'Actualización de módulos activos y pase a Plan Comercial.',anterior:'Básico',nuevo:'Comercial',ip:'190.24.112.45'},
  {id:'log_6',fecha:'2026-11-05 11:15:33',admin:'Supervisor de Agencia',correo:'super.agora@agora.test',rolAdmin:'SUPERADMIN',accion:'usuario.creado',tipo:'Usuario',entidad:'Rosa Natalia Ortega',codigo:'rortega@asocim.org',detalle:'Alta de usuario administrativo asignado a subcuenta ASOCIM.',anterior:'—',nuevo:'EJECUTIVO',ip:'186.91.43.12'},
  {id:'log_7',fecha:'2026-11-04 14:02:11',admin:'Dueño de Ágora',correo:'demo.agencia@agora.test',rolAdmin:'MASTER',accion:'subcuenta.aprobada',tipo:'Aprobación',entidad:'ASOCIM · Admisiones',codigo:'ASO-ADM',detalle:'Aprobación de renovación anual y auditoría completada.',anterior:'Pendiente',nuevo:'Activa',ip:'190.24.112.45'}
] : [];

V.agencyActivity = {
  need:async()=>{if(!window.__DEMO__){const d=await agoraApi('agency/activity');AGENCY_AUDIT_LOGS=d.items||[];}},
  render() {
    if(!esSuperAdmin()) return agencyRouteDenied();
    const logs = AGENCY_AUDIT_LOGS;
    const approvals = logs.filter(l => l.accion === 'subcuenta.aprobada').length;
    const roleChanges = logs.filter(l => l.accion === 'rol.cambiado').length;
    const total = logs.length;

    const kpis = `
      <div class="agency-kpis" style="margin-top:0;margin-bottom:20px">
        <div><small>Eventos de Auditoría</small><strong>${total}</strong><span class="src">Acciones administrativas</span></div>
        <div><small>Aprobaciones de Subcuenta</small><strong style="color:var(--brand)">${approvals}</strong><span class="src">Altas y validaciones</span></div>
        <div><small>Modificaciones de Rol</small><strong style="color:#d97706">${roleChanges}</strong><span class="src">Permisos directivos</span></div>
      </div>
    `;

    const filterBar = `
      <div class="agency-filter-bar" style="margin-bottom:14px">
        <div class="agency-search-wrap">
          <span class="search-in">${icon('search')}<input type="search" id="activity-search" placeholder="Buscar por administrador, subcuenta o acción…" autocomplete="off" aria-label="Buscar en log"></span>
        </div>
        <div class="agency-status-filters" role="group" aria-label="Filtro rápido por tipo de acción">
          <button type="button" class="agency-status-btn is-active" data-act-filter="all">Todos <span class="agency-filter-count">${total}</span></button>
          <button type="button" class="agency-status-btn" data-act-filter="aprobada">Aprobaciones <span class="agency-filter-count">${approvals}</span></button>
          <button type="button" class="agency-status-btn" data-act-filter="rol">Cambios de Rol <span class="agency-filter-count">${roleChanges}</span></button>
          <button type="button" class="agency-status-btn" data-act-filter="subcuenta">Subcuentas</button>
        </div>
      </div>
    `;

    const tableRows = logs.map(l => `
      <tr class="activity-row" data-type="${esc(l.accion)}" data-text="${esc((l.admin + ' ' + l.correo + ' ' + l.entidad + ' ' + l.codigo + ' ' + l.detalle + ' ' + l.tipo).toLowerCase())}">
        <td style="font-family:ui-monospace,monospace;font-size:11px;white-space:nowrap;color:var(--muted)">${esc(l.fecha)}</td>
        <td><b>${esc(l.admin)}</b><br><small style="color:var(--muted)">${esc(l.correo)}</small> <span class="sc ${l.rolAdmin==='MASTER'?'sc-brand':'sc-info'}" style="font-size:9.5px;padding:1px 5px">${esc(l.rolAdmin)}</span></td>
        <td><span class="sc ${l.tipo==='Aprobación'?'sc-success':(l.tipo==='Cambio de Rol'?'sc-warning':'sc-info')}">${esc(l.tipo)}</span></td>
        <td><b>${esc(l.entidad)}</b><br><small style="font-family:monospace;color:var(--muted)">${esc(l.codigo)}</small></td>
        <td>${esc(l.detalle)}<br><small style="color:var(--muted)">Estado: <s>${esc(l.anterior)}</s> → <b>${esc(l.nuevo)}</b></small></td>
        <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--muted)">${esc(l.ip)}</td>
      </tr>
    `).join('');

    const emptyNotice = `<p class="agency-filter-empty" id="activity-empty" hidden>No hay registros de auditoría que coincidan con la búsqueda.<br><button type="button" class="btn btn-sm" id="activity-reset" style="margin-top:8px">Restablecer filtros</button></p>`;

    const table = `<div class="tw"><table><thead><tr><th>Fecha y Hora</th><th>Administrador</th><th>Acción</th><th>Subcuenta / Destino</th><th>Detalle del Cambio</th><th>IP Origen</th></tr></thead><tbody id="activity-tbody">${tableRows}</tbody></table></div>${emptyNotice}`;

    const acts = `<button type="button" class="btn" id="activity-export">${icon('invoice')}Exportar CSV</button><button type="button" class="btn btn-cta" id="activity-refresh">${icon('receipt')}Actualizar</button>`;

    return launcherFrame('agencia', head(AG_GOB_NOMBRE, 'Registro de actividad', 'Todo lo que hacen los administradores de la agencia queda anotado aquí y no se puede borrar: aprobaciones de subcuentas, cambios de rol y licencias.', acts) + `<section class="panel pf-sec">${secH('shield','Movimientos','Filtra por tipo o busca por persona, subcuenta o detalle. Toca un movimiento para ver qué cambió.')}${kpis}${filterBar}${table}</section>`);
  },
  mounted(root) {
    const searchInput = root.querySelector('#activity-search');
    const filterBtns = root.querySelectorAll('[data-act-filter]');
    const rows = root.querySelectorAll('.activity-row');
    const emptyNotice = root.querySelector('#activity-empty');
    const resetBtn = root.querySelector('#activity-reset');
    const exportBtn = root.querySelector('#activity-export');
    const refreshBtn = root.querySelector('#activity-refresh');

    let currentFilter = 'all';
    let currentQuery = '';

    const applyFilters = () => {
      let visible = 0;
      rows.forEach(r => {
        const type = r.dataset.type || '';
        const text = r.dataset.text || '';

        let matchType = true;
        if (currentFilter === 'aprobada') matchType = type.includes('aprobada');
        else if (currentFilter === 'rol') matchType = type.includes('rol');
        else if (currentFilter === 'subcuenta') matchType = type.includes('subcuenta');

        const matchQuery = !currentQuery || text.includes(currentQuery);

        if (matchType && matchQuery) {
          r.style.display = '';
          visible++;
        } else {
          r.style.display = 'none';
        }
      });

      if (emptyNotice) emptyNotice.hidden = visible > 0;
    };

    if (searchInput) {
      searchInput.addEventListener('input', e => {
        currentQuery = (e.target.value || '').trim().toLowerCase();
        applyFilters();
      });
    }

    filterBtns.forEach(b => {
      b.addEventListener('click', () => {
        filterBtns.forEach(x => x.classList.remove('is-active'));
        b.classList.add('is-active');
        currentFilter = b.dataset.actFilter || 'all';
        applyFilters();
      });
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        currentQuery = '';
        filterBtns.forEach(b => b.classList.toggle('is-active', b.dataset.actFilter === 'all'));
        currentFilter = 'all';
        applyFilters();
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const csvRows = [
          ['Fecha', 'Administrador', 'Correo', 'Rol Admin', 'Accion', 'Tipo', 'Entidad', 'Codigo', 'Detalle', 'Estado Anterior', 'Estado Nuevo', 'IP'],
          ...AGENCY_AUDIT_LOGS.map(l => [
            l.fecha, l.admin, l.correo, l.rolAdmin, l.accion, l.tipo, l.entidad, l.codigo, l.detalle, l.anterior, l.nuevo, l.ip
          ])
        ];
        bajarCsv('log-actividad-agencia-' + (new Date().toISOString().slice(0,10)) + '.csv', csvRows);
        toast('Registro de actividad exportado en CSV.');
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        toast('Registro de actividad actualizado.');
        render();
      });
    }
  }
};

V.agencyStudio={
  need:async()=>{if(!esSuperAdmin())return;const d=await agoraApi('agency/studio');if(!Array.isArray(d.items)||typeof d.configured!=='boolean')throw new Error('Falta conectar el servicio de Estudio IA.');GOV.studio=d;},
  render(){
    if(!esSuperAdmin())return agencyRouteDenied();
    const data=GOV.studio;
    return launcherFrame('agencia',head('Desarrollo por subcuenta','Estudio IA','Prepara una solicitud para una única subcuenta y revisa su alcance antes de conectar el servicio.')+`<section class="panel"><p class="integration-notice">${data?.configured?'Servicio configurado: revisa su estado en el servidor.':'Sin conexión a IA: se guardan borradores; no se genera, ejecuta ni publica código.'}</p><form id="studio-request"><label class="fld">Subcuenta destino<select name="tenantId" required><option value="">Seleccionar…</option>${AGORA_CONN.map(c=>`<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('')}</select></label><label class="fld">Cambio solicitado<textarea name="prompt" rows="5" minlength="10" maxlength="8000" required placeholder="Describe el módulo, el cambio y cómo comprobarlo en esta subcuenta."></textarea></label><p class="src">Flujo previsto: borrador → propuesta de cambios → pruebas aisladas → revisión → publicación por subcuenta → reversión disponible. El núcleo compartido queda fuera del alcance de una solicitud local.</p><p class="fld-err" role="status"></p><button type="submit" class="btn btn-cta">Guardar solicitud</button><button class="btn" type="button" disabled title="Requiere el worker y un entorno de pruebas configurados">Ejecutar pruebas</button><button class="btn" type="button" disabled title="Requiere revisión, versión y destino de publicación">Publicar</button></form></section><section class="panel"><h2>Solicitudes</h2>${!data?loading():data.items?.length?data.items.map(j=>`<article class="studio-job"><b>${esc(AGORA_CONN.find(c=>Number(c.id)===Number(j.tenantId))?.name||j.tenantId)}</b><span class="sc sc-info">${esc(j.status)}</span><p>${esc(j.prompt)}</p><small>${esc(j.createdAt)}</small></article>`).join(''):'<p class="src">No hay solicitudes registradas.</p>'}</section>`);
  },mounted(root){const form=root.querySelector('#studio-request');if(!form)return;form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');busy(button,true);try{await agoraApi('agency/studio',{method:'POST',body:Object.fromEntries(new FormData(form))});GOV.studio=null;render();toast('Borrador registrado. No se ejecutó código.');}catch(err){form.querySelector('.fld-err').textContent=err.message;}finally{busy(button,false)}};}
};

function syncAgencyRail(){
 const layout=document.querySelector('.launch-layout:has(.launch-rail)');
 if(!layout || innerWidth<=760)return;
 const rect=layout.getBoundingClientRect(), width=parseFloat(getComputedStyle(layout).gridTemplateColumns);
 layout.style.setProperty('--agency-rail-left',rect.left+'px');
 layout.style.setProperty('--agency-rail-width',width+'px');
 layout.style.setProperty('--agency-rail-top',Math.max(12,(document.querySelector('#tb')?.getBoundingClientRect().bottom||64)+12)+'px');
}
window.addEventListener('resize',syncAgencyRail);

// Los módulos cargados de forma asíncrona reciben también las etiquetas del Master.
let uiTextFrame=0;
const uiTextObserver=new MutationObserver(()=>{
 if(uiTextFrame)return;
 uiTextFrame=requestAnimationFrame(()=>{uiTextFrame=0;applyUiTexts();});
});
uiTextObserver.observe(document.body,{subtree:true,childList:true,characterData:true});
/* Estudio: archivos editables y datos en un espacio local por persona/subcuenta. */
const STUDIO = {tenant:'',tab:'codigo',selected:'',identity:'',work:null};
const STUDIO_EXT=/\.(html?|css|js|ts|jsx|tsx|php|sql|json|csv|txt|md|svg)$/i;
function studioPath(value){
 const name=String(value||'').trim();
 if(!name || name.length>180 || name.startsWith('/') || name.includes('\\') || name.split('/').some(p=>!p||p==='.'||p==='..'||p.startsWith('.')) || !STUDIO_EXT.test(name))throw new Error('Usa una ruta relativa, sin carpetas ocultas, y una extensión de código, JSON, CSV o texto.');
 return name;
}
function studioAllowed(id){return esSuperAdmin()&&AGORA_CONN.some(c=>String(c.id)===String(id));}
function studioKey(){if(!studioAllowed(STUDIO.tenant))throw new Error('Selecciona una subcuenta asignada.');return 'agora:studio:v1:'+String(ME.id)+':'+STUDIO.tenant;}
function studioLoad(){
 const key=studioKey();
 if(STUDIO.identity===key&&STUDIO.work)return STUDIO.work;
 let work={files:[],data:null};
 try{const saved=localStorage.getItem(key);if(saved)work=studioValidateBundle(JSON.parse(saved));}catch{toast('No se pudo recuperar el espacio local. Puedes importar una copia exportada.');}
 STUDIO.identity=key;STUDIO.work=work;STUDIO.selected='';return work;
}
function studioValidateBundle(raw){
 if(!raw||!Array.isArray(raw.files)||raw.files.length>40)throw new Error('Paquete inválido: máximo 40 archivos.');
 const seen=new Set();let total=0;
 const files=raw.files.map(f=>{const path=studioPath(f.path);if(seen.has(path)||typeof f.content!=='string'||f.content.length>500000)throw new Error('Archivo duplicado o demasiado grande.');seen.add(path);total+=f.content.length;return {path,content:f.content};});
 if(total>2000000)throw new Error('El espacio supera 2 MB de texto.');
 const data=raw.data==null?null:studioData(raw.data);
 return {files,data};
}
function studioSave(work){const valid=studioValidateBundle(work);const key=studioKey();localStorage.setItem(key,JSON.stringify(valid));STUDIO.work=valid;STUDIO.identity=key;}
function studioCSV(text){
 const rows=[];let row=[],cell='',quoted=false,after=false;
 text=text.replace(/^\uFEFF/,'');
 for(let i=0;i<text.length;i++){
  const c=text[i];
  if(quoted){if(c==='"'){if(text[i+1]==='"'){cell+='"';i++;}else{quoted=false;after=true;}}else cell+=c;continue;}
  if(after && c!==','&&c!=='\r'&&c!=='\n')throw new Error('CSV inválido después de una comilla.');
  if(c==='"'){if(cell)throw new Error('CSV: comilla inesperada.');quoted=true;}
  else if(c===','){row.push(cell);cell='';after=false;}
  else if(c==='\n'||c==='\r'){if(c==='\r'&&text[i+1]==='\n')i++;row.push(cell);rows.push(row);row=[];cell='';after=false;}
  else cell+=c;
 }
 if(quoted)throw new Error('CSV: faltan comillas de cierre.');
 if(cell||row.length||after){row.push(cell);rows.push(row);}
 if(rows.length<2)throw new Error('CSV: incluye encabezados y al menos una fila.');
 const headers=rows.shift();if(headers.some(h=>!h.trim())||new Set(headers).size!==headers.length)throw new Error('CSV: encabezados vacíos o duplicados.');
 return rows.map(r=>{if(r.length!==headers.length)throw new Error('CSV: las filas tienen distinta cantidad de columnas.');return Object.fromEntries(headers.map((h,i)=>[h,r[i]]));});
}
function studioData(rows){
 if(!Array.isArray(rows)||rows.length>5000||rows.some(r=>!r||Array.isArray(r)||typeof r!=='object'))throw new Error('Usa un arreglo JSON de objetos, con máximo 5000 filas.');
 const keys=new Set();for(const r of rows)for(const [k,v] of Object.entries(r)){keys.add(k);if(v!==null&&typeof v==='object')throw new Error('Los datos deben ser tabulares, sin objetos anidados.');}
 if(keys.size>60||JSON.stringify(rows).length>2000000)throw new Error('Máximo 60 columnas y 2 MB de datos.');
 return rows;
}
function studioDownload(name,body,type='application/json'){
 const blob=new Blob([body],{type}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
function studioToCSV(rows){
 const keys=[...new Set(rows.flatMap(r=>Object.keys(r)))];
 const cell=v=>'"'+String(v??'').replace(/^[\s\u0000-\u001f]*[=+@-]/,"'$&").replace(/"/g,'""')+'"';
 return [keys.map(cell).join(','),...rows.map(r=>keys.map(k=>cell(r[k])).join(','))].join('\r\n');
}
function studioTextPanel(){return !esMaster()?'<p class="empty">La edición de textos está reservada al Master.</p>':`<form id="text-editor"><h2>Textos de la interfaz</h2><label class="fld">Alcance<select name="scope"><option value="${esc(STUDIO.tenant)}">Esta subcuenta</option><option value="global">Todo el ecosistema</option></select></label><div class="grid g2"><label class="fld">Texto original exacto<textarea name="original" required maxlength="500" rows="3"></textarea></label><label class="fld">Texto nuevo<textarea name="replacement" maxlength="2000" rows="3"></textarea></label></div><p class="src">La edición de textos es una herramienta del Estudio. No modifica datos de clientes ni ejecuta código.</p><p class="fld-err" role="status"></p><div class="form-ft"><button class="btn" name="restore" type="submit">Restablecer</button><button class="btn btn-cta" type="submit">Guardar texto</button></div></form>`;}
const studioTextMounted=V.agencyTexts.mounted;
const studioAiRender=V.agencyStudio.render,studioAiMounted=V.agencyStudio.mounted,studioAiNeed=V.agencyStudio.need;
V.agencyStudio.need=async()=>{if(STUDIO.tab==='ia')await studioAiNeed();};
function studioPanel(){
 const work=studioLoad();
 if(STUDIO.tab==='textos')return studioTextPanel();
 if(STUDIO.tab==='datos'){
 const rows=work.data||[],keys=[...new Set(rows.flatMap(r=>Object.keys(r)))];
 return `<h2>Importar y exportar datos</h2><p class="src">JSON tabular o CSV separado por comas. Revisa la vista previa antes de guardar. Estos datos permanecen en el Estudio; no se insertan en la base operativa.</p><label class="btn">${icon('upload')}Importar datos<input type="file" id="studio-data-import" accept=".json,.csv" hidden></label><button class="btn" data-studio-export="json" ${rows.length?'':'disabled'}>Exportar JSON</button><button class="btn" data-studio-export="csv" ${rows.length?'':'disabled'}>Exportar CSV</button><label class="fld">Datos editables (JSON)<textarea id="studio-data-editor" rows="12" spellcheck="false">${esc(JSON.stringify(rows,null,2))}</textarea></label><button class="btn" id="studio-data-preview">Validar y previsualizar</button><button class="btn btn-cta" id="studio-data-save">Guardar datos en Estudio</button><div id="studio-data-preview-area">${studioTable(rows,keys)}</div>`;
 }
 const selected=work.files.find(f=>f.path===STUDIO.selected);
 return `<div class="studio-files"><div><h2>Archivos</h2><label class="btn">${icon('upload')}Adjuntar archivos<input type="file" id="studio-files" multiple accept=".html,.css,.js,.ts,.jsx,.tsx,.php,.sql,.json,.csv,.txt,.md,.svg" hidden></label><p class="src">Hasta 40 archivos de texto. Los archivos adjuntos pueden abrirse, editarse y descargarse.</p><div class="studio-file-list">${work.files.map(f=>`<button class="btn ${selected?.path===f.path?'btn-cta':''}" data-studio-file="${esc(f.path)}">${esc(f.path)}</button>`).join('')||'<p>Aún no hay archivos.</p>'}</div></div><form id="studio-code-form"><h2>${selected?'Editar archivo':'Crear archivo'}</h2><label class="fld">Ruta relativa<input name="path" required value="${esc(selected?.path||'')}" placeholder="modulos/inicio.js"></label><label class="fld">Código o contenido<textarea name="content" rows="20" spellcheck="false" class="studio-code">${esc(selected?.content||'')}</textarea></label><div class="form-ft"><button class="btn" type="button" id="studio-new-file">Nuevo</button><button class="btn" type="button" id="studio-file-download">Descargar contenido</button><button class="btn btn-cta" type="submit">Guardar archivo</button></div><p class="src">Se edita una copia local. Guardar no ejecuta el archivo ni modifica el servidor.</p></form></div>`;
}
function studioTable(rows,keys=[...new Set(rows.flatMap(r=>Object.keys(r)))]){return `<p>${rows.length} filas · ${keys.length} columnas · Vista previa de las primeras 20 filas.</p><div class="tw"><table><thead><tr>${keys.map(k=>`<th>${esc(k)}</th>`).join('')}</tr></thead><tbody>${rows.slice(0,20).map(r=>`<tr>${keys.map(k=>`<td>${esc(String(r[k]??''))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;}
V.agencyStudio.render=function(){
 if(!esSuperAdmin())return agencyRouteDenied();
 if(!studioAllowed(STUDIO.tenant))STUDIO.tenant='';
 const tabs=[['codigo','Código y archivos'],['textos','Textos'],['datos','Datos'],['ia','Asistente IA']];
 if(STUDIO.tab==='ia')return studioAiRender.call(this).replace('<section class="panel">',`<section class="panel"><button class="btn" id="studio-back">← Volver al Estudio</button>`);
 return launcherFrame('agencia',head('Herramientas de Agencia','Estudio','Código, textos y datos en un espacio de trabajo por subcuenta.')+`<section class="panel pf-sec" data-no-text-edit>${secH('spark','Espacio de trabajo','Elige la subcuenta, edita sus archivos, textos y datos, y exporta una copia cuando termines.')}<div class="studio-toolbar"><label class="fld">Subcuenta destino<select id="studio-tenant"><option value="">Seleccionar…</option>${AGORA_CONN.map(c=>`<option value="${esc(c.id)}" ${String(c.id)===STUDIO.tenant?'selected':''}>${esc(c.name)}</option>`).join('')}</select></label><button class="btn" id="studio-export" ${STUDIO.tenant?'':'disabled'}>Exportar espacio</button><label class="btn ${STUDIO.tenant?'':'is-off'}" ${STUDIO.tenant?'':'aria-disabled="true" title="Elige primero la subcuenta" data-studio-pide'}>Importar espacio<input id="studio-bundle" type="file" accept=".json" ${STUDIO.tenant?'':'disabled'} hidden></label></div><p class="integration-notice">Archivos y datos guardados en este navegador, separados por usuario y subcuenta. Exporta una copia para trasladarlos. Publicación al servidor y conexión IA pendientes de integración.</p><nav class="studio-tabs" aria-label="Herramientas del Estudio">${tabs.map(([id,label])=>`<button class="btn ${STUDIO.tab===id?'btn-cta':''}" data-studio-tab="${id}" aria-pressed="${STUDIO.tab===id}">${label}</button>`).join('')}</nav><p id="studio-message" role="status" aria-live="polite"></p>${STUDIO.tenant?studioPanel():'<p class="empty">Selecciona la subcuenta en la que quieres trabajar.</p>'}</section>`);
};
V.agencyStudio.mounted=function(root){
 const report=err=>{const el=root.querySelector('#studio-message');if(el)el.textContent=err.message||String(err);else toast(err.message||String(err));};
 let dirty=false;root.querySelectorAll('textarea,#studio-code-form input').forEach(el=>el.addEventListener('input',()=>dirty=true));
 const leave=()=>!dirty||window.confirm('Hay cambios sin guardar. ¿Quieres descartarlos?');
 root.querySelectorAll('[data-studio-tab]').forEach(b=>b.onclick=()=>{if(leave()){STUDIO.tab=b.dataset.studioTab;render();}});
 const tenant=root.querySelector('#studio-tenant');if(tenant)tenant.onchange=()=>{if(!leave()){tenant.value=STUDIO.tenant;return;}STUDIO.tenant=tenant.value;STUDIO.identity='';repaint();};
 if(STUDIO.tab==='ia'){studioAiMounted(root);root.querySelector('#studio-back').onclick=()=>{STUDIO.tab='codigo';repaint();};return;}
 if(!STUDIO.tenant)return;
 const context=studioKey(),work=studioLoad();
 const stillCurrent=()=>{if(studioKey()!==context||!root.isConnected)throw new Error('El contexto cambió. Vuelve a importar en la subcuenta correcta.');};
 const read=async file=>{if(!file||file.size>2000000)throw new Error('Selecciona un archivo de hasta 2 MB.');const text=await file.text();stillCurrent();return text;};
 root.querySelector('#studio-export').onclick=()=>{try{studioDownload('estudio-'+STUDIO.tenant+'.json',JSON.stringify({format:'agora-studio-v1',tenantId:STUDIO.tenant,...studioLoad()},null,2));}catch(e){report(e);}};
 root.querySelector('#studio-bundle').onchange=async e=>{try{if(!leave())return;const raw=JSON.parse(await read(e.target.files[0]));if(raw.format!=='agora-studio-v1'||String(raw.tenantId)!==STUDIO.tenant)throw new Error('El paquete no corresponde a esta subcuenta.');const next=studioValidateBundle(raw);if(!window.confirm('Importar reemplazará los archivos y datos de este espacio local. ¿Continuar?'))return;studioSave(next);repaint();toast('Espacio importado.');}catch(err){report(err);}finally{e.target.value='';}};
 if(STUDIO.tab==='textos'){studioTextMounted(root);return;}
 if(STUDIO.tab==='datos'){
  const editor=root.querySelector('#studio-data-editor');
  root.querySelector('#studio-data-import').onchange=async e=>{try{const file=e.target.files[0],text=await read(file);const rows=studioData(/\.csv$/i.test(file.name)?studioCSV(text):JSON.parse(text));editor.value=JSON.stringify(rows,null,2);dirty=true;root.querySelector('#studio-data-preview-area').innerHTML=studioTable(rows);report('Importación validada. Pulsa Guardar datos para conservarla.');}catch(err){report(err);}finally{e.target.value='';}};
  root.querySelector('#studio-data-preview').onclick=()=>{try{root.querySelector('#studio-data-preview-area').innerHTML=studioTable(studioData(JSON.parse(editor.value)));report('Datos válidos.');}catch(e){report(e);}};
  root.querySelector('#studio-data-save').onclick=()=>{try{studioSave({...work,data:studioData(JSON.parse(editor.value))});dirty=false;repaint();toast('Datos guardados en Estudio.');}catch(e){report(e);}};
  root.querySelectorAll('[data-studio-export]').forEach(b=>b.onclick=()=>{try{const rows=studioData(JSON.parse(editor.value)),csv=b.dataset.studioExport==='csv';studioDownload('datos-'+STUDIO.tenant+(csv?'.csv':'.json'),csv?studioToCSV(rows):JSON.stringify(rows,null,2),csv?'text/csv;charset=utf-8':'application/json');}catch(e){report(e);}});return;
 }
 root.querySelectorAll('[data-studio-file]').forEach(b=>b.onclick=()=>{if(leave()){STUDIO.selected=b.dataset.studioFile;repaint();}});
 root.querySelector('#studio-new-file').onclick=()=>{if(leave()){STUDIO.selected='';repaint();}};
 const form=root.querySelector('#studio-code-form');
 form.onsubmit=e=>{e.preventDefault();try{const f=Object.fromEntries(new FormData(form));f.path=studioPath(f.path);const files=work.files.slice(),index=files.findIndex(x=>x.path===f.path);if(index>=0&&f.path!==STUDIO.selected)throw new Error('Esa ruta ya existe. Ábrela para editarla.');if(index>=0)files[index]=f;else files.push(f);studioSave({...work,files});STUDIO.selected=f.path;dirty=false;repaint();toast('Archivo guardado en Estudio.');}catch(err){report(err);}};
 root.querySelector('#studio-file-download').onclick=()=>{try{const f=Object.fromEntries(new FormData(form));studioDownload(studioPath(f.path).split('/').pop(),f.content,'text/plain;charset=utf-8');}catch(e){report(e);}};
 root.querySelector('#studio-files').onchange=async e=>{try{if(!leave())return;const incoming=[];for(const file of e.target.files)incoming.push({path:studioPath(file.name),content:await read(file)});const paths=new Set(work.files.map(f=>f.path));if(incoming.some(f=>paths.has(f.path)))throw new Error('Ya existe un archivo con ese nombre. No se reemplazó ninguno.');studioSave({...work,files:[...work.files,...incoming]});STUDIO.selected=incoming[0]?.path||'';repaint();toast('Archivos adjuntados.');}catch(err){report(err);}finally{e.target.value='';}};
};
// Compatibilidad de enlaces antiguos: textos abre ahora una herramienta del Estudio.
V.agencyTexts={need:V.agencyStudio.need,render(){STUDIO.tab='textos';return V.agencyStudio.render();},mounted:V.agencyStudio.mounted};

/* 2.1.19: «Importar espacio» sin subcuenta elegida avisa qué falta (antes no hacía nada). */
if(typeof document!=='undefined')document.addEventListener('click',e=>{if(e.target.closest?.('[data-studio-pide]')){e.preventDefault();toast('Elige primero la subcuenta del espacio de trabajo.');document.getElementById('studio-tenant')?.focus();}});
/* Producción: operaciones nativas, importación y listados acotados. */
if (!window.__DEMO__) {
  const usersRender=V.usuarios.render, usersMounted=V.usuarios.mounted;
  V.usuarios.render=function(){return usersRender.call(this)+`<section class="panel mt"><form id="server-user-search"><label class="fld">Buscar en todos los usuarios de esta subcuenta<input name="q" type="search" maxlength="120" value="${esc(USER_PAGE.q)}"></label><button class="btn" type="submit">Buscar en servidor</button></form><p class="src">${DATOS.usuarios?.length||0} visibles de ${USER_PAGE.total} resultados. Los filtros superiores se aplican a la página visible.</p>${USER_PAGE.next?'<button type="button" class="btn" id="users-next">Página siguiente</button>':''}</section>`;};
  V.usuarios.mounted=function(root){usersMounted?.call(this,root);root.querySelector('#server-user-search')?.addEventListener('submit',async e=>{e.preventDefault();USER_PAGE.q=new FormData(e.target).get('q');USER_PAGE.cursor='';await cargarUsuarios(true);repaint();});root.querySelector('#users-next')?.addEventListener('click',async()=>{USER_PAGE.cursor=USER_PAGE.next;await cargarUsuarios(true);repaint();});};
  V.ag_settings={
    render(){return head('Administración','Ajustes','Configuración y carga de datos de la subcuenta activa.')+`<section class="panel"><h2>Datos reales</h2><p>Importa un archivo JSON con hasta 500 contactos, etapas, oportunidades o facturas. Para reportes usa el esquema de la guía incluida en el proyecto. Los identificadores existentes actualizan el registro.</p><form id="native-import"><label class="fld">Archivo JSON<input type="file" name="file" accept="application/json,.json" required></label><p class="fld-err" role="status"></p><div class="form-ft"><button class="btn btn-cta" type="submit">Validar e importar</button></div></form><details><summary>Ejemplo de contacto</summary><pre style="white-space:pre-wrap">${esc(JSON.stringify({kind:'contact',items:[{id:'cliente-001',name:'Nombre del cliente',email:'cliente@ejemplo.com',phone:'+580000000000'}]},null,2))}</pre></details></section><section class="panel mt"><h2>Conexiones y operación</h2><p>La autenticación, los datos y la auditoría se guardan en MySQL/MariaDB. Las credenciales de integraciones se configuran en el servidor. La sincronización automática de GHL aún no está habilitada en esta versión.</p><p id="native-status" role="status">Comprobando conexión…</p><button class="btn" type="button" id="native-refresh">Comprobar servicios</button><a class="btn" href="${href('/usuarios')}">Administrar usuarios</a><a class="btn" href="${href('/perfil')}">Mi perfil</a></section>`;},
    mounted(root){const status=root.querySelector('#native-status');const refresh=async()=>{status.textContent='Comprobando conexión…';try{const d=await agoraApi('admin/diagnostics');status.textContent=`Base de datos conectada · Mensajería GHL: ${d.ghlConfigured?'credenciales configuradas; requiere cron':'sin configurar'} · revisado a las ${new Date().toLocaleTimeString('es-VE',{hour:'2-digit',minute:'2-digit',second:'2-digit'})}`;}catch(e){status.textContent=e.message;}};root.querySelector('#native-refresh').onclick=refresh;refresh();root.querySelector('#native-import').onsubmit=async e=>{e.preventDefault();const f=e.target,file=f.querySelector('[type=file]').files[0],button=f.querySelector('[type=submit]'),out=f.querySelector('[role=status]');busy(button,true,'Importando…');try{if(!file||file.size>1800000)throw Error('Selecciona un JSON de máximo 1,8 MB.');const body=JSON.parse(await file.text());const result=await agoraApi('workspace/import',{method:'POST',body});out.textContent=result.period?'Reporte importado: '+result.period:`Importación completada: ${result.imported} registros.`;TB.estado='sin-cargar';DATOS.contactos=null;}catch(err){out.textContent=err.message;}finally{busy(button,false);}};}
  };
}
/* =====================================================================================
   Agencia · herramientas unificadas (v2.1)
   Integra las funciones de la rama 1.4.2 (filtros avanzados, avisos de vencimiento,
   panel de seguridad, ficha de auditoría, informe de subcuentas, revocación de accesos
   y confirmación de acciones críticas) usando SOLO datos devueltos por el servidor.
   ===================================================================================== */

Object.assign(IC, {
  x: '<path d="M6 6l12 12M18 6L6 18"/>',
  tune: '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
  eye: '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
  eyeOff: '<path d="M3 3l18 18"/><path d="M10.6 6.1A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3 3.6M6.4 7.4A16.5 16.5 0 0 0 2.5 12S6 18 12 18a9 9 0 0 0 4.2-1"/><path d="M9.9 10a3 3 0 0 0 4.1 4.1"/>',
  tick: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  arrowR: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  briefcase: '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7M3 12.5h18"/>',
});
const AGENCIA_UMBRAL_VENCE = 15; // días antes del vencimiento en que se avisa

/* ---------- Confirmación de acciones críticas ---------- */
function dialogoConfirmarAccionCritica({titulo = 'Confirmar acción crítica', subtitulo = '', itemNombre = '', itemTipo = '', motivo = '', consecuencias = [], textoConfirmar = 'Confirmar', peligro = true, onConfirm = async () => {}} = {}) {
  const s = openModal(modalHead(esc(titulo), esc(subtitulo)) + `
    <div class="form critical-dialog ${peligro ? 'is-danger' : 'is-warn'}">
      <div class="critical-target">
        <span class="critical-ic" aria-hidden="true">${icon(peligro ? 'warn' : 'shield')}</span>
        <div><strong>${esc(itemTipo || 'Elemento afectado')}</strong><code>${esc(itemNombre)}</code>${motivo ? `<p>${esc(motivo)}</p>` : ''}</div>
      </div>
      ${consecuencias.length ? `<div class="critical-list"><p>Qué va a pasar</p><ul>${consecuencias.map(c => `<li>${esc(c)}</li>`).join('')}</ul></div>` : ''}
      <p class="fld-err" role="alert"></p>
      <div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="button" class="btn ${peligro ? 'btn-danger' : 'btn-cta'}" data-critical-ok>${icon(peligro ? 'lock' : 'check')}${esc(textoConfirmar)}</button></div>
    </div>`);
  const ok = s.querySelector('[data-critical-ok]'), err = s.querySelector('.fld-err');
  ok.onclick = async () => {
    busy(ok, true, 'Procesando…'); err.textContent = '';
    try { await onConfirm(); closeModal(); } catch (x) { busy(ok, false); err.textContent = x.message || 'No se pudo completar la acción.'; }
  };
  return s;
}

/* ---------- Datos de agencia ---------- */
const agenciaEstado = a => { const s = String(a?.estado || 'activa').toLowerCase(); return s === 'activa' || s === 'activo' ? 'activa' : s.startsWith('pend') ? 'pendiente' : 'inactiva'; };
const agenciaVence = a => (a && a.venceDias !== null && a.venceDias !== undefined && a.venceDias !== '') ? Number(a.venceDias) : null;
function agenciaVenceTexto(a) {
  const d = agenciaVence(a);
  if (d === null) return 'Sin vencimiento';
  if (d < 0) return `Vencida hace ${-d} ${-d === 1 ? 'día' : 'días'}`;
  if (d === 0) return 'Vence hoy';
  if (d === 1) return 'Vence mañana';
  return `Vence en ${d} días`;
}
async function cargarActividadAgencia() {
  if (window.__DEMO__) return;
  try { const d = await agoraApi('agency/activity'); AGENCY_AUDIT_LOGS = Array.isArray(d.items) ? d.items : []; }
  catch { AGENCY_AUDIT_LOGS = []; }
}
const logAntes = l => l.anterior ?? l.antes ?? '—';
const logDespues = l => l.nuevo ?? l.despues ?? '—';
/* Acciones del registro en palabras sencillas (el servidor guarda códigos como "rol.cambiado"). */
const ACCION_LEGIBLE = {
  'rol.cambiado': 'Cambió un rol', 'subcuenta.estado': 'Cambió el estado de una subcuenta', 'subcuenta.creada': 'Creó una subcuenta',
  'subcuenta.actualizada': 'Editó una subcuenta', 'subcuenta.cobros': 'Actualizó los cobros de una subcuenta', 'subcuenta.tipo': 'Cambió el tipo de cuenta de una subcuenta', 'productos.actualizados': 'Actualizó los productos y rubros', 'personalizacion.actualizada': 'Personalizó el tablero y los módulos', 'etiquetas.actualizadas': 'Actualizó las etiquetas del CRM', 'usuario.creado': 'Agregó a una persona',
  'usuario.editado': 'Editó a una persona', 'usuario.actualizado': 'Editó a una persona', 'usuario.revocado': 'Quitó el acceso a una persona',
  'clave.cambiada': 'Cambió una clave', 'permisos.actualizados': 'Cambió permisos', 'estado.actualizado': 'Cambió un estado',
  'texto.editado': 'Editó un texto del panel', 'textos.actualizados': 'Editó textos del panel', 'perfil.actualizado': 'Actualizó su perfil',
  'identidad.actualizada': 'Cambió la imagen de la empresa', 'configuracion.actualizada': 'Cambió la configuración', 'ia.borrador': 'Pidió un borrador con IA',
};
const accionLegible = a => ACCION_LEGIBLE[a] || (String(a || 'Movimiento').replace(/[._]+/g, ' ').replace(/^./, c => c.toUpperCase()));
const ACCIONES_CRITICAS = /revoc|estado|rol|elimin|desactiv|clave|permiso|bloque/i;

/* ---------- Filtros ---------- */
function agenciaFiltrosNuevos() {
  return {estado: 'all', q: '', fechaCampo: 'creacion', desde: '', hasta: '', vence: 'all', rubros: new Set(), volumen: 'all', riesgo: false};
}
let AGENCIA_FILTROS = agenciaFiltrosNuevos();
function agenciaCoincide(a, f = AGENCIA_FILTROS) {
  const st = agenciaEstado(a);
  if (f.estado !== 'all' && st !== f.estado) return false;
  if (f.q) {
    const text = [a.nombre, a.subcuenta, a.name, a.codigo, a.rubro, a.plan].filter(Boolean).join(' ').toLowerCase();
    if (!text.includes(f.q)) return false;
  }
  if (f.rubros.size && !f.rubros.has(String(a.rubro || 'Otro'))) return false;
  if (f.riesgo && !agenciaEnRiesgo(a)) return false;
  const d = agenciaVence(a);
  if (f.vence === 'vencida' && !(d !== null && d < 0)) return false;
  if (f.vence === 'critico' && !(d !== null && d >= 0 && d <= 7)) return false;
  if (f.vence === 'proximo' && !(d !== null && d > 7 && d <= 30)) return false;
  if (f.vence === 'lejano' && !(d !== null && d > 30)) return false;
  if (f.vence === 'sin' && d !== null) return false;
  const n = Number(a.usuarios || 0);
  if (f.volumen === '0' && n !== 0) return false;
  if (f.volumen === '1-5' && (n < 1 || n > 5)) return false;
  if (f.volumen === '6-20' && (n < 6 || n > 20)) return false;
  if (f.volumen === '20+' && n <= 20) return false;
  const fecha = f.fechaCampo === 'acceso' ? a.ultimoAcceso : a.fechaCreacion;
  if ((f.desde || f.hasta) && !fecha) return false;
  if (f.desde && fecha < f.desde) return false;
  if (f.hasta && fecha > f.hasta) return false;
  return true;
}
function agenciaChipsActivos(f = AGENCIA_FILTROS) {
  const chips = [];
  if (f.riesgo) chips.push(['riesgo', 'Solo subcuentas en riesgo']);
  if (f.desde || f.hasta) chips.push(['fecha', `${f.fechaCampo === 'acceso' ? 'Último acceso' : 'Creadas'}: ${f.desde || '…'} a ${f.hasta || '…'}`]);
  const venceTxt = {vencida: 'Vencidas', critico: 'Vencen en 7 días o menos', proximo: 'Vencen en 8 a 30 días', lejano: 'Vencen en más de 30 días', sin: 'Sin vencimiento'};
  if (f.vence !== 'all') chips.push(['vence', venceTxt[f.vence]]);
  f.rubros.forEach(r => chips.push(['rubro:' + r, 'Rubro: ' + r]));
  const volTxt = {'0': 'Sin usuarios', '1-5': '1 a 5 usuarios', '6-20': '6 a 20 usuarios', '20+': 'Más de 20 usuarios'};
  if (f.volumen !== 'all') chips.push(['volumen', volTxt[f.volumen]]);
  return chips;
}
function agenciaQuitarChip(id, f = AGENCIA_FILTROS) {
  if (id === 'fecha') { f.desde = ''; f.hasta = ''; }
  else if (id === 'riesgo') f.riesgo = false;
  else if (id === 'vence') f.vence = 'all';
  else if (id === 'volumen') f.volumen = 'all';
  else if (id.startsWith('rubro:')) f.rubros.delete(id.slice(6));
}

function agenciaPanelFiltrosHtml(accounts) {
  const f = AGENCIA_FILTROS;
  const rubros = [...new Set(accounts.map(a => String(a.rubro || 'Otro')))].sort((x, y) => x.localeCompare(y, 'es'));
  const chip = (attr, value, label, on) => `<button type="button" class="agency-chip-btn ${on ? 'is-active' : ''}" ${attr}="${esc(value)}" aria-pressed="${on}">${esc(label)}</button>`;
  return `<div class="agency-filters-overlay" data-adv-overlay hidden></div>
    <aside class="agency-filters-drawer" data-adv-drawer aria-label="Filtros avanzados" aria-hidden="true" inert>
      <div class="agency-drawer-header"><h3 class="agency-drawer-title">${icon('tune')}<span>Filtros avanzados</span></h3><button type="button" class="btn btn-sm" data-adv-close aria-label="Cerrar filtros">${icon('plus')}</button></div>
      <div class="agency-drawer-body">
        <fieldset class="agency-filter-group"><legend class="agency-filter-label">Rango de fechas</legend>
          <div class="seg-mini" role="radiogroup" aria-label="Fecha a filtrar">
            <label><input type="radio" name="adv-campo" value="creacion" ${f.fechaCampo === 'creacion' ? 'checked' : ''}> Creación</label>
            <label><input type="radio" name="adv-campo" value="acceso" ${f.fechaCampo === 'acceso' ? 'checked' : ''}> Último acceso</label>
          </div>
          <div class="grid g2"><label class="fld">Desde<input type="date" data-adv-desde value="${esc(f.desde)}"></label><label class="fld">Hasta<input type="date" data-adv-hasta value="${esc(f.hasta)}"></label></div>
          <div class="agency-filter-chips-grid">${chip('data-adv-rapido', '7', 'Últimos 7 días', false)}${chip('data-adv-rapido', '30', 'Últimos 30 días', false)}${chip('data-adv-rapido', 'anio', 'Este año', false)}${chip('data-adv-rapido', 'todo', 'Todo', false)}</div>
        </fieldset>
        <fieldset class="agency-filter-group"><legend class="agency-filter-label">Vencimiento de licencia</legend>
          <div class="agency-filter-chips-grid">${[['all', 'Todos'], ['vencida', 'Vencidas'], ['critico', '≤ 7 días'], ['proximo', '8 a 30 días'], ['lejano', '> 30 días'], ['sin', 'Sin fecha']].map(([v, l]) => chip('data-adv-vence', v, l, f.vence === v)).join('')}</div>
        </fieldset>
        <fieldset class="agency-filter-group"><legend class="agency-filter-label">Rubro</legend>
          <div class="agency-filter-chips-grid">${rubros.map(r => chip('data-adv-rubro', r, r, f.rubros.has(r))).join('') || '<span class="src">Sin rubros registrados</span>'}</div>
        </fieldset>
        <fieldset class="agency-filter-group"><legend class="agency-filter-label">Usuarios de la subcuenta</legend>
          <div class="agency-filter-chips-grid">${[['all', 'Cualquiera'], ['0', 'Ninguno'], ['1-5', '1 a 5'], ['6-20', '6 a 20'], ['20+', 'Más de 20']].map(([v, l]) => chip('data-adv-vol', v, l, f.volumen === v)).join('')}</div>
        </fieldset>
      </div>
      <div class="agency-drawer-footer"><button type="button" class="btn btn-sm" data-adv-reset>Limpiar</button><span class="src" data-adv-count role="status"></span><button type="button" class="btn btn-sm btn-cta" data-adv-apply>Ver resultados</button></div>
    </aside>`;
}

/* Conecta barra de búsqueda, estados rápidos, panel lateral y chips. */
function agenciaFiltrosMounted(root, accounts) {
  const f = AGENCIA_FILTROS;
  const cards = [...root.querySelectorAll('.agency-card[data-id]')];
  const byId = new Map(accounts.map(a => [String(a.id), a]));
  const empty = root.querySelector('#agency-filter-empty');
  const chipsBar = root.querySelector('[data-adv-chips]');
  const badge = root.querySelector('[data-adv-badge]');
  const drawer = root.querySelector('[data-adv-drawer]'), overlay = root.querySelector('[data-adv-overlay]');
  const count = root.querySelector('[data-adv-count]');
  const draft = () => ({...f, rubros: new Set(f.rubros)});
  let work = draft();

  const apply = () => {
    let visible = 0;
    cards.forEach(c => { const ok = agenciaCoincide(byId.get(c.dataset.id), f); c.hidden = !ok; if (ok) visible++; });
    if (empty) empty.hidden = visible > 0 || !cards.length;
    const chips = agenciaChipsActivos(f);
    if (badge) { badge.textContent = String(chips.length); badge.hidden = !chips.length; }
    if (chipsBar) {
      chipsBar.hidden = !chips.length;
      chipsBar.innerHTML = chips.length ? `<span class="agency-chips-label">Filtros activos</span>${chips.map(([id, l]) => `<span class="agency-active-chip"><span>${esc(l)}</span><button type="button" data-chip-off="${esc(id)}" aria-label="Quitar filtro ${esc(l)}">${icon('plus')}</button></span>`).join('')}<button type="button" class="link-btn" data-chip-clear>Limpiar todos</button>` : '';
      chipsBar.querySelectorAll('[data-chip-off]').forEach(b => b.onclick = () => { agenciaQuitarChip(b.dataset.chipOff, f); apply(); });
      chipsBar.querySelector('[data-chip-clear]')?.addEventListener('click', () => { Object.assign(f, agenciaFiltrosNuevos(), {estado: f.estado, q: f.q}); apply(); });
    }
  };
  const preview = () => { if (count) { const n = accounts.filter(a => agenciaCoincide(a, work)).length; count.textContent = `${n} ${n === 1 ? 'coincidencia' : 'coincidencias'}`; } };
  const syncDrawer = () => {
    drawer.querySelectorAll('[data-adv-vence]').forEach(b => { const on = b.dataset.advVence === work.vence; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on); });
    drawer.querySelectorAll('[data-adv-vol]').forEach(b => { const on = b.dataset.advVol === work.volumen; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on); });
    drawer.querySelectorAll('[data-adv-rubro]').forEach(b => { const on = work.rubros.has(b.dataset.advRubro); b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on); });
    drawer.querySelector('[data-adv-desde]').value = work.desde; drawer.querySelector('[data-adv-hasta]').value = work.hasta;
    drawer.querySelectorAll('[name=adv-campo]').forEach(r => { r.checked = r.value === work.fechaCampo; });
    preview();
  };
  const open = () => { work = draft(); syncDrawer(); overlay.hidden = false; drawer.inert = false; drawer.setAttribute('aria-hidden', 'false'); requestAnimationFrame(() => drawer.classList.add('is-open')); drawer.querySelector('button')?.focus(); };
  const close = () => { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); drawer.inert = true; overlay.hidden = true; root.querySelector('[data-adv-toggle]')?.focus(); };

  root.querySelector('[data-adv-toggle]')?.addEventListener('click', open);
  drawer?.querySelector('[data-adv-close]')?.addEventListener('click', close);
  overlay?.addEventListener('click', close);
  drawer?.addEventListener('keydown', e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });
  drawer?.querySelectorAll('[name=adv-campo]').forEach(r => r.onchange = () => { work.fechaCampo = r.value; preview(); });
  drawer?.querySelector('[data-adv-desde]')?.addEventListener('input', e => { work.desde = e.target.value; preview(); });
  drawer?.querySelector('[data-adv-hasta]')?.addEventListener('input', e => { work.hasta = e.target.value; preview(); });
  drawer?.querySelectorAll('[data-adv-rapido]').forEach(b => b.onclick = () => {
    const hoy = new Date(), iso = d => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const v = b.dataset.advRapido;
    if (v === 'todo') { work.desde = ''; work.hasta = ''; }
    else if (v === 'anio') { work.desde = hoy.getFullYear() + '-01-01'; work.hasta = iso(hoy); }
    else { work.desde = iso(new Date(hoy.getTime() - Number(v) * 86400000)); work.hasta = iso(hoy); }
    syncDrawer();
  });
  drawer?.querySelectorAll('[data-adv-vence]').forEach(b => b.onclick = () => { work.vence = b.dataset.advVence; syncDrawer(); });
  drawer?.querySelectorAll('[data-adv-vol]').forEach(b => b.onclick = () => { work.volumen = b.dataset.advVol; syncDrawer(); });
  drawer?.querySelectorAll('[data-adv-rubro]').forEach(b => b.onclick = () => { const r = b.dataset.advRubro; work.rubros.has(r) ? work.rubros.delete(r) : work.rubros.add(r); syncDrawer(); });
  drawer?.querySelector('[data-adv-reset]')?.addEventListener('click', () => { work = {...agenciaFiltrosNuevos(), estado: f.estado, q: f.q}; syncDrawer(); });
  drawer?.querySelector('[data-adv-apply]')?.addEventListener('click', () => { Object.assign(f, work); apply(); close(); });

  const search = root.querySelector('#agency-search-input');
  if (search) { search.value = f.q; search.addEventListener('input', () => { f.q = search.value.trim().toLowerCase(); apply(); }); }
  const estados = root.querySelectorAll('[data-agency-filter]');
  const marcarEstado = () => estados.forEach(b => { const on = b.dataset.agencyFilter === f.estado; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on); });
  estados.forEach(b => b.onclick = () => { f.estado = b.dataset.agencyFilter; marcarEstado(); apply(); });
  root.querySelector('#agency-filter-reset')?.addEventListener('click', () => { AGENCIA_FILTROS = agenciaFiltrosNuevos(); Object.assign(f, AGENCIA_FILTROS); AGENCIA_FILTROS = f; if (search) search.value = ''; marcarEstado(); apply(); });
  root.querySelector('[data-agency-quick]')?.addEventListener('click', e => {
    const tipo = e.currentTarget.dataset.agencyQuick;
    if (tipo === 'vence') { f.vence = accounts.some(a => (agenciaVence(a) ?? 1) < 0) ? 'vencida' : 'critico'; }
    else { f.estado = 'pendiente'; marcarEstado(); }
    apply();
  });
  root.querySelectorAll('[data-kpi-filter]').forEach(b => b.onclick = () => {
    const tipo = b.dataset.kpiFilter;
    if (tipo === 'riesgo') { f.riesgo = true; f.estado = 'all'; } else { f.riesgo = false; f.estado = tipo; }
    marcarEstado(); apply();
    root.querySelector('#agency-cards-grid')?.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
  });
  marcarEstado(); apply();
}

/* ---------- Aviso de vencimientos y pendientes ---------- */
function agenciaAvisoHtml(accounts) {
  const porVencer = accounts.filter(a => { const d = agenciaVence(a); return d !== null && d <= AGENCIA_UMBRAL_VENCE; });
  const pendientes = accounts.filter(a => agenciaEstado(a) === 'pendiente');
  if (!porVencer.length && !pendientes.length) return '';
  const vencidas = porVencer.filter(a => agenciaVence(a) < 0).length;
  const partes = [];
  if (porVencer.length) partes.push(`${porVencer.length} ${porVencer.length === 1 ? 'licencia vence' : 'licencias vencen'} en ${AGENCIA_UMBRAL_VENCE} días o menos${vencidas ? ` (${vencidas} ya ${vencidas === 1 ? 'vencida' : 'vencidas'})` : ''}: ${porVencer.slice(0, 3).map(a => esc(a.nombre || a.name)).join(', ')}${porVencer.length > 3 ? '…' : ''}`);
  if (pendientes.length) partes.push(`${pendientes.length} ${pendientes.length === 1 ? 'subcuenta pendiente' : 'subcuentas pendientes'} de activar`);
  return `<div class="agency-alert-banner ${vencidas ? 'is-bad' : ''}" role="status"><div class="agency-alert-info"><span class="agency-alert-dot" aria-hidden="true"></span><span>${partes.join(' · ')}</span></div><button type="button" class="btn btn-sm" data-agency-quick="${porVencer.length ? 'vence' : 'pendiente'}">${porVencer.length ? 'Ver vencimientos' : 'Ver pendientes'}</button></div>`;
}

/* ---------- Tareas de agencia (2.1.8) ----------
   Micromódulo del Centro de mando. Las tareas salen de los datos de cada subcuenta
   (licencias, aprobaciones, convenios, cobros, estado) y desaparecen solas al resolverse. */
function agenciaTareas(accounts) {
  const t = [], nom = a => a.nombre || a.name || 'Subcuenta';
  for (const a of accounts || []) {
    const st = agenciaEstado(a), d = agenciaVence(a), conv = Number(a.conveniosPorFirmar) || 0;
    if (d !== null && d < 0) t.push({p: 0, ic: 'warn', cls: 'is-bad', titulo: `Renovar la licencia de ${nom(a)}`, detalle: agenciaVenceTexto(a), accion: 'detalle', id: a.id, boton: 'Administrar'});
    else if (d !== null && d <= AGENCIA_UMBRAL_VENCE) t.push({p: d <= 7 ? 0 : 1, ic: 'clock', cls: d <= 7 ? 'is-bad' : 'is-warn', titulo: `Renovar la licencia de ${nom(a)}`, detalle: agenciaVenceTexto(a), accion: 'detalle', id: a.id, boton: 'Administrar'});
    if (st === 'pendiente') t.push({p: 1, ic: 'check', cls: 'is-warn', titulo: `Aprobar la subcuenta ${nom(a)}`, detalle: 'Está esperando tu aprobación para empezar a operar.', accion: 'detalle', id: a.id, boton: 'Revisar'});
    if (conv) t.push({p: 1, ic: 'invoice', cls: 'is-warn', titulo: `${conv} ${conv === 1 ? 'convenio por firmar' : 'convenios por firmar'} en ${nom(a)}`, detalle: 'Entra a la subcuenta para revisarlos y enviarlos a firma.', accion: st === 'activa' ? 'abrir' : 'detalle', id: a.id, boton: st === 'activa' ? 'Ir a subcuenta' : 'Administrar'});
    if (a.cobroMensual === null || a.cobroMensual === undefined) t.push({p: 2, ic: 'coins', cls: '', titulo: `Anotar lo que te paga ${nom(a)}`, detalle: 'No tiene plan ni servicios registrados; así no suma en los ingresos.', accion: 'cobros', id: a.id, boton: 'Anotar cobro'});
    if (st === 'inactiva') t.push({p: 2, ic: 'pause', cls: '', titulo: `Revisar ${nom(a)}`, detalle: 'Está inactiva: nadie puede entrar ni operar en ella.', accion: 'detalle', id: a.id, boton: 'Administrar'});
  }
  return t.sort((x, y) => x.p - y.p);
}
function agenciaTareasHtml(accounts) {
  if (!Array.isArray(accounts)) return '';
  const tareas = agenciaTareas(accounts), urgentes = tareas.filter(x => x.p === 0).length, max = 5;
  const prio = ['Urgente', 'Pronto', 'Cuando puedas'];
  const item = (x, i) => `<li class="ag-task ${x.cls}" ${i >= max ? 'data-task-extra hidden' : ''}><span class="ag-task-ic" aria-hidden="true">${icon(x.ic)}</span><span class="ag-task-tx"><b>${esc(x.titulo)}</b><small><span class="ag-task-prio">${prio[x.p]}</span> · ${esc(x.detalle)}</small></span><button type="button" class="btn btn-sm" data-task="${x.accion}" data-id="${esc(x.id)}">${x.boton}</button></li>`;
  return `<section class="panel pf-sec ag-tasks">
    ${secH('check', `Tareas de agencia <span class="ag-tasks-n">${tareas.length}</span>`, tareas.length ? (urgentes ? `${urgentes} ${urgentes === 1 ? 'tarea urgente' : 'tareas urgentes'}. Empieza por ${urgentes === 1 ? 'ella' : 'ellas'}.` : 'Nada urgente. Resuélvelas cuando puedas.') : 'Se arman solas con lo que necesita cada subcuenta.')}
    ${tareas.length ? `<ol class="ag-task-list">${tareas.map(item).join('')}</ol>${tareas.length > max ? `<button type="button" class="btn btn-sm ag-tasks-more" data-task-more aria-expanded="false">Ver las ${tareas.length} tareas</button>` : ''}` : `<p class="ag-tasks-empty">${icon('check')}<span><b>Todo al día.</b> No hay licencias por vencer, aprobaciones ni cobros pendientes.</span></p>`}
  </section>`;
}
function agenciaTareasMounted(root) {
  root.querySelectorAll('[data-task]').forEach(b => b.onclick = () => {
    const a = AGENCY_ACCOUNTS?.find(x => String(x.id) === String(b.dataset.id)); if (!a) return;
    if (b.dataset.task === 'abrir') abrirCuentaAgencia(a.id);
    else if (b.dataset.task === 'cobros' && esMaster()) agenciaCobrosEditar(a);
    else agenciaDetalle(a);
  });
  root.querySelector('[data-task-more]')?.addEventListener('click', e => {
    const abrir = e.currentTarget.getAttribute('aria-expanded') !== 'true';
    root.querySelectorAll('[data-task-extra]').forEach(li => li.hidden = !abrir);
    e.currentTarget.setAttribute('aria-expanded', String(abrir)); e.currentTarget.textContent = abrir ? 'Ver menos' : `Ver las ${root.querySelectorAll('.ag-task').length} tareas`;
  });
}

/* ---------- Panel de seguridad (actividad crítica reciente) ---------- */
function haceCuanto(fecha) {
  const t = Date.parse(String(fecha || '').replace(' ', 'T') + (/[zZ+]/.test(String(fecha)) ? '' : 'Z'));
  if (!Number.isFinite(t)) return esc(String(fecha || ''));
  const s = Math.max(0, Math.round((Date.now() - t) / 1000));
  if (s < 60) return 'Hace un momento';
  if (s < 3600) return `Hace ${Math.round(s / 60)} min`;
  if (s < 86400) return `Hace ${Math.round(s / 3600)} h`;
  const d = Math.round(s / 86400); return d === 1 ? 'Ayer' : `Hace ${d} días`;
}
function agenciaSeguridadHtml() {
  const logs = Array.isArray(AGENCY_AUDIT_LOGS) ? AGENCY_AUDIT_LOGS : [];
  const criticos = logs.filter(l => ACCIONES_CRITICAS.test(String(l.accion || '')));
  const plegado = store.get('agencia_seguridad_plegada', false) === true;
  const items = (criticos.length ? criticos : logs).slice(0, 5);
  return `<section class="panel pf-sec security-snapshot">
    ${secH('shield', 'Seguridad de la agencia', logs.length ? (criticos.length ? `${criticos.length} de los últimos ${logs.length} movimientos merecen tu atención: cambios de rol, de clave, de estado o de permisos.` : `Todo en orden: ninguno de los últimos ${logs.length} movimientos es delicado.`) : 'Todavía no hay movimientos anotados en la agencia.', `<a class="btn btn-sm" href="${href('/agencia/actividad')}">Ver todo</a><button type="button" class="btn btn-sm" data-sec-toggle aria-expanded="${!plegado}">${plegado ? 'Mostrar' : 'Ocultar'}</button>`)}
    <ol class="security-list" ${plegado ? 'hidden' : ''}>${items.map(l => `<li><button type="button" class="security-item" data-log="${esc(l.id)}"><span class="security-dot ${ACCIONES_CRITICAS.test(String(l.accion)) ? 'is-crit' : ''}" aria-hidden="true"></span><span class="security-tx"><b>${esc(l.accionTexto && !/^[a-z_]+\.[a-z_]+$/.test(l.accionTexto) ? l.accionTexto : accionLegible(l.accion || l.accionTexto))}</b><small>${esc(l.admin || l.actor || 'Sistema')} · ${esc(l.entidad || l.objeto || '')}</small></span><time>${haceCuanto(l.at || l.fecha)}</time></button></li>`).join('') || '<li class="src">Sin actividad reciente.</li>'}</ol>
  </section>`;
}
function agenciaSeguridadMounted(root) {
  const t = root.querySelector('[data-sec-toggle]'), list = root.querySelector('.security-list');
  t?.addEventListener('click', () => { const ocultar = !list.hidden; list.hidden = ocultar; store.set('agencia_seguridad_plegada', ocultar); t.textContent = ocultar ? 'Mostrar' : 'Ocultar'; t.setAttribute('aria-expanded', String(!ocultar)); });
  root.querySelectorAll('[data-log]').forEach(b => b.onclick = () => fichaForenseAuditoria(b.dataset.log));
}
function fichaForenseAuditoria(id) {
  const l = (AGENCY_AUDIT_LOGS || []).find(x => String(x.id) === String(id));
  if (!l) return toast('El evento ya no está en memoria. Recarga el registro.');
  let detalle = l.detalle;
  if (typeof detalle === 'string' && /^[{[]/.test(detalle)) { try { detalle = JSON.stringify(JSON.parse(detalle), null, 2); } catch { /* texto plano */ } }
  const fila = (k, v) => `<div><dt>${k}</dt><dd>${v}</dd></div>`;
  openModal(modalHead('Ficha de auditoría', 'Evento #' + esc(l.id)) + `<dl class="forensic">
      ${fila('Fecha (UTC)', `<code>${esc(l.fecha || l.at || '')}</code>`)}
      ${fila('Responsable', esc(l.admin || l.actor || 'Sistema') + (l.correo ? `<br><small>${esc(l.correo)}</small>` : ''))}
      ${fila('Acción', `<code>${esc(l.accion)}</code>`)}
      ${fila('Módulo', esc(l.tipo || l.modulo || '—'))}
      ${fila('Objeto', esc(l.entidad || l.objeto || '—'))}
      ${fila('Antes → después', `${esc(logAntes(l))} → <b>${esc(logDespues(l))}</b>`)}
      ${fila('Dirección IP', `<code>${esc(l.ip || 'No registrada')}</code>`)}
    </dl>${detalle && detalle !== '[]' && detalle !== '{}' ? `<details class="mt"><summary>Detalle técnico</summary><pre class="forensic-pre">${esc(String(detalle))}</pre></details>` : ''}
    <div class="form-ft"><button type="button" class="btn" data-close>Cerrar</button></div>`, {wide: true});
}

/* ---------- Informe de subcuentas (vista previa, CSV e impresión) ---------- */
function exportarInformeSubcuentas(accounts = AGENCY_ACCOUNTS || []) {
  const lista = accounts.filter(a => agenciaCoincide(a));
  const total = lista.length, cuenta = st => lista.filter(a => agenciaEstado(a) === st).length;
  const porVencer = lista.filter(a => { const d = agenciaVence(a); return d !== null && d <= 30; }).length;
  const ahora = new Date();
  const fecha = ahora.toLocaleDateString('es-VE', {year: 'numeric', month: 'long', day: 'numeric'}) + ' · ' + ahora.toLocaleTimeString('es-VE', {hour: '2-digit', minute: '2-digit'});
  const estadoTx = {activa: 'Activa', inactiva: 'Inactiva', pendiente: 'Pendiente'};
  const filas = lista.map(a => ({nombre: a.nombre || a.name || '', codigo: a.codigo || '', rubro: a.rubro || 'Otro', estado: estadoTx[agenciaEstado(a)], plan: a.plan || 'Sin plan', cobro: a.cobroMensual === null || a.cobroMensual === undefined ? '' : (Number(a.cobroMensual) || 0).toFixed(2), cobroTx: agenciaCobroTexto(a), vence: a.vence ? `${a.vence} (${agenciaVenceTexto(a)})` : 'Sin vencimiento', usuarios: Number(a.usuarios || 0), ultimo: a.ultimoAcceso || 'Sin accesos', moneda: a.moneda || 'USD', urg: (agenciaVence(a) ?? 99) <= 7}));
  const html = `<div class="report-sheet" id="report-sheet">
      <header class="report-h"><div><span class="report-eyebrow">Ágora Digital App · Agencia</span><h2>Informe de subcuentas</h2><p>${total === (accounts || []).length ? 'Todas las subcuentas' : `${total} de ${(accounts || []).length} subcuentas (filtros aplicados)`}</p></div><div class="report-meta"><div>${esc(fecha)}</div><div>Generado por ${esc(ME?.nombre || 'Agencia')}</div></div></header>
      <div class="report-kpis"><div><small>Total</small><b>${total}</b></div><div class="ok"><small>Activas</small><b>${cuenta('activa')}</b></div><div class="warn"><small>Vencen en 30 días</small><b>${porVencer}</b></div><div><small>Inactivas o pendientes</small><b>${cuenta('inactiva') + cuenta('pendiente')}</b></div></div>
      <div class="tw"><table><thead><tr><th>Empresa</th><th>Rubro</th><th>Estado</th><th>Plan</th><th class="num">Cobro mensual</th><th>Vencimiento</th><th class="num">Usuarios</th><th>Último acceso</th></tr></thead><tbody>${filas.map(r => `<tr><td><b>${esc(r.nombre)}</b><br><small><code>${esc(r.codigo)}</code></small></td><td>${esc(r.rubro)}</td><td>${esc(r.estado)}</td><td>${esc(r.plan)}</td><td class="num">${esc(r.cobroTx)}</td><td class="${r.urg ? 'is-urgent' : ''}">${esc(r.vence)}</td><td class="num">${r.usuarios}</td><td>${esc(r.ultimo)}</td></tr>`).join('') || '<tr><td colspan="7">No hay subcuentas con estos filtros.</td></tr>'}</tbody></table></div>
      <footer class="report-f">Datos registrados en el servidor de Ágora Digital App al momento de generar el informe. Documento interno.</footer>
    </div>`;
  const s = openModal(modalHead('Informe de subcuentas', 'Vista previa · respeta los filtros aplicados') + html + `<div class="form-ft"><button type="button" class="btn" data-close>Cerrar</button><button type="button" class="btn" data-rep-csv>${icon('download')}Descargar CSV</button><button type="button" class="btn btn-cta" data-rep-print>${icon('download')}Imprimir o guardar PDF</button></div>`, {wide: true});
  s.querySelector('[data-rep-csv]').onclick = () => {
    const q = v => { let t = String(v ?? ''); if (/^[\s\u0000-\u001f]*[=+@-]/.test(t)) t = "'" + t; return `"${t.replace(/"/g, '""')}"`; };
    const cols = [['nombre', 'Empresa'], ['codigo', 'Código'], ['rubro', 'Rubro'], ['estado', 'Estado'], ['plan', 'Plan'], ['cobro', 'Cobro mensual'], ['vence', 'Vencimiento'], ['usuarios', 'Usuarios'], ['ultimo', 'Último acceso'], ['moneda', 'Moneda']];
    const text = '﻿' + [cols.map(c => q(c[1])).join(';'), ...filas.map(r => cols.map(c => q(r[c[0]])).join(';'))].join('\r\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], {type: 'text/csv;charset=utf-8'})); a.download = `subcuentas-${new Date().toISOString().slice(0, 10)}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    toast('CSV descargado.');
  };
  s.querySelector('[data-rep-print]').onclick = () => { document.documentElement.classList.add('printing-report'); const off = () => { document.documentElement.classList.remove('printing-report'); removeEventListener('afterprint', off); }; addEventListener('afterprint', off); window.print(); };
}

/* ---------- Estado de la subcuenta (desactivar / reactivar) ---------- */
function agenciaCambiarEstado(a, estado) {
  const reactivar = estado === 'activa';
  dialogoConfirmarAccionCritica({
    titulo: reactivar ? 'Reactivar subcuenta' : 'Desactivar subcuenta',
    subtitulo: 'Cambia el acceso de todo el equipo de esta empresa',
    itemTipo: 'Subcuenta', itemNombre: `${a.nombre || a.name} (${a.codigo || a.id})`,
    consecuencias: reactivar
      ? ['El equipo de la subcuenta vuelve a entrar con normalidad.', 'El cambio queda en el registro de actividad de la agencia.']
      : ['Nadie de la subcuenta puede abrir sus módulos mientras esté inactiva.', 'Los datos se conservan; puedes reactivarla cuando quieras.', 'El cambio queda en el registro de actividad de la agencia.'],
    textoConfirmar: reactivar ? 'Reactivar' : 'Desactivar subcuenta', peligro: !reactivar,
    onConfirm: async () => {
      await agoraApi('agency/accounts', {method: 'PUT', body: {id: a.id, nombre: a.nombre, subcuenta: a.subcuenta || a.name, codigo: a.codigo, rubro: a.rubro || '', moneda: a.moneda || 'USD', notas: a.notas || '', estado}});
      await Promise.all([cargarAgencia(), cargarActividadAgencia()]);
      repaint(); toast(reactivar ? 'Subcuenta reactivada.' : 'Subcuenta desactivada.');
    },
  });
}

/* ---------- Revocar acceso de un usuario de subcuenta ---------- */
function agenciaRevocarUsuario(cuenta, u, alTerminar) {
  dialogoConfirmarAccionCritica({
    titulo: '¿Revocar el acceso?', subtitulo: 'Usuarios de ' + (cuenta?.subcuenta || cuenta?.nombre || 'la subcuenta'),
    itemTipo: 'Usuario de subcuenta', itemNombre: `${u.nombre} (${u.correo})`,
    consecuencias: ['Su sesión se cierra de inmediato en todos los equipos.', 'No podrá entrar a esta subcuenta hasta que lo reactives.', 'La revocación queda en la auditoría de la subcuenta.'],
    textoConfirmar: 'Revocar acceso',
    onConfirm: async () => {
      await agoraApi(`agency/users?accountId=${encodeURIComponent(cuenta.id)}&userId=${encodeURIComponent(u.id)}`, {method: 'DELETE'});
      toast(`Acceso revocado para ${u.nombre}.`);
      await alTerminar?.();
    },
  });
}

/* ---------- Revocar a un Superadmin de agencia (solo Master) ---------- */
function agenciaRevocarEquipo(u) {
  dialogoConfirmarAccionCritica({
    titulo: '¿Revocar al usuario de agencia?', subtitulo: 'Privilegios de Superadmin',
    itemTipo: 'Superadmin de agencia', itemNombre: `${u.nombre} (${u.correo})`,
    consecuencias: ['Pierde de inmediato el acceso a todas sus subcuentas asignadas.', 'Sus sesiones abiertas se cierran.', 'Puedes reactivarlo desde Editar acceso.'],
    textoConfirmar: 'Revocar privilegios',
    onConfirm: async () => {
      await agoraApi('agency/team', {method: 'PUT', body: {id: u.id, nombre: u.nombre, correo: u.correo, rol: u.rol, tenants: u.tenants || [], activo: false}});
      GOV.team = null; render(); toast(`Accesos revocados para ${u.nombre}.`);
    },
  });
}

/* ---------- Resumen de la agencia (tarjetas de indicadores) ----------
   Todo sale de agency/accounts: estado, venceDias, usuarios, usuariosActivos,
   ingresos30d (oportunidades ganadas en 30 días, en la moneda de cada subcuenta)
   y conveniosPorFirmar (convenios enviados sin firma). */
const AGENCIA_SIMBOLO = {USD: 'US$', EUR: '€', VES: 'Bs.'};
function agenciaEnRiesgo(a) {
  const d = agenciaVence(a);
  return agenciaEstado(a) !== 'activa' || (d !== null && d <= AGENCIA_UMBRAL_VENCE);
}
function agenciaMontosPorMoneda(lista) {
  const t = {};
  for (const a of lista) { const m = a.moneda || 'USD', v = Number(a.cobroMensual) || 0; t[m] = (t[m] || 0) + v; }
  const partes = Object.entries(t).filter(([, v]) => v > 0).sort((x, y) => y[1] - x[1]);
  return partes.length ? partes.map(([m, v]) => `${AGENCIA_SIMBOLO[m] || m} ${NF.format(v)}`) : [`${AGENCIA_SIMBOLO.USD} 0`];
}
function agenciaResumenHtml(accounts) {
  if (!Array.isArray(accounts)) return '';
  const total = accounts.length;
  const activas = accounts.filter(a => agenciaEstado(a) === 'activa').length;
  const riesgo = accounts.filter(agenciaEnRiesgo);
  const conCobro = riesgo.filter(a => Number(a.cobroMensual) > 0);
  const sinCobro = accounts.filter(a => a.cobroMensual === null || a.cobroMensual === undefined).length;
  const montos = agenciaMontosPorMoneda(riesgo);
  const totalMes = agenciaMontosPorMoneda(accounts);
  const pendSub = accounts.filter(a => agenciaEstado(a) === 'pendiente').length;
  const pendConv = accounts.reduce((s, a) => s + (Number(a.conveniosPorFirmar) || 0), 0);
  const usuarios = accounts.reduce((s, a) => s + (Number(a.usuarios) || 0), 0);
  const usuariosAct = accounts.reduce((s, a) => s + (Number(a.usuariosActivos ?? a.usuarios) || 0), 0);
  const pct = total ? Math.round(activas / total * 100) : 0;
  const tarjeta = (cls, ic, titulo, valor, detalle, filtro) => `<${filtro ? `button type="button" data-kpi-filter="${filtro}"` : 'div'} class="ag-resumen ${cls}">
      <span class="ag-resumen-ic" aria-hidden="true">${icon(ic)}</span>
      <span class="ag-resumen-t">${titulo}</span>
      <strong class="ag-resumen-v">${valor}</strong>
      <span class="ag-resumen-d">${detalle}</span>
    </${filtro ? 'button' : 'div'}>`;
  return `<section class="ag-resumen-grid" aria-label="Resumen de la agencia">
    ${tarjeta('is-ok', 'check', 'Subcuentas activas', `${activas}<small> de ${total}</small>`,
      `<span class="ag-resumen-bar" aria-hidden="true"><i style="width:${pct}%"></i></span>${pct} % funcionando`, 'activa')}
    ${tarjeta(conCobro.length ? 'is-risk' : riesgo.length ? 'is-warn' : '', 'warn', 'Ingresos en riesgo', montos.map(m => esc(m) + '<small>/mes</small>').join('<br>'),
      `${riesgo.length ? `${riesgo.length} ${riesgo.length === 1 ? 'subcuenta en riesgo' : 'subcuentas en riesgo'}` : 'Ninguna subcuenta en riesgo'} · cobras ${totalMes.map(esc).join(' + ')} al mes${sinCobro ? ` · ${sinCobro} sin cobro anotado` : ''}`, riesgo.length ? 'riesgo' : '')}
    ${tarjeta(pendSub + pendConv ? 'is-warn' : '', 'clock', 'Por aprobar', String(pendSub + pendConv),
      `${pendSub} ${pendSub === 1 ? 'subcuenta por activar' : 'subcuentas por activar'} · ${pendConv} ${pendConv === 1 ? 'convenio por firmar' : 'convenios por firmar'}`, pendSub ? 'pendiente' : '')}
    ${tarjeta('', 'users', 'Usuarios de la agencia', NI.format(usuarios),
      `${NI.format(usuariosAct)} con acceso · ${NI.format(usuarios - usuariosAct)} sin acceso`, '')}
  </section>
  <p class="ag-resumen-note"><b>¿De dónde salen estos números?</b> Los ingresos son lo que cada subcuenta te paga al mes por su plan y sus servicios; se anotan en Administrar → Cobros. Una subcuenta está en riesgo si está inactiva, pendiente o si su licencia vence en ${AGENCIA_UMBRAL_VENCE} días o menos. Toca una tarjeta para ver esas subcuentas.</p>`;
}

/* ---------- Cobros de cada subcuenta a la agencia (2.1.2) ----------
   Plan y servicios que paga la subcuenta, en su moneda. El servidor guarda centavos,
   calcula el cobro mensual equivalente (anual/12, trimestral/3, semestral/6; el pago
   único no suma) y audita cada cambio. Solo el Master edita. */
const AGENCIA_FRECUENCIAS = [['mensual', 'Mensual', 1], ['trimestral', 'Trimestral', 3], ['semestral', 'Semestral', 6], ['anual', 'Anual', 12], ['unico', 'Pago único', 0]];
const agenciaMonto = (v, moneda = 'USD') => `${AGENCIA_SIMBOLO[moneda] || moneda} ${NF.format(Number(v) || 0)}`;
function agenciaCobroTexto(a) {
  if (a?.cobroMensual === null || a?.cobroMensual === undefined) return 'Sin cobros registrados';
  return `${agenciaMonto(a.cobroMensual, a.moneda)}/mes`;
}
/** "1.234,56" o "1234.56" → "1234.56"; null si no es un monto válido. */
function agenciaLeerMonto(t) {
  let s = String(t ?? '').trim().replace(/\s/g, '');
  if (s === '') return '0';
  if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
  else if ((s.match(/\./g) || []).length > 1) s = s.replace(/\./g, '');
  return /^\d{1,9}(\.\d{1,2})?$/.test(s) ? s : null;
}
const agenciaMensual = (monto, f) => { const m = (AGENCIA_FRECUENCIAS.find(x => x[0] === f) || [])[2] || 0; return m ? (Number(monto) || 0) / m : 0; };

function agenciaCobrosResumenHtml(a) {
  const c = a.cobros;
  if (!c) return `<p class="dialog-copy">Cobros: <b>sin registrar</b>. ${esMaster() ? 'Registra el plan y los servicios que te paga esta subcuenta.' : ''}</p>`;
  const fx = f => (AGENCIA_FRECUENCIAS.find(x => x[0] === f) || [, f])[1];
  const activos = c.servicios.filter(s => s.activo);
  return `<div class="ag-cobros-res">
    <div class="ag-cobros-tot"><small>Cobro mensual equivalente</small><b>${esc(agenciaMonto(a.cobroMensual, a.moneda))}</b></div>
    <ul>
      <li><span>${esc(a.plan || 'Plan')}</span><b>${esc(agenciaMonto(c.plan.monto, a.moneda))} · ${esc(fx(c.plan.frecuencia))}</b></li>
      ${c.servicios.map(s => `<li class="${s.activo ? '' : 'is-off'}"><span>${esc(s.nombre)}${s.activo ? '' : ' (pausado)'}</span><b>${esc(agenciaMonto(s.monto, a.moneda))} · ${esc(fx(s.frecuencia))}</b></li>`).join('')}
    </ul>
    <p class="ag-cobros-pie">${activos.length} ${activos.length === 1 ? 'servicio activo' : 'servicios activos'}${c.actualizado ? ` · Actualizado ${esc(String(c.actualizado).slice(0, 10))}` : ''}</p>
  </div>`;
}

function agenciaCobrosEditar(a) {
  if (!esMaster()) { toast('Solo el Master puede cambiar los cobros.'); return; }
  const c = a.cobros || {plan: {monto: 0, frecuencia: 'mensual'}, servicios: []};
  const opciones = sel => AGENCIA_FRECUENCIAS.map(([v, t]) => `<option value="${v}" ${v === sel ? 'selected' : ''}>${t}</option>`).join('');
  const montoTx = v => Number(v) ? NF.format(Number(v)) : '';
  const fila = (s = {}) => `<div class="ag-serv" data-serv="${esc(s.id || '')}">
      <label class="fld ag-serv-n">Servicio<input data-k="nombre" maxlength="120" value="${esc(s.nombre || '')}" placeholder="Gestión de redes"></label>
      <label class="fld ag-serv-m">Monto<input data-k="monto" inputmode="decimal" value="${esc(montoTx(s.monto))}" placeholder="0,00"></label>
      <label class="fld ag-serv-f">Frecuencia<select data-k="frecuencia">${opciones(s.frecuencia || 'mensual')}</select></label>
      <label class="ag-serv-on"><input type="checkbox" data-k="activo" ${s.activo === false ? '' : 'checked'}><span>Activo</span></label>
      <button type="button" class="icon-btn ag-serv-x" data-quitar aria-label="Quitar servicio">${icon('x')}</button>
    </div>`;
  const s = openModal(modalHead('Cobros y fecha de corte · ' + esc(a.nombre || a.name), `Lo que te paga esta subcuenta, en ${esc(a.moneda || 'USD')}, y hasta cuándo tiene la licencia. Puedes cambiarlo cuando quieras.`) + `
    <form class="form ag-cobros-form" novalidate>
      <fieldset class="bx ag-cobros-corte"><legend>${icon('calendar')}Fecha de corte</legend>
        <p class="ag-cobros-ayuda">Es el día en que vence la licencia de esta subcuenta. Con esta fecha se arman los avisos de vencimiento y las Tareas de agencia.</p>
        <div class="ag-corte-fila">
          <label class="fld">Vence el<input type="date" name="corte" value="${esc(String(a.vence || '').slice(0, 10))}"></label>
          <div class="ag-corte-atajos" role="group" aria-label="Mover la fecha de corte"><button type="button" class="btn btn-sm" data-corte-mas="1">+1 mes</button><button type="button" class="btn btn-sm" data-corte-mas="3">+3 meses</button><button type="button" class="btn btn-sm" data-corte-mas="12">+1 año</button><button type="button" class="btn btn-sm" data-corte-quitar>Sin fecha</button></div>
        </div>
        <p class="ag-corte-tx" data-corte-tx></p>
      </fieldset>
      <fieldset class="bx ag-cobros-plan"><legend>${icon('wallet')}Plan</legend>
        <label class="fld">Nombre del plan<input name="nombrePlan" maxlength="80" value="${esc(a.plan || '')}" placeholder="Plan Oro"></label>
        <label class="fld">Monto<input name="planMonto" inputmode="decimal" value="${esc(montoTx(c.plan.monto))}" placeholder="0,00"></label>
        <label class="fld">Frecuencia<select name="planFrecuencia">${opciones(c.plan.frecuencia)}</select></label>
      </fieldset>
      <fieldset class="bx ag-cobros-servs"><legend>${icon('coins')}Servicios adicionales</legend>
        <div data-servs>${c.servicios.map(fila).join('')}</div>
        <button type="button" class="btn btn-sm" data-agregar>${icon('plus')}Agregar servicio</button>
      </fieldset>
      <div class="ag-cobros-tot is-live"><small>Cobro mensual equivalente</small><b data-total>—</b><span>Anual ÷ 12, semestral ÷ 6, trimestral ÷ 3. El pago único no suma al mes.</span></div>
      <p class="fld-err" role="alert"></p>
      <div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">Guardar</button></div>
    </form>`, {wide: true});
  const form = s.querySelector('form'), lista = s.querySelector('[data-servs]'), err = s.querySelector('.fld-err');
  const leer = () => {
    const planMonto = agenciaLeerMonto(form.planMonto.value);
    const servicios = [...lista.querySelectorAll('.ag-serv')].map(r => ({
      id: r.dataset.serv || undefined, nombre: r.querySelector('[data-k=nombre]').value.trim(),
      monto: agenciaLeerMonto(r.querySelector('[data-k=monto]').value), frecuencia: r.querySelector('[data-k=frecuencia]').value,
      activo: r.querySelector('[data-k=activo]').checked, fila: r,
    }));
    return {planMonto, servicios};
  };
  const total = () => {
    const {planMonto, servicios} = leer();
    const t = agenciaMensual(planMonto, form.planFrecuencia.value) + servicios.filter(x => x.activo).reduce((n, x) => n + agenciaMensual(x.monto, x.frecuencia), 0);
    s.querySelector('[data-total]').textContent = agenciaMonto(t, a.moneda) + '/mes';
  };
  s.querySelector('[data-agregar]').onclick = () => { lista.insertAdjacentHTML('beforeend', fila()); lista.lastElementChild.querySelector('input').focus(); total(); };
  lista.addEventListener('click', e => { const b = e.target.closest('[data-quitar]'); if (b) { b.closest('.ag-serv').remove(); total(); } });
  form.addEventListener('input', total); form.addEventListener('change', total); total();
  // Fecha de corte: atajos que suman meses desde la fecha actual (o desde hoy si no hay) y texto con los días que faltan.
  const corteTx = () => {
    const v = form.corte.value, el = s.querySelector('[data-corte-tx]');
    if (!v) { el.textContent = 'Sin fecha de corte: esta subcuenta no generará avisos de vencimiento.'; el.className = 'ag-corte-tx'; return; }
    const hoy = new Date(); const base = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    const [y, m, d] = v.split('-').map(Number), dias = Math.round((Date.UTC(y, m - 1, d) - base) / 86400000);
    el.textContent = `${agenciaFechaLarga(v)} · ${agenciaVenceTexto({venceDias: dias})}`;
    el.className = 'ag-corte-tx ' + (dias <= 7 ? 'is-bad' : dias <= AGENCIA_UMBRAL_VENCE ? 'is-warn' : 'is-ok');
  };
  s.querySelectorAll('[data-corte-mas]').forEach(b => b.onclick = () => {
    const hoy = new Date(), v = form.corte.value;
    const [y, m, d] = v ? v.split('-').map(Number) : [hoy.getFullYear(), hoy.getMonth() + 1, hoy.getDate()];
    const meses = Number(b.dataset.corteMas), dest = new Date(Date.UTC(y, m - 1 + meses, 1));
    const fin = new Date(Date.UTC(dest.getUTCFullYear(), dest.getUTCMonth() + 1, 0)).getUTCDate();
    dest.setUTCDate(Math.min(d, fin)); form.corte.value = dest.toISOString().slice(0, 10); corteTx();
  });
  s.querySelector('[data-corte-quitar]').onclick = () => { form.corte.value = ''; corteTx(); };
  form.corte.addEventListener('input', corteTx); corteTx();
  form.onsubmit = async e => {
    e.preventDefault(); err.textContent = '';
    form.querySelectorAll('[aria-invalid]').forEach(x => x.removeAttribute('aria-invalid'));
    const {planMonto, servicios} = leer();
    const marcar = (el, msg) => { el.setAttribute('aria-invalid', 'true'); el.focus(); err.textContent = msg; };
    if (planMonto === null) return marcar(form.planMonto, 'El monto del plan no es válido. Usa números con hasta dos decimales, por ejemplo 120,50.');
    for (const x of servicios) {
      if (!x.nombre) return marcar(x.fila.querySelector('[data-k=nombre]'), 'Escribe el nombre del servicio o quítalo.');
      if (x.monto === null) return marcar(x.fila.querySelector('[data-k=monto]'), `El monto de "${x.nombre}" no es válido.`);
    }
    const btn = form.querySelector('[type=submit]'); busy(btn, true, 'Guardando…');
    try {
      await agoraApi('agency/billing', {method: 'PUT', body: {id: a.id, corte: form.corte.value || null, nombrePlan: form.nombrePlan.value.trim(), plan: {monto: planMonto, frecuencia: form.planFrecuencia.value}, servicios: servicios.map(({fila: _f, ...x}) => x)}});
      await Promise.all([cargarAgencia(), cargarActividadAgencia()]);
      closeModal(); repaint(); toast('Cobros y fecha de corte guardados. Los avisos y los indicadores ya usan los datos nuevos.');
    } catch (x) { busy(btn, false); err.textContent = x.message || 'No se pudieron guardar los cobros.'; }
  };
}

/* =====================================================================================
   2.1.15 · Módulo ARP con el mismo esqueleto de la agencia
   -------------------------------------------------------------------------------------
   Los módulos de Ágora (ARP Comercial, Bandeja, Contactos, Oportunidades, Ajustes y Ayuda)
   se pintan desde agora-modulos.js. Aquí no se toca su lógica: solo se les pone la misma
   estructura visual de la agencia (icono de sección en recuadro vino, cabecera con ayuda,
   tarjetas con icono) y la paleta de colores de la cuenta.
   ===================================================================================== */

/* Icono de cada bloque según su título. Lo más específico va primero. */
const ARP_ICONOS = [
  [/tu d[ií]a|hoy|calendario|cita/i, 'calendar'],
  [/operaci[oó]n/i, 'pulse'],
  [/sin leer|conversaci|bandeja|mensaje|esperando respuesta|chat|whatsapp/i, 'chat'],
  [/fuente|canal|origen/i, 'split'],
  [/tiempo|sla|primera respuesta|velocidad|d[ií]as al cierre|cierre promedio|demora/i, 'clock'],
  [/inversi|anuncio|campa|publicidad|impresi|clic|meta ads/i, 'rocket'],
  [/coste|costo|gasto|comisi|ganancia|rentab|retorno|dinero|ticket|monto|valor|precio|presupuesto/i, 'coins'],
  [/equipo|ejecutiv|asesor|desempe|responsable/i, 'team'],
  [/oportunidad|embudo|etapa|ganad|venta|cierre|tasa/i, 'trend'],
  [/meta|avance|objetivo/i, 'check'],
  [/lead|contacto|persona|cartera/i, 'users'],
  [/aviso|novedad|notific|dispositivo/i, 'bell'],
  [/cuenta|conexi|integraci|acceso|clave/i, 'lock'],
  [/copia|respaldo|seguridad/i, 'shield'],
  [/identidad|logo|color|paleta/i, 'palette'],
  [/ayuda|manual|gu[ií]a/i, 'info'],
];
const arpIcono = texto => (ARP_ICONOS.find(([re]) => re.test(texto || '')) || [null, 'pie'])[1];
const arpTile = (texto, cls) => `<span class="${cls}" aria-hidden="true">${icon(IC[arpIcono(texto)] ? arpIcono(texto) : 'pie')}</span>`;

/** Pone el esqueleto de la agencia sobre lo que acaba de pintar un módulo ARP. Es idempotente. */
function arpEsqueleto(root) {
  if (!root || !root.classList?.contains('ag')) return;
  arpPersonalizar(root);
  // Cabeceras de sección: icono + título + ayuda (igual que secH en la agencia).
  root.querySelectorAll('.t-sec-row:not([data-arp])').forEach(row => {
    row.dataset.arp = '1'; row.classList.add('arp-sec');
    row.insertAdjacentHTML('afterbegin', arpTile(row.querySelector('h2')?.textContent, 'arp-sec-ic'));
  });
  root.querySelectorAll('h2.t-sec:not([data-arp])').forEach(h => {
    h.dataset.arp = '1';
    if (h.closest('.t-sec-row')) return;
    const row = document.createElement('div'); row.className = 'arp-sec arp-sec-solo'; row.dataset.arp = '1';
    h.replaceWith(row); row.insertAdjacentHTML('afterbegin', arpTile(h.textContent, 'arp-sec-ic')); const box = document.createElement('div'); box.append(h); row.append(box);
  });
  // Paneles: icono en recuadro junto al título, como las secciones de la agencia.
  root.querySelectorAll('.panel-h:not([data-arp])').forEach(ph => {
    ph.dataset.arp = '1';
    const h = ph.querySelector('h2'); if (!h) return;
    ph.classList.add('arp-ph'); ph.insertAdjacentHTML('afterbegin', arpTile(h.textContent, 'arp-ph-ic'));
  });
  // Tarjetas de cifras: icono + rótulo arriba, cifra grande y nota (como el resumen de la agencia).
  root.querySelectorAll('.micro:not([data-arp])').forEach(m => {
    m.dataset.arp = '1';
    const h = m.querySelector(':scope > h3'); if (!h || m.querySelector('.sk')) return;
    m.classList.add('arp-mic'); h.insertAdjacentHTML('beforebegin', arpTile(h.textContent, 'arp-mic-ic'));
  });
  // Entrada escalonada de los bloques, igual que las tarjetas de la agencia.
  // Solo en la primera carga de la pantalla: las actualizaciones automáticas no vuelven a animar.
  let i = 0; const primera = performance.now() - ARP_T < 1800;
  root.querySelectorAll('.micro:not([data-arp-in]), .hero:not([data-arp-in]), .panel:not([data-arp-in]), .board-column:not([data-arp-in]), .integration-card:not([data-arp-in]), .live-inbox-layout > section:not([data-arp-in]), .records-layout > *:not([data-arp-in])').forEach(el => {
    el.dataset.arpIn = primera ? '1' : '0'; if (primera) el.style.setProperty('--arp-i', String(Math.min(i++, 12)));
  });
}
let ARP_OBS = null, ARP_RAF = 0, ARP_T = 0;
function arpVigilar() {
  ARP_T = performance.now();
  const view = document.getElementById('view');
  if (!view || !view.classList.contains('ag')) return;
  arpEsqueleto(view);
  if (ARP_OBS && ARP_OBS.__view === view) return;
  ARP_OBS?.disconnect();
  ARP_OBS = new MutationObserver(() => { cancelAnimationFrame(ARP_RAF); ARP_RAF = requestAnimationFrame(() => arpEsqueleto(view)); });
  ARP_OBS.__view = view;
  ARP_OBS.observe(view, {childList: true, subtree: true});
}

/* =====================================================================================
   Paleta de colores por cuenta (2.1.15)
   -------------------------------------------------------------------------------------
   Cada subcuenta puede elegir su color principal (botones, iconos, estado actual) y su
   color de acento (resaltes). Se guarda con la identidad del espacio. La agencia sigue
   siempre con los colores de Ágora: la paleta solo se aplica dentro de la subcuenta.
   ===================================================================================== */
const PALETAS = [
  ['agora', 'Ágora', '#AD103C', '#FABD4B'],
  ['azul', 'Azul profundo', '#282E49', '#FABD4B'],
  ['oceano', 'Océano', '#0B6E99', '#36C2B4'],
  ['esmeralda', 'Esmeralda', '#0F7B5F', '#E2B33C'],
  ['morado', 'Morado', '#5B3FA8', '#F08BB4'],
  ['naranja', 'Naranja', '#C2410C', '#2E3A59'],
  ['grafito', 'Grafito', '#3F3F46', '#AD103C'],
];
const HEX = /^#[0-9a-f]{6}$/i;
const hexRgb = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
const rgbHex = rgb => '#' + rgb.map(v => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('').toUpperCase();
const luz = h => { const c = hexRgb(h).map(v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; };
const contraste = (a, b) => { const x = luz(a), y = luz(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
const mezclar = (h, con, t) => { const a = hexRgb(h), b = hexRgb(con); return rgbHex(a.map((v, i) => v + (b[i] - v) * t)); };
/** Normaliza una paleta: dos colores #RRGGBB o nada. */
function paletaLimpia(p) {
  if (!p || typeof p !== 'object') return null;
  const principal = HEX.test(p.principal || '') ? p.principal.toUpperCase() : null;
  const acento = HEX.test(p.acento || '') ? p.acento.toUpperCase() : null;
  return principal ? {principal, acento: acento || '#FABD4B'} : null;
}
/** El principal debe leerse sobre blanco (claro) y sobre negro (oscuro): se ajusta solo si hace falta. */
function paletaTonos(p) {
  let claro = p.principal, oscuro = p.principal;
  for (let i = 0; i < 12 && contraste(claro, '#FFFFFF') < 4.5; i++) claro = mezclar(claro, '#000000', .1);
  for (let i = 0; i < 12 && contraste(oscuro, '#1C1C1E') < 4.5; i++) oscuro = mezclar(oscuro, '#FFFFFF', .14);
  const tinta = c => contraste(c, '#FFFFFF') >= 4.5 ? '#FFFFFF' : '#1D1D1F';
  return {claro, oscuro, tintaClaro: tinta(claro), tintaOscuro: tinta(oscuro), acento: p.acento};
}
const PALETA_VARS = ['--brand', '--brand-ink', '--brand-soft', '--gold', '--mod-vino', '--focus', '--iso'];
/** Aplica (o quita) la paleta de la cuenta activa. Se llama en cada pintado y al cambiar de tema. */
function aplicarPaleta(forzada) {
  const root = document.documentElement;
  const enCuenta = !!ME && !(esSuperAdmin() && AGENCY_CONTEXT);
  const p = paletaLimpia(forzada !== undefined ? forzada : (enCuenta ? ESPACIO?.paleta : null));
  if (!p) { PALETA_VARS.forEach(v => root.style.removeProperty(v)); delete root.dataset.paleta; return; }
  const t = paletaTonos(p), oscuro = root.dataset.theme === 'dark';
  const brand = oscuro ? t.oscuro : t.claro;
  root.style.setProperty('--brand', brand);
  root.style.setProperty('--brand-ink', oscuro ? t.tintaOscuro : t.tintaClaro);
  root.style.setProperty('--brand-soft', `color-mix(in srgb, ${brand} ${oscuro ? 18 : 10}%, transparent)`);
  root.style.setProperty('--mod-vino', brand);
  root.style.setProperty('--gold', t.acento);
  root.style.setProperty('--focus', `color-mix(in srgb, ${brand} 45%, transparent)`);
  // La marca Ágora conserva su vino: la paleta de la cuenta nunca pinta el logo de Ágora.
  root.style.setProperty('--iso', oscuro ? '#F0477A' : '#AD103C');
  root.dataset.paleta = 'cuenta';
}
/** Recuadro del formulario de identidad para elegir la paleta, con vista previa en vivo. */
function paletaBx(actual) {
  const p = paletaLimpia(actual) || {principal: PALETAS[0][2], acento: PALETAS[0][3]};
  const elegida = PALETAS.find(x => x[2] === p.principal && x[3] === p.acento)?.[0] || 'propia';
  return `<fieldset class="bx bx-wide pal-bx">
    <legend>${icon('palette')}Colores de la cuenta</legend>
    <p class="bx-help">Se usan en los botones, los iconos y los resaltes de esta subcuenta. La agencia sigue con los colores de Ágora.</p>
    <div class="pal-presets" role="radiogroup" aria-label="Paletas sugeridas">
      ${PALETAS.map(([id, nombre, a, b]) => `<button type="button" class="pal-op${id === elegida ? ' is-on' : ''}" role="radio" aria-checked="${id === elegida}" data-pal="${id}" data-a="${a}" data-b="${b}"><span class="pal-sw" style="--a:${a};--b:${b}" aria-hidden="true"></span><span>${nombre}</span></button>`).join('')}
    </div>
    <div class="pal-row">
      <label class="fld pal-fld">Color principal<span class="pal-in"><input type="color" name="palPrincipal" value="${p.principal}"><input type="text" name="palPrincipalHex" value="${p.principal}" maxlength="7" spellcheck="false" aria-label="Código del color principal" inputmode="text"></span></label>
      <label class="fld pal-fld">Color de acento<span class="pal-in"><input type="color" name="palAcento" value="${p.acento}"><input type="text" name="palAcentoHex" value="${p.acento}" maxlength="7" spellcheck="false" aria-label="Código del color de acento" inputmode="text"></span></label>
      <div class="pal-prev" aria-hidden="true"><span class="pal-prev-ic">${icon('trend')}</span><span class="pal-prev-btn">Guardar</span><span class="pal-prev-chip">Activa</span><span class="pal-prev-bar"><i></i></span></div>
    </div>
    <p class="pal-aviso" role="status"></p>
    <input type="hidden" name="paleta" value="${esc(JSON.stringify(p))}">
  </fieldset>`;
}
/** Conecta el recuadro de paleta de un formulario. `vivo` aplica la paleta a la pantalla mientras se elige. */
function paletaConectar(form, vivo = false) {
  const bx = form?.querySelector('.pal-bx'); if (!bx) return;
  const el = n => form.elements.namedItem(n);
  const pintar = () => {
    const p = {principal: el('palPrincipal').value.toUpperCase(), acento: el('palAcento').value.toUpperCase()};
    el('paleta').value = JSON.stringify(p);
    const t = paletaTonos(p);
    bx.style.setProperty('--pal-a', t.claro); bx.style.setProperty('--pal-ink', t.tintaClaro); bx.style.setProperty('--pal-b', p.acento);
    const aviso = bx.querySelector('.pal-aviso');
    aviso.textContent = t.claro !== p.principal ? `Ajustamos el principal a ${t.claro} para que el texto se lea bien sobre fondo claro.` : '';
    bx.querySelectorAll('.pal-op').forEach(b => { const on = b.dataset.a === p.principal && b.dataset.b === p.acento; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', String(on)); });
    if (vivo) aplicarPaleta(p);
  };
  bx.addEventListener('click', e => {
    const b = e.target.closest('.pal-op'); if (!b) return;
    el('palPrincipal').value = b.dataset.a; el('palPrincipalHex').value = b.dataset.a;
    el('palAcento').value = b.dataset.b; el('palAcentoHex').value = b.dataset.b; pintar();
  });
  [['palPrincipal', 'palPrincipalHex'], ['palAcento', 'palAcentoHex']].forEach(([c, h]) => {
    el(c).addEventListener('input', () => { el(h).value = el(c).value.toUpperCase(); pintar(); });
    el(h).addEventListener('input', () => { const v = el(h).value.trim(); const x = v.startsWith('#') ? v : '#' + v; if (HEX.test(x)) { el(c).value = x.toLowerCase(); pintar(); } });
  });
  pintar();
}

/* =====================================================================================
   Logos de empresa (2.1.15): solo PNG, proporción revisada y sin pixelado
   -------------------------------------------------------------------------------------
   - Se acepta únicamente PNG (se revisa el tipo y la firma del archivo, no solo el nombre).
   - Mínimo 256 px en el lado largo y 64 px en el corto; proporción entre 1:3 y 6:1.
   - Se recortan los bordes transparentes para que el logo llene su recuadro.
   - Si es muy grande se reduce a 1024 px en el lado largo con suavizado de alta calidad,
     y se guarda como PNG con transparencia. Así se ve nítido en pantallas de alta densidad.
   ===================================================================================== */
const LOGO_REGLAS = {maxBytes: 2 * 1024 * 1024, minLargo: 256, minCorto: 64, maxLado: 1024, minProp: 1 / 3, maxProp: 6};
const LOGO_AYUDA = 'Solo PNG (ideal con fondo transparente), de al menos 256 px en el lado más largo y hasta 2 MB. Lo ajustamos para que se vea nítido.';
async function logoPng(file) {
  const R = LOGO_REGLAS;
  if (!file) throw new Error('Elige un archivo PNG.');
  if (file.type !== 'image/png' || !/\.png$/i.test(file.name || '.png')) throw new Error('El logo debe ser un archivo PNG. Guárdalo como .png e inténtalo de nuevo.');
  if (!file.size || file.size > R.maxBytes) throw new Error(`El archivo pesa ${Math.round((file.size || 0) / 1024)} KB. El máximo es 2 MB.`);
  const firma = new Uint8Array(await file.slice(0, 8).arrayBuffer());
  if (![0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A].every((b, i) => firma[i] === b)) throw new Error('El archivo dice ser PNG pero no lo es. Expórtalo de nuevo como PNG.');
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => no(new Error('No se pudo leer la imagen.')); i.src = url; });
    let w = img.naturalWidth, h = img.naturalHeight;
    if (!w || !h) throw new Error('No se pudo leer el tamaño de la imagen.');
    if (w > 4096 || h > 4096) throw new Error(`La imagen mide ${w} × ${h} px. El máximo es 4096 px por lado.`);
    // Recorte de bordes transparentes (y de un fondo blanco liso no, para no comernos el diseño).
    const c0 = document.createElement('canvas'); c0.width = w; c0.height = h;
    const x0 = c0.getContext('2d', {willReadFrequently: true}); x0.drawImage(img, 0, 0);
    const px = x0.getImageData(0, 0, w, h).data;
    let top = h, left = w, right = -1, bottom = -1;
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) if (px[(y * w + x) * 4 + 3] > 8) { if (x < left) left = x; if (x > right) right = x; if (y < top) top = y; if (y > bottom) bottom = y; }
    if (right < 0) throw new Error('El logo está completamente transparente.');
    const pad = Math.round(Math.max(right - left + 1, bottom - top + 1) * .02);
    left = Math.max(0, left - pad); top = Math.max(0, top - pad); right = Math.min(w - 1, right + pad); bottom = Math.min(h - 1, bottom + pad);
    const cw = right - left + 1, ch = bottom - top + 1;
    const largo = Math.max(cw, ch), corto = Math.min(cw, ch), prop = cw / ch;
    if (largo < R.minLargo || corto < R.minCorto) throw new Error(`El logo mide ${cw} × ${ch} px sin bordes. Se vería pixelado: súbelo de al menos 256 px en el lado más largo (ideal 1024 px).`);
    if (prop > R.maxProp || prop < R.minProp) throw new Error(`La proporción del logo (${cw} × ${ch} px) es muy ${prop > 1 ? 'alargada' : 'alta'}. Usa una versión entre 1:3 y 6:1, por ejemplo el isotipo o el logo apilado.`);
    // Reducción en pasos de mitad: más nítida que un solo salto grande.
    const k = Math.min(1, R.maxLado / largo);
    let src = document.createElement('canvas'); src.width = cw; src.height = ch;
    src.getContext('2d').drawImage(c0, left, top, cw, ch, 0, 0, cw, ch);
    let sw = cw, sh = ch; const tw = Math.max(1, Math.round(cw * k)), th = Math.max(1, Math.round(ch * k));
    while (sw / 2 >= tw && sh / 2 >= th) {
      const c = document.createElement('canvas'); c.width = Math.round(sw / 2); c.height = Math.round(sh / 2);
      const x = c.getContext('2d'); x.imageSmoothingEnabled = true; x.imageSmoothingQuality = 'high'; x.drawImage(src, 0, 0, c.width, c.height);
      src = c; sw = c.width; sh = c.height;
    }
    const out = document.createElement('canvas'); out.width = tw; out.height = th;
    const xo = out.getContext('2d'); xo.imageSmoothingEnabled = true; xo.imageSmoothingQuality = 'high'; xo.drawImage(src, 0, 0, tw, th);
    const data = out.toDataURL('image/png');
    if (!data.startsWith('data:image/png;base64,')) throw new Error('No se pudo preparar el logo.');
    if (data.length > 2900000) throw new Error('El logo quedó muy pesado. Prueba con una versión más simple o con menos colores.');
    return {data, w: tw, h: th, original: {w, h}, recorte: {w: cw, h: ch}, prop};
  } finally { URL.revokeObjectURL(url); }
}
const logoProp = r => { const p = r.prop; return p >= 1 ? `${(Math.round(p * 10) / 10).toString().replace('.', ',')}:1` : `1:${(Math.round(10 / p) / 10).toString().replace('.', ',')}`; };
const logoNota = r => `PNG listo: ${r.w} × ${r.h} px · proporción ${logoProp(r)}${r.original.w !== r.recorte.w || r.original.h !== r.recorte.h ? ' · bordes vacíos recortados' : ''}.`;

/* =====================================================================================
   2.1.16 · Cada negocio a su manera
   -------------------------------------------------------------------------------------
   1. Tipo de cuenta: la agencia define, al crear o editar la subcuenta, qué tipo de cliente
      es (servicios, producto…), qué vende y cómo cobra (recurrente, pago único, venta única).
   2. Productos y servicios: rubros y productos manuales con su sistema de cobro.
   3. Personalizar: el administrador elige las tarjetas del tablero del Inicio, qué secciones
      y cifras muestra el ARP Comercial (y en qué orden), las etiquetas y embudos del CRM y
      qué módulos se ven en su subcuenta.
   Todo se guarda en el servidor por subcuenta (agora_settings), sin migraciones.
   ===================================================================================== */

/* ---------- 1. Tipo de cuenta ---------- */
const PERFIL_OPC = {
  tipoCliente: [['servicios', 'Cliente de servicios', 'La agencia le presta servicios: mercadeo, atención, gestión.'], ['producto', 'Cliente de producto', 'Usa el producto o la licencia de Ágora.'], ['mixto', 'Servicios y producto', 'Usa la licencia y además contrata servicios.'], ['interna', 'Cuenta interna', 'Es de la propia agencia; no se le cobra.']],
  negocio: [['servicios', 'Servicios'], ['productos', 'Productos'], ['mixto', 'Servicios y productos'], ['suscripciones', 'Suscripciones o planes']],
  cobro: [['recurrente', 'Cobro recurrente', 'Cobra cada cierto tiempo, por ejemplo cada mes.'], ['unico', 'Pago único', 'Cobra una sola vez por el servicio.'], ['venta', 'Venta única', 'Vende y cobra en el momento, sin repetir.'], ['mixto', 'Mixto', 'Combina cobros recurrentes y ventas.']],
};
const FRECUENCIAS_P = [['semanal', 'Semanal'], ['quincenal', 'Quincenal'], ['mensual', 'Mensual'], ['trimestral', 'Trimestral'], ['semestral', 'Semestral'], ['anual', 'Anual']];
const FREC_MES = {semanal: 52 / 12, quincenal: 2, mensual: 1, trimestral: 1 / 3, semestral: 1 / 6, anual: 1 / 12};
const FREC_CADA = {semanal: 'semana', quincenal: 'quincena', mensual: 'mes', trimestral: 'trimestre', semestral: 'semestre', anual: 'año'};
const perfilEt = (k, v) => (PERFIL_OPC[k].find(x => x[0] === v) || [])[1] || '';
/** "Cliente de servicios · Cobro recurrente mensual · día 5". */
function perfilTexto(p, corto = false) {
  if (!p) return 'Sin definir';
  const cobro = perfilEt('cobro', p.cobro) + (p.frecuencia && ['recurrente', 'mixto'].includes(p.cobro) ? ' ' + (FRECUENCIAS_P.find(x => x[0] === p.frecuencia)?.[1] || '').toLowerCase() : '');
  return corto ? `${perfilEt('tipoCliente', p.tipoCliente)} · ${cobro}` : `${perfilEt('tipoCliente', p.tipoCliente)} · vende ${perfilEt('negocio', p.negocio).toLowerCase()} · ${cobro}${p.diaCobro ? ` · día ${p.diaCobro}` : ''}`;
}
/** Recuadro "Tipo de cuenta" del formulario de la subcuenta (patrón .bx). */
function perfilBx(p, compacto = false) {
  const v = p || {tipoCliente: 'servicios', negocio: 'servicios', cobro: 'recurrente', frecuencia: 'mensual', diaCobro: null, factura: false};
  const radios = (k, cols) => `<div class="pfl-ops pfl-${cols}" role="radiogroup">${PERFIL_OPC[k].map(([id, t, d]) => `<label class="pfl-op"><input type="radio" name="pf_${k}" value="${id}" ${v[k] === id ? 'checked' : ''}><span ${compacto && d ? `title="${esc(d)}"` : ''}><b>${esc(t)}</b>${d && !compacto ? `<small>${esc(d)}</small>` : ''}</span></label>`).join('')}</div>`;
  return `<fieldset class="bx bx-wide pfl-bx">
    <legend>${icon('split')}Tipo de cuenta</legend>
    <p class="bx-help">Define qué tipo de cliente es y cómo cobra. Con esto se arma su tablero inicial y sus productos; la subcuenta lo puede ajustar después en Personalizar.</p>
    <div class="pfl-grid ${compacto ? 'is-compacto' : ''}">
      <div class="pfl-col"><p class="pfl-l">¿Qué le das a esta empresa?</p>${radios('tipoCliente', compacto ? 4 : 2)}</div>
      <div class="pfl-col"><p class="pfl-l">¿Cómo cobra esta empresa a sus clientes?</p>${radios('cobro', compacto ? 4 : 2)}</div>
    </div>
    <div class="bx-row pfl-row">
      <label class="fld">Qué vende<select name="pf_negocio">${PERFIL_OPC.negocio.map(([id, t]) => `<option value="${id}" ${v.negocio === id ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>
      <label class="fld" data-pfl-frec>Frecuencia del cobro<select name="pf_frecuencia">${FRECUENCIAS_P.map(([id, t]) => `<option value="${id}" ${(v.frecuencia || 'mensual') === id ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
      <label class="fld">Día de cobro<input name="pf_dia" inputmode="numeric" maxlength="2" placeholder="1 a 28" value="${v.diaCobro || ''}"></label>
      <label class="bx-check pfl-fact"><input type="checkbox" name="pf_factura" ${v.factura ? 'checked' : ''}> Emite factura</label>
    </div>
  </fieldset>`;
}
function perfilConectar(form) {
  const frec = form.querySelector('[data-pfl-frec]'); if (!frec) return;
  const sync = () => { const c = form.querySelector('[name=pf_cobro]:checked')?.value; frec.hidden = !['recurrente', 'mixto'].includes(c); };
  form.querySelectorAll('[name=pf_cobro]').forEach(r => r.addEventListener('change', sync)); sync();
}
/** Lee el recuadro. Devuelve {perfil} o {error}. */
function perfilLeer(form) {
  const val = n => form.querySelector(`[name=${n}]:checked`)?.value || form.elements.namedItem(n)?.value || '';
  const dia = String(form.elements.namedItem('pf_dia')?.value || '').trim();
  if (dia && !(/^\d{1,2}$/.test(dia) && +dia >= 1 && +dia <= 28)) return {error: 'El día de cobro va del 1 al 28.'};
  const cobro = val('pf_cobro') || 'recurrente';
  return {perfil: {tipoCliente: val('pf_tipoCliente') || 'servicios', negocio: val('pf_negocio') || 'servicios', cobro, frecuencia: ['recurrente', 'mixto'].includes(cobro) ? val('pf_frecuencia') || 'mensual' : null, diaCobro: dia ? Number(dia) : null, factura: !!form.elements.namedItem('pf_factura')?.checked, notas: ''}};
}
/** Editar solo el tipo de cuenta desde Administrar subcuenta. */
function perfilEditar(a) {
  if (!esSuperAdmin() || !a) return;
  const s = openModal(modalHead('Tipo de cuenta · ' + esc(a.nombre || a.name), 'Qué tipo de cliente es y cómo cobra. Puedes cambiarlo cuando quieras.') +
    `<form class="bx-form" novalidate><div class="bx-grid">${perfilBx(a.perfil)}</div><p class="fld-err" role="alert"></p>
     <div class="bx-foot"><p class="bx-help">Cambiarlo no borra datos: solo ajusta el tablero inicial de la subcuenta.</p><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Guardar tipo de cuenta</button></div></div></form>`, {wide: true});
  const f = s.querySelector('form'), err = s.querySelector('.fld-err');
  perfilConectar(f);
  f.onsubmit = async e => {
    e.preventDefault(); const r = perfilLeer(f); if (r.error) { err.textContent = r.error; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      await agoraApi('agency/accounts', {method: 'PUT', body: {id: a.id, nombre: a.nombre, subcuenta: a.subcuenta || a.name, codigo: a.codigo, rubro: a.rubro || 'Otro', moneda: a.moneda || 'USD', estado: a.estado || 'activa', notas: a.notas || '', perfil: r.perfil}});
      closeModal(); await cargarAgencia(); repaint(); toast('Tipo de cuenta guardado.');
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}

/* ---------- Datos de la subcuenta activa ---------- */
let CATALOGO = null, CATALOGO_P = null, TABLERO_DATOS = null, TABLERO_T = 0;
window.addEventListener?.('agora:account-changing', () => { CATALOGO = null; CATALOGO_P = null; TABLERO_DATOS = null; TABLERO_T = 0; PZ = null; PZ_CRM = null; });
const personalCfg = () => ESPACIO?.personal || null;
const cuentaPerfil = () => ESPACIO?.perfil || null;
function cargarCatalogo(force) {
  if (CATALOGO && !force) return Promise.resolve(CATALOGO);
  CATALOGO_P ??= agoraApi('workspace/productos').then(d => { CATALOGO = {rubros: d.rubros || [], productos: d.productos || [], canEdit: !!d.canEdit, actualizado: d.actualizado || null}; return CATALOGO; }).finally(() => { CATALOGO_P = null; });
  return CATALOGO_P;
}
async function guardarPersonal(config) {
  const d = await agoraApi('workspace/personalizar', {method: 'PUT', body: {config}});
  if (ESPACIO) ESPACIO.personal = d.config || config;
  TABLERO_DATOS = null;
  return d;
}
/** Módulos que nunca se ocultan: sin ellos no se puede volver a encender nada. */
var MODULOS_FIJOS = ['centro', 'personalizar', 'usuarios', 'conexiones', 'ayuda'];
/* Se llama antes de que este bloque corra (la primera ruta): por eso no depende de constantes. */
function moduloOculto(id) {
  if (['centro', 'personalizar', 'usuarios', 'conexiones', 'ayuda'].includes(id)) return false;
  const o = ESPACIO?.personal?.modulos?.ocultos;
  return Array.isArray(o) && o.includes(id);
}
const moduloApagado = () => `<section class="panel"><p class="empty"><b>${esc(MODS[R.mod]?.name || 'Módulo')} está apagado</b>Quien administra esta subcuenta lo ocultó en Personalizar → Módulos.<br><a class="btn btn-cta" href="${href('/centro')}">${icon('home')}Volver al inicio</a>${esAdmin() ? `<a class="btn" href="${href('/personalizar')}">${icon('gear')}Ir a Personalizar</a>` : ''}</p></section>`;

/* ---------- 2. Productos y servicios ---------- */
const COBRO_P = {recurrente: ['Recurrente', 'sc-ok'], unico: ['Pago único', 'sc-info'], venta: ['Venta única', 'sc-warn']};
const mensualDe = p => p.cobro === 'recurrente' && p.activo !== false ? (Number(p.precio) || 0) * (FREC_MES[p.frecuencia] || 1) : 0;
const precioTexto = p => agenciaMonto(Number(p.precio) || 0, p.moneda || 'USD') + (p.cobro === 'recurrente' ? ` / ${FREC_CADA[p.frecuencia] || 'mes'}` : '');
const nuevoId = pre => pre + '-' + Math.random().toString(36).slice(2, 9);
function porMoneda(lista, f) {
  const t = {}; for (const x of lista) { const m = x.moneda || 'USD'; t[m] = (t[m] || 0) + f(x); }
  const p = Object.entries(t).filter(([, v]) => v > 0);
  return p.length ? p.map(([m, v]) => agenciaMonto(v, m)).join(' + ') : agenciaMonto(0, ESPACIO?.moneda || 'USD');
}
let PROD_F = {cobro: '', rubro: '', q: ''};
MODS.productos = {g: 'ingresos', view: 'productos', name: 'Productos y servicios', icon: 'wallet', path: '/productos', c: 'var(--mod-oro)',
  desc: 'Tus rubros y productos, con su forma de cobro.', ok: () => !!ME || agoraReady()};
V.productos = {
  need: () => Promise.all([cargarCatalogo(), MODS.catalogo?.ok() && typeof cargarTablero === 'function' ? cargarTablero().catch(() => null) : null]),
  render() {
    if (!CATALOGO) return loading('Productos y servicios');
    const {rubros, productos, canEdit} = CATALOGO;
    const activos = productos.filter(p => p.activo !== false);
    const rec = activos.filter(p => p.cobro === 'recurrente');
    const q = norm(PROD_F.q || '');
    const lista = productos.filter(p => (!PROD_F.cobro || p.cobro === PROD_F.cobro) && (!PROD_F.rubro || p.rubroId === PROD_F.rubro || (PROD_F.rubro === '-' && !p.rubroId)) && (!q || norm(`${p.nombre} ${p.codigo} ${p.descripcion}`).includes(q)));
    const rubroDe = id => rubros.find(r => r.id === id)?.nombre || 'Sin rubro';
    const chip = (k, v, t, n) => `<button type="button" class="agency-chip-btn ${PROD_F[k] === v ? 'is-active' : ''}" data-pf="${k}" data-v="${esc(v)}" aria-pressed="${PROD_F[k] === v}">${esc(t)}${n != null ? ` <span class="n">${int(n)}</span>` : ''}</button>`;
    const filas = lista.map(p => `<tr class="${p.activo === false ? 'is-off' : ''}">
      <td><b>${esc(p.nombre)}</b><small>${p.codigo ? `<span class="mono">${esc(p.codigo)}</span> · ` : ''}${p.tipo === 'producto' ? 'Producto' : 'Servicio'}</small></td>
      <td>${esc(rubroDe(p.rubroId))}</td>
      <td><span class="sc ${COBRO_P[p.cobro]?.[1] || 'sc-gray'}">${esc(COBRO_P[p.cobro]?.[0] || '—')}</span>${p.cobro === 'recurrente' && p.duracion ? `<small>${int(p.duracion)} cobros</small>` : p.cobro === 'recurrente' ? '<small>Sin fecha de fin</small>' : p.cobro === 'venta' && p.stock != null ? `<small>${int(p.stock)} en existencia</small>` : ''}</td>
      <td class="n">${esc(precioTexto(p))}${p.cobro === 'recurrente' && p.frecuencia !== 'mensual' ? `<small>Equivale a ${esc(agenciaMonto(mensualDe({...p, activo: true}), p.moneda))} al mes</small>` : ''}</td>
      <td><span class="sc ${p.activo === false ? 'sc-gray' : 'sc-ok'}">${p.activo === false ? 'Pausado' : 'Activo'}</span></td>
      <td class="n">${canEdit ? `<button type="button" class="icon-btn" data-prod="${esc(p.id)}" aria-label="Editar ${esc(p.nombre)}" title="Editar">${icon('edit')}</button>` : ''}</td></tr>`).join('');
    const vacio = productos.length ? '<b>No hay productos con estos filtros</b>Prueba con otro rubro o forma de cobro.' : `<b>Todavía no hay productos</b>${canEdit ? 'Crea tu primer rubro y luego tus productos o servicios, con su forma de cobro.' : 'Quien administra la subcuenta todavía no cargó productos.'}`;
    return `<div class="prod-v">${prodPestanas('productos')}${head('Ventas y facturación', 'Productos y servicios', 'Organiza lo que vendes por rubros y define cómo se cobra cada cosa: recurrente, pago único o venta única.',
      canEdit ? `${catalogoReporte().length ? `<button class="btn" type="button" data-prog-pasar title="Crea productos a partir de los programas del Catálogo">${icon('download')}Traer del Catálogo</button>` : ''}<button class="btn" type="button" data-rubro-nuevo>${icon('plus')}Nuevo rubro</button><button class="btn btn-cta" type="button" data-prod-nuevo>${icon('plus')}Nuevo producto</button>` : '')}
      <section class="ag-resumen-grid prod-kpis" aria-label="Resumen del catálogo">
        ${tbTarjeta('', 'wallet', 'Productos activos', `${int(activos.length)}<small> de ${int(productos.length)}</small>`, `${int(productos.length - activos.length)} pausados`)}
        ${tbTarjeta('is-ok', 'sync', 'Planes recurrentes', int(rec.length), rec.length ? `Suman ${esc(porMoneda(rec, mensualDe))} al mes por cliente` : 'Ninguno todavía')}
        ${tbTarjeta('', 'receipt', 'Pagos y ventas únicas', int(activos.length - rec.length), `${int(activos.filter(p => p.cobro === 'unico').length)} de pago único · ${int(activos.filter(p => p.cobro === 'venta').length)} de venta única`)}
        ${tbTarjeta('', 'split', 'Rubros', int(rubros.length), rubros.length ? esc(rubros.slice(0, 3).map(r => r.nombre).join(', ')) + (rubros.length > 3 ? '…' : '') : 'Crea el primero')}
      </section>
      <div class="prod-grid">
        <section class="panel pf-sec prod-rubros">${secH('split', 'Rubros', 'Agrupan tus productos. Toca uno para filtrar.')}
          <ul class="prod-rub-list">
            <li><button type="button" class="prod-rub ${PROD_F.rubro === '' ? 'is-on' : ''}" data-pf="rubro" data-v=""><span>Todos</span><b>${int(productos.length)}</b></button></li>
            ${rubros.map(r => `<li><button type="button" class="prod-rub ${PROD_F.rubro === r.id ? 'is-on' : ''}" data-pf="rubro" data-v="${esc(r.id)}"><span>${esc(r.nombre)}${r.descripcion ? `<small>${esc(r.descripcion)}</small>` : ''}</span><b>${int(productos.filter(p => p.rubroId === r.id).length)}</b></button>${canEdit ? `<button type="button" class="icon-btn prod-rub-ed" data-rubro="${esc(r.id)}" aria-label="Editar rubro ${esc(r.nombre)}">${icon('edit')}</button>` : ''}</li>`).join('')}
            ${productos.some(p => !p.rubroId) ? `<li><button type="button" class="prod-rub ${PROD_F.rubro === '-' ? 'is-on' : ''}" data-pf="rubro" data-v="-"><span>Sin rubro</span><b>${int(productos.filter(p => !p.rubroId).length)}</b></button></li>` : ''}
          </ul>
          ${canEdit ? `<button type="button" class="btn btn-sm" data-rubro-nuevo>${icon('plus')}Agregar rubro</button>` : ''}
        </section>
        <section class="panel pf-sec prod-lista">${secH('wallet', 'Productos', `${int(lista.length)} ${lista.length === 1 ? 'producto' : 'productos'} en la vista`)}
          <div class="prod-tools"><div class="agency-chips" role="group" aria-label="Forma de cobro">${chip('cobro', '', 'Todos')}${chip('cobro', 'recurrente', 'Recurrente', productos.filter(p => p.cobro === 'recurrente').length)}${chip('cobro', 'unico', 'Pago único', productos.filter(p => p.cobro === 'unico').length)}${chip('cobro', 'venta', 'Venta única', productos.filter(p => p.cobro === 'venta').length)}</div>
            <label class="fld grow prod-q"><span class="sr-only">Buscar</span><span class="search-in">${icon('search')}<input type="search" data-prod-q value="${esc(PROD_F.q)}" placeholder="Buscar por nombre o código" autocomplete="off"></span></label></div>
          <div class="tw full"><table class="prod-t"><thead><tr><th>Producto</th><th>Rubro</th><th>Cobro</th><th class="n">Precio</th><th>Estado</th><th class="n"><span class="sr-only">Acciones</span></th></tr></thead>
          <tbody>${filas || `<tr><td colspan="6" class="empty">${vacio}${canEdit && !productos.length ? `<br><button class="btn btn-sm btn-cta" type="button" data-prod-nuevo>${icon('plus')}Crear producto</button>` : ''}</td></tr>`}</tbody></table></div>
          <p class="src">Para comparar, el precio recurrente se lleva a un mes (semanal × 4,33; quincenal × 2; trimestral ÷ 3; semestral ÷ 6; anual ÷ 12). Los pagos y ventas únicas no suman al mes.</p>
        </section>
      </div></div>`;
  },
  mounted(root) {
    if (!CATALOGO) return;
    root.querySelectorAll('[data-pf]').forEach(b => b.onclick = () => { PROD_F[b.dataset.pf] = b.dataset.v; repaint(); });
    const q = root.querySelector('[data-prod-q]');
    if (q) { let t = 0; q.oninput = () => { clearTimeout(t); t = setTimeout(() => { PROD_F.q = q.value; repaint(); const n = $('[data-prod-q]'); if (n) { n.focus(); n.setSelectionRange(n.value.length, n.value.length); } }, 250); }; }
    root.querySelectorAll('[data-rubro-nuevo]').forEach(b => b.onclick = () => rubroEditar(null));
    root.querySelectorAll('[data-rubro]').forEach(b => b.onclick = () => rubroEditar(CATALOGO.rubros.find(r => r.id === b.dataset.rubro)));
    root.querySelectorAll('[data-prod-nuevo]').forEach(b => b.onclick = () => productoEditar(null));
    root.querySelectorAll('[data-prog-pasar]').forEach(b => b.onclick = () => programasAProductos());
    root.querySelectorAll('[data-prod]').forEach(b => b.onclick = () => productoEditar(CATALOGO.productos.find(p => p.id === b.dataset.prod)));
  },
};
/* 2.1.17 · El Catálogo del reporte mensual vive dentro de Productos y servicios: comparten
   pestañas, el menú marca Productos y servicios y /catalogo sigue funcionando. */
if (MODS.catalogo) MODS.catalogo.dentro = 'productos';
const catalogoReporte = () => { try { return (typeof TB !== 'undefined' && TB.estado === 'listo' && TB.api?.catalogoFilas?.()) || []; } catch (e) { return []; } };
function prodPestanas(actual) {
  const hayCat = MODS.catalogo && MODS.catalogo.ok() && !moduloOculto('catalogo');
  if (!hayCat) return '';
  const t = (id, ico, txt, sub) => `<a class="prod-tab ${actual === id ? 'is-on' : ''}" href="${href(MODS[id].path)}" ${actual === id ? 'aria-current="page"' : ''}>${icon(ico)}<span><b>${esc(txt)}</b><small>${esc(sub)}</small></span></a>`;
  return `<nav class="prod-tabs" aria-label="Productos y servicios">${t('productos', 'wallet', 'Productos y servicios', 'Rubros, precios y forma de cobro')}${t('catalogo', 'receipt', 'Catálogo', 'Programas del reporte mensual')}</nav>`;
}
if (V.tc_catalogo) { const r0 = V.tc_catalogo.render; V.tc_catalogo.render = function () { return prodPestanas('catalogo') + r0.call(this); }; }
function programasAProductos() {
  const filas = catalogoReporte();
  const codigos = new Set(CATALOGO.productos.map(p => String(p.codigo || '').toUpperCase()).filter(Boolean));
  const nuevos = filas.filter(f => f.codigo && !codigos.has(f.codigo.toUpperCase()));
  if (!nuevos.length) { toast('Todos los programas del Catálogo ya están en Productos y servicios.'); return; }
  let rubro = CATALOGO.rubros.find(r => norm(r.nombre) === norm('Programas del catálogo'));
  confirmModal('Traer programas del Catálogo', `Se crearán ${nuevos.length} ${nuevos.length === 1 ? 'producto' : 'productos'} con cobro recurrente mensual, en el rubro «Programas del catálogo»: ${esc(nuevos.slice(0, 4).map(f => f.codigo).join(', '))}${nuevos.length > 4 ? '…' : ''}. Luego puedes editar cada uno.`, 'Traer programas', async () => {
    const rubros = rubro ? CATALOGO.rubros : [...CATALOGO.rubros, rubro = {id: nuevoId('r'), nombre: 'Programas del catálogo', descripcion: 'Creados desde el Catálogo del reporte mensual'}];
    const prods = nuevos.map(f => { const m = String(f.duracion).match(/\d+/); return {id: nuevoId('p'), rubroId: rubro.id, nombre: f.nombre.slice(0, 120) || f.codigo, codigo: f.codigo.toUpperCase().replace(/[^A-Z0-9._-]/g, '').slice(0, 30), tipo: 'servicio', cobro: 'recurrente', precio: f.mensual || 0, moneda: ESPACIO?.moneda || 'USD', frecuencia: 'mensual', duracion: m ? Math.min(600, Math.max(1, +m[0])) : null, stock: null, descripcion: f.duracion ? 'Duración: ' + f.duracion : '', activo: true}; });
    await guardarCatalogo(rubros, [...CATALOGO.productos, ...prods]);
    repaint(); toast(`${prods.length} ${prods.length === 1 ? 'programa pasó' : 'programas pasaron'} a Productos y servicios.`);
  });
}
async function guardarCatalogo(rubros, productos) {
  const d = await agoraApi('workspace/productos', {method: 'PUT', body: {rubros, productos}});
  CATALOGO = {rubros: d.rubros || rubros, productos: d.productos || productos, canEdit: true, actualizado: d.actualizado || null};
  TABLERO_DATOS = null;
}
function rubroEditar(r) {
  const usados = r ? CATALOGO.productos.filter(p => p.rubroId === r.id).length : 0;
  const s = openModal(modalHead(r ? 'Editar rubro' : 'Nuevo rubro', 'Un rubro agrupa productos parecidos, por ejemplo "Planes de internet" o "Diplomados".') +
    `<form class="bx-form" novalidate><div class="bx-grid"><fieldset class="bx bx-wide"><legend>${icon('split')}Rubro</legend>
      <label class="fld"><span class="fld-l">Nombre <span class="req" aria-hidden="true">*</span></span><input name="nombre" maxlength="80" required autofocus value="${esc(r?.nombre || '')}" placeholder="Ej: Planes de internet"></label>
      <label class="fld">Descripción<input name="descripcion" maxlength="240" value="${esc(r?.descripcion || '')}" placeholder="Opcional"></label>
    </fieldset></div><p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">${r ? (usados ? `${usados} ${usados === 1 ? 'producto usa' : 'productos usan'} este rubro.` : 'Ningún producto usa este rubro.') : 'Luego eliges el rubro de cada producto.'}</p><div class="acts">${r ? `<button class="btn btn-danger" type="button" data-borrar>Eliminar</button>` : ''}<button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${r ? 'Guardar cambios' : 'Crear rubro'}</button></div></div></form>`);
  const f = s.querySelector('form'), err = s.querySelector('.fld-err');
  f.onsubmit = async e => {
    e.preventDefault(); const nombre = f.nombre.value.trim();
    if (!nombre) { err.textContent = 'Escribe el nombre del rubro.'; f.nombre.focus(); return; }
    if (CATALOGO.rubros.some(x => x.id !== r?.id && norm(x.nombre) === norm(nombre))) { err.textContent = 'Ya existe un rubro con ese nombre.'; return; }
    const fila = {id: r?.id || nuevoId('r'), nombre, descripcion: f.descripcion.value.trim()};
    const rubros = r ? CATALOGO.rubros.map(x => x.id === r.id ? fila : x) : [...CATALOGO.rubros, fila];
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try { await guardarCatalogo(rubros, CATALOGO.productos); closeModal(); repaint(); toast(r ? 'Rubro actualizado.' : `Rubro "${nombre}" creado.`); } catch (x) { busy(b, false); err.textContent = x.message; }
  };
  s.querySelector('[data-borrar]')?.addEventListener('click', () => confirmModal('Eliminar rubro', usados ? `Los ${usados} productos de "${esc(r.nombre)}" quedan sin rubro. Los productos no se borran.` : `Se eliminará "${esc(r.nombre)}".`, 'Eliminar rubro', async () => {
    await guardarCatalogo(CATALOGO.rubros.filter(x => x.id !== r.id), CATALOGO.productos.map(p => p.rubroId === r.id ? {...p, rubroId: ''} : p));
    if (PROD_F.rubro === r.id) PROD_F.rubro = ''; repaint(); toast('Rubro eliminado.');
  }, true));
}
function productoEditar(p) {
  const {rubros} = CATALOGO;
  const v = p || {tipo: cuentaPerfil()?.negocio === 'productos' ? 'producto' : 'servicio', cobro: ({recurrente: 'recurrente', unico: 'unico', venta: 'venta'})[cuentaPerfil()?.cobro] || 'recurrente', frecuencia: cuentaPerfil()?.frecuencia || 'mensual', moneda: ESPACIO?.moneda || 'USD', activo: true, rubroId: PROD_F.rubro && PROD_F.rubro !== '-' ? PROD_F.rubro : ''};
  const cobroOp = (id, t, d) => `<label class="pfl-op"><input type="radio" name="cobro" value="${id}" ${v.cobro === id ? 'checked' : ''}><span><b>${t}</b><small>${d}</small></span></label>`;
  const s = openModal(modalHead(p ? 'Editar producto' : 'Nuevo producto', 'Lo que vendes y cómo lo cobras. Puedes pausarlo sin borrarlo.') +
    `<form class="bx-form" novalidate><div class="bx-grid">
      <fieldset class="bx"><legend>${icon('wallet')}Producto</legend>
        <label class="fld"><span class="fld-l">Nombre <span class="req" aria-hidden="true">*</span></span><input name="nombre" maxlength="120" required autofocus value="${esc(v.nombre || '')}" placeholder="Ej: Fibra 100 Mbps"></label>
        <div class="bx-row">
          <label class="fld">Rubro<select name="rubroId"><option value="">Sin rubro</option>${rubros.map(r => `<option value="${esc(r.id)}" ${v.rubroId === r.id ? 'selected' : ''}>${esc(r.nombre)}</option>`).join('')}</select></label>
          <label class="fld">Código<input name="codigo" maxlength="30" class="mono-in" value="${esc(v.codigo || '')}" placeholder="Opcional"></label>
        </div>
        <div class="bx-row">
          <label class="fld">Es un<select name="tipo"><option value="servicio" ${v.tipo !== 'producto' ? 'selected' : ''}>Servicio</option><option value="producto" ${v.tipo === 'producto' ? 'selected' : ''}>Producto</option></select></label>
          <label class="bx-check prod-activo"><input type="checkbox" name="activo" ${v.activo !== false ? 'checked' : ''}> Activo (se puede vender)</label>
        </div>
        <label class="fld">Descripción<textarea name="descripcion" maxlength="500" rows="2" placeholder="Opcional">${esc(v.descripcion || '')}</textarea></label>
      </fieldset>
      <fieldset class="bx"><legend>${icon('coins')}Sistema de cobro</legend>
        <div class="pfl-ops pfl-1" role="radiogroup" aria-label="Cómo se cobra">${cobroOp('recurrente', 'Recurrente', 'Se cobra cada cierto tiempo: mensualidad, plan, suscripción.')}${cobroOp('unico', 'Pago único', 'Un solo pago por el servicio: inscripción, instalación.')}${cobroOp('venta', 'Venta única', 'Se vende y se cobra en el momento: equipos, artículos.')}</div>
        <div class="bx-row">
          <label class="fld"><span class="fld-l">Precio <span class="req" aria-hidden="true">*</span></span><input name="precio" inputmode="decimal" value="${v.precio != null ? esc(NF.format(Number(v.precio))) : ''}" placeholder="0,00"></label>
          <label class="fld">Moneda<select name="moneda">${['USD', 'VES', 'EUR'].map(m => `<option ${m === (v.moneda || 'USD') ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
        </div>
        <div class="bx-row" data-si="recurrente">
          <label class="fld">Se cobra<select name="frecuencia">${FRECUENCIAS_P.map(([id, t]) => `<option value="${id}" ${(v.frecuencia || 'mensual') === id ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
          <label class="fld">Número de cobros<input name="duracion" inputmode="numeric" maxlength="3" value="${v.duracion || ''}" placeholder="Vacío = sin fin"></label>
        </div>
        <div class="bx-row" data-si="venta"><label class="fld">Existencia<input name="stock" inputmode="numeric" maxlength="8" value="${v.stock ?? ''}" placeholder="Vacío = no llevar control"></label></div>
        <p class="prod-eq" data-eq role="status"></p>
      </fieldset>
    </div><p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">El precio admite decimales con coma: 25,50.</p><div class="acts">${p ? `<button class="btn btn-danger" type="button" data-borrar>Eliminar</button>` : ''}<button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${p ? 'Guardar cambios' : 'Crear producto'}</button></div></div></form>`, {wide: true});
  const f = s.querySelector('form'), err = s.querySelector('.fld-err');
  const cobro = () => f.querySelector('[name=cobro]:checked')?.value || 'recurrente';
  const sync = () => {
    const c = cobro(); f.querySelectorAll('[data-si]').forEach(x => x.hidden = x.dataset.si !== c);
    const precio = agenciaLeerMonto(f.precio.value), eq = s.querySelector('[data-eq]');
    eq.textContent = precio === null ? 'Revisa el precio: usa solo números, con coma para los decimales.' : c === 'recurrente'
      ? `Equivale a ${agenciaMonto(precio * (FREC_MES[f.frecuencia.value] || 1), f.moneda.value)} al mes${f.duracion.value ? ` · total ${agenciaMonto(precio * Number(f.duracion.value || 0), f.moneda.value)} en ${f.duracion.value} cobros` : ''}.`
      : c === 'unico' ? 'Se cobra una sola vez. No suma al ingreso mensual.' : 'Se cobra al venderlo. No suma al ingreso mensual.';
  };
  f.addEventListener('input', sync); f.addEventListener('change', sync); sync();
  f.onsubmit = async e => {
    e.preventDefault(); err.textContent = '';
    const nombre = f.nombre.value.trim(), precio = agenciaLeerMonto(f.precio.value), c = cobro();
    if (!nombre) { err.textContent = 'Escribe el nombre del producto.'; f.nombre.focus(); return; }
    if (precio === null || !f.precio.value.trim()) { err.textContent = 'Escribe un precio válido (puede ser 0).'; f.precio.focus(); return; }
    const dur = f.duracion.value.trim(), stock = f.stock.value.trim();
    if (c === 'recurrente' && dur && !(/^\d{1,3}$/.test(dur) && +dur >= 1)) { err.textContent = 'El número de cobros debe ser un número entero (o déjalo vacío).'; return; }
    if (c === 'venta' && stock && !/^\d{1,8}$/.test(stock)) { err.textContent = 'La existencia debe ser un número entero (o déjala vacía).'; return; }
    const codigo = f.codigo.value.trim().toUpperCase();
    if (codigo && !/^[A-Z0-9._-]{1,30}$/.test(codigo)) { err.textContent = 'El código solo admite letras, números, punto, guion y guion bajo.'; return; }
    if (codigo && CATALOGO.productos.some(x => x.id !== p?.id && (x.codigo || '').toUpperCase() === codigo)) { err.textContent = `El código ${codigo} ya lo usa otro producto.`; return; }
    const fila = {id: p?.id || nuevoId('p'), rubroId: f.rubroId.value, nombre, codigo, tipo: f.tipo.value, cobro: c, precio, moneda: f.moneda.value,
      frecuencia: c === 'recurrente' ? f.frecuencia.value : null, duracion: c === 'recurrente' && dur ? Number(dur) : null, stock: c === 'venta' && stock ? Number(stock) : null,
      descripcion: f.descripcion.value.trim(), activo: f.activo.checked};
    const productos = p ? CATALOGO.productos.map(x => x.id === p.id ? fila : x) : [...CATALOGO.productos, fila];
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try { await guardarCatalogo(CATALOGO.rubros, productos); closeModal(); repaint(); toast(p ? 'Producto actualizado.' : `Producto "${nombre}" creado.`); } catch (x) { busy(b, false); err.textContent = x.message; }
  };
  s.querySelector('[data-borrar]')?.addEventListener('click', () => confirmModal('Eliminar producto', `Se eliminará "${esc(p.nombre)}" del catálogo. Si solo quieres dejar de venderlo, mejor desmarca "Activo".`, 'Eliminar producto', async () => {
    await guardarCatalogo(CATALOGO.rubros, CATALOGO.productos.filter(x => x.id !== p.id)); repaint(); toast('Producto eliminado.');
  }, true));
}

/* ---------- 3. Tablero del Inicio ---------- */
const tbTarjeta = (cls, ic, titulo, valor, detalle, link) => `<${link ? `a href="${href(link)}"` : 'div'} class="ag-resumen ${cls}">
    <span class="ag-resumen-ic" aria-hidden="true">${icon(ic)}</span><span class="ag-resumen-t">${titulo}</span>
    <strong class="ag-resumen-v">${valor}</strong><span class="ag-resumen-d">${detalle}</span></${link ? 'a' : 'div'}>`;
/* Catálogo de tarjetas. "mod" decide si la persona la puede ver (permiso y módulo encendido). */
const TB_TARJETAS = {
  conversaciones: {t: 'Conversaciones', ic: 'chat', mod: 'bandeja', d: 'Todas las conversaciones y cuántas faltan por leer.'},
  sinLeer: {t: 'Sin leer', ic: 'bell', mod: 'bandeja', d: 'Conversaciones que esperan respuesta.'},
  contactos: {t: 'Contactos', ic: 'users', mod: 'contactos', d: 'Fichas guardadas en la subcuenta.'},
  oportunidades: {t: 'Oportunidades abiertas', ic: 'trend', mod: 'oportunidades', d: 'Negocios en curso y el dinero en juego.'},
  ganadas: {t: 'Ventas ganadas', ic: 'check', mod: 'oportunidades', d: 'Oportunidades ganadas y su valor.'},
  ventasMes: {t: 'Ventas del mes', ic: 'coins', mod: 'comercial', d: 'Lo vendido este mes y el avance de la meta.'},
  tareas: {t: 'Tareas pendientes', ic: 'clock', mod: 'tareas', d: 'Seguimientos por hacer del equipo.'},
  convenios: {t: 'Convenios por firmar', ic: 'invoice', mod: 'convenios', d: 'Convenios enviados que esperan firma.'},
  productos: {t: 'Productos activos', ic: 'wallet', mod: 'productos', d: 'Lo que tienes a la venta, por rubro.'},
  recurrentes: {t: 'Planes recurrentes', ic: 'sync', mod: 'productos', d: 'Productos de cobro recurrente y su valor al mes.'},
  ventasUnicas: {t: 'Pagos y ventas únicas', ic: 'receipt', mod: 'productos', d: 'Productos de pago único y de venta única.'},
};
const TB_DEFECTO = {
  servicios: ['conversaciones', 'oportunidades', 'ganadas', 'tareas'],
  productos: ['ventasMes', 'productos', 'ventasUnicas', 'sinLeer'],
  suscripciones: ['recurrentes', 'ganadas', 'sinLeer', 'tareas'],
  mixto: ['ventasMes', 'oportunidades', 'productos', 'tareas'],
};
const tbDefecto = () => TB_DEFECTO[cuentaPerfil()?.negocio] || TB_DEFECTO.servicios;
function tbConfig() {
  const c = personalCfg()?.tablero;
  return {visible: c?.visible !== false, titulo: c?.titulo || '', tarjetas: Array.isArray(c?.tarjetas) && c.tarjetas.length ? c.tarjetas.filter(id => TB_TARJETAS[id]) : tbDefecto()};
}
const tbVisible = id => { const m = TB_TARJETAS[id]?.mod; return !!m && !!MODS[m] && moduleAllowed(m) && !moduloOculto(m); };
function tableroInicioHtml() {
  const c = tbConfig(); if (!c.visible) return '';
  const ids = c.tarjetas.filter(tbVisible);
  if (!ids.length) return esAdmin() ? `<section class="tb-inicio is-empty"><p>No hay tarjetas para mostrar en tu tablero. <a href="${href('/personalizar')}">Elige tus tarjetas</a>.</p></section>` : '';
  return `<section class="tb-inicio" aria-labelledby="tb-inicio-t"><div class="tb-inicio-h"><h2 id="tb-inicio-t">${esc(c.titulo || 'Tu tablero')}<span>${perfilEt('negocio', cuentaPerfil()?.negocio) ? esc(perfilTexto(cuentaPerfil(), true)) : 'Cifras de hoy'}</span></h2>${esAdmin() ? `<a class="btn btn-sm" href="${href('/personalizar')}">${icon('gear')}Personalizar</a>` : ''}</div>
    <div class="ag-resumen-grid tb-grid" data-tb>${ids.map(id => tbTarjeta('', TB_TARJETAS[id].ic, esc(TB_TARJETAS[id].t), '<span class="sk w40"></span>', '<span class="sk w80"></span>', MODS[TB_TARJETAS[id].mod].path).replace('class="ag-resumen ', `data-tb-id="${id}" class="ag-resumen `)).join('')}</div></section>`;
}
async function tbDatos() {
  if (TABLERO_DATOS && Date.now() - TABLERO_T < 60000) return TABLERO_DATOS;
  const intento = p => p.then(v => v, () => null);
  const ids = tbConfig().tarjetas.filter(tbVisible);
  const quiere = (...k) => k.some(x => ids.includes(x));
  const [dash, won, mes, tareas, catalogo, contratos] = await Promise.all([
    quiere('conversaciones', 'sinLeer', 'contactos', 'oportunidades') ? intento(agoraApi('workspace/dashboard')) : null,
    quiere('ganadas') ? intento(agoraApi('workspace/won')) : null,
    quiere('ventasMes') ? intento(agoraApi('workspace/board-month?period=' + mesActualYM())) : null,
    quiere('tareas') ? intento(agoraApi('workspace/tareas')) : null,
    quiere('productos', 'recurrentes', 'ventasUnicas') ? intento(cargarCatalogo()) : null,
    quiere('convenios') ? intento(loadContratos()) : null,
  ]);
  TABLERO_DATOS = {dash, won, mes, tareas, catalogo, contratos}; TABLERO_T = Date.now();
  return TABLERO_DATOS;
}
function tbValor(id, d) {
  const mon = ESPACIO?.moneda || 'USD', sinDato = ['—', 'Sin datos por ahora'];
  switch (id) {
    case 'conversaciones': return d.dash ? [int(d.dash.conversations?.total || 0), `${int(d.dash.conversations?.unread || 0)} sin leer`] : sinDato;
    case 'sinLeer': return d.dash ? [int(d.dash.conversations?.unread || 0), d.dash.conversations?.unread ? 'Responde primero las más viejas' : 'Todo al día'] : sinDato;
    case 'contactos': return d.dash ? [int(d.dash.contacts || 0), 'Fichas en la subcuenta'] : sinDato;
    case 'oportunidades': return d.dash ? [int(d.dash.opportunities?.count || 0), `${agenciaMonto(d.dash.opportunities?.value || 0, mon)} en juego`] : sinDato;
    case 'ganadas': return d.won ? [int(d.won.totals?.count || 0), `${agenciaMonto(d.won.totals?.sum || 0, mon)} en total`] : sinDato;
    case 'ventasMes': { const s = d.mes?.sales, g = d.mes?.goal; return s ? [agenciaMonto(s.total || 0, mon), g?.progress != null ? `${Math.round(g.progress)} % de la meta del mes` : `${int(s.closings || 0)} cierres · sin meta cargada`] : sinDato; }
    case 'tareas': { const n = d.tareas?.resumen?.pendientes ?? (Array.isArray(d.tareas?.items) ? d.tareas.items.filter(t => t.estado !== 'hecha').length : null); return n == null ? sinDato : [int(n), n ? 'Por hacer' : 'Nada pendiente']; }
    case 'convenios': { const c = Array.isArray(d.contratos) ? d.contratos : null; return c ? [int(c.filter(x => x.estado === 'ENVIADO').length), `${int(c.filter(x => x.estado === 'FIRMADO').length)} firmados`] : sinDato; }
    case 'productos': { const c = d.catalogo; if (!c) return sinDato; const a = c.productos.filter(p => p.activo !== false); return [int(a.length), `${int(c.rubros.length)} ${c.rubros.length === 1 ? 'rubro' : 'rubros'}`]; }
    case 'recurrentes': { const c = d.catalogo; if (!c) return sinDato; const r = c.productos.filter(p => p.activo !== false && p.cobro === 'recurrente'); return [int(r.length), r.length ? `${porMoneda(r, mensualDe)} al mes por cliente` : 'Ninguno todavía']; }
    case 'ventasUnicas': { const c = d.catalogo; if (!c) return sinDato; const a = c.productos.filter(p => p.activo !== false); return [int(a.filter(p => p.cobro !== 'recurrente').length), `${int(a.filter(p => p.cobro === 'unico').length)} de pago único · ${int(a.filter(p => p.cobro === 'venta').length)} de venta`]; }
  }
  return sinDato;
}
async function tableroLlenar(root) {
  const box = root?.querySelector('[data-tb]'); if (!box) return;
  const epoch = NAV_EPOCH;
  const d = await tbDatos().catch(() => null); if (!d || epoch !== NAV_EPOCH || !box.isConnected) return;
  box.querySelectorAll('[data-tb-id]').forEach(el => {
    const [v, n] = tbValor(el.dataset.tbId, d);
    el.querySelector('.ag-resumen-v').textContent = v; el.querySelector('.ag-resumen-d').textContent = n;
  });
}
if (V.centro) V.centro.mounted = root => { tableroLlenar(root); };

/* ---------- ARP Comercial a la medida ---------- */
const ARP_SECCIONES = [
  {id: 'dia', t: 'Tu día', re: /^tu d[ií]a/i, cifras: ['Leads de hoy', 'Conversaciones', 'Sin leer', 'Esperando respuesta', 'Contactos', 'Oportunidades abiertas']},
  {id: 'operacion', t: 'Operación del día', re: /^operaci[oó]n del d[ií]a/i, cifras: ['Tiempo de respuesta promedio (SLA)', 'Sin leer ahora', 'Leads del día', 'Primera respuesta más lenta', 'Conversaciones atendidas', 'Sin leer por asesor', 'Fuente de los leads', 'Leads por ejecutivo']},
  {id: 'anuncios', t: 'Inversión en anuncios', re: /^inversi[oó]n en anuncios/i, cifras: ['Invertido en el mes', 'Impresiones', 'Coste por conversión', 'Coste por lead', 'Retorno por cada 100', 'Campañas por gasto']},
  {id: 'embudo', t: 'Embudo por etapa', re: /^embudo por etapa/i, cifras: []},
  {id: 'rentabilidad', t: 'Rentabilidad del mes', re: /^rentabilidad/i, cifras: ['Ganancia después de publicidad y comisiones', 'Ventas ganadas del mes', 'Mis ventas del mes', 'Ganancia real del mes', 'Costo por cierre', 'Ventas por cada 100 invertido', 'Costos de mercadeo y ventas', 'Comisiones del equipo']},
  {id: 'equipo', t: 'Desempeño del equipo de ventas', re: /^desempe[ñn]o del equipo/i, cifras: []},
  {id: 'eficacia', t: 'Eficacia de ventas', re: /^eficacia de ventas/i, cifras: ['Tiempo de cierre promedio', 'Valor cerrado', 'Velocidad de venta', 'Ticket promedio', 'Tasa de cierre', 'Por ejecutivo']},
];
const MESES_RE = /\b(de\s+)?(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)(\s+(de\s+)?\d{4})?/gi;
/** Clave estable de una cifra: sin acentos, sin el mes y sin lo que va entre paréntesis (salvo SLA). */
const arpClave = (sec, txt) => sec + ':' + norm(String(txt || '').replace(/\((?!sla)[^)]*\)/gi, '').replace(MESES_RE, ' en el mes ').replace(/invertido en el mes.*/i, 'invertido en el mes')).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').replace(/-en-el-mes$/, '').slice(0, 70);
function arpPersonalizar(root) {
  if (R.view !== 'ag_dashboard') return;
  if (typeof kpiArp === 'function') kpiArp(root); // 2.1.17: nombres y metas de los indicadores (04k)
  const cfg = personalCfg()?.arp; if (!cfg) return;
  const base = root.querySelector(':scope > .tv3 > div:not(.mod-h), :scope .tv3 > div:not(.mod-h)'); if (!base) return;
  const ocultas = new Set(cfg.ocultas || []), apagadas = new Set(cfg.secciones || []);
  const orden = [...(cfg.orden || []).filter(id => ARP_SECCIONES.some(s => s.id === id)), ...ARP_SECCIONES.map(s => s.id).filter(id => !(cfg.orden || []).includes(id))];
  // Segmentos: cada cabecera de sección con todo lo que sigue hasta la próxima cabecera.
  base.classList.add('arp-flex');
  let actual = 'inicio', n = 0;
  const marcar = el => { el.dataset.arpSec = actual; el.style.order = String((orden.indexOf(actual) + 1) * 100 + (n++ % 100)); el.classList.toggle('arp-off', apagadas.has(actual)); };
  for (const hijo of [...base.children]) {
    const filas = [...hijo.children].filter(c => c.classList.contains('t-sec-row'));
    if (hijo.classList.contains('t-sec-row') || filas.length) {
      if (filas.length) {
        hijo.classList.add('arp-contents');
        for (const c of [...hijo.children]) { if (c.classList.contains('t-sec-row')) { actual = ARP_SECCIONES.find(s => s.re.test(c.querySelector('h2')?.textContent.trim() || ''))?.id || actual; n = 0; } marcar(c); }
        continue;
      }
      actual = ARP_SECCIONES.find(s => s.re.test(hijo.querySelector('h2')?.textContent.trim() || ''))?.id || actual; n = 0;
    }
    marcar(hijo);
  }
  // Cifras sueltas: tarjetas, cifras grandes y paneles.
  root.querySelectorAll('.micro, .hero, .panel').forEach(el => {
    const sec = el.closest('[data-arp-sec]')?.dataset.arpSec; if (!sec) return;
    const h = el.matches('.panel') ? el.querySelector(':scope > .panel-h h2') : el.querySelector('h3, .t-label');
    const txt = h?.dataset.kpiOrig || h?.textContent; // 2.1.17: si un KPI renombró la cifra, se usa el rótulo original
    if (txt) el.classList.toggle('arp-off', ocultas.has(arpClave(sec, txt)));
  });
}

/* ---------- 4. Pantalla Personalizar ----------
   2.1.17 · Lo protagonista son los embudos y los indicadores (KPI) del negocio; el tablero del
   Inicio queda como pestaña secundaria al final. Los editores de Embudos e Indicadores viven en
   04k-embudos-capi.js (embTab, indTab…); aquí solo se arma la pantalla y la barra de guardar,
   que guarda de una vez todo lo pendiente (embudos, indicadores y personalización). */
MODS.personalizar = {g: 'administracion', view: 'personalizar', name: 'Personalizar', icon: 'palette', path: '/personalizar', c: 'var(--mod-seguridad)',
  desc: 'Tus embudos, tus indicadores (KPI), el CRM y los módulos visibles.', ok: () => esAdmin() || esSuperAdmin()};
{ const ayuda = MODS.ayuda; delete MODS.ayuda; MODS.ayuda = ayuda; }
let PZ_TAB = 'embudos', PZ = null, PZ_CRM = null;
const PZ_TABS = [['embudos', 'trend', 'Embudos'], ['indicadores', 'pie', 'Indicadores (KPI)'], ['crm', 'users', 'CRM'], ['modulos', 'sidebar', 'Módulos'], ['inicio', 'home', 'Pantalla de inicio']];
function pzBorrador() {
  const c = personalCfg() || {};
  const t = tbConfig();
  return {tablero: {visible: t.visible, titulo: t.titulo, tarjetas: [...t.tarjetas]}, arp: {orden: [...(c.arp?.orden || [])], ocultas: [...(c.arp?.ocultas || [])], secciones: [...(c.arp?.secciones || [])]}, crm: {...(c.crm || {})}, modulos: {ocultos: [...(c.modulos?.ocultos || [])]}};
}
const pzSucio = () => PZ && JSON.stringify(PZ) !== JSON.stringify(pzBorrador());
/** ¿Hay algo sin guardar en cualquier pestaña? */
const pzTodoSucio = () => pzSucio() || (typeof embSucio === 'function' && embSucio()) || (typeof indSucio === 'function' && indSucio());
V.personalizar = {
  need: async () => {
    if (!ESPACIO) await bootEspacio();
    const listo = agoraReady();
    await Promise.all([
      !PZ_CRM && listo ? agoraApi('workspace/tags').catch(() => ({items: []})).then(t => { PZ_CRM = {tags: [...(t.items || [])]}; }) : null,
      listo && typeof embCargar === 'function' ? embCargar().catch(() => null) : null,
      typeof indCargar === 'function' ? indCargar().catch(() => null) : null,
    ]);
  },
  render() {
    PZ ??= pzBorrador();
    if (!PZ_TABS.some(t => t[0] === PZ_TAB)) PZ_TAB = 'embudos';
    const cuerpo = {embudos: () => embTab(), indicadores: () => indTab(), crm: pzCrm, modulos: pzModulos, inicio: pzTablero}[PZ_TAB]();
    const p = cuentaPerfil();
    return `<div class="pz">${head('Administración', 'Personalizar', 'Ajusta Ágora a tu negocio: las etapas de tus embudos, los indicadores (KPI) que mides y sus metas, el CRM y los módulos. Los cambios valen para toda la subcuenta.',
      `<a class="btn" href="${href('/comercial')}">${icon('trend')}Ver el ARP Comercial</a>`)}
      <div class="pz-perfil"><span class="pz-perfil-ic" aria-hidden="true">${icon('split')}</span><div><small>Tipo de cuenta</small><b>${esc(perfilTexto(p))}</b></div><p>${p ? 'Lo define la agencia al crear la subcuenta. Sirve de punto de partida para tus indicadores y tu Inicio.' : 'La agencia todavía no definió el tipo de cuenta. Mientras tanto se usan los valores de una cuenta de servicios.'}</p></div>
      <nav class="agency-chips pz-tabs" role="tablist" aria-label="Qué personalizar">${PZ_TABS.map(([id, ic, t]) => `<button type="button" role="tab" class="agency-chip-btn ${PZ_TAB === id ? 'is-active' : ''} ${id === 'inicio' ? 'pz-tab-sec' : ''}" aria-selected="${PZ_TAB === id}" data-pz-tab="${id}">${icon(ic)}${t}</button>`).join('')}</nav>
      <div class="pz-cuerpo" data-pz-cuerpo>${cuerpo}</div>${pzPie()}</div>`;
  },
  mounted(root) {
    root.querySelectorAll('[data-pz-tab]').forEach(b => b.onclick = () => { PZ_TAB = b.dataset.pzTab; repaint(); });
    pzPieConectar(root);
    // Pantalla de inicio
    root.querySelector('[name=tb_visible]')?.addEventListener('change', e => { PZ.tablero.visible = e.target.checked; repaint(); });
    root.querySelector('[name=tb_titulo]')?.addEventListener('change', e => { PZ.tablero.titulo = e.target.value.trim().slice(0, 60); repaint(); });
    root.querySelectorAll('[data-tb-on]').forEach(c => c.onchange = () => { const id = c.dataset.tbOn; PZ.tablero.tarjetas = c.checked ? [...PZ.tablero.tarjetas, id].slice(0, 16) : PZ.tablero.tarjetas.filter(x => x !== id); repaint(); });
    root.querySelectorAll('[data-tb-mover]').forEach(b => b.onclick = () => { const [id, d] = b.dataset.tbMover.split('|'); pzMover(PZ.tablero.tarjetas, id, +d); repaint(); });
    root.querySelector('[data-tb-defecto]')?.addEventListener('click', () => { PZ.tablero.tarjetas = [...tbDefecto()]; repaint(); });
    // ARP (vive en la pestaña Indicadores)
    root.querySelectorAll('[data-arp-on]').forEach(c => c.onchange = () => { const id = c.dataset.arpOn; PZ.arp.secciones = c.checked ? PZ.arp.secciones.filter(x => x !== id) : [...PZ.arp.secciones, id]; repaint(); });
    root.querySelectorAll('[data-arp-cifra]').forEach(c => c.onchange = () => { const k = c.dataset.arpCifra; PZ.arp.ocultas = c.checked ? PZ.arp.ocultas.filter(x => x !== k) : [...PZ.arp.ocultas, k]; pzPieRefrescar(root); });
    root.querySelectorAll('[data-arp-mover]').forEach(b => b.onclick = () => { const [id, d] = b.dataset.arpMover.split('|'); if (!PZ.arp.orden.length) PZ.arp.orden = ARP_SECCIONES.map(s => s.id); pzMover(PZ.arp.orden, id, +d); repaint(); });
    root.querySelector('[data-arp-defecto]')?.addEventListener('click', () => { PZ.arp = {orden: [], ocultas: [], secciones: []}; repaint(); });
    // Módulos
    root.querySelectorAll('[data-mod-on]').forEach(c => c.onchange = () => { const id = c.dataset.modOn; PZ.modulos.ocultos = c.checked ? PZ.modulos.ocultos.filter(x => x !== id) : [...PZ.modulos.ocultos, id]; repaint(); });
    // CRM, Embudos e Indicadores
    pzCrmConectar(root);
    if (PZ_TAB === 'embudos' && typeof embConectar === 'function') embConectar(root);
    if (PZ_TAB === 'indicadores' && typeof indConectar === 'function') indConectar(root);
  },
};
function pzMover(lista, id, d) { const i = lista.indexOf(id), j = i + d; if (i < 0 || j < 0 || j >= lista.length) return; [lista[i], lista[j]] = [lista[j], lista[i]]; }
const pzPie = () => { const s = pzTodoSucio(); return `<div class="pz-pie ${s ? 'is-sucio' : ''}" data-pz-pie><p>${s ? '<b>Tienes cambios sin guardar.</b> Se aplican a toda la subcuenta al guardar.' : 'Todo guardado.'}</p><div class="acts">${s ? '<button class="btn" type="button" data-pz-descartar>Descartar</button>' : ''}<button class="btn btn-cta" type="button" data-pz-guardar ${s ? '' : 'disabled'}>Guardar cambios</button></div></div>`; };
/** Repinta solo la barra de guardar (para no perder el foco mientras se escribe). */
function pzPieRefrescar(root) { const p = (root || document).querySelector('[data-pz-pie]'); if (!p) return; p.outerHTML = pzPie(); pzPieConectar(root || document); }
function pzPieConectar(root) {
  root.querySelector('[data-pz-guardar]')?.addEventListener('click', async e => {
    const b = e.currentTarget; busy(b, true, 'Guardando…');
    try {
      if (typeof embSucio === 'function' && embSucio()) await embGuardar();
      if (typeof indSucio === 'function' && indSucio()) await indGuardar();
      if (pzSucio()) { await guardarPersonal(PZ); PZ = pzBorrador(); }
      repaint(); toast('Cambios guardados. Ya los ve todo tu equipo.');
    } catch (x) { busy(b, false); toast(x.message); }
  });
  root.querySelector('[data-pz-descartar]')?.addEventListener('click', () => { PZ = pzBorrador(); if (typeof embDescartar === 'function') embDescartar(); if (typeof indDescartar === 'function') indDescartar(); repaint(); });
}
window.addEventListener?.('beforeunload', e => { if (R?.view === 'personalizar' && pzTodoSucio()) { e.preventDefault(); e.returnValue = ''; } });
const pzFlechas = (attr, id, i, n, nombre) => `<span class="pz-flechas"><button type="button" class="icon-btn" ${attr}="${id}|-1" ${i ? '' : 'disabled'} aria-label="Subir ${esc(nombre)}">${icon('chev')}</button><button type="button" class="icon-btn pz-abajo" ${attr}="${id}|1" ${i < n - 1 ? '' : 'disabled'} aria-label="Bajar ${esc(nombre)}">${icon('chev')}</button></span>`;
function pzTablero() {
  const t = PZ.tablero, elegidas = t.tarjetas.filter(id => TB_TARJETAS[id]), resto = Object.keys(TB_TARJETAS).filter(id => !elegidas.includes(id));
  const fila = (id, on, i) => { const x = TB_TARJETAS[id], vis = tbVisible(id); return `<li class="pz-item ${on ? 'is-on' : ''}"><label class="pz-check"><input type="checkbox" data-tb-on="${id}" ${on ? 'checked' : ''}><span class="pz-ic" aria-hidden="true">${icon(x.ic)}</span><span><b>${esc(x.t)}</b><small>${esc(x.d)}${vis ? '' : ' · No se verá: su módulo está apagado o no tienes permiso.'}</small></span></label>${on ? pzFlechas('data-tb-mover', id, i, elegidas.length, x.t) : ''}</li>`; };
  return `<div class="pz-grid"><section class="panel pf-sec">${secH('home', 'Pantalla de inicio', 'Las tarjetas que ve tu equipo al entrar a la subcuenta, arriba de los módulos. Los indicadores marcados «En el Inicio» se muestran aparte, con su semáforo.', `<button type="button" class="btn btn-sm" data-tb-defecto>${icon('refresh')}Usar las del tipo de cuenta</button>`)}
      <div class="pz-row"><label class="bx-check"><input type="checkbox" name="tb_visible" ${t.visible ? 'checked' : ''}> Mostrar el tablero en el Inicio</label><label class="fld pz-titulo">Título del tablero<input name="tb_titulo" maxlength="60" value="${esc(t.titulo)}" placeholder="Tu tablero"></label></div>
      <p class="pz-l">En el tablero · ${elegidas.length} de 16 · usa las flechas para ordenar</p>
      <ol class="pz-list">${elegidas.map((id, i) => fila(id, true, i)).join('') || '<li class="pz-vacio">No elegiste tarjetas. Marca alguna de abajo.</li>'}</ol>
      <p class="pz-l">Disponibles</p>
      <ul class="pz-list">${resto.map(id => fila(id, false, 0)).join('')}</ul>
    </section>
    <section class="panel pf-sec pz-prev">${secH('home', 'Vista previa', 'Así se verá en el Inicio.')}
      ${t.visible ? `<div class="ag-resumen-grid tb-grid">${elegidas.filter(tbVisible).map(id => tbTarjeta('', TB_TARJETAS[id].ic, esc(TB_TARJETAS[id].t), '<span class="sk w40"></span>', esc(TB_TARJETAS[id].d))).join('') || '<p class="pf-text">Sin tarjetas visibles.</p>'}</div>` : '<p class="pf-text">El tablero está oculto: el Inicio muestra solo los módulos.</p>'}
    </section></div>`;
}
/** Secciones y cifras del ARP Comercial: se muestra dentro de la pestaña Indicadores (KPI). */
function pzArp() {
  const a = PZ.arp, orden = a.orden.length ? [...a.orden.filter(id => ARP_SECCIONES.some(s => s.id === id)), ...ARP_SECCIONES.map(s => s.id).filter(id => !a.orden.includes(id))] : ARP_SECCIONES.map(s => s.id);
  const sec = (id, i) => { const s = ARP_SECCIONES.find(x => x.id === id), on = !a.secciones.includes(id); return `<li class="pz-sec ${on ? 'is-on' : ''}">
      <div class="pz-item is-on"><label class="pz-check"><input type="checkbox" data-arp-on="${id}" ${on ? 'checked' : ''}><span class="pz-ic" aria-hidden="true">${icon(arpIcono(s.t))}</span><span><b>${esc(s.t)}</b><small>${on ? (s.cifras.length ? `${s.cifras.filter(c => !a.ocultas.includes(arpClave(id, c))).length} de ${s.cifras.length} cifras visibles` : 'Sección completa') : 'Oculta'}</small></span></label>${pzFlechas('data-arp-mover', id, i, orden.length, s.t)}</div>
      ${on && s.cifras.length ? `<div class="pz-cifras">${s.cifras.map(c => `<label class="pz-cifra"><input type="checkbox" data-arp-cifra="${esc(arpClave(id, c))}" ${a.ocultas.includes(arpClave(id, c)) ? '' : 'checked'}>${esc(c)}</label>`).join('')}</div>` : ''}</li>`; };
  return `<section class="panel pf-sec">${secH('trend', 'Tablero del ARP Comercial', 'Qué secciones y qué cifras muestra el ARP, y en qué orden. Lo oculto no se borra: vuelve a aparecer al marcarlo. Los nombres y metas de tus indicadores también se ven allí.', `<button type="button" class="btn btn-sm" data-arp-defecto>${icon('refresh')}Mostrar todo</button>`)}
    <ol class="pz-list pz-arp">${orden.map(sec).join('')}</ol></section>`;
}
function pzModulos() {
  const grupos = Object.keys(GRUPOS).map(g => [g, Object.keys(MODS).filter(k => MODS[k].g === g && !MODS[k].fijo)]).filter(([, ids]) => ids.length);
  return `<section class="panel pf-sec">${secH('sidebar', 'Módulos de la subcuenta', 'Apaga lo que tu negocio no usa: desaparece del menú, del Inicio y de la búsqueda. Personalizar, Usuarios, Ajustes y Ayuda siempre quedan encendidos.')}
    <div class="pz-mods">${grupos.map(([g, ids]) => `<div class="pz-mod-g"><p class="pz-l"><span class="mdm-ic" style="--c:${GRUPOS[g].c}">${icon(GRUPOS[g].icon)}</span>${esc(GRUPOS[g].name)}</p>
      ${ids.map(id => { const fijo = MODULOS_FIJOS.includes(id), on = fijo || !PZ.modulos.ocultos.includes(id); return `<label class="pz-mod ${on ? 'is-on' : ''}"><span><b>${esc(MODS[id].name)}</b><small>${esc(MODS[id].desc)}</small></span><span class="pz-sw"><input type="checkbox" role="switch" data-mod-on="${id}" ${on ? 'checked' : ''} ${fijo ? 'disabled' : ''}><i aria-hidden="true"></i></span></label>`; }).join('')}</div>`).join('')}</div></section>`;
}
function pzCrm() {
  if (!agoraReady()) return `<section class="panel"><p class="empty"><b>El CRM no está disponible</b>Esta subcuenta todavía no tiene la operación comercial activa.</p></section>`;
  const c = PZ_CRM || {tags: []};
  return `<div class="pz-grid"><section class="panel pf-sec">${secH('pin', 'Etiquetas de contactos', 'Las etiquetas que tu equipo usa para clasificar contactos: interesado, referido, frío…')}
      <ul class="pz-tags">${c.tags.map((t, i) => `<li class="pz-tag">${esc(t)}<button type="button" data-tag-x="${i}" aria-label="Quitar ${esc(t)}">×</button></li>`).join('') || '<li class="pz-vacio">Sin etiquetas.</li>'}</ul>
      <form class="pz-tag-add" data-tag-form><label class="fld grow"><span class="sr-only">Nueva etiqueta</span><input name="tag" maxlength="40" placeholder="Nueva etiqueta" autocomplete="off"></label><button class="btn btn-sm" type="submit">${icon('plus')}Agregar</button></form>
      <div class="pz-acts"><button type="button" class="btn btn-sm btn-cta" data-tags-guardar>Guardar etiquetas</button></div>
    </section>
    <section class="panel pf-sec">${secH('edit', 'Cómo llamas las cosas', 'El nombre que usa tu negocio para una oportunidad y para un contacto. Vacío: se usa el nombre de Ágora.')}
      <div class="bx-row"><label class="fld">Una oportunidad se llama<input data-crm-nombre="oportunidad" maxlength="30" value="${esc(PZ.crm.oportunidad || '')}" placeholder="Oportunidad"></label>
      <label class="fld">Un contacto se llama<input data-crm-nombre="contacto" maxlength="30" value="${esc(PZ.crm.contacto || '')}" placeholder="Contacto"></label></div>
      <p class="bx-help">Las etapas de cada embudo se editan en la pestaña <button type="button" class="pz-link" data-pz-ir="embudos">Embudos</button>.</p>
    </section></div>`;
}
function pzCrmConectar(root) {
  root.querySelectorAll('[data-pz-ir]').forEach(b => b.onclick = () => { PZ_TAB = b.dataset.pzIr; repaint(); });
  root.querySelectorAll('[data-crm-nombre]').forEach(i => i.oninput = () => { PZ.crm[i.dataset.crmNombre] = i.value.trim().slice(0, 30); if (!PZ.crm[i.dataset.crmNombre]) delete PZ.crm[i.dataset.crmNombre]; pzPieRefrescar(root); });
  if (!PZ_CRM) return;
  root.querySelectorAll('[data-tag-x]').forEach(b => b.onclick = () => { PZ_CRM.tags.splice(+b.dataset.tagX, 1); repaint(); });
  root.querySelector('[data-tag-form]')?.addEventListener('submit', e => { e.preventDefault(); const v = e.target.tag.value.trim().toLowerCase(); if (!v) return; if (!PZ_CRM.tags.includes(v)) PZ_CRM.tags.push(v); repaint(); $('[data-tag-form] input')?.focus(); });
  root.querySelector('[data-tags-guardar]')?.addEventListener('click', async e => {
    const b = e.currentTarget; busy(b, true, 'Guardando…');
    try { const d = await agoraApi('workspace/etiquetas', {method: 'PUT', body: {items: PZ_CRM.tags}}); PZ_CRM.tags = d.items || PZ_CRM.tags; busy(b, false); toast('Etiquetas guardadas.'); } catch (x) { busy(b, false); toast(x.message); }
  });
}
/* =====================================================================================
   2.1.17 · Excel y tablas dinámicas (equipo datos)
   -------------------------------------------------------------------------------------
   1. AgoraExcel: escribe y lee .xlsx reales sin librerías (ZIP + SpreadsheetML) y lee .csv.
        AgoraExcel.descargar('contactos', [{nombre, columnas:[{clave,titulo,tipo?}], filas:[{clave:valor}]}])
        AgoraExcel.escribir(hojas) -> Blob · AgoraExcel.leer(file) -> Promise<[{nombre, filas:[[celda…]…]}]>
      Al escribir, el texto que empieza con = + - @ lleva un apóstrofo delante (no se ejecuta como fórmula).
   2. AgoraTabla.mejorar(tabla, {nombre, modulo}): búsqueda, orden por columna, columnas visibles,
      conteo, paginación ligera, encabezado fijo y «Descargar Excel» con lo que se ve.
      Se aplica sola a las tablas de datos de los módulos de la subcuenta (ver TABLA_MODS).
      Para excluir una tabla: <table data-sin-mejorar> (o un contenedor con ese atributo).
   ===================================================================================== */
const AgoraExcel = (() => {
  const enc = s => new TextEncoder().encode(s);
  /* ---------- ZIP ---------- */
  const CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
  const crc32 = b => { let c = 0xFFFFFFFF; for (let i = 0; i < b.length; i++) c = CRC[(c ^ b[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  function zip(archivos) {
    const d = new Date(), time = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1), date = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
    const partes = [], central = []; let off = 0;
    for (const [nombre, texto] of archivos) {
      const n = enc(nombre), data = typeof texto === 'string' ? enc(texto) : texto, crc = crc32(data);
      const h = new DataView(new ArrayBuffer(30));
      h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint16(8, 0, true);
      h.setUint16(10, time, true); h.setUint16(12, date, true); h.setUint32(14, crc, true); h.setUint32(18, data.length, true); h.setUint32(22, data.length, true);
      h.setUint16(26, n.length, true); h.setUint16(28, 0, true);
      partes.push(new Uint8Array(h.buffer), n, data);
      const c = new DataView(new ArrayBuffer(46));
      c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true); c.setUint16(10, 0, true);
      c.setUint16(12, time, true); c.setUint16(14, date, true); c.setUint32(16, crc, true); c.setUint32(20, data.length, true); c.setUint32(24, data.length, true);
      c.setUint16(28, n.length, true); c.setUint32(42, off, true);
      central.push(new Uint8Array(c.buffer), n);
      off += 30 + n.length + data.length;
    }
    const tam = central.reduce((s, x) => s + x.length, 0), e = new DataView(new ArrayBuffer(22));
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, archivos.length, true); e.setUint16(10, archivos.length, true); e.setUint32(12, tam, true); e.setUint32(16, off, true);
    const todo = [...partes, ...central, new Uint8Array(e.buffer)], out = new Uint8Array(todo.reduce((s, x) => s + x.length, 0));
    let p = 0; for (const x of todo) { out.set(x, p); p += x.length; }
    return out;
  }
  async function unzip(u8) {
    const v = new DataView(u8.buffer, u8.byteOffset, u8.byteLength);
    let e = -1; for (let i = u8.length - 22; i >= Math.max(0, u8.length - 65557); i--) if (v.getUint32(i, true) === 0x06054b50) { e = i; break; }
    if (e < 0) throw new Error('El archivo no es un Excel válido (.xlsx).');
    const n = v.getUint16(e + 10, true); let p = v.getUint32(e + 16, true); const out = {};
    for (let k = 0; k < n; k++) {
      if (v.getUint32(p, true) !== 0x02014b50) throw new Error('El archivo Excel está dañado.');
      const met = v.getUint16(p + 10, true), csize = v.getUint32(p + 20, true), nl = v.getUint16(p + 28, true), xl = v.getUint16(p + 30, true), cl = v.getUint16(p + 32, true), loc = v.getUint32(p + 42, true);
      const nombre = new TextDecoder().decode(u8.subarray(p + 46, p + 46 + nl));
      const ini = loc + 30 + v.getUint16(loc + 26, true) + v.getUint16(loc + 28, true), datos = u8.subarray(ini, ini + csize);
      out[nombre] = {met, datos};
      p += 46 + nl + xl + cl;
    }
    return {
      tiene: f => !!out[f],
      async texto(f) {
        const x = out[f]; if (!x) return null;
        if (x.met === 0) return new TextDecoder().decode(x.datos);
        if (x.met !== 8) throw new Error('El Excel usa una compresión que no se puede leer. Guárdalo de nuevo como .xlsx o .csv.');
        if (typeof DecompressionStream === 'undefined') throw new Error('Tu navegador no puede abrir este Excel. Guárdalo como .csv e inténtalo de nuevo.');
        const s = new Blob([x.datos]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
        return new TextDecoder().decode(await new Response(s).arrayBuffer());
      },
    };
  }
  /* ---------- XML ---------- */
  const xesc = s => String(s).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F￾￿]/g, '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const xdec = s => String(s).replace(/&(#x[0-9a-f]+|#\d+|lt|gt|amp|quot|apos);/gi, (m, k) => k[0] === '#' ? String.fromCodePoint(k[1] === 'x' || k[1] === 'X' ? parseInt(k.slice(2), 16) : +k.slice(1)) : ({lt: '<', gt: '>', amp: '&', quot: '"', apos: "'"})[k.toLowerCase()]);
  const attrs = s => { const o = {}; String(s).replace(/([\w:]+)\s*=\s*"([^"]*)"/g, (m, k, v) => { o[k] = xdec(v); o[k.replace(/^\w+:/, '')] ??= xdec(v); }); return o; };
  const colLetra = i => { let s = ''; i++; while (i > 0) { const m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); } return s; };
  const colIndice = s => { let n = 0; for (const ch of s.toUpperCase()) n = n * 26 + ch.charCodeAt(0) - 64; return n - 1; };
  /** El texto que Excel podría tomar como fórmula lleva un apóstrofo delante (como el CSV de Ágora). */
  const neutralizar = s => /^[\s\u0000-\u001f]*[=+\-@]/.test(s) ? "'" + s : s;
  const nombreHoja = (s, usados) => { let n = String(s || 'Hoja').replace(/[\[\]:*?/\\]/g, ' ').trim().slice(0, 31) || 'Hoja'; let b = n, k = 2; while (usados.has(n.toLowerCase())) n = b.slice(0, 28) + ' ' + k++; usados.add(n.toLowerCase()); return n; };
  function hojaXml(h) {
    const cols = h.columnas || [], filas = h.filas || [];
    const ancho = cols.map(c => Math.min(60, Math.max(9, String(c.titulo ?? c.clave).length + 3)));
    let xml = '';
    const fila = (r, celdas) => `<row r="${r}">${celdas.join('')}</row>`;
    const cab = cols.map((c, i) => `<c r="${colLetra(i)}1" t="inlineStr" s="1"><is><t xml:space="preserve">${xesc(c.titulo ?? c.clave)}</t></is></c>`);
    xml += fila(1, cab);
    filas.forEach((f, j) => {
      const r = j + 2, celdas = [];
      cols.forEach((c, i) => {
        let v = Array.isArray(f) ? f[i] : f?.[c.clave];
        if (v == null || v === '') return;
        const ref = colLetra(i) + r;
        if (typeof v === 'boolean') v = v ? 'Sí' : 'No';
        if (typeof v === 'number' && Number.isFinite(v)) {
          celdas.push(`<c r="${ref}"${Number.isInteger(v) ? '' : ' s="2"'}><v>${v}</v></c>`);
          ancho[i] = Math.max(ancho[i], Math.min(60, String(v).length + 3));
          return;
        }
        const s = neutralizar(String(v));
        ancho[i] = Math.max(ancho[i], Math.min(60, s.length + 2));
        celdas.push(`<c r="${ref}" t="inlineStr"><is><t xml:space="preserve">${xesc(s)}</t></is></c>`);
      });
      xml += fila(r, celdas);
    });
    const ult = colLetra(Math.max(0, cols.length - 1)) + (filas.length + 1);
    return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><dimension ref="A1:${ult}"/><sheetViews><sheetView workbookViewId="0"${h.congelar === false ? '' : '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView>'}${h.congelar === false ? '/>' : ''}</sheetViews><sheetFormatPr defaultRowHeight="15"/>${cols.length ? `<cols>${ancho.map((w, i) => `<col min="${i + 1}" max="${i + 1}" width="${w}" customWidth="1"/>`).join('')}</cols>` : ''}<sheetData>${xml}</sheetData>${cols.length && h.filtro !== false ? `<autoFilter ref="A1:${ult}"/>` : ''}</worksheet>`;
  }
  function escribirBytes(hojas) {
    hojas = (Array.isArray(hojas) ? hojas : [hojas]).filter(Boolean);
    if (!hojas.length) hojas = [{nombre: 'Hoja', columnas: [], filas: []}];
    const usados = new Set(), nombres = hojas.map(h => nombreHoja(h.nombre, usados));
    const archivos = [
      ['[Content_Types].xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>${hojas.map((h, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>`],
      ['_rels/.rels', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>`],
      ['docProps/core.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Ágora</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">${new Date().toISOString().slice(0, 19)}Z</dcterms:created></cp:coreProperties>`],
      ['docProps/app.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Ágora</Application></Properties>`],
      ['xl/workbook.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>${nombres.map((n, i) => `<sheet name="${xesc(n)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')}</sheets><definedNames>${hojas.map((h, i) => (h.columnas || []).length && h.filtro !== false ? `<definedName name="_xlnm._FilterDatabase" localSheetId="${i}" hidden="1">'${xesc(nombres[i].replace(/'/g, "''"))}'!$A$1:$${colLetra(h.columnas.length - 1)}$${(h.filas || []).length + 1}</definedName>` : '').join('')}</definedNames></workbook>`],
      ['xl/_rels/workbook.xml.rels', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${hojas.map((h, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')}<Relationship Id="rId${hojas.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>`],
      ['xl/styles.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FF5A0A22"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF6E7EC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFAD103C"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><\/styleSheet>`],
      ...hojas.map((h, i) => [`xl/worksheets/sheet${i + 1}.xml`, hojaXml(h)]),
    ];
    return zip(archivos);
  }
  const TIPO = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  const escribir = hojas => new Blob([escribirBytes(hojas)], {type: TIPO});
  function descargar(nombre, hojas) {
    const n = String(nombre || 'datos').replace(/[\\/:*?"<>|]+/g, '-').replace(/\.xlsx$/i, '') + '.xlsx';
    const a = document.createElement('a'); a.href = URL.createObjectURL(escribir(hojas)); a.download = n;
    document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    return n;
  }
  /* ---------- Lectura ---------- */
  const FECHAS = new Set([14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57]);
  function serialAFecha(n, es1904) {
    const ms = Math.round(((es1904 ? n + 1462 : n) - 25569) * 86400000), d = new Date(ms), p = x => String(x).padStart(2, '0');
    const f = `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())}`, h = `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}`;
    if (n < 1) return h;
    return n % 1 ? `${f} ${h}` : f;
  }
  async function leerXlsx(u8) {
    const z = await unzip(u8);
    const wb = await z.texto('xl/workbook.xml'); if (!wb) throw new Error('El archivo no tiene hojas de Excel.');
    const es1904 = /<workbookPr[^>]*date1904="(1|true)"/.test(wb);
    const rels = {}; ((await z.texto('xl/_rels/workbook.xml.rels')) || '').replace(/<Relationship\b([^>]*)\/?>/g, (m, a) => { const x = attrs(a); rels[x.Id] = x.Target; });
    const ss = []; ((await z.texto('xl/sharedStrings.xml')) || '').replace(/<si>([\s\S]*?)<\/si>/g, (m, si) => { ss.push(textoRico(si)); });
    const fechaXf = [];
    const st = (await z.texto('xl/styles.xml')) || '';
    const fmts = {}; st.replace(/<numFmt\b([^>]*)\/?>/g, (m, a) => { const x = attrs(a); fmts[x.numFmtId] = x.formatCode || ''; });
    const xfs = (st.match(/<cellXfs[^>]*>([\s\S]*?)<\/cellXfs>/) || [])[1] || '';
    xfs.replace(/<xf\b([^>]*)\/?>/g, (m, a) => { const id = +(attrs(a).numFmtId || 0), code = fmts[id]; fechaXf.push(FECHAS.has(id) || (!!code && /[dmyhs]/i.test(code.replace(/"[^"]*"|\[[^\]]*\]|\\./g, '')) && !/^general$/i.test(code))); });
    const hojas = [];
    for (const [, a] of wb.matchAll(/<sheet\b([^>]*)\/?>/g)) {
      const x = attrs(a), rid = Object.entries(x).find(([k]) => /(^|:)id$/.test(k) && k !== 'sheetId')?.[1];
      let t = rels[rid] || ''; t = t.startsWith('/') ? t.slice(1) : 'xl/' + t.replace(/^\.\//, '');
      const xml = await z.texto(t); if (xml == null) continue;
      hojas.push({nombre: x.name || 'Hoja', filas: filasDe(xml, ss, fechaXf, es1904)});
    }
    return hojas;
  }
  const textoRico = s => { s = s.replace(/<rPh\b[\s\S]*?<\/rPh>/g, ''); let o = ''; s.replace(/<t\b[^>]*>([\s\S]*?)<\/t>|<t\b[^>]*\/>/g, (m, t) => { o += xdec(t || ''); }); return o; };
  function filasDe(xml, ss, fechaXf, es1904) {
    const filas = []; let rAuto = 0;
    for (const [, ra, cuerpo] of xml.matchAll(/<row\b([^>]*?)(?:\/>|>([\s\S]*?)<\/row>)/g)) {
      const rn = attrs(ra).r ? +attrs(ra).r - 1 : rAuto; rAuto = rn + 1;
      const fila = []; let cAuto = 0;
      for (const [, ca, cc] of (cuerpo || '').matchAll(/<c\b([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g)) {
        const x = attrs(ca), ci = x.r ? colIndice(x.r.replace(/\d+/g, '')) : cAuto; cAuto = ci + 1;
        const v = ((cc || '').match(/<v>([\s\S]*?)<\/v>/) || [])[1];
        let val = null;
        if (x.t === 's') val = ss[+v] ?? '';
        else if (x.t === 'inlineStr') val = textoRico(((cc || '').match(/<is>([\s\S]*?)<\/is>/) || [])[1] || '');
        else if (x.t === 'str' || x.t === 'e') val = v == null ? '' : xdec(v);
        else if (x.t === 'b') val = v === '1';
        else if (v != null) { const n = Number(v); val = Number.isFinite(n) ? (fechaXf[+(x.s || 0)] ? serialAFecha(n, es1904) : n) : xdec(v); }
        if (val !== null) fila[ci] = val;
      }
      for (let i = 0; i < fila.length; i++) if (fila[i] === undefined) fila[i] = '';
      filas[rn] = fila;
    }
    for (let i = 0; i < filas.length; i++) if (!filas[i]) filas[i] = [];
    return filas;
  }
  function leerCsv(texto) {
    texto = texto.replace(/^﻿/, '');
    const primera = texto.split(/\r?\n/, 1)[0] || '', fuera = primera.replace(/"[^"]*"/g, '');
    const sep = [';', ',', '\t'].map(s => [s, fuera.split(s).length]).sort((a, b) => b[1] - a[1])[0][0];
    const filas = []; let fila = [], celda = '', q = false;
    for (let i = 0; i < texto.length; i++) {
      const ch = texto[i];
      if (q) { if (ch === '"') { if (texto[i + 1] === '"') { celda += '"'; i++; } else q = false; } else celda += ch; continue; }
      if (ch === '"' && celda === '') q = true;
      else if (ch === sep) { fila.push(celda); celda = ''; }
      else if (ch === '\n' || ch === '\r') { if (ch === '\r' && texto[i + 1] === '\n') i++; fila.push(celda); filas.push(fila); fila = []; celda = ''; }
      else celda += ch;
    }
    if (celda !== '' || fila.length) { fila.push(celda); filas.push(fila); }
    return [{nombre: 'CSV', filas}];
  }
  async function leerBytes(u8, nombre = '') {
    if (u8 instanceof ArrayBuffer) u8 = new Uint8Array(u8);
    if (u8[0] === 0x50 && u8[1] === 0x4B) return leerXlsx(u8);
    if (u8[0] === 0xD0 && u8[1] === 0xCF) throw new Error('Ese es un Excel antiguo (.xls). Ábrelo y guárdalo como .xlsx o .csv.');
    if (/\.xlsx$/i.test(nombre)) throw new Error('El archivo no es un Excel válido (.xlsx).');
    let t = new TextDecoder('utf-8').decode(u8);
    if (t.includes('�')) { try { t = new TextDecoder('windows-1252').decode(u8); } catch {} }
    return leerCsv(t);
  }
  async function leer(file) {
    if (!file) throw new Error('Elige un archivo.');
    if (file.size > 25 * 1024 * 1024) throw new Error('El archivo pesa más de 25 MB. Divídelo en partes.');
    return leerBytes(new Uint8Array(await file.arrayBuffer()), file.name || '');
  }
  return {descargar, escribir, escribirBytes, leer, leerBytes, leerCsv, neutralizar, colLetra, TIPO};
})();
window.AgoraExcel = AgoraExcel;

/* =====================================================================================
   Tablas dinámicas
   ===================================================================================== */
/* Módulos de la subcuenta donde las tablas se mejoran solas. */
const TABLA_MODS = ['inicio', 'ventas', 'facturacion', 'costos', 'catalogo', 'equipo', 'publicidad', 'tendencias', 'calidad', 'productos', 'tareas', 'convenios', 'conConvenio', 'registro', 'auditoria', 'usuarios', 'contactos', 'oportunidades', 'migracion'];
const TABLA_POR_PAGINA = 50;
const TABLA_ESTADO = {};   // búsqueda, orden y página por tabla: sobreviven a un repintado
const tablaGuardadas = k => { try { return JSON.parse(localStorage.getItem('agora.tabla.' + k) || '[]'); } catch { return []; } };
const tablaGuardar = (k, v) => { try { localStorage.setItem('agora.tabla.' + k, JSON.stringify(v)); } catch {} };
/** Texto legible de una celda: las líneas secundarias (<small>) quedan separadas con « · ». */
function tablaTexto(td) {
  if (!td) return '';
  if (td.dataset?.valor != null) return td.dataset.valor;
  const c = td.cloneNode(true);
  c.querySelectorAll('.sr-only, button, svg, [aria-hidden="true"]').forEach(x => x.remove());
  c.querySelectorAll('small, br').forEach(x => x.replaceWith(' · ' + (x.textContent || '')));
  return (c.textContent || '').replace(/\s+/g, ' ').replace(/^( · )+|( · )+$/g, '').replace(/( · )+/g, ' · ').trim();
}
/** Número dentro de un texto con formato venezolano o internacional ("1.234,56", "$ 30", "12 %"). */
function tablaNumero(s) {
  s = String(s ?? '').trim(); if (!s || /[a-zñáéíóú]{2,}/i.test(s.replace(/\b(usd|ves|bs|eur)\b/gi, ''))) return null;
  if (/^\+|^0\d/.test(s)) return null; // teléfonos y códigos con cero delante se quedan como texto
  let t = s.replace(/[^\d.,\-]/g, ''); if (!/\d/.test(t)) return null;
  const c = t.lastIndexOf(','), p = t.lastIndexOf('.');
  if (c >= 0 && p >= 0) t = c > p ? t.replace(/\./g, '').replace(',', '.') : t.replace(/,/g, '');
  else if (c >= 0) t = (t.match(/,/g).length > 1) ? t.replace(/,/g, '') : t.replace(',', '.');
  else if (/^-?\d{1,3}(\.\d{3})+$/.test(t)) t = t.replace(/\./g, '');
  const n = Number(t); return Number.isFinite(n) ? n : null;
}
const MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
function tablaFecha(s) {
  s = String(s ?? '').trim().toLowerCase(); let m;
  if ((m = s.match(/^(\d{4})-(\d{2})-(\d{2})/))) return Date.UTC(+m[1], m[2] - 1, +m[3]);
  if ((m = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})/))) return Date.UTC(m[3].length === 2 ? 2000 + +m[3] : +m[3], m[2] - 1, +m[1]);
  if ((m = s.match(/^(\d{1,2})\s+(?:de\s+)?([a-zé]{3})[a-zé]*\.?(?:\s+(?:de\s+)?(\d{4}))?/)) && MESES_C.includes(m[2])) return Date.UTC(m[3] ? +m[3] : new Date().getFullYear(), MESES_C.indexOf(m[2]), +m[1]);
  return null;
}
const tablaCabeceras = t => [...(t.tHead?.rows[t.tHead.rows.length - 1]?.cells || [])];
/* 2.1.19: el título incluye el texto del botón de orden (antes quedaba vacío y el menú «Columnas» salía sin columnas). */
const tablaTitulo = th => { if (!th) return ''; const c = th.cloneNode(true); c.querySelectorAll('.te-thm, .sr-only, svg, [aria-hidden="true"]').forEach(x => x.remove()); return (c.textContent || '').replace(/[↑↓]/g, '').replace(/\s+/g, ' ').trim(); };
const tablaFilas = t => [...(t.tBodies[0]?.rows || [])].filter(r => !r.querySelector('td.empty') && !(r.cells.length === 1 && r.cells[0].colSpan > 1));

const AgoraTabla = {
  /** ¿Merece la barra? Tablas con encabezado y al menos 3 filas de datos, fuera de formularios. */
  candidata(t) {
    if (!t || t.dataset.xt || t.closest('[data-sin-mejorar], form, .modal, .scrim, .xt-skip') || t.classList.contains('heat') || t.classList.contains('matriz')) return false;
    if (!t.tHead || t.tHead.rows.length !== 1 || tablaCabeceras(t).length < 2) return false;
    return tablaFilas(t).length >= 3 || t.dataset.mejorar != null || !!window.TablaEd?.siempre?.(t, typeof R !== 'undefined' ? R.mod : '');
  },
  mejorar(t, {nombre = '', modulo = ''} = {}) {
    if (!t || t.dataset.xt) return null;
    t.dataset.xt = '1';
    /* 2.1.19: columnas editables y filas a mano (04m). Transforma la tabla antes de leerla. */
    let ed = null; try { ed = window.TablaEd?.preparar?.(t, {nombre, modulo}) || null; } catch (e) { console.warn('[tabla editable]', e); }
    const ths = tablaCabeceras(t), titulos = ths.map(tablaTitulo);
    const tw = t.closest('.tw') || t.parentElement;
    const externo = !!t.querySelector('thead [data-sort]') || !!tw?.nextElementSibling?.classList?.contains('pager');
    const clave = (modulo || 'x') + '.' + (nombre || titulos.slice(0, 3).join('|')).slice(0, 80);
    const id = 'xt' + Math.random().toString(36).slice(2, 8); t.dataset.xtId = id; t.classList.add('xt-t');
    const st = TABLA_ESTADO[clave] ||= {q: '', col: -1, dir: 1, p: 0};
    let ocultas = ed ? ed.ocultas() : tablaGuardadas(clave).filter(x => titulos.includes(x));
    const acciones = i => !titulos[i] || /^acciones?$/i.test(titulos[i]);
    const estilo = document.createElement('style');
    const aplicarCols = () => { estilo.textContent = titulos.map((x, i) => ocultas.includes(x) ? `table[data-xt-id="${id}"] tr > :nth-child(${i + 1}){display:none}` : '').join(''); };
    aplicarCols();
    const bar = document.createElement('div'); bar.className = 'xt-bar' + (externo ? ' is-externo' : ''); bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', 'Herramientas de la tabla' + (nombre ? ' ' + nombre : ''));
    bar.innerHTML = `${externo ? '' : `<label class="xt-q"><span class="sr-only">Buscar en la tabla</span>${icon('search')}<input type="search" placeholder="Buscar en la tabla" autocomplete="off" value="${esc(st.q)}"></label>`}
      <span class="xt-n" role="status" aria-live="polite"></span>
      <span class="xt-acts">${ed?.manual?.() && ed.tipo === 'tabla' ? `<button type="button" class="btn btn-sm btn-cta xt-man" title="Agregar una fila con el formulario de estas columnas">${icon('plus')}<span>Agregar manual</span></button>` : ''}<button type="button" class="btn btn-sm xt-cols" aria-haspopup="true" aria-expanded="false" title="${ed?.puedeEditar?.() ? 'Cambiar, agregar o quitar columnas' : 'Elegir columnas'}">${icon('sidebar')}<span>Columnas</span></button><button type="button" class="btn btn-sm xt-xls" ${ed?.puedeEditar?.() ? 'aria-haspopup="true" aria-expanded="false"' : ''} title="${ed?.puedeEditar?.() ? 'Descargar, plantilla o carga masiva' : 'Descargar en Excel lo que ves'}">${icon('download')}<span>Excel</span>${ed?.puedeEditar?.() ? icon('chev') : ''}</button></span>`;
    tw.before(bar); bar.append(estilo);
    let pager = null;
    if (!externo) { pager = document.createElement('div'); pager.className = 'xt-pager'; tw.after(pager); }
    const filas0 = tablaFilas(t);
    filas0.forEach((r, i) => { r.dataset.xtI = i; });
    const tipoCol = i => {
      const vals = filas0.map(r => tablaTexto(r.cells[i])).filter(Boolean).slice(0, 40); if (!vals.length) return 'texto';
      if (vals.filter(v => tablaFecha(v) != null).length >= vals.length * .8) return 'fecha';
      if (vals.filter(v => tablaNumero(v) != null).length >= vals.length * .8) return 'numero';
      return 'texto';
    };
    const tipos = titulos.map((_, i) => tipoCol(i));
    const visibles = () => {
      const q = norm(st.q || '');
      let rs = tablaFilas(t);
      if (!externo) {
        rs.sort((a, b) => a.dataset.xtI - b.dataset.xtI);
        if (st.col >= 0) {
          const i = st.col, k = r => { const s = r.cells[i]?.dataset?.orden ?? tablaTexto(r.cells[i]); return tipos[i] === 'numero' ? tablaNumero(s) : tipos[i] === 'fecha' ? tablaFecha(s) : s; };
          rs.sort((a, b) => { const x = k(a), y = k(b); if (x == null || x === '') return 1; if (y == null || y === '') return -1; return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'es', {numeric: true, sensitivity: 'base'})) * st.dir; });
        }
      }
      return {todas: rs, filtradas: q ? rs.filter(r => norm(r.textContent || '').includes(q)) : rs};
    };
    const pintar = () => {
      const {todas, filtradas} = visibles(), n = bar.querySelector('.xt-n');
      if (!externo) {
        const body = t.tBodies[0], pags = Math.max(1, Math.ceil(filtradas.length / TABLA_POR_PAGINA)); st.p = Math.min(st.p, pags - 1);
        const ini = st.p * TABLA_POR_PAGINA, set = new Set(filtradas.slice(ini, ini + TABLA_POR_PAGINA));
        for (const r of todas) { body.append(r); r.hidden = !set.has(r); }
        let vacio = body.querySelector('.xt-vacio');
        if (!filtradas.length && todas.length) { if (!vacio) { vacio = document.createElement('tr'); vacio.className = 'xt-vacio'; vacio.innerHTML = `<td colspan="${ths.length}" class="empty"><b>Nada coincide con «${esc(st.q)}»</b>Prueba con otra palabra.</td>`; body.append(vacio); } else vacio.querySelector('b').textContent = `Nada coincide con «${st.q}»`; }
        else vacio?.remove();
        pager.hidden = filtradas.length <= TABLA_POR_PAGINA;
        pager.innerHTML = pager.hidden ? '' : `<span>${int(ini + 1)}–${int(Math.min(filtradas.length, ini + TABLA_POR_PAGINA))} de ${int(filtradas.length)}</span><div><button type="button" class="btn btn-sm" data-xp="-1" ${st.p ? '' : 'disabled'}>Anterior</button><button type="button" class="btn btn-sm" data-xp="1" ${st.p >= pags - 1 ? 'disabled' : ''}>Siguiente</button></div>`;
        ths.forEach((th, i) => { if (acciones(i)) return; th.setAttribute('aria-sort', st.col === i ? (st.dir > 0 ? 'ascending' : 'descending') : 'none'); th.classList.toggle('xt-on', st.col === i); });
      }
      n.textContent = st.q && filtradas.length !== todas.length ? `${int(filtradas.length)} de ${int(todas.length)} filas` : `${int(todas.length)} ${todas.length === 1 ? 'fila' : 'filas'}`;
      bar.querySelector('.xt-cols').classList.toggle('is-on', ocultas.length > 0);
      tw.classList.toggle('xt-alta', filtradas.length > 14);
    };
    if (!externo) {
      ths.forEach((th, i) => {
        if (acciones(i)) return;
        th.classList.add('xt-th'); th.tabIndex = 0; th.title = 'Ordenar por ' + titulos[i];
        const ord = () => { st.dir = st.col === i ? -st.dir : (tipos[i] === 'texto' ? 1 : -1); st.col = i; st.p = 0; pintar(); };
        th.addEventListener('click', ord); th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ord(); } });
      });
      const q = bar.querySelector('input'); let tq = 0;
      q.addEventListener('input', () => { clearTimeout(tq); tq = setTimeout(() => { st.q = q.value; st.p = 0; pintar(); }, 120); });
      pager.addEventListener('click', e => { const b = e.target.closest('[data-xp]'); if (!b) return; st.p += +b.dataset.xp; pintar(); bar.scrollIntoView({block: 'nearest', behavior: reduced() ? 'auto' : 'smooth'}); });
    }
    /* 2.1.19: menú de cada columna (⋮), filas a mano y celdas de columnas propias. */
    if (ed) {
      ths.forEach((th, i) => {
        if (acciones(i) || th.querySelector('.te-thm')) return;
        const mb = document.createElement('button'); mb.type = 'button'; mb.className = 'te-thm'; mb.setAttribute('aria-haspopup', 'true'); mb.setAttribute('aria-expanded', 'false');
        mb.setAttribute('aria-label', 'Opciones de la columna ' + titulos[i]); mb.title = 'Opciones de la columna'; mb.innerHTML = '<span aria-hidden="true">⋮</span>';
        th.append(mb);
        mb.addEventListener('click', e => { e.stopPropagation(); e.preventDefault(); teMenuColumna(ed, th, mb, {ordenar: externo ? null : dir => { st.col = i; st.dir = dir; st.p = 0; pintar(); }}); });
        mb.addEventListener('keydown', e => e.stopPropagation());
      });
      const abrir = e => {
        const td = e.target.closest('td[data-te-celda]'); if (td && ed.tipo === 'tabla') { e.stopPropagation(); teEditarCelda(ed, td); return; }
        const tr = e.target.closest('tr.te-manual'); if (!tr || e.target.closest('a, button')) return;
        e.stopPropagation(); const f = (ed.manuales || []).find(m => m.id === tr.dataset.teFila); if (f) teAgregarManual(ed, f);
      };
      t.tBodies[0]?.addEventListener('click', abrir);
      t.tBodies[0]?.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches?.('tr.te-manual')) abrir(e); });
      bar.querySelector('.xt-man')?.addEventListener('click', () => teAgregarManual(ed));
    }
    bar.querySelector('.xt-cols').addEventListener('click', e => {
      const b = e.currentTarget;
      if (ed?.puedeEditar?.()) { teEditorColumnas(ed); return; }
      const pop = openPop(b, `<p class="xt-pop-h">Columnas visibles</p><div class="xt-pop-l">${titulos.map((x, i) => acciones(i) ? '' : `<label><input type="checkbox" value="${esc(x)}" ${ocultas.includes(x) ? '' : 'checked'}> ${esc(x)}</label>`).join('')}</div><button type="button" class="btn btn-sm" data-xt-todas>Mostrar todas</button>`, 'xt-pop', {align: 'right'});
      if (!pop) return;
      const sync = () => { ocultas = [...pop.querySelectorAll('input')].filter(x => !x.checked).map(x => x.value); if (ocultas.length >= titulos.filter((_, i) => !acciones(i)).length) { ocultas = ocultas.slice(1); pop.querySelector('input').checked = true; } tablaGuardar(clave, ocultas); aplicarCols(); pintar(); };
      pop.addEventListener('change', sync);
      pop.querySelector('[data-xt-todas]').onclick = () => { pop.querySelectorAll('input').forEach(x => { x.checked = true; }); sync(); };
    });
    const descargarVista = () => {
      if (typeof TB !== 'undefined' && String(R.view).startsWith('tc_') && TB.api?.puedeExportar && !TB.api.puedeExportar()) return toast('Tu rol no puede descargar datos.');
      let ext = typeof TB !== 'undefined' && externo ? TB.api?.filas?.(R.view) : null;
      /* 2.1.19: con columnas editadas, el Excel sale con los nombres, el orden y las filas de la tabla. Si la tabla
         está completa en pantalla (sin páginas del reporte), se toma tal como se ve, con sus columnas propias. */
      if (ext && ed) { if (ed.tipo === 'tabla' && !tw?.nextElementSibling?.classList?.contains('pager')) ext = null; else ext = teMapearExterno(ed, ext); }
      const nom = `${(MODS[modulo]?.name || modulo || 'tabla')}${nombre && nombre !== MODS[modulo]?.name ? ' - ' + nombre : ''}`.toLowerCase().replace(/\s+/g, '-');
      let hoja;
      if (ext) hoja = {nombre: nombre || MODS[modulo]?.name, columnas: ext.columnas.filter(c => !ocultas.includes(c.titulo)), filas: ext.filas};
      else {
        const cols = titulos.map((x, i) => ({clave: 'c' + i, titulo: x, i})).filter(c => !acciones(c.i) && !ocultas.includes(c.titulo));
        const rs = visibles().filtradas;
        const filas = rs.map(r => Object.fromEntries(cols.map(c => { const s = tablaTexto(r.cells[c.i]); return [c.clave, tipos[c.i] === 'numero' && tablaNumero(s) != null ? tablaNumero(s) : s]; })));
        hoja = {nombre: nombre || MODS[modulo]?.name || 'Tabla', columnas: cols, filas};
      }
      const n = AgoraExcel.descargar(nom + '-' + new Date().toISOString().slice(0, 10), [hoja]);
      toast(`Descargaste ${int(hoja.filas.length)} ${hoja.filas.length === 1 ? 'fila' : 'filas'} en ${n}`);
      try { agoraApi('workspace/auditoria', {method: 'POST', body: {accion: 'descargo.excel', detalle: `${MODS[modulo]?.name || modulo} · ${hoja.filas.length} filas`}}).catch(() => {}); } catch {}
    };
    bar.querySelector('.xt-xls').addEventListener('click', e => { if (ed?.puedeEditar?.()) teMenuExcel(ed, e.currentTarget, descargarVista); else descargarVista(); });
    pintar();
    return {bar, pintar};
  },
  /** Mejora las tablas candidatas dentro de un contenedor (idempotente). */
  revisar(root) {
    if (!root?.querySelectorAll || typeof R === 'undefined' || !TABLA_MODS.includes(R.mod)) return;
    root.querySelectorAll('table:not([data-xt])').forEach(t => {
      if (!AgoraTabla.candidata(t)) return;
      const sec = t.closest('.panel, section, .pf-sec');
      const nombre = t.dataset.nombre || tablaTexto(sec?.querySelector('h2, h3, .t-panel, .panel-h')) || MODS[R.mod]?.name || '';
      try { AgoraTabla.mejorar(t, {nombre: nombre.slice(0, 60), modulo: R.mod}); } catch (e) { console.warn('[tabla]', e); }
    });
    for (const fn of AgoraTabla.ganchos) try { fn(root); } catch (e) { console.warn('[tabla]', e); }
  },
  ganchos: [],
  alPintar(fn) { AgoraTabla.ganchos.push(fn); },
};
window.AgoraTabla = AgoraTabla;
/* El contenido principal cambia (pintado, región del tablero, módulos ARP): se revisa en el siguiente cuadro. */
(function tablaVigilar() {
  if (typeof MutationObserver === 'undefined' || typeof document === 'undefined' || !document.getElementById) return;
  let pend = 0;
  const arrancar = () => {
    const app = document.getElementById('app'); if (!app) return false;
    new MutationObserver(() => { if (pend) return; pend = requestAnimationFrame(() => { pend = 0; AgoraTabla.revisar(app); }); }).observe(app, {childList: true, subtree: true});
    return true;
  };
  if (!arrancar()) document.addEventListener?.('DOMContentLoaded', arrancar, {once: true});
})();
/* =====================================================================================
   2.1.17 · Migración de datos con Excel (equipo datos)
   -------------------------------------------------------------------------------------
   - camposDe(modulo) -> Promise<campos>: campos personalizables de un módulo (con caché por
     subcuenta). El formulario de venta ganada lo usa con camposDe('ventas').
   - Módulo «Migración de datos» (/migracion, solo administración): Campos (ordenar, renombrar,
     mostrar, obligatorio, campos propios), Descargar (plantilla y datos actuales) y Cargar
     (subir .xlsx/.csv, relacionar columnas, revisar, simular e importar por lotes de 500).
   - Botón «Excel» en el encabezado de Contactos, Oportunidades, Productos, Ventas, Tareas y Usuarios.
   ===================================================================================== */
const MIG_LOTE = 500;
const MIG_DE_MOD = {contactos: 'contactos', oportunidades: 'oportunidades', productos: 'productos', ventas: 'ventas', tareas: 'tareas', usuarios: 'usuarios'};
const MIG_TIPOS = {texto: 'Texto', numero: 'Número', fecha: 'Fecha', correo: 'Correo', telefono: 'Teléfono', lista: 'Lista de opciones', si_no: 'Sí o no'};
let MIG = {mod: 'contactos', tab: 'campos', modulos: null, info: {}, edit: null, sucio: false, carga: null};
let CAMPOS_CACHE = {}, CAMPOS_P = {};
window.addEventListener?.('agora:account-changing', () => { CAMPOS_CACHE = {}; CAMPOS_P = {}; MIG = {...MIG, modulos: null, info: {}, edit: null, sucio: false, carga: null}; });

/** Campos de un módulo (todos, ordenados; filtra c.visible para mostrar). */
function camposDe(modulo, force) {
  if (CAMPOS_CACHE[modulo] && !force) return Promise.resolve(CAMPOS_CACHE[modulo].campos);
  CAMPOS_P[modulo] ??= agoraApi('workspace/campos?modulo=' + encodeURIComponent(modulo)).then(d => { CAMPOS_CACHE[modulo] = d; MIG.info[modulo] = d; return d.campos || []; }).finally(() => { delete CAMPOS_P[modulo]; });
  return CAMPOS_P[modulo];
}
window.camposDe = camposDe;

/* ---------- Validación en el navegador (la misma regla del servidor; el servidor manda) ---------- */
const migNorm = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
function migNumero(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? v : null;
  let s = String(v ?? '').replace(/[\s $€]|bs\.?|usd|ves|eur/gi, '');
  if (!s || !/^-?[0-9.,]+$/.test(s)) return null;
  const c = s.lastIndexOf(','), p = s.lastIndexOf('.');
  if (c >= 0 && p >= 0) s = c > p ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
  else if (c >= 0) s = (s.match(/,/g).length > 1) ? s.replace(/,/g, '') : s.replace(',', '.');
  else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
  const n = Number(s); return Number.isFinite(n) ? n : null;
}
function migFecha(v) {
  const p = x => String(x).padStart(2, '0');
  if (typeof v === 'number' || /^\d{4,5}(\.\d+)?$/.test(String(v).trim())) { const n = Number(v); if (n < 1 || n > 2958465) return null; const d = new Date(Math.round((n - 25569) * 86400000)); return `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())}`; }
  const s = String(v).trim(); let m, y, mo, d;
  if ((m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ][0-9:.]+Z?)?$/))) [y, mo, d] = [+m[1], +m[2], +m[3]];
  else if ((m = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$/))) { [d, mo, y] = [+m[1], +m[2], +m[3]]; if (y < 100) y += 2000; }
  else return null;
  const f = new Date(Date.UTC(y, mo - 1, d));
  return f.getUTCFullYear() === y && f.getUTCMonth() === mo - 1 && f.getUTCDate() === d ? `${y}-${p(mo)}-${p(d)}` : null;
}
/** [valor, error] de una celda según el tipo del campo. Vacío = [null, null] salvo que sea obligatorio. */
function migCelda(c, v) {
  if (typeof v === 'string') { v = v.trim(); if (/^'[=+\-@]/.test(v)) v = v.slice(1); }
  if (v == null || v === '') return [null, c.requerido ? 'Es obligatorio.' : null];
  switch (c.tipo) {
    case 'numero': { const n = migNumero(v); return n == null ? [null, 'Debe ser un número.'] : Math.abs(n) > 1e12 ? [null, 'Número fuera de rango.'] : [n, null]; }
    case 'fecha': { const f = migFecha(v); return f ? [f, null] : [null, 'Fecha inválida. Usa 2026-11-13 o 13/11/2026.']; }
    case 'correo': { const s = String(v).toLowerCase(); return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s) && s.length <= 254 ? [s, null] : [null, 'Correo inválido.']; }
    case 'telefono': { const s = String(v); return /^\+?[0-9 ().-]{4,40}$/.test(s) && (s.match(/\d/g) || []).length >= 4 ? [s, null] : [null, 'Teléfono inválido.']; }
    case 'si_no': { if (typeof v === 'boolean') return [v, null]; const n = migNorm(v); if (['si', 's', 'x', '1', 'true', 'verdadero', 'activo', 'yes'].includes(n)) return [true, null]; if (['no', 'n', '0', 'false', 'falso', 'inactivo'].includes(n)) return [false, null]; return [null, 'Escribe Sí o No.']; }
    case 'lista': { const o = (c.opciones || []).find(x => migNorm(x) === migNorm(v)); return o ? [o, null] : [null, `Elige una de estas opciones: ${(c.opciones || []).slice(0, 8).join(', ')}.`]; }
    default: return String(v).length > 2000 ? [null, 'Texto demasiado largo (máximo 2.000 caracteres).'] : [String(v), null];
  }
}
window.AgoraMigracion = {celda: migCelda, numero: migNumero, fecha: migFecha, norm: migNorm, LOTE: MIG_LOTE};

/* ---------- Descargas ---------- */
const migArchivo = (mod, que) => `${migNorm(MIG.info[mod]?.nombre || mod) || mod}-${que}-${new Date().toISOString().slice(0, 10)}`;
async function migDescargarPlantilla(mod) {
  const d = await agoraApi('workspace/migracion/plantilla?modulo=' + encodeURIComponent(mod));
  const cols = d.columnas.map(c => ({clave: c.clave, titulo: c.titulo, tipo: c.tipo}));
  const inst = {nombre: 'Instrucciones', filtro: false, columnas: [{clave: 'titulo', titulo: 'Columna'}, {clave: 'tipo', titulo: 'Tipo de dato'}, {clave: 'req', titulo: 'Obligatorio'}, {clave: 'opc', titulo: 'Opciones válidas'}, {clave: 'ej', titulo: 'Ejemplo'}, {clave: 'ayuda', titulo: 'Cómo llenarla'}],
    filas: d.columnas.map(c => ({titulo: c.titulo, tipo: MIG_TIPOS[c.tipo] || c.tipo, req: c.requerido ? 'Sí' : 'No', opc: (c.opciones || []).join(', '), ej: d.ejemplo?.[0]?.[c.clave] ?? '', ayuda: c.ayuda || ''}))};
  inst.filas.push({}, {titulo: 'Llena la hoja «' + d.nombre + '» desde la fila 2. No cambies los nombres de la fila 1: así Ágora reconoce cada columna. Las celdas vacías no borran datos al actualizar.'});
  const n = AgoraExcel.descargar(migArchivo(mod, 'plantilla'), [{nombre: d.nombre, columnas: cols, filas: []}, inst]);
  toast(`Plantilla lista: ${n}`);
}
async function migDescargarDatos(mod, alAvanzar) {
  let cursor = 0, filas = [], cols = null, nombre = mod, total = 0;
  for (let i = 0; i < 200; i++) {
    const d = await agoraApi(`workspace/migracion/exportar?modulo=${encodeURIComponent(mod)}${cursor ? '&cursor=' + cursor : ''}`);
    cols ??= d.columnas; nombre = d.nombre; total = d.total; filas = filas.concat(d.filas || []); alAvanzar?.(filas.length, total);
    if (!d.siguiente) break; cursor = d.siguiente;
  }
  const n = AgoraExcel.descargar(migArchivo(mod, 'datos'), [{nombre, columnas: cols.map(c => ({clave: c.clave, titulo: c.titulo, tipo: c.tipo})), filas}]);
  toast(`Descargaste ${int(filas.length)} ${filas.length === 1 ? 'registro' : 'registros'} en ${n}`);
  return filas.length;
}

/* ---------- Módulo ---------- */
MODS.migracion = {g: 'administracion', view: 'migracion', name: 'Migración de datos', icon: 'upload', path: '/migracion', c: 'var(--mod-seguridad)',
  desc: 'Descarga y carga tus datos con Excel, y ajusta sus campos.', ok: () => esAdmin()};
V.migracion = {
  async need() {
    if (!MIG.modulos) { const d = await agoraApi('workspace/campos'); MIG.modulos = d.modulos || []; }
    if (!MIG.modulos.some(m => m.id === MIG.mod)) MIG.mod = MIG.modulos[0]?.id || 'contactos';
    await camposDe(MIG.mod);
    if (!MIG.edit || MIG.edit.mod !== MIG.mod) migEditarDesde(MIG.mod);
  },
  render() {
    const m = MIG.modulos?.find(x => x.id === MIG.mod), info = MIG.info[MIG.mod];
    const cab = head('Administración', 'Migración de datos', 'Trae la información de tu empresa en bloque: descarga la plantilla, llénala en Excel y cárgala. También puedes ajustar los campos de cada módulo.');
    if (!MIG.modulos || !info) return `<div class="mg-v">${cab}<div class="mg-mods" aria-hidden="true">${'<span class="sk mg-sk-mod"></span>'.repeat(6)}</div><section class="panel mg-panel"><span class="sk w40"></span><span class="sk w80"></span><span class="sk w60"></span><span class="sk w80"></span></section></div>`;
    if (!m.importa && MIG.tab === 'cargar') MIG.tab = 'descargar';
    const tabs = [['campos', 'edit', 'Campos'], ['descargar', 'download', 'Descargar'], ...(m.importa ? [['cargar', 'upload', 'Cargar']] : [])];
    return `<div class="mg-v">${cab}
      <nav class="mg-mods" aria-label="Módulo de datos">${MIG.modulos.map(x => `<button type="button" class="mg-mod ${x.id === MIG.mod ? 'is-on' : ''}" data-mg-mod="${esc(x.id)}" aria-pressed="${x.id === MIG.mod}"><span class="mg-mod-ic" aria-hidden="true">${icon(x.icono || 'invoice')}</span><span><b>${esc(x.nombre)}</b><small>${esc(x.importa ? x.descripcion : 'Solo descarga')}</small></span></button>`).join('')}</nav>
      <section class="panel mg-panel">
        <div class="mg-tabs" role="tablist" aria-label="Qué quieres hacer">${tabs.map(([id, ic, t]) => `<button type="button" role="tab" class="mg-tab ${MIG.tab === id ? 'is-on' : ''}" aria-selected="${MIG.tab === id}" data-mg-tab="${id}">${icon(ic)}${t}</button>`).join('')}</div>
        <div class="mg-body" role="tabpanel">${MIG.tab === 'campos' ? migCamposHtml(m, info) : MIG.tab === 'descargar' ? migDescargarHtml(m, info) : migCargarHtml(m, info)}</div>
      </section></div>`;
  },
  mounted(root) {
    if (!MIG.modulos || !MIG.info[MIG.mod]) return;
    root.querySelectorAll('[data-mg-mod]').forEach(b => b.onclick = () => migCambiar(() => { MIG.mod = b.dataset.mgMod; MIG.carga = null; render(); }));
    root.querySelectorAll('[data-mg-tab]').forEach(b => b.onclick = () => migCambiar(() => { MIG.tab = b.dataset.mgTab; repaint(); }, b.dataset.mgTab === 'campos'));
    const body = root.querySelector('.mg-body');
    if (MIG.tab === 'campos') migCamposMontar(body); else if (MIG.tab === 'descargar') migDescargarMontar(body); else migCargarMontar(body);
  },
};
function migCambiar(fn, mismo) { if (!MIG.sucio || mismo) return fn(); confirmModal('Cambios sin guardar', 'Tienes cambios en los campos que no guardaste. Si sigues, se pierden.', 'Descartar cambios', async () => { MIG.sucio = false; MIG.edit = null; fn(); }, true); }
/** Abre el módulo en un módulo y pestaña concretos (desde el botón Excel de otros módulos). */
function migracionIr(mod, tab = 'cargar') { MIG.mod = mod; MIG.tab = tab; MIG.carga = null; MIG.edit = null; MIG.sucio = false; go('/migracion'); }

/* ---------- Pestaña Campos ---------- */
function migEditarDesde(mod) { MIG.edit = {mod, campos: (MIG.info[mod]?.campos || []).map(c => ({...c, opciones: c.opciones ? [...c.opciones] : null}))}; MIG.sucio = false; }
function migCamposHtml(m, info) {
  const can = info.canEdit, cs = MIG.edit.campos;
  const fila = (c, i) => `<li class="mg-campo ${c.visible ? '' : 'is-off'}" data-i="${i}" ${can ? 'draggable="true"' : ''}>
      ${can ? `<span class="mg-asa" aria-hidden="true" title="Arrastra para ordenar">⋮⋮</span>` : ''}
      <span class="mg-n">${i + 1}</span>
      <label class="mg-tit"><span class="sr-only">Nombre de la columna ${i + 1}</span><input value="${esc(c.titulo)}" maxlength="60" data-mg-t="${i}" ${can ? '' : 'disabled'}>${c.tituloBase && c.tituloBase !== c.titulo ? `<small>Antes: ${esc(c.tituloBase)}</small>` : c.personalizado ? '<small>Campo propio</small>' : ''}</label>
      <span class="sc ${c.personalizado ? 'sc-info' : 'sc-gray'} mg-tipo" title="${esc(c.opciones ? 'Opciones: ' + c.opciones.join(', ') : '')}">${esc(MIG_TIPOS[c.tipo] || c.tipo)}</span>
      <label class="mg-sw mg-sw-v" title="${c.bloqueado ? 'Este campo siempre se muestra' : 'Mostrar en Excel y formularios'}"><input type="checkbox" data-mg-v="${i}" ${c.visible ? 'checked' : ''} ${can && !c.bloqueado ? '' : 'disabled'}><span>Visible</span></label>
      <label class="mg-sw mg-sw-r" title="${c.bloqueado ? 'Ágora lo necesita siempre' : 'La carga avisa si viene vacío'}"><input type="checkbox" data-mg-r="${i}" ${c.requerido ? 'checked' : ''} ${can && !c.bloqueado ? '' : 'disabled'}><span>Obligatorio</span></label>
      ${can ? `<span class="mg-flechas"><button type="button" class="icon-btn" data-mg-up="${i}" aria-label="Subir ${esc(c.titulo)}" ${i ? '' : 'disabled'}>${icon('chev')}</button><button type="button" class="icon-btn" data-mg-down="${i}" aria-label="Bajar ${esc(c.titulo)}" ${i < cs.length - 1 ? '' : 'disabled'}>${icon('chev')}</button>${c.personalizado ? `<button type="button" class="icon-btn" data-mg-ed="${i}" aria-label="Editar ${esc(c.titulo)}">${icon('edit')}</button>` : ''}</span>` : ''}
    </li>`;
  return `${secH('edit', `Campos de ${esc(m.nombre)}`, can ? 'El orden y los nombres de aquí son los de la plantilla, la descarga y los formularios. Arrastra o usa las flechas.' : 'Solo quien administra la subcuenta puede cambiar los campos.',
      can ? `<button type="button" class="btn btn-sm" data-mg-rest>${icon('sync')}Restaurar</button>${m.importa ? `<button type="button" class="btn btn-sm btn-tint" data-mg-nuevo>${icon('plus')}Agregar campo</button>` : ''}` : '')}
    <ol class="mg-campos">${cs.map(fila).join('')}</ol>
    ${info.actualizado ? `<p class="src">Última modificación: ${esc(new Date(info.actualizado).toLocaleString('es-VE'))}${info.por ? ' · ' + esc(info.por) : ''}</p>` : ''}
    ${can ? `<div class="mg-pie ${MIG.sucio ? 'is-on' : ''}"><span>${MIG.sucio ? 'Tienes cambios sin guardar.' : 'Sin cambios.'}</span><div class="acts"><button type="button" class="btn" data-mg-desc ${MIG.sucio ? '' : 'disabled'}>Descartar</button><button type="button" class="btn btn-cta" data-mg-guardar ${MIG.sucio ? '' : 'disabled'}>Guardar campos</button></div></div>` : ''}`;
}
function migCamposMontar(body) {
  const cs = MIG.edit.campos, sucio = () => { MIG.sucio = true; const p = body.querySelector('.mg-pie'); if (p) { p.classList.add('is-on'); p.querySelector('span').textContent = 'Tienes cambios sin guardar.'; p.querySelectorAll('button').forEach(b => { b.disabled = false; }); } };
  const mover = (i, j) => { if (j < 0 || j >= cs.length) return; const [c] = cs.splice(i, 1); cs.splice(j, 0, c); MIG.sucio = true; repaint(); requestAnimationFrame(() => $(`[data-i="${j}"] .mg-flechas button:not([disabled])`)?.focus()); };
  body.querySelectorAll('[data-mg-t]').forEach(x => x.oninput = () => { cs[+x.dataset.mgT].titulo = x.value; sucio(); });
  body.querySelectorAll('[data-mg-v]').forEach(x => x.onchange = () => { cs[+x.dataset.mgV].visible = x.checked; x.closest('.mg-campo').classList.toggle('is-off', !x.checked); sucio(); });
  body.querySelectorAll('[data-mg-r]').forEach(x => x.onchange = () => { cs[+x.dataset.mgR].requerido = x.checked; sucio(); });
  body.querySelectorAll('[data-mg-up]').forEach(b => b.onclick = () => mover(+b.dataset.mgUp, +b.dataset.mgUp - 1));
  body.querySelectorAll('[data-mg-down]').forEach(b => b.onclick = () => mover(+b.dataset.mgDown, +b.dataset.mgDown + 1));
  body.querySelectorAll('[data-mg-ed]').forEach(b => b.onclick = () => migCampoEditar(+b.dataset.mgEd));
  body.querySelector('[data-mg-nuevo]')?.addEventListener('click', () => migCampoEditar(null));
  body.querySelector('[data-mg-desc]')?.addEventListener('click', () => { migEditarDesde(MIG.mod); repaint(); });
  body.querySelector('[data-mg-rest]')?.addEventListener('click', () => confirmModal('Restaurar campos', 'Los campos vuelven a sus nombres y orden de fábrica, y se quitan los campos propios. Los datos ya guardados no se borran.', 'Restaurar', async () => {
    const d = await agoraApi('workspace/campos', {method: 'PUT', body: {modulo: MIG.mod, restaurar: true}});
    CAMPOS_CACHE[MIG.mod] = d; MIG.info[MIG.mod] = d; migEditarDesde(MIG.mod); repaint(); toast('Campos restaurados.');
  }, true));
  body.querySelector('[data-mg-guardar]')?.addEventListener('click', async e => {
    const b = e.currentTarget, vacio = cs.find(c => !c.titulo.trim());
    if (vacio) { toast('Cada campo necesita un nombre.'); return; }
    const vistos = {}; for (const c of cs) { const k = migNorm(c.titulo); if (vistos[k]) { toast(`Dos campos se llaman «${c.titulo.trim()}». Usa nombres distintos.`); return; } vistos[k] = 1; }
    busy(b, true, 'Guardando…');
    try {
      const d = await agoraApi('workspace/campos', {method: 'PUT', body: {modulo: MIG.mod, campos: cs.map((c, i) => ({clave: c.clave, titulo: c.titulo.trim(), tipo: c.tipo, opciones: c.opciones, orden: i, visible: c.visible, requerido: c.requerido, personalizado: !!c.personalizado}))}});
      CAMPOS_CACHE[MIG.mod] = d; MIG.info[MIG.mod] = d; migEditarDesde(MIG.mod); repaint(); toast('Campos guardados.');
    } catch (x) { busy(b, false); toast(x.message); }
  });
  /* Arrastrar y soltar */
  let desde = null;
  body.querySelectorAll('.mg-campo[draggable]').forEach(li => {
    li.addEventListener('dragstart', e => { desde = +li.dataset.i; li.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(desde)); } catch {} });
    li.addEventListener('dragend', () => { li.classList.remove('is-drag'); body.querySelectorAll('.is-over').forEach(x => x.classList.remove('is-over')); });
    li.addEventListener('dragover', e => { e.preventDefault(); li.classList.add('is-over'); });
    li.addEventListener('dragleave', () => li.classList.remove('is-over'));
    li.addEventListener('drop', e => { e.preventDefault(); const a = +li.dataset.i; if (desde != null && desde !== a) mover(desde, a); desde = null; });
  });
}
function migCampoEditar(i) {
  const c = i == null ? {titulo: '', tipo: 'texto', opciones: null, requerido: false, visible: true, personalizado: true} : MIG.edit.campos[i];
  const s = openModal(modalHead(i == null ? 'Agregar campo' : 'Editar campo', 'Un campo propio guarda un dato que tu empresa necesita, por ejemplo «Cédula» o «Sede».') +
    `<form class="bx-form" novalidate><div class="bx-grid"><fieldset class="bx bx-wide"><legend>${icon('edit')}Campo</legend>
      <label class="fld"><span class="fld-l">Nombre de la columna <span class="req" aria-hidden="true">*</span></span><input name="titulo" maxlength="60" required autofocus value="${esc(c.titulo)}" placeholder="Ej: Cédula"></label>
      <div class="bx-row"><label class="fld">Tipo de dato<select name="tipo">${Object.entries(MIG_TIPOS).map(([k, t]) => `<option value="${k}" ${c.tipo === k ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
      <label class="bx-check"><input type="checkbox" name="requerido" ${c.requerido ? 'checked' : ''}> Obligatorio</label></div>
      <label class="fld" data-mg-opc><span class="fld-l">Opciones <span class="req" aria-hidden="true">*</span></span><input name="opciones" value="${esc((c.opciones || []).join(', '))}" placeholder="Separadas por coma: Básico, Avanzado"></label>
    </fieldset></div><p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">Se guarda al pulsar «Guardar campos».</p><div class="acts">${i != null ? '<button class="btn btn-danger" type="button" data-borrar>Quitar campo</button>' : ''}<button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${i == null ? 'Agregar' : 'Listo'}</button></div></div></form>`);
  const f = s.querySelector('form'), err = s.querySelector('.fld-err'), opc = s.querySelector('[data-mg-opc]');
  const sync = () => { opc.hidden = f.tipo.value !== 'lista'; }; f.tipo.onchange = sync; sync();
  f.onsubmit = e => {
    e.preventDefault(); const t = f.titulo.value.trim().replace(/\s+/g, ' ');
    if (!t) { err.textContent = 'Escribe el nombre de la columna.'; return; }
    if (MIG.edit.campos.some((x, j) => j !== i && migNorm(x.titulo) === migNorm(t))) { err.textContent = 'Ya hay un campo con ese nombre.'; return; }
    const o = f.tipo.value === 'lista' ? [...new Set(f.opciones.value.split(',').map(x => x.trim()).filter(Boolean))] : null;
    if (o && !o.length) { err.textContent = 'Escribe al menos una opción.'; return; }
    if (o && o.some(x => x.length > 60)) { err.textContent = 'Cada opción puede tener hasta 60 caracteres.'; return; }
    const datos = {titulo: t, tipo: f.tipo.value, opciones: o, requerido: f.requerido.checked};
    if (i == null) {
      if (MIG.edit.campos.filter(x => x.personalizado).length >= 40) { err.textContent = 'Hasta 40 campos propios por módulo.'; return; }
      let base = 'cf_' + (migNorm(t).slice(0, 28) || 'campo'), clave = base, k = 2; while (MIG.edit.campos.some(x => x.clave === clave)) clave = base + '_' + k++;
      MIG.edit.campos.push({clave, visible: true, personalizado: true, tituloBase: null, ...datos});
    } else Object.assign(MIG.edit.campos[i], datos);
    MIG.sucio = true; closeModal(); repaint();
  };
  s.querySelector('[data-borrar]')?.addEventListener('click', () => { MIG.edit.campos.splice(i, 1); MIG.sucio = true; closeModal(); repaint(); toast('Campo quitado. Pulsa «Guardar campos» para confirmar. Los datos ya cargados no se borran.'); });
}

/* ---------- Pestaña Descargar ---------- */
function migDescargarHtml(m, info) {
  const vis = info.campos.filter(c => c.visible);
  return `${secH('download', `Descargar ${esc(m.nombre.toLowerCase())}`, 'Archivos .xlsx que abren en Excel, LibreOffice o Google Sheets, con las columnas en el orden de tus campos.')}
    <div class="mg-cards">
      ${m.importa ? `<article class="bx mg-card"><span class="mg-card-ic" aria-hidden="true">${icon('invoice')}</span><h3>Plantilla vacía</h3><p>Las ${int(vis.length)} columnas listas para llenar y una hoja de instrucciones con el tipo de dato y las opciones válidas de cada una.</p><button type="button" class="btn btn-cta" data-mg-plant>${icon('download')}Descargar plantilla</button></article>` : ''}
      <article class="bx mg-card"><span class="mg-card-ic" aria-hidden="true">${icon('receipt')}</span><h3>Datos actuales</h3><p>Todo lo que hay hoy en ${esc(m.nombre)}${m.importa ? ', con su ID: cámbialo en Excel y vuelve a cargarlo para actualizar en bloque' : ''}.</p><button type="button" class="btn ${m.importa ? '' : 'btn-cta'}" data-mg-datos>${icon('download')}Descargar datos</button><p class="mg-card-st" role="status"></p></article>
    </div>
    <p class="src">Columnas: ${vis.map(c => esc(c.titulo)).join(' · ')}. Lo que empieza con =, +, - o @ se guarda con un apóstrofo delante para que Excel no lo ejecute como fórmula.</p>`;
}
function migDescargarMontar(body) {
  body.querySelector('[data-mg-plant]')?.addEventListener('click', async e => { const b = e.currentTarget; busy(b, true, 'Preparando…'); try { await migDescargarPlantilla(MIG.mod); } catch (x) { toast(x.message); } busy(b, false); });
  body.querySelector('[data-mg-datos]')?.addEventListener('click', async e => {
    const b = e.currentTarget, st = body.querySelector('.mg-card-st'); busy(b, true, 'Preparando…');
    try { await migDescargarDatos(MIG.mod, (n, t) => { st.textContent = `${int(n)} de ${int(t)} registros…`; }); st.textContent = ''; } catch (x) { st.textContent = ''; toast(x.message); }
    busy(b, false);
  });
}

/* ---------- Pestaña Cargar ---------- */
const MIG_SINONIMOS = {nombre: ['name', 'nombres', 'cliente', 'nombreyapellido'], correo: ['email', 'mail', 'correoelectronico', 'e-mail'], telefono: ['phone', 'celular', 'movil', 'whatsapp', 'tlf'], empresa: ['company', 'compania'], documento: ['cedula', 'rif', 'ci'], precio: ['price', 'costo'], monto: ['total', 'importe'], titulo: ['tarea', 'title']};
function migAutoMapa(cabeceras, campos) {
  const usados = new Set();
  return cabeceras.map(h => {
    const n = migNorm(h); if (!n) return '';
    const c = campos.find(c => !usados.has(c.clave) && [c.titulo, c.tituloBase, c.clave].some(x => x && migNorm(x) === n))
      || campos.find(c => !usados.has(c.clave) && (MIG_SINONIMOS[c.clave] || []).some(x => migNorm(x) === n));
    if (c) { usados.add(c.clave); return c.clave; }
    return '';
  });
}
/** Filas del archivo como objetos {clave: valor}, con su número de fila de Excel y los errores locales. */
function migFilas(st, campos) {
  const porClave = Object.fromEntries(campos.map(c => [c.clave, c])), out = [];
  st.datos.forEach((r, j) => {
    if (!r || !r.some(x => x !== '' && x != null)) return;
    const o = {}, errores = {};
    st.mapa.forEach((k, i) => { if (k) o[k] = r[i] ?? ''; });
    for (const c of campos) {
      if (!(c.clave in o)) { if (c.requerido && c.visible) errores[c.clave] = 'Es obligatorio.'; continue; }
      const [, e] = migCelda(c, o[c.clave]); if (e) errores[c.clave] = e;
    }
    out.push({n: st.inicio + j + 2, o, errores, srv: null});
  });
  void porClave;
  return out;
}
function migCargarHtml(m, info) {
  const st = MIG.carga, campos = info.campos;
  if (!st) return `${secH('upload', `Cargar ${esc(m.nombre.toLowerCase())}`, 'Sube tu Excel o CSV. Ágora reconoce las columnas por su nombre y te deja revisar todo antes de guardar.')}
    <label class="mg-drop" data-mg-drop tabindex="0"><input type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
      <span class="mg-drop-ic" aria-hidden="true">${icon('upload')}</span><b>Arrastra tu archivo aquí o toca para elegirlo</b><small>Excel (.xlsx) o CSV separado por punto y coma o coma · hasta 25 MB</small></label>
    <ol class="mg-pasos"><li><b>1</b>Sube el archivo</li><li><b>2</b>Revisa las columnas</li><li><b>3</b>Simula y corrige</li><li><b>4</b>Importa</li></ol>
    <p class="src">¿No tienes el archivo? <button type="button" class="link" data-mg-plant>Descarga la plantilla</button> con tus columnas.</p>`;
  if (st.informe) return migInformeHtml(m, st);
  const filas = st.filas || [], conErr = filas.filter(f => Object.keys(f.errores).length || f.srv?.length).length;
  const req = campos.filter(c => c.requerido && c.visible && !st.mapa.includes(c.clave));
  const mapeadas = st.cab.map((h, i) => [h, i]).filter(([, i]) => st.mapa[i]);
  const ver = filas.slice(0, 50), titulo = Object.fromEntries(campos.map(c => [c.clave, c.titulo]));
  return `${secH('upload', esc(st.archivo), `${int(filas.length)} filas con datos${st.hojas.length > 1 ? ` · hoja «${esc(st.hoja)}»` : ''}`, `<button type="button" class="btn btn-sm" data-mg-otro>${icon('sync')}Elegir otro archivo</button>`)}
    ${st.hojas.length > 1 ? `<label class="fld mg-hoja">Hoja del archivo<select data-mg-hoja>${st.hojas.map((h, i) => `<option value="${i}" ${h.nombre === st.hoja ? 'selected' : ''}>${esc(h.nombre)}</option>`).join('')}</select></label>` : ''}
    <div class="mg-paso"><h3><b>2</b>Columnas del archivo → campos de Ágora</h3>
      <div class="tw full" data-sin-mejorar><table class="mg-mapa"><thead><tr><th>Columna del archivo</th><th>Ejemplo</th><th>Se guarda en</th></tr></thead><tbody>
      ${st.cab.map((h, i) => `<tr class="${st.mapa[i] ? '' : 'is-off'}"><td><b>${esc(h || `(columna ${AgoraExcel.colLetra(i)})`)}</b></td><td class="mg-ej">${esc(String(st.datos.find(r => r?.[i] !== '' && r?.[i] != null)?.[i] ?? '—').slice(0, 60))}</td>
        <td><label><span class="sr-only">Campo para ${esc(h)}</span><select data-mg-map="${i}"><option value="">No importar</option>${campos.map(c => `<option value="${esc(c.clave)}" ${st.mapa[i] === c.clave ? 'selected' : ''} ${st.mapa.includes(c.clave) && st.mapa[i] !== c.clave ? 'disabled' : ''}>${esc(c.titulo)}${c.requerido ? ' *' : ''}${c.visible ? '' : ' (oculto)'}</option>`).join('')}</select></label></td></tr>`).join('')}
      </tbody></table></div>
      ${req.length ? `<p class="mg-aviso">${icon('warn')}Falta relacionar: ${req.map(c => `<b>${esc(c.titulo)}</b>`).join(', ')}. Son obligatorios.</p>` : ''}
    </div>
    <div class="mg-paso"><h3><b>3</b>Revisa antes de guardar</h3>
      <div class="mg-chips"><span class="sc sc-info">${int(filas.length)} filas</span><span class="sc ${conErr ? 'sc-warn' : 'sc-ok'}">${conErr ? `${int(conErr)} con errores` : 'Sin errores a la vista'}</span>${st.simulado ? `<span class="sc sc-ok">Simulación: ${int(st.simulado.creados)} nuevas · ${int(st.simulado.actualizados)} por actualizar</span>` : ''}</div>
      ${mapeadas.length ? `<div class="tw full mg-prev" data-sin-mejorar><table><thead><tr><th class="n">Fila</th>${mapeadas.map(([, i]) => `<th>${esc(titulo[st.mapa[i]])}</th>`).join('')}</tr></thead><tbody>
        ${ver.map(f => { const srv = Object.fromEntries((f.srv || []).map(e => [e.campo, e.mensaje])); const general = (f.srv || []).filter(e => !e.campo || !mapeadas.some(([, i]) => titulo[st.mapa[i]] === e.campo)); return `<tr class="${Object.keys(f.errores).length || f.srv?.length ? 'is-err' : ''}"><td class="n">${f.n}${general.length ? `<small class="mg-e">${esc(general.map(e => (e.campo ? e.campo + ': ' : '') + e.mensaje).join(' '))}</small>` : ''}</td>${mapeadas.map(([, i]) => { const k = st.mapa[i], e = f.errores[k] || srv[titulo[k]]; return `<td class="${e ? 'mg-err' : ''}" ${e ? `title="${esc(e)}"` : ''}>${esc(String(f.o[k] ?? '').slice(0, 80))}${e ? `<small class="mg-e">${esc(e)}</small>` : ''}</td>`; }).join('')}</tr>`; }).join('')}
      </tbody></table></div>${filas.length > 50 ? `<p class="src">Se muestran las primeras 50 filas. La simulación revisa todas.</p>` : ''}` : '<p class="empty"><b>Relaciona al menos una columna</b>Elige en «Se guarda en» a qué campo va cada columna.</p>'}
    </div>
    <div class="mg-paso mg-final"><h3><b>4</b>Importa</h3>
      <div class="mg-modos" role="radiogroup" aria-label="Qué hacer con los registros que ya existen">
        <label class="pfl-op"><input type="radio" name="mg-modo" value="crear" ${st.modo === 'crear' ? 'checked' : ''}><span><b>Solo crear nuevos</b><small>Si una fila trae el ID de un registro que ya existe, se marca como error.</small></span></label>
        <label class="pfl-op"><input type="radio" name="mg-modo" value="actualizar" ${st.modo !== 'crear' ? 'checked' : ''}><span><b>Crear y actualizar</b><small>Actualiza por ID${MIG.mod === 'contactos' ? ' o correo' : MIG.mod === 'productos' ? ', código o nombre' : ''}. Las celdas vacías no borran datos.</small></span></label>
      </div>
      <div class="mg-prog" hidden role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i><span></span></div>
      <div class="acts"><button type="button" class="btn" data-mg-sim ${req.length || !filas.length ? 'disabled' : ''}>${icon('shield')}Simular carga</button><button type="button" class="btn btn-cta" data-mg-imp ${req.length || !filas.length ? 'disabled' : ''}>${icon('upload')}Importar ${int(filas.length)} ${filas.length === 1 ? 'fila' : 'filas'}</button></div>
      <p class="src">Se envía en lotes de ${MIG_LOTE} filas. Las filas con errores no se guardan; al final te damos un informe para corregirlas.</p>
    </div>`;
}
function migInformeHtml(m, st) {
  const r = st.informe, errs = r.errores;
  return `${secH('check', 'Carga terminada', `${esc(st.archivo)} · ${esc(m.nombre)}`, `<button type="button" class="btn btn-sm" data-mg-otro>${icon('upload')}Cargar otro archivo</button>`)}
    <section class="ag-resumen-grid mg-res" aria-label="Resultado">
      ${tbTarjeta('is-ok', 'plus', 'Creados', int(r.creados), 'Registros nuevos')}
      ${tbTarjeta('', 'sync', 'Actualizados', int(r.actualizados), 'Ya existían')}
      ${tbTarjeta(errs.length ? 'is-warn' : '', 'warn', 'Filas con error', int(new Set(errs.map(e => e.fila)).size), errs.length ? 'No se guardaron' : 'Ninguna')}
      ${tbTarjeta('', 'invoice', 'Filas leídas', int(r.filas), `${int(r.lotes)} ${r.lotes === 1 ? 'lote' : 'lotes'}`)}
    </section>
    ${errs.length ? `<div class="mg-paso"><h3>Qué corregir</h3><div class="tw full"><table class="mg-errs" data-nombre="Errores de la carga"><thead><tr><th class="n">Fila</th><th>Campo</th><th>Qué pasó</th></tr></thead><tbody>${errs.slice(0, 500).map(e => `<tr><td class="n">${e.fila}</td><td>${esc(e.campo || '—')}</td><td>${esc(e.mensaje)}</td></tr>`).join('')}</tbody></table></div></div>` : '<p class="empty"><b>Todo quedó guardado</b>Ya puedes ver los datos en su módulo.</p>'}
    <div class="acts mg-inf-acts"><button type="button" class="btn ${errs.length ? 'btn-cta' : ''}" data-mg-inf>${icon('download')}Descargar informe</button>${MIG_IR[MIG.mod] ? `<a class="btn ${errs.length ? '' : 'btn-cta'}" href="${href(MIG_IR[MIG.mod])}">Ir a ${esc(m.nombre)}</a>` : ''}</div>`;
}
const MIG_IR = {contactos: '/contactos', oportunidades: '/oportunidades', productos: '/productos', tareas: '/tareas'};
async function migLeerArchivo(file) {
  const hojas = (await AgoraExcel.leer(file)).filter(h => h.filas.some(r => r.length));
  if (!hojas.length) throw new Error('El archivo está vacío.');
  const pref = hojas.find(h => !/instruc/i.test(h.nombre)) || hojas[0];
  MIG.carga = {archivo: file.name, hojas, modo: 'actualizar'}; migUsarHoja(pref);
}
function migUsarHoja(h) {
  const st = MIG.carga, campos = MIG.info[MIG.mod].campos;
  const ini = h.filas.findIndex(r => r.some(x => String(x ?? '').trim() !== ''));
  st.hoja = h.nombre; st.inicio = ini; st.cab = (h.filas[ini] || []).map(x => String(x ?? '').trim()); st.datos = h.filas.slice(ini + 1);
  st.mapa = migAutoMapa(st.cab, campos); st.filas = migFilas(st, campos); st.simulado = null; st.informe = null;
}
function migCargarMontar(body) {
  const st = MIG.carga, campos = MIG.info[MIG.mod].campos;
  body.querySelector('[data-mg-plant]')?.addEventListener('click', () => migDescargarPlantilla(MIG.mod).catch(x => toast(x.message)));
  body.querySelector('[data-mg-otro]')?.addEventListener('click', () => { MIG.carga = null; repaint(); });
  const drop = body.querySelector('[data-mg-drop]');
  if (drop) {
    const inp = drop.querySelector('input');
    const tomar = async f => { if (!f) return; drop.classList.add('is-busy'); drop.querySelector('b').textContent = 'Leyendo ' + f.name + '…'; try { await migLeerArchivo(f); repaint(); } catch (x) { drop.classList.remove('is-busy'); drop.querySelector('b').textContent = 'Arrastra tu archivo aquí o toca para elegirlo'; toast(x.message); } };
    inp.onchange = () => tomar(inp.files[0]);
    drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inp.click(); } });
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('is-over'); tomar(e.dataTransfer.files[0]); });
    return;
  }
  if (!st) return;
  body.querySelector('[data-mg-inf]')?.addEventListener('click', () => migInformeDescargar());
  body.querySelector('[data-mg-hoja]')?.addEventListener('change', e => { migUsarHoja(st.hojas[+e.target.value]); repaint(); });
  body.querySelectorAll('[data-mg-map]').forEach(s => s.onchange = () => { st.mapa[+s.dataset.mgMap] = s.value; st.filas = migFilas(st, campos); st.simulado = null; repaint(); });
  body.querySelectorAll('[name=mg-modo]').forEach(r => r.onchange = () => { st.modo = r.value; st.simulado = null; });
  body.querySelector('[data-mg-sim]')?.addEventListener('click', e => migEnviar('simular', e.currentTarget));
  body.querySelector('[data-mg-imp]')?.addEventListener('click', e => {
    const n = st.filas.filter(f => Object.keys(f.errores).length).length;
    const ir = () => migEnviar(st.modo === 'crear' ? 'crear' : 'actualizar', e.currentTarget);
    if (n) confirmModal('Hay filas con errores', `${int(n)} ${n === 1 ? 'fila tiene' : 'filas tienen'} errores y no se van a guardar. Las demás sí. ¿Seguimos?`, 'Importar las válidas', async () => ir());
    else ir();
  });
}
async function migEnviar(modo, boton) {
  const st = MIG.carga, filas = st.filas, lotes = Math.ceil(filas.length / MIG_LOTE), body = $('.mg-body');
  const prog = body.querySelector('.mg-prog'), barra = prog.querySelector('i'), txt = prog.querySelector('span');
  body.querySelectorAll('.mg-final button, .mg-final input, [data-mg-map]').forEach(x => { x.disabled = true; });
  prog.hidden = false; busy(boton, true, modo === 'simular' ? 'Simulando…' : 'Importando…');
  const r = {creados: 0, actualizados: 0, errores: [], filas: filas.length, lotes};
  filas.forEach(f => { f.srv = []; });
  try {
    for (let l = 0; l < lotes; l++) {
      const parte = filas.slice(l * MIG_LOTE, (l + 1) * MIG_LOTE);
      txt.textContent = `${modo === 'simular' ? 'Revisando' : 'Guardando'} lote ${l + 1} de ${lotes}…`;
      const d = await agoraApi('workspace/migracion/importar', {method: 'POST', body: {modulo: MIG.mod, modo, desde: l * MIG_LOTE + 1, filas: parte.map(f => f.o)}});
      r.creados += d.creados || 0; r.actualizados += d.actualizados || 0;
      for (const e of d.errores || []) { const f = filas[e.fila - 1]; const fila = f ? f.n : e.fila; r.errores.push({...e, fila}); f?.srv.push(e); }
      const pct = Math.round((l + 1) / lotes * 100); barra.style.width = pct + '%'; prog.setAttribute('aria-valuenow', pct);
    }
    if (modo === 'simular') { st.simulado = r; toast(r.errores.length ? `Simulación lista: ${int(new Set(r.errores.map(e => e.fila)).size)} filas con errores.` : 'Simulación lista: todo se puede importar.'); }
    else {
      st.informe = r;       if (MIG.mod === 'productos' && typeof CATALOGO !== 'undefined') CATALOGO = null;
      if (MIG.mod === 'tareas' && typeof DATOS !== 'undefined' && DATOS) DATOS.tareas = null;
      toast(`Carga terminada: ${int(r.creados)} creados, ${int(r.actualizados)} actualizados.`);
    }
  } catch (x) {
    if (modo !== 'simular' && (r.creados || r.actualizados)) { r.errores.push({fila: 0, campo: '', mensaje: 'La carga se detuvo: ' + x.message}); st.informe = r; }
    toast(x.message);
  }
  repaint();
}
function migInformeDescargar() {
  const st = MIG.carga, r = st.informe, titulo = Object.fromEntries(MIG.info[MIG.mod].campos.map(c => [c.clave, c.titulo]));
  const conErr = new Map(); r.errores.forEach(e => { if (!conErr.has(e.fila)) conErr.set(e.fila, []); conErr.get(e.fila).push((e.campo ? e.campo + ': ' : '') + e.mensaje); });
  const claves = [...new Set(st.mapa.filter(Boolean))];
  const hojas = [
    {nombre: 'Resumen', filtro: false, columnas: [{clave: 'a', titulo: 'Dato'}, {clave: 'b', titulo: 'Valor'}], filas: [{a: 'Archivo', b: st.archivo}, {a: 'Módulo', b: MIG.info[MIG.mod].nombre}, {a: 'Fecha', b: new Date().toLocaleString('es-VE')}, {a: 'Filas leídas', b: r.filas}, {a: 'Creados', b: r.creados}, {a: 'Actualizados', b: r.actualizados}, {a: 'Filas con error', b: conErr.size}]},
    {nombre: 'Errores', columnas: [{clave: 'fila', titulo: 'Fila'}, {clave: 'campo', titulo: 'Campo'}, {clave: 'mensaje', titulo: 'Qué pasó'}], filas: r.errores},
    {nombre: 'Filas por corregir', columnas: [{clave: '__fila', titulo: 'Fila original'}, {clave: '__err', titulo: 'Errores'}, ...claves.map(k => ({clave: k, titulo: titulo[k]}))], filas: st.filas.filter(f => conErr.has(f.n)).map(f => ({__fila: f.n, __err: conErr.get(f.n).join(' · '), ...f.o}))},
  ];
  AgoraExcel.descargar(migArchivo(MIG.mod, 'informe'), hojas);
}

/* ---------- Botón «Excel» en el encabezado de los módulos de datos ---------- */
AgoraTabla.alPintar(root => {
  const mod = MIG_DE_MOD[R.mod]; if (!mod || !esAdmin() || R.view === 'migracion') return;
  if (root.querySelector('.mg-hbtn')) return;
  const h = root.querySelector('.mod-h'); if (!h) return;
  let acts = h.querySelector(':scope > .acts'); if (!acts) { acts = document.createElement('div'); acts.className = 'acts'; h.append(acts); }
  const b = document.createElement('button'); b.type = 'button'; b.className = 'btn mg-hbtn'; b.setAttribute('aria-haspopup', 'true'); b.setAttribute('aria-expanded', 'false');
  b.innerHTML = `${icon('invoice')}Excel${icon('chev')}`; b.title = 'Descargar o cargar con Excel';
  acts.prepend(b);
  b.onclick = () => {
    const ver = mod !== 'usuarios';
    const p = openPop(b, `<p class="xt-pop-h">${esc(MODS[R.mod]?.name || '')} en Excel</p>
      <button type="button" class="mg-op" data-op="datos">${icon('download')}<span><b>Descargar datos</b><small>Todo, con tus columnas</small></span></button>
      ${ver ? `<button type="button" class="mg-op" data-op="plantilla">${icon('invoice')}<span><b>Descargar plantilla</b><small>Vacía, lista para llenar</small></span></button>
      <button type="button" class="mg-op" data-op="cargar">${icon('upload')}<span><b>Cargar desde Excel</b><small>Carga masiva con revisión</small></span></button>` : ''}
      <button type="button" class="mg-op" data-op="campos">${icon('edit')}<span><b>Ajustar columnas</b><small>Orden, nombres y campos propios</small></span></button>`, 'xt-pop mg-hpop', {align: 'right'});
    if (!p) return;
    p.onclick = async e => {
      const op = e.target.closest('[data-op]')?.dataset.op; if (!op) return; closePop();
      try {
        if (op === 'datos') { await camposDe(mod); await migDescargarDatos(mod); }
        else if (op === 'plantilla') { await camposDe(mod); await migDescargarPlantilla(mod); }
        else migracionIr(mod, op);
      } catch (x) { toast(x.message); }
    };
  };
});
/* =====================================================================================
   2.1.17 · 04j-venta-ganada · La venta ganada alimenta todo el ecosistema
   -------------------------------------------------------------------------------------
   1. Detección: Oportunidades, la ficha y la Bandeja (agora-modulos.js, que NO se toca)
      marcan "ganada" con POST workspace/opportunity/(new|edit|status) y {status:'won'}.
      Una capa sobre window.fetch ve esa respuesta y, justo después de que se confirma,
      abre el formulario "Venta ganada". (El #won-banner del HTML es otra cosa: el aviso del
      bundle para ganadas SIN monto; no se toca.)
   2. Formulario en 4 pasos (Cliente · Producto y cobro · Detalles · Resumen) con validación
      en vivo. Guarda con POST workspace/ventas: el servidor crea la venta, actualiza la
      oportunidad y el contacto, crea las tareas, descuenta la existencia y audita, todo en
      una transacción.
      Si la persona cierra sin guardar, la oportunidad SIGUE ganada (ya lo confirmó) y queda
      como "venta por completar" (POST workspace/ventas/pendiente): tarea de recordatorio,
      chip en Oportunidades → Ganadas, aviso flotante y lista en Ventas registradas.
   3. Ventas → pestaña "Ventas registradas" (el reporte mensual importado sigue igual en su pestaña).
   4. Ficha del contacto, Ganadas, Tareas, tablero del Inicio y Facturación leen las ventas.
   Evento para otros equipos (KPI): window 'agora:venta-ganada' con detail = registro venta.
   API pública: window.agoraVentaGanada(oportunidadId) abre el formulario a mano.
   ===================================================================================== */
const VG = {datos: null, mes: '', carga: null, t: 0, filtro: {estado: '', q: ''}, abiertas: new Set(), borrador: {}, avisoOculto: false, obs: null, raf: 0, contacto: {}};
const VG_ESTADO = {al_dia: ['Al día', 'sc-ok'], vencida: ['Cobro vencido', 'sc-warn'], pagada: ['Pagada', 'sc-info']};
const VG_COBRO = {recurrente: ['Recurrente', 'sc-ok', 'Se cobra cada cierto tiempo.'], unico: ['Pago único', 'sc-info', 'Un solo pago, o repartido en cuotas.'], venta: ['Venta única', 'sc-warn', 'Se vende y se cobra en el momento.']};
const VG_FREC = [['semanal', 'Semanal'], ['quincenal', 'Quincenal'], ['mensual', 'Mensual'], ['trimestral', 'Trimestral'], ['semestral', 'Semestral'], ['anual', 'Anual']];
const VG_FREC_CADA = {semanal: 'semana', quincenal: 'quincena', mensual: 'mes', trimestral: 'trimestre', semestral: 'semestre', anual: 'año'};
const VG_SIMBOLO = {USD: 'US$', EUR: '€', VES: 'Bs.'};
const vgMonto = (v, m = 'USD') => `${VG_SIMBOLO[m] || m} ${NF.format(Number(v) || 0)}`;
const vgPorMoneda = o => { const p = Object.entries(o || {}).filter(([, v]) => v > 0); return p.length ? p.map(([m, v]) => vgMonto(v, m)).join(' + ') : vgMonto(0, ESPACIO?.moneda || 'USD'); };
const vgFecha = ymd => { if (!ymd) return '—'; const [y, m, d] = String(ymd).slice(0, 10).split('-'); return `${d}/${m}/${y}`; };
const vgHoy = () => { const h = new Date(); return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`; };
const vgPuede = () => !['USUARIO', 'CONSULTA', 'VISOR'].includes((AGORA_ME && AGORA_ME.rol) || (ME && ME.rol) || 'CONSULTA');
function vgInvalidar() {
  VG.datos = null; VG.t = 0; VG.contacto = {};
  if (typeof DATOS !== 'undefined' && DATOS) DATOS.tareas = null;
  if (typeof CATALOGO !== 'undefined') CATALOGO = null;
  if (typeof TABLERO_DATOS !== 'undefined') TABLERO_DATOS = null;
}
window.addEventListener?.('agora:account-changing', () => { vgInvalidar(); VG.abiertas.clear(); VG.borrador = {}; VG.avisoOculto = false; document.getElementById?.('vg-aviso')?.remove(); });
if (typeof ORIGEN === 'object' && ORIGEN) ORIGEN.venta = 'Venta ganada';

/** Lista del mes (PERIODO) + resumen + cobros próximos + pendientes. Caché de 30 s. */
function vgCargar(force) {
  const mes = (typeof PERIODO === 'string' && PERIODO) || vgHoy().slice(0, 7);
  if (!force && VG.datos && VG.mes === mes && Date.now() - VG.t < 30000) return Promise.resolve(VG.datos);
  if (VG.carga && VG.mes === mes) return VG.carga;
  VG.mes = mes;
  VG.carga = agoraApi('workspace/ventas?mes=' + encodeURIComponent(mes)).then(d => { VG.datos = d; VG.t = Date.now(); return d; }).finally(() => { VG.carga = null; });
  return VG.carga;
}

/* ---------- 1. Detección del "ganado" (capa sobre fetch, sin tocar el bundle) ---------- */
function vgInterceptar() {
  if (typeof window === 'undefined' || typeof window.fetch !== 'function' || window.fetch.__vg) return;
  const previo = window.fetch;
  const envuelto = function (input, init) {
    const p = previo.apply(this, arguments);
    try {
      const url = typeof input === 'string' ? input : (input && input.url) || '';
      const metodo = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
      if (metodo === 'POST' && /workspace\/opportunity\/(new|edit|status)(\?|$)/.test(url) && init && typeof init.body === 'string') {
        let body = null; try { body = JSON.parse(init.body); } catch { body = null; }
        if (body && body.status === 'won') p.then(res => { if (res && res.ok) vgGanada(url, res.clone()); }).catch(() => {});
      }
    } catch { /* la capa nunca rompe una petición */ }
    return p;
  };
  envuelto.__vg = true;
  window.fetch = envuelto;
}
async function vgGanada(url, res) {
  let d = {}; try { d = await res.json(); } catch { d = {}; }
  const op = d.opportunity || null;
  const id = op?.id || new URL(url, location.href).searchParams.get('opportunityId');
  if (!id || op?.ventaId || VG.abiertas.has(id) || !vgPuede()) return;
  VG.abiertas.add(id);
  window.dispatchEvent?.(new CustomEvent('agora:oportunidad-ganada', {detail: {id, oportunidad: op}}));
  vgInvalidar();
  // El diálogo del bundle se cierra solo al confirmar; damos un respiro para no taparlo.
  setTimeout(() => vgAbrir(id, {nueva: true}), 380);
}
window.agoraVentaGanada = (oportunidadId, opts = {}) => vgAbrir(oportunidadId, {nueva: true, ...opts});
vgInterceptar();

/* ---------- 2. Formulario "Venta ganada" ---------- */
const vgPlan = (cobro, monto, frecuencia, cuotas, inicio, dia) => {
  const n = cobro === 'venta' ? 1 : Math.max(1, cuotas || 12);
  let montos = Array(n).fill(Math.round(monto * 100) / 100);
  if (cobro === 'unico' && n > 1) { const base = Math.floor(monto / n * 100) / 100; montos = Array(n).fill(base); montos[n - 1] = Math.round((monto - base * (n - 1)) * 100) / 100; }
  const meses = {mensual: 1, trimestral: 3, semestral: 6, anual: 12}[frecuencia], dias = {semanal: 7, quincenal: 15}[frecuencia];
  if (!inicio || !/^\d{4}-\d{2}-\d{2}$/.test(inicio)) return [];
  const [y, m, d] = inicio.split('-').map(Number), out = [];
  for (let i = 0; i < n; i++) {
    let f = inicio;
    if (i && dias) { const t = new Date(Date.UTC(y, m - 1, d + dias * i)); f = t.toISOString().slice(0, 10); }
    else if (i) { const t = m - 1 + (meses || 1) * i, yy = y + Math.floor(t / 12), mm = t % 12 + 1, fin = new Date(Date.UTC(yy, mm, 0)).getUTCDate(); f = `${yy}-${String(mm).padStart(2, '0')}-${String(Math.min(dia || d, fin)).padStart(2, '0')}`; }
    out.push({n: i + 1, fecha: f, monto: montos[i], estado: 'pendiente'});
  }
  return out;
};
async function vgCampos() {
  try {
    const lista = typeof camposDe === 'function' ? await camposDe('ventas') : [];
    return (Array.isArray(lista) ? lista : []).filter(c => c && c.personalizado === true && c.oculto !== true && c.activo !== false)
      .sort((a, b) => (Number(a.orden) || 0) - (Number(b.orden) || 0))
      .map(c => ({clave: String(c.clave ?? c.key ?? c.id ?? ''), nombre: String(c.nombre ?? c.name ?? c.etiqueta ?? c.clave ?? c.id ?? 'Campo'), tipo: String(c.tipo ?? c.type ?? 'texto'), opciones: Array.isArray(c.opciones) ? c.opciones : (Array.isArray(c.options) ? c.options : []), requerido: !!(c.requerido ?? c.obligatorio ?? c.required)}))
      .filter(c => /^[A-Za-z0-9_.-]{1,60}$/.test(c.clave));
  } catch { return []; }
}
function vgCampoHtml(c, valor) {
  const n = `cf__${esc(c.clave)}`, req = c.requerido ? ' <span class="req" aria-hidden="true">*</span>' : '', v = valor ?? '';
  const t = c.tipo.toLowerCase();
  if (['si_no', 'sino', 'booleano', 'boolean', 'casilla'].includes(t)) return `<label class="bx-check"><input type="checkbox" name="${n}" ${v === 'Sí' || v === true ? 'checked' : ''}> ${esc(c.nombre)}</label>`;
  if (['lista', 'opciones', 'select', 'seleccion'].includes(t) && c.opciones.length) return `<label class="fld"><span class="fld-l">${esc(c.nombre)}${req}</span><select name="${n}" ${c.requerido ? 'required' : ''}><option value="">Elige…</option>${c.opciones.map(o => { const val = typeof o === 'object' ? (o.valor ?? o.value ?? o.nombre) : o; return `<option ${String(val) === String(v) ? 'selected' : ''}>${esc(val)}</option>`; }).join('')}</select><small class="vg-err" data-err="${n}"></small></label>`;
  if (['texto_largo', 'parrafo', 'textarea', 'nota'].includes(t)) return `<label class="fld bx-wide-f"><span class="fld-l">${esc(c.nombre)}${req}</span><textarea name="${n}" rows="2" maxlength="1000" ${c.requerido ? 'required' : ''}>${esc(v)}</textarea><small class="vg-err" data-err="${n}"></small></label>`;
  const tipo = ['fecha', 'date'].includes(t) ? 'date' : ['correo', 'email'].includes(t) ? 'email' : 'text';
  const modo = ['numero', 'número', 'monto', 'number', 'moneda'].includes(t) ? ' inputmode="decimal"' : ['telefono', 'teléfono', 'phone'].includes(t) ? ' inputmode="tel"' : '';
  return `<label class="fld"><span class="fld-l">${esc(c.nombre)}${req}</span><input type="${tipo}" name="${n}"${modo} maxlength="1000" value="${esc(v)}" ${c.requerido ? 'required' : ''}><small class="vg-err" data-err="${n}"></small></label>`;
}
const VG_PASOS = [['user', 'Cliente'], ['wallet', 'Producto y cobro'], ['edit', 'Detalles'], ['check', 'Resumen']];

async function vgAbrir(oportunidadId, {nueva = false, ventaId = null} = {}) {
  if (!oportunidadId && !ventaId) return;
  const titulo = ventaId ? 'Editar venta' : 'Venta ganada';
  const s = openModal(modalHead(titulo, ventaId ? 'Cambia lo que haga falta: la oportunidad, el contacto y los cobros se actualizan solos.' : 'Completa los datos una sola vez: se guardan en el contacto, la oportunidad, Ventas, Tareas y Cobros.') +
    `<div class="vg-cargando" aria-busy="true"><div class="vg-pasos">${VG_PASOS.map(() => '<span class="sk w60"></span>').join('')}</div><div class="bx-grid"><div class="bx"><span class="sk w40"></span><span class="sk w80"></span><span class="sk w60"></span></div><div class="bx"><span class="sk w40"></span><span class="sk w80"></span><span class="sk w60"></span></div></div></div>`, {wide: true, cls: 'modal vg-modal'});
  let guardada = false, ctx, campos;
  // Cerrar sin guardar (X, Esc o "Completar después"): la oportunidad queda "venta por completar".
  const layer = s.parentNode;
  const vigia = new MutationObserver(() => {
    if (s.isConnected) return; vigia.disconnect();
    if (!guardada && nueva && ctx && !ctx.venta) vgPendiente(ctx.oportunidad, s);
  });
  if (layer) vigia.observe(layer, {childList: true});
  // Un clic fuera no borra lo escrito.
  s.addEventListener('click', e => { if (e.target === s) { e.stopPropagation(); s.querySelector('.modal')?.classList.add('vg-sacude'); setTimeout(() => s.querySelector('.modal')?.classList.remove('vg-sacude'), 400); } });
  try {
    [ctx, campos] = await Promise.all([agoraApi('workspace/ventas/contexto?' + (ventaId ? 'ventaId=' + encodeURIComponent(ventaId) : 'oportunidadId=' + encodeURIComponent(oportunidadId))), vgCampos()]);
  } catch (x) {
    if (!s.isConnected) return;
    s.querySelector('.vg-cargando').outerHTML = `<p class="empty"><b>No se pudo abrir el formulario</b>${esc(x.message)}</p><div class="bx-foot"><p class="bx-help">${nueva ? 'La oportunidad quedó ganada. Puedes completar la venta luego desde Ventas.' : ''}</p><div class="acts"><button class="btn" type="button" data-close>Cerrar</button></div></div>`;
    return;
  }
  if (!s.isConnected) return;
  if (ctx.venta && !ventaId) { nueva = false; ventaId = ctx.venta.id; s.querySelector('.modal-h h2').textContent = 'Editar venta'; }
  vgFormulario(s, ctx, campos, {ventaId, nueva, alGuardar: () => { guardada = true; }});
}

function vgFormulario(s, ctx, campos, {ventaId, nueva, alGuardar}) {
  const op = ctx.oportunidad || {}, ct = ctx.contacto || {}, v = ctx.venta, perfil = ctx.perfil || ESPACIO?.perfil || {};
  const productos = (ctx.catalogo?.productos || []).filter(p => p.activo !== false || p.id === v?.productoId);
  const rubros = ctx.catalogo?.rubros || [];
  const borr = (!v && VG.borrador[op.id]) || null;
  const prodPorNombre = productos.find(p => norm(op.name || '').includes(norm(p.nombre)));
  const p0 = v ? productos.find(p => p.id === v.productoId) : (prodPorNombre || productos[0]);
  const c = v?.cliente || {nombre: ct.name || [ct.firstName, ct.lastName].filter(Boolean).join(' ') || op.contactName || '', documento: ct.documento || '', correo: ct.email || '', telefono: ct.phone || '', direccion: [ct.address1, ct.city].filter(Boolean).join(', '), empresa: ct.companyName || ''};
  const valorOp = Number(op.monetaryValue) > 1 ? Number(op.monetaryValue) : 0;
  const ini = v || {cobro: p0?.cobro || 'recurrente', monto: p0 && !(valorOp && !prodPorNombre) ? p0.precio : (valorOp || p0?.precio || ''), moneda: p0?.moneda || ESPACIO?.moneda || 'USD', frecuencia: p0?.frecuencia || perfil.frecuencia || 'mensual', cuotas: p0?.cobro === 'recurrente' ? p0?.duracion : 1, cantidad: 1, fechaInicio: vgHoy(), diaCobro: perfil.diaCobro || '', vendedorId: op.ownerId || ctx.yo, notas: '', fecha: vgHoy()};
  const cf = Object.fromEntries((Array.isArray(v?.customFields) ? v.customFields : []).map(x => [x.clave ?? x.id, x.valor ?? x.value]));   // 2.1.17: también acepta {id,value} de Migración
  const grupos = [...rubros.map(r => [r.nombre, productos.filter(p => p.rubroId === r.id)]), ['Sin rubro', productos.filter(p => !rubros.some(r => r.id === p.rubroId))]].filter(([, l]) => l.length);
  const opcionesProd = grupos.map(([g, l]) => `<optgroup label="${esc(g)}">${l.map(p => `<option value="${esc(p.id)}" ${p.id === p0?.id ? 'selected' : ''}>${esc(p.nombre)}${p.codigo ? ' · ' + esc(p.codigo) : ''}</option>`).join('')}</optgroup>`).join('');
  const vendedores = ctx.vendedores || [];
  const cobroOp = (id) => `<label class="pfl-op"><input type="radio" name="cobro" value="${id}" ${ini.cobro === id ? 'checked' : ''}><span><b>${VG_COBRO[id][0]}</b><small>${VG_COBRO[id][2]}</small></span></label>`;
  const fld = (name, label, value, {req, type = 'text', ph = '', max = 190, modo = '', ayuda = ''} = {}) => `<label class="fld"><span class="fld-l">${label}${req ? ' <span class="req" aria-hidden="true">*</span>' : ''}</span><input name="${name}" type="${type}" maxlength="${max}" ${modo ? `inputmode="${modo}"` : ''} value="${esc(value ?? '')}" placeholder="${esc(ph)}" ${req ? 'required' : ''} autocomplete="off">${ayuda ? `<small class="vg-ayuda">${ayuda}</small>` : ''}<small class="vg-err" data-err="${name}"></small></label>`;

  const html = `<form class="bx-form vg-form" novalidate>
    <ol class="vg-pasos" role="list">${VG_PASOS.map(([ic, t], i) => `<li><button type="button" class="vg-paso ${i === 0 ? 'is-on' : ''}" data-ir="${i}" aria-current="${i === 0 ? 'step' : 'false'}"><span class="vg-paso-n">${icon(ic)}</span><span class="vg-paso-t">${t}</span></button></li>`).join('')}</ol>
    ${op.name ? `<p class="vg-op">${icon('trend')}<span>Oportunidad <b>${esc(op.name)}</b>${op.status === 'won' ? ' · <span class="sc sc-ok">Ganada</span>' : ''}${v ? ` · <span class="sc sc-info">Venta registrada el ${esc(vgFecha(v.fecha))}</span>` : ''}</span></p>` : ''}
    <section class="vg-paso-c" data-paso="0">
      <div class="bx-grid">
        <fieldset class="bx"><legend>${icon('user')}Cliente</legend>
          ${fld('nombre', 'Nombre y apellido', c.nombre, {req: true, ph: 'Como aparecerá en el contacto'})}
          <div class="bx-row">${fld('documento', 'Cédula o RIF', c.documento, {ph: 'V-12345678', max: 40})}${fld('telefono', 'Teléfono', c.telefono, {ph: '+58 414 0000000', max: 40, modo: 'tel'})}</div>
          ${fld('correo', 'Correo', c.correo, {type: 'email', ph: 'nombre@correo.com', max: 254, modo: 'email'})}
        </fieldset>
        <fieldset class="bx"><legend>${icon('home')}Dirección y empresa</legend>
          ${fld('direccion', 'Dirección', c.direccion, {max: 254, ph: 'Calle, ciudad'})}
          ${fld('empresa', 'Empresa', c.empresa, {ph: 'Opcional'})}
          <p class="bx-help">${ct.id ? `Se toman de la ficha de <b>${esc(ct.name || 'el contacto')}</b>. Lo que cambies aquí se guarda también en la ficha.` : 'Se guardan en la ficha del contacto.'}</p>
        </fieldset>
      </div>
    </section>
    <section class="vg-paso-c" data-paso="1" hidden>
      <div class="bx-grid">
        <fieldset class="bx"><legend>${icon('wallet')}Producto o servicio</legend>
          ${productos.length ? `<label class="fld"><span class="fld-l">Producto <span class="req" aria-hidden="true">*</span></span><select name="productoId" required>${opcionesProd}</select><small class="vg-err" data-err="productoId"></small></label>
          <div class="vg-prod" data-prod-info></div>` : `<p class="empty"><b>Tu catálogo está vacío</b>Crea el producto en Productos y servicios y vuelve a abrir esta venta.${typeof esAdmin === 'function' && esAdmin() ? `<br><a class="btn btn-sm" href="${href('/productos')}">${icon('plus')}Ir a Productos</a>` : ''}</p>`}
          <div class="pfl-ops pfl-1 vg-cobros" role="radiogroup" aria-label="Sistema de cobro">${cobroOp('recurrente')}${cobroOp('unico')}${cobroOp('venta')}</div>
        </fieldset>
        <fieldset class="bx"><legend>${icon('coins')}Monto y cobro</legend>
          <div class="bx-row">${fld('monto', 'Monto', ini.monto !== '' && ini.monto != null ? NF.format(Number(ini.monto)) : '', {req: true, ph: '0,00', modo: 'decimal', max: 16, ayuda: '<span data-monto-ayuda></span>'})}
            <label class="fld">Moneda<select name="moneda">${['USD', 'VES', 'EUR'].map(m => `<option ${m === ini.moneda ? 'selected' : ''}>${m}</option>`).join('')}</select></label></div>
          ${valorOp ? `<p class="vg-mini">Valor de la oportunidad: <b>${esc(vgMonto(valorOp, ini.moneda))}</b> <button type="button" class="lnk-btn" data-usar-valor>Usar este valor</button></p>` : ''}
          <div class="bx-row" data-si="periodo"><label class="fld">Frecuencia<select name="frecuencia">${VG_FREC.map(([id, t]) => `<option value="${id}" ${(ini.frecuencia || 'mensual') === id ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
            ${fld('cuotas', '<span data-cuotas-l>Número de cobros</span>', ini.cuotas ?? '', {modo: 'numeric', max: 3, ph: 'Vacío = sin fin'})}</div>
          <div class="bx-row" data-si="venta">${fld('cantidad', 'Cantidad', ini.cantidad || 1, {modo: 'numeric', max: 6})}<span class="vg-stock" data-stock></span></div>
          <div class="bx-row">${fld('fechaInicio', 'Fecha de inicio', ini.fechaInicio, {req: true, type: 'date', max: 10})}${fld('diaCobro', 'Día de cobro', ini.diaCobro ?? '', {modo: 'numeric', max: 2, ph: '1 a 28', ayuda: perfil.diaCobro ? `El de la subcuenta es el ${esc(perfil.diaCobro)}.` : ''})}</div>
          <p class="prod-eq vg-eq" data-eq role="status"></p>
        </fieldset>
      </div>
    </section>
    <section class="vg-paso-c" data-paso="2" hidden>
      <div class="bx-grid">
        <fieldset class="bx"><legend>${icon('team')}Vendedor y notas</legend>
          <div class="bx-row"><label class="fld">Vendedor<select name="vendedorId" ${ctx.puedeCambiarVendedor ? '' : 'disabled'}>${vendedores.map(u => `<option value="${esc(u.id)}" ${String(u.id) === String(ini.vendedorId) ? 'selected' : ''}>${esc(u.name)}</option>`).join('') || '<option value="">Tú</option>'}</select>${ctx.puedeCambiarVendedor ? '' : '<small class="vg-ayuda">Es quien tiene la oportunidad.</small>'}</label>
            ${fld('fecha', 'Fecha de la venta', ini.fecha || vgHoy(), {type: 'date', max: 10, req: true})}</div>
          <label class="fld">Notas<textarea name="notas" rows="3" maxlength="2000" placeholder="Acuerdos, condiciones especiales, cómo pagará…">${esc(ini.notas || '')}</textarea></label>
        </fieldset>
        <fieldset class="bx"><legend>${icon('split')}Campos de la venta</legend>
          ${campos.length ? `<div class="vg-campos">${campos.map(x => vgCampoHtml(x, cf[x.clave])).join('')}</div>` : `<p class="bx-help">Esta subcuenta no tiene campos propios para ventas.${typeof esAdmin === 'function' && esAdmin() ? ' Puedes crearlos en Ajustes → Campos.' : ''}</p>`}
        </fieldset>
      </div>
    </section>
    <section class="vg-paso-c" data-paso="3" hidden><div data-resumen></div></section>
    <p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help" data-pie>${nueva ? 'Si lo dejas para después, la oportunidad sigue ganada y te queda una tarea para completarla.' : 'Los cobros ya pagados se conservan.'}</p>
      <div class="acts"><button class="btn" type="button" data-close>${nueva ? 'Completar después' : 'Cancelar'}</button><button class="btn" type="button" data-atras hidden>Atrás</button><button class="btn btn-cta" type="button" data-siguiente>Siguiente</button><button class="btn btn-cta" type="submit" data-guardar hidden>${ventaId ? 'Guardar cambios' : 'Registrar venta'}</button></div></div>
  </form>`;
  s.querySelector('.vg-cargando').outerHTML = html;
  const f = s.querySelector('form'), err = s.querySelector('.fld-err');
  if (borr) for (const [k, val] of Object.entries(borr)) { const el = f.elements.namedItem(k); if (!el) continue; if (el instanceof RadioNodeList) { [...el].forEach(r => { r.checked = r.value === val; }); } else if (el.type === 'checkbox') el.checked = !!val; else el.value = val; }
  let paso = 0;
  const val = n => String(f.elements.namedItem(n)?.value ?? '').trim();
  const cobro = () => f.querySelector('[name=cobro]:checked')?.value || 'recurrente';
  const prod = () => productos.find(p => p.id === val('productoId'));
  const leerMonto = t => { const r = agenciaLeerMonto(t); return r === null ? null : Number(r); };
  const cuotasN = () => { const q = val('cuotas'); return q === '' ? null : Number(q); };
  const ponErr = (name, msg) => { const e = f.querySelector(`[data-err="${name}"]`); if (e) e.textContent = msg || ''; f.elements.namedItem(name)?.setAttribute?.('aria-invalid', msg ? 'true' : 'false'); return !msg; };
  // Validación por campo (se muestra en vivo después del primer toque).
  const REGLAS = {
    nombre: () => val('nombre').length < 3 ? 'Escribe el nombre del cliente.' : '',
    documento: () => val('documento') && !/^[A-Za-z0-9 ./-]{3,40}$/.test(val('documento')) ? 'Usa letras, números, punto o guion. Ej.: V-12345678.' : '',
    correo: () => val('correo') && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val('correo')) ? 'Revisa el correo.' : '',
    telefono: () => val('telefono') && !/^[0-9+() .-]{7,40}$/.test(val('telefono')) ? 'Revisa el teléfono: solo números, espacios, +, guion y paréntesis.' : '',
    productoId: () => !prod() ? 'Elige un producto del catálogo.' : '',
    monto: () => { const m = leerMonto(val('monto')); return !val('monto') || m === null || !(m > 0) ? 'Escribe un monto mayor que cero. Usa coma para los decimales.' : ''; },
    cuotas: () => { const c = cobro(), q = val('cuotas'); if (c === 'venta') return ''; if (c === 'unico' && q === '') return ''; return q !== '' && !(/^\d{1,3}$/.test(q) && +q >= 1 && +q <= (c === 'unico' ? 120 : 600)) ? `Debe ser un número entero entre 1 y ${c === 'unico' ? 120 : 600}.` : ''; },
    cantidad: () => { if (cobro() !== 'venta') return ''; const q = val('cantidad'); if (!/^\d{1,6}$/.test(q) || +q < 1) return 'La cantidad debe ser 1 o más.'; const p = prod(); return p && p.cobro === 'venta' && p.stock != null && +q > Number(p.stock) + (v && v.productoId === p.id ? Number(v.cantidad || 0) : 0) ? `Solo quedan ${p.stock} en existencia.` : ''; },
    fechaInicio: () => !/^\d{4}-\d{2}-\d{2}$/.test(val('fechaInicio')) ? 'Elige la fecha de inicio.' : '',
    diaCobro: () => val('diaCobro') && !(/^\d{1,2}$/.test(val('diaCobro')) && +val('diaCobro') >= 1 && +val('diaCobro') <= 28) ? 'El día de cobro va del 1 al 28.' : '',
    fecha: () => !/^\d{4}-\d{2}-\d{2}$/.test(val('fecha')) ? 'Elige la fecha de la venta.' : val('fecha') > vgHoy() ? 'La fecha de la venta no puede ser futura.' : '',
  };
  for (const x of campos) if (x.requerido) REGLAS['cf__' + x.clave] = () => { const el = f.elements.namedItem('cf__' + x.clave); return el && el.type !== 'checkbox' && !String(el.value).trim() ? `Completa ${x.nombre}.` : ''; };
  const PASO_CAMPOS = [['nombre', 'documento', 'correo', 'telefono'], ['productoId', 'monto', 'cuotas', 'cantidad', 'fechaInicio', 'diaCobro'], ['fecha', ...campos.filter(x => x.requerido).map(x => 'cf__' + x.clave)], []];
  const tocados = new Set();
  const validar = (lista, mostrar = true) => { let ok = true; for (const n of lista) { const m = REGLAS[n]?.() || ''; if (m) ok = false; if (mostrar || tocados.has(n)) ponErr(n, m); } return ok; };
  const plan = () => { const m = leerMonto(val('monto')) || 0, c = cobro(); return vgPlan(c, m, c === 'venta' ? '' : val('frecuencia'), c === 'recurrente' ? (cuotasN() ?? 12) : c === 'unico' ? (cuotasN() || 1) : 1, val('fechaInicio'), val('diaCobro') ? +val('diaCobro') : null); };
  const sync = () => {
    const c = cobro(), p = prod(), mon = val('moneda'), q = cuotasN();
    f.querySelectorAll('[data-si]').forEach(x => { x.hidden = x.dataset.si === 'venta' ? c !== 'venta' : c === 'venta'; });
    const ql = f.querySelector('[data-cuotas-l]'); if (ql) ql.textContent = c === 'unico' ? 'Cuotas' : 'Número de cobros';
    const qi = f.elements.namedItem('cuotas'); if (qi) qi.placeholder = c === 'unico' ? '1 = de contado' : 'Vacío = sin fin';
    const fr = f.elements.namedItem('frecuencia'); if (fr) fr.closest('label').hidden = c === 'unico' && (q || 1) < 2;
    const info = f.querySelector('[data-prod-info]');
    if (info) info.innerHTML = p ? `<span class="sc ${VG_COBRO[p.cobro]?.[1] || 'sc-gray'}">${esc(VG_COBRO[p.cobro]?.[0] || p.cobro)}</span><span>${esc(vgMonto(p.precio, p.moneda))}${p.cobro === 'recurrente' ? ' / ' + esc(VG_FREC_CADA[p.frecuencia] || 'mes') : ''}${p.cobro === 'recurrente' && p.duracion ? ` · ${esc(p.duracion)} cobros` : ''}</span>${p.cobro === 'venta' && p.stock != null ? `<span class="${p.stock < 1 ? 'vg-bad' : ''}">Existencia: ${esc(p.stock)}</span>` : ''}${p.descripcion ? `<small>${esc(p.descripcion)}</small>` : ''}` : '';
    const st = f.querySelector('[data-stock]'); if (st) st.textContent = p && p.cobro === 'venta' && p.stock != null ? `Quedan ${p.stock}` : 'Sin control de existencia';
    const ma = f.querySelector('[data-monto-ayuda]'); if (ma) ma.textContent = c === 'recurrente' ? `Lo que paga cada ${VG_FREC_CADA[val('frecuencia')] || 'mes'}.` : c === 'unico' ? 'El total; si hay cuotas, se reparte.' : 'El total de la venta.';
    const pl = plan(), eq = f.querySelector('[data-eq]'), m = leerMonto(val('monto'));
    if (eq) eq.textContent = !(m > 0) ? 'Escribe el monto para ver el plan de cobros.' : !pl.length ? 'Elige la fecha de inicio.'
      : c === 'recurrente' ? (q ? `${q} cobros de ${vgMonto(m, mon)} · total ${vgMonto(m * q, mon)} · el último el ${vgFecha(pl[pl.length - 1].fecha)}.` : `Cobro ${(VG_FREC.find(x => x[0] === val('frecuencia'))?.[1] || '').toLowerCase()} de ${vgMonto(m, mon)} sin fecha de fin · primero el ${vgFecha(pl[0].fecha)}.`)
      : c === 'unico' ? (pl.length > 1 ? `${pl.length} cuotas de ${vgMonto(pl[0].monto, mon)} · total ${vgMonto(m, mon)}.` : `Un solo pago de ${vgMonto(m, mon)} el ${vgFecha(pl[0].fecha)}.`)
      : `Se cobra ${vgMonto(m, mon)} el ${vgFecha(pl[0].fecha)}.`;
  };
  const resumen = () => {
    const c = cobro(), p = prod(), pl = plan(), mon = val('moneda'), m = leerMonto(val('monto')) || 0, q = cuotasN();
    const vend = vendedores.find(u => String(u.id) === val('vendedorId'))?.name || 'Tú';
    const tareasN = 1 + Math.max(1, pl.filter(x => x.fecha <= new Date(Date.now() + 31 * 864e5).toISOString().slice(0, 10)).length);
    const cambios = [['nombre', 'name', 'nombre'], ['correo', 'email', 'correo'], ['telefono', 'phone', 'teléfono'], ['documento', 'documento', 'documento'], ['direccion', 'address1', 'dirección'], ['empresa', 'companyName', 'empresa']].filter(([k, ck]) => val(k) && val(k) !== String(ct[ck] || '')).map(x => x[2]);
    const stock = p && c === 'venta' && p.stock != null ? `${p.stock} → ${Math.max(0, p.stock - (+val('cantidad') || 1) + (v && v.productoId === p.id && v.cobro === 'venta' ? Number(v.cantidad || 0) : 0))}` : '';
    const item = (ic, t, d) => `<li><span class="vg-r-ic" aria-hidden="true">${icon(ic)}</span><div><b>${t}</b><small>${d}</small></div></li>`;
    return `<div class="vg-resumen">
      <div class="vg-r-total"><span>${esc(p?.nombre || 'Producto')}</span><strong>${esc(vgMonto(m, mon))}${c === 'recurrente' ? `<small> / ${esc(VG_FREC_CADA[val('frecuencia')] || 'mes')}</small>` : ''}</strong><span class="sc ${VG_COBRO[c][1]}">${VG_COBRO[c][0]}</span><small>${esc(val('nombre'))} · vende ${esc(vend)}</small></div>
      <div><p class="vg-r-l">Al ${ventaId ? 'guardar' : 'registrar'} se ${ventaId ? 'actualiza' : 'crea'}:</p><ul class="vg-r-lista">
        ${item('receipt', ventaId ? 'La venta' : 'Una venta en Ventas registradas', `${pl.length}${c === 'recurrente' && !q ? '+' : ''} ${pl.length === 1 ? 'cobro' : 'cobros'} · ${c === 'recurrente' && !q ? 'sin fecha de fin' : 'total ' + esc(vgMonto(pl.reduce((t, x) => t + x.monto, 0), mon))}`)}
        ${item('trend', 'La oportunidad', `Queda ganada con valor ${esc(vgMonto(m, mon))} y la marca "Venta registrada".`)}
        ${item('user', 'El contacto', cambios.length ? `Se actualiza: ${esc(cambios.join(', '))}. Lleva la etiqueta "cliente".` : 'Lleva la etiqueta "cliente" y los campos de la venta.')}
        ${ventaId ? '' : item('check', 'Tareas de seguimiento', `${tareasN}: dar la bienvenida y ${tareasN - 1 === 1 ? 'el primer cobro' : `los ${tareasN - 1} cobros del próximo mes`}, para ${esc(vend)}.`)}
        ${stock ? item('wallet', 'Existencia del producto', esc(stock)) : ''}
      </ul></div>
      <div class="vg-r-plan"><p class="vg-r-l">Plan de cobros</p><div class="tw"><table><thead><tr><th>N.º</th><th>Fecha</th><th class="n">Monto</th></tr></thead><tbody>${pl.slice(0, 6).map(x => `<tr><td>${x.n}</td><td>${esc(vgFecha(x.fecha))}</td><td class="n">${esc(vgMonto(x.monto, mon))}</td></tr>`).join('')}</tbody></table></div>${pl.length > 6 ? `<small class="vg-ayuda">Y ${pl.length - 6} cobros más.</small>` : ''}</div>
    </div>`;
  };
  const ir = n => {
    if (n > paso && !validar(PASO_CAMPOS[paso])) { err.textContent = 'Revisa los campos marcados antes de seguir.'; return; }
    if (n === 3) for (let i = 0; i < 3; i++) if (!validar(PASO_CAMPOS[i])) { paso = i; err.textContent = 'Falta revisar algo en este paso.'; return ir(i); }
    err.textContent = ''; paso = n;
    f.querySelectorAll('[data-paso]').forEach(x => { x.hidden = +x.dataset.paso !== n; });
    f.querySelectorAll('[data-ir]').forEach(b => { const i = +b.dataset.ir; b.classList.toggle('is-on', i === n); b.classList.toggle('is-done', i < n); b.setAttribute('aria-current', i === n ? 'step' : 'false'); });
    f.querySelector('[data-atras]').hidden = n === 0; f.querySelector('[data-siguiente]').hidden = n === 3; f.querySelector('[data-guardar]').hidden = n !== 3;
    if (n === 3) f.querySelector('[data-resumen]').innerHTML = resumen();
    (f.querySelector(`[data-paso="${n}"] input:not([type=radio]):not([disabled]), [data-paso="${n}"] select`) || f.querySelector('[data-guardar]:not([hidden])'))?.focus({preventScroll: true});
    f.querySelector(`[data-paso="${n}"]`)?.scrollIntoView?.({block: 'nearest'});
  };
  f.querySelectorAll('[data-ir]').forEach(b => b.onclick = () => ir(+b.dataset.ir));
  f.querySelector('[data-siguiente]').onclick = () => ir(paso + 1);
  f.querySelector('[data-atras]').onclick = () => ir(paso - 1);
  f.querySelector('[data-usar-valor]')?.addEventListener('click', () => { f.elements.namedItem('monto').value = NF.format(valorOp); sync(); validar(['monto']); });
  f.elements.namedItem('productoId')?.addEventListener('change', () => {
    const p = prod(); if (!p) return;
    f.elements.namedItem('monto').value = NF.format(Number(p.precio) * (p.cobro === 'venta' ? (+val('cantidad') || 1) : 1));
    f.elements.namedItem('moneda').value = p.moneda || 'USD';
    f.querySelectorAll('[name=cobro]').forEach(r => { r.checked = r.value === p.cobro; });
    if (p.frecuencia) f.elements.namedItem('frecuencia').value = p.frecuencia;
    f.elements.namedItem('cuotas').value = p.cobro === 'recurrente' ? (p.duracion || '') : 1;
  });
  f.addEventListener('input', e => { if (e.target.name) tocados.add(e.target.name); sync(); validar([...tocados], false); });
  f.addEventListener('change', e => { if (e.target.name) tocados.add(e.target.name); sync(); validar([...tocados], false); });
  f.addEventListener('focusout', e => { const n = e.target.name; if (n && REGLAS[n]) { tocados.add(n); validar([n]); } });
  // Enter no envía a mitad del formulario: avanza.
  f.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && paso < 3) { e.preventDefault(); ir(paso + 1); } });
  // Borrador en memoria por si la persona cierra y vuelve.
  const guardarBorrador = () => { if (v) return; const o = {}; for (const el of f.elements) if (el.name) o[el.name] = el.type === 'checkbox' ? el.checked : el.type === 'radio' ? (el.checked ? el.value : o[el.name]) : el.value; VG.borrador[op.id] = o; };
  f.addEventListener('input', guardarBorrador); f.addEventListener('change', guardarBorrador);
  sync();
  f.onsubmit = async e => {
    e.preventDefault(); err.textContent = '';
    if (paso !== 3) { ir(paso + 1); return; }
    for (let i = 0; i < 3; i++) if (!validar(PASO_CAMPOS[i])) { ir(i); err.textContent = 'Falta revisar algo en este paso.'; return; }
    const c = cobro();
    const body = {
      oportunidadId: op.id, contactoId: ct.id || op.contactId || '',
      cliente: {nombre: val('nombre'), documento: val('documento'), correo: val('correo'), telefono: val('telefono'), direccion: val('direccion'), empresa: val('empresa')},
      productoId: val('productoId'), cobro: c, monto: leerMonto(val('monto')), moneda: val('moneda'),
      frecuencia: c === 'venta' ? null : val('frecuencia'), cuotas: c === 'venta' ? 1 : (c === 'unico' ? (cuotasN() || 1) : cuotasN()),
      cantidad: c === 'venta' ? +val('cantidad') || 1 : 1, fechaInicio: val('fechaInicio'), diaCobro: val('diaCobro') ? +val('diaCobro') : null,
      vendedorId: val('vendedorId') ? +val('vendedorId') || val('vendedorId') : null, fecha: val('fecha'), notas: val('notas'),
      customFields: campos.map(x => { const el = f.elements.namedItem('cf__' + x.clave); return {clave: x.clave, nombre: x.nombre, valor: el ? (el.type === 'checkbox' ? (el.checked ? 'Sí' : 'No') : String(el.value).trim()) : ''}; }),
    };
    if (ventaId) { body.id = ventaId; body.version = v?.version; }
    const b = f.querySelector('[data-guardar]'); busy(b, true, ventaId ? 'Guardando…' : 'Registrando…');
    try {
      const r = await agoraApi('workspace/ventas', {method: ventaId ? 'PUT' : 'POST', body});
      alGuardar(); delete VG.borrador[op.id]; vgInvalidar();
      closeModal();
      toast(ventaId ? 'Venta actualizada en todo el sistema.' : `Venta registrada: ${vgMonto(r.venta.monto, r.venta.moneda)}. Ya está en el contacto, Tareas y Ventas.`);
      // Para los indicadores (KPI) y cualquier módulo que quiera refrescarse.
      window.dispatchEvent?.(new CustomEvent('agora:venta-ganada', {detail: r.venta}));
      window.dispatchEvent?.(new Event('agora:opportunity-edited'));
      vgRefrescar();
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
/** La persona cerró el formulario sin guardar: la oportunidad queda ganada y "venta por completar". */
async function vgPendiente(op) {
  if (!op?.id) return;
  try {
    await agoraApi('workspace/ventas/pendiente', {method: 'POST', body: {oportunidadId: op.id}});
    vgInvalidar(); VG.avisoOculto = false;
    toast('La venta quedó por completar. Te dejamos una tarea para terminarla.');
    vgRefrescar();
  } catch { /* si falla, la oportunidad igual quedó ganada; el aviso se recalcula luego */ }
}
function vgRefrescar() {
  if (typeof R === 'undefined' || !R) return;
  if (['tc_ventas', 'tareas', 'tc_facturas', 'centro'].includes(R.view)) { if (R.view === 'tareas') { cargarTareas(true).then(() => repaint()).catch(() => {}); } else vgCargar(true).then(() => repaint()).catch(() => repaint()); }
  else vgVigilar(true);
}

/* ---------- 3. Ventas → pestaña "Ventas registradas" ---------- */
function vgDetalle(id) {
  const v = VG.datos?.items?.find(x => x.id === id); if (!v) return;
  const puede = VG.datos.canEdit;
  const s = openModal(modalHead(esc(v.cliente?.nombre || 'Venta'), `${esc(v.producto)} · ${esc(VG_COBRO[v.cobro]?.[0] || v.cobro)} · vendida el ${esc(vgFecha(v.fecha))}`) + `<div class="vg-det">
    <div class="vg-det-datos">${kv([['Monto', esc(vgMonto(v.monto, v.moneda)) + (v.cobro === 'recurrente' ? ` / ${esc(VG_FREC_CADA[v.frecuencia] || 'mes')}` : '')], ['Total', v.sinFin ? 'Sin fecha de fin' : esc(vgMonto(v.total, v.moneda))], ['Documento', esc(v.cliente?.documento || '—')], ['Correo', esc(v.cliente?.correo || '—')], ['Teléfono', esc(v.cliente?.telefono || '—')], ['Vendedor', esc(v.vendedor || '—')], ['Día de cobro', v.diaCobro ? esc(v.diaCobro) : '—'], ...(v.customFields || []).map(x => [esc(x.nombre), esc(x.valor || '—')]), ['Notas', esc(v.notas || '—')]])}</div>
    <div class="vg-det-plan"><p class="vg-r-l">Plan de cobros</p><div class="tw"><table><thead><tr><th>N.º</th><th>Fecha</th><th class="n">Monto</th><th>Estado</th></tr></thead><tbody>${(v.plan || []).map(c => { const venc = c.estado !== 'pagado' && c.fecha < vgHoy(); return `<tr><td>${c.n}</td><td>${esc(vgFecha(c.fecha))}</td><td class="n">${esc(vgMonto(c.monto, v.moneda))}</td><td>${puede ? `<button type="button" class="sc ${c.estado === 'pagado' ? 'sc-ok' : venc ? 'sc-warn' : 'sc-gray'} vg-cobro-b" data-cobro="${c.n}" data-pagado="${c.estado === 'pagado' ? 0 : 1}" title="${c.estado === 'pagado' ? 'Marcar como pendiente' : 'Marcar como pagado'}">${c.estado === 'pagado' ? 'Pagado' : venc ? 'Vencido' : 'Pendiente'}</button>` : `<span class="sc ${c.estado === 'pagado' ? 'sc-ok' : 'sc-gray'}">${c.estado === 'pagado' ? 'Pagado' : 'Pendiente'}</span>`}</td></tr>`; }).join('')}</tbody></table></div><p class="bx-help">${puede ? 'Toca el estado de un cobro para marcarlo pagado o pendiente. Su tarea se cierra sola.' : ''}</p></div>
  </div><div class="bx-foot"><p class="bx-help"></p><div class="acts"><button class="btn" type="button" data-close>Cerrar</button>${puede ? `<button class="btn btn-cta" type="button" data-editar>${icon('edit')}Editar venta</button>` : ''}</div></div>`, {wide: true});
  s.querySelector('[data-editar]')?.addEventListener('click', () => vgAbrir(null, {ventaId: v.id}));
  s.querySelectorAll('[data-cobro]').forEach(b => b.onclick = async () => {
    busy(b, true, '…');
    try { await agoraApi('workspace/ventas/cobro', {method: 'POST', body: {ventaId: v.id, n: +b.dataset.cobro, pagado: b.dataset.pagado === '1'}}); vgInvalidar(); await vgCargar(true); vgDetalle(v.id); repaint(); toast(b.dataset.pagado === '1' ? 'Cobro marcado como pagado.' : 'Cobro marcado como pendiente.'); }
    catch (x) { busy(b, false); toast(x.message); }
  });
}
function vgCsv() {
  const items = VG.datos?.items || []; if (!items.length) { toast('No hay ventas para exportar en este mes.'); return; }
  const seguro = x => { let t = String(x ?? ''); if (/^[=+\-@\t\r]/.test(t)) t = "'" + t; return /[";\n,]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
  const filas = [['Fecha', 'Cliente', 'Documento', 'Correo', 'Teléfono', 'Producto', 'Rubro', 'Cobro', 'Frecuencia', 'Cuotas', 'Monto', 'Moneda', 'Total', 'Inicio', 'Vendedor', 'Estado', 'Notas']]
    .concat(items.map(v => [v.fecha, v.cliente?.nombre, v.cliente?.documento, v.cliente?.correo, v.cliente?.telefono, v.producto, v.rubro, VG_COBRO[v.cobro]?.[0] || v.cobro, v.frecuencia || '', v.cuotas ?? (v.sinFin ? 'sin fin' : ''), v.monto, v.moneda, v.sinFin ? '' : v.total, v.fechaInicio, v.vendedor, VG_ESTADO[v.estado]?.[0] || v.estado, v.notas]));
  const blob = new Blob(['﻿' + filas.map(r => r.map(seguro).join(';')).join('\r\n')], {type: 'text/csv;charset=utf-8'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `ventas-registradas-${VG.mes}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}
function vgRegistradasHtml() {
  const d = VG.datos;
  const acts = d?.items?.length ? `<button class="btn" type="button" data-vg-csv>${icon('download')}Exportar CSV</button>` : '';
  const cabeza = head('Ventas', 'Ventas registradas', 'Lo que se vende al ganar una oportunidad: cliente, producto, cobros y vendedor, en un solo registro.', acts);
  if (!d) return cabeza + loading();
  const r = d.resumen || {}, mes = typeof mesNombre === 'function' ? mesNombre(VG.mes) : VG.mes;
  const f = VG.filtro, q = norm(f.q || '');
  const items = d.items.filter(v => (!f.estado || v.estado === f.estado) && (!q || norm(`${v.cliente?.nombre} ${v.cliente?.documento} ${v.producto} ${v.vendedor}`).includes(q)));
  const tarjeta = (cls, ic, t, val, det) => `<div class="ag-resumen ${cls}"><span class="ag-resumen-ic" aria-hidden="true">${icon(ic)}</span><span class="ag-resumen-t">${t}</span><strong class="ag-resumen-v">${val}</strong><span class="ag-resumen-d">${det}</span></div>`;
  const venc = Object.values(r.vencido || {}).some(x => x > 0);
  const kpis = `<section class="ag-resumen-grid vg-kpis" aria-label="Resumen de ${esc(mes)}">
    ${tarjeta('', 'receipt', 'Ventas del mes', int(r.cantidad || 0), esc(mes))}
    ${tarjeta('is-ok', 'coins', 'Vendido', esc(vgPorMoneda(r.vendido)), 'Primer cobro o valor de cada venta')}
    ${tarjeta('', 'check', 'Cobrado en el mes', esc(vgPorMoneda(r.cobrado)), 'Cobros marcados como pagados')}
    ${tarjeta(venc ? 'is-warn' : '', 'clock', 'Por cobrar en el mes', esc(vgPorMoneda(r.porCobrar)), venc ? `Vencido: ${esc(vgPorMoneda(r.vencido))}` : 'Nada vencido')}
  </section>`;
  const pend = (d.pendientes || []).length ? `<section class="panel vg-pend">${secH('warn', `${d.pendientes.length === 1 ? 'Una venta ganada' : d.pendientes.length + ' ventas ganadas'} por completar`, 'Se ganaron pero falta el formulario de venta. Complétalas para que lleguen al contacto, Tareas y Cobros.')}
    <ul class="vg-pend-l">${d.pendientes.slice(0, 8).map(p => `<li><span><b>${esc(p.name)}</b><small>Ganada el ${esc(vgFecha(p.wonAt))}${p.monetaryValue ? ' · ' + esc(vgMonto(p.monetaryValue, ESPACIO?.moneda || 'USD')) : ''}</small></span>${d.canEdit ? `<button class="btn btn-sm btn-tint" type="button" data-vg-completar="${esc(p.id)}">Completar venta</button>` : ''}</li>`).join('')}</ul></section>` : '';
  const chip = (k, t, n) => `<button type="button" class="agency-chip-btn ${f.estado === k ? 'is-active' : ''}" data-vg-f="${k}" aria-pressed="${f.estado === k}">${t}${n != null ? ` · ${n}` : ''}</button>`;
  const cols = [
    {k: 'fecha', l: 'Fecha', f: v => `<span title="${esc(vgFecha(v))}">${esc(vgFecha(v).slice(0, 5))}</span>`},
    {k: 'cliente', l: 'Cliente', f: (c, v) => `<span class="who-cell"><b>${esc(c?.nombre || '—')}</b><small>${esc(c?.documento || c?.correo || c?.telefono || '')}</small></span>`},
    {k: 'producto', l: 'Producto', f: (p, v) => `<span class="who-cell"><b>${esc(p)}</b><small>${esc([v.rubro, v.vendedor ? 'vende ' + v.vendedor : ''].filter(Boolean).join(' · '))}</small></span>`},
    {k: 'cobro', l: 'Cobro', f: (c, v) => `<span class="sc ${VG_COBRO[c]?.[1] || 'sc-gray'}">${esc(VG_COBRO[c]?.[0] || c)}</span><small class="vg-sub">${c === 'recurrente' ? esc((VG_FREC.find(x => x[0] === v.frecuencia)?.[1] || '') + (v.cuotas ? ` · ${v.cuotas} cobros` : ' · sin fin')) : c === 'unico' && v.cuotas > 1 ? `${v.cuotas} cuotas` : c === 'venta' && v.cantidad > 1 ? `${v.cantidad} unidades` : ''}</small>`},
    {k: 'monto', l: 'Monto', n: 1, f: (m, v) => esc(vgMonto(m, v.moneda))},
    {k: 'estado', l: 'Estado', f: x => `<span class="sc ${VG_ESTADO[x]?.[1] || 'sc-gray'}">${esc(VG_ESTADO[x]?.[0] || x)}</span>`},
    {k: 'id', l: '', f: id => `<span class="vg-acc"><button class="icon-btn" type="button" data-vg-ver="${esc(id)}" aria-label="Ver cobros" title="Ver cobros">${icon('receipt')}</button>${d.canEdit ? `<button class="icon-btn" type="button" data-vg-editar="${esc(id)}" aria-label="Editar venta" title="Editar venta">${icon('edit')}</button>` : ''}</span>`},
  ];
  const vacio = d.items.length ? '<b>No hay ventas con estos filtros</b>Prueba con otro estado o quita la búsqueda.' : `<b>Todavía no hay ventas registradas en ${esc(mes)}</b>Cuando ganes una oportunidad, se abre el formulario de venta y la venta aparece aquí.`;
  const tabla = `<section class="panel pf-sec vg-tabla">${secH('receipt', 'Ventas de ' + esc(mes), `${int(items.length)} ${items.length === 1 ? 'venta' : 'ventas'} en la vista`)}
    <div class="vg-tools"><div class="agency-chips" role="group" aria-label="Estado">${chip('', 'Todas', d.items.length)}${chip('al_dia', 'Al día', d.items.filter(v => v.estado === 'al_dia').length)}${chip('vencida', 'Cobro vencido', d.items.filter(v => v.estado === 'vencida').length)}${chip('pagada', 'Pagadas', d.items.filter(v => v.estado === 'pagada').length)}</div>
      <label class="fld grow vg-buscar"><span class="sr-only">Buscar</span><span class="search-in">${icon('search')}<input type="search" data-vg-q value="${esc(f.q || '')}" placeholder="Cliente, documento, producto o vendedor" autocomplete="off"></span></label></div>
    ${table(cols, items, {empty: vacio})}</section>`;
  const prox = d.proximos || [];
  const cobros = `<section class="panel pf-sec vg-prox">${secH('clock', 'Cobros próximos', 'Lo que vence en los próximos 31 días y lo atrasado.')}
    ${prox.length ? `<ul class="vg-prox-l">${prox.slice(0, 12).map(c => `<li class="${c.vencido ? 'is-venc' : ''}"><span class="vg-prox-f"><b>${esc(vgFecha(c.fecha).slice(0, 5))}</b><small>${c.vencido ? 'Vencido' : 'Vence'}</small></span><span class="vg-prox-t"><b>${esc(c.cliente)}</b><small>${esc(c.producto)}${c.de > 1 ? ` · cobro ${c.n}${c.sinFin ? '' : ' de ' + c.de}` : ''}</small></span><strong>${esc(vgMonto(c.monto, c.moneda))}</strong>${d.canEdit ? `<button class="icon-btn" type="button" data-vg-pagar="${esc(c.ventaId)}" data-n="${c.n}" aria-label="Marcar pagado" title="Marcar pagado">${icon('check')}</button>` : ''}</li>`).join('')}</ul>` : '<p class="empty"><b>Sin cobros próximos</b>Cuando registres ventas, sus cobros aparecen aquí.</p>'}</section>`;
  return cabeza + kpis + pend + `<div class="vg-cols">${tabla}${cobros}</div>`;
}
function vgRegistradasMontar(root) {
  root.querySelector('[data-vg-csv]')?.addEventListener('click', vgCsv);
  root.querySelectorAll('[data-vg-f]').forEach(b => b.onclick = () => { VG.filtro.estado = b.dataset.vgF; repaint(); });
  const qi = root.querySelector('[data-vg-q]');
  if (qi) qi.oninput = () => { VG.filtro.q = qi.value; clearTimeout(VG.qt); VG.qt = setTimeout(() => { repaint(); const n = $('[data-vg-q]'); if (n) { n.focus(); n.setSelectionRange(n.value.length, n.value.length); } }, 220); };
  root.querySelectorAll('[data-vg-ver]').forEach(b => b.onclick = () => vgDetalle(b.dataset.vgVer));
  root.querySelectorAll('[data-vg-editar]').forEach(b => b.onclick = () => vgAbrir(null, {ventaId: b.dataset.vgEditar}));
  root.querySelectorAll('[data-vg-completar]').forEach(b => b.onclick = () => vgAbrir(b.dataset.vgCompletar, {nueva: true}));
  root.querySelectorAll('[data-vg-pagar]').forEach(b => b.onclick = async () => {
    busy(b, true, '');
    try { await agoraApi('workspace/ventas/cobro', {method: 'POST', body: {ventaId: b.dataset.vgPagar, n: +b.dataset.n, pagado: true}}); vgInvalidar(); await vgCargar(true); repaint(); toast('Cobro marcado como pagado.'); }
    catch (x) { busy(b, false); toast(x.message); }
  });
}
if (typeof V === 'object' && V.tc_ventas) {
  const base = V.tc_ventas;
  const tab = () => { let t = ''; try { t = store.get('vg.tab', ''); } catch { t = ''; } return t === 'reporte' || t === 'registradas' ? t : (typeof TB !== 'undefined' && TB.estado === 'listo' ? 'reporte' : 'registradas'); };
  const tabs = t => `<div class="vg-tabs" role="tablist" aria-label="Vistas de Ventas"><button type="button" role="tab" class="agency-chip-btn ${t === 'registradas' ? 'is-active' : ''}" aria-selected="${t === 'registradas'}" data-vg-tab="registradas">${icon('receipt')}Ventas registradas${VG.datos?.pendientes?.length ? ` <span class="vg-badge">${VG.datos.pendientes.length}</span>` : ''}</button><button type="button" role="tab" class="agency-chip-btn ${t === 'reporte' ? 'is-active' : ''}" aria-selected="${t === 'reporte'}" data-vg-tab="reporte">${icon('pie')}Reporte del mes</button></div>`;
  V.tc_ventas = {
    need: () => Promise.allSettled([base.need ? base.need() : null, vgCargar()]).then(r => { if (r[1].status === 'rejected' && tab() === 'registradas') throw r[1].reason; }),
    render() { const t = tab(); return tabs(t) + (t === 'reporte' ? base.render() : vgRegistradasHtml()); },
    mounted(root) {
      root.querySelectorAll('[data-vg-tab]').forEach(b => b.onclick = () => { try { store.set('vg.tab', b.dataset.vgTab); } catch { /* sin almacenamiento */ } repaint(); });
      if (tab() === 'reporte') base.mounted?.(root); else vgRegistradasMontar(root);
    },
  };
}
/* Facturación: una sección pequeña con los cobros por recibir de las ventas registradas. */
if (typeof V === 'object' && V.tc_facturas) {
  const base = V.tc_facturas;
  V.tc_facturas = {
    need: () => Promise.allSettled([base.need ? base.need() : null, vgCargar()]).then(r => { if (r[0].status === 'rejected') throw r[0].reason; }),
    render() { return base.render(); },
    mounted(root) {
      base.mounted?.(root);
      const p = VG.datos?.proximos || [], cab = root.querySelector('.mod-h');
      if (!p.length || !cab || root.querySelector('.vg-fact')) return;
      const sec = document.createElement('section'); sec.className = 'panel pf-sec vg-fact';
      sec.innerHTML = `${secH('clock', 'Cobros por recibir de las ventas registradas', `${p.length} ${p.length === 1 ? 'cobro' : 'cobros'} en los próximos 31 días o atrasados · ${esc(vgPorMoneda(p.reduce((o, c) => { o[c.moneda] = (o[c.moneda] || 0) + c.monto; return o; }, {})))}`, `<a class="btn btn-sm" href="${href('/ventas')}" data-vg-ir-ventas>${icon('receipt')}Ver en Ventas</a>`)}
        <ul class="vg-prox-l vg-prox-h">${p.slice(0, 6).map(c => `<li class="${c.vencido ? 'is-venc' : ''}"><span class="vg-prox-f"><b>${esc(vgFecha(c.fecha).slice(0, 5))}</b><small>${c.vencido ? 'Vencido' : 'Vence'}</small></span><span class="vg-prox-t"><b>${esc(c.cliente)}</b><small>${esc(c.producto)}</small></span><strong>${esc(vgMonto(c.monto, c.moneda))}</strong></li>`).join('')}</ul>`;
      cab.after(sec);
      sec.querySelector('[data-vg-ir-ventas]')?.addEventListener('click', () => { try { store.set('vg.tab', 'registradas'); } catch { /* sin almacenamiento */ } });
    },
  };
}

/* ---------- 4. Ficha del contacto, Oportunidades → Ganadas y aviso de pendientes ---------- */
async function vgFicha(panel, contactId) {
  panel.dataset.vg = contactId;
  let d = VG.contacto[contactId];
  if (!d) { try { d = await agoraApi('workspace/ventas?contactoId=' + encodeURIComponent(contactId)); VG.contacto[contactId] = d; } catch { return; } }
  if (!panel.isConnected || panel.dataset.vg !== contactId) return;
  panel.querySelector('.vg-ficha')?.remove();
  const items = d.items || [];
  if (!items.length) return;
  const box = document.createElement('div'); box.className = 'vg-ficha';
  box.innerHTML = `<div class="section-label">Ventas registradas</div><ul class="vg-ficha-l">${items.map(v => { const prox = (v.plan || []).find(c => c.estado !== 'pagado'); return `<li><span><b>${esc(v.producto)}</b><small>${esc(vgMonto(v.monto, v.moneda))}${v.cobro === 'recurrente' ? ' / ' + esc(VG_FREC_CADA[v.frecuencia] || 'mes') : ''} · ${esc(vgFecha(v.fecha))}${prox ? ` · próximo cobro ${esc(vgFecha(prox.fecha))}` : ''}</small></span><span class="sc ${VG_ESTADO[v.estado]?.[1] || 'sc-gray'}">${esc(VG_ESTADO[v.estado]?.[0] || v.estado)}</span>${d.canEdit ? `<button type="button" class="icon-btn" data-vg-editar="${esc(v.id)}" aria-label="Editar venta" title="Editar venta">${icon('edit')}</button>` : ''}</li>`; }).join('')}</ul>`;
  box.querySelectorAll('[data-vg-editar]').forEach(b => b.onclick = () => vgAbrir(null, {ventaId: b.dataset.vgEditar}));
  (panel.querySelector('.record-columns') || panel.querySelector('.record-header'))?.after(box);
}
function vgChips(root) {
  const d = VG.datos; if (!d) return;
  const por = new Map((d.vinculos || []).map(x => [x.oportunidadId, x]));
  const pend = new Set((d.pendientes || []).map(p => p.id));
  root.querySelectorAll('[data-won-id]').forEach(row => {
    const id = row.dataset.wonId, marca = por.has(id) ? 'ok' : pend.has(id) ? 'pend' : '';
    if (row.dataset.vgChip === marca) return;
    row.dataset.vgChip = marca; row.querySelector('.vg-chip')?.remove();
    if (!marca) return;
    const el = document.createElement(marca === 'ok' ? 'span' : 'button');
    el.className = 'sc vg-chip ' + (marca === 'ok' ? 'sc-ok' : 'sc-warn');
    el.textContent = marca === 'ok' ? 'Venta registrada' : 'Venta por completar';
    if (marca === 'pend') { el.type = 'button'; el.title = 'Completar la venta'; el.onclick = e => { e.stopPropagation(); vgAbrir(id, {nueva: true}); }; }
    (row.querySelector('.won-row-info small') || row.querySelector('.won-row-info') || row).append(el);
  });
}
function vgAviso() {
  const n = VG.datos?.pendientes?.length || 0, viejo = document.getElementById('vg-aviso');
  if (!n || VG.avisoOculto || !vgPuede() || (typeof R !== 'undefined' && R?.view === 'tc_ventas')) { viejo?.remove(); return; }
  const html = `<span class="vg-aviso-ic" aria-hidden="true">${icon('warn')}</span><span><b>${n === 1 ? 'Una venta ganada' : n + ' ventas ganadas'} por completar</b><small>Falta el formulario de venta.</small></span><button type="button" class="btn btn-sm btn-cta" data-vg-aviso-ir>Completar</button><button type="button" class="x" data-vg-aviso-x aria-label="Ocultar aviso">${icon('plus')}</button>`;
  const el = viejo || Object.assign(document.createElement('div'), {id: 'vg-aviso', className: 'vg-aviso'});
  el.setAttribute('role', 'status');
  if (el.dataset.n !== String(n)) { el.innerHTML = html; el.dataset.n = String(n); }
  el.querySelector('[data-vg-aviso-ir]').onclick = () => { if (n === 1) vgAbrir(VG.datos.pendientes[0].id, {nueva: true}); else { try { store.set('vg.tab', 'registradas'); } catch { /* sin almacenamiento */ } go('/ventas'); } };
  el.querySelector('[data-vg-aviso-x]').onclick = () => { VG.avisoOculto = true; el.remove(); };
  if (!viejo) document.body.append(el);
}
/** Mira la pantalla: ficha abierta, lista de ganadas y aviso. Se llama tras cada cambio del DOM (rAF). */
function vgVigilar(force) {
  if (typeof document === 'undefined' || !document.getElementById) return;
  // El bundle pone su propio envoltorio de fetch (aviso de mantenimiento) después de entrar: si
  // la capa quedó por debajo, se vuelve a poner encima.
  vgInterceptar();
  if (typeof agoraReady !== 'function' || !agoraReady() || typeof AGENCY_CONTEXT !== 'undefined' && AGENCY_CONTEXT) { document.getElementById('vg-aviso')?.remove(); return; }
  const app = document.getElementById('app'); if (!app) return;
  const ficha = app.querySelector('#contact-record');
  if (ficha && ficha.querySelector('.record-header')) {
    const id = app.querySelector('[data-contact].selected')?.dataset.contact;
    if (id && (force || ficha.dataset.vg !== id || (!ficha.querySelector('.vg-ficha') && VG.contacto[id]?.items?.length))) vgFicha(ficha, id);
  }
  if (!VG.datos || Date.now() - VG.t > 60000 || force) { if (!VG.carga) vgCargar(force).then(() => { vgChips(app); vgAviso(); }).catch(() => {}); }
  else { vgChips(app); vgAviso(); }
}
function vgObservar() {
  if (VG.obs || typeof MutationObserver === 'undefined') return;
  const app = document.getElementById('app'); if (!app) return;
  VG.obs = new MutationObserver(() => { cancelAnimationFrame(VG.raf); VG.raf = requestAnimationFrame(() => vgVigilar(false)); });
  VG.obs.observe(app, {childList: true, subtree: true});
  vgVigilar(false);
}
if (typeof document !== 'undefined' && document.getElementById && typeof MutationObserver !== 'undefined') {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', vgObservar); else setTimeout(vgObservar, 0);
}
window.addEventListener?.('agora:opportunity-edited', () => { VG.t = 0; });

/* ---------- 5. Tablero del Inicio: la tarjeta de ventas lee las ventas registradas ---------- */
if (typeof TB_TARJETAS === 'object' && typeof tbValor === 'function' && typeof tbDatos === 'function') {
  TB_TARJETAS.ventasRegistradas = {t: 'Ventas registradas', ic: 'receipt', mod: 'ventas', d: 'Ventas del mes registradas al ganar, y lo que falta por cobrar.'};
  const datosBase = tbDatos, valorBase = tbValor;
  tbDatos = async function () {
    const d = await datosBase();
    if (d && !('ventas' in d)) { const ids = tbConfig().tarjetas.filter(tbVisible); d.ventas = ids.some(x => x === 'ventasMes' || x === 'ventasRegistradas') ? await agoraApi('workspace/ventas?mes=' + mesActualYM()).catch(() => null) : null; }
    return d;
  };
  tbValor = function (id, d) {
    const r = d?.ventas?.resumen;
    if (id === 'ventasRegistradas') return r ? [int(r.cantidad || 0), r.cantidad ? `${vgPorMoneda(r.vendido)} · por cobrar ${vgPorMoneda(r.porCobrar)}` : 'Ninguna este mes'] : ['—', 'Sin datos por ahora'];
    if (id === 'ventasMes' && r && r.cantidad > 0 && !d.mes?.sales?.total) return [vgPorMoneda(r.vendido), `${int(r.cantidad)} ${r.cantidad === 1 ? 'venta registrada' : 'ventas registradas'}`];
    return valorBase(id, d);
  };
}
/* =====================================================================================
   2.1.17 · Embudos, indicadores (KPI) y gestión unificada de usuarios
   -------------------------------------------------------------------------------------
   1. Embudos (Personalizar → Embudos): por subcuenta, crear, renombrar y borrar embudos; por
      etapa: nombre, orden (arrastrar o flechas), color, probabilidad, tipo (abierta, ganada,
      perdida), días meta en la etapa y si cuenta como lead, cita o cierre para los KPI.
      API workspace/embudos. Se guarda en los mismos registros 'pipeline' que leen Oportunidades
      y el ARP, así que esos módulos ven el cambio sin tocar agora-modulos.js.
   2. Indicadores (KPI) (Personalizar → Indicadores): activar, renombrar, ordenar, meta, formato,
      umbrales verde/ámbar, «más es mejor» o «menos es mejor», fuente (lista fija de métricas que
      la app ya calcula) y KPI personalizados A ÷ B o A − B. API workspace/indicadores. Las metas
      del mes son las mismas del ARP (ajuste board.<periodo>): no se duplican.
      Se ven en el Inicio (tarjetas con semáforo) y en el ARP (nombre propio y chip de meta).
   3. Agencia → Gestión de usuarios: Master y Superadmin ven y editan a todos los usuarios a su
      alcance (agencia y subcuentas) en una sola tabla. API agency/usuarios.
   ===================================================================================== */

const kClon = v => JSON.parse(JSON.stringify(v ?? null));
const kNuevoId = pre => pre + '_' + Math.random().toString(36).slice(2, 9);
window.addEventListener?.('agora:account-changing', () => { EMB = null; EMB_ORIG = null; EMB_BASE = ''; EMB_SEL = 0; IND = null; IND_ORIG = null; IND_BASE = ''; KPI_CFG = null; KPI_CFG_T = 0; KPI_VAL = null; KPI_VAL_T = 0; });

/* =====================================================================================
   1. Embudos
   ===================================================================================== */
var EMB = null, EMB_ORIG = null, EMB_BASE = '', EMB_SEL = 0, EMB_P = null, EMB_DRAG = null;
const EMB_COLORES = ['#6B7A99', '#2F6FDE', '#0E9F8F', '#D98E04', '#7C4DDB', '#D2337A', '#1F9D55', '#C2410C'];
const EMB_TIPOS = [['abierta', 'Abierta'], ['ganada', 'Ganada'], ['perdida', 'Perdida']];
const EMB_KPI = [['', 'No cuenta'], ['lead', 'Lead'], ['cita', 'Cita'], ['cierre', 'Cierre']];
function embCargar(force) {
  if (EMB && !force) return Promise.resolve(EMB);
  EMB_P ??= agoraApi('workspace/embudos').then(d => { embAplicar(d); return EMB; }).finally(() => { EMB_P = null; });
  return EMB_P;
}
function embAplicar(d) {
  EMB_ORIG = d;
  EMB = {pipelines: kClon(d.pipelines || []).map(p => ({id: p.id, name: p.name, stages: p.stages || []})), conteos: d.conteos || {}, mover: {}};
  EMB_BASE = embFirma();
  if (EMB_SEL >= EMB.pipelines.length) EMB_SEL = 0;
}
const embFirma = () => JSON.stringify({p: EMB?.pipelines, m: EMB?.mover});
function embSucio() { return !!EMB && embFirma() !== EMB_BASE; }
function embDescartar() { if (EMB_ORIG) embAplicar(EMB_ORIG); }
const embConteo = (p, s) => EMB?.conteos?.[p + '|' + s] || {n: 0, abiertas: 0, valor: 0};
/** ¿La etapa ya existe en el servidor? (solo esas pueden tener oportunidades) */
const embGuardada = (p, s) => (EMB_ORIG?.pipelines || []).some(x => x.id === p && (x.stages || []).some(y => y.id === s));
function embValidar() {
  const nombres = new Set();
  if (!EMB.pipelines.length) return 'Debe quedar al menos un embudo.';
  for (const p of EMB.pipelines) {
    const n = String(p.name || '').trim(); if (!n) return 'Cada embudo necesita un nombre.';
    if (nombres.has(norm(n))) return `Ya hay un embudo llamado «${n}».`; nombres.add(norm(n));
    if (!p.stages.length) return `«${n}» necesita al menos una etapa.`;
    const et = new Set();
    for (const s of p.stages) { const sn = String(s.name || '').trim(); if (!sn) return `Hay una etapa sin nombre en «${n}».`; if (et.has(norm(sn))) return `La etapa «${sn}» está repetida en «${n}».`; et.add(norm(sn)); }
  }
  return '';
}
async function embGuardar() {
  const malo = embValidar(); if (malo) { PZ_TAB = 'embudos'; throw new Error(malo); }
  const body = {pipelines: EMB.pipelines.map(p => ({id: p.id, name: p.name.trim(), stages: p.stages.map(s => ({id: s.id, name: String(s.name).trim(), color: s.color, probabilidad: s.probabilidad === '' || s.probabilidad == null ? null : Number(s.probabilidad), tipo: s.tipo, sla: s.sla === '' || s.sla == null ? null : Number(s.sla), kpi: s.kpi || ''}))})), mover: EMB.mover};
  const d = await agoraApi('workspace/embudos', {method: 'PUT', body});
  embAplicar(d); KPI_VAL = null; if (typeof TABLERO_DATOS !== 'undefined') TABLERO_DATOS = null;
  if (d.movidas) toast(`${d.movidas} ${d.movidas === 1 ? 'oportunidad movida' : 'oportunidades movidas'} de etapa.`);
  return d;
}
function embEtapaNueva(p) {
  const usados = new Set(p.stages.map(s => s.color));
  return {id: kNuevoId('st'), name: '', color: EMB_COLORES.find(c => !usados.has(c)) || EMB_COLORES[p.stages.length % EMB_COLORES.length], probabilidad: 50, tipo: 'abierta', sla: null, kpi: ''};
}
const embTipoIc = t => t === 'ganada' ? 'check' : t === 'perdida' ? 'x' : 'chevR';
function embTab() {
  if (!agoraReady()) return `<section class="panel"><p class="empty"><b>Los embudos no están disponibles</b>Esta subcuenta todavía no tiene la operación comercial activa.</p></section>`;
  if (!EMB) return `<section class="panel pf-sec">${secH('trend', 'Embudos', 'Cargando tus embudos…')}<div class="emb-sk"><span class="sk w80"></span><span class="sk w60"></span><span class="sk w80"></span></div><div class="pz-acts"><button type="button" class="btn btn-sm" data-emb-reintentar>${icon('refresh')}Reintentar</button></div></section>`;
  const p = EMB.pipelines[EMB_SEL];
  const totalOps = q => q.stages.reduce((a, s) => a + embConteo(q.id, s.id).n, 0);
  const lista = `<aside class="panel pf-sec emb-lista">${secH('trend', 'Tus embudos', 'Elige uno para editar sus etapas.')}
    <ul class="emb-pipes">${EMB.pipelines.map((q, i) => `<li><button type="button" class="emb-pipe ${i === EMB_SEL ? 'is-on' : ''}" data-emb-sel="${i}" aria-pressed="${i === EMB_SEL}"><span class="emb-pipe-barra" aria-hidden="true">${q.stages.map(s => `<i style="background:${esc(s.color)}"></i>`).join('')}</span><b>${esc(q.name || 'Embudo sin nombre')}</b><small>${q.stages.length} ${q.stages.length === 1 ? 'etapa' : 'etapas'} · ${int(totalOps(q))} ${totalOps(q) === 1 ? 'oportunidad' : 'oportunidades'}</small></button></li>`).join('')}</ul>
    <button type="button" class="btn btn-sm emb-nuevo" data-emb-nuevo ${EMB.pipelines.length >= 20 ? 'disabled' : ''}>${icon('plus')}Nuevo embudo</button></aside>`;
  if (!p) return `<div class="emb">${lista}<section class="panel"><p class="empty"><b>Sin embudos</b>Crea tu primer embudo para ordenar tus oportunidades.</p></section></div>`;
  const fila = (s, i) => { const c = embConteo(p.id, s.id); return `<li class="emb-et" data-et="${i}">
      <span class="emb-asa" draggable="true" data-emb-asa="${i}" title="Arrastra para ordenar" aria-hidden="true">${icon('more')}</span>
      <label class="emb-color" title="Color de la etapa"><span class="sr-only">Color de ${esc(s.name || 'la etapa')}</span><input type="color" data-f="color" value="${esc(s.color || '#6B7A99')}"></label>
      <label class="fld emb-nom"><span>Etapa ${i + 1}</span><input data-f="name" maxlength="120" value="${esc(s.name)}" placeholder="Nombre de la etapa" required></label>
      <label class="fld emb-tipo"><span>Tipo</span><select data-f="tipo">${EMB_TIPOS.map(([v, l]) => `<option value="${v}" ${s.tipo === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
      <label class="fld emb-prob"><span>Probabilidad</span><span class="emb-suf"><input data-f="probabilidad" type="number" inputmode="numeric" min="0" max="100" value="${esc(s.probabilidad ?? '')}"><i>%</i></span></label>
      <label class="fld emb-sla"><span>Días meta</span><input data-f="sla" type="number" inputmode="numeric" min="1" max="365" value="${esc(s.sla ?? '')}" placeholder="Sin límite"></label>
      <label class="fld emb-kpi"><span>Para los KPI</span><select data-f="kpi">${EMB_KPI.map(([v, l]) => `<option value="${v}" ${(s.kpi || '') === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
      <span class="emb-n sc ${c.n ? 'sc-info' : 'sc-gray'}" title="Oportunidades en esta etapa">${int(c.n)} ${c.n === 1 ? 'oport.' : 'oport.'}</span>
      <span class="emb-acc">${pzFlechas('data-emb-mover', i, i, p.stages.length, s.name || 'etapa')}<button type="button" class="icon-btn emb-x" data-emb-quitar="${i}" aria-label="Borrar la etapa ${esc(s.name)}" ${p.stages.length < 2 ? 'disabled title="Un embudo necesita al menos una etapa"' : ''}>${icon('x')}</button></span>
    </li>`; };
  const movs = Object.keys(EMB.mover).length;
  return `<div class="emb">${lista}
    <section class="panel pf-sec emb-editor" aria-label="Etapas de ${esc(p.name)}">
      <div class="emb-cab"><label class="fld grow">Nombre del embudo<input data-emb-nombre maxlength="120" value="${esc(p.name)}" placeholder="Por ejemplo: Ventas"></label>
        <button type="button" class="btn btn-sm btn-danger" data-emb-borrar ${EMB.pipelines.length < 2 ? 'disabled title="Debe quedar al menos un embudo"' : ''}>${icon('x')}Eliminar embudo</button></div>
      <div class="emb-barra" role="img" aria-label="Vista del embudo: ${esc(p.stages.map(s => s.name).join(', '))}">${p.stages.map(s => `<span style="--c:${esc(s.color)}" class="is-${esc(s.tipo)}"><i aria-hidden="true">${icon(embTipoIc(s.tipo))}</i><b>${esc(s.name || 'Sin nombre')}</b><small>${esc(s.probabilidad ?? 0)} %</small></span>`).join('')}</div>
      <p class="pz-l">Etapas · arrastra desde ${icon('more')} o usa las flechas para ordenar</p>
      <ol class="emb-etapas" data-emb-etapas>${p.stages.map(fila).join('')}</ol>
      <div class="emb-pie"><button type="button" class="btn btn-sm" data-emb-etapa ${p.stages.length >= 30 ? 'disabled' : ''}>${icon('plus')}Agregar etapa</button>
        <p class="bx-help"><b>Probabilidad</b>: qué tan cerca del cierre está una oportunidad en esa etapa. <b>Días meta</b>: cuánto debería durar como máximo. <b>Para los KPI</b>: si la etapa cuenta como lead, cita o cierre en tus indicadores.${movs ? ` <b>${movs} ${movs === 1 ? 'etapa borrada moverá' : 'etapas borradas moverán'} sus oportunidades al guardar.</b>` : ''}</p></div>
    </section></div>`;
}
function embConectar(root) {
  root.querySelector('[data-emb-reintentar]')?.addEventListener('click', async e => { busy(e.currentTarget, true, 'Cargando…'); try { await embCargar(true); } catch (x) { toast(x.message); } repaint(); });
  if (!EMB) return;
  const p = EMB.pipelines[EMB_SEL];
  root.querySelectorAll('[data-emb-sel]').forEach(b => b.onclick = () => { EMB_SEL = +b.dataset.embSel; repaint(); });
  root.querySelector('[data-emb-nuevo]')?.addEventListener('click', () => {
    const q = {id: kNuevoId('pl'), name: 'Embudo nuevo', stages: []};
    [['Nuevo', 10, 'abierta', 'lead'], ['En conversación', 30, 'abierta', ''], ['Propuesta enviada', 60, 'abierta', 'cita'], ['Ganada', 100, 'ganada', 'cierre'], ['Perdida', 0, 'perdida', '']].forEach(([n, pr, t, k]) => q.stages.push({...embEtapaNueva(q), name: n, probabilidad: pr, tipo: t, kpi: k}));
    EMB.pipelines.push(q); EMB_SEL = EMB.pipelines.length - 1; repaint();
    setTimeout(() => document.querySelector('[data-emb-nombre]')?.select(), 30);
  });
  if (!p) return;
  const nombre = root.querySelector('[data-emb-nombre]');
  nombre?.addEventListener('input', () => { p.name = nombre.value; const b = root.querySelector('.emb-pipe.is-on b'); if (b) b.textContent = nombre.value || 'Embudo sin nombre'; pzPieRefrescar(root); });
  root.querySelector('[data-emb-borrar]')?.addEventListener('click', () => embBorrarEmbudo(p));
  root.querySelector('[data-emb-etapa]')?.addEventListener('click', () => {
    p.stages.push(embEtapaNueva(p)); repaint();
    setTimeout(() => [...document.querySelectorAll('.emb-et [data-f=name]')].pop()?.focus(), 30);
  });
  root.querySelectorAll('[data-emb-mover]').forEach(b => b.onclick = () => { const [i, d] = b.dataset.embMover.split('|').map(Number); embMover(p, i, i + d); });
  root.querySelectorAll('[data-emb-quitar]').forEach(b => b.onclick = () => embQuitarEtapa(p, +b.dataset.embQuitar));
  const lista = root.querySelector('[data-emb-etapas]'); if (!lista) return;
  lista.addEventListener('input', e => {
    const f = e.target.dataset.f, li = e.target.closest('[data-et]'); if (!f || !li) return;
    const s = p.stages[+li.dataset.et]; let v = e.target.value;
    if (f === 'probabilidad' || f === 'sla') v = v === '' ? null : Math.max(f === 'sla' ? 1 : 0, Math.min(f === 'sla' ? 365 : 100, Math.round(Number(v)) || 0));
    s[f] = v; pzPieRefrescar(root);
    const chip = root.querySelectorAll('.emb-barra > span')[+li.dataset.et];
    if (chip && f === 'name') chip.querySelector('b').textContent = v || 'Sin nombre';
    if (chip && f === 'probabilidad') chip.querySelector('small').textContent = (v ?? 0) + ' %';
  });
  lista.addEventListener('change', e => {
    const f = e.target.dataset.f, li = e.target.closest('[data-et]'); if (!f || !li) return;
    const s = p.stages[+li.dataset.et];
    if (f === 'tipo') { if (s.tipo === 'ganada') { s.probabilidad = 100; if (!s.kpi) s.kpi = 'cierre'; } if (s.tipo === 'perdida') s.probabilidad = 0; }
    // Solo los selectores repintan: un campo de texto dispara «change» al perder el foco y un repintado ahí se comería el clic siguiente.
    if (['tipo', 'kpi', 'color'].includes(f)) repaint();
  });
  // Arrastrar y soltar (desde el asa; los campos siguen siendo editables)
  lista.addEventListener('dragstart', e => { const a = e.target.closest?.('[data-emb-asa]'); if (!a) return; EMB_DRAG = +a.dataset.embAsa; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(EMB_DRAG)); e.dataTransfer.setDragImage(a.closest('li'), 20, 20); } catch {} a.closest('li').classList.add('is-arrastrando'); });
  lista.addEventListener('dragover', e => { if (EMB_DRAG == null) return; const li = e.target.closest('[data-et]'); if (!li) return; e.preventDefault(); lista.querySelectorAll('.is-destino').forEach(x => x.classList.remove('is-destino')); li.classList.add('is-destino'); });
  lista.addEventListener('dragend', () => { EMB_DRAG = null; lista.querySelectorAll('.is-destino, .is-arrastrando').forEach(x => x.classList.remove('is-destino', 'is-arrastrando')); });
  lista.addEventListener('drop', e => { const li = e.target.closest('[data-et]'); if (EMB_DRAG == null || !li) return; e.preventDefault(); const de = EMB_DRAG; EMB_DRAG = null; embMover(p, de, +li.dataset.et); });
}
function embMover(p, de, a) {
  if (de === a || a < 0 || a >= p.stages.length) return;
  const [s] = p.stages.splice(de, 1); p.stages.splice(a, 0, s); repaint();
  setTimeout(() => document.querySelector(`[data-et="${a}"] [data-f=name]`)?.focus({preventScroll: true}), 30);
}
/** Opciones de destino para las oportunidades de una etapa que se borra. */
const embDestinos = (excluir) => EMB.pipelines.flatMap(q => q.stages.filter(s => !excluir(q, s) && String(s.name || '').trim()).map(s => [q.id + '|' + s.id, (EMB.pipelines.length > 1 ? q.name + ' · ' : '') + s.name]));
function embQuitarEtapa(p, i) {
  const s = p.stages[i]; if (!s || p.stages.length < 2) return;
  const c = embConteo(p.id, s.id);
  if (!c.n || !embGuardada(p.id, s.id)) { p.stages.splice(i, 1); repaint(); return; }
  const ops = embDestinos((q, x) => q.id === p.id && x.id === s.id);
  const m = openModal(modalHead('¿A dónde van sus oportunidades?', `La etapa «${esc(s.name)}» tiene ${int(c.n)} ${c.n === 1 ? 'oportunidad' : 'oportunidades'}`) + `<form class="form" data-emb-dest><label class="fld">Mover las oportunidades a<select name="destino" required>${ops.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('')}</select></label><p class="bx-help">Se mueven en el servidor cuando guardes los cambios. Si descartas, no pasa nada.</p><div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta btn-danger">Borrar etapa y mover</button></div></form>`);
  m.querySelector('form').onsubmit = e => { e.preventDefault(); EMB.mover[p.id + '|' + s.id] = e.currentTarget.destino.value; p.stages.splice(i, 1); closeModal(); repaint(); };
}
function embBorrarEmbudo(p) {
  if (EMB.pipelines.length < 2) return;
  const conOps = p.stages.filter(s => embConteo(p.id, s.id).n && embGuardada(p.id, s.id));
  const ops = embDestinos(q => q.id === p.id);
  const m = openModal(modalHead('¿Eliminar este embudo?', esc(p.name || 'Embudo sin nombre')) + `<form class="form">${conOps.length ? `<p style="margin:0">Algunas etapas tienen oportunidades. Elige a dónde se mueven:</p>${conOps.map(s => `<label class="fld">${esc(s.name)} · ${int(embConteo(p.id, s.id).n)} oport.<select name="${esc(p.id + '|' + s.id)}" required>${ops.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('')}</select></label>`).join('')}` : '<p style="margin:0">No tiene oportunidades. Se elimina al guardar los cambios.</p>'}<div class="form-ft"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-danger">Eliminar embudo</button></div></form>`);
  m.querySelector('form').onsubmit = e => { e.preventDefault(); const f = new FormData(e.currentTarget); conOps.forEach(s => { EMB.mover[p.id + '|' + s.id] = f.get(p.id + '|' + s.id); }); EMB.pipelines = EMB.pipelines.filter(q => q !== p); EMB_SEL = 0; closeModal(); repaint(); };
}

/* =====================================================================================
   2. Indicadores (KPI)
   ===================================================================================== */
var IND = null, IND_ORIG = null, IND_BASE = '', IND_P = null;
var KPI_CFG = null, KPI_CFG_T = 0, KPI_CFG_P = null, KPI_VAL = null, KPI_VAL_T = 0, KPI_VAL_P = null;
const KPI_FORMATOS = [['monto', 'Monto con moneda'], ['numero', 'Número'], ['porcentaje', 'Porcentaje (%)']];
const KPI_PERIODOS = [['dia', 'Por día'], ['semana', 'Por semana'], ['mes', 'Por mes'], ['trimestre', 'Por trimestre'], ['anio', 'Por año']];
const KPI_ESTADOS = {ok: 'En meta', warn: 'Cerca de la meta', bad: 'Necesita atención'};
/** De dónde sale cada métrica (para saber qué pedir y a qué módulo enlazar). */
const KPI_FUENTE = {
  ventasMes: ['mes', 'comercial'], cierresMes: ['mes', 'comercial'], gananciaMes: ['mes', 'comercial'], costoCierre: ['mes', 'comercial'], leadsMes: ['mes', 'comercial'], conversion: ['mes', 'comercial'],
  ingresos: ['won', 'oportunidades'], leadsDia: ['dia', 'comercial'], tiempoRespuesta: ['dia', 'bandeja'], inversionAds: ['ads', 'comercial'], costoLead: ['ads', 'comercial'], retornoAds: ['ads', 'comercial'],
  ticketPromedio: ['efic', 'comercial'], tasaCierre: ['efic', 'comercial'], cicloVenta: ['efic', 'comercial'],
  sinLeer: ['dash', 'bandeja'], conversaciones: ['dash', 'bandeja'], contactos: ['dash', 'contactos'], oportunidadesAbiertas: ['dash', 'oportunidades'], valorAbierto: ['dash', 'oportunidades'],
  leadsEmbudo: ['emb', 'oportunidades'], citas: ['emb', 'oportunidades'], cierresEmbudo: ['emb', 'oportunidades'],
  tareasPendientes: ['tareas', 'tareas'], tareasVencidas: ['tareas', 'tareas'], conveniosPorFirmar: ['contratos', 'convenios'], cobrosPendientes: ['ventas', 'ventas'],
};
/** Cifra del ARP Comercial que corresponde a cada métrica (por su rótulo original). */
const KPI_ARP = {
  leadsDia: /^leads (de hoy|del d[ií]a)/i, sinLeer: /^sin leer/i, conversaciones: /^conversaciones( atendidas)?$/i, oportunidadesAbiertas: /^oportunidades abiertas/i,
  tiempoRespuesta: /^tiempo de respuesta/i, inversionAds: /^invertido/i, costoLead: /^coste? por lead/i, retornoAds: /^retorno por cada 100/i,
  ventasMes: /^ventas ganadas del mes/i, costoCierre: /^costo por cierre/i, gananciaMes: /^ganancia real del mes/i,
  ticketPromedio: /^ticket promedio/i, tasaCierre: /^tasa de cierre/i, cicloVenta: /^tiempo de cierre promedio/i,
};
function indCargar(force) {
  if (IND && !force) return Promise.resolve(IND);
  IND_P ??= agoraApi('workspace/indicadores?period=' + mesActualYM()).then(d => { indAplicar(d); return IND; }).finally(() => { IND_P = null; });
  return IND_P;
}
function indAplicar(d) {
  IND_ORIG = d; KPI_CFG = {kpis: d.config?.kpis || [], fuentes: d.fuentes || []}; KPI_CFG_T = Date.now();
  const mes = d.mes || {};
  IND = {kpis: kClon(d.config?.kpis || []), fuentes: d.fuentes || [], asesores: d.asesores || [], canEdit: !!d.canEdit, porDefecto: !!d.porDefecto, actualizado: d.config?.actualizado, por: d.config?.por,
    mes: {period: mes.period || mesActualYM(), teamGoal: mes.teamGoal ?? null, green: mes.green ?? 100, amber: mes.amber ?? 70, closingWord: mes.closingWord || 'cierres', goals: {...(mes.goals || {})}, otherAds: mes.otherAds ?? null, fixedCosts: mes.fixedCosts ?? null}};
  IND_BASE = indFirma();
}
const indFirma = () => JSON.stringify({k: IND?.kpis, m: IND?.mes});
function indSucio() { return !!IND && indFirma() !== IND_BASE; }
function indDescartar() { if (IND_ORIG) indAplicar(IND_ORIG); }
const kpiFuente = id => (IND?.fuentes || KPI_CFG?.fuentes || []).find(f => f.id === id);
const kpiNombreFuente = id => id === 'personalizado' ? 'Personalizado' : kpiFuente(id)?.nombre || id;
async function indGuardar() {
  const vm = IND.kpis.find(k => k.fuente === 'ventasMes');
  if (vm) { IND.mes.teamGoal = vm.meta; IND.mes.green = vm.verde; IND.mes.amber = vm.ambar; }
  const d = await agoraApi('workspace/indicadores', {method: 'PUT', body: {kpis: IND.kpis, mes: IND.mes}});
  indAplicar(d); KPI_VAL = null; if (typeof TABLERO_DATOS !== 'undefined') TABLERO_DATOS = null;
  return d;
}
/* ---------- Valores actuales ---------- */
async function kpiValores(kpis, force) {
  if (KPI_VAL && !force && Date.now() - KPI_VAL_T < 60000) return KPI_VAL;
  if (KPI_VAL_P) return KPI_VAL_P;
  const usa = new Set();
  for (const k of kpis || []) { if (k.fuente === 'personalizado') { usa.add(k.formula?.a); usa.add(k.formula?.b); } else usa.add(k.fuente); }
  const pide = new Set([...usa].map(f => KPI_FUENTE[f]?.[0]).filter(Boolean));
  const tr = p => p.then(v => v, () => null);
  KPI_VAL_P = (async () => {
    const [dash, mes, won, dia, ads, efic, tareas, contratos, emb, ventas] = await Promise.all([
      pide.has('dash') ? tr(agoraApi('workspace/dashboard')) : null,
      pide.has('mes') ? tr(agoraApi('workspace/board-month?period=' + mesActualYM())) : null,
      pide.has('won') ? tr(agoraApi('workspace/won')) : null,
      pide.has('dia') ? tr(agoraApi('workspace/dia')) : null,
      pide.has('ads') ? tr(agoraApi('workspace/ads?period=' + mesActualYM())) : null,
      pide.has('efic') ? tr(agoraApi('workspace/eficacia')) : null,
      pide.has('tareas') ? tr(agoraApi('workspace/tareas')) : null,
      pide.has('contratos') && typeof loadContratos === 'function' ? tr(Promise.resolve().then(() => loadContratos())) : null,
      pide.has('emb') && (esAdmin() || esSuperAdmin()) ? tr(EMB ? Promise.resolve(EMB_ORIG) : agoraApi('workspace/embudos')) : null,
      pide.has('ventas') ? tr(agoraApi('workspace/ventas')) : null,
    ]);
    const n = v => v == null || v === '' || !Number.isFinite(Number(v)) ? null : Number(v);
    const v = {};
    if (dash) { v.conversaciones = n(dash.conversations?.total); v.sinLeer = n(dash.conversations?.unread); v.contactos = n(dash.contacts); v.oportunidadesAbiertas = n(dash.opportunities?.count); v.valorAbierto = n(dash.opportunities?.value); }
    if (mes) {
      const s = mes.sales || {}, team = Array.isArray(mes.team) ? mes.team : [];
      v.ventasMes = n(s.total); v.cierresMes = n(s.closings); v.gananciaMes = n(mes.profitability?.profit); v.costoCierre = n(mes.profitability?.costPerClosing);
      const leads = team.reduce((a, t) => a + (Number(t.leads) || 0), 0); v.leadsMes = team.some(t => t.leads != null) ? leads : null;
      v.conversion = v.leadsMes ? (Number(s.closings) || 0) / v.leadsMes * 100 : null;
      v.ticketPromedio = s.closings ? (Number(s.total) || 0) / s.closings : null;
    }
    if (won) v.ingresos = n(won.totals?.sum);
    if (dia) { v.leadsDia = n(dia.leads); v.tiempoRespuesta = dia.slaPromedioSeg != null ? Math.round(Number(dia.slaPromedioSeg) / 6) / 10 : null; }
    if (ads && ads.estado !== 'sin-conexion') { v.inversionAds = n(ads.mes); v.costoLead = n(ads.costePorLead); v.retornoAds = n(ads.retornoPorCien); }
    if (efic) { v.ticketPromedio = n(efic.ticket) ?? v.ticketPromedio ?? null; v.tasaCierre = n(efic.tasaCierre); v.cicloVenta = n(efic.duracionPromedioDias); }
    if (tareas) {
      const it = Array.isArray(tareas.items) ? tareas.items : [], hoy = new Date().toISOString().slice(0, 10), abiertas = it.filter(t => !['hecha', 'completada'].includes(t.estado));
      v.tareasPendientes = n(tareas.resumen?.pendientes) ?? abiertas.length; v.tareasVencidas = n(tareas.resumen?.vencidas) ?? abiertas.filter(t => t.vence && t.vence < hoy).length;
    }
    if (Array.isArray(contratos)) v.conveniosPorFirmar = contratos.filter(c => c.estado === 'ENVIADO').length;
    if (emb?.pipelines) {
      const cuenta = tipo => emb.pipelines.reduce((a, p) => a + (p.stages || []).filter(s => s.kpi === tipo).reduce((b, s) => b + (emb.conteos?.[p.id + '|' + s.id]?.abiertas ?? emb.conteos?.[p.id + '|' + s.id]?.n ?? 0), 0), 0);
      v.leadsEmbudo = cuenta('lead'); v.citas = cuenta('cita'); v.cierresEmbudo = emb.pipelines.reduce((a, p) => a + (p.stages || []).filter(s => s.kpi === 'cierre').reduce((b, s) => b + (emb.conteos?.[p.id + '|' + s.id]?.n ?? 0), 0), 0);
    }
    // 2.1.17: Ventas registradas devuelve resumen.porCobrar por moneda ({USD: 120, ...}); se suma la moneda de la subcuenta (o todo si es una sola).
    const porCobrar = o => { if (!o || typeof o !== 'object') return null; const m = ESPACIO?.moneda || 'USD', ks = Object.keys(o); return ks.length ? (o[m] != null ? +o[m] : ks.length === 1 ? +o[ks[0]] : ks.reduce((t, k) => t + (+o[k] || 0), 0)) : 0; };
    if (ventas) v.cobrosPendientes = n(ventas.resumen?.pendiente ?? ventas.totales?.pendiente ?? ventas.pendiente ?? porCobrar(ventas.resumen?.porCobrar));
    KPI_VAL = v; KPI_VAL_T = Date.now(); return v;
  })().finally(() => { KPI_VAL_P = null; });
  return KPI_VAL_P;
}
function kpiValor(k, v) {
  if (!v) return null;
  if (k.fuente === 'personalizado') {
    const f = k.formula || {}, a = v[f.a], b = v[f.b];
    if (a == null || b == null) return null;
    let r = f.op === '/' ? (b ? a / b : null) : a - b;
    if (r != null && f.porCien) r *= 100;
    return r;
  }
  return v[k.fuente] ?? null;
}
/** Semáforo: avance contra la meta, en %. Con «menos es mejor» el avance es meta ÷ valor. */
function kpiEstado(k, val) {
  const meta = k.meta == null || k.meta === '' ? null : Number(k.meta);
  if (val == null || meta == null || !Number.isFinite(meta)) return null;
  let r;
  if (k.sentido === 'menos') r = meta === 0 ? (val <= 0 ? 100 : 0) : val <= 0 ? 200 : meta / val * 100;
  else { if (meta <= 0) return null; r = val / meta * 100; }
  const verde = Number(k.verde ?? 100), ambar = Number(k.ambar ?? 70);
  return {r, e: r >= verde ? 'ok' : r >= ambar ? 'warn' : 'bad'};
}
const KPI_NF = new Intl.NumberFormat('es-VE', {maximumFractionDigits: 1});
function kpiFmt(k, v) {
  if (v == null || !Number.isFinite(Number(v))) return '—';
  const r = Math.round(Number(v) * 10) / 10;
  if (k.formato === 'monto') return agenciaMonto(Math.round(Number(v) * 100) / 100, k.moneda || ESPACIO?.moneda || 'USD');
  if (k.formato === 'porcentaje') return `${KPI_NF.format(r)} %`;
  return KPI_NF.format(r);
}
const kpiMetaTxt = k => k.meta == null || k.meta === '' ? 'Sin meta' : `Meta ${kpiFmt(k, k.meta)}${k.periodo && k.periodo !== 'mes' ? ' ' + (KPI_PERIODOS.find(p => p[0] === k.periodo)?.[1] || '').toLowerCase() : ' al mes'}`;
/* ---------- Pestaña ---------- */
function indTab() {
  if (!IND) return `<section class="panel pf-sec">${secH('pie', 'Indicadores (KPI)', 'Cargando tus indicadores…')}<div class="emb-sk"><span class="sk w80"></span><span class="sk w60"></span></div><div class="pz-acts"><button type="button" class="btn btn-sm" data-ind-reintentar>${icon('refresh')}Reintentar</button></div></section>`;
  const v = KPI_VAL;
  const fila = (k, i) => {
    const val = kpiValor(k, v), st = kpiEstado(k, val), f = k.fuente === 'personalizado' ? `${kpiNombreFuente(k.formula?.a)} ${k.formula?.op === '/' ? '÷' : '−'} ${kpiNombreFuente(k.formula?.b)}` : kpiNombreFuente(k.fuente);
    return `<li class="pz-item kpi-fila ${k.activo ? 'is-on' : ''}">
      <span class="pz-sw" title="${k.activo ? 'Activo' : 'Oculto'}"><input type="checkbox" role="switch" data-kpi-on="${i}" ${k.activo ? 'checked' : ''} aria-label="Mostrar ${esc(k.nombre)}"><i aria-hidden="true"></i></span>
      <button type="button" class="kpi-fila-tx" data-kpi-editar="${i}"><b>${esc(k.nombre)}${k.inicio ? ' <span class="sc sc-info">En el Inicio</span>' : ''}</b><small>${f !== k.nombre ? esc(f) + ' · ' : ''}${esc(kpiMetaTxt(k))} · ${k.sentido === 'menos' ? 'menos es mejor' : 'más es mejor'}</small></button>
      <span class="kpi-val ${st ? 'kpi-' + st.e : ''}" title="${st ? esc(KPI_ESTADOS[st.e]) : 'Sin meta o sin dato'}">${v ? esc(kpiFmt(k, val)) : '<span class="sk w40"></span>'}${st ? `<small>${Math.round(st.r)} % · ${esc(KPI_ESTADOS[st.e])}</small>` : ''}</span>
      ${pzFlechas('data-kpi-mover', i, i, IND.kpis.length, k.nombre)}
      <button type="button" class="icon-btn" data-kpi-editar="${i}" aria-label="Editar ${esc(k.nombre)}">${icon('edit')}</button>
      <button type="button" class="icon-btn emb-x" data-kpi-quitar="${i}" aria-label="Quitar ${esc(k.nombre)}">${icon('x')}</button></li>`;
  };
  const m = IND.mes, mesNombre = (() => { try { return new Date(m.period + '-15').toLocaleDateString('es-VE', {month: 'long', year: 'numeric'}); } catch { return m.period; } })();
  const inp = (name, val, label, extra = '') => `<label class="fld">${label}<input data-mes="${name}" inputmode="decimal" value="${esc(val ?? '')}" ${extra}></label>`;
  return `<section class="panel pf-sec">${secH('pie', 'Indicadores clave', `Lo que mides para saber si tu negocio va bien. Tu equipo los ve en el Inicio y en el ARP Comercial con su semáforo.${IND.porDefecto ? ' Estos son los sugeridos: ajústalos a tu negocio.' : ''}`, `<button type="button" class="btn btn-sm" data-kpi-nuevo ${IND.kpis.length >= 40 ? 'disabled' : ''}>${icon('plus')}Agregar indicador</button>`)}
      <ol class="pz-list kpi-lista">${IND.kpis.map(fila).join('') || '<li class="pz-vacio">No tienes indicadores. Agrega el primero.</li>'}</ol>
      <p class="bx-help">Verde y ámbar son el % de avance contra la meta. Con «menos es mejor» (por ejemplo, el costo por lead), el avance es meta ÷ valor: si gastas menos de la meta, vas en verde.</p>
    </section>
    <div class="pz-grid">
    <section class="panel pf-sec">${secH('check', 'Metas del mes · ' + esc(mesNombre), 'Es la misma configuración del mes que usa el ARP Comercial: lo que cambies aquí se ve allí.', `<a class="btn btn-sm" href="${href('/configuracion')}">${icon('edit')}Configuración mensual</a>`)}
      <div class="bx-grid kpi-mes">
        ${inp('teamGoal', m.teamGoal, 'Meta de ventas del equipo')}
        <label class="fld">Una venta se llama<input data-mes="closingWord" maxlength="30" value="${esc(m.closingWord)}" placeholder="cierres"></label>
        ${inp('green', m.green, 'Verde desde (% de la meta)')}
        ${inp('amber', m.amber, 'Ámbar desde (% de la meta)')}
        ${inp('otherAds', m.otherAds, 'Otros gastos de publicidad')}
        ${inp('fixedCosts', m.fixedCosts, 'Costos fijos del mes')}
      </div>
      ${IND.asesores.length ? `<p class="pz-l">Meta por asesor</p><div class="kpi-metas">${IND.asesores.map(a => `<label class="fld"><span>${esc(a.name)}</span><input data-meta-asesor="${esc(a.id)}" inputmode="decimal" value="${esc(m.goals[a.id] ?? '')}" placeholder="Sin meta"></label>`).join('')}</div>` : ''}
      <p class="bx-help">Las metas por asesora y los costos del reporte de Gestión Comercial siguen en <a href="${href('/configuracion')}">Configuración mensual</a>.</p>
    </section>
    ${pzArp()}</div>`;
}
function indConectar(root) {
  root.querySelector('[data-ind-reintentar]')?.addEventListener('click', async e => { busy(e.currentTarget, true, 'Cargando…'); try { await indCargar(true); } catch (x) { toast(x.message); } repaint(); });
  if (!IND) return;
  if (!KPI_VAL && agoraReady()) kpiValores(IND.kpis).then(() => { if (R.view === 'personalizar' && PZ_TAB === 'indicadores' && !document.querySelector('.modal')) repaint(); }).catch(() => {});
  root.querySelectorAll('[data-kpi-on]').forEach(c => c.onchange = () => { IND.kpis[+c.dataset.kpiOn].activo = c.checked; repaint(); });
  root.querySelectorAll('[data-kpi-mover]').forEach(b => b.onclick = () => { const [i, d] = b.dataset.kpiMover.split('|').map(Number), j = i + d; if (j < 0 || j >= IND.kpis.length) return; [IND.kpis[i], IND.kpis[j]] = [IND.kpis[j], IND.kpis[i]]; repaint(); });
  root.querySelectorAll('[data-kpi-editar]').forEach(b => b.onclick = () => kpiEditar(+b.dataset.kpiEditar));
  root.querySelectorAll('[data-kpi-quitar]').forEach(b => b.onclick = () => { const k = IND.kpis[+b.dataset.kpiQuitar]; confirmModal('¿Quitar este indicador?', `«${esc(k.nombre)}» deja de verse en el Inicio y en el ARP. Puedes volver a agregarlo cuando quieras.`, 'Quitar', async () => { IND.kpis.splice(IND.kpis.indexOf(k), 1); setTimeout(repaint, 0); }, true); });
  root.querySelector('[data-kpi-nuevo]')?.addEventListener('click', () => kpiEditar(-1));
  const num = s => { s = String(s ?? '').trim().replace(/\s/g, ''); if (!s) return null; s = s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s; const n = Number(s); return Number.isFinite(n) && n >= 0 ? Math.round(n * 100) / 100 : NaN; };
  root.querySelectorAll('[data-mes]').forEach(i => i.oninput = () => {
    const k = i.dataset.mes;
    if (k === 'closingWord') IND.mes.closingWord = i.value.trim().slice(0, 30);
    else { const n = num(i.value); i.setAttribute('aria-invalid', Number.isNaN(n) ? 'true' : 'false'); if (Number.isNaN(n)) return; IND.mes[k] = k === 'green' || k === 'amber' ? (n ?? (k === 'green' ? 100 : 70)) : n; }
    const vm = IND.kpis.find(x => x.fuente === 'ventasMes'); if (vm) { vm.meta = IND.mes.teamGoal; vm.verde = IND.mes.green; vm.ambar = IND.mes.amber; }
    pzPieRefrescar(root);
  });
  root.querySelectorAll('[data-meta-asesor]').forEach(i => i.oninput = () => { const n = num(i.value); i.setAttribute('aria-invalid', Number.isNaN(n) ? 'true' : 'false'); if (Number.isNaN(n)) return; if (n == null) delete IND.mes.goals[i.dataset.metaAsesor]; else IND.mes.goals[i.dataset.metaAsesor] = n; pzPieRefrescar(root); });
}
function kpiEditar(i) {
  const nuevo = i < 0, base = nuevo ? {id: kNuevoId('kpi'), fuente: 'ventasMes', nombre: '', activo: true, inicio: false, meta: null, periodo: 'mes', formato: 'monto', moneda: null, verde: 100, ambar: 70, sentido: 'mas', formula: null} : IND.kpis[i];
  const k = kClon(base), fuentes = IND.fuentes;
  const opF = sel => fuentes.map(f => `<option value="${esc(f.id)}" ${sel === f.id ? 'selected' : ''}>${esc(f.nombre)}</option>`).join('');
  const s = openModal(modalHead(nuevo ? 'Agregar indicador' : 'Editar indicador', nuevo ? 'Elige qué medir y cuál es la meta' : esc(k.nombre)) + `<form class="bx-form kpi-form" novalidate>
    <div class="bx-grid">
      <fieldset class="bx bx-wide"><legend>${icon('pie')}Qué mide</legend>
        <div class="bx-row"><label class="fld">Fuente<select name="fuente">${opF(k.fuente)}<option value="personalizado" ${k.fuente === 'personalizado' ? 'selected' : ''}>Personalizado: A ÷ B o A − B</option></select></label>
        <label class="fld">Nombre que se muestra<input name="nombre" maxlength="60" required value="${esc(k.nombre)}" placeholder="${esc(kpiNombreFuente(k.fuente))}"></label></div>
        <div class="bx-row kpi-formula" ${k.fuente === 'personalizado' ? '' : 'hidden'}><label class="fld">Métrica A<select name="fa">${opF(k.formula?.a || 'ventasMes')}</select></label><label class="fld kpi-op">Operación<select name="fop"><option value="/" ${k.formula?.op !== '-' ? 'selected' : ''}>÷ dividida entre</option><option value="-" ${k.formula?.op === '-' ? 'selected' : ''}>− menos</option></select></label><label class="fld">Métrica B<select name="fb">${opF(k.formula?.b || 'leadsMes')}</select></label></div>
        <label class="bx-check kpi-formula" ${k.fuente === 'personalizado' ? '' : 'hidden'}><input type="checkbox" name="fpc" ${k.formula?.porCien ? 'checked' : ''}> Multiplicar por 100 (para verlo como %)</label>
      </fieldset>
      <fieldset class="bx"><legend>${icon('check')}Meta</legend>
        <div class="bx-row"><label class="fld">Meta<input name="meta" inputmode="decimal" value="${esc(k.meta ?? '')}" placeholder="Sin meta"></label><label class="fld">Periodo<select name="periodo">${KPI_PERIODOS.map(([v, l]) => `<option value="${v}" ${k.periodo === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label></div>
        <div class="bx-row"><label class="fld">Formato<select name="formato">${KPI_FORMATOS.map(([v, l]) => `<option value="${v}" ${k.formato === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label><label class="fld kpi-moneda" ${k.formato === 'monto' ? '' : 'hidden'}>Moneda<select name="moneda"><option value="">La de la subcuenta</option>${['USD', 'VES', 'EUR'].map(m => `<option ${k.moneda === m ? 'selected' : ''}>${m}</option>`).join('')}</select></label></div>
        ${k.fuente === 'ventasMes' ? '<p class="bx-help">Esta meta es la meta del equipo del mes: la misma que usa el ARP Comercial.</p>' : ''}
      </fieldset>
      <fieldset class="bx"><legend>${icon('tune')}Semáforo</legend>
        <div class="kpi-sentido" role="radiogroup" aria-label="Qué es mejor"><label><input type="radio" name="sentido" value="mas" ${k.sentido !== 'menos' ? 'checked' : ''}><span><b>Más es mejor</b><small>Ventas, leads, conversión…</small></span></label><label><input type="radio" name="sentido" value="menos" ${k.sentido === 'menos' ? 'checked' : ''}><span><b>Menos es mejor</b><small>Costos, tiempos, vencidas…</small></span></label></div>
        <div class="bx-row"><label class="fld">Verde desde (%)<input name="verde" inputmode="decimal" value="${esc(k.verde ?? 100)}"></label><label class="fld">Ámbar desde (%)<input name="ambar" inputmode="decimal" value="${esc(k.ambar ?? 70)}"></label></div>
      </fieldset>
      <fieldset class="bx bx-wide"><legend>${icon('eye')}Dónde se ve</legend>
        <label class="bx-check"><input type="checkbox" name="activo" ${k.activo ? 'checked' : ''}> Activo (se ve en el ARP y en esta lista)</label>
        <label class="bx-check"><input type="checkbox" name="inicio" ${k.inicio ? 'checked' : ''}> Mostrarlo en el Inicio de la subcuenta, con su semáforo</label>
      </fieldset>
    </div>
    <p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">Se aplica al guardar los cambios de Personalizar.</p><div class="acts"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">${nuevo ? 'Agregar' : 'Listo'}</button></div></div>
  </form>`, {wide: true});
  const f = s.querySelector('form');
  f.fuente.onchange = () => {
    const fu = kpiFuente(f.fuente.value), pers = f.fuente.value === 'personalizado';
    s.querySelectorAll('.kpi-formula').forEach(x => x.hidden = !pers);
    if (fu) { f.formato.value = fu.formato; f.querySelector(`[name=sentido][value=${fu.sentido}]`).checked = true; f.nombre.placeholder = fu.nombre; }
    s.querySelector('.kpi-moneda').hidden = f.formato.value !== 'monto';
  };
  f.formato.onchange = () => { s.querySelector('.kpi-moneda').hidden = f.formato.value !== 'monto'; };
  f.onsubmit = e => {
    e.preventDefault();
    const err = f.querySelector('.fld-err'), num = (v, nombre) => { v = String(v || '').trim().replace(/\s/g, ''); if (!v) return null; v = v.includes(',') ? v.replace(/\./g, '').replace(',', '.') : v; const n = Number(v); if (!Number.isFinite(n) || n < 0) throw new Error(`«${nombre}»: escribe un número positivo.`); return Math.round(n * 100) / 100; };
    try {
      const fuente = f.fuente.value, nombre = f.nombre.value.trim() || kpiNombreFuente(fuente);
      if (fuente === 'personalizado' && f.fa.value === f.fb.value && f.fop.value === '-') throw new Error('A − A siempre da cero: elige dos métricas distintas.');
      const verde = num(f.verde.value, 'Verde desde') ?? 100, ambar = num(f.ambar.value, 'Ámbar desde') ?? 70;
      if (ambar > verde) throw new Error('El ámbar no puede ser mayor que el verde.');
      const out = {id: k.id, fuente, nombre: nombre.slice(0, 60), activo: f.activo.checked, inicio: f.inicio.checked, meta: num(f.meta.value, 'Meta'), periodo: f.periodo.value, formato: f.formato.value, moneda: f.formato.value === 'monto' ? (f.moneda.value || null) : null, verde, ambar, sentido: f.querySelector('[name=sentido]:checked')?.value || 'mas', formula: fuente === 'personalizado' ? {a: f.fa.value, op: f.fop.value, b: f.fb.value, porCien: f.fpc.checked} : null};
      if (fuente === 'ventasMes') {
        if (IND.kpis.some((x, j) => x.fuente === 'ventasMes' && j !== i)) throw new Error('Ya tienes un indicador de ventas del mes: edítalo en vez de repetirlo.');
        IND.mes.teamGoal = out.meta; IND.mes.green = verde; IND.mes.amber = ambar;
      }
      if (nuevo) IND.kpis.push(out); else IND.kpis[i] = out;
      closeModal(); repaint();
    } catch (x) { err.textContent = x.message; }
  };
}
/* ---------- Inicio: tarjetas con semáforo ---------- */
function kpiCfgCargar(force) {
  if (KPI_CFG && !force && Date.now() - KPI_CFG_T < 60000) return Promise.resolve(KPI_CFG);
  KPI_CFG_P ??= agoraApi('workspace/indicadores?period=' + mesActualYM()).then(d => { KPI_CFG = {kpis: d.config?.kpis || [], fuentes: d.fuentes || []}; KPI_CFG_T = Date.now(); return KPI_CFG; }).finally(() => { KPI_CFG_P = null; });
  return KPI_CFG_P;
}
const kpiHref = k => { const mod = KPI_FUENTE[k.fuente === 'personalizado' ? k.formula?.a : k.fuente]?.[1]; return MODS[mod] && moduleAllowed(mod) && !moduloOculto(mod) ? MODS[mod].path : null; };
function kpiTarjeta(k, val, cargando) {
  const st = cargando ? null : kpiEstado(k, val), link = kpiHref(k), tag = link ? `a href="${href(link)}"` : 'div';
  return `<${tag} class="kpi-card ${st ? 'kpi-' + st.e : ''}" data-kpi-card="${esc(k.id)}"><span class="kpi-card-t">${esc(k.nombre)}</span>
    <strong class="kpi-card-v">${cargando ? '<span class="sk w60"></span>' : esc(kpiFmt(k, val))}</strong>
    <span class="kpi-barra" aria-hidden="true"><i style="width:${st ? Math.max(4, Math.min(100, st.r)) : 0}%"></i></span>
    <span class="kpi-card-d">${cargando ? '<span class="sk w80"></span>' : `${esc(kpiMetaTxt(k))}${st ? ` · <b>${Math.round(st.r)} % · ${esc(KPI_ESTADOS[st.e])}</b>` : ''}`}</span></${link ? 'a' : 'div'}>`;
}
async function kpiInicio(root) {
  if (!root || !agoraReady() || root.querySelector('.kpi-inicio')) return;
  const ancla = root.querySelector('.tb-inicio') || root.querySelector('.operations-heading'); if (!ancla) return;
  const epoch = NAV_EPOCH;
  const cfg = await kpiCfgCargar().catch(() => null); if (!cfg || epoch !== NAV_EPOCH || !ancla.isConnected || root.querySelector('.kpi-inicio')) return;
  const lista = cfg.kpis.filter(k => k.activo && k.inicio); if (!lista.length) return;
  ancla.insertAdjacentHTML('afterend', `<section class="kpi-inicio" aria-labelledby="kpi-inicio-t"><div class="tb-inicio-h"><h2 id="kpi-inicio-t">Indicadores clave<span>Tu avance contra la meta</span></h2>${esAdmin() || esSuperAdmin() ? `<a class="btn btn-sm" href="${href('/personalizar')}" data-kpi-ir>${icon('tune')}Ajustar metas</a>` : ''}</div><div class="kpi-grid">${lista.map(k => kpiTarjeta(k, null, true)).join('')}</div></section>`);
  root.querySelector('[data-kpi-ir]')?.addEventListener('click', () => { PZ_TAB = 'indicadores'; });
  const v = await kpiValores(lista).catch(() => null);
  const box = root.querySelector('.kpi-inicio .kpi-grid'); if (!box || epoch !== NAV_EPOCH) return;
  box.innerHTML = lista.map(k => kpiTarjeta(k, kpiValor(k, v || {}), false)).join('');
}
if (V.centro) { const montar = V.centro.mounted; V.centro.mounted = function (root) { montar?.call(this, root); kpiInicio(root); }; }
/* ---------- ARP Comercial: nombre propio y chip de meta (capa sobre agora-modulos.js) ---------- */
function kpiArp(root) {
  if (!root || !agoraReady()) return;
  if (!KPI_CFG || Date.now() - KPI_CFG_T > 120000) { if (!KPI_CFG_P) kpiCfgCargar(true).then(() => root.isConnected && kpiArp(root)).catch(() => {}); if (!KPI_CFG) return; }
  const lista = KPI_CFG.kpis.filter(k => k.activo && KPI_ARP[k.fuente]); if (!lista.length) return;
  if (!KPI_VAL && !KPI_VAL_P) kpiValores(KPI_CFG.kpis).then(() => root.isConnected && kpiArp(root)).catch(() => {});
  root.querySelectorAll('.micro, .hero').forEach(el => {
    const h = el.querySelector(':scope > h3, .t-label'); if (!h) return;
    const orig = h.dataset.kpiOrig || h.textContent.trim();
    const k = lista.find(x => KPI_ARP[x.fuente].test(orig)); if (!k) return;
    const def = kpiFuente(k.fuente)?.nombre;
    if (el.matches('.micro') && h.matches('h3') && !h.children.length) {
      const quiero = k.nombre && k.nombre !== def ? k.nombre : orig;
      if (h.textContent !== quiero) { h.dataset.kpiOrig = orig; h.textContent = quiero; h.title = quiero !== orig ? 'Antes: ' + orig : ''; }
    }
    const st = KPI_VAL ? kpiEstado(k, kpiValor(k, KPI_VAL)) : null;
    let chip = el.querySelector(':scope > .kpi-chip');
    if (k.meta == null || k.meta === '') { chip?.remove(); return; }
    const txt = `${kpiMetaTxt(k)}${st ? ` · ${Math.round(st.r)} %` : ''}`, cls = `kpi-chip ${st ? 'kpi-' + st.e : ''}`;
    if (!chip) { chip = document.createElement('span'); el.append(chip); }
    if (chip.className !== cls) chip.className = cls;
    if (chip.textContent !== txt) { chip.textContent = txt; chip.title = st ? KPI_ESTADOS[st.e] : 'Sin dato para comparar'; }
  });
}

/* =====================================================================================
   3. Agencia → Gestión de usuarios (vista unificada)
   Reemplaza la tabla de 04b (que solo mostraba Master y Superadmin). El alta de usuarios de
   agencia sigue usando agencyTeamForm() y la de subcuenta agenciaUsuarioNuevo().
   ===================================================================================== */
var USR = null, USR_F = {q: '', sub: '', rol: '', est: ''};
const USR_ROL = {MASTER: 'Master · Dueño', SUPERADMIN: 'Superadmin de Agencia', ADMIN: 'Administración', SUPERVISOR: 'Supervisión', EJECUTIVO: 'Ejecutivo', REMITENTE: 'Remitente', USUARIO: 'Usuario', CONSULTA: 'Consulta', VISOR: 'Visor'};
const usrRol = r => USR_ROL[r] || r;
const usrSub = id => USR?.subcuentas?.find(s => String(s.id) === String(id))?.nombre || AGORA_CONN?.find?.(c => String(c.id) === String(id))?.name || 'Subcuenta ' + id;
function usrFiltrados() {
  const q = norm(USR_F.q || '');
  return (USR?.items || []).filter(u => (!q || norm(u.nombre + ' ' + u.correo).includes(q))
    && (!USR_F.sub || (USR_F.sub === 'agencia' ? u.ambito === 'agencia' : String(u.subcuentaId) === USR_F.sub || (u.rol === 'SUPERADMIN' && (u.tenants || []).map(String).includes(USR_F.sub))))
    && (!USR_F.rol || u.rol === USR_F.rol) && (!USR_F.est || (USR_F.est === 'activo') === !!u.activo));
}
function usrFilas() {
  const rows = usrFiltrados();
  if (!rows.length) return `<tr><td colspan="6"><p class="empty"><b>Nadie coincide</b>Prueba con otra búsqueda o quita los filtros.</p></td></tr>`;
  return rows.map(u => `<tr class="${u.activo ? '' : 'is-off'}"><td><b>${esc(u.nombre)}</b>${u.yo ? ' <span class="sc sc-gray">Tú</span>' : ''}<br><small>${esc(u.correo)}</small></td>
    <td><span class="sc ${u.rol === 'MASTER' ? 'sc-brand' : u.ambito === 'agencia' ? 'sc-info' : 'sc-gray'}">${esc(usrRol(u.rol))}</span></td>
    <td>${u.ambito === 'agencia' ? (u.rol === 'MASTER' ? 'Agencia · todas las subcuentas' : `Agencia · ${(u.tenants || []).length} ${(u.tenants || []).length === 1 ? 'subcuenta' : 'subcuentas'}<br><small>${esc((u.tenants || []).map(usrSub).join(', ') || 'Sin subcuentas')}</small>`) : esc(u.subcuenta || usrSub(u.subcuentaId))}</td>
    <td><span class="sc ${u.activo ? 'sc-ok' : 'sc-warn'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
    <td><small>${u.ultimoAcceso ? esc(String(u.ultimoAcceso).slice(0, 16).replace('T', ' ')) : 'Nunca'}</small></td>
    <td class="usr-acc">${u.editable ? `<button type="button" class="btn btn-sm" data-usr-editar="${esc(u.id)}">${icon('edit')}Editar</button>` : `<span class="src" title="${esc(u.protegido || '')}">${icon('lock')}Protegido</span>`}</td></tr>`).join('');
}
V.agencyTeam = {
  need: async () => { if (!esSuperAdmin()) return; USR = await agoraApi('agency/usuarios'); if (!Array.isArray(USR?.items)) throw new Error('Falta conectar el servicio de usuarios de Agencia.'); },
  render() {
    if (!esSuperAdmin()) return agencyRouteDenied();
    const it = USR?.items || [], subs = USR?.subcuentas || [];
    const acts = `${esMaster() ? `<button class="btn" type="button" data-team-new>${icon('plus')}Usuario de agencia</button>` : ''}<button class="btn btn-cta" type="button" data-usr-nuevo ${subs.length ? '' : 'disabled'}>${icon('plus')}Usuario de subcuenta</button>`;
    const roles = [...new Set(it.map(u => u.rol))];
    const cuerpo = USR ? `<div class="ag-resumen-grid usr-res">
        ${[['team', 'Personas', it.length, 'A tu alcance'], ['shield', 'Agencia', it.filter(u => u.ambito === 'agencia').length, 'Master y Superadmin'], ['users', 'En subcuentas', it.filter(u => u.ambito === 'subcuenta').length, `${subs.length} ${subs.length === 1 ? 'subcuenta' : 'subcuentas'}`], ['lock', 'Inactivos', it.filter(u => !u.activo).length, 'Sin acceso']].map(([ic, t, v, d]) => `<div class="ag-resumen"><span class="ag-resumen-ic" aria-hidden="true">${icon(ic)}</span><span class="ag-resumen-t">${t}</span><strong class="ag-resumen-v">${int(v)}</strong><span class="ag-resumen-d">${d}</span></div>`).join('')}</div>
      <section class="panel pf-sec">${secH('team', 'Todos los usuarios', 'Edita nombre, correo, rol, estado, subcuentas y contraseña de cualquier persona a tu alcance. Nadie edita al Master salvo el Master.')}
        <div class="usr-filtros" role="search"><label class="fld grow"><span class="sr-only">Buscar</span><input type="search" data-usr-q value="${esc(USR_F.q)}" placeholder="Buscar por nombre o correo" autocomplete="off"></label>
          <label class="fld"><span class="sr-only">Subcuenta</span><select data-usr-f="sub"><option value="">Todas las subcuentas</option><option value="agencia" ${USR_F.sub === 'agencia' ? 'selected' : ''}>Solo agencia</option>${subs.map(s => `<option value="${esc(s.id)}" ${USR_F.sub === String(s.id) ? 'selected' : ''}>${esc(s.nombre)}</option>`).join('')}</select></label>
          <label class="fld"><span class="sr-only">Rol</span><select data-usr-f="rol"><option value="">Todos los roles</option>${roles.map(r => `<option value="${esc(r)}" ${USR_F.rol === r ? 'selected' : ''}>${esc(usrRol(r))}</option>`).join('')}</select></label>
          <label class="fld"><span class="sr-only">Estado</span><select data-usr-f="est"><option value="">Activos e inactivos</option><option value="activo" ${USR_F.est === 'activo' ? 'selected' : ''}>Solo activos</option><option value="inactivo" ${USR_F.est === 'inactivo' ? 'selected' : ''}>Solo inactivos</option></select></label></div>
        <div class="tw usr-tw"><table class="usr-t"><thead><tr><th>Persona</th><th>Rol</th><th>Dónde trabaja</th><th>Estado</th><th>Último acceso</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody data-usr-filas>${usrFilas()}</tbody></table></div>
        ${USR.parcial ? '<p class="bx-help">Se muestran los primeros 1.000. Usa la búsqueda para encontrar a alguien más.</p>' : ''}
      </section>` : loading();
    return launcherFrame('agencia', head(AG_GOB_NOMBRE, 'Gestión de usuarios', 'Las personas de la agencia y de todas tus subcuentas, en un solo lugar.', acts) + cuerpo);
  },
  mounted(root) {
    root.querySelector('[data-team-new]')?.addEventListener('click', () => agencyTeamForm());
    root.querySelector('[data-usr-nuevo]')?.addEventListener('click', () => agenciaUsuarioNuevo((USR?.subcuentas || []).map(s => ({id: s.id, subcuenta: s.nombre})), USR_F.sub && USR_F.sub !== 'agencia' ? USR_F.sub : ''));
    const filas = () => { const tb = root.querySelector('[data-usr-filas]'); if (tb) { tb.innerHTML = usrFilas(); conectar(); } };
    const conectar = () => root.querySelectorAll('[data-usr-editar]').forEach(b => b.onclick = () => usrEditar(USR.items.find(u => String(u.id) === b.dataset.usrEditar)));
    root.querySelector('[data-usr-q]')?.addEventListener('input', e => { USR_F.q = e.target.value; filas(); });
    root.querySelectorAll('[data-usr-f]').forEach(s => s.onchange = () => { USR_F[s.dataset.usrF] = s.value; filas(); });
    conectar();
  },
};
function usrEditar(u) {
  if (!u) return;
  const subs = USR.subcuentas || [], master = !!USR.esMaster, esMasterU = u.rol === 'MASTER';
  const rolesAg = esMasterU ? ['MASTER'] : (u.ambito === 'agencia' || master ? ['SUPERADMIN'] : []);
  const rolesSub = esMasterU || (u.ambito === 'agencia' && !master) ? [] : USR.roles?.subcuenta || [];
  const s = openModal(modalHead('Editar usuario', esc(u.correo)) + `<form class="bx-form usr-form" novalidate>
    <div class="bx-grid">
      <fieldset class="bx"><legend>${icon('user')}Datos</legend>
        <label class="fld">Nombre<input name="nombre" maxlength="120" required value="${esc(u.nombre)}"></label>
        <label class="fld">Correo<input name="correo" type="email" maxlength="254" required value="${esc(u.correo)}"></label>
        <label class="bx-check"><input type="checkbox" name="activo" ${u.activo ? 'checked' : ''} ${u.yo || esMasterU ? 'disabled' : ''}> Usuario activo${u.yo ? ' (no puedes desactivarte)' : ''}</label>
      </fieldset>
      <fieldset class="bx"><legend>${icon('shield')}Rol y acceso</legend>
        <label class="fld">Rol<select name="rol" ${u.yo || esMasterU ? 'disabled' : ''}>${rolesAg.length ? `<optgroup label="Agencia">${rolesAg.map(r => `<option value="${r}" ${u.rol === r ? 'selected' : ''}>${esc(usrRol(r))}</option>`).join('')}</optgroup>` : ''}${rolesSub.length ? `<optgroup label="Subcuenta">${rolesSub.map(r => `<option value="${r}" ${u.rol === r ? 'selected' : ''}>${esc(usrRol(r))}</option>`).join('')}</optgroup>` : ''}</select></label>
        <label class="fld usr-local">Subcuenta<select name="subcuentaId">${subs.map(x => `<option value="${esc(x.id)}" ${String(u.subcuentaId) === String(x.id) ? 'selected' : ''}>${esc(x.nombre)}</option>`).join('')}</select></label>
        <fieldset class="tenant-choices usr-ag"><legend>Subcuentas asignadas</legend>${subs.map(x => `<label><input type="checkbox" name="tenant" value="${esc(x.id)}" ${(u.tenants || []).map(String).includes(String(x.id)) ? 'checked' : ''}>${esc(x.nombre)}</label>`).join('')}</fieldset>
        ${esMasterU ? '<p class="bx-help">El Master entra a todas las subcuentas y no cambia de rol.</p>' : ''}
      </fieldset>
      <fieldset class="bx bx-wide"><legend>${icon('lock')}Contraseña</legend>
        ${u.yo ? '<p class="bx-help">Tu propia contraseña se cambia en Mi perfil.</p>' : `<div class="usr-clave"><button type="button" class="btn btn-sm" data-usr-generar>${icon('refresh')}Generar contraseña temporal</button><span>o</span><label class="fld grow"><span class="sr-only">Nueva contraseña</span><input name="clave" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Escribe una nueva"></label><button type="button" class="btn btn-sm" data-usr-clave>Guardar contraseña</button></div><p class="usr-temporal" data-usr-temporal hidden></p><p class="bx-help">Al cambiarla se cierran sus sesiones abiertas.</p>`}
      </fieldset>
    </div>
    <p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">Queda en el registro de actividad.</p><div class="acts"><button type="button" class="btn" data-close>Cancelar</button><button type="submit" class="btn btn-cta">Guardar cambios</button></div></div></form>`, {wide: true});
  const f = s.querySelector('form'), err = f.querySelector('.fld-err');
  const ver = () => { const ag = ['MASTER', 'SUPERADMIN'].includes(f.rol.value); f.querySelector('.usr-local').hidden = ag; f.querySelector('.usr-ag').hidden = !ag || f.rol.value === 'MASTER'; };
  f.rol.onchange = ver; ver();
  const clave = async body => {
    const b = body.generar ? f.querySelector('[data-usr-generar]') : f.querySelector('[data-usr-clave]'); busy(b, true, 'Guardando…'); err.textContent = '';
    try {
      const d = await agoraApi('agency/usuarios/clave', {method: 'POST', body: {id: u.id, ...body}});
      const out = f.querySelector('[data-usr-temporal]'); out.hidden = false;
      out.innerHTML = d.temporal ? `Contraseña temporal: <code>${esc(d.temporal)}</code> <button type="button" class="btn btn-sm" data-copiar>Copiar</button><small>Compártela por un canal seguro. Solo se muestra esta vez.</small>` : 'Contraseña actualizada.';
      out.querySelector('[data-copiar]')?.addEventListener('click', () => { navigator.clipboard?.writeText(d.temporal).then(() => toast('Copiada.'), () => {}); });
      if (f.clave) f.clave.value = '';
    } catch (x) { err.textContent = x.message; } finally { busy(b, false); }
  };
  f.querySelector('[data-usr-generar]')?.addEventListener('click', () => clave({generar: true}));
  f.querySelector('[data-usr-clave]')?.addEventListener('click', () => { const v = f.clave.value; if (v.length < 8) { err.textContent = 'La contraseña debe tener al menos 8 caracteres.'; return; } clave({clave: v}); });
  f.onsubmit = async e => {
    e.preventDefault(); err.textContent = '';
    const nombre = f.nombre.value.trim(), correo = f.correo.value.trim().toLowerCase(), rol = f.rol.value, ag = ['MASTER', 'SUPERADMIN'].includes(rol);
    if (!nombre) { err.textContent = 'Escribe el nombre.'; return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) { err.textContent = 'Revisa el correo.'; return; }
    const tenants = [...f.querySelectorAll('[name=tenant]:checked')].map(x => Number(x.value));
    if (rol === 'SUPERADMIN' && !tenants.length) { err.textContent = 'Asigna al menos una subcuenta.'; return; }
    const body = {id: u.id, nombre, correo, ...(f.activo.disabled ? {} : {activo: f.activo.checked}), ...(f.rol.disabled ? {} : {rolNuevo: rol}), ...(ag ? (rol === 'SUPERADMIN' ? {tenants} : {}) : {subcuentaId: Number(f.subcuentaId.value)})};
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      const d = await agoraApi('agency/usuarios', {method: 'PUT', body});
      const i = USR.items.findIndex(x => String(x.id) === String(u.id)); if (i >= 0 && d.usuario) USR.items[i] = d.usuario;
      closeModal(); repaint(); toast('Usuario actualizado.');
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
/* =====================================================================================
   2.1.17 · Experiencia de agencia dentro de la subcuenta (equipo experiencia)
   -------------------------------------------------------------------------------------
   Capa ligera sobre lo que ya pinta cada módulo. No cambia su lógica ni sus datos:
   - Paneles y tarjetas de cifras con icono, como el Centro de mando (reusa arpTile de 04f).
   - Entrada suave y escalonada de los bloques, solo al llegar a la pantalla (no al filtrar).
   - Tablas digeribles: números a la derecha con cifras tabulares, estados como píldoras,
     etiquetas por celda para verlas como tarjetas en el celular. El motor de búsqueda,
     orden y Excel es de AgoraTabla (equipo datos): aquí solo se da formato, con clases xp-*.
   - Esqueleto donde un módulo muestra "Cargando…".
   - Franja "¿De dónde salen estos datos?" en los módulos que vienen del reporte mensual,
     con el camino para corregirlos (Configuración mensual o volver a importar).
   - Edición de tareas (PUT workspace/tareas/{id}) con el formulario de recuadros .bx.
   Todo es idempotente: cada nodo tratado queda marcado con data-xp*.
   ===================================================================================== */

/* Iconos extra para títulos que no cubre ARP_ICONOS. Lo más específico va primero. */
const XP_ICONOS = [
  [/program|cat[aá]logo|producto|rubro|acr[oó]nimo/i, 'receipt'],
  [/factur|libro|registro documental/i, 'invoice'],
  [/tendencia|mes a mes|a[nñ]o a a[nñ]o|hist[oó]ric/i, 'pulse'],
  [/observaci|calidad|corregir|revisar/i, 'warn'],
  [/n[oó]mina|cargo/i, 'team'],
  [/herramienta|servicio/i, 'gear'],
  [/periodo|per[ií]odo/i, 'calendar'],
  [/umbral|sem[aá]foro/i, 'pulse'],
  [/rol|permiso|usuario|persona/i, 'users'],
  [/convenio|contrato/i, 'invoice'],
  [/auditor|registro|movimiento|actividad/i, 'history'],
  [/tarea|pendiente|seguimiento|abierta|hecha|resuelta/i, 'check'],
  [/vencid|atrasad|para hoy/i, 'clock'],
  [/borrador|firmad|enviad|por firmar/i, 'invoice'],
];
const xpIcono = t => {
  const x = XP_ICONOS.find(([re]) => re.test(t || '')); if (x) return x[1];
  const a = typeof arpIcono === 'function' ? arpIcono(t) : 'pie';
  // Sin coincidencia: el icono del módulo, para que todo el bloque hable el mismo idioma.
  return a === 'pie' && MODS[R?.mod]?.icon ? MODS[R.mod].icon : a;
};
const xpTile = (t, cls) => `<span class="${cls}" aria-hidden="true">${icon(IC[xpIcono(t)] ? xpIcono(t) : 'pie')}</span>`;

/* Módulos cuyas cifras vienen del reporte mensual importado: cómo se corrigen. */
const XP_ORIGEN = {
  inicio: {txt: 'Las cifras salen del reporte mensual. Metas, umbrales y costos manuales se cambian en Configuración mensual; lo demás, volviendo a importar el reporte corregido.', cfg: 1},
  equipo: {txt: 'Montos, inscritos y comisiones vienen del reporte de cada asesora. Las metas por asesora se cambian en Configuración mensual; los montos, volviendo a importar el reporte.', cfg: 1},
  publicidad: {txt: 'Campañas, canales y cupos vienen del reporte mensual. La inversión en publicidad se ajusta en Configuración mensual; lo demás, volviendo a importar.', cfg: 1},
  ventas: {txt: 'Estas ventas vienen de las hojas de cada asesora en el reporte mensual. Para corregir una, vuelve a importar el reporte; las ventas nuevas se registran como venta ganada en Oportunidades o se cargan por Excel en Migración de datos.', opp: 1, mig: 1},
  facturacion: {txt: 'Estos registros vienen de los libros mensuales de facturación. Para corregir uno, vuelve a importar el libro del mes.'},
  costos: {txt: 'Los costos vienen de la estructura de costo del reporte. Puedes reemplazar publicidad, gastos y totales del mes en Configuración mensual sin tocar el archivo.', cfg: 1},
  catalogo: {txt: 'El catálogo del reporte es de solo lectura. Lo que vendes, con su precio y forma de cobro, se edita en Productos y servicios (o por Excel en Migración de datos).', prod: 1, mig: 1},
  tendencias: {txt: 'El histórico mes a mes viene del reporte. Para corregir un mes, vuelve a importar su reporte.'},
  calidad: {txt: 'Marca cada observación como resuelta aquí mismo. Si el dato estaba mal, corrígelo volviendo a importar el reporte del mes.'},
};
/** El reporte mensual se vuelve a importar en Ajustes. La Migración de datos (equipo datos, /migracion)
    carga contactos, oportunidades, productos y ventas por Excel: se ofrece donde aplica, si existe. */
const xpMigracion = () => !!(MODS.migracion && moduleAllowed('migracion') && !moduloOculto('migracion'));
function xpOrigenHtml(mod) {
  const o = XP_ORIGEN[mod]; if (!o) return '';
  const admin = typeof esAdmin === 'function' && esAdmin();
  const btn = (p, ic, l, cta) => `<a class="btn btn-sm ${cta ? 'btn-tint' : ''}" href="${href(p)}">${icon(ic)}${esc(l)}</a>`;
  const acts = [
    o.cfg && admin && MODS.configuracion && moduleAllowed('configuracion') ? btn('/configuracion', 'edit', 'Configuración mensual', 1) : '',
    o.prod && MODS.productos && moduleAllowed('productos') ? btn('/productos', 'edit', 'Productos y servicios', 1) : '',
    o.opp && MODS.oportunidades && moduleAllowed('oportunidades') ? btn('/oportunidades', 'trend', 'Ir a Oportunidades') : '',
    o.mig && admin && xpMigracion() ? btn('/migracion', 'upload', 'Migración de datos') : '',
    admin && MODS.conexiones && moduleAllowed('conexiones') ? btn('/ajustes', 'upload', 'Importar reporte en Ajustes') : '',
  ].join('');
  // En el celular queda plegada (solo el título) para no empujar el contenido hacia abajo.
  const abierta = !(typeof matchMedia === 'function' && matchMedia('(max-width: 640px)').matches);
  return `<details class="xp-origen" data-xp-origen="${esc(mod)}" ${abierta ? 'open' : ''}><summary><span class="xp-origen-ic" aria-hidden="true">${icon('info')}</span><b>¿Cómo corrijo estos datos?</b></summary>
    <p>${esc(o.txt)}${admin ? '' : ' Si ves un error, avísale a quien administra la subcuenta.'}</p>${acts ? `<div class="xp-origen-acts">${acts}</div>` : ''}</details>`;
}

/* Estados que se muestran como píldora (color + texto). */
const XP_TONO = (() => {
  const t = {};
  const add = (tono, xs) => xs.split('|').forEach(x => { t[x] = tono; });
  add('ok', 'hecha|hecho|pagado|pagada|cobrado|cobrada|activa|activo|firmado|firmada|resuelta|resuelto|ganada|ganado|al día|en meta|aprobado|aprobada|completado|completada|conectado|conectada');
  add('warn', 'pendiente|por firmar|por cobrar|en riesgo|parcial|por vencer|por aprobar|ámbar|media');
  add('bad', 'vencido|vencida|revocado|revocada|perdida|perdido|crítico|crítica|inactiva|inactivo|atrasado|atrasada|rechazado|rechazada|alta|bloqueado|bloqueada|desactivada|desactivado');
  add('info', 'enviado|enviada|abierta|abierto|en curso|nuevo|nueva|recurrente|mensual|pago único|baja');
  add('gray', 'borrador|previsto|prevista|variable|fijo|fija|sin asignar|cerrado|cerrada|archivado|venta única');
  return t;
})();
const XP_COL_ESTADO = /^(estado|situaci[oó]n|tipo|cobro|forma de cobro|prioridad)$/i;
const XP_NUM = /^[-+−]?\s*(US\$|USD|Bs\.?S?|\$|€)?\s*[-+−]?\d[\d.]*(,\d+)?\s*(%|mil|pts?)?$/i;

/** Da formato a una tabla: columnas numéricas, píldoras de estado y etiquetas por celda. */
function xpTabla(tb) {
  if (tb.closest('.scrim, .modal') || tb.classList.contains('perm')) return;
  const filas = [...tb.tBodies].flatMap(b => [...b.rows]).filter(r => !r.querySelector('td[colspan]'));
  const ths = tb.tHead ? [...(tb.tHead.rows[tb.tHead.rows.length - 1]?.cells || [])] : [];
  const primera = !tb.dataset.xp;
  if (primera) {
    tb.dataset.xp = '1'; tb.classList.add('xp-tbl');
    const cols = Math.max(ths.length, ...filas.slice(0, 3).map(r => r.cells.length), 0);
    // Pocas columnas y sin campos: en el celular se ve como tarjetas. Si no, desplazamiento contenido.
    const conCampos = !!tb.querySelector('input, select, textarea');
    tb.classList.add(cols && cols <= 6 && !conCampos && !tb.classList.contains('heat') && ths.length ? 'xp-cards' : 'xp-scroll');
  }
  // Columnas numéricas que no vienen marcadas (.n): se alinean a la derecha si casi todo es número.
  const numCol = ths.map((th, i) => {
    if (th.classList.contains('n')) return true;
    const vals = filas.map(r => r.cells[i]).filter(Boolean).map(c => (c.childElementCount ? (c.firstChild?.textContent || '') : c.textContent).trim()).filter(v => v && v !== '—');
    return vals.length > 0 && vals.filter(v => XP_NUM.test(v)).length / vals.length >= .8;
  });
  ths.forEach((th, i) => { if (numCol[i]) th.classList.add('xp-n'); });
  const estadoCol = ths.map(th => XP_COL_ESTADO.test((th.textContent || '').replace(/[↑↓]/g, '').trim()));
  filas.forEach(r => {
    if (r.dataset.xp) return; r.dataset.xp = '1';
    [...r.cells].forEach((td, i) => {
      const th = ths[i]; const l = th ? (th.textContent || '').replace(/[↑↓]/g, '').trim() : '';
      if (l) td.dataset.th = l;
      if (numCol[i]) td.classList.add('xp-n');
      if (!td.childElementCount) {
        const v = (td.textContent || '').trim(), tono = XP_TONO[v.toLowerCase()];
        if (v && v.length <= 24 && (tono && (estadoCol[i] || ['ok', 'warn', 'bad'].includes(tono) && !numCol[i] && i > 0))) {
          td.innerHTML = `<span class="sc sc-${tono} xp-chip">${esc(v)}</span>`;
        }
      }
    });
  });
}

/** Tareas: botón Editar junto a "Marcar hecha" / "Reabrir". */
function xpTareasEditar(root) {
  if (R.view !== 'tareas') return;
  root.querySelectorAll('[data-tarea-hecha], [data-tarea-abrir]').forEach(b => {
    const td = b.closest('td'); if (!td || td.querySelector('[data-xp-tarea]')) return;
    const id = b.dataset.tareaHecha || b.dataset.tareaAbrir;
    td.classList.add('xp-acts'); if (!b.title) b.title = b.getAttribute('aria-label') || b.textContent.trim();
    b.insertAdjacentHTML('beforebegin', `<button class="btn btn-sm xp-edit" type="button" data-xp-tarea="${esc(id)}" aria-label="Editar tarea" title="Editar tarea">${icon('edit')}</button>`);
  });
}

let XP_RUTA = '', XP_SK = false, XP_OBS = null, XP_RAF = 0;
const xpEnCuenta = () => !!ME && !!R?.mod && !(typeof esSuperAdmin === 'function' && esSuperAdmin() && AGENCY_CONTEXT);

/** Aplica la experiencia de agencia a lo que acaba de pintar un módulo. */
function xpEsqueleto() {
  const app = document.getElementById('app'); if (!app) return;
  const raiz = document.documentElement, cuenta = xpEnCuenta();
  if (cuenta) { raiz.dataset.xp = 'cuenta'; app.dataset.xpMod = R.mod; } else { delete raiz.dataset.xp; delete app.dataset.xpMod; }
  // "Cargando…" de cualquier módulo (incluido el paquete ARP): esqueleto en vez de texto.
  app.querySelectorAll('p, span, div, li, small, td').forEach(el => {
    if (el.dataset.xpCarga || el.childElementCount || !/^Cargando[^.]{0,50}(…|\.\.\.)?$/.test((el.textContent || '').trim())) return;
    el.dataset.xpCarga = '1'; el.classList.add('xp-cargando'); el.setAttribute('role', 'status');
  });
  if (!cuenta) return;
  const vista = app.lastElementChild; if (!vista) return;
  const ag = vista.querySelector('#view.ag');
  // Franja de origen de los datos, debajo de la cabecera del módulo.
  if (XP_ORIGEN[R.mod] && typeof TB !== 'undefined' && TB.estado === 'listo' && !vista.querySelector('[data-xp-origen]')) {
    const h = vista.querySelector(':scope > .mod-h') || vista.querySelector('.mod-h');
    if (h) h.insertAdjacentHTML('afterend', xpOrigenHtml(R.mod));
  }
  if (!ag) {
    // Paneles: icono en recuadro junto al título (como secH en la agencia).
    vista.querySelectorAll('.panel > .panel-h:not([data-xp])').forEach(ph => {
      ph.dataset.xp = '1'; const h = ph.querySelector('h2'); if (!h || ph.querySelector('.arp-ph-ic')) return;
      ph.classList.add('xp-ph'); ph.insertAdjacentHTML('afterbegin', xpTile(h.textContent, 'arp-ph-ic'));
    });
    // Tarjetas de cifras: icono + rótulo arriba, como las tarjetas de resumen de la agencia.
    vista.querySelectorAll('.micro:not([data-xp])').forEach(m => {
      const h = m.querySelector(':scope > h3'); if (!h || m.querySelector('.sk')) return;
      m.dataset.xp = '1'; if (m.querySelector('.arp-mic-ic')) return;
      m.classList.add('xp-mic'); h.insertAdjacentHTML('beforebegin', xpTile(h.textContent, 'arp-mic-ic'));
    });
  }
  // Contactos sin ficha abierta: estado vacío con explicación.
  vista.querySelectorAll('.records-record').forEach(rr => {
    const vacia = !rr.textContent.trim() && !rr.querySelector('img, form, button');
    rr.classList.toggle('xp-vacio', vacia);
  });
  vista.querySelectorAll('table').forEach(xpTabla);
  xpTareasEditar(vista);
  // Entrada escalonada: al llegar a la pantalla o cuando el esqueleto da paso a los datos.
  const ruta = R.mod + '|' + path(), conSk = !!vista.querySelector('.sk');
  const animar = ruta !== XP_RUTA || (XP_SK && !conSk);
  XP_RUTA = ruta; XP_SK = conSk;
  if (!ag && animar && !conSk) {
    let i = 0;
    vista.querySelectorAll(':scope > .mod-h, :scope > .xp-origen, :scope > .grid > *, :scope > .panel, :scope > section, :scope > form > .grid > *, :scope > form > .panel, :scope > .ag-resumen-grid > *, :scope > .tb-inicio, :scope > .hx').forEach(el => {
      if (el.dataset.xpIn) return; el.dataset.xpIn = '1'; el.style.setProperty('--xp-i', String(Math.min(i++, 10)));
    });
  }
}
function xpVigilar() {
  const app = document.getElementById('app'); if (!app || XP_OBS) return;
  XP_OBS = new MutationObserver(() => {
    cancelAnimationFrame(XP_RAF);
    XP_RAF = requestAnimationFrame(() => { XP_OBS.disconnect(); try { xpEsqueleto(); } catch (e) { console.warn('[experiencia]', e); } XP_OBS.observe(app, {childList: true, subtree: true}); });
  });
  XP_OBS.observe(app, {childList: true, subtree: true});
  xpEsqueleto();
}
if (typeof document !== 'undefined' && document.readyState !== undefined) {
  if (document.readyState === 'loading') document.addEventListener?.('DOMContentLoaded', xpVigilar); else setTimeout(xpVigilar, 0);
}

/* =====================================================================================
   Tareas: crear y editar con el mismo formulario de recuadros (.bx)
   ===================================================================================== */
function xpTareaModal(previa) {
  const hoy = hoyYMD(), editar = !!previa?.id;
  const s = openModal(modalHead(editar ? 'Editar tarea' : 'Nueva tarea', editar ? 'Corrige lo que haga falta. El cambio queda en la auditoría.' : 'Queda a nombre de quien la tiene que hacer, con su fecha.') +
    `<form class="bx-form" novalidate><div class="bx-grid">
      <fieldset class="bx bx-wide"><legend>${icon('check')}Tarea</legend>
        <label class="fld"><span class="fld-l">¿Qué hay que hacer? <span class="req" aria-hidden="true">*</span></span><input name="titulo" maxlength="250" required autofocus value="${esc(previa?.titulo || '')}" placeholder="Llamar para confirmar la inscripción"></label>
        <label class="fld">Detalle<textarea name="detalle" maxlength="2000" rows="2" placeholder="Opcional">${esc(previa?.detalle || '')}</textarea></label>
      </fieldset>
      <fieldset class="bx"><legend>${icon('calendar')}Cuándo</legend>
        <div class="bx-row">
          <label class="fld">Fecha<input name="vence" type="date" value="${esc(previa?.vence || (editar ? '' : hoy))}"></label>
          <label class="fld">Hora<input name="hora" type="time" value="${esc(previa?.hora || (editar ? '' : '09:00'))}"></label>
        </div>
      </fieldset>
      <fieldset class="bx"><legend>${icon('team')}${editar ? 'Estado' : 'Responsable'}</legend>
        ${editar
          ? `<label class="fld">Estado<select name="estado"><option value="pendiente" ${previa.estado !== 'hecha' ? 'selected' : ''}>Pendiente</option><option value="hecha" ${previa.estado === 'hecha' ? 'selected' : ''}>Hecha</option></select></label><p class="bx-help">Responsable: <b>${esc(previa.responsable || 'Sin asignar')}</b></p>`
          : `<label class="fld">Responsable<input name="responsable" maxlength="120" value="${esc(previa?.responsable || quienSoy())}"></label>`}
      </fieldset>
    </div><p class="fld-err" role="alert"></p>
    <div class="bx-foot"><p class="bx-help">${editar && previa.contacto ? 'Contacto: ' + esc(previa.contacto) : 'Los campos con <span class="req">*</span> son obligatorios.'}</p><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${editar ? 'Guardar cambios' : 'Guardar tarea'}</button></div></div></form>`, {wide: true});
  const f = s.querySelector('form'), err = f.querySelector('.fld-err');
  f.onsubmit = async ev => {
    ev.preventDefault();
    const fd = Object.fromEntries(new FormData(f));
    if (!String(fd.titulo || '').trim()) { err.textContent = 'Escribe qué hay que hacer.'; f.titulo.focus(); return; }
    if (!editar && fd.vence && fd.vence < hoy) { err.textContent = 'La fecha no puede ser anterior a hoy.'; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      if (editar) await agoraApi(`workspace/tareas/${encodeURIComponent(previa.id)}`, {method: 'PUT', body: {...fd, titulo: fd.titulo.trim(), ...(previa.version ? {version: previa.version} : {})}});
      else await agoraApi('workspace/tareas', {method: 'POST', body: {...fd, titulo: fd.titulo.trim(), origen: previa?.origen || 'manual', contactId: previa?.contactId || null, conversationId: previa?.conversationId || null}});
      closeModal(); await cargarTareas(true); repaint(); toast(editar ? 'Tarea actualizada.' : 'Tarea guardada.');
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}
// Todas las llamadas existentes (botón, paleta de comandos, recordatorios) usan ya este formulario.
if (typeof tareaModal === 'function') tareaModal = xpTareaModal;
document.addEventListener?.('click', e => {
  const b = e.target.closest?.('[data-xp-tarea]'); if (!b) return;
  const t = (DATOS.tareas || []).find(x => String(x.id) === b.dataset.xpTarea);
  if (t) xpTareaModal(t); else toast('No encontramos esa tarea. Recarga la página.');
});
/* =====================================================================================
   2.1.19 · Tablas editables y «Agregar manual»
   -------------------------------------------------------------------------------------
   Cada tabla de datos (los «trackers»: facturación, ventas, costos, catálogo, equipo, tareas,
   productos…) tiene una sola lista de columnas que manda en todo:
     · lo que se ve en la tabla (nombre, orden, columnas ocultas y columnas propias),
     · el Excel que se descarga, la plantilla vacía y la carga masiva,
     · el formulario de «Agregar manual».
   Dos fuentes, según el tipo de tabla:
     · Tablas de registros (Contactos, Oportunidades, Productos, Ventas registradas, Tareas): las
       columnas son los campos de Migración de datos (workspace/campos). Agregar manual crea el
       registro de verdad (workspace/migracion/importar, una fila) y la carga masiva es la de Migración.
     · Tablas del reporte (Facturación, Ventas del mes, Costos, Catálogo, Equipo…): las columnas viven en
       workspace/tablas (ver servidor Controllers/Tablas.php). Las filas agregadas a mano y las cargadas
       por Excel se guardan aparte y se muestran en la tabla del mes con la marca «A mano».
   Se engancha en AgoraTabla.mejorar (04h): TablaEd.preparar() transforma la tabla antes de que la
   barra la lea, y TablaEd.* da el editor de columnas, el menú de cada columna y el menú Excel.
   ===================================================================================== */
const TE_COLS = ['inicio', 'ventas', 'facturacion', 'costos', 'catalogo', 'equipo', 'publicidad', 'tendencias', 'calidad', 'productos', 'tareas', 'convenios', 'conConvenio', 'contactos', 'oportunidades'];
const TE_MANUAL = ['ventas', 'facturacion', 'costos', 'catalogo', 'equipo', 'publicidad', 'productos', 'tareas', 'contactos', 'oportunidades'];
const TE_TIPOS = {texto: 'Texto', numero: 'Número', fecha: 'Fecha', correo: 'Correo', telefono: 'Teléfono', lista: 'Lista de opciones', si_no: 'Sí o no'};
const TE_LOTE = 500;
const TE = {cache: {}, cargando: {}, errores: {}};
window.addEventListener?.('agora:account-changing', () => { TE.cache = {}; TE.cargando = {}; TE.errores = {}; });

const teNorm = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
const teHash = s => { let a = 5381, b = 52711; for (const ch of String(s)) { const c = ch.codePointAt(0); a = (((a << 5) + a) + c) >>> 0; b = (((b << 5) + b) ^ c) >>> 0; } return (a.toString(36) + b.toString(36)).replace(/[^a-z0-9]/g, '').padEnd(10, '0').slice(0, 10); };
const tePeriodo = mod => (MODS[mod]?.periodo && typeof PERIODO !== 'undefined' && /^\d{4}-\d{2}$/.test(PERIODO || '')) ? PERIODO : '';
const teAdmin = () => { try { return esAdmin(); } catch { return false; } };
const tePuedeEscribir = () => { const rol = (typeof AGORA_ME !== 'undefined' && AGORA_ME?.rol) || (typeof ME !== 'undefined' && ME?.rol) || ''; return rol !== 'USUARIO' && rol !== 'CONSULTA'; };
const teTituloTh = th => { if (!th) return ''; const c = th.cloneNode(true); c.querySelectorAll('.te-thm, .sr-only, svg').forEach(x => x.remove()); return (c.textContent || '').replace(/[↑↓]/g, '').replace(/\s+/g, ' ').trim(); };
const teEsAccion = t => !t || /^acciones?$/i.test(t);
/** Módulo de registros de la tabla (o del encabezado), o null si es una tabla del reporte. */
function teRegistro(t, mod = R?.mod) {
  const m = typeof MIG_DE_MOD !== 'undefined' ? MIG_DE_MOD[mod] : null;
  if (!m || m === 'usuarios') return null;
  if (mod === 'ventas') { const raiz = t || document.getElementById('app'); if (!(t ? t.closest('.vg-tabla') : raiz?.querySelector('.vg-tabla'))) return null; }
  return m;
}
/** Formato legible de un valor guardado. */
function teFmt(c, v) {
  if (v == null || v === '') return '';
  if (c?.tipo === 'si_no' || typeof v === 'boolean') return v === true || v === 'true' ? 'Sí' : v === false || v === 'false' ? 'No' : String(v);
  if (c?.tipo === 'numero' && typeof v === 'number') return v.toLocaleString('es-VE', {maximumFractionDigits: 2});
  if (c?.tipo === 'fecha' && /^\d{4}-\d{2}-\d{2}$/.test(v)) { const [y, m, d] = v.split('-'); return `${d}/${m}/${y}`; }
  return String(v);
}
/** [valor, error] de una celda según su tipo (la misma regla de Migración y del servidor). */
function teCelda(c, v) {
  if (typeof migCelda === 'function') return migCelda({tipo: c.tipo, requerido: !!c.requerido, opciones: c.opciones}, v);
  if (v == null || v === '') return [null, c.requerido ? 'Es obligatorio.' : null];
  return [String(v), null];
}

/* ---------- Datos del servidor: columnas, filas manuales y valores, por módulo y mes ---------- */
function teLlave(mod) { return mod + '|' + tePeriodo(mod); }
function teCargar(mod, force) {
  const k = teLlave(mod);
  if (TE.cache[k] && !force) return Promise.resolve(TE.cache[k]);
  if (TE.cargando[k]) return TE.cargando[k];
  const per = tePeriodo(mod);
  TE.cargando[k] = agoraApi(`workspace/tablas?modulo=${encodeURIComponent(mod)}${per ? '&periodo=' + per : ''}`)
    .then(d => { TE.cache[k] = {tablas: d.tablas || {}, filas: d.filas || {}, valores: d.valores || {}, canEdit: !!d.canEdit}; delete TE.errores[k]; return TE.cache[k]; })
    .catch(e => { TE.errores[k] = e.message || 'No se pudo cargar'; TE.cache[k] = {tablas: {}, filas: {}, valores: {}, canEdit: false, error: true}; return TE.cache[k]; })
    .finally(() => { delete TE.cargando[k]; });
  return TE.cargando[k];
}
/** Vuelve a pintar el módulo para que la tabla tome los cambios. */
function teRefrescar() {
  try { if (typeof R !== 'undefined' && String(R.view).startsWith('tc_') && typeof TB !== 'undefined' && TB.api && document.getElementById('region') && !document.querySelector('#app .vg-tabla')) { TB.region(); return; } } catch { /* sigue con el repintado completo */ }
  try { repaint(); } catch { render(); }
}

/* ---------- Columnas de una tabla del reporte ---------- */
/** base = [{titulo, tipo, pos}] (las columnas que trae el módulo, sin la de acciones). */
function teColumnas(base, guardadas) {
  const ids = [], vistos = {};
  base.forEach((b, i) => { let id = 'b:' + (teNorm(b.titulo).slice(0, 56) || 'c' + i); if (vistos[id]) id = id.slice(0, 56) + (vistos[id]++ + 1); else vistos[id] = 1; ids.push(id); });
  const fab = base.map((b, i) => ({id: ids[i], base: b.titulo, titulo: b.titulo, tipo: b.tipo || 'texto', opciones: null, visible: true, requerido: false, propia: false, pos: b.pos}));
  if (!Array.isArray(guardadas) || !guardadas.length) return fab;
  const out = [];
  for (const g of guardadas) {
    if (String(g.id).startsWith('cf_')) out.push({id: g.id, base: null, titulo: g.titulo, tipo: g.tipo || 'texto', opciones: g.opciones || null, visible: g.visible !== false, requerido: !!g.requerido, propia: true});
    else { const i = ids.indexOf(g.id); if (i >= 0) out.push({...fab[i], titulo: g.titulo || fab[i].titulo, tipo: g.tipo || fab[i].tipo, opciones: g.opciones || null, visible: g.visible !== false, requerido: !!g.requerido}); }
  }
  fab.forEach((c, i) => { if (out.some(x => x.id === c.id)) return; const prev = i ? out.findIndex(x => x.id === fab[i - 1].id) : -1; out.splice(prev + 1, 0, c); });
  return out;
}
const teSalida = cols => cols.map((c, i) => ({id: c.id, base: c.base, titulo: c.titulo.trim(), tipo: c.tipo, opciones: c.opciones, visible: c.visible, requerido: !!c.requerido, orden: i}));

/** Cambia el texto del encabezado sin tocar la flecha de orden ni los botones. */
function teRenombrar(th, viejo, nuevo) {
  if (!th || !nuevo || viejo === nuevo) return;
  const w = document.createTreeWalker(th, NodeFilter.SHOW_TEXT, {acceptNode: n => n.parentElement?.closest('.te-thm, .sr-only') ? NodeFilter.FILTER_REJECT : (n.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP)});
  const n = w.nextNode(); if (!n) { th.prepend(nuevo); return; }
  const i = n.nodeValue.toLowerCase().indexOf(viejo.toLowerCase());
  n.nodeValue = i >= 0 ? n.nodeValue.slice(0, i) + nuevo + n.nodeValue.slice(i + viejo.length) : nuevo;
  th.dataset.teTitulo = nuevo;
}

/* =====================================================================================
   TablaEd: lo que usa AgoraTabla (04h)
   ===================================================================================== */
const TablaEd = {
  /** ¿La tabla se mejora aunque tenga pocas filas? (para que «Agregar manual» exista con la tabla vacía). */
  siempre(t, mod) { return TE_MANUAL.includes(mod) && !!t.closest?.('#app'); },
  /**
   * Transforma la tabla antes de que AgoraTabla la lea. Devuelve el «editor» de esa tabla o null.
   * La primera vez que se abre un módulo pide los datos al servidor y, si hay algo que mostrar, repinta.
   */
  preparar(t, {modulo, nombre}) {
    if (!TE_COLS.includes(modulo) || t.closest('.modal, .scrim, form')) return null;
    const ths = tablaCabeceras(t); if (ths.length < 2) return null;
    const titulos = ths.map(teTituloTh);
    const reg = teRegistro(t, modulo);
    if (reg) return TablaEd.prepararRegistro(t, {modulo, nombre, reg, ths, titulos});
    const base = titulos.map((titulo, pos) => ({titulo, pos})).filter(b => !teEsAccion(b.titulo));
    if (!base.length) return null;
    const clave = modulo + '.' + teHash(base.map(b => teNorm(b.titulo)).join('|'));
    const k = teLlave(modulo), est = TE.cache[k];
    if (!est) { teCargar(modulo).then(d => { if ((d.tablas[clave] || d.filas[clave]?.length || d.valores[clave]) && document.body.contains(t)) teRefrescar(); }); }
    const datos = est || {tablas: {}, filas: {}, valores: {}};
    const filasBase = tablaFilas(t);
    // Tipo de cada columna del módulo, según sus datos (para validar lo que se agrega a mano).
    base.forEach(b => {
      const vals = filasBase.map(r => tablaTexto(r.cells[b.pos])).filter(Boolean).slice(0, 30);
      const num = v => tablaNumero(String(v).replace(/US\$|Bs\.?S?|USD|VES|EUR|€|\$|%/gi, '').trim());
      b.tipo = !vals.length ? 'texto' : vals.filter(v => tablaFecha(v) != null).length >= vals.length * .8 ? 'fecha' : vals.filter(v => num(v) != null).length >= vals.length * .8 ? 'numero' : 'texto';
    });
    const cols = teColumnas(base, datos.tablas[clave]?.columnas);
    const manuales = datos.filas[clave] || [], valores = datos.valores[clave] || {};
    const acciones = titulos.map((x, i) => teEsAccion(x) ? i : -1).filter(i => i >= 0);
    const propias = cols.filter(c => c.propia);
    const firmas = new Map();
    // 1. Filas del reporte: firma (para los valores de columnas propias) y celdas nuevas.
    for (const r of filasBase) {
      const f = teHash(base.map(b => tablaTexto(r.cells[b.pos])).join('|'));
      firmas.set(r, f); r.dataset.teFirma = f;
    }
    // 2. Filas agregadas a mano (con los mismos lugares que las del reporte).
    const body = t.tBodies[0] || t.createTBody();
    body.querySelectorAll('tr').forEach(r => { if (r.querySelector('td.empty') && manuales.length) r.remove(); });
    for (const m of manuales) {
      const tr = document.createElement('tr'); tr.className = 'te-manual'; tr.dataset.teFila = m.id; tr.tabIndex = 0; tr.title = 'Agregada a mano · toca para editarla';
      titulos.forEach((x, i) => { const td = document.createElement('td'); const c = cols.find(y => y.pos === i); if (c) { td.textContent = teFmt(c, m.datos?.[c.id]); if (c.tipo === 'numero') td.className = 'n'; } tr.append(td); });
      body.append(tr);
    }
    // 3. Orden final: columnas en el orden guardado, columnas propias en su lugar y acciones al final.
    const reordenar = (tr, celda) => {
      const cells = [...tr.cells]; if (cells.length !== titulos.length) return;
      const frag = cols.map(c => c.propia ? celda(c) : cells[c.pos]); acciones.forEach(i => frag.push(cells[i]));
      tr.append(...frag.filter(Boolean));
    };
    const trh = t.tHead.rows[t.tHead.rows.length - 1];
    reordenar(trh, c => { const th = document.createElement('th'); th.textContent = c.titulo; th.dataset.teCol = c.id; if (c.tipo === 'numero') th.className = 'n'; return th; });
    for (const r of [...body.rows]) {
      if (r.cells.length === 1 && r.cells[0].colSpan > 1) { r.cells[0].colSpan = titulos.length + propias.length; continue; }
      const man = manuales.find(m => m.id === r.dataset.teFila), f = firmas.get(r);
      reordenar(r, c => { const td = document.createElement('td'); const v = man ? man.datos?.[c.id] : valores[f]?.[c.id]; td.textContent = teFmt(c, v); td.className = 'te-cf' + (c.tipo === 'numero' ? ' n' : ''); if (!man) { td.dataset.teCelda = c.id; td.title = 'Toca para escribir'; } return td; });
    }
    // 4. Nombres nuevos y marca «A mano».
    cols.forEach(c => { if (!c.propia && c.titulo !== c.base) teRenombrar(ths[c.pos], c.base, c.titulo); if (!c.propia) ths[c.pos].dataset.teCol = c.id; });
    body.querySelectorAll('tr.te-manual').forEach(tr => { const td = tr.cells[0]; if (td) td.insertAdjacentHTML('afterbegin', '<span class="te-chip" aria-hidden="true">A mano</span>'); });
    return TablaEd.editor({t, modulo, nombre, clave, cols, manuales, valores, base, tipo: 'tabla'});
  },

  /** Tablas de registros: las columnas son los campos del módulo (Migración de datos). */
  prepararRegistro(t, {modulo, nombre, reg, ths, titulos}) {
    const info = typeof CAMPOS_CACHE !== 'undefined' ? CAMPOS_CACHE[reg] : null;
    if (!info) { if (typeof camposDe === 'function') camposDe(reg).then(cs => { if (cs.some(c => c.tituloBase && c.titulo !== c.tituloBase || !c.visible) && document.body.contains(t)) teRefrescar(); }).catch(() => {}); }
    const campos = info?.campos || [];
    const deTh = {};
    titulos.forEach((x, i) => { const n = teNorm(x); const c = campos.find(c => teNorm(c.tituloBase || '') === n || teNorm(c.titulo) === n); if (c) deTh[i] = c; });
    Object.entries(deTh).forEach(([i, c]) => { if (c.titulo !== titulos[i]) teRenombrar(ths[i], titulos[i], c.titulo); ths[i].dataset.teCol = c.clave; });
    const cols = campos.map(c => ({id: c.clave, base: c.tituloBase, titulo: c.titulo, tipo: c.tipo, opciones: c.opciones, visible: c.visible, requerido: c.requerido, propia: !!c.personalizado, bloqueado: !!c.bloqueado}));
    return TablaEd.editor({t, modulo, nombre, clave: 'campos.' + reg, cols, reg, deTh, tipo: 'campos'});
  },

  /** El objeto que usa la barra de la tabla. */
  editor(ctx) {
    const ed = {
      ...ctx,
      ocultas() {
        if (ctx.tipo === 'campos') return Object.entries(ctx.deTh || {}).filter(([, c]) => !c.visible).map(([i]) => teTituloTh(tablaCabeceras(ctx.t)[i]));
        return ctx.cols.filter(c => !c.visible).map(c => c.titulo);
      },
      puedeEditar: () => teAdmin(),
      manual: () => TE_MANUAL.includes(ctx.modulo) && tePuedeEscribir(),
      /** Columna (de la lista) que corresponde a una columna de la tabla, por su id. */
      colDeTh(th) { const id = th?.dataset?.teCol; return id ? ctx.cols.find(c => c.id === id) : null; },
    };
    return ed;
  },
};
window.TablaEd = TablaEd;

/* =====================================================================================
   Guardar columnas (las dos fuentes)
   ===================================================================================== */
async function teGuardarCols(ed, cols) {
  if (ed.tipo === 'campos') {
    const d = await agoraApi('workspace/campos', {method: 'PUT', body: {modulo: ed.reg, campos: cols.map((c, i) => ({clave: c.id, titulo: c.titulo.trim(), tipo: c.tipo, opciones: c.opciones, orden: i, visible: c.visible, requerido: !!c.requerido, personalizado: !!c.propia}))}});
    if (typeof CAMPOS_CACHE !== 'undefined') CAMPOS_CACHE[ed.reg] = d;
    if (typeof MIG !== 'undefined') { MIG.info[ed.reg] = d; if (MIG.edit?.mod === ed.reg) MIG.edit = null; }
    return;
  }
  const d = await agoraApi('workspace/tablas', {method: 'PUT', body: {clave: ed.clave, modulo: ed.modulo, columnas: teSalida(cols)}});
  const est = TE.cache[teLlave(ed.modulo)]; if (est) est.tablas[ed.clave] = d.tabla;
}
async function teRestaurarCols(ed) {
  if (ed.tipo === 'campos') {
    const d = await agoraApi('workspace/campos', {method: 'PUT', body: {modulo: ed.reg, restaurar: true}});
    if (typeof CAMPOS_CACHE !== 'undefined') CAMPOS_CACHE[ed.reg] = d;
    if (typeof MIG !== 'undefined') { MIG.info[ed.reg] = d; MIG.edit = null; }
    return;
  }
  await agoraApi('workspace/tablas', {method: 'PUT', body: {clave: ed.clave, modulo: ed.modulo, restaurar: true}});
  const est = TE.cache[teLlave(ed.modulo)]; if (est) delete est.tablas[ed.clave];
}
function teNuevaClave(cols, titulo) { const base = 'cf_' + (teNorm(titulo).slice(0, 28) || 'columna'); let k = base, n = 2; while (cols.some(c => c.id === k)) k = base + '_' + n++; return k; }
function teNombreLibre(cols, titulo, menos) { return !cols.some(c => c !== menos && teNorm(c.titulo) === teNorm(titulo)); }

/* =====================================================================================
   Editor de columnas (modal): nombre, orden, mostrar u ocultar, agregar y quitar columnas
   ===================================================================================== */
/** Las tablas de registros cargan sus campos la primera vez que se abre el editor o el menú. */
async function teAsegurar(ed) {
  if (ed.tipo !== 'campos' || typeof camposDe !== 'function') return ed;
  const cs = await camposDe(ed.reg);
  ed.cols = cs.map(c => ({id: c.clave, base: c.tituloBase, titulo: c.titulo, tipo: c.tipo, opciones: c.opciones, visible: c.visible, requerido: c.requerido, propia: !!c.personalizado, bloqueado: !!c.bloqueado}));
  return ed;
}
async function teEditorColumnas(ed, {agregarEn = null, foco = null} = {}) {
  try { await teAsegurar(ed); } catch (x) { toast(x.message); return; }
  const cols = ed.cols.map(c => ({...c, opciones: c.opciones ? [...c.opciones] : null}));
  const titulo = ed.tipo === 'campos' ? `Columnas de ${esc(MODS[ed.modulo]?.name || '')}` : `Columnas de la tabla${ed.nombre ? ' «' + esc(ed.nombre) + '»' : ''}`;
  const ayuda = ed.tipo === 'campos'
    ? 'Son los campos del módulo: mandan en la tabla, el formulario «Agregar manual», la plantilla de Excel y la carga masiva.'
    : 'Lo que cambies aquí se usa en la tabla, en el Excel que descargas, en la plantilla para la carga masiva y en el formulario «Agregar manual».';
  let sucio = false;
  const s = openModal(modalHead(titulo, ayuda) + `<div class="te-ed">
      <ol class="te-cols" aria-label="Columnas"></ol>
      <form class="te-add bx" novalidate>
        <p class="te-add-h">${icon('plus')}Agregar columna</p>
        <div class="te-add-g">
          <label class="fld">Nombre<input name="titulo" maxlength="60" placeholder="Ej: Sede, Cédula, Observación"></label>
          <label class="fld">Tipo de dato<select name="tipo">${Object.entries(TE_TIPOS).map(([k, x]) => `<option value="${k}">${x}</option>`).join('')}</select></label>
          <label class="fld te-opc" hidden>Opciones<input name="opciones" placeholder="Separadas por coma: Caracas, Valencia"></label>
          <button type="submit" class="btn btn-tint">${icon('plus')}Agregar</button>
        </div>
        <p class="fld-err" role="alert"></p>
      </form>
      <p class="fld-err te-err" role="alert"></p>
      <div class="bx-foot"><button type="button" class="btn" data-te-rest>${icon('sync')}Restaurar</button><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="button" data-te-ok>Guardar columnas</button></div></div>
    </div>`, {wide: true});
  const lista = s.querySelector('.te-cols'), err = s.querySelector('.te-err'), add = s.querySelector('.te-add');
  const pintar = (enfocar) => {
    lista.innerHTML = cols.map((c, i) => `<li class="te-col ${c.visible ? '' : 'is-off'}" data-i="${i}" draggable="true">
      <span class="mg-asa" aria-hidden="true" title="Arrastra para ordenar">⋮⋮</span><span class="mg-n">${i + 1}</span>
      <label class="te-tit"><span class="sr-only">Nombre de la columna ${i + 1}</span><input value="${esc(c.titulo)}" maxlength="60" data-te-t="${i}">${c.base && c.base !== c.titulo ? `<small>Antes: ${esc(c.base)}</small>` : c.propia ? '<small>Columna propia</small>' : ''}</label>
      <span class="sc ${c.propia ? 'sc-info' : 'sc-gray'}" title="${esc(c.opciones ? 'Opciones: ' + c.opciones.join(', ') : '')}">${esc(TE_TIPOS[c.tipo] || 'Texto')}</span>
      <label class="mg-sw" title="${c.bloqueado ? 'Esta columna siempre se muestra' : 'Mostrar u ocultar'}"><input type="checkbox" data-te-v="${i}" ${c.visible ? 'checked' : ''} ${c.bloqueado ? 'disabled' : ''}><span>Visible</span></label>
      <label class="mg-sw" title="${c.bloqueado ? 'Ágora la necesita siempre' : 'El formulario y la carga avisan si viene vacía'}"><input type="checkbox" data-te-r="${i}" ${c.requerido ? 'checked' : ''} ${c.bloqueado ? 'disabled' : ''}><span>Obligatoria</span></label>
      <span class="mg-flechas"><button type="button" class="icon-btn" data-te-up="${i}" aria-label="Subir ${esc(c.titulo)}" ${i ? '' : 'disabled'}>${icon('chev')}</button><button type="button" class="icon-btn" data-te-down="${i}" aria-label="Bajar ${esc(c.titulo)}" ${i < cols.length - 1 ? '' : 'disabled'}>${icon('chev')}</button>${c.propia ? `<button type="button" class="icon-btn te-del" data-te-del="${i}" aria-label="Quitar ${esc(c.titulo)}" title="Quitar columna">${icon('trash')}</button>` : ''}</span>
    </li>`).join('');
    if (enfocar != null) lista.querySelector(`[data-i="${enfocar}"] input[data-te-t]`)?.focus();
  };
  const mover = (i, j) => { if (j < 0 || j >= cols.length) return; const [c] = cols.splice(i, 1); cols.splice(j, 0, c); sucio = true; pintar(); lista.querySelector(`[data-i="${j}"] .mg-flechas button:not([disabled])`)?.focus(); };
  lista.addEventListener('input', e => { const i = e.target.dataset.teT; if (i != null) { cols[+i].titulo = e.target.value; sucio = true; } });
  lista.addEventListener('change', e => {
    const v = e.target.dataset.teV, r = e.target.dataset.teR;
    if (v != null) { cols[+v].visible = e.target.checked; e.target.closest('.te-col').classList.toggle('is-off', !e.target.checked); sucio = true; }
    if (r != null) { cols[+r].requerido = e.target.checked; sucio = true; }
  });
  lista.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.teUp) mover(+b.dataset.teUp, +b.dataset.teUp - 1);
    else if (b.dataset.teDown) mover(+b.dataset.teDown, +b.dataset.teDown + 1);
    else if (b.dataset.teDel) { const i = +b.dataset.teDel; const c = cols[i]; cols.splice(i, 1); sucio = true; pintar(); toast(`Se quitará «${c.titulo}» al guardar. Sus datos guardados no se borran.`); }
  });
  let desde = null;
  lista.addEventListener('dragstart', e => { const li = e.target.closest('.te-col'); if (!li) return; desde = +li.dataset.i; li.classList.add('is-drag'); try { e.dataTransfer.setData('text/plain', String(desde)); } catch { /* navegador sin datos */ } });
  lista.addEventListener('dragover', e => { const li = e.target.closest('.te-col'); if (li) { e.preventDefault(); li.classList.add('is-over'); } });
  lista.addEventListener('dragleave', e => e.target.closest('.te-col')?.classList.remove('is-over'));
  lista.addEventListener('drop', e => { const li = e.target.closest('.te-col'); if (!li) return; e.preventDefault(); const a = +li.dataset.i; if (desde != null && desde !== a) mover(desde, a); desde = null; });
  lista.addEventListener('dragend', () => lista.querySelectorAll('.is-drag, .is-over').forEach(x => x.classList.remove('is-drag', 'is-over')));
  const sync = () => { add.querySelector('.te-opc').hidden = add.tipo.value !== 'lista'; }; add.tipo.onchange = sync; sync();
  add.onsubmit = e => {
    e.preventDefault(); const ea = add.querySelector('.fld-err'); ea.textContent = '';
    const t = add.titulo.value.trim().replace(/\s+/g, ' ');
    if (!t) { ea.textContent = 'Escribe el nombre de la columna.'; add.titulo.focus(); return; }
    if (!teNombreLibre(cols, t)) { ea.textContent = 'Ya hay una columna con ese nombre.'; return; }
    const o = add.tipo.value === 'lista' ? [...new Set(add.opciones.value.split(',').map(x => x.trim()).filter(Boolean))] : null;
    if (o && !o.length) { ea.textContent = 'Escribe al menos una opción.'; return; }
    if (cols.filter(c => c.propia).length >= 40) { ea.textContent = 'Hasta 40 columnas propias por tabla.'; return; }
    const c = {id: teNuevaClave(cols, t), base: null, titulo: t, tipo: add.tipo.value, opciones: o, visible: true, requerido: false, propia: true};
    const en = agregarEn != null ? Math.min(cols.length, agregarEn + 1) : cols.length; cols.splice(en, 0, c); agregarEn = null;
    sucio = true; add.reset(); sync(); pintar(en); toast(`Columna «${t}» lista. Pulsa «Guardar columnas».`);
  };
  s.querySelector('[data-te-ok]').onclick = async e => {
    err.textContent = '';
    if (cols.some(c => !c.titulo.trim())) { err.textContent = 'Cada columna necesita un nombre.'; return; }
    for (const c of cols) if (!teNombreLibre(cols, c.titulo.trim(), c)) { err.textContent = `Dos columnas se llaman «${c.titulo.trim()}». Usa nombres distintos.`; return; }
    if (!cols.some(c => c.visible)) { err.textContent = 'Deja al menos una columna visible.'; return; }
    const b = e.currentTarget; busy(b, true, 'Guardando…');
    try { await teGuardarCols(ed, cols); closeModal(); toast('Columnas guardadas. El Excel y el formulario ya las usan.'); teRefrescar(); }
    catch (x) { busy(b, false); err.textContent = x.message; }
  };
  s.querySelector('[data-te-rest]').onclick = () => {
    confirmModal('Restaurar columnas', 'Las columnas vuelven a sus nombres y orden de fábrica, y se quitan las columnas propias. Los datos guardados no se borran.', 'Restaurar', async () => { await teRestaurarCols(ed); toast('Columnas restauradas.'); teRefrescar(); }, true);
  };
  pintar(foco);
  if (agregarEn != null) add.titulo.focus();
  void sucio;
}

/* =====================================================================================
   Menú de cada columna (el botón ⋮ del encabezado)
   ===================================================================================== */
async function teMenuColumna(ed, th, boton, {ordenar} = {}) {
  try { await teAsegurar(ed); } catch (x) { toast(x.message); return; }
  if (ed.tipo === 'campos' && !th.dataset.teCol) { const n = teNorm(teTituloTh(th)); const c = ed.cols.find(c => teNorm(c.base || '') === n || teNorm(c.titulo) === n); if (c) th.dataset.teCol = c.id; }
  const c = ed.colDeTh(th), i = c ? ed.cols.indexOf(c) : -1, admin = ed.puedeEditar();
  const nombre = teTituloTh(th);
  const op = (k, ic, t, sub = '') => `<button type="button" class="mg-op" data-te-op="${k}">${icon(ic)}<span><b>${t}</b>${sub ? `<small>${sub}</small>` : ''}</span></button>`;
  const p = openPop(boton, `<p class="xt-pop-h">${esc(nombre)}</p>
    ${ordenar ? op('asc', 'trend', 'Ordenar de menor a mayor') + op('desc', 'trend', 'Ordenar de mayor a menor') : ''}
    ${admin && c ? `<form class="te-ren" novalidate><label class="fld"><span class="fld-l">Cambiar nombre</span><span class="te-ren-r"><input name="t" maxlength="60" value="${esc(c.titulo)}"><button class="btn btn-sm btn-cta" type="submit">Listo</button></span></label><p class="fld-err" role="alert"></p></form>` : ''}
    ${admin && c && !c.bloqueado ? op('ocultar', 'eye', 'Ocultar columna', 'Vuelve con «Columnas»') : ''}
    ${admin && c && i > 0 ? op('izq', 'chev', 'Mover a la izquierda') : ''}
    ${admin && c && i < ed.cols.length - 1 ? op('der', 'chev', 'Mover a la derecha') : ''}
    ${admin ? op('agregar', 'plus', 'Agregar columna aquí', 'Una columna propia a la derecha') : ''}
    ${admin && c?.propia ? op('quitar', 'trash', 'Quitar columna', 'Sus datos guardados no se borran') : ''}
    ${admin ? op('todas', 'sidebar', 'Todas las columnas…', 'Nombre, orden y columnas visibles') : op('todas', 'sidebar', 'Columnas visibles…')}`, 'xt-pop te-pop', {align: 'left'});
  if (!p) return;
  const guardar = async (cols, msg) => { closePop(); try { await teGuardarCols(ed, cols); toast(msg); teRefrescar(); } catch (x) { toast(x.message); } };
  const f = p.querySelector('.te-ren');
  if (f) f.onsubmit = e => {
    e.preventDefault(); const t = f.t.value.trim().replace(/\s+/g, ' '), er = f.querySelector('.fld-err');
    if (!t) { er.textContent = 'Escribe un nombre.'; return; }
    if (!teNombreLibre(ed.cols, t, c)) { er.textContent = 'Ya hay una columna con ese nombre.'; return; }
    guardar(ed.cols.map(x => x === c ? {...x, titulo: t} : x), `La columna ahora se llama «${t}».`);
  };
  p.addEventListener('click', e => {
    const k = e.target.closest('[data-te-op]')?.dataset.teOp; if (!k) return;
    if (k === 'asc' || k === 'desc') { closePop(); ordenar?.(k === 'asc' ? 1 : -1); return; }
    if (k === 'ocultar') { if (ed.cols.filter(x => x.visible).length <= 1) { toast('Deja al menos una columna visible.'); return; } guardar(ed.cols.map(x => x === c ? {...x, visible: false} : x), `Ocultaste «${c.titulo}». Vuelve a mostrarla desde «Columnas».`); return; }
    if (k === 'izq' || k === 'der') { const cols = [...ed.cols], j = i + (k === 'izq' ? -1 : 1); [cols[i], cols[j]] = [cols[j], cols[i]]; guardar(cols, 'Columna movida.'); return; }
    if (k === 'quitar') { closePop(); confirmModal('Quitar columna', `La columna «${esc(c.titulo)}» deja de mostrarse en la tabla, el Excel y el formulario. Sus datos guardados no se borran.`, 'Quitar', async () => { await teGuardarCols(ed, ed.cols.filter(x => x !== c)); toast('Columna quitada.'); teRefrescar(); }, true); return; }
    if (k === 'agregar') { closePop(); teEditorColumnas(ed, {agregarEn: i >= 0 ? i : ed.cols.length - 1}); return; }
    if (k === 'todas') { closePop(); if (admin) teEditorColumnas(ed, {foco: i >= 0 ? i : null}); else boton.closest('.xt-wrap, .panel, section, #app')?.querySelector('.xt-cols')?.click(); }
  });
  f?.t.focus(); f?.t.select();
}

/* =====================================================================================
   «Agregar manual»: formulario armado con las columnas de la tabla
   ===================================================================================== */
function teInput(c, v) {
  const n = `name="${esc(c.id)}"`, req = c.requerido ? 'required' : '', val = v == null ? '' : v;
  switch (c.tipo) {
    case 'numero': return `<input ${n} ${req} inputmode="decimal" value="${esc(val)}" placeholder="0,00">`;
    case 'fecha': return `<input ${n} ${req} type="date" value="${esc(/^\d{4}-\d{2}-\d{2}$/.test(val) ? val : '')}">`;
    case 'correo': return `<input ${n} ${req} type="email" autocomplete="off" value="${esc(val)}" placeholder="nombre@empresa.com">`;
    case 'telefono': return `<input ${n} ${req} type="tel" value="${esc(val)}" placeholder="+58 414 1234567">`;
    case 'lista': return `<select ${n} ${req}><option value="">Elige…</option>${(c.opciones || []).map(o => `<option ${o === val ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
    case 'si_no': return `<select ${n} ${req}><option value="">—</option><option value="Sí" ${val === true || val === 'Sí' ? 'selected' : ''}>Sí</option><option value="No" ${val === false || val === 'No' ? 'selected' : ''}>No</option></select>`;
    default: return String(val).length > 80 ? `<textarea ${n} ${req} rows="3" maxlength="2000">${esc(val)}</textarea>` : `<input ${n} ${req} maxlength="2000" value="${esc(val)}">`;
  }
}
/**
 * Abre el formulario. ed: el editor de la tabla (o uno armado para el encabezado de un módulo de registros).
 * fila: una fila manual para editarla (solo tablas del reporte).
 */
async function teAgregarManual(ed, fila = null) {
  let cols = ed.cols;
  if (ed.tipo === 'campos') { try { const cs = await camposDe(ed.reg); cols = cs.map(c => ({id: c.clave, titulo: c.titulo, tipo: c.tipo, opciones: c.opciones, visible: c.visible, requerido: c.requerido, propia: c.personalizado, ayuda: c.ayuda})); } catch (x) { toast(x.message); return; } }
  cols = cols.filter(c => c.visible && c.id !== 'id');
  if (!cols.length) { toast('Esta tabla no tiene columnas visibles. Muestra alguna desde «Columnas».'); return; }
  const mod = MODS[ed.modulo]?.name || ed.modulo, per = tePeriodo(ed.modulo);
  const mes = per ? new Date(per + '-15').toLocaleDateString('es-VE', {month: 'long', year: 'numeric'}) : '';
  const sub = fila ? 'Cambia los datos de la fila que agregaste a mano.' : ed.tipo === 'campos' ? `Crea un registro nuevo en ${esc(mod)} con las columnas que definiste.` : `La fila se agrega a la tabla${mes ? ' de ' + esc(mes) : ''} con las columnas que definiste.`;
  const campo = c => `<label class="fld ${c.tipo === 'texto' && cols.length > 6 ? '' : ''}"><span class="fld-l">${esc(c.titulo)}${c.requerido ? ' <span class="req" aria-hidden="true">*</span>' : ''}</span>${teInput(c, fila?.datos?.[c.id])}<span class="fld-err" data-err="${esc(c.id)}"></span>${c.ayuda ? `<small class="fld-help">${esc(c.ayuda)}</small>` : ''}</label>`;
  const s = openModal(modalHead(fila ? 'Editar fila' : 'Agregar manual · ' + esc(ed.nombre || mod), sub) + `<form class="bx-form te-form" novalidate>
      <div class="te-form-g">${cols.map(campo).join('')}</div>
      <p class="fld-err te-err" role="alert"></p>
      <div class="bx-foot"><p class="bx-help">${ed.tipo === 'campos' ? 'También puedes cargar muchos a la vez con «Excel → Cargar desde Excel».' : 'Las columnas se cambian con «Columnas» en la tabla.'}</p><div class="acts">${fila ? '<button class="btn btn-danger" type="button" data-te-quitar>Quitar fila</button>' : ''}<button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">${fila ? 'Guardar' : 'Agregar'}</button></div></div>
    </form>`, {wide: cols.length > 4});
  const f = s.querySelector('form'), err = s.querySelector('.te-err');
  f.querySelector('input, select, textarea')?.focus();
  const leer = () => {
    const datos = {}; let mal = 0;
    for (const c of cols) {
      const el = f.elements[c.id], fe = f.querySelector(`[data-err="${CSS.escape(c.id)}"]`); if (!el) continue;
      let v = el.value; if (c.tipo === 'si_no') v = v === 'Sí' ? true : v === 'No' ? false : '';
      const [val, e] = teCelda(c, v);
      fe.textContent = e || ''; el.setAttribute('aria-invalid', e ? 'true' : 'false'); if (e) mal++;
      if (val != null && val !== '') datos[c.id] = val;
    }
    return {datos, mal};
  };
  f.addEventListener('change', e => { if (e.target.name) { const c = cols.find(x => x.id === e.target.name); const fe = f.querySelector(`[data-err="${CSS.escape(e.target.name)}"]`); if (c && fe) { let v = e.target.value; if (c.tipo === 'si_no') v = v === 'Sí' ? true : v === 'No' ? false : ''; fe.textContent = teCelda(c, v)[1] || ''; } } });
  s.querySelector('[data-te-quitar]')?.addEventListener('click', async e => {
    const b = e.currentTarget; busy(b, true, 'Quitando…');
    try { await agoraApi(`workspace/tablas/filas?clave=${encodeURIComponent(ed.clave)}&id=${encodeURIComponent(fila.id)}`, {method: 'DELETE'}); const est = TE.cache[teLlave(ed.modulo)]; if (est?.filas[ed.clave]) est.filas[ed.clave] = est.filas[ed.clave].filter(x => x.id !== fila.id); closeModal(); toast('Fila quitada.'); teRefrescar(); }
    catch (x) { busy(b, false); err.textContent = x.message; }
  });
  f.onsubmit = async e => {
    e.preventDefault(); err.textContent = '';
    const {datos, mal} = leer();
    if (mal) { err.textContent = 'Revisa los campos marcados.'; f.querySelector('[aria-invalid="true"]')?.focus(); return; }
    if (!Object.keys(datos).length) { err.textContent = 'Llena al menos un dato.'; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      if (ed.tipo === 'campos') {
        const r = await agoraApi('workspace/migracion/importar', {method: 'POST', body: {modulo: ed.reg, modo: 'crear', manual: true, desde: 1, filas: [datos]}});
        if (r.errores?.length) { busy(b, false); err.textContent = r.errores.map(x => (x.campo ? x.campo + ': ' : '') + x.mensaje).join(' · '); return; }
        closeModal(); toast(`Listo: se agregó a ${mod}.`);
        try { window.dispatchEvent(new CustomEvent('agora:registro-agregado', {detail: {modulo: ed.reg}})); } catch { /* sin eventos */ }
        if (typeof DATOS !== 'undefined' && ed.reg === 'tareas') DATOS.tareas = null;
        render();
      } else {
        const est = TE.cache[teLlave(ed.modulo)];
        if (fila) {
          const r = await agoraApi('workspace/tablas/filas', {method: 'PUT', body: {clave: ed.clave, id: fila.id, datos}});
          if (est?.filas[ed.clave]) est.filas[ed.clave] = est.filas[ed.clave].map(x => x.id === fila.id ? r.fila : x);
          closeModal(); toast('Fila guardada.');
        } else {
          const r = await agoraApi('workspace/tablas/filas', {method: 'POST', body: {clave: ed.clave, modulo: ed.modulo, periodo: tePeriodo(ed.modulo), datos}});
          if (est) (est.filas[ed.clave] ||= []).push(r.fila);
          closeModal(); toast('Fila agregada a la tabla.');
        }
        if (!est) await teCargar(ed.modulo, true);
        teRefrescar();
      }
    } catch (x) { busy(b, false); err.textContent = x.message; }
  };
}

/** Escribir el valor de una columna propia en una fila del reporte. */
function teEditarCelda(ed, td) {
  const c = ed.cols.find(x => x.id === td.dataset.teCelda), firma = td.closest('tr')?.dataset.teFirma; if (!c || !firma) return;
  if (!tePuedeEscribir()) { toast('Tu rol es de consulta.'); return; }
  const actual = TE.cache[teLlave(ed.modulo)]?.valores?.[ed.clave]?.[firma]?.[c.id];
  const s = openModal(modalHead(esc(c.titulo), 'Escribe el dato de esta fila. Se guarda junto a la fila del reporte.') + `<form class="bx-form" novalidate><label class="fld"><span class="fld-l">${esc(c.titulo)}</span>${teInput(c, actual)}</label><p class="fld-err" role="alert"></p><div class="bx-foot"><span></span><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="submit">Guardar</button></div></div></form>`);
  const f = s.querySelector('form'); f.elements[c.id]?.focus();
  f.onsubmit = async e => {
    e.preventDefault(); let v = f.elements[c.id].value; if (c.tipo === 'si_no') v = v === 'Sí' ? true : v === 'No' ? false : '';
    const [val, er] = teCelda({...c, requerido: false}, v); if (er) { f.querySelector('.fld-err').textContent = er; return; }
    const b = f.querySelector('[type=submit]'); busy(b, true, 'Guardando…');
    try {
      await agoraApi('workspace/tablas/valor', {method: 'PUT', body: {clave: ed.clave, firma, columna: c.id, valor: val}});
      const est = TE.cache[teLlave(ed.modulo)]; if (est) { const vs = (est.valores[ed.clave] ||= {}); (vs[firma] ||= {}); if (val == null || val === '') delete vs[firma][c.id]; else vs[firma][c.id] = val; }
      closeModal(); td.textContent = teFmt(c, val); toast('Dato guardado.');
    } catch (x) { busy(b, false); f.querySelector('.fld-err').textContent = x.message; }
  };
}

/* =====================================================================================
   Excel de una tabla del reporte: plantilla con sus columnas y carga masiva
   ===================================================================================== */
function teArchivo(ed, que) { return `${teNorm(MODS[ed.modulo]?.name || ed.modulo)}-${teNorm(ed.nombre || 'tabla').slice(0, 30)}-${que}-${new Date().toISOString().slice(0, 10)}`; }
function tePlantilla(ed) {
  if (ed.tipo === 'campos') return camposDe(ed.reg).then(() => migDescargarPlantilla(ed.reg));
  const cols = ed.cols.filter(c => c.visible);
  const inst = {nombre: 'Instrucciones', filtro: false, columnas: [{clave: 't', titulo: 'Columna'}, {clave: 'tipo', titulo: 'Tipo de dato'}, {clave: 'req', titulo: 'Obligatoria'}, {clave: 'opc', titulo: 'Opciones válidas'}],
    filas: cols.map(c => ({t: c.titulo, tipo: TE_TIPOS[c.tipo] || 'Texto', req: c.requerido ? 'Sí' : 'No', opc: (c.opciones || []).join(', ')}))};
  inst.filas.push({}, {t: `Llena la hoja «${ed.nombre || 'Datos'}» desde la fila 2 y no cambies los nombres de la fila 1. Si cambias las columnas en Ágora, descarga la plantilla otra vez.`});
  const n = AgoraExcel.descargar(teArchivo(ed, 'plantilla'), [{nombre: ed.nombre || 'Datos', columnas: cols.map(c => ({clave: c.id, titulo: c.titulo, tipo: c.tipo})), filas: []}, inst]);
  toast(`Plantilla lista: ${n}`);
  return Promise.resolve();
}
function teCargarExcel(ed) {
  if (ed.tipo === 'campos') { migracionIr(ed.reg, 'cargar'); return; }
  const inp = document.createElement('input'); inp.type = 'file'; inp.accept = '.xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv'; inp.hidden = true;
  document.body.append(inp);
  inp.onchange = async () => {
    const file = inp.files?.[0]; inp.remove(); if (!file) return;
    if (file.size > 25 * 1024 * 1024) { toast('El archivo pasa de 25 MB.'); return; }
    let hojas; try { hojas = await AgoraExcel.leer(file); } catch (x) { toast(x.message); return; }
    const hoja = hojas.find(h => h.nombre !== 'Instrucciones' && h.filas?.length) || hojas[0];
    if (!hoja?.filas?.length) { toast('El archivo no tiene filas.'); return; }
    const cab = hoja.filas[0].map(x => String(x ?? '').trim());
    const mapa = cab.map(x => { const n = teNorm(x); return n ? ed.cols.find(c => teNorm(c.titulo) === n || (c.base && teNorm(c.base) === n)) || null : null; });
    const reconocidas = mapa.filter(Boolean);
    if (!reconocidas.length) { toast('Ninguna columna del archivo coincide con las de la tabla. Descarga la plantilla y úsala.'); return; }
    const filas = [], errores = [];
    hoja.filas.slice(1).forEach((r, i) => {
      if (!r || r.every(x => x == null || String(x).trim() === '')) return;
      const datos = {}; let mal = false;
      mapa.forEach((c, j) => { if (!c) return; const [v, e] = teCelda({...c, requerido: false}, r[j]); if (e) { errores.push({fila: i + 2, campo: c.titulo, mensaje: e}); mal = true; } else if (v != null && v !== '') datos[c.id] = v; });
      ed.cols.filter(c => c.requerido && c.visible).forEach(c => { if (datos[c.id] == null) { errores.push({fila: i + 2, campo: c.titulo, mensaje: 'Es obligatorio.'}); mal = true; } });
      if (!mal && Object.keys(datos).length) filas.push({n: i + 2, datos});
    });
    const ignoradas = cab.filter((x, j) => x && !mapa[j]);
    const s = openModal(modalHead('Cargar desde Excel', esc(file.name)) + `<div class="te-carga">
        <div class="grid g3"><div class="micro"><h3>Filas listas</h3><p class="t-metric" style="margin:0">${int(filas.length)}</p></div><div class="micro"><h3>Con errores</h3><p class="t-metric" style="margin:0">${int(new Set(errores.map(x => x.fila)).size)}</p></div><div class="micro"><h3>Columnas reconocidas</h3><p class="t-metric" style="margin:0">${int(reconocidas.length)} de ${int(ed.cols.filter(c => c.visible).length)}</p></div></div>
        ${ignoradas.length ? `<p class="src">No se usan estas columnas del archivo: ${esc(ignoradas.join(', '))}.</p>` : ''}
        ${errores.length ? `<div class="te-errs"><p class="te-add-h">${icon('warn')}Lo que hay que corregir</p><ul>${errores.slice(0, 30).map(x => `<li>Fila ${x.fila} · ${esc(x.campo)}: ${esc(x.mensaje)}</li>`).join('')}${errores.length > 30 ? `<li>… y ${int(errores.length - 30)} más</li>` : ''}</ul></div>` : '<p class="src">Todo se ve bien.</p>'}
        <p class="fld-err" role="alert"></p>
        <div class="bx-foot"><p class="bx-help">Las filas se agregan a la tabla${tePeriodo(ed.modulo) ? ' del mes elegido arriba' : ''} con la marca «A mano». Las filas con error no se cargan.</p><div class="acts"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-cta" type="button" data-te-cargar ${filas.length ? '' : 'disabled'}>Cargar ${int(filas.length)} ${filas.length === 1 ? 'fila' : 'filas'}</button></div></div>
      </div>`, {wide: true});
    s.querySelector('[data-te-cargar]').onclick = async e => {
      const b = e.currentTarget; busy(b, true, 'Cargando…'); let creados = 0; const fallas = [];
      try {
        for (let i = 0; i < filas.length; i += TE_LOTE) {
          const lote = filas.slice(i, i + TE_LOTE);
          const r = await agoraApi('workspace/tablas/importar', {method: 'POST', body: {clave: ed.clave, modulo: ed.modulo, periodo: tePeriodo(ed.modulo), desde: lote[0].n, filas: lote.map(x => x.datos)}});
          creados += r.creados || 0; fallas.push(...(r.errores || []));
          b.innerHTML = `<span class="spin" aria-hidden="true"></span>${int(Math.min(filas.length, i + TE_LOTE))} de ${int(filas.length)}…`;
        }
        closeModal(); await teCargar(ed.modulo, true); teRefrescar();
        toast(`Se cargaron ${int(creados)} ${creados === 1 ? 'fila' : 'filas'}${fallas.length ? ` · ${int(fallas.length)} con error` : ''}.`);
      } catch (x) { busy(b, false); s.querySelector('.fld-err').textContent = x.message; }
    };
  };
  inp.click();
}

/** Menú del botón Excel de la barra de la tabla. */
function teMenuExcel(ed, b, descargarVista) {
  const admin = ed.puedeEditar();
  const p = openPop(b, `<p class="xt-pop-h">Excel de esta tabla</p>
      <button type="button" class="mg-op" data-op="vista">${icon('download')}<span><b>Descargar lo que ves</b><small>Con tus columnas y filtros</small></span></button>
      ${admin ? `<button type="button" class="mg-op" data-op="plantilla">${icon('invoice')}<span><b>Descargar plantilla</b><small>Vacía, con las columnas actuales</small></span></button>
      <button type="button" class="mg-op" data-op="cargar">${icon('upload')}<span><b>Cargar desde Excel</b><small>Carga masiva con revisión</small></span></button>` : ''}`, 'xt-pop mg-hpop', {align: 'right'});
  if (!p) return;
  p.onclick = async e => {
    const op = e.target.closest('[data-op]')?.dataset.op; if (!op) return; closePop();
    try { if (op === 'vista') descargarVista(); else if (op === 'plantilla') await tePlantilla(ed); else teCargarExcel(ed); } catch (x) { toast(x.message); }
  };
}

/** Columnas de TB.api.filas (tablas que ordena el reporte) con los nombres, el orden y las filas manuales de la tabla. */
function teMapearExterno(ed, ext) {
  if (ed.tipo !== 'tabla') { const ocultas = new Set(ed.ocultas()); return {...ext, columnas: ext.columnas.map(c => { const k = Object.values(ed.deTh || {}).find(x => teNorm(x.tituloBase || '') === teNorm(c.titulo)); return k ? {...c, titulo: k.titulo} : c; }).filter(c => !ocultas.has(c.titulo))}; }
  const porNorm = {}; ext.columnas.forEach(c => { porNorm[teNorm(c.titulo)] = c; });
  const cols = ed.cols.filter(c => c.visible).map(c => { const e = c.base ? porNorm[teNorm(c.base)] : null; return {clave: e ? e.clave : c.id, titulo: c.titulo, tipo: e?.tipo || c.tipo, id: c.id}; });
  const filas = ext.filas.concat((ed.manuales || []).map(m => Object.fromEntries(cols.map(c => [c.clave, m.datos?.[c.id] ?? '']))));
  return {...ext, columnas: cols, filas};
}

/* ---------- «Agregar manual» en el encabezado de los módulos de registros (también sin tabla) ---------- */
AgoraTabla.alPintar(root => {
  if (typeof R === 'undefined' || !TE_MANUAL.includes(R.mod) || !tePuedeEscribir()) return;
  const reg = teRegistro(null, R.mod); if (!reg || (reg === 'productos' && !teAdmin())) return;
  if (root.querySelector('.te-hbtn')) return;
  const h = root.querySelector('.mod-h'); if (!h) return;
  let acts = h.querySelector(':scope > .acts'); if (!acts) { acts = document.createElement('div'); acts.className = 'acts'; h.append(acts); }
  const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-cta te-hbtn';
  b.innerHTML = `${icon('plus')}Agregar manual`; b.title = 'Agregar un registro con el formulario de tus columnas';
  acts.append(b);
  b.onclick = () => teAgregarManual({tipo: 'campos', reg, modulo: R.mod, nombre: MODS[R.mod]?.name, cols: []});
});
/* Agencia: punto de entrada y contexto administrativo global. */
let AGENCY_CONTEXT = true;
let AGENCY_ACCOUNTS = null;
async function cargarAgencia() {
  if (!esSuperAdmin()) throw new Error('Acceso reservado al SuperAdmin');
  const d = await agoraApi('agency/accounts');
  AGENCY_ACCOUNTS = Array.isArray(d.items) ? d.items : [];
}
function volverAgencia() {
  if (!esSuperAdmin() || SWITCHING) return;
  resetAccountData(); AGENCY_CONTEXT = true; AGENCY_ACCOUNTS = null;
  loadUiTexts().then(()=>{if(AGENCY_CONTEXT)go('/agencia');});
}
async function abrirCuentaAgencia(id) {
  if (!esSuperAdmin() || SWITCHING) return false;
  if (!AGORA_CONN.some(x => String(x.id) === String(id))) {
    toast('La subcuenta no está autorizada. Actualiza Agencia e inténtalo de nuevo.'); return false;
  }
  return cambiarSubcuenta(id);
}
V.agencia = {
  need: () => Promise.all([cargarAgencia(), cargarActividadAgencia()]),
  render() {
    if (!esSuperAdmin()) return noAccess();
    const accounts = AGENCY_ACCOUNTS;
    const estadoTx = {activa: 'Activa', inactiva: 'Inactiva', pendiente: 'Pendiente'};
    const cards = !accounts ? '<p class="agency-empty" role="status">Cargando las empresas…</p>' : accounts.length ? accounts.map((a, i) => {
      const st = agenciaEstado(a), d = agenciaVence(a);
      const venceCls = d === null ? '' : d < 0 ? 'is-bad' : d <= 7 ? 'is-warn' : d <= AGENCIA_UMBRAL_VENCE ? 'is-soon' : '';
      return `<article class="agency-card" data-id="${esc(a.id)}" data-status="${st}" style="--card-order:${i}">
        <div class="agency-card-top"><span class="agency-initial">${agenciaLogoHtml(a)}</span><span class="agency-status agency-status-${st}">${estadoTx[st]}</span></div>
        <h2>${esc(a.nombre || a.name || 'Empresa')}</h2>
        <p>${esc(a.subcuenta || a.name || 'Subcuenta')}</p><p class="agency-industry">${esc(a.rubro || 'Sin rubro')} · ${esc(a.moneda || 'USD')}</p><p class="agency-tipo ${a.perfil ? '' : 'is-vacio'}">${icon('split')}${esc(a.perfil ? perfilTexto(a.perfil, true) : 'Tipo de cuenta sin definir')}</p>
        <div class="agency-meta">${a.codigo ? `<span class="agency-code-badge">${esc(a.codigo)}</span>` : ''}<span>${esc(a.plan || 'Plan sin configurar')}</span>${a.cobroMensual !== null && a.cobroMensual !== undefined ? `<span class="agency-cobro">${esc(agenciaCobroTexto(a))}</span>` : ''}<span>${Number(a.usuarios || 0)} ${Number(a.usuarios) === 1 ? 'usuario' : 'usuarios'}</span>${d !== null ? `<span class="agency-vence ${venceCls}">${esc(agenciaVenceTexto(a))}</span>` : ''}</div>
        <div class="agency-actions"><button type="button" class="btn btn-cta" data-agency-open="${esc(a.id)}" ${st === 'activa' ? '' : 'disabled title="Reactívala para entrar"'}>Ir a subcuenta ${icon('chevR')}</button><button type="button" class="btn" data-agency-manage="${esc(a.id)}">${icon('gear')}Administrar</button></div>
      </article>`;
    }).join('') : '<p class="agency-empty">No hay subcuentas todavía. Crea la primera para comenzar.</p>';
    const name = String(ME?.nombre || 'equipo').split(' ')[0];
    const n = st => (accounts || []).filter(a => agenciaEstado(a) === st).length;
    const filterBar = accounts && accounts.length ? `
      <div class="agency-filter-bar">
        <div class="agency-search-wrap"><span class="search-in">${icon('search')}<input type="search" id="agency-search-input" placeholder="Buscar por empresa, código, rubro o plan…" autocomplete="off" aria-label="Buscar subcuentas"></span></div>
        <div class="agency-status-filters" role="group" aria-label="Filtro rápido por estado">
          <button type="button" class="agency-status-btn" data-agency-filter="all">Todas <span class="agency-filter-count">${accounts.length}</span></button>
          <button type="button" class="agency-status-btn" data-agency-filter="activa">Activas <span class="agency-filter-count">${n('activa')}</span></button>
          <button type="button" class="agency-status-btn" data-agency-filter="inactiva">Inactivas <span class="agency-filter-count">${n('inactiva')}</span></button>
          <button type="button" class="agency-status-btn" data-agency-filter="pendiente">Pendientes <span class="agency-filter-count">${n('pendiente')}</span></button>
        </div>
        <button type="button" class="btn" data-adv-toggle aria-haspopup="dialog">${icon('tune')}<span>Filtros avanzados</span><span class="agency-adv-badge" data-adv-badge hidden>0</span></button>
      </div>
      <div class="agency-active-chips-bar" data-adv-chips hidden></div>
      <p class="agency-filter-empty" id="agency-filter-empty" hidden>No hay subcuentas que coincidan con los filtros.<br><button type="button" class="btn btn-sm" id="agency-filter-reset">Restablecer filtros</button></p>` : '';
    return launcherFrame('agencia', `<div class="agency-shell"><header class="operations-heading"><p class="operations-eyebrow"><span></span>Administración de Agencia</p><h1>Hola, <em>${esc(name)}</em></h1><p class="operations-lede">Elige una empresa y entra a su espacio de trabajo.</p><p class="launch-summary"><strong>${accounts ? accounts.length : '—'} subcuentas</strong> · Cada empresa con sus usuarios, módulos y datos aparte. <a class="launch-summary-link" href="${href('/agencia/control')}">Ver cómo va la agencia en ${AG_GOB_NOMBRE} ${icon('chevR')}</a></p></header><section aria-labelledby="launch-accounts"><div class="launch-section-heading"><h2 id="launch-accounts">Todas las subcuentas</h2><div class="acts"><button type="button" class="btn" id="agency-export" ${accounts?.length ? '' : 'disabled'}>${icon('download')}Informe</button><button type="button" class="btn" id="agency-start-tour">${icon('spark')}Guía</button><button type="button" class="btn btn-cta" data-agency-create ${esMaster() ? '' : 'disabled title="Solo el Master puede crear subcuentas"'}>${icon('plus')}Nueva subcuenta</button></div></div>${filterBar}<div class="agency-grid" id="agency-cards-grid">${cards}</div></section>${accounts?.length ? agenciaPanelFiltrosHtml(accounts) : ''}</div>`);
  },
  mounted(root) {
    root.querySelector('#agency-start-tour')?.addEventListener('click', () => agenciaIniciarTour(1));
    root.querySelector('#agency-export')?.addEventListener('click', () => exportarInformeSubcuentas(AGENCY_ACCOUNTS || []));
    root.querySelector('[data-agency-create]')?.addEventListener('click', () => agenciaCuentaEditar(null));
    root.querySelectorAll('[data-agency-open]').forEach(el => el.onclick = () => abrirCuentaAgencia(el.dataset.agencyOpen));
    root.querySelectorAll('[data-agency-manage]').forEach(el => el.onclick = () => {
      const a = AGENCY_ACCOUNTS?.find(x => String(x.id) === String(el.dataset.agencyManage));
      if (a) agenciaDetalle(a);
    });
    if (AGENCY_ACCOUNTS?.length) agenciaFiltrosMounted(root, AGENCY_ACCOUNTS);
    if (!store.get('admin_tour_seen') && (esMaster() || esSuperAdmin() || esAdmin())) {
      setTimeout(() => {
        if (R.view === 'agencia' && root.isConnected && !store.get('admin_tour_seen') && !modal) agenciaIniciarTour(1);
      }, 500);
    }
  },
};
/* Centro de mando (antes "Gobierno de agencia", 2.1.7): resumen, subcuentas recientes, seguridad y herramientas del equipo.
   Las tarjetas del resumen llevan al Inicio de agencia con el filtro ya aplicado. */
V.agencyControl = {
  need: () => Promise.all([cargarAgencia(), cargarActividadAgencia()]),
  render() {
    if (!esSuperAdmin()) return agencyRouteDenied();
    const accounts = AGENCY_ACCOUNTS;
    const herramienta = (ruta, ic, titulo, desc) => `<a class="ag-tool" href="${href(ruta)}"><span class="ag-tool-ic" aria-hidden="true">${icon(ic)}</span><span class="ag-tool-tx"><b>${titulo}</b><small>${desc}</small></span>${icon('chevR')}</a>`;
    return launcherFrame('agencia', head('Agencia', AG_GOB_NOMBRE, 'Cómo va tu agencia de un vistazo: subcuentas, cobros, pendientes y seguridad.')
      + (accounts ? agenciaResumenHtml(accounts) : '<p class="agency-empty" role="status">Cargando el resumen…</p>')
      + launcherRecent(true)
      + `<div class="ag-control-row">${agenciaTareasHtml(accounts)}${agenciaSeguridadHtml()}</div>`
      + `<section class="ag-tools" aria-labelledby="ag-tools-h"><h2 id="ag-tools-h" class="launch-section-label">Herramientas del equipo</h2><div class="ag-tools-grid">
          ${herramienta('/agencia/usuarios', 'team', 'Gestión de usuarios', 'Quién de tu equipo administra la agencia y a qué subcuentas entra.')}
          ${herramienta('/agencia/actividad', 'shield', 'Registro de actividad', 'Todo lo que se ha hecho, con fecha, hora y responsable.')}
          ${herramienta('/agencia/estudio-ia', 'spark', 'Estudio', 'Textos del panel, importaciones y borradores con IA.')}
        </div></section>`);
  },
  mounted(root) {
    root.querySelectorAll('[data-agency-open]').forEach(el => el.onclick = () => abrirCuentaAgencia(el.dataset.agencyOpen));
    root.querySelectorAll('[data-kpi-filter]').forEach(b => b.onclick = () => {
      const f = AGENCIA_FILTROS, tipo = b.dataset.kpiFilter;
      if (tipo === 'riesgo') { f.riesgo = true; f.estado = 'all'; } else { f.riesgo = false; f.estado = tipo; }
      go('/agencia');
    });
    agenciaSeguridadMounted(root);
    agenciaTareasMounted(root);
  },
};
function agenciaIniciarTour(step = 1) {
  const steps = [
    {
      num: 1,
      badge: 'Paso 1 de 3',
      tag: 'Tus subcuentas',
      title: 'Cada empresa en su propio espacio',
      desc: 'Cada empresa tiene su espacio, su imagen y sus módulos. El servidor revisa en cada paso que solo entre quien tiene permiso.',
      bullets: [
        'Búsqueda en tiempo real y filtros rápidos por estado (Activas, Inactivas, Pendientes).',
        'Filtros avanzados por fechas, vencimiento de licencia, rubro y número de usuarios.',
        'Alta y edición con validación instantánea de campos y código corporativo (ej: NOV-OPS).'
      ],
      action: 'Explorar subcuentas',
      runAction: () => {
        closeModal();
        const search = document.querySelector('#agency-search-input');
        if (search) { search.focus(); }
      }
    },
    {
      num: 2,
      badge: 'Paso 2 de 3',
      tag: 'Avisos',
      title: 'Vencimientos y solicitudes',
      desc: 'En Tareas de agencia, dentro del Centro de mando, ves a tiempo qué licencias están por vencer y qué subcuentas esperan tu aprobación.',
      bullets: [
        'Indicadores visuales en las tarjetas de subcuenta para estados activo, inactivo y pendiente.',
        'Aviso automático cuando una licencia vence en 15 días o menos.',
        'Acceso directo a la revisión de subcuentas con un solo clic.'
      ],
      action: 'Ver pendientes',
      runAction: () => {
        closeModal();
        const btn = document.querySelector('[data-agency-filter="pendiente"]');
        if (btn) btn.click();
      }
    },
    {
      num: 3,
      badge: 'Paso 3 de 3',
      tag: AG_GOB_NOMBRE,
      title: 'Resumen, seguridad y registro de actividad',
      desc: 'En el Centro de mando ves cómo va la agencia: subcuentas activas, ingresos en riesgo, pendientes y movimientos delicados. Los roles clave solo los cambian Master y Superadmin.',
      bullets: [
        'Resumen con tarjetas que te llevan directo a las subcuentas que necesitan atención.',
        'Registro de actividad con altas, cambios de rol y aprobaciones, exportable en CSV.',
        'Modo oscuro y claro ajustable para máxima comodidad visual.'
      ],
      action: `Ir a ${AG_GOB_NOMBRE}`,
      runAction: () => {
        closeModal();
        go('/agencia/control');
      }
    }
  ];

  const current = steps[step - 1] || steps[0];
  const s = openModal(
    modalHead(`Guía de Bienvenida · ${current.badge}`, current.tag) +
    `<div class="form tour-modal-content" style="gap:16px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <div style="display:flex;gap:6px;flex:1;">
          ${steps.map((st, i) => `
            <div style="height:4px;flex:1;border-radius:2px;background:${i + 1 <= step ? 'var(--brand)' : 'var(--line)'};transition:background .2s;"></div>
          `).join('')}
        </div>
        <span style="font-size:11px;font-weight:700;color:var(--brand);font-family:monospace;">${step}/3</span>
      </div>

      <div style="background:var(--surface-2);padding:16px;border-radius:14px;border:1px solid var(--line);">
        <h3 style="margin:0 0 6px;font-size:16px;font-weight:750;color:var(--ink);">${esc(current.title)}</h3>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:var(--ink-2);">${esc(current.desc)}</p>
        <ul style="margin:0;padding-left:18px;font-size:12px;line-height:1.6;color:var(--muted);">
          ${current.bullets.map(b => `<li style="margin-bottom:4px;">${esc(b)}</li>`).join('')}
        </ul>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:8px;">
        <button type="button" class="btn btn-sm" id="tour-action-btn" style="color:var(--brand);font-weight:600;">${icon('spark')}${esc(current.action)}</button>
        <div style="display:flex;align-items:center;gap:8px;">
          <button type="button" class="btn btn-sm" id="tour-skip-btn">Saltar tour</button>
          ${step > 1 ? `<button type="button" class="btn btn-sm" id="tour-prev-btn">Anterior</button>` : ''}
          ${step < 3 
            ? `<button type="button" class="btn btn-sm btn-cta" id="tour-next-btn">Siguiente ${icon('chevR')}</button>` 
            : `<button type="button" class="btn btn-sm btn-cta" id="tour-finish-btn">${icon('check')}Comenzar</button>`}
        </div>
      </div>
    </div>`
  );

  s.querySelector('#tour-skip-btn').onclick = () => {
    store.set('admin_tour_seen', true);
    closeModal();
    toast('Puedes volver a abrir la guía en cualquier momento desde el botón "Guía interactiva".');
  };

  s.querySelector('#tour-action-btn')?.addEventListener('click', () => {
    store.set('admin_tour_seen', true);
    current.runAction();
  });

  s.querySelector('#tour-prev-btn')?.addEventListener('click', () => {
    agenciaIniciarTour(step - 1);
  });

  s.querySelector('#tour-next-btn')?.addEventListener('click', () => {
    agenciaIniciarTour(step + 1);
  });

  s.querySelector('#tour-finish-btn')?.addEventListener('click', () => {
    store.set('admin_tour_seen', true);
    closeModal();
    toast('¡Listo! Bienvenido a Ágora Digital.');
  });
}
function agenciaLogoHtml(a) {
  const logo = a?.logo;
  if (typeof logo === 'string' && (/^data:image\/(png|jpeg|webp);base64,[a-zA-Z0-9+/=]+$/.test(logo) || /^\/assets\/[\w./-]+\.(png|jpg|jpeg|webp)$/.test(logo))) {
    return `<img class="agency-logo-img" src="${esc(logo)}" alt="Logo de ${esc(a.nombre || 'empresa')}" loading="lazy">`;
  }
  return esc((a?.nombre || a?.name || 'E').slice(0,1).toUpperCase());
}
/* Administrar subcuenta (2.1.9): página propia con el esqueleto de Mi perfil.
   Izquierda: identidad y acciones. Derecha: Cobros y fecha de corte, Datos, Personas, Actividad y Estado. */
function agenciaDetalle(a) { if (a) go('/agencia/subcuenta/' + a.id); }
function agenciaFechaLarga(v) {
  if (!v) return 'Sin fecha';
  const [y, m, dd] = String(v).slice(0, 10).split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, dd)).toLocaleDateString('es-VE', {day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'});
}
V.agencyAccount = {
  need: () => Promise.all([cargarAgencia(), cargarActividadAgencia()]),
  render() {
    if (!esSuperAdmin()) return agencyRouteDenied();
    if (!AGENCY_ACCOUNTS) return launcherFrame('agencia', '<p class="agency-empty" role="status">Cargando la subcuenta…</p>');
    const a = AGENCY_ACCOUNTS.find(x => String(x.id) === String(R.id));
    if (!a) return launcherFrame('agencia', `<section class="panel"><p class="empty"><b>No encontramos esa subcuenta</b>Puede que ya no exista o que no tengas acceso.<br><a class="btn" href="${href('/agencia')}">Volver a subcuentas</a></p></section>`);
    const st = agenciaEstado(a), d = agenciaVence(a), activa = st === 'activa';
    const estadoTx = {activa: 'Activa', inactiva: 'Inactiva', pendiente: 'Pendiente'}[st];
    const corteCls = d === null ? '' : d <= 7 ? 'is-bad' : d <= AGENCIA_UMBRAL_VENCE ? 'is-warn' : 'is-ok';
    const dato = (k, v) => `<div><dt>${k}</dt><dd>${v}</dd></div>`;
    const logs = (AGENCY_AUDIT_LOGS || []).filter(l => [String(a.id), a.nombre, a.subcuenta, a.codigo].includes(String(l.objeto ?? l.entidad ?? ''))).slice(0, 4);
    const usuarios = Number(a.usuarios || 0), activos = Number(a.usuariosActivos ?? a.usuarios ?? 0);
    const seccion = (ic, t, desc, cuerpo, acts = '') => `<section class="panel pf-sec">${secH(ic, t, desc, acts)}${cuerpo}</section>`;
    const accion = l => l.accionTexto && !/^[a-z_]+\.[a-z_]+$/.test(l.accionTexto) ? l.accionTexto : accionLegible(l.accion || l.accionTexto);
    const contenido = `<div class="pf">${head('Subcuentas', 'Administrar ' + esc(a.nombre || a.name), esc(a.subcuenta || ''), `<a class="btn" href="${href('/agencia')}">${icon('chevL')}Volver a subcuentas</a>`)}
      <div class="pf-grid">
        <aside class="panel pf-card" aria-label="Identidad de la subcuenta">
          <div class="pf-avatar pf-avatar-sq">${agenciaLogoHtml(a)}</div>
          <h2 class="pf-name">${esc(a.nombre || a.name)}</h2>
          <p class="pf-mail">${esc(a.subcuenta || '')}</p>
          <span class="agency-status agency-status-${st} pf-role">${estadoTx}</span>
          <div class="pf-photo-acts"><button type="button" class="btn btn-cta" data-acc-abrir ${activa ? '' : 'disabled title="Actívala para entrar"'}>Ir a subcuenta ${icon('chevR')}</button></div>
          <dl class="pf-meta">${dato('Código', esc(a.codigo || 'Sin código'))}${dato('Rubro', esc(a.rubro || 'Sin rubro'))}${dato('Moneda', esc(a.moneda || 'USD'))}${dato('Creada', esc(a.fechaCreacion || '—'))}${dato('Último acceso', esc(a.ultimoAcceso || 'Sin registros'))}</dl>
        </aside>
        <div class="pf-main">
          ${seccion('coins', 'Cobros y fecha de corte', 'Lo que te paga esta subcuenta y hasta cuándo tiene la licencia.',
            `<div class="acc-corte ${corteCls}"><span class="acc-corte-ic" aria-hidden="true">${icon('calendar')}</span><div class="acc-corte-tx"><small>Fecha de corte</small><b>${esc(agenciaFechaLarga(a.vence))}</b><span>${esc(a.vence ? agenciaVenceTexto(a) : 'Sin fecha: no recibirás avisos de vencimiento')}</span></div></div>${agenciaCobrosResumenHtml(a)}`,
            esMaster() ? `<button type="button" class="btn btn-sm btn-cta" data-acc-cobros>${icon('edit')}Editar cobros y corte</button>` : '')}
          ${seccion('split', 'Tipo de cuenta', 'Qué tipo de cliente es, qué vende y cómo cobra. Arma su tablero inicial.',
            a.perfil ? `<dl class="acc-datos">${dato('Cliente', esc(perfilEt('tipoCliente', a.perfil.tipoCliente)))}${dato('Vende', esc(perfilEt('negocio', a.perfil.negocio)))}${dato('Cobra', esc(perfilEt('cobro', a.perfil.cobro) + (a.perfil.frecuencia ? ' · ' + (FRECUENCIAS_P.find(x => x[0] === a.perfil.frecuencia)?.[1] || '').toLowerCase() : '')))}${dato('Día de cobro', a.perfil.diaCobro ? 'Día ' + a.perfil.diaCobro : 'Sin día fijo')}${dato('Factura', a.perfil.factura ? 'Sí emite' : 'No emite')}</dl>` : '<p class="pf-text acc-vacio">Todavía no definiste el tipo de cuenta. Defínelo para que su tablero arranque con las cifras correctas.</p>',
            `<button type="button" class="btn btn-sm ${a.perfil ? '' : 'btn-cta'}" data-acc-tipo>${icon('edit')}${a.perfil ? 'Editar tipo' : 'Definir tipo'}</button>`)}
          ${seccion('briefcase', 'Datos de la empresa', 'Cómo se identifica la subcuenta en la agencia y en los informes.',
            `<dl class="acc-datos">${dato('Empresa', esc(a.nombre || '—'))}${dato('Subcuenta', esc(a.subcuenta || '—'))}${dato('Plan', esc(a.plan || 'Sin plan'))}${dato('Código', esc(a.codigo || '—'))}${dato('Rubro', esc(a.rubro || '—'))}${dato('Moneda', esc(a.moneda || 'USD'))}${a.notas ? `<div class="pf-wide"><dt>Notas</dt><dd>${esc(a.notas)}</dd></div>` : ''}</dl>`,
            `<button type="button" class="btn btn-sm" data-acc-editar>${icon('edit')}Editar datos</button>`)}
          ${seccion('team', 'Personas', 'Quién entra a esta subcuenta y con qué rol.',
            `<div class="acc-stats"><div><b>${NI.format(usuarios)}</b><small>${usuarios === 1 ? 'usuario' : 'usuarios'}</small></div><div><b>${NI.format(activos)}</b><small>con acceso</small></div><div><b>${NI.format(Math.max(0, usuarios - activos))}</b><small>sin acceso</small></div></div>`,
            `<button type="button" class="btn btn-sm" data-acc-personas>${icon('users')}Administrar usuarios</button>`)}
          ${seccion('shield', 'Actividad reciente', 'Lo último que cambió en esta subcuenta.',
            logs.length ? `<ol class="security-list acc-log">${logs.map(l => `<li><button type="button" class="security-item" data-log="${esc(l.id)}"><span class="security-dot ${ACCIONES_CRITICAS.test(String(l.accion)) ? 'is-crit' : ''}" aria-hidden="true"></span><span class="security-tx"><b>${esc(accion(l))}</b><small>${esc(l.admin || l.actor || 'Sistema')}</small></span><time>${haceCuanto(l.at || l.fecha)}</time></button></li>`).join('')}</ol>` : '<p class="pf-text acc-vacio">Sin movimientos recientes en esta subcuenta.</p>',
            `<a class="btn btn-sm" href="${href('/agencia/actividad')}">Ver registro completo</a>`)}
          ${seccion('pause', 'Estado de la subcuenta', activa ? 'Activa: su equipo puede entrar y operar.' : st === 'pendiente' ? 'Pendiente: espera tu aprobación para empezar a operar.' : 'Inactiva: nadie puede entrar ni operar en ella.',
            `<div class="pf-row"><div class="pf-row-tx"><b>${activa ? 'Desactivar subcuenta' : 'Activar subcuenta'}</b><small>${activa ? 'Cierra el acceso de su equipo. Sus datos se conservan.' : 'Abre el acceso a su equipo con los datos que ya tiene.'}</small></div><button type="button" class="btn btn-sm ${activa ? 'btn-danger' : 'btn-cta'}" data-acc-estado>${activa ? 'Desactivar' : 'Activar'}</button></div>`)}
        </div>
      </div></div>`;
    return launcherFrame('agencia', contenido);
  },
  mounted(root) {
    const a = AGENCY_ACCOUNTS?.find(x => String(x.id) === String(R.id)); if (!a) return;
    root.querySelector('[data-acc-abrir]')?.addEventListener('click', () => abrirCuentaAgencia(a.id));
    root.querySelector('[data-acc-cobros]')?.addEventListener('click', () => agenciaCobrosEditar(a));
    root.querySelector('[data-acc-editar]')?.addEventListener('click', () => agenciaCuentaEditar(a));
    root.querySelector('[data-acc-tipo]')?.addEventListener('click', () => perfilEditar(a));
    root.querySelector('[data-acc-personas]')?.addEventListener('click', () => agenciaUsuarios(a.id, AGENCY_ACCOUNTS || []));
    root.querySelector('[data-acc-estado]')?.addEventListener('click', () => agenciaCambiarEstado(a, agenciaEstado(a) === 'activa' ? 'inactiva' : 'activa'));
    root.querySelectorAll('[data-log]').forEach(b => b.onclick = () => fichaForenseAuditoria(b.dataset.log));
  },
};
function agenciaDetalleModal(a) {
  const modal = openModal(modalHead('Administrar ' + esc(a.nombre || a.name), esc(a.subcuenta || a.name)) +
  `<div class="form"><p class="dialog-copy">Configura la identidad y los accesos de esta subcuenta.</p><p class="dialog-copy">Código: <strong style="font-family:monospace;letter-spacing:0.04em">${esc(a.codigo||'Sin asignar')}</strong> · Rubro: ${esc(a.rubro||'Otro')} · Moneda: ${esc(a.moneda||'USD')}${window.__DEMO__?' · Datos de demostración':''}</p>${agenciaCobrosResumenHtml(a)}<p class="dialog-copy">Plan: ${esc(a.plan || 'Sin configurar')} · ${esc(agenciaVenceTexto(a))} · ${Number(a.usuarios || 0)} usuarios · Último acceso: ${esc(a.ultimoAcceso || 'sin registros')}</p><div class="form-ft" style="justify-content:flex-start;flex-wrap:wrap"><button type="button" class="btn" id="ag-editar">Editar empresa</button><button type="button" class="btn" id="ag-personas">Administrar usuarios</button>${esMaster() ? `<button type="button" class="btn" id="ag-cobros">${icon('coins')}Cobros y servicios</button>` : ''}${esMaster() || esSuperAdmin() ? `<button type="button" class="btn ${agenciaEstado(a) === 'activa' ? 'btn-danger' : ''}" id="ag-estado">${agenciaEstado(a) === 'activa' ? 'Desactivar' : 'Reactivar'}</button>` : ''}<button type="button" class="btn btn-cta" id="ag-abrir" ${agenciaEstado(a) === 'activa' ? '' : 'disabled'}>Entrar</button></div></div>`);
  modal.querySelector('#ag-estado')?.addEventListener('click', () => agenciaCambiarEstado(a, agenciaEstado(a) === 'activa' ? 'inactiva' : 'activa'));
  modal.querySelector('#ag-editar').onclick = () => agenciaCuentaEditar(a);
  modal.querySelector('#ag-cobros')?.addEventListener('click', () => agenciaCobrosEditar(a));
  modal.querySelector('#ag-personas').onclick = () => agenciaUsuarios(a.id, AGENCY_ACCOUNTS || []);
  modal.querySelector('#ag-abrir').onclick = () => abrirCuentaAgencia(a.id);
}

/* =====================================================================================
   Arranque: ¿qué sesiones hay abiertas?
   ===================================================================================== */
async function bootContratos() {
  try { ME = (await api('yo')).usuario || null; } catch { ME = null; }
  if (!ME) DATA.reset();
}
applyTheme();
if (AG_READY && window.AGORA_MAINTENANCE !== false) { try { AG.installMaintenance(); } catch { /* sin vigilancia */ } }
(async () => {
  await Promise.all([bootContratos(), bootAgora()]);
  if (ME && AGORA_ME && !esSuperAdmin()) await bootEspacio();
  await loadUiTexts();
  const p = path();
  if (ME && AGORA_ME && esSuperAdmin() && !['/', '/entrar', '/agencia', '/perfil','/agencia/control','/agencia/usuarios','/agencia/actividad','/agencia/textos','/agencia/estudio-ia'].includes(p) && !/^\/agencia\/subcuenta\//.test(p) && !route().unknown && ws?.current()) {
    const activated = await cambiarSubcuenta(ws.current());
    if (activated) go(p, {replace:true});
    return;
  }
  if (!ME || !AGORA_ME) {
    if (p !== '/entrar') { store.set('volver', p); history.replaceState(null, '', HASH ? location.pathname + '#/entrar' : '/entrar'); }
  } else if (p === '/entrar' || p === '/') {
    const back = store.get('volver', ''); store.set('volver', '');
    const target = esSuperAdmin() ? '/agencia' : (back && back !== '/entrar' && back !== '/' ? back : homePath());
    history.replaceState(null, '', HASH ? location.pathname + '#' + target : target);
  }
  render();
})();
})();
