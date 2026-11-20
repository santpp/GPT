# Agora New App · novedades por versión

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

