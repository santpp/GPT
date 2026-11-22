# Agora New App · novedades por versión

**Novedades 2.18.9 · La entrada encaja en el logo de la pantalla (22 nov):**
- Pedido del usuario: ya no se divide la palabra hacia los lados. Al terminar de cargar, **cada pieza del logo (aro, dos rayos de la estrella, Á, tilde, g, o, r, a) vuela una tras otra a su misma pieza del logo que se ve en pantalla** y encaja ahí. Es genérico: sirve en cualquier pantalla porque todas tienen el logo.
- **Dos salidas:** antes de iniciar sesión, las piezas van al logo de la cabecera y el fondo se desvanece; después de iniciar sesión y al recargar, van al logo de arriba y el fondo **se recoge hacia el logo**, como si la app se abriera desde él.
- Si no hay logo a la vista o está activado «reducir movimiento», se desvanece sin moverse.
- `tests/fixes-2188.py` ampliada a 11.

**Novedades 2.18.8 · Pantalla de entrada con el logo de Ágora (22 nov):**
- Pedido del usuario (idea «Casa Momo», sutil): al **abrir o recargar** y al **iniciar sesión**, el fondo de la app tapa la carga y el logo entra **pieza por pieza**: el aro, la estrella, y las letras de «Ágora» una a una, con una línea fina que avanza debajo. Cuando la pantalla terminó de cargar (sin esqueletos y con la letra lista; nunca antes de 0,9 s ni más de 7 s), **se abre en dos**: el símbolo se va a la izquierda, el nombre a la derecha y la app aparece en medio. Así no se ven los recursos llegando a destiempo.
- Con «reducir movimiento» en el teléfono solo se desvanece. Va dentro de `index.html` para verse desde el primer instante.
- Recordatorio: desde 2.18.0 Inicio no lleva widgets; se crean en «Editar Dashboard» de cada módulo.
- Prueba nueva: `tests/fixes-2188.py` (10).

**Novedades 2.18.7 · Tarjetas siempre 4 por fila (22 nov):**
- Pedido del usuario: en todos los módulos las tarjetas van **4 por fila** (2 en el teléfono); los gráficos no entran en esta regla. La última fila, si tiene menos, se reparte el ancho por igual. Comprobado en 11 módulos, ordenador y teléfono.
- `tests/fixes-2182.py` ampliada a 24.

**Novedades 2.18.6 · Dos tarjetas de leads, filas parejas y «Editar» en su lugar (22 nov):**
- Pedido del usuario: **«Leads de hoy» y «Leads asignados a mí» son dos tarjetas** del mismo tamaño, cada una con su número grande y su enunciado («Leads del día asignados a ti»). Se ordenan u ocultan por separado. Si el usuario no está enlazado con la cuenta, la tarjeta lo indica.
- **Proporciones:** cuando la última fila tiene menos tarjetas, se reparten el ancho por igual (antes una quedaba el doble de ancha). Comprobado en 9 módulos, ordenador y teléfono.
- **«Editar»:** títulos y enunciados dejan lugar al botón (en «Sin leer» el enunciado quedaba debajo).
- `tests/fixes-2182.py` ampliada a 23.

**Novedades 2.18.5 · Sin la nota de «Leads asignados a mí» (22 nov):**
- Pedido del usuario: se quita «Los que tienes a tu nombre en la cuenta» del Dashboard. Si el usuario está enlazado con la cuenta, solo se ve «Leads asignados a mí» y la cifra; si no lo está, sigue la indicación para enlazarlo.

**Novedades 2.18.4 · El resultado llena la tarjeta (22 nov):**
- Pedido del usuario (no compactar, sino agrandar): **la cifra ocupa todo el alto libre de la tarjeta**, centrada, y su letra crece según el ancho de la tarjeta y lo largo de la cifra: de 30 a 84 px en el ordenador (24 a 56 px en el teléfono). «4» se ve grande y «US$ 10.260,06» cabe sin cortarse. Comprobado en 7 módulos, ordenador y teléfono: ninguna cifra se sale.
- Se deshizo lo compactado en 2.18.3 (la nota de «Leads asignados a mí» vuelve a verse).

**Novedades 2.18.3 · Dashboard aún más compacto (22 nov):**
- La tarjeta «Leads de hoy» estiraba toda su fila con su bloque extra. Ahora «Leads asignados a mí» va en una línea corta (separada por una raya punteada) y su nota pasa a la ayuda al pasar el ratón; si el usuario no está enlazado con la cuenta, la indicación sigue visible. Menos aire entre título y cifra y relleno más chico en todas las tarjetas.
- La primera fila del Dashboard pasa de 263 px (2.18.1) a 170 px.

**Novedades 2.18.2 · Tarjetas más compactas y filtro del Dashboard (22 nov):**
- **Menos espacio en blanco:** en todas las tarjetas el contenido empieza arriba (antes se centraba en vertical). Solo el **resultado** va centrado, y **más grande** (28 a 36 px; 26 px en el teléfono). Títulos y enunciados siguen a la izquierda.
- **Líneas iguales:** la línea de arriba de cada tarjeta mide 4 px en todas las filas (antes la primera fila quedaba recortada y se veía más fina) y cada caja **cierra con una línea abajo**.
- **Esqueletos de carga** del mismo alto que una tarjeta cargada.
- **Filtro de fechas del Dashboard:** «Ayer», «Hoy» y «Otro periodo». «Otro periodo» abre el selector de fechas (desde/hasta) y, debajo, un mes completo.
- Prueba nueva: `tests/fixes-2182.py` (17).

**Novedades 2.18.1 · Los widgets son parte del tablero, como en la cuenta (21 nov):**
- Pedido del usuario: las cifras propias ya no van en un bloque aparte: **entran en la misma cuadrícula de tarjetas del módulo**, con la misma forma que sus vecinas (título, enunciado y cifra). Las tarjetas que ya había conservan sus títulos, enunciados y separaciones.
- **Se ordenan como un todo desde «Editar Dashboard»**: la lista de ese editor trae las tarjetas del módulo y los widgets juntos; se suben, bajan, muestran u ocultan igual (cada persona su vista, o la del equipo).
- **Todos los módulos y submódulos** (23): también Bandeja, Contactos, Convenios, Con convenio, Registro, Libro de ventas, Datos fiscales y Tasas. Cada uno tiene un solo «Editar Dashboard» y desde ahí se agregan los widgets. Los gráficos siguen al final del módulo.
- Prueba nueva: `tests/fixes-2181.py`; `widgets-ui.py` comprueba que un widget sube al primer lugar entre las tarjetas.

**Novedades 2.18.0 · Punto 7 de los widgets, plantillas por rubro y lo que reportó el usuario (21 nov):**
- **Los widgets se crean y editan dentro de cada tablero** (pedido del usuario): «Editar Dashboard» del módulo trae arriba «Widgets del equipo · Agregar/Editar widgets». Se quitó el botón suelto y **Inicio ya no tiene widgets** (solo sus avisos). Los módulos que no tenían «Editar Dashboard» (Gestión Comercial con reporte, Equipo, Tendencias, Catálogo, Oportunidades, Redes) ahora lo tienen y abre los widgets.
- **Correo de Ágora:** el selector solo mostraba subcuentas con la conexión ya probada. Ahora muestra también las guardadas sin probar («se prueba al guardar») y al guardar Ágora prueba la conexión; si no responde, lo explica. Si ninguna tiene conexión, la ventana dice dónde ponerla.
- **Buscar objetivo:** en «Fórmulas y variables» (y en el detalle de una fórmula) se elige una meta y una variable propia; Ágora calcula cuánto tiene que valer la variable (también en fórmulas no lineales, como la cuota de un préstamo) y ofrece guardarlo para ese mes. No cambia nada por sí solo.
- **Avisos de los widgets** (dentro de Ágora; los avisos al teléfono siguen en pausa): en el semáforo de una cifra, «Avisar en Inicio si queda en Cerca o Bajo». Inicio muestra los que están fuera de meta, con enlace al módulo. Respeta los tableros «solo de la administración».
- **Consolidado de la agencia** (Agencia → Consolidado): hasta 8 cifras por mes en todas las subcuentas, cada una en su moneda, con totales por moneda (sin mezclar), gráfico al final y descarga para Excel. Da las mismas cifras que dentro de cada subcuenta.
- **Plantillas por rubro:** cuatro incluidas (Educación con reporte mensual, Ventas y metas, Cobranza, Atención al cliente) y las que guarden los dueños desde una subcuenta («Guardar como plantilla»: tableros, fórmulas y variables, nunca datos de clientes). La administración las aplica desde el editor: «Agregar» o «Reemplazar»; crea las fórmulas que falten sin duplicarlas. Migración `012_widgets_plantillas.sql`.
- Pruebas nuevas: `tests/widgets-p7.py` (35) y `tests/fixes-218.py` (11); `widgets-ui.py` y `widgets-graficos.py` usan la nueva entrada.

**Novedades 2.17.0 · Eliminar subcuenta, eliminar y suspender usuarios (21 nov):**
- **Eliminar subcuenta** (solo el dueño Master), en la ficha de la subcuenta en Agencia: borra la subcuenta y **todo lo suyo** (usuarios y sus sesiones, ventas, cobros, facturas, contratos, reportes, tableros, widgets, la conexión con la cuenta y lo que el motor guardaba de ella). Lo que vive en la cuenta no se toca. Queda en el registro de la agencia. «Desactivar» sigue existiendo para solo cerrar el acceso.
- **Eliminar usuario** de una subcuenta, desde «Usuarios y roles» (Administración y dueños) y desde la lista de usuarios de la subcuenta en Agencia. Sus ventas y contratos se conservan, sin responsable. Nadie se elimina a sí mismo y la Administración no elimina a otra Administración.
- **Confirmación con una operación aritmética** («¿Cuánto es 4 × 9?»): el botón queda bloqueado hasta escribir el resultado. La operación la hace el servidor, va firmada, ligada a lo que se borra y a quien lo pide, vence en 5 minutos y el servidor vuelve a comprobarla. Un resultado malo no borra, lo dice y trae otra operación.
- **Suspender:** «Desactivar» y «Revocar» pasan a llamarse **Suspender** (cierra sus sesiones, conserva sus datos, se puede reactivar). El estado se ve como «Suspendida».
- Prueba nueva: `tests/borrado.py` (36).

**Novedades 2.16.1 · Tarjetas de cifras: título y enunciado a la izquierda (21 nov):**
- Pedido del usuario: en todas las tarjetas de cifras (Inicio, módulos y widgets) el **título y el enunciado van alineados a la izquierda**; el **resultado** (la cifra y su línea de abajo) sigue **centrado**. Igual en el teléfono. Las ideas del punto 7 del plan de widgets quedan para luego.

