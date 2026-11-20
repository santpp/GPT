# Contexto para Claude · Agora New App (ERP)

Se carga junto con el `CLAUDE.md` de la raíz (reglas generales, Git, entregas, roadmap). Aquí está lo propio del ERP y **cómo se desarrolla**. El historial de decisiones está en `../docs/decisiones.md` (busca el nombre del módulo antes de tocarlo). Si algo choca con lo que pide el usuario, manda el usuario.

## Qué es

- Gestión completa de una subcuenta: ventas y facturación, clientes y atención (bandeja, contactos, oportunidades, tareas), gestión y resultados, contratos y administración. Más el panel de agencia (dueños).
- Servidor de pruebas: **tests.agoradigital.app** (CyberPanel + OpenLiteSpeed). Instalación: `INSTALAR-CYBERPANEL.md`.
- Ágora Mobile es otro producto: **no se toca desde aquí**. El ERP lleva su propia copia del motor de Mobile (`servidor/motor/`, de 1.10.14) y de sus pantallas (`public_html/assets/agora-modulos.js`). Cambios en esas copias: marca «Agora New App» y anótalos en `servidor/motor/LEEME.md`.

## Mapa del código

| Parte | Dónde |
|---|---|
| Pantallas | `public_html/assets/agora-app.js` (un solo archivo grande que termina en `render();`; **el código nuevo va antes del arranque**). Pantallas de Mobile empaquetadas: `agora-modulos.js` |
| Estilos | Nuevos en `assets/agora-new.css`. Base: `diseno.css`, `sistema.css`, `modulos.css` |
| API | `public_html/api/index.php` → `servidor/app/src/Controllers/Api.php` (y los demás controladores) |
| Cuenta en vivo | `servidor/app/src/Core/MotorMobile.php` (puente al motor) |
| Permisos | `servidor/app/src/Security/Auth.php::POLICY`. En pantalla usa `moduleAllowed(k)`, nunca `MODS[k].ok()` directo |
| Base de datos | Tablas `agora_*`; migraciones idempotentes en `servidor/app/database/migrations/` (el arranque aplica la más nueva). Tablas nuevas con `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` |
| Rutas nuevas | `public_html/.htaccess` y `dev-router.php` |
| Dueños y claves | Solo por terminal: `servidor/bin/crear-dueno.php`, `servidor/bin/cambiar-clave.php correo` / `--desbloquear` |

## Los dos modos de cada módulo

Muchos módulos tienen dos caras y hay que probar las dos:

1. **Con reporte mensual importado** (como ASOCIM): cifras del reporte, tablas con «Agregar manual» y «Columnas», y el aviso «¿Cómo corrijo estos datos?» (`XP_ORIGEN`).
2. **Sin reporte** (el caso de tests.agoradigital.app y de toda subcuenta nueva): vistas «en vivo» de `TB_VIVO` armadas con las ventas registradas, la cuenta conectada y las cifras del mes (`workspace/board-month`).

Con la subcuenta conectada, lo que vive en la cuenta (contactos, oportunidades, tareas, conversaciones) **se escribe en la cuenta**: sin copias locales, sin «Agregar manual» ni Excel (`EN_VIVO`).

## Criterio único de edición

Es el criterio que se usa en todos los módulos. Un módulo nuevo o modificado tiene que cumplirlo:

