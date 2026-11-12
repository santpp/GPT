# Guía de diseño de Ágora

Punto de partida para cualquier persona o IA que vaya a tocar la interfaz. Explica **dónde está cada cosa**, **qué reglas hay que respetar** y **cómo comprobar** que un cambio no rompe nada. Si algo de aquí choca con el código, manda el código; actualiza esta guía en el mismo cambio.

Estado: versión 1.10.12 (noviembre de 2026). El sistema de diseño completo está aplicado y lo vigila `tests/design-tokens.py`.

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

### Paleta (1.10.12)

Todo color sólido de `app.css` y `layout.css` es uno de estos 18 (más los tres de canal). `tests/design-tokens.py` falla si aparece otro. Las variables están en `:root` de `app.css`.

| Grupo | Variable | Claro | Oscuro (automático) | Uso |
|---|---|---|---|---|
| Texto | `--ink` | `#0b0b10` | `#e6e8f0` | Texto principal |
| | `--muted` | `#30343c` | `#9aa0b4` | Texto secundario (12,5:1) |
| | `--subtle` | `#5d6370` | convertido | Texto terciario, iconos, metadatos (6:1 sobre blanco) |
| Marca | `--green` | `#ad103c` | `#d13c63` | **Rojo de marca** (nombre histórico). Acción principal, activo, montos, foco |
| | `--brand-soft` | `#f7e9ee` | convertido | Fondos suaves de marca (avatares, franja de instalar) |
| | `--navy` | `#282e49` | — | Azul profundo de marca (barra del sistema, detalles) |
| | `--accent` | `#fabd4b` | — | Oro: **solo fondos o detalles**, nunca texto ni contornos (1,7:1) |
| | `--gold-soft` | `#fff4d5` | convertido | Fondo de avisos |
| | `--gold-ink` | `#7a5c18` | convertido | Texto sobre fondo dorado o de aviso |
| Estado | `--success` | `#2f7a52` | convertido | Hecho, enviado, conectado |
| | `--success-soft` | `#eaf7ef` | convertido | Fondo de éxito |
| | `--info` | `#0a57d0` | convertido | Información, enlaces neutros |
| | `--info-soft` | `#e9f1ff` | convertido | Fondo de información |
| Superficie | `--paper` | `#fff` | `#1b1f2c` | Tarjetas y paneles |
| | `--bg` | `#f7f7fa` | `#12151f` | Fondo de página |
| | `--surface` | `#f1f1f5` | convertido | Campos de búsqueda, zonas hundidas |
| | `--line` | `#e5e4e9` | `#2f3445` | Bordes y separadores |
| | `--line-strong` | `#d8dde6` | convertido | Bordes de botones y campos |
| Canales | — | `#1faf54`, `#c13584`, `#0866ff` | — | WhatsApp, Instagram, Facebook (colores de esas marcas) |

Las transparencias (`#rrggbbaa`) se permiten para sombras, fondos oscurecidos y anillos de foco.

«Convertido»: `tools/make-dark.py` genera el tono oscuro de cada color que aparece en una regla. Las variables principales tienen además un valor oscuro fijo.

Reglas:
- **Error = rojo de marca.** No hay un rojo de error aparte.
- Rojo de marca = acción o estado activo. No lo uses para decorar.
- El texto solo usa colores de texto (`--ink`, `--muted`, `--subtle`, marca o estado), nunca de línea ni de fondo.
- Modo oscuro: nunca negro puro.
- Un color nuevo solo con motivo: se añade a esta tabla, a `:root` y a `tests/design-tokens.py` en el mismo cambio.

## 4. Tipografía

