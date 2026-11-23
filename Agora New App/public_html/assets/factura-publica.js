/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
/* 2.20.0 · La factura que abre el cliente con el enlace del correo. Sin sesión: el token del enlace (después de #) va al servidor,
   que solo guarda su huella, vence a los 60 días y avisa a quien la envió la primera vez que se abre. La factura se muestra en un marco
   aislado (sin scripts) y se imprime o guarda en PDF con el botón. */
(function () {
  'use strict';
  const t = (location.hash || '').slice(1), m = document.getElementById('m'), s = document.getElementById('s');
  const aviso = txt => { m.textContent = ''; const p = document.createElement('p'); p.className = 'aviso'; p.textContent = txt; m.appendChild(p); s.textContent = ''; };
  if (!/^[a-f0-9]{64}$/.test(t)) { aviso('El enlace no es válido. Pide uno nuevo a quien te lo envió.'); return; }
  history.replaceState(null, '', location.pathname); // el token no queda a la vista
  fetch('/api/factura-publica?t=' + t, {credentials: 'omit', cache: 'no-store'}).then(r => r.json().then(d => ({ok: r.ok, d}))).then(({ok, d}) => {
    if (!ok) { aviso(d.error || 'No se pudo abrir la factura.'); return; }
    document.getElementById('t').textContent = 'Factura N.º ' + (d.numero || '');
    s.textContent = (d.empresa ? d.empresa : '') + (d.estado === 'ANULADA' ? ' · Anulada' : '');
    document.title = 'Factura ' + (d.numero || '') + (d.empresa ? ' · ' + d.empresa : '');
    const f = document.createElement('iframe'); f.setAttribute('sandbox', 'allow-same-origin allow-modals'); f.title = 'Factura'; f.srcdoc = d.html;
    m.textContent = ''; m.appendChild(f);
    f.onload = () => { try { f.style.height = Math.max(600, f.contentDocument.documentElement.scrollHeight + 24) + 'px'; } catch (e) {} };
    const b = document.getElementById('imp'); b.hidden = false; b.onclick = () => { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { window.print(); } };
  }).catch(() => aviso('No se pudo abrir la factura. Revisa tu conexión e inténtalo de nuevo.'));
})();
