# Contexto para Claude · Proyecto Ágora

Léelo entero antes de tocar nada. Es el resumen de cómo trabajamos y de lo que ya está hecho. Si algo aquí choca con lo que pide el usuario en la conversación, manda el usuario.

## Quién y cómo

- Proyecto **Ágora** (Ágora Digital): espacio de trabajo comercial en español sobre subcuentas de GoHighLevel (GHL). Lo usan agencias y sus clientes (ej.: ASOCIM, educación).
- El usuario escribe en **español**. Responde en español, claro, sin jerga técnica innecesaria.
- **Al final de cada respuesta, un roadmap corto y actualizado** (hecho, de tu lado, siguiente, en pausa, pendiente de decidir, en espera, futuro lejano).
- Decide tú lo que sea de criterio técnico o de diseño y explícalo; pregunta solo lo que de verdad es del usuario (producto, prioridades, nombres, gastos).
- Prefiere hacer y entregar (ZIP listo) a describir. Muestra evidencia: pruebas que pasan, capturas revisadas.
- Si algo no se pudo probar (por ejemplo, contra la cuenta real), dilo sin rodeos.

## Reglas que nunca se rompen

- **El navegador nunca habla directo con GHL.** Todo pasa por nuestro servidor.
- **En la pantalla del cliente nunca aparece** «GoHighLevel», «GHL», «HighLevel», «LeadConnector», ni endpoints ni nombres de permisos técnicos. Se dice «la cuenta» o «la cuenta de la subcuenta». (En guías de instalación para la agencia sí se pueden nombrar permisos.)
- **Nada de secretos en Git**: ni tokens, ni `config.php`, ni `config/local.php`, ni claves, ni `servidor/storage/`. Revisa el diff antes de cada commit.
- Los tokens se escriben en la pantalla, se guardan **cifrados** y nunca se vuelven a mostrar completos (solo los 4 últimos caracteres).
- **No tocar el servidor de producción** (VPS de nueva.agoradigital.app).
- **No mostrar a nadie los códigos de 6 dígitos** de inicio de sesión de otras personas (el usuario lo pidió una vez y se rechazó).
- **No usar `pkill -f`** (está bloqueado). Para parar procesos: `pgrep -f "…"` y `kill <pid>`.
- **Sin Docker.** Base de datos: MySQL/MariaDB.
- Migraciones: **idempotentes y automáticas** (se aplican solas al abrir la app).
- Verifica la API de GHL contra la copia local de la documentación: `/home/user/gohighlevel/highlevel-api-docs` (`apps/*.json`, `apps/v3/*.json`, `docs/oauth/Scopes.md`). Desde la sesión no hay acceso a la web de GHL ni a la cuenta real.

## Git y entregas

- Rama de trabajo: `ccr-78b82221-4a0crl`. Al terminar: `git push -u origin ccr-78b82221-4a0crl` y también `git push origin ccr-78b82221-4a0crl:main` (el usuario lo pidió así).
- Los commits terminan con las líneas de atribución que indique el sistema (Co-Authored-By y Claude-Session). No pongas el nombre del modelo en el código ni en los documentos.
- Cada versión de Agora New App se entrega como **ZIP + `.sha256`** generado con `Agora New App/tools/package.py` (revisa secretos, exige archivos clave, excluye config, storage, pruebas y demos). Borra del repo el ZIP de la versión anterior (`git rm`). Envía el ZIP al usuario con SendUserFile.
- El manual en PDF se entrega aparte.

## Estructura del repositorio

- **Raíz** (`src/`, `public/`, `database/`, `tests/`, `deploy/`): **Ágora Mobile** (Ágora 1.x), en producción en nueva.agoradigital.app. Última: **1.10.14**. Paquete en `Agora Mobile/agora-update-1.10.14.zip` (+ `.sha256`, `manual-vendedores.pdf`). PHP 8.3 sin framework, módulos ES en el navegador, `public/router.php`, `src/live-inbox.php`, pasarela `GhlGateway` (20 consultas cada 10 s, 50 000 al día), `TokenVault` (sodium, XChaCha20). Diseño: `docs/DESIGN.md`, `tests/design-tokens.py`.
- **`Agora New App/`**: el **ERP** (no llamarlo «armazón»). Hoy en pruebas en **tests.agoradigital.app** (CyberPanel + OpenLiteSpeed). Versión actual: **2.8.14**.
- `docs/roadmap.md`: historial de versiones de ambos.

## Agora New App (ERP) · cómo está hecho