1. **Se edita donde se ve.** Si un módulo muestra un dato que es nuestro (no de la cuenta ni del reporte), ese dato tiene un botón para cambiarlo en el mismo módulo. Por ejemplo: «Cargar costos / Editar costos» en Costos, «Marcar pagado» en Facturación, «Agregar manual» en Ventas. No se manda a otro módulo para editar lo que se está viendo.
2. **Un dato, un lugar de verdad.** Si dos módulos muestran el mismo dato, uno lo edita y el otro lo muestra bloqueado con un enlace («Sale de “Costos del mes”. Editar en Costos»). Nunca dos formularios que se pisan.
3. **Tarjetas siempre editables.** Todo módulo con tarjetas de cifras tiene **«Editar Dashboard»** (ícono de lápiz) con `tarjEditar(vista, CATALOGO, DEFECTO, título, repintar)` y `vivoTarjetas(...)`. El nombre del botón es siempre «Editar Dashboard».
4. **Las tarjetas por defecto miden lo que el módulo promete.** Si una tarjeta solo puede mostrar «—» hasta que se cargue algo, su texto dice dónde cargarlo (en el mismo módulo).
5. **Permisos:** solo administración (MASTER, SUPERADMIN, ADMIN) edita cifras del mes y costos; el resto ve los datos sin botones. El servidor lo exige (`manage()`), no solo la pantalla.
6. **El servidor valida todo lo que se guarda** (tipos, largos, montos ≥ 0, listas con tope), aunque la pantalla ya lo valide.
7. **Lo del reporte no se edita en la tabla**: se corrige reimportando o en Configuración mensual. Solo se agregan filas o columnas propias (TablaEd).

## Lenguaje visual

- Usa lo que ya existe: `head()` para la cabecera, `secH()` para cada sección, tarjetas `.ag-resumen`, tablas dentro de `.tw`, píldoras `.sc sc-ok|sc-warn|sc-bad|sc-gray`, botones `.btn`, `.btn-cta`, `.btn-sm`, ventanas con `openModal(modalHead(...))`, filas editables como `.ag-serv`.
- Nombres de botones fijos en todo el ERP: **«Descargar»** (ícono de descarga, nunca la palabra «Excel») y **«Editar Dashboard»** (ícono de lápiz), en la misma línea. **Las tarjetas de cifras van sin ícono** (título, cifra y nota, como «Resumen de hoy»), para elegir las tarjetas.
- **Tipografía:** Author en todo. Escala única (final de `agora-new.css`, sección 2.11.5): título 28 px (24 en el teléfono), sección 20, panel 16,5, tarjeta rótulo 12,5 y cifra 26 (22 en el teléfono), botones de cabecera 13,5. No pongas tamaños propios en una pantalla: usa estas clases. `tests/tipografia.py` lo revisa.
- Confirmaciones con `toast('…')` cortas («Costos guardados.»). Nada de franjas, insignias o íconos nuevos para confirmar: el usuario lo rechazó por «verse muy IA».
- Sin textos repetidos (la etiqueta superior no repite el título). **Los gráficos van al final del módulo.**
- Botones de icono solo cuando su significado es obvio. Si no, icono + texto (por ejemplo, «Ficha» en la Bandeja).
- En el teléfono (390 px): nada se desborda; las tablas se leen como fichas.
- En pantalla nunca aparecen nombres del proveedor de la cuenta, endpoints ni permisos técnicos: se dice «la cuenta».

## Instrucciones de desarrollo

1. **Antes de tocar:** busca el módulo en `../docs/decisiones.md` y en `FIXES-PENDIENTES.txt`. Lee solo los trozos necesarios (grep), no el archivo entero.
2. **Reproduce primero** en local (abajo), en el modo que tiene el usuario (con o sin reporte; teléfono o escritorio) y con su rol.
3. **Cambia lo mínimo** y aplica el criterio de edición y el lenguaje visual de arriba.
4. **Prueba:** una prueba nueva `tests/<tema>.py` con lo que se arregló (servidor y pantalla, teléfono y escritorio, rol sin permiso), más las pruebas que toquen el módulo. Revisa con captura solo lo que cambió.
5. **Versión** (todo a la vez; `tests/ui-estados.py` comprueba que coincidan): `?v=X` en `index.html` y en el `import` de `motor/consumption.js` dentro de `agora-app.js`, `CACHE` en `sw.js`, `version.json`, `'version'` de `health` en `Api.php`, versión por defecto en `tools/package.py`, `ANALISIS.md`, `NOVEDADES.md`, `ERRORES.md` y `../docs/roadmap.md`, y la versión en el `CLAUDE.md` de la raíz.
6. **Entrega:** borra `servidor/config/config.php` y `tests/fake-ghl.log`, luego `python3 tools/package.py` (revisa secretos) → ZIP + `.sha256`. Haz `git rm` del ZIP anterior, commit, push a la rama y a `main`, y envíalo con SendUserFile.
7. **Revisión completa** («prueba todo, no arregles nada»): sigue la metodología del `CLAUDE.md` raíz. Lo que no se arregla va a `FIXES-PENDIENTES.txt`.