**Novedades 2.16.0 · Widgets fases 4, 5 y 6: gráficos, detalle con descarga, «quién ve» y cifras del reporte (21 nov):**
- **Gráficos (fase 4):** en el mismo editor, cada widget puede ser cifra, **línea, barras, área** (hasta 4 series, últimos 3 a 12 meses), **dona** (partes de un total, con su %; más de 5 partes se juntan en «Otros») o **tabla de los primeros** (3 a 10, de mayor a menor). Van **al final del módulo**, en «Gráficos del equipo». Al pasar el ratón (o tocar) muestran el mes y el valor de cada serie. Los puntos son **las mismas cifras** que el widget de ese mes (probado). Colores de la identidad en orden fijo, revisados para daltonismo en modo claro y oscuro; leyenda con dos series o más; un solo eje (si las series miden cosas distintas se avisa). Una cifra «de hoy» no puede ir en un gráfico por mes (se explica).
- **Detalle y descarga (fase 5):** tocar una cifra (o «Ver detalle y descargar» en un gráfico) abre qué mide, de dónde sale, los últimos 6 meses y, si es fórmula, **cuánto vale cada parte**. «Descargar» baja un archivo para Excel y queda en Auditoría.
- **Quién ve (fase 5):** cada tablero puede ser de **todo el equipo** o **solo de la administración** (se marca en pantalla; el servidor no se lo manda a los demás). **«Copiar los widgets de otro módulo»** en el editor. Plantillas por rubro: más adelante (decisión del usuario).
- **Módulos del reporte (fase 6):** 14 cifras nuevas del **reporte mensual importado** (vendido, inscritos, recurrencia, primera cuota, entradas, pendiente, comisiones, inversión en campañas, resultados, ventas por campañas, costo por resultado, gastos fijos, gastos variables y nómina), en dinero y no en centavos. Los widgets salen en todos los módulos armados con el reporte (también Catálogo); un mes sin reporte da «—» con la razón.
- **Cuota de la cuenta:** las cifras del mes que consultan la cuenta se guardan unos minutos por usuario (60 s el mes en curso, 10 min los meses cerrados) para que un gráfico de 12 meses no gaste la cuota. Migración `011_widgets_cache.sql`.
- Las tablas de los widgets no reciben el buscador ni las columnas de las tablas del módulo.
- Pruebas: `tests/widgets-graficos.py` (21) y `tests/widgets-api.py` ampliada a 69.

**Novedades 2.15.0 · Widgets del equipo: editor de tableros y editor de fórmulas (21 nov):**
- **«Widgets del equipo»** en Inicio, Gestión Comercial, Ventas, Productos, Facturación, Facturas, Costos, Oportunidades, Tareas, Equipo, Publicidad, Tendencias, Calidad y Redes: van justo después de las cifras del módulo, con el mismo estilo (línea, «Editar» y hover rojo). Los ve todo el equipo; si un módulo no tiene widgets, solo la administración ve «Agregar widgets».
- **Editor de tableros** (solo Administración, Supervisión y dueños): agregar una cifra del sistema o una fórmula, ordenar, duplicar, quitar; por widget: título, nota, tamaño (1 o 2 columnas), período (el del módulo, el mes en curso, el anterior o un mes fijo), comparar con el mes anterior o el mismo mes del año pasado, formato, decimales y **semáforo** (en meta / cerca / bajo, «mejor si es más alto o más bajo»). «Restablecer» quita los widgets del módulo. Si dos personas editan a la vez, la segunda recibe aviso en vez de pisar los cambios.
- **Editor de fórmulas:** al escribir `{` sugiere cifras, fórmulas y variables (flechas, Enter); «Insertar función» con la ayuda de cada una; la fórmula se ve **con colores** y el error se **marca donde está**; **vista previa en vivo** con el valor real del mes; ejemplos de un toque (Margen, Margen %, Ticket promedio, Ventas que faltan para la meta, Crecimiento, Días hábiles que quedan).
- **Fórmulas y variables:** lista de fórmulas para editarlas o borrarlas (no se borra una que use un tablero; se explica) y variables propias (por ejemplo, un sueldo o una meta) para todos los meses o solo uno.
- Archivo aparte `assets/widgets.js` (decisión del usuario); `agora-app.js` solo expone lo necesario.
- Pruebas: `tests/widgets-ui.py` (25, escritorio y teléfono, administración, ejecutivo y consulta) y `tests/widgets-api.py` ampliada a 52 (tableros: validación, conflicto de versión, permisos, fórmula en uso).

**Novedades 2.14.0 · Widgets, primera entrega: diccionario de métricas y motor de fórmulas (21 nov, sin cambios visibles):**
- **Decisiones del usuario:** los widgets se construyen ya, en archivos aparte; **solo administración** (Administración, Supervisión y dueños) crea widgets, fórmulas y variables; plantillas por rubro más adelante; funciones extra financieras y de fechas.
- **Diccionario de 37 métricas** (ventas, cobros, metas, rentabilidad, cuenta, día, ganadas, tareas). Cada una lee la misma ruta que su tarjeta, con el mismo usuario y sus permisos: el widget da **exactamente** la cifra de hoy (probado con administración, ejecutivo, consulta y una subcuenta sin reporte).
- **Motor de fórmulas propio** (nunca ejecuta texto como código): `+ − × ÷ ^`, paréntesis, comparaciones, SI/Y/O/NO, MIN, MAX, REDONDEAR, RAIZ, POTENCIA, LOG, PROMEDIO, MEDIANA, DESVIACIÓN, CRECIMIENTO, PORCENTAJE; **financieras** (PAGO, VF, VA, NPER, INTERES, INTERES_COMPUESTO) y **de fechas** (HOY, FECHA, DIAS, DIAS_HABILES, FIN_MES…). Controla las unidades (no suma dinero con porcentaje), «—» con la razón si falta un dato o se divide entre cero, y errores que señalan dónde está el problema.
- **Fórmulas y variables guardadas por subcuenta:** una fórmula puede usar otras y variables por mes (por ejemplo, el sueldo de noviembre); «mes anterior» y «año pasado»; se rechazan vueltas y nombres repetidos.
- Siguiente: la pantalla (editor de tableros y editor de fórmulas con vista previa).
- Pruebas nuevas: `tests/formulas.php` (433, 300 al azar) y `tests/widgets-api.py` (36).

**Novedades 2.13.1 · Líneas azules, «Editar» gris y clave de dueño solo por terminal (21 nov):**
- **Cifras:** la línea de arriba de cada cifra vuelve a ser **azul** y al pasar se vuelve **roja** con brillo. Los botones «Editar» van en **gris oscuro sobre blanco** con borde gris; al pasar, fondo rojo y letra blanca.
- **La clave de un dueño (Master o Superadmin) solo se cambia por terminal** (`php servidor/bin/cambiar-clave.php correo`): sin «Cambiar mi clave», sin «¿Olvidaste tu clave?» (responde igual, pero no envía código) y el Master tampoco cambia la de otro dueño desde Usuarios. El servidor lo exige; en el perfil del dueño se explica. Los demás usuarios siguen igual.
- Las cuentas de dueño siguen creándose solo por terminal (decisión confirmada).
- Prueba nueva: `tests/dueno-clave.py`.

**Novedades 2.13.0 · Correo de Ágora, código al entrar, «¿Olvidaste tu clave?» y sesión de 14 días (21 nov):**
- **Correo de Ágora sin SMTP:** el dueño elige en Agencia → «Correo de Ágora» la subcuenta de la agencia por la que salen los correos del sistema. Usa el correo de esa cuenta. Botón «Probar envío». Solo el dueño (Master) lo ve y lo cambia.
- **Código al iniciar sesión:** con el correo elegido, después de la clave llega un código de 6 dígitos (caduca en 10 minutos, 5 intentos, se usa una vez, «Reenviar código» cada minuto). Se puede apagar desde el mismo selector.
- **Recuérdame (14 días):** la sesión dura 14 días y ese navegador queda **de confianza**: no vuelve a pedir el código en 14 días. Es una cookie segura y aleatoria del navegador, **no la IP** (no falla con CGNAT ni con datos móviles). Sin «Recuérdame», la sesión es normal y cada entrada pide código.
- **«¿Olvidaste tu clave?»** en pasos: correo → código (15 minutos) → clave nueva dos veces. Responde igual exista o no el correo. Al cambiarla se cierran todas las sesiones y se olvidan los equipos de confianza.
- **Seguridad:** los códigos nunca se guardan ni se muestran (solo su huella), límites de intentos por correo e IP, y si la subcuenta que envía se desconecta se vuelve a entrar solo con la clave (nadie queda fuera).
- Convenios: el texto ya no promete un correo («se genera el enlace de firma para compartirlo con el cliente»).
- Pruebas nuevas: `tests/acceso-correo.py` (23) y `tests/acceso-ui.py` (15). La cuenta simulada ahora recibe correos.

**Novedades 2.12.15 · Pendientes medios resueltos: listo para desplegar (21 nov):**
- **[9]** El registro de actividad (agencia y Auditoría) cuenta el detalle en palabras («Se cambió el token de la cuenta»): sin JSON ni identificadores.
- **[10]** Abrir una venta cuya oportunidad ya no existe en la cuenta responde «La cuenta no encuentra esa oportunidad» y **no pausa la subcuenta** (antes, el Tablero quedaba ~15 s «en espera»).
- **[11]** Las descargas de ejecutivos y de consulta **quedan en Auditoría**. Solo pueden registrar descargas; ver la auditoría sigue siendo de quien tiene el permiso.
- **[13]** Teléfono: el aviso «venta por completar» va encima de la barra de abajo, deja espacio a los botones fijos y los avisos breves suben por encima.
- Backend revisado antes de desplegar: los 109 archivos PHP sin errores y 13 pruebas de servidor en verde (256 comprobaciones). Prueba nueva: `tests/fixes-21215.py`.

**Novedades 2.12.14 · Fixes menores y cabeceras de seguridad para el despliegue (21 nov):**
- **Cabeceras de seguridad** en el `.htaccess`: política de contenido (CSP) más completa, HSTS (un año), permisos del navegador (sin cámara ni ubicación), aislamiento de ventana y de recursos (COOP/CORP), sin «X-Powered-By». Se bloquean los archivos ocultos y de trabajo (`.git`, `.env`, notas, respaldos, registros, guiones). La API manda las suyas aunque el servidor no aplique el `.htaccess` (sin marco, CSP cerrada, HSTS por HTTPS). Se revisaron 29 pantallas con dos cuentas: ninguna rompe la política.
- **M2:** /modulos/general lleva al Inicio (antes, un módulo vacío).
- **M3:** rol sin enlace con la cuenta: solo el aviso de enlace (en el Inicio y sin el «No tienes permiso…» repetido).
- **M4:** «1 cierre en el mes» (antes «1 cierres»).
- **M5:** los Excel de Usuarios y de Auditoría traen el nombre del rol («Administración»), no el código.
- **M7:** al editar una venta importada sin vendedor queda vacía de verdad (null), no con un texto vacío.
- **M8:** «Buscar en la cartera» ahora usa el texto buscado (la ruta sigue en uso; se corrigió en vez de borrarla).
- M1 y M6 ya estaban resueltos en el código (lo de M1 en ASOCIM es su logo de prueba, que es la imagen de Ágora).
- Prueba nueva: `tests/fixes-21214.py`.