- `public_html/` (pantallas) y `servidor/` (PHP 8.3, `Agora\Controllers|Core|Security|Services`). Entrada de la API: `public_html/api/index.php` → `Controllers/Api.php`.
- Tablas `agora_*`. Migraciones en `servidor/app/database/migrations/` (MySQL) y `database/sqlite/` (modo pruebas). Última: `005_enlaces.sql` (`agora_user_links`).
- Roles: MASTER y SUPERADMIN (dueños de Ágora, **solo se crean por terminal** con `servidor/bin/crear-dueno.php`), ADMIN, SUPERVISOR, EJECUTIVO, CONSULTA (y otros heredados). Permisos en `Security/Auth.php::POLICY` (los dueños MASTER/SUPERADMIN ven **todos** los módulos en pantalla, sin filtro de permisos, reporte ni módulos apagados: `moduleAllowed` en `agora-app.js`; **nunca llames a `MODS[x].ok()` directo**, usa `moduleAllowed`); `Auth::effective()` los manda a la pantalla (`workspace/branding` → `permisos`), y el menú muestra solo lo permitido.
- **Puente con el motor de Ágora Mobile**: `servidor/motor/` es una copia de `src/` y `database/` de Mobile 1.10.14 con cambios marcados «Agora New App» (ver `servidor/motor/LEEME.md`). `Core/MotorMobile.php` envía al motor las rutas en vivo (bandeja, contactos, oportunidades, tablero, día, eficacia, anuncios) cuando la subcuenta está conectada. Tenant único del motor = 1 (la agencia); id de conexión del motor = id de la subcuenta del ERP. Clave del motor: `servidor/storage/motor-vault.key`.
- Conexión de cada subcuenta: Location ID + token (cifrado) en la ficha de la subcuenta (`Services/GhlConnection.php`). Conexión de la agencia (Company ID + token de agencia) para Consumos, en Agencia → Subcuentas.
- Enlace persona ↔ usuario de la cuenta: `Services/UserLinks.php` (explícito, o por correo si no hay). Un ejecutivo sin enlace no ve nada y se le avisa.
- Lo que vive en la cuenta se escribe en la cuenta, sin copias locales: tareas (`MotorMobile::tarea`), «Nuevo contacto» (`MotorMobile::contactoNuevo`). Con la subcuenta conectada no hay «Agregar manual» ni Excel en Contactos, Oportunidades y Tareas.
- Lecturas opcionales (redes, campañas) usan `GhlGateway::execute(..., soft: true)`: un 4xx no pausa la subcuenta.
- Módulos: Tablero comercial (antes «ARP Comercial»; ruta `/comercial`), Inicio con «Resumen de hoy», Bandeja/Contactos/Oportunidades (al día con Mobile 1.10.14), Tareas, **Redes y anuncios** (`/redes`: Campañas de Meta, cifras de redes de Social Planner y publicaciones por red, solo lectura; las publicaciones incluyen las nativas de Meta Business Suite que la cuenta sincroniza), **Embudo y publicidad** (Campañas de Meta en vivo arriba + reporte mensual), módulos del reporte mensual (Gestión Comercial, Equipo, Ventas, Facturación, Costos, Catálogo, Tendencias, Calidad y Configuración mensual: con reporte muestran el reporte; **sin reporte se arman con ventas registradas, cuenta, campañas de Meta y la configuración del mes** (`TB_VIVO` en `agora-app.js`; Configuración mensual guarda `teamGoal`, `otherAds`, `fixedCosts` en `board.<mes>` vía `workspace/board-month`)), Contratos, Administración.
- Pantalla: `public_html/assets/agora-app.js` (grande; el código principal vive dentro de un IIFE que termina con `render();` — **agrega código nuevo antes del arranque**, por ejemplo junto a `contactoNuevoModal`, o no será visible para `MODS`), `agora-modulos.js` (módulos de Mobile empaquetados), estilos nuevos en `assets/agora-new.css` (se carga al final).
- **Al cambiar de versión** actualiza a la vez: `?v=X` en `index.html` y en el `import` de `motor/consumption.js` dentro de `agora-app.js`, `CACHE` en `sw.js`, `version.json`, `'version'` de `health` en `Api.php`, versión por defecto de `tools/package.py`, `ANALISIS.md`, `NOVEDADES.md`, `ERRORES.md`, `docs/roadmap.md`. `tests/ui-estados.py` comprueba que coincidan.
- Rutas nuevas de la pantalla: agrégalas en `public_html/.htaccess` (OpenLiteSpeed: reiniciar `lsws`) y en `dev-router.php`.

## Pruebas en local

- Script: `/tmp/claude-0/-home-user-GPT/47d2de27-7ad9-51d6-885f-20e6833c2033/scratchpad/up-erp.sh` (si no existe, recrearlo): levanta MariaDB (`/tmp/mdb`, socket `/tmp/mdb/run/mysql.sock`, base `agora_erp`), la cuenta simulada `tests/fake-ghl.py 8099` y la API en `:8090` con `APP_ENV=development AGORA_GHL_FAKE=http://127.0.0.1:8099`, copiando un `config.php` local. **Borra `servidor/config/config.php` y `tests/fake-ghl.log` antes de empaquetar y de hacer commit.**
- Cuentas de prueba: dueña `duena@example.test` / `Clave-Local-2026!`; ASOCIM: `admin.asocim@agora.test`, `supervisor.asocim@…`, `ejecutivo1.asocim@…`, `ejecutivo2.asocim@…`, `consulta.asocim@…`, todas con `Agora-Pruebas-2026!`.
- Orden de pruebas (11 s de pausa entre una y otra y `delete from agora_rate_limits`): `motor-puente.py duena@example.test 'Clave-Local-2026!'` (deja ASOCIM conectada), `permisos.py` (mismos args), `enlaces.py`, `tareas.py`, `contactos.py`, `redes.py`, `campanas.py`, `tarjetas.py`, `ui-estados.py`, `ghl-conexion.php`.
- Revisa la pantalla con Playwright (Chromium en `/opt/pw-browsers/chromium`, `--no-sandbox`), en teléfono (390×844) y escritorio (1280×800), y mira las capturas antes de entregar.