- Familia: `var(--sans)` = Author Variable → letra del sistema.
- **Escala** (lo escrito en CSS → lo que se ve, ×1,2 con mínimo 11,5): 10→12 · 11→13,2 · 12→14,4 · 13→15,6 · 14→16,8 · 16→19,2 · 18→21,6 · 22→26,4 · 26→31,2 · 32→38,4 px. No se usan otros tamaños (lo vigila `tests/design-tokens.py`).
- Pesos: 400 normal, 600 títulos de tarjeta y etiquetas, 700 cifras y títulos de pantalla. «Texto grande» pone todo en 700.
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
| `--shadow-sm` | `0 1px 3px #12151f1f` | Elementos pegados al fondo (chips, tarjetas elevadas) |
| `--shadow-md` | `0 8px 24px #12151f1f` | Menús desplegables, tarjetas flotantes |
| `--shadow-lg` | `0 16px 40px #12151f26` | Diálogos y paneles («Más») |

Las tarjetas normales no llevan sombra: borde `--line` y esquina `--r-md`.

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

## 9 bis. Estados de interacción

| Estado | Cómo se ve | Regla |
|---|---|---|
| Reposo | Borde `--line-strong`, texto `--ink` | — |
| Encima (ratón) | Borde o texto en rojo de marca | Solo como ayuda: en el teléfono no existe |
| Pulsado | Se encoge al 97 % (`button:active`) | Se apaga con movimiento reducido |
| Foco (teclado) | Contorno de 2 px en rojo de marca, separado 2 px | Nunca quitar `outline` sin poner otro |
| Desactivado | Opacidad 50 % y cursor de espera | Los botones que envían algo se desactivan mientras esperan y vuelven si falla |
| Cargando | Esqueleto (`skeleton()`) o el texto del botón cambia («Cargando…», «Moviendo…») | Nunca una pantalla en blanco |
| Activo / elegido | Rojo de marca (texto o línea inferior de la pestaña) | Uno solo por grupo |

## 9 ter. Formularios y acciones

- Cada campo tiene una etiqueta visible encima. El texto de ejemplo no sustituye a la etiqueta.
- El tipo de campo ayuda al teclado del teléfono: `type="search"`, `type="email"`, `inputmode="numeric"` para montos.
- Las acciones que escriben en la cuenta llevan un identificador de petición (`requestId`): si se pulsa dos veces, solo se hace una vez. Mientras tanto, el botón se desactiva.
- Confirmar solo lo que no se puede deshacer o lo que descarta trabajo («Salir sin guardar»). Nunca preguntar «¿Seguro?» en acciones normales.
- Después de una acción, una confirmación breve: aviso flotante (`agora:toast`) o la línea de estado de la pantalla («Reserva sábado pasó a «Propuesta enviada»»).

## 9 quater. Listas, totales y vacíos

- La línea de estado bajo los filtros dice **cuántos hay y qué se muestra**: «120 contactos asignados a ti · se muestran 50, los más recientes primero».
- Si la lista está cortada, se dice («las más recientes») y se ofrece **Ver más** cuando se puede.
- Quien solo ve lo suyo lo lee en la propia línea («asignados a ti»), sin avisos aparte.
- Los vacíos distinguen entre «no hay nada» («Todavía no tienes contactos asignados.») y «no coincide» («Ningún contacto coincide con esa búsqueda.»).
- Un dato que la cuenta no dio se dice («La cuenta no devolvió este dato»). Nunca se muestra un 0 que parezca real.

## 9 quinquies. Roles: quién ve qué

| | Vendedor | Administrador de subcuenta | Propietaria |
|---|---|---|---|
| Pantallas | Tablero, Conversaciones, Contactos, Oportunidades | Igual | Igual + Consumos, Conexiones (en «Más» en el teléfono) |
| Datos | Solo lo asignado | Toda la subcuenta | Todas las subcuentas |
| Asignar, «Entrar como», mantenimiento | No | Asignar | Todo |

Al diseñar una pantalla, comprueba las tres vistas. «Entrar como» (Mi cuenta) muestra la de cualquier usuario en solo lectura.

## 9 sexies. Pantallas y rutas

