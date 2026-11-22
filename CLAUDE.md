# Contexto para Claude · Proyecto Ágora

Resumen de cómo trabajamos. El detalle por módulo y las decisiones de cada versión están en **`docs/decisiones.md`**: antes de tocar un módulo, busca allí su nombre. Si algo choca con lo que pide el usuario, manda el usuario.

## Quién y cómo

- **Ágora** (Ágora Digital): espacio de trabajo comercial en español sobre subcuentas de GoHighLevel (GHL). Lo usan agencias y sus clientes (ej.: ASOCIM, educación).
- Responde en **español**, claro, sin jerga técnica innecesaria.
- **Al final de cada respuesta, un roadmap corto** en este orden: hecho, de tu lado, siguiente (lo más urgente primero), después, en pausa, en espera, futuro lejano y, de último, **«Otros»**. Cada punto dice su producto (ERP o Mobile). «En pausa» es del ERP (avisos al teléfono, publicar/borrar publicaciones, conexión directa con Meta); «En espera» (plantillas de WhatsApp) y «Futuro lejano» (inventario) son de Mobile. Quita de «De tu lado» lo ya confirmado.
- Decide tú lo técnico y de diseño y explícalo; pregunta solo lo que es del usuario (producto, prioridades, nombres, gastos).
- Haz y entrega (ZIP listo) en vez de describir. Muestra evidencia (pruebas, capturas revisadas). Si algo no se pudo probar (p. ej. con la cuenta real), dilo.
- **Ahorra tokens:** no leas archivos grandes enteros (busca con grep y lee trozos); corre las pruebas en segundo plano y muestra solo el resumen; pocas capturas, solo de lo que cambió.

## Reglas que nunca se rompen

- **El navegador nunca habla directo con GHL.** Todo pasa por nuestro servidor.
- **En pantalla nunca aparece** «GoHighLevel», «GHL», «HighLevel», «LeadConnector», endpoints ni nombres de permisos técnicos: se dice «la cuenta». (En guías de instalación para la agencia sí se pueden nombrar permisos.)
- **Nada de secretos en Git** (tokens, `config.php`, `config/local.php`, claves, `servidor/storage/`). Revisa el diff antes de cada commit.
- Tokens: se escriben en pantalla, se guardan **cifrados** y nunca se muestran completos (solo los 4 últimos caracteres).
- **No tocar el servidor de producción** (VPS de nueva.agoradigital.app).
- **No mostrar a nadie los códigos de 6 dígitos** de inicio de sesión de otras personas.
- **No usar `pkill -f`**: `pgrep -f "…"` y `kill <pid>`.
- **Sin Docker.** Base de datos: MySQL/MariaDB. Migraciones **idempotentes y automáticas**. Toda tabla nueva declara `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` (el servidor real de Mobile tenía latin1 y falló con «→»).
- Verifica la API de GHL con la copia local: `/home/user/gohighlevel/highlevel-api-docs` (`apps/*.json`, `apps/v3/*.json`, `docs/oauth/Scopes.md`). No hay acceso a la web de GHL ni a la cuenta real.

## Git y entregas

- Rama: `ccr-78b82221-4a0crl`. Al terminar: `git push -u origin ccr-78b82221-4a0crl` y `git push origin ccr-78b82221-4a0crl:main`.
- Los commits terminan con las líneas de atribución que indique el sistema. No pongas el nombre del modelo en código ni documentos.
- Cada versión se entrega como **ZIP + `.sha256`**, se borra del repo el ZIP anterior (`git rm`) y se envía con SendUserFile. El manual PDF va aparte.

## Dos productos, dos desarrollos

| | **Ágora Mobile** (raíz del repo) | **Agora New App · ERP** (`Agora New App/`) |
|---|---|---|
| Qué es | App del vendedor (bandeja, contactos, oportunidades, tablero), PWA y Android | Gestión completa (ventas, facturación, reporte mensual, contratos, agencia) |
| Dónde | Producción: nueva.agoradigital.app | Pruebas: tests.agoradigital.app (CyberPanel + OpenLiteSpeed) |
| Versión | **1.10.35** | **2.18.3** |
| Código | 23 módulos ES (`public/assets/*.js`), `src/` PHP 8.3 | `public_html/assets/agora-app.js` (un archivo grande, se va a refactorizar), `agora-modulos.js` (pantallas de Mobile empaquetadas), `servidor/` |
| Paquete | `python3 deploy/native/package.py "Agora Mobile/agora-update-X.zip" --update` + `deploy/native/UPDATE-X.md`; versión en `src/Installation.php`, pie de `public/index.html` y `tests/integration.php`; `.sha256` con `sha256sum` | `python3 tools/package.py` (revisa secretos, excluye config, storage, pruebas y demos) |
| Pruebas | `tests/*.php` y `tests/*-ui.py` (servidor :8080) | `Agora New App/tests/*.py` (API :8090) |

- **Se desarrollan por separado**: al trabajar en uno, no se toca el otro. **Mobile no se refactoriza** y se toca solo cuando el usuario pide un cambio de Mobile.
- El ERP lleva una **copia del motor de Mobile** (`servidor/motor/`, de 1.10.14). Sus cambios se marcan «Agora New App» y se anotan en `servidor/motor/LEEME.md`. Cambiar la copia no cambia Mobile, y al revés.
- Ideas que pasan de un producto al otro se deciden y entregan por separado.
- `docs/roadmap.md`: historial de versiones de ambos.

## Revisión completa y fixes (metodología)