## Pendientes y decisiones del usuario

- **Por confirmar con la cuenta real** (el usuario prueba en tests): escrituras (enviar, asignar, editar contacto, mover/crear oportunidad, marcar ganada, tareas, nuevo contacto), cifras de redes y **Campañas de Meta** (la documentación no dice la forma de la respuesta).
- **No hacer por ahora**: avisos al teléfono («la prioridad no es móvil»), publicar o borrar publicaciones desde Ágora, conexión directa con Meta.
- **Aprendido**: la cuenta sí sincroniza publicaciones hechas en Meta Business Suite («Native post», interruptor «Sync posts» en Social Planner). No afirmes que algo no existe en la cuenta sin evidencia; si el usuario muestra lo contrario, créele y corrige.
- **2.8.2 por confirmar**: la lista de publicaciones se pide con `Version: v3` (y si falla, 2021-07-28); las Campañas de Meta necesitan la cuenta publicitaria conectada en el administrador de anuncios de la cuenta (no basta Social Planner). Resultado real (2.8.3): ASOCIM da 422 «Access Token not found» (Meta) y 404 «connect a page first» (Ad Manager) → la agencia debe reconectar la cuenta publicitaria; las publicaciones vuelven en 0 con ambas versiones. Si salen vacías, la dueña ve un «Detalle técnico» con el código y motivo de cada intento: pídele captura de eso.
- **Tarjetas a elección (2.8.4)**: componente `tarjCargar/tarjElegidas/tarjEditar` en `agora-app.js` + ruta `workspace/preferencias` (clave `prefs.<vista>.u<id>` y `prefs.<vista>.equipo` en `agora_settings`). Hoy en Ventas registradas (`VG_TARJETAS`, vista `ventas`) en Resumen de hoy del Inicio (`INI_TARJETAS`, vista `inicio`) Gestión Comercial sin reporte (`VG_TARJETAS`, vista `gestion`), Equipo (`EQ_TARJETAS`, vista `equipo`), Costos (`CO_TARJETAS`, vista `costos`) Facturación (`FA_TARJETAS`, vista `facturas`) y Tendencias (`TD_TARJETAS`, vista `tendencias`); se puede llevar a otras vistas con su propio catálogo.
- **Estudio** (agencia): «Próximamente» en el riel, en Herramientas del equipo y en Personalizar → Módulos; la ruta `/agencia/estudio-ia` muestra el aviso (el código viejo sigue en el archivo, sin usarse).
- **Tablero comercial · «Ventas del mes y del año» (2.8.13)**: `vtInyectar` (se llama desde `arpEsqueleto`), sección `ventasAnio` en `ARP_SECCIONES`. Mes a mes: reporte (`TB.api.ventasDelAnio`, el reporte guarda **céntimos**, se divide entre 100) y, si no, lo vendido en ventas registradas; meta = `teamGoal`. Solo para quien ve cifras del equipo (decisión del usuario).
- **Ventas importadas por Excel** (Migración): sin `oportunidadId`, sin `productoId` (producto escrito), vendedor escrito (owner null) y **sin plan de cobros**. Desde 2.8.14 se editan igual (`Ventas::contexto` con `sinOportunidad`, `leer` acepta producto y vendedor escritos); el plan se arma al guardar. Pendiente de decidir: armar el plan de las importadas sin editarlas una por una, y qué hacer con las cuotas ya pasadas.
- **Siguiente posible**: bajar de campaña a conjuntos/anuncios; campañas de Google; módulos del reporte para empresas que no son ASOCIM (el usuario debe decir cuáles y qué miden).
- Ágora Mobile V2.1: resumen diario o semanal y cabeceras de seguridad (OpenLiteSpeed ignora `Header` en `.htaccess`). Pendiente de decidir: límite de intentos de contraseña por correo y Capacitor 6.2.3. En espera: plantillas de WhatsApp (falta el identificador de la cuenta de WhatsApp Business). Futuro lejano: módulo de inventario.
- Registro de errores y su estado: `Agora New App/ERRORES.md`. Revisión por módulos: `Agora New App/REVISION-MODULOS.md`. Guía de instalación: `Agora New App/INSTALAR-CYBERPANEL.md`.