| Ruta | Pantalla | Archivo |
|---|---|---|
| `/tablero` | Tablero | `dashboard.js` |
| `/bandeja` | Conversaciones | `live-inbox.js` |
| `/contactos` | Contactos | `contacts.js` |
| `/oportunidades` | Oportunidades (Embudos · Ganadas) | `board.js`, `won.js` |
| `/ayuda` | Manual | `help.js` |
| `/consumos` | Consumos (propietaria) | `consumption.js` |
| `/conexiones` | Conexiones (propietaria) | `integrations.js` |
| `/conversations/<id>` | Abre una conversación (desde avisos) | `live-inbox.js` |

Las novedades pueden enlazar a cualquiera de las siete primeras.

## 9 septies. Imagen de antes y después

Antes de un cambio visual grande se muestra una imagen. El método usado hasta ahora:
- Playwright a 390 × 844 con datos inventados (los mismos de `tools/make-manual.py`).
- El cambio se aplica solo en la captura, sirviendo un CSS modificado con `page.route('**/assets/<archivo>.css', …)` o inyectando estilos con `page.add_style_tag`.
- La imagen pone «Hoy» y «Propuesta» lado a lado, en claro y en oscuro.

El repositorio no se toca hasta que se aprueba.

## 9 octies. Lista de comprobación antes de entregar

- [ ] `make-dark.py` y `make-large.py` ejecutados.
- [ ] `tests/design-tokens.py` en verde (paleta, escala y sombras).
- [ ] Claro, oscuro y «Texto grande» revisados.
- [ ] 360 y 412 px sin nada fuera del ancho (`tests/phone-fit-ui.py`).
- [ ] Toques ≥ 44 px, foco visible, movimiento reducido.
- [ ] Vendedor, administrador y propietaria revisados (o «Entrar como»).
- [ ] Textos en español, sin nombres técnicos ni de la plataforma; palabras del glosario (§13) y textos de referencia (§14).
- [ ] Si se ve en el manual: capturas y PDF regenerados.
- [ ] Versión, nota de actualización y roadmap.

## 10. Decisiones y pendientes

- **Hecho (1.10.11)**: esquinas y toque con nombre; monto en rojo de marca.
- **Hecho (1.10.12)**: sistema completo. 154 colores pasan a 18 de paleta (más los de canal), 28 tamaños de letra a una escala de 10 y 20 sombras a 3 niveles. Lo vigila `tests/design-tokens.py`.
- **Pendiente técnico**: las reglas usan los valores de la paleta escritos tal cual, no `var(--…)`, porque `make-dark.py` convierte colores literales. Pasar a `var()` exige que el modo oscuro defina cada variable; se puede hacer más adelante sin cambio visible.
- **Descartado**: animación «física» con muelles, grano/textura, asimetría decorativa, tira de chips de etapas, filtro «Sin monto» en Embudos.
- **Cabeceras de seguridad** (CSP, HSTS, frame-ancestors…): previstas en V2.1; la app no usa scripts en línea ni de terceros, así que la CSP puede ser estricta (permitiendo `style` en línea).

## 11. Cómo comprobar un cambio de interfaz

1. `python3 tools/make-dark.py && python3 tools/make-large.py`
2. Servidor y base de pruebas: `bash /tmp/mdb/up.sh` y `. /tmp/mdb/env.sh` (entorno de desarrollo).
3. `python3 tests/design-tokens.py` (paleta, escala de letra y sombras).
4. Pruebas de pantalla (Playwright, con datos simulados): `CHROMIUM_PATH=/opt/pw-browsers/chromium python3 tests/<nombre>-ui.py`. Imprescindibles tras tocar estilos: `phone-fit-ui.py` (ancho del teléfono y barra inferior), `mobile-ui.py`, `readability-ui.py`, `open-motion-ui.py`, `embudos-ui.py`.
5. Si cambia algo que sale en el manual: `python3 tools/make-manual.py` y revisar las capturas.
6. Versión nueva: `src/Installation.php` (VERSION), pie en `public/index.html`, `tests/integration.php`, nota en `deploy/native/UPDATE-x.y.z.md` y `docs/roadmap.md`.