**Novedades 2.12.13 · «¿Venta ganada?» igual que en Ventas, también desde la ficha del contacto (21 nov):**
- **Un solo flujo:** desde la conversación y desde la ficha del contacto (Contactos) se elige la oportunidad (o «+ Nueva venta») y enseguida se abre el **formulario de venta de Ventas** (Cliente · Producto y cobro · Detalles · Resumen), que guarda el monto en la oportunidad, la venta, las tareas y los cobros. Ya no se escribe el valor dos veces.
- **Arreglo:** con «+ Nueva venta» el formulario de venta no se abría (la cuenta devuelve el código de la oportunidad nueva en otro campo y se perdía).
- **Botón «¿Venta ganada?» en la ficha del contacto**, junto a «Enviar mensaje» y «Editar ficha».
- Sin la ventana suelta «venta ganada sin monto» de la cuenta: en el ERP las ganadas sin venta salen como «ventas por completar» y llevan al formulario completo.
- Prueba nueva: `tests/fixes-21213.py`. La cuenta simulada de pruebas ahora sabe crear oportunidades.

**Novedades 2.12.12 · Íconos de categoría, sin frases obvias y Bandeja más arriba (21 nov):**
- **Gestión y resultados** lleva un ícono de bolsa de valores (velas sobre ejes). **Administración y Ajustes** usan un engranaje de verdad (antes parecía un sol).
- **Sin frases obvias bajo el título** de los módulos (por ejemplo «Todos los canales en una sola lista…»). La descripción de cada módulo queda solo en el Inicio. El Dashboard conserva su línea de fecha y hora de actualización.
- **Bandeja:** el conteo y «Mostrar» van en una sola fila compacta; la lista de conversaciones empieza bastante más arriba.

**Novedades 2.12.11 · «Resumen del día» como los demás módulos (21 nov):**
- El título y los botones «Editar Dashboard» y «Dashboard» van **fuera de la tarjeta**; la tarjeta blanca es solo la caja de cifras. Título de 20 px (18 en el teléfono), como las secciones de los demás módulos.
- Se revisaron todos los módulos (con y sin reporte): ningún «Editar Dashboard» ni «Dashboard» queda dentro de una tarjeta. `tests/fixes-2126.py` lo comprueba.

**Novedades 2.12.10 · Botones alineados y colores del menú también para el dueño (21 nov):**
- «Editar Dashboard» y «Dashboard» en «Resumen del día» ahora son idénticos: mismo alto, relleno y línea de texto (en el teléfono quedaban desalineados).
- El color por categoría del menú de módulos no se aplicaba cuando el dueño entraba con una subcuenta abierta (ese arranque salía antes). Ahora se aplica siempre.

**Novedades 2.12.9 · Línea roja solo al pasar y botones iguales en «Resumen del día» (21 nov):**
- Las cifras quedan con su **línea fina gris** de siempre; la **línea roja con brillo aparece solo al pasar** (o al tocar en el teléfono).
- **«Dashboard» / «Ir a Dashboard»** usa el mismo botón que «Editar Dashboard» (píldora gris), alineados en la misma línea.

**Novedades 2.12.8 · Cifras como la referencia del usuario y «Dashboard» (20 nov):**
- **En todos los módulos**, las cifras van **centradas** y compactas: rótulo, descripción breve, cifra y nota, sin íconos y sin el degradado de la tarjeta grande. En las filas con una tarjeta más alta, el contenido de las demás queda centrado en vertical (sin hueco abajo).
- **Línea roja de Ágora** arriba de cada cifra (antes azul), que **se ilumina** al pasar: vino intenso con un brillo suave.
- **«Editar»** solo con la palabra, sin lápiz: arriba a la derecha en el ordenador y debajo de la cifra en el teléfono.
- **«Tablero comercial» ahora se llama «Dashboard»** en el menú, la cabecera, Athena y los textos de ayuda.
- **Un color por categoría** (menú y tarjetas de módulos del Inicio): Ventas vino, Facturación oro, Clientes y atención azul profundo, Gestión y resultados azul medio, Contratos rosa y Administración vino oscuro.

**Novedades 2.12.7 · Más color en el menú y respuesta de color en «Cancelar» y la X (20 nov):**
- **Menú con más variedad, dentro del manual y sin oro:** cada sección tiene su color y sus módulos lo heredan. Ventas en vino, Facturación en vino oscuro, Clientes y atención en azul profundo, Gestión y resultados en azul medio, Contratos en rosa vino y Administración en pizarra.
- **«Cancelar», «Cerrar», «Restaurar» y la X de las ventanas** se llenan de vino con letra blanca al pasar, enfocar o tocar, con las mismas animaciones que «Editar».
- En el teléfono, el lápiz de cada cifra también da ese destello al tocarlo.

**Novedades 2.12.6 · Nuevo estilo de las cifras, celdas vivas y «Resumen del día» (20 nov):**
- **Estilo de las cifras (idea del usuario), en todos los módulos:** a la izquierda; rótulo con flecha y debajo una línea breve que explica la cifra («Leads del día», «Conversaciones de la cuenta»); la cifra grande y su nota. Ícono de trazado lineal pequeño en vino arriba a la derecha. La tarjeta grande del Tablero lleva un degradado muy suave y su fecha como etiqueta («Hoy · 20 nov»).
- **Sin huecos:** la última fila de cada caja se estira hasta llenar el ancho, también al ocultar o reordenar cifras. Si una caja tiene menos cifras que columnas, se reparten en partes iguales.
- **Celdas vivas:** cada cifra lleva arriba una línea azul de Ágora que, al pasar por esa celda (o enfocarla con el teclado), se llena de vino de izquierda a derecha. Las celdas entran escalonadas con las animaciones del sistema (respeta «reducir movimiento»).
- **Botón «Editar» en cada cifra:** contorno vino; al pasar se llena de vino con letra blanca. Abre «Editar Dashboard» con esa cifra marcada. En el teléfono queda solo el lápiz para que el rótulo quepa.
- «Resumen de hoy» del Centro de mando pasa a llamarse **«Resumen del día»**.
- Rótulos de las cifras a 14 px (13 en el teléfono). Prueba nueva: `tests/fixes-2126.py`; `fixes-2123.py` y `tipografia.py` actualizadas.

**Novedades 2.12.5 · El teléfono cumple las mismas medidas que el ordenador (20 nov):**
- La 2.12.4 solo se había medido en el ordenador. En el teléfono se escapaban algunas piezas:
  - las tablas leídas como fichas (radio 14, relleno 12 × 14 y 10 px entre fichas);
  - los recuadros de módulos (radio 20);
  - las tarjetas de Oportunidades (14), Contratos (22) y Herramientas de agencia (18);
  - los íconos de sección, unos de 36 px y otros de 32.
- Ahora todo tiene radio 16. Fichas y tarjetas llevan relleno de 14 × 16 y 12 px entre una y otra. Los íconos de sección son de 36 px en el ordenador y 32 px en el teléfono, todos iguales.
- `tests/fixes-2123.py` también mide el teléfono.

**Novedades 2.12.4 · Armonía jerárquica: las mismas medidas en todos los módulos (20 nov):**
- Se midieron las 27 pantallas (con y sin reporte) y se igualó todo:
  - **Esquinas:** 16 px en todas las cajas. Antes había 22, 20, 16 y 14.
  - **Relleno de los paneles:** 20 × 24 px (16 en el teléfono). Antes había siete combinaciones.
  - **Espacio entre cajas seguidas:** 16 px siempre. Antes, 16, 18, 20 o 32.
  - **Del título del módulo a la primera caja:** 20 px.
  - **Encabezados de sección:** 32 px de aire arriba y 12 px hasta su caja.
- Los rótulos pequeños del Inicio («Acceso reciente») dejan las mayúsculas y pasan a 13 px. Los títulos de panel quedan todos en 16,5 px.
- `tests/fixes-2123.py` lo comprueba en todos los módulos.

**Novedades 2.12.3 · Todos los módulos con la misma caja de cifras, sin carnaval y con orden editable (20 nov):**
- **Todos los módulos:** cada grupo de cifras es una sola tarjeta blanca con las cifras separadas por líneas finas (Ventas, Cobros, Facturas, Costos, Equipo, Tendencias, Calidad, Tasas, Embudo y publicidad, Redes, Tablero…), con y sin reporte mensual. Ya no hay cuadrículas de tarjetas sueltas.
- **Sin carnaval de colores:** los íconos de los módulos usan solo vino, vino oscuro, azul profundo y azul medio (sin oro ni verde). Los gráficos de reparto (asesores, fuentes) y las etapas del embudo, vino, azul profundo y sus tonos. El verde y el ámbar quedan solo para estados.
- **Los gráficos de ventas** («Ventas del mes» y «Tendencia del año») salen del Tablero comercial: están en Gestión Comercial, con «Editar gráficos».
- **«Editar Dashboard» en todos los módulos con cifras**, también en el Tablero comercial y en las vistas armadas con el reporte mensual: se elige qué cifras ver y en qué orden (flechas), se guarda por persona o para el equipo y «Restaurar» vuelve a la vista de fábrica. Las cifras que aparezcan después (otro período) se muestran al final.
- Prueba nueva: `tests/fixes-2123.py`.

**Novedades 2.12.2 · Tablero comercial como los demás tableros (20 nov):**
- Cada bloque de cifras del Tablero (Tu día, Operación del día, Rentabilidad…) es **una sola caja blanca** con las cifras separadas por líneas finas, igual que Gestión Comercial y «Resumen de hoy». Ya no hay tarjetas sueltas con espacio entre ellas.
- 4 por fila en el ordenador y 2 en el teléfono (la tarjeta grande ocupa la fila entera en el teléfono).
- `tests/fixes-2121.py` lo comprueba.

**Novedades 2.12.1 · Formulario de factura más claro y 4 cifras por fila en todos los tableros (20 nov):**
- **Factura:**
  - «Moneda y tasa» va antes de los productos, con una línea clara: «1 US$ = Bs. 245,50 · BCV dólar del 20/11/2026».
  - Ya no aparece el campo fijo «Moneda: Bolívares».
  - Cada línea dice su moneda («Precio (US$)») y su equivalente («1 × US$ 55,00 = Bs. 13.502,50»).
- **El total ya no parece incongruente.** Antes decía «US$ 63,80 × 918,77», pero la tasa estaba redondeada a dos decimales y el cálculo se hace línea por línea en Bs, así que la cuenta no daba. Ahora dice «Base Bs. … + IVA Bs. …» (que suma exactamente el total) y, aparte, el equivalente en US$. La tasa se muestra con todos sus decimales. El total que se ve es el mismo que guarda el servidor, al céntimo.
- **4 cifras por fila en el ordenador** en el Tablero comercial (la tarjeta grande pasa a ser una más de la fila) y en el «Resumen de hoy», como en los demás tableros. En pantallas medianas, 2; en el teléfono, como antes.
- Prueba nueva: `tests/fixes-2121.py`.

**Novedades 2.12.0 · Divisas en la factura, colores de Ágora y selectores como el del Inicio (20 nov):**
- **Tasas del día: tres tasas.** BCV dólar, BCV euro y USDT de Binance (el punto medio de los anuncios confiables de P2P). Llegan solas cada hora; si alguna no llega, se escribe a mano por subcuenta.
- **Factura con precios en divisas:**
  - La factura se emite siempre en bolívares.
  - Al hacerla se elige «Precios en» (Bs., US$ o €) y con qué tasa se pasan a Bs.
  - El editor muestra el cálculo («US$ 10,00 × 245,50 (BCV dólar del 20/11/2026)»).
  - **El servidor hace la conversión** con la tasa vigente; no se fía del navegador.
  - La factura impresa dice de qué moneda y tasa salieron los precios.
  - Un precio en euros solo se convierte con BCV euro.
  - «Facturar» un cobro en dólares abre la factura con los precios en US$ y la tasa BCV elegida.
