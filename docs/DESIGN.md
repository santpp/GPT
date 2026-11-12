# Guía de diseño de Ágora

Punto de partida para cualquier persona o IA que vaya a tocar la interfaz. Explica **dónde está cada cosa**, **qué reglas hay que respetar** y **cómo comprobar** que un cambio no rompe nada. Si algo de aquí choca con el código, manda el código; actualiza esta guía en el mismo cambio.

Estado: versión 1.10.11 (noviembre de 2026).

---

## 1. Qué es Ágora y para quién

- Espacio de trabajo comercial en español sobre subcuentas de una plataforma de CRM. Lo usan **vendedores** (sobre todo en el **teléfono**, a veces personas mayores) y **la propietaria** (administración).
- Prioridades de diseño, en este orden: **que se lea**, **que se toque bien con el dedo**, **que no haya dudas** sobre qué hace cada botón. La estética va después.
- El cliente **nunca** ve el nombre de la plataforma de origen (ni marcas, endpoints, alcances o tokens). Se dice «la cuenta», «la subcuenta».

## 2. Mapa de archivos

| Qué | Dónde | Notas |
|---|---|---|
| Página única (shell) | `public/index.html` | Barra lateral/inferior, barra superior, diálogos. Sin scripts en línea. |
| Pantallas | `public/assets/*.js` (módulos ES, sin framework) | `app.js` registra las pantallas en `VIEWS` y las muestra con `show()`. Una pantalla = un archivo: `dashboard.js` (Tablero), `live-inbox.js` (Conversaciones), `contacts.js` (Contactos), `board.js` (Oportunidades › Embudos), `won.js` (Ganadas), `consumption.js` (Consumos), `integrations.js` (Conexiones), `help.js` (Ayuda). |
| Estado compartido | `public/assets/workspace.js` | Subcuenta actual, perfil, filtros pendientes entre pantallas. |
| Estilos base | `public/assets/app.css` | Variables de color (`:root`), botones, tipografía. |
| Estilos por pantalla | `public/assets/layout.css` | Se añade **al final**, en bloques comentados por versión (`/* 1.10.9 · … */`). |
| Modo oscuro | `public/assets/dark.css` | **Generado**: `python3 tools/make-dark.py`. No editar a mano. |
| Tamaño de letra y «Texto grande» | `public/assets/text.css` | **Generado**: `python3 tools/make-large.py`. No editar a mano. |
| Tema y texto grande (antes de pintar) | `public/assets/theme.js` | Evita el destello blanco. |
| Esqueletos de carga | `public/assets/skeleton.js` | `skeleton('list'|'lines'|…, n)`. |
| Formatos (dinero, búsqueda) | `public/assets/format.js` | `money()`, `searchText()`. |
| Iconos | SVG en línea dentro de cada JS / `index.html` | No hay librería de iconos. |
| Tipografía | `public/assets/fonts/` | Author Variable (Fontshare). **No viaja en el paquete**: se sube a mano (ver `LEEME.txt`). Sin ella, letra del sistema. |
| Marca | `docs/brand.md`, `public/assets/agora-logo.*`, `icon.svg` | Colores y logo oficiales. |
| Manual de vendedores | `public/assets/manual/vendedores.html` + `manual/img/` | Capturas y PDF: `python3 tools/make-manual.py`. El PDF se sube aparte a `public_html/assets/manual-vendedores.pdf`. |
| Service worker | `public/sw.js` | Lista `SHELL`: si añades un JS o CSS nuevo, añádelo ahí. |

**Después de tocar `app.css` o `layout.css`, ejecuta siempre** `python3 tools/make-dark.py && python3 tools/make-large.py`.

## 3. Marca y color

Colores oficiales (`docs/brand.md`): vino `#AD103C`, azul profundo `#282E49`, oro `#FABD4B`, blanco de marca `#EFEFEF`, negro.

Variables en `app.css` (`:root`) — **úsalas en vez de escribir colores**:

