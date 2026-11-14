# Agora New App · novedades por versión

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