- **Vencimiento opcional:** una fecha (con hora, si se quiere) o «en N horas» (de 1 a 720).
- **«Editar Dashboard» en la línea de la cabecera** también en Gestión Comercial (con «Ver las ventas»), junto a «Descargar», como en todos los módulos.
- **Solo los colores de Ágora.** Apariencia ya no ofrece «Color de acento», y la ficha de la subcuenta ya no tiene «Colores de la cuenta». Los gráficos de reparto (asesores, fuentes) y las etapas del embudo usan la escala del manual: vino, azul profundo, oro y sus tonos.
- **Selectores de período como el del Inicio:** el Tablero comercial (Hoy, Ayer, 7 días…) y el mes de Rentabilidad usan la misma barra: opción en blanco, punto verde y casita para volver.
- «Cifras del mes» pasa a llamarse **«Editar costos o números»** (con lápiz).
- Prueba nueva: `tests/fixes-2120.py`.

**Novedades 2.11.8 · Consumos: de qué subcuenta es cada gasto (20 nov):**
- Antes, un cargo solo se asignaba a una subcuenta si la cuenta ponía su identificador en el campo de siempre. Si no, iba a «Agencia · movimientos propios»: así cayeron los 6.465 cargos de WhatsApp.
- **Búsqueda completa:** Ágora busca el identificador de cualquier subcuenta conectada en *todos* los datos del cargo. Si aparece en otro lugar, el cargo se suma a esa subcuenta sin hacer nada más.
- **«¿De qué subcuenta es el gasto sin identificar?»** (Agencia → Consumos): muestra los datos que traen esos cargos y cuánto suma cada valor. Por ejemplo, el número de WhatsApp de cada línea. Al elegir la subcuenta y tocar «Asignar», queda una **regla**: desde ese momento, esos cargos suman a esa subcuenta, en este mes y en los demás. Las reglas se pueden quitar y quedan en el Registro de actividad.
- La referencia de cada mensaje es distinta en cada cargo, así que no sirve para agrupar y no se ofrece.
- Los tipos se leen en palabras («WhatsApp · marketing») y sin la referencia técnica («ref: wamid…»).
- El grupo de la agencia se llama «Agencia · sin subcuenta identificada».
- Solo la dueña ve y cambia las reglas; el servidor lo exige.
- Prueba nueva: `tests/consumos-reparto.py`.

**Novedades 2.11.7 · Mismas cifras que Mobile y menú de la agencia unificado (20 nov):**
- **«Leads de hoy» igual que en Mobile.** El ERP contaba «hoy» en hora universal: en Venezuela, de 8 de la noche a 8 de la noche. Mobile lo cuenta con el día del teléfono. Ahora el ERP manda la zona horaria y el día de quien mira, igual que Mobile. Las demás cifras (sin leer, conversaciones, ganadas) ya eran las mismas: salen de la misma cuenta con el mismo código.
- **Menú de la agencia** (Subcuentas y Centro de mando) con el mismo lenguaje que el menú de las subcuentas:
  - Fondo claro e ícono en recuadro de color.
  - El activo, con fondo suave de marca.
  - Rótulos en tipo oración.
  - Sin el logo repetido (ya está arriba), sin la franja dorada, sin mayúsculas espaciadas y sin el aviso «Estudio · Próximamente» (Estudio sigue en Herramientas de Agencia).
- En el teléfono, ese menú ya no ocupa media pantalla: es una fila de pestañas deslizable (pendiente [14]).
- «Seguridad de la agencia» decía «entro» (el código interno); ahora dice «Entró».
- **Inicio de sesión más limpio:** sin el 👋 ni el punto verde junto a Athena, sin las píldoras de abajo (Convenios, Ventas, Auditoría), sin «Operación comercial y convenios», sin «Entra a la operación comercial…» y sin «Tu acceso lo crea el administrador…».
- Prueba nueva: `tests/fixes-2117.py`.

**Novedades 2.11.6 · Atajos en las tarjetas, con un solo criterio (20 nov):**
- **Tablero comercial:** cada tarjeta lleva a su módulo, como «Resumen de hoy». El rótulo termina en «›» y se toca la tarjeta entera.
  - «Sin leer» abre la bandeja filtrada en «Sin leer»; «Esperando respuesta», en «Esperando».
  - «Leads de hoy» abre los contactos de hoy.
  - Ganadas, valor cerrado, ticket y tasa de cierre abren las ventas ganadas en Oportunidades.
  - «Mis ventas del mes» abre Ventas; ganancia, costos y retorno abren Costos.
- **«Editar Dashboard» en todos los módulos:** la tarjeta cuyo dato vive en otro módulo lleva a él. Ejemplos: «Quien más vendió» en Equipo lleva a Ventas; «Cobrado» en Gestión Comercial lleva a Cobros. Las tarjetas del propio módulo no llevan a ningún lado.
- Solo hay atajo si la persona puede abrir ese módulo.
- Las medidas de la letra y de las tarjetas quedan anotadas en la guía de diseño (`docs/DESIGN.md`) y en el contexto del ERP.
- Prueba nueva: `tests/atajos.py`.

**Novedades 2.11.5 · Una sola fuente y una sola escala (20 nov):**
- La fuente es **Author** en todo el ERP (como en Mobile); solo los códigos usan letra de ancho fijo.
- **Mismos tamaños en los 26 módulos:**
  - Título del módulo: 28 px (24 en el teléfono).
  - Títulos de sección: 20 px; títulos de panel: 16,5 px.
  - Tarjetas, como «Resumen de hoy»: rótulo de 12,5 px y cifra de 26 px (22 en el teléfono).
  - Botones de cabecera: 13,5 px.
- Antes había títulos de 18, 21,75, 22, 24 y 33 px y cifras de 26, 30 y 30,7 px según el módulo.
- Prueba nueva: `tests/tipografia.py` (escritorio y teléfono, 26 módulos).

**Novedades 2.11.4 · Gestión Comercial como «Resumen de hoy» en el teléfono (19 nov):**
- En el teléfono, la caja «Cifras del mes» de Gestión Comercial pone las cifras en **dos columnas con divisores**, igual que «Resumen de hoy» en Inicio (antes las apilaba en una sola). Los montos largos caben sin partirse.
- `tests/fixes-2111.py` lo comprueba (14).

**Novedades 2.11.3 · Tarjetas sin íconos (pedido del usuario, 19 nov):**
- **Las tarjetas de cifras van sin ícono**, como «Resumen de hoy»: título, cifra y nota. Aplica a todos los módulos, a la caja de Gestión Comercial y a las tarjetas del reporte mensual.
- **«Descargar», «Editar Dashboard» y «Editar gráficos» recuperan sus íconos** (se entendió mal en la 2.11.2: lo que se pedía quitar eran los íconos de las tarjetas).
- `tests/fixes-2111.py` lo comprueba (13).

**Novedades 2.11.2 · Botones sin íconos (pedido del usuario, 19 nov):**
- «Descargar», «Editar Dashboard» y «Editar gráficos» van sin ícono, solo con el texto, y quedan en la misma línea también en el teléfono.
- `tests/fixes-2111.py` lo comprueba (12).

**Novedades 2.11.1 · Correcciones del usuario (19 nov):**
- **Facturación solo en bolívares.** El editor ya no ofrece otra moneda. «Facturar» un cobro en dólares pasa el monto a bolívares con la tasa BCV vigente y lo anota en la línea. La factura en bolívares se emite aunque falte la tasa.
- **Dólares y euros quedan listos para activar:** al activarlos, la administración de cada subcuenta (y los dueños) elige en Datos fiscales en qué monedas factura.
- **«Por forma de cobro» muestra siempre las tres formas:** Recurrente, Venta no recurrente y Venta única, aunque alguna esté en cero. Además, hay dos tarjetas nuevas para elegir: «Ventas no recurrentes» y «Ventas únicas».
- **En todos los módulos:** el botón de descarga dice **«Descargar»** con su ícono (antes «Excel»), y **«Editar Dashboard»** lleva un lápiz (antes «Editar tarjetas» con engranaje). «Editar gráficos» también lleva el lápiz.
- Prueba nueva: `tests/fixes-2111.py` (11).

**Novedades 2.11.0 · Facturación con su propia categoría y facturación fiscal (19 nov):**
- **Categoría nueva «Facturación»** con cinco submódulos: **Cobros** (el antiguo «Facturación»; la dirección vieja sigue funcionando), **Facturas**, **Libro de ventas**, **Datos fiscales** y **Tasas del día**. «Ventas y facturación» pasa a llamarse «Ventas».
- **Facturas fiscales (SENIAT)**, con el motor de facturación de mi.agora:
  - Factura, nota de crédito y nota de débito.
  - Correlativo sin saltos, que se asigna al emitir (un borrador no gasta número).
  - Número de control de la imprenta digital (SERDIMPRE): mientras no haya conexión con ella, se registra a mano. También admite formas libres con rangos de números de control.
  - Tasa BCV y equivalente en bolívares. IVA general, reducido, de lujo o exento, e IGTF si se activa.
  - Factura lista para imprimir o guardar en PDF.
  - Lo emitido no se cambia: se anula (con motivo) o se corrige con una nota. La base de datos lo impide y cada documento queda sellado en una cadena que se puede verificar.
- **Factura simple** (sin validez fiscal): la que emiten las subcuentas sin datos fiscales. No pide RIF, domicilio ni tasa.
- **Facturar desde Cobros:** cada cobro tiene «Facturar», que abre la factura con el cliente, el producto y el monto. Una vez facturado, el cobro muestra el número de la factura.
- **Roles:**
  - Los vendedores y usuarios facturan, y ven solo las facturas que prepararon.
  - Anular y emitir notas: administración y supervisión.
  - Datos fiscales: solo administración.
  - Libro de ventas: administración y supervisión.
  - Consulta: solo ve.
- **IVA por producto:** en Productos y servicios cada producto dice qué IVA lleva, y la factura lo toma solo.
- La tasa BCV llega sola cada día (tarea programada). Si no llega, se escribe en Tasas del día.
- Pruebas nuevas: `tests/facturacion.py` (34) y `tests/facturacion-ui.py` (26).

**Novedades 2.10.3 · Correcciones del cliente (19 nov):**
- **Costos se editan en Costos.** Antes las tarjetas mostraban «—» y no había dónde cargar nada en el módulo. Ahora «Cargar costos» abre una lista por concepto: sueldos, alquiler, comisiones o publicidad fuera de la cuenta, cada uno como fijo, variable o publicidad, con «Copiar del mes anterior». Con eso las tarjetas miden: **Costo total, Inversión en anuncios, Costos fijos y Margen del mes**, y también Costos variables y Costo total por venta si se eligen. Configuración mensual muestra esos totales sin poder pisarlos y lleva a Costos.
- **Criterio único de edición en todos los módulos:** se edita donde se ve, un dato tiene un solo lugar donde se cambia y toda caja de cifras tiene «Editar tarjetas». Calidad de datos ya lo tiene, y «Elegir cifras» pasó a llamarse «Editar tarjetas» en todos los módulos.
- **Bandeja: la ficha del contacto se encuentra.** El ícono sin texto que la abría es ahora el botón «Ficha». Allí están «Editar» (datos del contacto) y **«Agregar observación»** (antes «Crear nota»), y la sección se llama «Observaciones».
- Redes y anuncios, en una subcuenta sin conexión, ofrece «Conectar en Ajustes» en vez de un «Reintentar» que no servía.
- Revisión visual de los 27 módulos, en teléfono y escritorio, con y sin reporte: sin desbordes, sin textos técnicos y sin errores.
- Prueba nueva: `tests/costos-editar.py` (17).

