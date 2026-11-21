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
- Nombres de botones fijos en todo el ERP: **«Descargar»** (ícono de descarga, nunca la palabra «Excel») y **«Editar Dashboard»** (ícono de lápiz), en la misma línea. Las tarjetas de cifras van sin ícono (2.12.8).
- **Tipografía y medidas:** Author en todo (la fuente está en `assets/fonts/` y va en el paquete). Escala única al final de `agora-new.css` (bloque 2.11.5). No pongas tamaños propios en una pantalla; `tests/tipografia.py` lo revisa. Tabla completa en `../docs/DESIGN.md` § 4 bis.
  - Título del módulo: 28 px (24 en el teléfono). Bajada: 13 px.
  - Títulos: sección 20 px (18 en el teléfono); panel 16,5 px.
  - Texto: 14,5 px. Tablas: 14 px.
  - Botones de cabecera: 13,5 px; alto 36 px; forma de píldora.
  - Tarjetas de cifras:
    - Rótulo: 14 px (13 en el teléfono), centrado, con una línea de descripción de 12,5 px. Nota: 12,5 px. Cifra: 26 px (22 en el teléfono).
    - Estilo (2.12.8, referencia del usuario): todo centrado y compacto, **sin íconos ni degradados**; en reposo, la línea fina gris de la caja; **la línea roja con brillo aparece solo al pasar** (2.12.9); botón «Editar» **solo con la palabra** (arriba a la derecha; debajo de la cifra en el teléfono) que abre «Editar Dashboard» con esa cifra marcada; sin huecos en la última fila (`cajaRellenar`) y contenido centrado en vertical. Lo hace `cajaVivo` en `tgAplicar`; `tests/fixes-2126.py` lo revisa.
    - Caja: radio 16 px; cada cifra con relleno 16 × 20 px (12 en el teléfono).
    - Rejilla: 4 columnas con 14 px de separación (2 columnas con 10 px en el teléfono).
  - **Ritmo único (2.12.4):** toda caja con radio 16 px; paneles con relleno 20 × 24 px (16 en el teléfono); 16 px entre cajas seguidas (en filas de varias columnas, el espacio lo pone la fila); encabezado de sección con 32 px arriba y 12 px hasta su caja; 20 px del título del módulo a la primera caja. **En el teléfono igual (2.12.5):** fichas y tarjetas (`board-card2`, `kpi-card`, filas de tabla) con radio 16, relleno 14 × 16 y 12 px entre una y otra; íconos de sección 36 px (32 en el teléfono). `tests/fixes-2123.py` mide los dos anchos; siempre revisa ordenador **y** teléfono.
- **Atajos en las tarjetas (criterio único):**
  - Si el dato de una tarjeta vive en otro módulo, la tarjeta lleva a ese módulo, con chevron en el rótulo y el filtro puesto.
  - Las tarjetas del propio módulo no llevan a ningún lado.
  - Solo hay atajo si la persona puede abrir el destino.
  - Una tarjeta nueva en un catálogo de «Editar Dashboard» lleva `ir: {mod, filtro?, pestana?, since?, ayuda}` cuando su dato es de otro módulo.
  - El Dashboard (antes «Tablero comercial», pantalla de Mobile) usa `TB_ATAJOS`.
  - Todo navega con `tarjIr(r)`. `tests/atajos.py` lo revisa.