## Probar en local

- Arranque: `/tmp/claude-0/-home-user-GPT/47d2de27-7ad9-51d6-885f-20e6833c2033/scratchpad/up-erp.sh` (MariaDB en `/tmp/mdb`, cuenta simulada `tests/fake-ghl.py 8099`, API en `:8090`). No le pongas `| tail`: los procesos que deja corriendo mantienen la tubería abierta.
- Cuentas: dueña `duena@example.test` / `Clave-Local-2026!`; ASOCIM (con reporte) `admin|supervisor|ejecutivo1|ejecutivo2|consulta.asocim@agora.test` / `Agora-Pruebas-2026!`.
- **Subcuenta sin reporte:** la 1 («Prueba SA»), con la dueña. En Playwright, después de entrar: `fetch('/api/workspace/active', {method:'POST', body:{connectionId:1}})`.
- Entre una prueba y otra: 11 s de pausa y `delete from agora_rate_limits`.
- Orden de las pruebas: `motor-puente.py duena@example.test 'Clave-Local-2026!'`, `permisos.py` (mismos argumentos), `enlaces.py`, `tareas.py`, `contactos.py`, `redes.py`, `campanas.py`, `tarjetas.py`, `ventas-importadas.py`, `estudiantes.py`, `venta-cuenta.py` (no se puede repetir), `ui-estados.py`, `menu-principal.py`, `herramientas.py`, `fixes-296.py`, `graficos-gestion.py`, `costos-editar.py`, `facturacion.py`, `facturacion-ui.py` (necesita la anterior), `fixes-2111.py`, `tipografia.py`, `ghl-conexion.php`.

## Facturación (2.11.0)

- Categoría propia: Cobros (`tc_facturas`, ruta `/cobros`), Facturas, Libro de ventas, Datos fiscales y Tasas del día. Plan y decisiones: `PLAN-FACTURACION-FISCAL.md` y `../docs/decisiones.md`.
- Motor fiscal traído de mi.agora en `servidor/app/src/Fiscal/` (Documents, Calculator, Money, LibroVentas, Representation, FxSources) con la capa `Fiscal/Base`, que lo conecta al ERP. **Se cambia lo menos posible**: la lógica fiscal (correlativo, inmutabilidad, cadena de huellas, cálculos en céntimos) ya está probada. Controlador: `Controllers/Facturacion.php` (rutas `workspace/fiscal/*`, `workspace/facturas*`, `workspace/libro-ventas`). Tablas `agora_fiscal_*` (migración 007). Disparadores: `Fiscal/Esquema`.
- Tipos de documento: `FAC` (fiscal), `NC`, `ND` y `SIM` (factura simple, sin validez fiscal; la única de una subcuenta sin datos fiscales).
- **Moneda: solo bolívares.** Para habilitar dólares y euros, `Controllers/Facturacion::DIVISAS = true` (cuando el usuario lo diga); luego cada subcuenta elige en Datos fiscales.
- Imprenta digital: SERDIMPRE, sin conexión todavía (el número de control se registra a mano). Tasa BCV: la trae `bin/worker.php`; también se puede escribir a mano.

## Decisiones vigentes del ERP

- Ventas desde el 1/1/2026. «Nuevo convenio» lleva a contratos.agoradigital.app. Estudiantes solo en subcuentas de educación. No se fusionan ni esconden módulos.
- **En espera:** módulo de agenda de citas (lo pidió el cliente; el usuario lo dejó para después).
- **En pausa:** avisos al teléfono, publicar o borrar publicaciones, conexión directa con Meta.
- **Refactorización de `agora-app.js`:** después del lanzamiento, cuando el usuario avise. Hasta entonces se trabaja sobre el archivo actual.
- Widgets editables «tan personalizables o más que la cuenta», después de la refactorización.