**Novedades 2.10.2:**
- **Nuevo comando para destrabar la entrada:** `php servidor/bin/cambiar-clave.php correo@ejemplo.com` pone una contraseña nueva a cualquier cuenta (también la Master), la deja activa, cierra sus sesiones y quita el bloqueo por intentos. `… cambiar-clave.php --desbloquear` solo quita los bloqueos. Antes no había forma de cambiar la clave de un dueño.
- La guía de instalación explica los tres casos en que no deja entrar: clave incorrecta, «Demasiados intentos» y «Ambiente de pruebas» (falta `config.php`).

**Novedades 2.10.1:**
- **Sin textos repetidos en los encabezados:** si el texto pequeño de arriba dice lo mismo que el título (por ejemplo «Gestión comercial» sobre «Gestión Comercial»), ya no se muestra.
- **El botón «Excel» de arriba lleva el ícono de descarga**, igual que el «Excel» de las tablas (antes, un documento).
- `tests/herramientas.py` lo comprueba (39).

**Novedades 2.10.0 · Orden de los módulos (pedido del usuario):**
- **Gestión Comercial:** las cifras van en una sola caja, como el «Resumen de hoy» del Inicio (título «Cifras de <mes>», «Elegir cifras» y «Ver las ventas» arriba, cifras separadas por líneas), y cada una conserva su ícono y su color.
- **«Inversión en anuncios» se mudó del Tablero comercial a Costos** (al final del módulo, con «Campañas por gasto»).
- **«Embudo por etapa» se mudó del Tablero comercial a Oportunidades → Embudos** (al final; no aparece en «Ganadas»).
- El botón «Ir a Ajustes» de «No hay cuenta publicitaria conectada» ya se ve con su texto.
- `tests/fixes-296.py` lo comprueba (26).

**Novedades 2.9.9:**
- **Los gráficos de ventas van al final de Gestión Comercial** (pedido del usuario), con reporte y sin él. Antes, sin reporte, salían debajo de las tarjetas.
- `tests/graficos-gestion.py` lo comprueba (10).

**Novedades 2.9.8 · Conversaciones sin «scroll infinito» en el ordenador:**
- Con muchas conversaciones, la página se alargaba miles de píxeles en blanco debajo del chat: el texto oculto de «sin leer» de cada conversación se salía de la lista. Ahora la lista lo contiene y la página mide lo que debe.
- Lo mismo se aplicó a las listas de Contactos y a las columnas de Oportunidades, por si acaso.

**Novedades 2.9.7 · Gráficos de ventas en Gestión Comercial:**
- **«Ventas del mes» y «Tendencia del año» también en Gestión Comercial**, los mismos del Tablero comercial (mismos datos y misma regla de quién los ve: quien ve las cifras del equipo).
- **«Editar gráficos»:** cada persona elige cuáles ver; la administración puede dejar su elección como vista del equipo. Primer paso hacia los widgets editables.
- Con reporte mensual, por defecto sale solo la tendencia del año (la cabecera del reporte ya trae la gráfica del mes); sin reporte, salen los dos, justo debajo de las tarjetas.
- Los gráficos siguen el mes elegido arriba (Oct · Nov · Dic).
- Prueba nueva: `tests/graficos-gestion.py` (9).

**Novedades 2.9.6 · Arreglos de la revisión completa (18 nov):**
- **Importaciones más seguras:** con la subcuenta conectada, Tareas (como Contactos y Oportunidades) ya no ofrece «Descargar plantilla» ni «Cargar desde Excel». Antes, «Cargar» abría Migración con **Productos y servicios** elegido y el archivo de tareas se podía cargar como productos. Migración nunca vuelve a abrir la carga de un módulo distinto al pedido.
- **Ventas desde el 1 de enero de 2026** (decisión del usuario): al crear, editar e importar, la fecha de inicio no puede ser anterior. El mensaje lo dice en palabras. (Antes, una venta importada de hace más de 400 días no se podía editar nunca.)
- **Mensualidades sin fin siempre con cobros por delante:** una importada trae todos sus cobros desde el inicio (los pasados, pagados) más 12 por delante; y cualquier mensualidad sin fin suma 12 más cuando le quedan menos de 3. Antes, una importada antigua quedaba «al día» sin nada por cobrar.
- **Editar una mensualidad ya no borra cobros pagados:** con más de 12 cobros pagados, editarla los conserva todos.
- **Nuevo convenio:** ahora solo lleva a **contratos.agoradigital.app** (se abre en otra pestaña). El buscador de clientes de antes ignoraba lo escrito.
- **Montos de convenio:** «40.50» se lee como 40,50 (antes 4050). Igual con «40,50», «1.200,50» y «1,200.50».
- **Tablero comercial sin contradicciones** (con la subcuenta conectada, las cifras del mes las calcula el mismo motor que el resto del Tablero, con la configuración del mes de Ágora y la comisión de Ajustes):
  - Rentabilidad usa lo invertido en Meta que se ve arriba (antes decía «la cuenta no ha informado lo invertido»).
  - «Campañas por gasto» muestra las campañas de Meta (antes «Sin movimiento»).
  - El equipo del mes aparece con las personas importadas de la cuenta; quien administra sale solo si cerró algo (como en «Por ejecutivo»).
  - «Ganadas en la cuenta este mes» y «Ventas del mes · según el reporte mensual» dicen de dónde sale cada cifra, y el avance de la meta dice «según lo ganado en la cuenta».
- **«Mis ventas del mes»** ya no muestra «null ganados»: sin enlace con la cuenta dice «Tu usuario no está enlazado con la cuenta».
- **Excel:** «0.750» es 0,75 (antes 750) y «2026» escrito en una fecha ya no se lee como 18/07/1905.
- Lo que quedó para después está en **FIXES-PENDIENTES.txt**.
- Prueba nueva: `tests/fixes-296.py` (24).

**Novedades 2.9.5 · Las mismas herramientas en todas las tablas (Ventas y facturación primero):**
- **Un solo botón «Excel» arriba, con el mismo menú en todas las vistas:** Descargar lo que ves (solo lo filtrado) · Descargar todo · Descargar plantilla · Cargar desde Excel · Ajustar columnas. Cada opción aparece si la vista la admite y si tu rol puede usarla. Ahora también está en Facturación y Catálogo.
- **Sin botones CSV sueltos** («Descargar CSV», «Exportar CSV», «Descargar Excel · N» de Estudiantes): todo sale por «Excel».
- **Todas las tablas traen su barra «Columnas» y «Excel»**, también las que se arman con los datos en vivo (Facturación sin reporte, Gestión Comercial, Equipo, Tendencias, Catálogo sin reporte, campañas) y la de Estudiantes. En esas tablas calculadas no hay «Agregar manual».
- **Sin buscadores repetidos:** si la vista ya tiene sus filtros arriba, la tabla no trae otro buscador.
- **Ventas registradas: filtros Vendedor y Producto** (como Facturación) y «Quitar filtros». La descarga respeta todos los filtros.
- Prueba nueva: `tests/herramientas.py` (29).

**Novedades 2.9.4 · Menú principal (Inicio):**
- **Encabezado en una franja:** «[logo] Ágora Digital App / Hola, nombre», con el logo de la empresa a la derecha si lo tiene. Debajo, en una línea: «Elige un módulo… · 5 módulos · 25 herramientas».
- **«Ir a Dashboard»** en lugar de «Ver el tablero completo» (en el teléfono, «Dashboard»). Lleva al Tablero comercial.
- **Cifras del «Resumen de hoy» iguales en cualquier pantalla:** título con flecha, número grande debajo y una línea de detalle. En el teléfono, dos por fila.
- **Todo se puede tocar:** en cada tarjeta de módulo, las etiquetas de sus herramientas (Ventas, Facturación, Contactos…) llevan directo a esa herramienta; el resto de la tarjeta, al módulo.
- Quedan para después (a pedido): cuatro cifras por fila y la separación con la barra superior.
- Prueba nueva: `tests/menu-principal.py` (14).

**Novedades 2.9.3:**
- **Ícono de la pestaña (favicon) de Ágora Mobile.** El que traía el ERP era un ícono ajeno que venía en los archivos originales; ahora es el mismo de Ágora Mobile, también en la raíz del sitio (`/favicon.ico`).

**Novedades 2.9.2:**
- **Estudiantes solo en subcuentas de academia o educación.** Una venta es un estudiante únicamente si el **rubro de la subcuenta** es de educación (Educación, academia, instituto, colegio, universidad, formación…). En las demás subcuentas, Catálogo queda como antes, sin Estudiantes ni estados, y el servidor rechaza cambiar el estado de estudiante.
  - El rubro se elige en Agencia → la ficha de la subcuenta. **ASOCIM debe tener el rubro «Educación»** para ver Estudiantes.
- Prueba `tests/estudiantes.py` ampliada (16).

**Novedades 2.9.1:**
- **Mismo esqueleto que Ventas registradas** en Facturación, Productos y servicios y Catálogo (solo cambia cómo se ve; los datos son los mismos):
  - Tarjetas con icono, cifra y detalle; la tabla dentro de su recuadro con título y cantidad; al lado, la lista corta.
  - Facturación: «Registros» con sus filtros y, al lado, «Cobros por recibir» (con reporte) o «Atrasados de meses anteriores» (sin reporte).
  - Productos y servicios: la lista de productos a la izquierda y los rubros al lado, como en Ventas.
- **Catálogo → Estudiantes.** Cada venta registrada (también las cargadas por Excel) es un estudiante en su programa.
  - **Estado a mano:** Activo · En pausa · Retirado · Culminado. Se toca el estado en la fila y se elige. Las ventas empiezan como Activo. No cambia los cobros.
  - **Desglose arriba:** ventas activas y cuánto suman; **recurrencia mensual** solo de los activos (por ejemplo, «10 activos × US$ 50» = US$ 500/mes); en pausa; retirados y culminados.
  - **Filtros siempre visibles** (se quedan arriba al bajar): N.º de cédula y vendedor, más el estado.
  - **«Descargar Excel» baja solo lo filtrado** (el botón dice cuántas filas): si filtras a una vendedora y salen 19, el Excel trae esas 19, con sus totales al final.
  - **Abajo, los totales generales** de todos los estudiantes, sin filtros. Al lado, «Por programa»: activos y su recurrencia.
- **Descargas con filtros:** «Exportar CSV» de Ventas registradas baja solo lo filtrado. El botón «Excel» del encabezado ofrece «Descargar lo que ves» (lo filtrado) y «Descargar todo».
- Prueba nueva: `tests/estudiantes.py` (14).