## 12. Otros documentos

- `docs/roadmap.md` — estado, versiones y decisiones.
- `docs/brand.md` — identidad de marca.
- `docs/architecture.md` — arquitectura (servidor PHP, base de datos, sincronización).
- `deploy/native/UPDATE-*.md` — qué cambió en cada versión.

---

## 13. Glosario: una palabra para cada cosa

Usa siempre la palabra de la izquierda. Si un texto usa otra, se corrige.

| Palabra | Qué es | No digas |
|---|---|---|
| **Cuenta** / **subcuenta** | Cada negocio conectado (ASOCIM, UBA Line…). En pantalla, «subcuenta» cuando hay que distinguirla de la agencia | El nombre de la plataforma de origen, «location», «ubicación» |
| **Contacto** | La persona del cliente en la lista y la ficha | «Lead» (salvo en las cifras de leads), «registro», «cliente» como nombre de pantalla |
| **Lead** | Contacto **nuevo** en un periodo («Leads de hoy», «Mis leads de hoy») | Usarlo para cualquier contacto |
| **Conversación** | El hilo de mensajes con un contacto | «Chat» en textos (sí en el código) |
| **Oportunidad** | Una posible venta abierta | «Deal», «negocio» |
| **Embudo** | El recorrido de ventas con sus etapas | «Pipeline» |
| **Etapa** | Cada paso del embudo | «Fase», «columna», «stage» |
| **Ganada** / **venta ganada** | Oportunidad cerrada con éxito | «Cerrada», «won» |
| **Monto** | Lo que vale una venta | «Valor», «importe» (salvo en tablas de Consumos) |
| **Responsable** | La persona del equipo a quien está asignado algo | «Dueño», «owner», «agente» |
| **Asignado a ti** | Lo que es tuyo | «Asignado a tu usuario» (aún aparece en Embudos y en el panel de la conversación: corregir) |
| **Ficha** | La pantalla con los datos de un contacto | «Perfil» (el perfil es «Mi cuenta») |
| **Mi cuenta** | Ajustes personales (tema, texto grande, huella, sesiones) | «Perfil», «Ajustes» |
| **Avisos** | Notificaciones al teléfono | «Notificaciones push» |
| **Novedades** | Mensajes de la propietaria a todo el equipo | «Anuncios», «noticias» |
| **Propietaria** / **quien administra** | La administradora de Ágora | «Admin», «superusuario» |
| **Vendedor** / **equipo** | Las personas que usan Ágora para vender | «Agente», «usuario final» |

Nota: el subtítulo de Contactos usa «Personas, no registros.» y el manual dice «tus personas». Es un uso expresivo aceptado solo ahí; en botones, estados y listas se dice siempre «contacto».

## 14. Textos de referencia

Reutiliza estos textos tal cual. Si necesitas uno nuevo, sigue el mismo tono y añádelo aquí.