| Variable | Valor claro | Uso |
|---|---|---|
| `--ink` | `#0b0b10` | Texto principal |
| `--muted` | `#30343c` | Texto secundario (contraste 12,5:1) |
| `--green` | `#ad103c` | **Rojo de marca** (el nombre es histórico). Acción principal, activo, montos. En oscuro se vuelve `#d13c63` |
| `--accent` | `#fabd4b` | Oro: **solo fondos o detalles**, nunca texto ni contornos (1,7:1 sobre blanco) |
| `--line` | `#e5e4e9` | Bordes y separadores |
| `--paper` | `#fff` | Superficie de tarjetas |
| `--bg` | `#f7f7fa` | Fondo de página |

Reglas:
- Rojo de marca = acción o estado activo. No lo uses para decorar.
- Los colores de WhatsApp, Instagram y Facebook en las etiquetas de canal son de esas marcas: no se sustituyen.
- Modo oscuro: nunca negro puro (fondo `#12151f`, superficie `#1b1f2c`).
- **Deuda conocida**: hay ~150 colores escritos a mano. Propuesta pendiente: reducirlos a ~18 con nombre (ver §10).

## 4. Tipografía

- Familia: `var(--sans)` = Author Variable → letra del sistema.
- **Los tamaños en `layout.css` no son los que se ven**: `make-large.py` los multiplica por **1,2** (mínimo 11,5 px) y, con «Texto grande», por 1,4 en negrita. Si quieres que algo se vea a 16 px, escribe ~13,4 px.
- Mensajes del chat y cuadro de respuesta: 17 px fijos (bloque `FINAL` de `make-large.py`).
- En el teléfono, los `input` y `textarea` deben medir ≥16 px reales para que iOS no haga zoom.

## 5. Forma, espacio y toque

Valores con nombre (bloque «1.10.11 · Sistema de diseño» en `layout.css`):

| Variable | Valor | Uso |
|---|---|---|
| `--r-sm` | 8 px | Botones, campos, desplegables |
| `--r-md` | 12 px | Tarjetas |
| `--r-lg` | 16 px | Paneles (p. ej. «Más») |
| `--r-pill` | 999 px | Píldoras, chips |
| `--tap` | 44 px | **Toque mínimo** de todo lo que se pulsa (Apple 44, WCAG 2.2 pide 24) |
| `--ease` | `cubic-bezier(.22,.61,.36,1)` | Curva de todas las animaciones |

Puntos de corte: **600 px** (teléfono: barra inferior, una columna), 850 px (barra lateral estrecha), 1200 px (paneles laterales). Se prueba a **360, 372, 390 y 412 px**.

## 6. Componentes y patrones

| Patrón | Cómo | Dónde verlo |
|---|---|---|
| Botón principal | `.primary` (fondo `--green`, texto blanco) | Enviar, Guardar |
| Botón secundario | `.secondary` (borde `--line`) | Editar ficha |
| Enlace de acción | `.link-button` (subrayado, rojo) | «Ahora no», «Ver la lista» |
| Barra de filtros | `.live-toolbar` con `<label>Nombre<select>` | Embudos (Embudo · Responsable · Etapa) |
| Desplegables | `<select>` nativo **siempre dentro de un `<label>` visible** con estilo `.live-toolbar select`. Nunca un `select` suelto sin estilo | Embudos, bandeja |
| Buscador | `<label class="search">⌕<input type="search">` | Conversaciones, Contactos, Oportunidades |
| Tarjeta | `.board-card2`: toda la tarjeta abre la edición; acciones en `.board-ic` (icono + texto) | Embudos |
| Lista | `.record-row` / `.conversation-item` con avatar de iniciales | Contactos, bandeja |
| Panel inferior | `#more-sheet`: fondo oscurecido + panel que sube | «Más» |
| Franja de aviso | `.push-banner` (avisos) / `.push-banner.install-banner` (instalar) | Arriba del contenido |
| Diálogos | `<dialog id="action-dialog">` con `#dialog-content` | Mi cuenta, novedades |
| Carga | `skeleton()` | Todas las listas |
| Vacío | Una frase que diga **qué pasa y qué hacer** («Todavía no tienes contactos asignados.») | — |

Navegación:
- Ordenador: barra lateral con todas las pantallas.
- Teléfono: barra inferior **fija, nunca se desliza**: Tablero, Conversaciones, Contactos, Oportunidades y, **solo para la propietaria**, «Más» (⋯) con Consumos, Conexiones y Ayuda. Pantallas nuevas de administración van a «Más».
- «Mi cuenta» está en el círculo de iniciales de arriba.

