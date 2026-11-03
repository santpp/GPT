# Revisión del paquete 1.1.0 y qué se corrigió en la 1.1.1

Revisado: `public_html/` (index, hojas de estilo, módulos empaquetados, `sw.js`, `.htaccess`, `config.js`),
`construccion/` (fuentes, parches, `API.md`) y las 29 capturas. Cada punto dice dónde se ve.

## 1. Lo que se perdía de nuestra imagen (corregido en la 1.1.1)

| Hallazgo | Dónde | Estado |
|---|---|---|
| La letra Author no se carga: la pila es Apple (`-apple-system`, SF Pro, Helvetica, Segoe). | `sistema.css:12-14` | Corregido: `identidad.css` vuelve a Author. |
| `app.css` y `layout.css` de Ágora **no se cargan** («era de otra generación»). Los módulos quedan re-vestidos con otra hoja, no conservados. | cabecera de `modulos.css`, `index.html` | Se mantiene el armazón nuevo, pero la capa `identidad.css` devuelve la paleta, la letra y el trazo de Ágora. |
| Texto pequeño: 11,5–13,5 px en la bandeja (nombres 13,5, vista previa 12,5, hora 11,5); mensajes 12–13 px. | `modulos.css:273-275` y siguientes | Corregido: todas las letras +15 % (suelo 12,5 px), mensajes y cuadro de respuesta a 17 px, texto negro `#0b0b10` y grises oscuros. |
| Grises Apple (`#6E6E73`, `#86868B`) con poco contraste. | `sistema.css` tokens | Corregido: `--muted:#30343c`, `--muted-2:#4a4f58`. |

## 2. Lo que se lee como «hecho por IA» (corregido salvo lo indicado)

1. **Vidrio esmerilado** (desenfoque) en la barra, las pestañas del celular, el menú lateral y los menús: `backdrop-filter` en `sistema.css:251, 282, 324`. → Barra plana y opaca.
2. **Punto verde** en el mes en curso (`.dot-now`, `.pt-curso`) y punto dorado en el próximo. Ya habíamos descartado el punto verde. → Quitados.
3. **Halo luminoso** detrás del logo del acceso (`.lg-halo`, `lgFloat`, `lgHalo`). → Quitado.
4. **Tarjetas de color por módulo** en el menú (iconos sobre cuadros de colores: vino, oro, azul…). → Icono de línea negro, sin cuadro.
5. **Cápsulas tintadas** por todas partes: botones «Descargar informe», «Agregar» (`btn-tint`), estados «en curso», «SuperAdmin» (`sc-*`), botón «Activa» verde. → Botones de contorno; estados como texto.
6. **Mensajes enviados en rosa** (`brand-soft`). → El índigo suave de Ágora.
7. **Athena, «tu agente»**: botón con avatar y hoja con puntos animados (`athDot`). → Restilado como un enlace más, sin avatar. **Decisión pendiente tuya:** si debe existir o no (ver 4).
8. **Barra superior con 12 controles** (historial, buscar ⌘K, Athena, Inicio, Ajustes, Auditoría, estado, tema, cuenta…). Sin cambio: es estructura del armazón; conviene reducirla cuando se decida qué se queda.
9. Cifras gigantes (hasta 64 px) con área degradada en el gráfico del Resumen. → Cifra héroe a 48 px; el degradado del gráfico se mantiene (decisión de diseño pendiente).

## 3. Quejas de los vendedores (aplicadas también a este paquete)

- **Mensajes y cuadro de respuesta muy pequeños** → 17 px y negros; también en la bandeja (nombres, vista previa).
- **Ver de un vistazo si el lead llegó por Facebook, WhatsApp o Instagram** → la lista y el encabezado del chat muestran la marca y el nombre del canal en su color (verde, rosa, azul; SMS y correo en negro). Código en `construccion/agora/live-inbox.js` y en los dos empaquetados.
- Error encontrado al agrandar el texto: las filas de la lista se encogían y se montaban unas sobre otras (`flex-shrink`), y el chat podía no quedar en el último mensaje. Ambos corregidos.

## 4. Funcionamiento y despliegue (resuelto en la 1.2.0: ver el final)

- **Las 9 pantallas nuevas no tienen servidor.** `API.md` pide `workspace/resumen`, `dia`, `ads`, `eficacia`, `tareas`, `reminders`, `auditoria`, `usuarios`, `branding`. Ninguna existe en el servidor de Ágora 1.10.x (comprobado). Sin ellas, esos módulos mostrarán error. Es trabajo de servidor, no de pantalla.
- **Dos sistemas en uno.** El paquete asume además el servidor del panel de Contratos (`/api/yo`, `/api/contratos`, `/api/usuarios`, `/api/registro`, `/api/ghl/…`). No está en este repositorio.
- **Dominios separados no funcionan con nuestro servidor.** `config.js` ofrece dominios distintos con cookies `SameSite=None`; el servidor de Ágora fija `SameSite=Strict` y no tiene CORS. Sólo vale el mismo dominio.
- **`/api/usuarios` y `/api/workspace/…` pueden pisarse** entre los dos sistemas (lo avisa el propio `config.js`).
- **Athena y Auditoría** son funciones nuevas con alcance propio (qué lee, a quién se la muestra). Conviene definirlas antes de publicarlas.
- **Caché del trabajador de servicio:** `sw.js` sólo borraba cachés `agora-shell-*`, así que las de versiones anteriores `agora-digital-app-*` quedaban para siempre. Corregido.
- **No se puede reconstruir desde el zip:** `parches.py` necesita el `panel.js` original, que no viene incluido. Los arreglos se aplicaron a los archivos ya empaquetados y a las fuentes de `construccion/agora/`; hay que conservar `identidad.css` y `identidad-texto.css` si se vuelve a empaquetar.
- **Sin secretos ni dominios externos** en el paquete (revisado). La pantalla «Archivo de pruebas» sólo existe en `prueba.html`.

## 5. Archivos nuevos o cambiados en la 1.1.1

- Nuevos: `assets/identidad.css` (a mano), `assets/identidad-texto.css` (generado por `construccion/texto-grande.py`), `capturas-identidad/`, este informe.
- Cambiados: `index.html` (enlaza las dos hojas al final), `assets/agora-modulos.js` y `prueba.html` (canal con color; el chat abre en el último mensaje), `construccion/agora/live-inbox.js`, `sw.js` (caché 1.1.1 y limpieza), `version.json`.
- Sin cambios: `sistema.css`, `modulos.css`, `agora-app.js`.

## 6. 1.2.0 · el servidor

- Las nueve rutas existen ya en Ágora 1.11.0, con pruebas (56 + 28 comprobaciones) y una prueba de punta a punta de estas pantallas contra el
  servidor real (`construccion/arnes/`): recordatorio → tarea → auditoría, crear cuentas, cambiar el logo, resumen.
- Ajustes en las pantallas que salieron de esa prueba: el logo admite PNG, JPG o WebP de hasta 400 KB (el servidor no acepta SVG, que puede llevar
  código); la clave temporal pide 12 caracteres (la regla de Ágora); la ventana «Agregar persona» ya no se sale de la tarjeta con letra grande; el
  contador de la fila de conversación ya no se corta.
- Queda abierto: los cobros, planes y egresos del **Resumen** (vienen en cero hasta conectar el sistema de contratos) y las pantallas de Contratos
  (otro servidor).