| Situación | Texto |
|---|---|
| Acción principal de enviar | **Enviar** · **Enviar mensaje** · **Enviar plantilla** |
| Editar | **Editar ficha** (contacto) · tocar la tarjeta (oportunidad) |
| Mover de etapa | **Mover a…** → «*Nombre* pasó a «*Etapa*».» |
| Cargar más | **Ver más** → mientras carga, «Cargando…» |
| Quitar filtros | **Quitar filtros** |
| Aplazar un aviso | **Ahora no** |
| Volver | **Volver a mi vista** («Entrar como») · flecha ← (atrás) |
| Cargando | «Cargando…» · «Cargando mensajes anteriores…» · «Moviendo…» · «Abriendo la ficha…» |
| Lista vacía (no hay nada) | «Todavía no tienes contactos asignados.» · «Esta cuenta todavía no tiene conversaciones.» |
| Lista vacía (no coincide) | «Ningún contacto coincide con esa búsqueda.» · «Nada coincide» (dentro de una etapa) |
| Total de una lista | «120 contactos asignados a ti · se muestran 50, los más recientes primero» |
| Dato que la cuenta no dio | «La cuenta no devolvió este dato» |
| Sin cambios al guardar | «No has cambiado nada todavía.» |
| Pausa por cuota | «*La lista* está en espera para respetar la cuota.» + reintentar solo |
| Sin permiso | «Tu usuario no tiene permiso para *consultar oportunidades*.» |
| Asignado a otra persona | «Este contacto está asignado a otra persona.» |
| Solo lectura («Entrar como») | «Estás viendo Ágora como otra persona: sólo puedes mirar.» |
| Hora de actualización | «Actualizado a las 10:00» (vendedor) · «Cifras de la cuenta · actualizado a las 10:00» (propietaria) |
| Instalar la app | «**Ágora no está instalada en este teléfono.** Instálala para abrirla con un toque y recibir un aviso cada vez que te escribe un cliente.» |

Botones: verbo en infinitivo y sin punto. Mensajes de estado: frase completa con punto.

## 15. Qué no hacer (y por qué)

| No | Por qué | Se hace así |
|---|---|---|
| Tiras de chips o barras que se deslizan de lado | Lo que no se ve no existe; en el teléfono se pierde | Desplegable con etiqueta (Etapa) o panel «Más» |
| `<select>` suelto, sin etiqueta ni estilo | Parece roto y distinto al resto | `<label>Nombre<select>` dentro de `.live-toolbar` |
| Barra de abajo con más de 5 botones o deslizable | Etiquetas pisadas, se pierde la pestaña activa | 4 pestañas + «Más» (solo propietaria) |
| Filtros que no sirven para la tarea de esa pantalla | Distraen | Ej.: «Sin monto» va en Ganadas, no en Embudos |
| Emojis como iconos | Cambian según el teléfono y abaratan | SVG de línea (§16) |
| Colores nuevos «parecidos» | Así se llegó a 154 | La paleta (§3); `tests/design-tokens.py` lo impide |
| Oro (`#fabd4b`) para texto o contornos | 1,7:1 sobre blanco, no se lee | Oro solo de fondo; texto en `--gold-ink` |
| Un 0 cuando falta el dato | Parece real y engaña | «La cuenta no devolvió este dato» |
| Contar «lo tuyo» filtrando entre los últimos de la cuenta | Salen menos de los que hay | Pedir a la cuenta ya filtrado por responsable |
| Nombres técnicos o de la plataforma en pantalla | Rompe la marca y confunde | Glosario (§13) |
| Mostrar códigos de acceso, tokens o claves a otra persona | Seguridad: permitiría entrar como otro | «Entrar como» en solo lectura |
| Animaciones sin versión «movimiento reducido» | Marean a algunas personas | `@media(prefers-reduced-motion:reduce)` |
| Muelles, grano, texturas, asimetrías decorativas | Herramienta de trabajo: prima leer rápido | Descartado expresamente |
| Pantallas en blanco mientras carga | Parece colgado | Esqueleto (`skeleton()`) |

## 16. Iconos existentes

Todos: SVG en línea, 24 × 24, trazo `currentColor` de 1,7–1,9, extremos redondeados, `aria-hidden="true"`. Copia el que exista antes de dibujar otro.