Iconos: SVG en línea, `viewBox="0 0 24 24"`, `stroke="currentColor"`, `stroke-width="1.7"`, extremos redondeados, `aria-hidden="true"`. Sin emojis como iconos.

## 7. Movimiento

Animaciones existentes (reutiliza antes de crear):

| Nombre | Efecto | Uso |
|---|---|---|
| `fade-up` | Sube 8–14 px y aparece | Bloques, diálogos, ficha en pantalla grande |
| `sk-in` | Igual, escalonado con `--i` | Listas y esqueletos |
| `slide-in-right` / `slide-in-left` | Entra de lado | Chat, ficha (teléfono) |
| `sheet-up` + `sheet-fade` | Panel desde abajo y fondo | «Más» (se invierten al cerrar) |
| `att-shimmer` | Brillo de carga | Esqueletos, imágenes |

Duraciones: 0,25–0,45 s con `var(--ease)`. **Toda animación nueva debe apagarse** en `@media(prefers-reduced-motion:reduce)`.

## 8. Texto (microcopia)

- Español neutro, tuteo, frases cortas. Verbos concretos («Enviar mensaje», «Mover a…», «Volver a mi vista»).
- Los errores dicen **qué pasó y qué hacer**, sin códigos técnicos ni nombres de servicios.
- Cifras con el formato de `format.js` (`1.730,50 $`).
- Nada de «Oops» salvo la pantalla de mantenimiento (ya existente).

## 9. Accesibilidad (obligatorio)

- Contraste de texto ≥ 4,5:1 (≥ 3:1 en texto grande y contornos de foco), en **claro y oscuro**.
- Toque ≥ `--tap` (44 px).
- Foco visible con teclado (contorno rojo de marca, no oro).
- `lang="es"`, cada campo con etiqueta, botones de solo icono con `aria-label`.
- Movimiento reducido respetado.
- Nada se sale del ancho de 360 px.

## 10. Decisiones y pendientes

- **Hecho (1.10.11)**: esquinas y toque con nombre; monto en rojo de marca.
- **Propuesto, pendiente de aprobar**: sistema completo — colores a ~18 tokens con nombre (texto: ink/muted/subtle; marca: brand/brand-soft/navy/gold/gold-soft/gold-ink; estado: success/success-soft/info/info-soft; superficies: paper/bg/surface/line/line-strong), letra a una escala (10·11·12·13·14·16·18·22·26·32 antes del ×1,2) y sombras a 3 niveles (sm/md/lg). Se aplica por partes comparando capturas.
- **Descartado**: animación «física» con muelles, grano/textura, asimetría decorativa, tira de chips de etapas, filtro «Sin monto» en Embudos.
- **Cabeceras de seguridad** (CSP, HSTS, frame-ancestors…): previstas en V2.1; la app no usa scripts en línea ni de terceros, así que la CSP puede ser estricta (permitiendo `style` en línea).

## 11. Cómo comprobar un cambio de interfaz

1. `python3 tools/make-dark.py && python3 tools/make-large.py`
2. Servidor y base de pruebas: `bash /tmp/mdb/up.sh` y `. /tmp/mdb/env.sh` (entorno de desarrollo).
3. Pruebas de pantalla (Playwright, con datos simulados): `CHROMIUM_PATH=/opt/pw-browsers/chromium python3 tests/<nombre>-ui.py`. Imprescindibles tras tocar estilos: `phone-fit-ui.py` (ancho del teléfono y barra inferior), `mobile-ui.py`, `readability-ui.py`, `open-motion-ui.py`, `embudos-ui.py`.
4. Si cambia algo que sale en el manual: `python3 tools/make-manual.py` y revisar las capturas.
5. Versión nueva: `src/Installation.php` (VERSION), pie en `public/index.html`, `tests/integration.php`, nota en `deploy/native/UPDATE-x.y.z.md` y `docs/roadmap.md`.

## 12. Otros documentos

- `docs/roadmap.md` — estado, versiones y decisiones.
- `docs/brand.md` — identidad de marca.
- `docs/architecture.md` — arquitectura (servidor PHP, base de datos, sincronización).
- `deploy/native/UPDATE-*.md` — qué cambió en cada versión.
