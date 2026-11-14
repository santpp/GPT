# Agora New App · novedades por versión

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