Cuando el usuario pide «prueba todo / no arregles nada»:
1. **Solo probar**; ni una línea de código hasta entregar el informe.
2. Probar: pruebas automáticas; API con cada rol; guardados (validaciones, conflictos de versión, registros ajenos, HTML inyectado); todas las pantallas con cada rol en ordenador y teléfono (errores, 4xx/5xx, «null/undefined», textos técnicos, desborde a 390 px); exportaciones y cargas por Excel; que las cifras coincidan entre módulos. Copia de la base antes de escribir y restaurarla al final. Guiones en el scratchpad (`qa/api-probe.py`, `qa/explora.py`, `qa/escrituras.py`).
3. Informe por gravedad (Graves · Medios · Menores), con lo que salió bien y lo que no se pudo probar.
4. El usuario decide punto por punto: «arreglar» o «al txt».
5. Lo arreglado va en una versión con su prueba `tests/fixes-XYZ.py` y se anota en NOVEDADES.md, ERRORES.md y docs/roadmap.md.
6. Lo demás va a **`Agora New App/FIXES-PENDIENTES.txt`** (qué pasa, por qué importa, cómo verlo, idea de arreglo); al arreglarlo se borra de ahí.

## ERP · lo esencial

- **Contexto e instrucciones de desarrollo del ERP: `Agora New App/CLAUDE.md`.** Incluye el mapa del código, los dos modos de cada módulo (con y sin reporte), el **criterio único de edición**, el lenguaje visual, los pasos para cambiar de versión y las pruebas locales.
- Lo mínimo para no romper nada: `agora-app.js` termina en `render();` (lo nuevo va antes); permisos con `moduleAllowed`; con la subcuenta conectada, lo de la cuenta se escribe en la cuenta.

## Pruebas en local

- **ERP:** `/tmp/claude-0/-home-user-GPT/47d2de27-7ad9-51d6-885f-20e6833c2033/scratchpad/up-erp.sh` (si no existe, recrearlo) levanta MariaDB (`/tmp/mdb`, socket `/tmp/mdb/run/mysql.sock`, base `agora_erp`), la cuenta simulada `tests/fake-ghl.py 8099` y la API en `:8090` (`APP_ENV=development AGORA_GHL_FAKE=http://127.0.0.1:8099`), copiando un `config.php` local. **Borra `servidor/config/config.php` y `tests/fake-ghl.log` antes de empaquetar y de hacer commit.**
- Cuentas: dueña `duena@example.test` / `Clave-Local-2026!`; ASOCIM `admin|supervisor|ejecutivo1|ejecutivo2|consulta.asocim@agora.test` / `Agora-Pruebas-2026!`.
- Orden (11 s de pausa y `delete from agora_rate_limits` entre una y otra): `motor-puente.py duena@example.test 'Clave-Local-2026!'` (deja ASOCIM conectada), `permisos.py` (mismos args), `enlaces.py`, `tareas.py`, `contactos.py`, `redes.py`, `campanas.py`, `tarjetas.py`, `ventas-importadas.py`, `estudiantes.py`, `venta-cuenta.py` (no repetible), `ui-estados.py`, `menu-principal.py`, `herramientas.py`, `fixes-296.py`, `graficos-gestion.py`, `atajos.py`, `fixes-2117.py`, `consumos-reparto.py`, `fixes-2120.py`, `fixes-2121.py`, `fixes-2123.py`, `ghl-conexion.php`.
- **Mobile:** `source /tmp/mdb/env.sh` (base `workspace`) y `cd public && php -S localhost:8080 router.php`; pruebas UI con `CHROMIUM_PATH=/opt/pw-browsers/chromium`; `php tests/integration.php` necesita ese servidor.
- Revisa la pantalla con Playwright (Chromium en `/opt/pw-browsers/chromium`, `--no-sandbox`) en teléfono (390×844) y escritorio (1280×800).

## Estado y decisiones vigentes (detalle en `docs/decisiones.md`)

- **Refactorización de `agora-app.js` (ERP): después del lanzamiento, cuando el usuario avise.** Plan (Etapa 1): una herramienta lee el archivo y escribe los archivos; primero en orden sin importaciones, luego importaciones y router generados; se verifica con nombres sin definir, HTML idéntico antes y después y todas las pruebas. Mobile no entra.
- **ERP en espera:** módulo de agenda de citas (pedido del cliente).
- **Por confirmar con la cuenta real:** escrituras (enviar, asignar, editar contacto, mover/crear oportunidad, marcar ganada, tareas, nuevo contacto), cifras de redes y Campañas de Meta.
- **No hacer por ahora:** avisos al teléfono, publicar o borrar publicaciones desde Ágora, conexión directa con Meta.
- **No afirmes que algo no existe en la cuenta sin evidencia**; si el usuario muestra lo contrario, créele y corrige.
- Decisiones del usuario que aplican a todo el ERP:
  - Ventas desde el 1/1/2026.
  - «Nuevo convenio» lleva a contratos.agoradigital.app.
  - Estudiantes solo en subcuentas de educación.
  - No se fusionan ni esconden módulos.
  - **Los gráficos van al final del módulo.**
  - Widgets editables «tan personalizables o más que la cuenta»: **en construcción ya, en archivos aparte** (`servidor/app/src/Widgets/`). Solo administración crea widgets y fórmulas. Plan y fases: `Agora New App/PLAN-WIDGETS.md`.
- Pendientes del ERP: `Agora New App/FIXES-PENDIENTES.txt`. Errores: `Agora New App/ERRORES.md`. Instalación: `Agora New App/INSTALAR-CYBERPANEL.md`.