**Novedades 2.9.0:**
- **«Venta ganada» escribe en la cuenta y en Ágora a la vez.** Con la subcuenta conectada, al registrar la venta de una oportunidad: la oportunidad queda **ganada en la cuenta** con su monto y la venta se guarda en Ágora **enlazada por el identificador de la oportunidad y del contacto de la cuenta**.
  - **Sin duplicados:** una oportunidad tiene una sola venta. Si se vuelve a pulsar «Venta ganada», se ofrece editar la que ya existe.
  - Si la cuenta no acepta el cambio, no se guarda nada en Ágora (no quedan medias ventas).
  - Al editar el monto de la venta, también se corrige en la oportunidad de la cuenta. El correo o teléfono del cliente se completa en la cuenta solo si allí estaba vacío.
  - Las tareas de seguimiento de la venta se crean en la cuenta, igual que las del módulo Tareas.
  - Un ejecutivo solo puede registrar la venta de oportunidades asignadas a él.
- **Copia ordenada (espejo) de contactos, oportunidades y ganadas.** Cada vez que Ágora lee la cuenta, guarda esos datos en dos tablas propias y **sobreescribe** lo anterior (una fila por contacto y por oportunidad, nunca repetidas). Las conversaciones no se copian. Los módulos se quedan como estaban.
- **«Ganadas sin venta» y la meta del mes** se calculan con las ganadas de la cuenta, no con copias viejas.
- **Ventas importadas por Excel:** si su correo o teléfono coincide con un contacto de la cuenta, quedan enlazadas a ese contacto.
- **Arreglado:** las migraciones nuevas ya se aplican solas al abrir la app (antes se detenía en la 005).
- Prueba nueva: `tests/venta-cuenta.py` (14).

**Novedades 2.8.17:**
- **Facturación: filtros.** Estado del cobro (Todos · Pagados · Pendientes · Vencidos, con su cantidad), búsqueda por cliente, documento o producto, vendedor y producto. Los filtros afectan también a las tarjetas (por ejemplo, lo cobrado de una vendedora). «Quitar filtros» vuelve a todo.
- **Contactos: filtro «Entraron»** (Todos · Entraron hoy · 7 días · 30 días), por fecha de alta en la cuenta y en la hora del dispositivo, del más nuevo al más viejo. Quien solo ve lo suyo ve solo los suyos. Se recuerda mientras dure la sesión.
- **Resumen de hoy:** «Leads de hoy» ahora abre Contactos con «Entraron hoy» puesto.

**Novedades 2.8.16:**
- **Resumen de hoy (Inicio): cada cifra lleva a su detalle.** Sin leer → Bandeja con «No leídas»; Esperando respuesta y Fuera de tiempo → Bandeja con «Sin respuesta»; Conversaciones → Bandeja; Oportunidades abiertas → Oportunidades; Ventas ganadas del mes → Oportunidades, pestaña «Ganadas»; Contactos → Contactos; Tareas pendientes → Tareas; Leads de hoy, Conversaciones del día y Tiempo de respuesta → Tablero comercial (abre en «Hoy», con fuentes, asesores y tiempos). Si la persona no tiene permiso para el módulo, la cifra no es un enlace.

**Novedades 2.8.15:**
- **Las ventas cargadas por Excel ya tienen su plan de cobros**, sin editarlas una por una. Decisión: las cuotas con fecha anterior a hoy quedan **pagadas** (las ventas venían «al día»); las que vienen, **pendientes**.
  - Las que ya están: reciben el plan solas la primera vez que se leen las ventas (una sola vez; no se vuelven a tocar).
  - Las que se importen desde ahora: nacen con su plan.
  - Con eso cuentan en Cobrado, Por cobrar, Facturación, Tendencias y Gestión Comercial.
- **Arreglado:** al editar una venta importada, el vendedor escrito («como vino del Excel») se cambiaba sin avisar por la persona que hizo la importación. Ahora se conserva salvo que elijas otro.
- Prueba nueva: `tests/ventas-importadas.py` (9).

**Novedades 2.8.14:**
- **Arreglado: «Editar venta» decía «Falta la oportunidad»** en las ventas cargadas por Excel (Migración de datos). Ahora se editan sin oportunidad:
  - El producto queda **escrito tal como vino** (por ejemplo «CIPRO»), con la opción de enlazarlo a uno del catálogo si existe.
  - El vendedor se conserva **como vino del Excel** si no es un usuario de Ágora; se puede cambiar por uno del equipo.
  - Si no tenía fecha de inicio de cobros, se usa la fecha de la venta.
  - Al guardar se arma su **plan de cobros**, así la venta empieza a contar en Cobrado, Por cobrar y Facturación.

**Novedades 2.8.13:**
- **Tablero comercial: sección «Ventas del mes y del año»**, con las dos gráficas del reporte de Gestión Comercial:
  - **Ventas del mes:** lo vendido en el mes, la diferencia con el mismo mes del año anterior, cuánto falta para la meta (de Configuración mensual) y la línea de los últimos 6 meses con el año anterior punteado y la meta.
  - **Tendencia del año:** el año actual contra el anterior mes a mes, meses por encima del año anterior, mejor mes, mes más bajo y promedio mensual.
- Cada mes sale del **reporte mensual** si lo trae; si no, de **lo vendido** en las ventas registradas. Al pie se indica qué meses salen de cada fuente.
- Solo la ve quien ve cifras del equipo (administración, supervisión y dueños). Se puede ocultar o mover en Personalizar → Secciones del Tablero comercial.

**Novedades 2.8.12:** ya no queda ningún módulo con «Esta subcuenta todavía no tiene reportes».
- **Tendencias sin reporte:** las ventas registradas de los últimos 12 meses: gráfico de lo vendido por mes y tabla mes a mes (ventas, recurrentes, vendido, a cobrar y cobrado). Tarjetas a elección: vendido y ventas frente al mes anterior, promedio de 12 meses, mejor mes y cobrado.
- **Calidad de datos sin reporte:** 9 revisiones sobre las ventas registradas y el catálogo (ganadas sin venta completada, cobros vencidos hace más de 30 días, ventas sin plan de cobros, montos en cero, ventas sin vendedor, un documento con nombres distintos, clientes sin documento o sin correo ni teléfono, productos sin precio), con cuántos casos y ejemplos. Primero lo que hay que corregir.
- **Catálogo sin reporte:** cada producto o servicio con sus ventas, las que siguen activas y lo recurrente al mes.
- **Configuración mensual sin reporte:** meta de ventas del mes, inversión en anuncios fuera de la cuenta (Google, otras redes o Meta mientras no esté conectada) y costos fijos. Se guarda en la misma configuración que lee el Tablero comercial.
- **Costos** suma la inversión cargada a mano a la de Meta («Inversión en anuncios») y tiene tarjetas nuevas: costos fijos, costo total y costo total por venta. **Gestión Comercial** y Ventas tienen la tarjeta «Meta del mes».
- Estos cuatro módulos aparecen en el menú sin reporte. Con reporte, todo sigue mostrando el reporte.

**Novedades 2.8.11:**
- **Facturación sin reporte mensual:** sale del plan de cobros de las ventas registradas (también de las de meses anteriores, como las recurrentes). Muestra los **cobros del mes** (fecha, cliente, producto, cuota, monto y estado: pagado, pendiente o vencido) y los **atrasados de meses anteriores**, con «Marcar pagado» para quien puede editar. Tarjetas a elección: a cobrar en el mes, cobrado, pendiente, vencido, avance de cobro, clientes con deuda y próximo cobro.
- Facturación aparece en el menú aunque no haya reporte. Con un reporte cargado, sigue mostrando el reporte (y el bloque de cobros por recibir de siempre).

**Novedades 2.8.10:**
- Se quitó el aviso «Esta subcuenta no tiene reporte mensual cargado…» de Gestión Comercial, Equipo y Costos.

**Novedades 2.8.9:**
- **Equipo sin reporte mensual:** cada persona con sus ventas registradas (ventas, vendido, recurrente al mes) y lo que dice la cuenta (oportunidades ganadas del mes, leads de hoy y conversaciones sin leer ahora). Tarjetas a elección: personas con actividad, quien más vendió, ventas registradas, ventas por persona, ganadas en la cuenta, ventas por completar, sin leer ahora, tiempo de respuesta y leads de hoy.
- **Costos sin reporte mensual:** la inversión en Meta del mes frente a lo vendido: costo por venta, costo por oportunidad ganada, costo por resultado, retorno de la inversión, clics y CTR, más el detalle de las campañas. Otros costos (sueldos, comisiones, herramientas) siguen necesitando el reporte.
- **Gestión Comercial, Equipo y Costos ya aparecen en el menú aunque no haya reporte** (para quien ve cifras del equipo). Con un reporte cargado, los tres siguen mostrando el reporte.

**Novedades 2.8.8:**
- **Gestión Comercial ya muestra datos sin reporte mensual.** Antes solo leía el reporte que se importa en Ajustes; sin él decía «Esta subcuenta todavía no tiene reportes», aunque hubiera ventas registradas. Ahora, si no hay reporte, arma el mes con las **ventas registradas**: tarjetas a elección (las mismas 14 de Ventas, con su propia elección), **por vendedor** (ventas, vendido, recurrente al mes y ticket promedio), **por forma de cobro** y **por producto o servicio**. Avisa si hay oportunidades ganadas sin su venta completada.
- Con un reporte cargado, Gestión Comercial sigue mostrando el reporte.

**Novedades 2.8.7:**
- **Arreglado: «Acceso restringido» para los dueños de Ágora** al abrir un módulo del reporte (por ejemplo, Gestión Comercial) en una subcuenta sin reporte cargado. La 2.8.6 los mostraba en el menú, pero al abrirlos la pantalla volvía a mirar solo el permiso del rol. Ahora todo el sistema usa la misma regla (también el buscador, los atajos y la pestaña Catálogo).
- **«Resumen de hoy» del Inicio: «Elegir cifras».** Cada persona elige qué cifras ver y en qué orden, entre 11: sin leer, esperando respuesta, oportunidades abiertas, conversaciones, contactos, leads de hoy, conversaciones del día, tiempo de respuesta, fuera de tiempo, tareas pendientes y ventas ganadas del mes. La administración puede fijar la vista del equipo. Solo se le pide a la cuenta lo que se va a mostrar.
- En escritorio, el resumen ordena las cifras de tres en tres, con líneas parejas.

**Novedades 2.8.6:**
- **Los dueños de Ágora (Master y Superadmin) ven todos los módulos** de cualquier subcuenta, sin importar permisos, si hay reporte mensual cargado o si la subcuenta apagó módulos en Personalizar. El resto de las personas sigue viendo solo lo suyo.
- **Estudio vuelve como «Próximamente»:** en el menú de agencia, en «Herramientas del equipo» y en Personalizar → Módulos (con el interruptor bloqueado). Si alguien abre su dirección, ve el aviso de «Próximamente».
- Personalizar → Módulos ya mostraba todos los módulos de la subcuenta (26 más Inicio); se suma Estudio.