| Icono | Para qué | Dónde está |
|---|---|---|
| Tablero, Conversaciones, Contactos, Oportunidades, Consumos, Conexiones | Navegación | `public/index.html` (barra lateral) |
| Tres puntos (`DOTS`) | «Más» | `app.js` |
| Ayuda (`HELP_ICON`) | Ayuda en «Más» | `app.js` |
| Campana / campana tachada (`BELL`, `BELL_OFF`) | Avisos activos o no | `app.js` |
| Conversación, persona, flecha, chevron (`ICONS.chat/person/move/chevron`) | Acciones de tarjeta y plegar etapa | `board.js` |
| Calendario, flecha abajo | Selector de periodo | `dashboard.js` |
| WhatsApp, Instagram, Facebook, SMS, correo | Canal de cada conversación | `live-inbox.js` (`channelMarks`) |
| Chevron (`CHEVRON`) | Menú de canal | `live-inbox.js` |
| Atrás, ampliar, reducir, contacto (`UI_ICONS`) | Cabecera del chat | `message-view.js` |
| PDF, archivo (`ICONS`) | Adjuntos | `message-view.js` |
| Logo y marca | Cabecera, iconos de app | `public/assets/agora-logo.*`, `agora-mark.*`, `icon.svg`, `icons/` |

Pendiente: reunirlos en un solo archivo (`icons.js`) para no duplicarlos.

## 17. Capturas de referencia: así debe verse

Se regeneran con `python3 tools/make-manual.py` en cada versión que cambie algo visible (teléfono de 390 px, datos inventados). Si una pantalla tuya no se parece a esto, algo se ha roto.

| Pantalla | Captura |
|---|---|
| Conversaciones | `public/assets/manual/img/01-conversaciones.png` |
| Menú de canal | `public/assets/manual/img/02-canal.png` |
| Sin respuesta | `public/assets/manual/img/03-sin-respuesta.png` |
| Ficha de contacto | `public/assets/manual/img/04-ficha.png` |
| Ganadas | `public/assets/manual/img/05-ganadas.png` |
| Ventas sin monto (alerta) | `public/assets/manual/img/06-alerta.png` |
| Tablero | `public/assets/manual/img/07-tablero.png` |
| Tutorial (logo = ayuda) | `public/assets/manual/img/08-ayuda.png` |
| Mi cuenta | `public/assets/manual/img/09-perfil.png` |
| Embudos | `public/assets/manual/img/10-embudos.png` |

Faltan (no salen en el manual de vendedores): Consumos, Conexiones, «Más», «Entrar como» y modo oscuro.

## 18. Plantilla para proponer un cambio de interfaz

Copia esto en la conversación, la tarea o el PR:

```
### Problema
Qué falla o molesta, con captura si es posible. Quién lo sufre (vendedor / administrador / propietaria).

### Propuesta
Qué cambia, en una o dos frases. Qué se quita.

### Imagen
«Hoy» y «Propuesta» lado a lado (§9 septies). En claro y, si cambian colores, en oscuro.

### Impacto
- Roles: qué ve cada uno después del cambio.
- Teléfono 360–412 px: ¿cabe?
- Oscuro y texto grande: ¿revisado?
- Accesibilidad: contraste, toque de 44 px, movimiento reducido.
- Textos: ¿siguen el glosario (§13) y los textos de referencia (§14)?
- Manual: ¿hay que cambiar texto o capturas?
- Pruebas: cuáles cambian y cuál nueva lo vigila.

### Fuera de alcance
Lo que se deja para otro momento.
```

## 19. Datos de prueba

- **Nunca datos reales** de clientes en capturas, manual, pruebas ni imágenes de propuesta.
- Datos inventados para capturas y manual: `tools/make-manual.py` (personas, conversaciones, oportunidades, ventas, tablero). Reutilízalos para imágenes de antes y después.
- Pruebas de pantalla: cada `tests/*-ui.py` simula la cuenta con `page.route('**/api/**', …)`; no llaman a la plataforma de origen.
- Pruebas del servidor: `tests/*.php` contra una base MariaDB de pruebas, con la cuenta simulada (`GhlClient` con transporte falso). Crean y borran sus propios datos.
- Los correos de prueba terminan en `@example.test`; los teléfonos son de ejemplo.