- **Inicio de sesión:** solo marca, Athena con su globo, «Inicia sesión», los campos, «Continuar» y «¿Olvidaste tu clave?». Sin emojis, puntos de estado, píldoras ni frases de relleno (el usuario los quitó en 2.11.7).
- **Una caja por grupo de cifras en TODOS los módulos** (borde, radio 16 px, fondo blanco) con las cifras separadas por líneas finas; nada de tarjetas sueltas con espacio entre ellas. Lo hace el bloque 2.12.3 de `agora-new.css` para `.ag-resumen-grid`, `.g-hero` y `.grid` con `.micro`.
- **Los botones de módulo («Editar Dashboard», «Dashboard», «Descargar») van siempre fuera de las tarjetas**, en la línea del título (2.12.11; también «Resumen del día»).
- **«Editar Dashboard» en todo módulo con cifras:** con catálogo (`tarjEditar`) donde lo hay; si no (Tablero, vistas del reporte), el genérico `tgAplicar`/`tgEditar` lee las cifras en pantalla y guarda orden y ocultas (vista `g_<módulo>`).
- **Tableros: 4 cifras por fila** en el ordenador (2 en pantallas medianas). Una tarjeta grande ocupa una columna más, no media pantalla.
- **Colores (2.12.3):** dos familias del manual, vino y azul profundo, con sus tonos (`--ag-1`, `--ag-2`, `--ag-4` a `--ag-8`; módulos `--mod-*`). El oro solo para detalles pequeños y para la categoría Facturación; verde y ámbar solo para estados. Nada de selectores de color.
- **Menú (2.12.8):** un color distinto por categoría (`--cat-*` en `agora-new.css`; los módulos heredan el de su sección justo antes del arranque, para todos los caminos, también el del dueño): Ventas vino, Facturación oro, Clientes azul profundo, Gestión azul medio, Contratos rosa, Administración vino oscuro. **Botones secundarios de las ventanas** («Cancelar», «Cerrar», «Restaurar», la X): al pasar, enfocar o tocar, fondo vino y letra blanca.
- **Selectores de período:** la barra `.per` del Inicio (opción en blanco, punto verde `pt-curso`, casita para volver).
- **Menús:** el de la agencia y el de las subcuentas hablan igual: fondo claro, ícono en recuadro de color (`.mdm-ic`), activo con fondo suave de marca y rótulos en tipo oración. Nada de mayúsculas espaciadas, franjas de color ni avisos «Próximamente» en el menú.
- **Cifras de la cuenta = cifras de Mobile.** Todo lo que dependa de «hoy» se pide con la zona del dispositivo (`dashZona()`), igual que Mobile.
- Confirmaciones con `toast('…')` cortas («Costos guardados.»). Nada de franjas, insignias o íconos nuevos para confirmar: el usuario lo rechazó por «verse muy IA».
- **Sin frases bajo el título del módulo** (2.12.12: «son obviedades»): `head()` puede recibir `lede`, pero no se muestra; la descripción de cada módulo vive solo en el Inicio. Excepción: la línea de estado del Dashboard. Íconos de categoría: Gestión y resultados `stocks` (velas), Administración y Ajustes `gear` (engranaje).
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
- Orden de las pruebas: `motor-puente.py duena@example.test 'Clave-Local-2026!'`, `permisos.py` (mismos argumentos), `enlaces.py`, `tareas.py`, `contactos.py`, `redes.py`, `campanas.py`, `tarjetas.py`, `ventas-importadas.py`, `estudiantes.py`, `venta-cuenta.py` (no se puede repetir), `ui-estados.py`, `menu-principal.py`, `herramientas.py`, `fixes-296.py`, `graficos-gestion.py`, `costos-editar.py`, `facturacion.py`, `facturacion-ui.py` (necesita la anterior), `fixes-2111.py`, `tipografia.py`, `atajos.py`, `fixes-2117.py`, `consumos-reparto.py`, `fixes-2120.py`, `fixes-2121.py`, `fixes-2123.py`, `ghl-conexion.php`.

## Venta ganada (2.12.13)

- Un solo flujo: «¿Venta ganada?» (Bandeja y ficha del contacto) → `window.agoraGanar` elige la oportunidad → al quedar ganada, `vgInterceptar`/`vgGanada` abre el formulario de venta (`vgAbrir`), el mismo de Ventas registradas. No se pide el valor aparte; el formulario lo guarda en la oportunidad. Las ganadas sin venta son «ventas por completar».

## Facturación (2.11.0)

- Categoría propia: Cobros (`tc_facturas`, ruta `/cobros`), Facturas, Libro de ventas, Datos fiscales y Tasas del día. Plan y decisiones: `PLAN-FACTURACION-FISCAL.md` y `../docs/decisiones.md`.
- Motor fiscal traído de mi.agora en `servidor/app/src/Fiscal/` (Documents, Calculator, Money, LibroVentas, Representation, FxSources) con la capa `Fiscal/Base`, que lo conecta al ERP. **Se cambia lo menos posible**: la lógica fiscal (correlativo, inmutabilidad, cadena de huellas, cálculos en céntimos) ya está probada. Controlador: `Controllers/Facturacion.php` (rutas `workspace/fiscal/*`, `workspace/facturas*`, `workspace/libro-ventas`). Tablas `agora_fiscal_*` (migración 007). Disparadores: `Fiscal/Esquema`.
- Tipos de documento: `FAC` (fiscal), `NC`, `ND` y `SIM` (factura simple, sin validez fiscal; la única de una subcuenta sin datos fiscales).
- **Moneda de la factura: bolívares.** Desde 2.12.0 los precios se pueden escribir en US$ o € con la tasa elegida (BCV dólar, BCV euro o USDT Binance): `Facturacion::preparar()` convierte en el servidor y guarda `ref_json` (se imprime). `DIVISAS` sigue en false: facturar *en* otra moneda es otra cosa y no está pedido.
- **Tasas:** `Fiscal/Tasas::FUENTES` (BCV, BCV_EUR, USDT). Automáticas en `agora_fiscal_fx`; a mano: BCV en `agora_fiscal_tasas`, las otras en `agora_fiscal_tasas_ref` (migración 008). Columnas nuevas de documentos (`ref_json`, `due_time`) las agrega `Esquema::columnas()` (MySQL 8 no tiene «ADD COLUMN IF NOT EXISTS»).
- Imprenta digital: SERDIMPRE, sin conexión todavía (el número de control se registra a mano). Tasa BCV: la trae `bin/worker.php`; también se puede escribir a mano.

## Decisiones vigentes del ERP

- Ventas desde el 1/1/2026. «Nuevo convenio» lleva a contratos.agoradigital.app. Estudiantes solo en subcuentas de educación. No se fusionan ni esconden módulos.
- **En espera:** módulo de agenda de citas (lo pidió el cliente; el usuario lo dejó para después).
- **En pausa:** avisos al teléfono, publicar o borrar publicaciones, conexión directa con Meta.
- **Refactorización de `agora-app.js`:** después del lanzamiento, cuando el usuario avise. Hasta entonces se trabaja sobre el archivo actual.
- Widgets editables «tan personalizables o más que la cuenta», después de la refactorización.