**Novedades 2.8.5:**
- **«Editar tarjetas» sin tope:** se pueden poner a la vista todas las tarjetas del catálogo (hoy 14), no solo 8.
- **«Registro» vuelve al menú de Contratos.** En la 2.4.0 salió del menú diciendo que estaba dentro de Auditoría, pero Auditoría no tenía ningún enlace a él: solo se llegaba escribiendo la dirección. Además ahora muestra **solo lo que pasa con los convenios** (antes repetía toda la bitácora de Auditoría, con ajustes y «Sin nombre»).
- Revisión contra el ERP recibido (2.1.19): no se borró ningún módulo (eran 26 y ahora son 27, con «Redes y anuncios»). Lo que cambió es cuándo aparecen: el menú oculta lo que el rol no puede usar y los módulos del reporte mensual solo aparecen con un reporte cargado.

**Novedades 2.8.4:**
- **Ventas registradas: «Editar tarjetas»**, como editar los widgets de un tablero. Cada persona elige qué cifras ver arriba (hasta 8) y en qué orden. Su elección se guarda en el servidor y la acompaña en cualquier equipo.
- 14 tarjetas para elegir: Ventas del mes, Vendido, Cobrado en el mes, Por cobrar en el mes, Cobros vencidos, Ticket promedio, Ingreso recurrente al mes, Ventas recurrentes, Pagos y ventas únicas, Ventas pagadas, Clientes, Quien más vendió, Lo más vendido y Ventas por completar.
- La administración puede guardar su elección como **vista del equipo**: la ven quienes no hayan elegido la suya. «Restaurar» (o «Volver a la vista del equipo») borra la elección propia.
- Sin migraciones (se guarda en la tabla de ajustes). Ruta nueva: `workspace/preferencias`. Prueba nueva: `tests/tarjetas.py` (10).

**Novedades 2.8.3:**
- **Campañas de Meta: diagnóstico de la cuenta real.** El detalle técnico de ASOCIM mostró que la cuenta publicitaria está registrada, pero Meta no entrega su autorización («Access Token not found», código 422) y el Ad Manager de la cuenta no tiene página conectada (404). Ahora el bloque lo dice así: **«Hay que volver a conectar la cuenta publicitaria de Meta»**, con los pasos.
- **Publicaciones:** si la cuenta responde sin publicaciones, Ágora hace un último intento nombrando todas las redes conectadas y mirando un año atrás. Si sigue en cero, lo dice claro: la cuenta no entrega publicaciones para leer desde fuera, aunque se vean en su planificador; las cifras sí las incluyen.
- **Pantalla (teléfono):** en «Eficacia de ventas» los filtros Embudo y Ejecutivo ocupan todo el ancho, uno al lado del otro, sin sangría; en tablas vacías (por ejemplo, Productos) el icono ya no queda encima del texto ni hay una línea que cruce el botón «Crear producto»; la pestaña de Personalizar dice **«Indicadores»** (sin «(KPI)»).
- Sin migraciones ni cambios en `.htaccess`.

**Novedades 2.8.2:**
- **Publicaciones vacías en la cuenta real (arreglo probable):** la lista se pedía con la versión vieja de lectura, que no trae las publicaciones hechas en Meta Business Suite. Ahora se pide con la versión nueva («v3») y, si esa falla, con la vieja; si con «Publicadas» no viene nada, se prueba con «Todas» y se filtra aquí. La respuesta se lee sin suponer dónde viene la lista. Se guarda 5 minutos.
- **Campañas de Meta: el aviso dice la causa real.** Si la cuenta no entrega la lista, Ágora pregunta además si hay cuenta publicitaria conectada y si hay totales del mes. Avisos posibles: «no hay cuenta publicitaria conectada en el administrador de anuncios» (tener Facebook e Instagram conectados para publicar no basta), «está conectada pero no entregó la lista», o solo los totales del mes si es lo único que da. Un aviso de error se vuelve a comprobar a los 2 minutos (antes, 20).
- **Detalle técnico solo para dueños de Ágora:** cuando campañas o publicaciones salen vacías, la dueña ve debajo cómo respondió la cuenta en cada intento (código y motivo, sin contenido). Sirve para saber qué falta sin acceso al servidor.
- Sin migraciones ni cambios en `.htaccess`.

**Novedades 2.8.1:**
- **Vuelven las publicaciones en «Redes y anuncios»**, solo para ver: publicadas (abre aquí), programadas, con error y borradores; de los últimos 90 días y los próximos 60; agrupadas por red y con filtro «Red». **Incluyen las hechas en Meta Business Suite** («Hecha en Meta»), que la cuenta sincroniza cuando la red tiene activada la sincronización de publicaciones. (En 2.7.3 se habían quitado por un error de interpretación: se creía que la cuenta solo informaba lo publicado desde su planificador.)
- El `.htaccess` vuelve a permitir imágenes por https (las miniaturas de las publicaciones). **Cambia el `.htaccess`.**
- Necesita además el permiso `socialplanner/post.readonly`.

**Novedades 2.8.0:**
- **Campañas de Meta** (solo lectura), con los datos de la cuenta publicitaria conectada en el administrador de anuncios de la cuenta, también para campañas creadas en el Administrador de anuncios de Meta. Por mes (los últimos 6): inversión, resultados, costo por resultado, impresiones, alcance, clics y CTR, en total y por campaña, con su estado (activa, en pausa…).
- Está en **dos módulos**: **«Redes y anuncios»** (antes «Redes sociales»), arriba de las cifras de las redes, y **«Embudo y publicidad»**, arriba de lo que viene del reporte mensual. Embudo y publicidad ahora aparece también en subcuentas sin reporte, solo con las campañas.
- Lo ven Administración y Supervisión (son cifras de la empresa). Un ejecutivo no ve la inversión.
- Necesita el permiso `adPublishing.readonly` en la integración privada y la cuenta publicitaria de Meta conectada en la cuenta. Si falta algo, el bloque lo dice en palabras; leer campañas nunca pausa la bandeja.
- Sin migraciones. Prueba nueva: `tests/campanas.py` (9).

**Novedades 2.7.3:**
- **Redes sociales queda solo con las cifras:** redes conectadas y cifras de los últimos 7 días frente a los 7 anteriores, en total y por red. Se quitó la lista de publicaciones (la cuenta solo informa lo publicado desde su planificador, y en Ágora se publica desde Meta Business Suite).
- El `.htaccess` vuelve a permitir solo imágenes propias (ya no hacen falta las externas).

**Novedades 2.7.2:**
- **Redes sociales: las publicaciones se ven y se dividen por red.** La pantalla abre en «Publicadas» (antes abría en «Programadas», que suele estar vacía) y mira 90 días atrás (antes 30). Con «Todas las redes» se agrupan por red con su cantidad; el filtro «Red» deja ver solo una.
- La pantalla explica que la cuenta solo informa lo que pasa por su planificador: lo que se sube directamente en Instagram o Facebook no aparece.
- Las imágenes de las publicaciones ya pueden mostrarse (la regla de seguridad del sitio solo permitía imágenes propias); si una no carga, se oculta en vez de verse rota. **Cambia el `.htaccess`.**

**Novedades 2.7.1:**
- **Redes sociales queda solo para ver métricas.** Se quitaron «Nueva publicación» y «Borrar»: en Ágora no se publica ni se borra (el servidor tampoco lo acepta). Quedan las redes conectadas, las cifras de los últimos 7 días frente a los 7 anteriores (ahora también separadas por red) y la lista de publicaciones por estado.
- **Arreglado: «La cuenta no dio las cifras de las redes».** Se le pedían a la cuenta con el identificador equivocado de cada red; ahora va el que pide la documentación. Solo se piden las redes que la cuenta puede medir.
- **Arreglado: «La cuenta pidió esperar un momento» al abrir Redes.** Un rechazo de la cuenta en una lectura de redes pausaba toda la subcuenta unos segundos (también la bandeja). Ahora las lecturas de redes no pausan nada.
- Las redes se guardan 5 minutos y las cifras 15, para no gastar cuota.
- Para las cifras basta el permiso de lectura de Social Planner; el de escritura (`socialplanner/post.write`) ya no hace falta.

**Novedades 2.7.0:**
- **Módulo nuevo: Redes sociales** (Gestión y resultados), con el Social Planner de la cuenta conectada:
  - Redes conectadas (Facebook, Instagram, LinkedIn, Google Business, TikTok…), con aviso cuando una conexión venció.
  - Cifras de los últimos días: publicaciones, impresiones, alcance, me gusta, comentarios y seguidores, con el cambio frente al periodo anterior.
  - Publicaciones de los últimos 30 días a los próximos 60, por estado: programadas, publicadas, con error (con el motivo), borradores.
  - Nueva publicación: texto, imagen por enlace (opcional), redes elegidas, y fecha y hora o borrador. Borrar una programada o un borrador.
  - Lo ven Administración y Supervisión; publica quien está enlazado con la cuenta. Queda en la Auditoría.
- Permisos nuevos que conviene agregar a la integración privada: `socialplanner/account.readonly`, `socialplanner/post.readonly`, `socialplanner/post.write`. Si faltan, el módulo lo dice en palabras y la conexión sigue funcionando para lo demás.
- Sin migraciones. Prueba nueva: `tests/redes.py` (15).

**Novedades 2.6.2:**
- **«ARP Comercial» pasa a llamarse «Tablero comercial»** en el menú, Inicio, Athena, Personalizar, Indicadores y la guía de uso. La dirección sigue siendo `/comercial`.
- **El equipo de cada cuenta conectada se vuelve a leer solo, una vez al día**, con la misma tarea programada de siempre (`servidor/bin/worker.php`, cada minuto). Hasta tres subcuentas por vuelta para cuidar la cuota. El botón «Actualizar equipo» sigue para hacerlo al momento.
- **Tablas en el teléfono:** las de más de tres columnas (Ventas, Equipo, Facturación, Costos, Usuarios, Auditoría…) se leen como fichas, una por fila, con el nombre de cada dato. Ya no hay que deslizar a los lados. En la computadora no cambian.
- Sin migraciones.

**Novedades 2.6.1:**
- **El tablero (ARP Comercial) ya trae datos en «Operación del día», «Sin leer por asesor», «Fuente de los leads», «Leads por ejecutivo», «Eficacia» y «Anuncios».** Esas tarjetas venían en el código recibido apuntando a rutas vacías; ahora las calcula el motor con la cuenta conectada (el cálculo escrito para la línea 1.11 de Ágora Mobile). «Frente a ayer» aparece desde el segundo día. La inversión en anuncios no se muestra a quien solo ve lo suyo.
- Cuando de verdad no hubo nada («Hoy todavía no entró ningún lead», «Nadie tiene conversaciones sin leer ahora») se dice en una frase, sin el cuadro de «Sin datos todavía».
- **Inicio:** la tarjeta de acceso al tablero se reemplaza por un «Resumen de hoy» sobrio con tres cifras reales (sin leer, esperando respuesta, oportunidades abiertas) y el enlace «Ver el tablero completo». Para un ejecutivo dice «Lo tuyo hoy» y cuenta solo lo suyo.
- Sin migraciones del ERP; el motor crea solo su tabla `daily_metrics` la primera vez.

**Novedades 2.6.0:**
- **«Nuevo contacto» escribe en la cuenta** (Contactos, con la subcuenta conectada): nombre, apellido, teléfono y correo. Hace falta el teléfono o el correo. Al crearlo se abre su ficha. El que crea un ejecutivo queda asignado a él; los demás pueden marcar «Asignármelo a mí». Si la cuenta ya tiene un contacto con ese teléfono o correo, no lo duplica y lo dice. Queda en la Auditoría.
- **Un solo tablero de cifras: ARP Comercial.** Inicio ya no repite las tarjetas de conversaciones, oportunidades, ganadas y tareas: muestra un acceso a ARP Comercial y los módulos. «Indicadores clave» solo aparece en Inicio cuando hay al menos una meta configurada. Personalizar ya no ofrece «Pantalla de inicio».
- Sin migraciones. Prueba nueva: `tests/contactos.py` (10).

**Novedades 2.5.0:**
- **Bandeja, Contactos y Embudos al día con Ágora Mobile 1.10.14**, conservando lo propio de esta app (recordatorios y tareas).
- **Embudos nuevos:** filtros por etapa y por responsable, buscador por nombre, teléfono o correo, etapas plegables, tarjetas compactas y tres atajos en cada oportunidad: abrir la conversación, abrir la ficha y «Mover a…» otra etapa en un toque. En el teléfono las etapas van una debajo de otra.
- **Bandeja:** se busca también por teléfono y correo (un teléfono se entiende aunque lleve espacios, guiones o paréntesis); cada conversación muestra el canal con su color y, a quien ve toda la subcuenta, el responsable o «Sin asignar»; el historial ya no salta cuando cambia el alto del cuadro de respuesta.
- **Contactos:** botón «Ver más» para seguir la lista, textos «asignados a ti», y la ficha se abre directa desde una oportunidad.
- Sin migraciones. `agora-modulos.js` lleva ahora las funciones de Contactos y Embudos de Ágora Mobile 1.10.14 tal cual, y los cambios de la Bandeja aplicados a mano.

**Novedades 2.4.0:**
- **Cada persona ve solo sus módulos.** El menú, el inicio y el buscador muestran únicamente lo que su rol y sus permisos individuales permiten (un ejecutivo pasa de 24 entradas a 9). El servidor le envía a la pantalla los mismos permisos que aplica. Los módulos del reporte mensual (Gestión Comercial, Equipo, Facturación, Costos, Tendencias, Calidad, Configuración mensual) solo aparecen si la subcuenta tiene un reporte cargado.
- **Enlace de cada persona con su usuario de la cuenta** (Usuarios y roles): columna «Usuario en la cuenta», opción «Enlazar con la cuenta», botón «Actualizar equipo» y, al agregar una persona, «Traer del equipo de la cuenta». Sin enlace elegido se sigue deduciendo por correo. Un ejecutivo sin enlace ve un aviso que le explica por qué no ve nada.
- **Las tareas se escriben en la cuenta.** En una subcuenta conectada, cada tarea creada, corregida o completada en Ágora queda en la ficha del contacto en la cuenta; y las creadas desde la ficha aparecen en Tareas. Una sola tarea en los dos sitios. Por eso «Nueva tarea» pide el contacto.
- **Sin copias locales de lo que vive en la cuenta.** Con la subcuenta conectada desaparecen «Excel» y «Agregar manual» de Contactos, Oportunidades y Tareas, esos módulos salen de Migración de datos y Personalizar ya no ofrece embudos propios. El servidor rechaza esas cargas.
- **Menos duplicados:** un solo botón para crear en Tareas y en Productos; Ajustes queda con el reporte mensual y el estado de la subcuenta; «Registro» de convenios sale del menú (está en Auditoría); «Estudio» sale del menú de agencia; el atajo «Auditoría» sale de la barra superior; «Con convenio» ya no se titula «Contactos».
- **Textos:** Ajustes y Ayuda sin textos de la demo ni nombres de proveedor; Auditoría y Registro muestran las acciones en palabras; el pie de página muestra el rol legible.
- **Migración nueva y automática:** `005_enlaces.sql` (tabla `agora_user_links`). Se aplica sola al abrir la app.
- Pruebas nuevas: `tests/permisos.py` (44), `tests/enlaces.py` (13), `tests/tareas.py` (15).

**Novedades 2.3.3:**
- **Bandeja: el botón de la ficha del contacto ya abre la ficha** (antes no hacía nada). En el teléfono reemplaza al chat y se cierra con la ×; en escritorio sale al lado.
- Ficha y editor de contacto ordenados: oportunidad en filas, recuadros por grupo, interruptor «No contactar», campo modificado resaltado. El buscador ya no muestra dos bordes. El modo plantilla oculta la caja de texto libre.
- Comprobación nueva `tests/ui-estados.py`: falla si un estado del programa de la bandeja no tiene estilo, o si la versión no coincide entre `index.html`, `version.json` y `sw.js`. Sin migraciones.

**Novedades 2.3.2:**
- **«Ir a subcuenta» en una subcuenta recién creada** ya no dice «La subcuenta no está autorizada»: la lista de subcuentas se vuelve a pedir al servidor en vez de usar la del inicio de sesión. Sin migraciones.

**Novedades 2.3.1:**
- **Conexión de la agencia** en Agencia → Subcuentas (arriba): Company ID y **token de agencia** con «Guardar y probar». Es el token que lee la billetera para **Consumos**; antes solo estaba dentro de Consumos. Si falla, dice por qué en palabras (token rechazado, sin permiso o Company ID equivocado). Guardar, probar y quitar quedan en el Registro de actividad.
- **Ventana «Nueva subcuenta / Datos de la empresa» reordenada**: la conexión va justo después de los datos de la empresa, con Location ID y token lado a lado. El Company ID ya no se pide por subcuenta (es de la agencia).
- **Los estilos ya no se quedan viejos**: la app pide cada hoja de estilo y programa con su versión (`?v=2.3.1`) y el trabajador sin conexión busca primero en la red. Esto arregla pantallas «sin estilo» (botones grises, letra del sistema) tras actualizar sobre una versión anterior.
- Sin migraciones nuevas. Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php` y `servidor/storage/`.

**Novedades 2.3.0:**
- **Bandeja, contactos, oportunidades, ganadas y tablero en vivo** para las subcuentas conectadas: los atiende el mismo motor que Ágora Mobile (`servidor/motor/`), con su cuota (20 consultas cada 10 s por subcuenta), sus reglas de asignación (cada persona ve lo suyo según su correo en la cuenta) y su protección contra envíos repetidos. Las subcuentas sin conexión siguen como antes, con datos importados.
- **Consumos** (Agencia → Consumos, solo dueños): token de agencia y Company ID cifrados; gasto y recargas por subcuenta y mes, en texto o PDF.
- **Cuentas de dueño solo por terminal**: `servidor/bin/crear-dueno.php` crea la Master y los Superadmin. La web ya no crea ni asciende dueños, e `install.php` ya no instala (solo explica los pasos).
- `config.php` lleva `vault_key` (clave del cifrado de tokens). Si se escribe mal, la app avisa en vez de usar otra clave.
- Cabecera del chat en el teléfono: el nombre ya no se monta con «¿Venta ganada?».
- Tablas nuevas del motor en la misma base: se crean solas al conectar la primera subcuenta.
- Guía de instalación reescrita paso a paso (`INSTALAR-CYBERPANEL.md`).

**Novedades 2.2.0 (Agora New App):**
- **Conexión con la cuenta en la ficha de cada subcuenta** (Agencia → Editar datos → «Conexión con la cuenta»): Location ID, Company ID (opcional) y token de la integración privada. El token se guarda **cifrado** (necesita la extensión PHP `sodium`, incluida en PHP 8.3) y nunca vuelve a mostrarse: solo sus 4 últimos caracteres. «Probar conexión» comprueba el token y dice qué datos puede leer (contactos, conversaciones, oportunidades, equipo, calendarios, facturas, productos, Social Planner, anuncios). El envío de mensajes usa esa conexión; `ghl.tenants` de `config.php` sigue valiendo como respaldo.
- **Migración `004_ghl_conexion.sql`, automática**: se aplica sola la primera vez que se abre la app tras subir los archivos (también puedes ejecutar `servidor/bin/migrate.php`).
- La clave del cifrado se deriva de `app_key`. Si quieres separarla, añade a `config.php` `'vault_key'=>'…64 hexadecimales…'` **antes** de guardar tokens. Cambiar `app_key` o `vault_key` obliga a volver a escribir los tokens.
- Una sola tipografía, **Author** (se quitó Inter). Contactos en el teléfono ya no se pisan. Ajustes ya no se queda cargando. Gestión Comercial abre para administradores. Los logos grandes (hasta 8000 px) se reducen en vez de rechazarse.
- **Producción sin modo pruebas**: el paquete `agora-new-app-2.2.0.zip` no trae datos sintéticos, demos ni el acceso admin / 123456789. Si falta `config.php`, la app avisa en vez de abrir una base de pruebas. El paquete de demostración sigue aparte: `agora-cyberpanel-2.1.19-demo.zip`.
- Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php`. Reinicia OpenLiteSpeed si cambió el `.htaccess` (no cambió en esta versión). No se usa Docker.

**Novedades 2.1.19:** columnas editables en todas las tablas (nombre, orden, ocultar, agregar y quitar columnas), botón «Agregar manual» con formulario armado con esas columnas, y Excel de cada tabla (descarga, plantilla y carga masiva) que sigue las columnas editadas. Se corrigieron los menús «Columnas» y «Excel» que no abrían. **No hay migraciones nuevas**: todo vive en `agora_settings` y `agora_records`. Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php` y `servidor/storage/`. No se usa Docker.

**Novedades 2.1.18:** se quitó de todas las subcuentas la franja «Cohorte académica · Año de corte · Responsable de gestión» del encabezado del reporte, junto con sus campos en Configuración mensual. Era una personalización de un cliente que ya no se usa. **No hay migraciones nuevas** y no hace falta tocar la base de datos: si una subcuenta tenía esos datos guardados, simplemente ya no se muestran.

**Novedades 2.1.17:** Personalizar con embudos y parámetros de KPI, formulario de venta ganada integrado, Excel y Migración de datos en cada módulo, tablas dinámicas, Catálogo dentro de Productos y servicios y edición de todos los usuarios por el superadmin. **Tampoco hay migraciones nuevas** (todo en `agora_settings` y `agora_records`). Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php` y `servidor/storage/`. El `.htaccess` trae las rutas nuevas (`/migracion`, `/productos`, `/personalizar`): súbelo también. No se usa Docker.

**Novedades 2.1.16:** tipo de cuenta por subcuenta, módulo Productos y servicios (rubros y productos con cobro recurrente, pago único o venta única) y módulo Personalizar (tablero del Inicio, ARP Comercial, CRM y módulos visibles). **No hay migraciones nuevas**: todo se guarda en `agora_settings`. Basta con reemplazar `public_html/` y `servidor/` (conservando `servidor/config/config.php` y `servidor/storage/`). En modo pruebas, si quieres ver el catálogo de ejemplo, borra `servidor/storage/agora-pruebas.sqlite` para que se vuelva a crear.

**Novedades 2.1.15:** módulo ARP con el mismo diseño de la agencia, paleta de colores por cuenta y logos solo en PNG. No hay migraciones nuevas: basta con reemplazar `public_html/` y `servidor/` (conservando `servidor/config/config.php` y `servidor/storage/`). La extensión `gd` (paquete `lsphp83-gd`) es necesaria para reducir logos grandes.

