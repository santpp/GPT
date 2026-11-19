# Plan · Facturación (con facturación fiscal) en Agora New App

Pedido del usuario (19 nov 2026): sacar Facturación de «Ventas y facturación» y darle **su propia categoría con submódulos**. Dentro, traer el **facturero de mi.agora**: facturación y facturación fiscal (SENIAT), porque se va a facturar fiscalmente.

Estado: **plan, sin código todavía.** Las decisiones marcadas con ❓ son del usuario.

---

## 1. Qué trae mi.agora (análisis del ZIP)

mi.agora tiene tres piezas relacionadas con facturar:

| Pieza en mi.agora | Qué hace | ¿Viene completa en el ZIP? |
|---|---|---|
| **Facturación fiscal** (`src/Fiscal/*`, `src/Api/FiscalApi.php`, `database/modules/05_billing_module.sql`, pantalla en `servidor.*.js`) | Facturas, notas de crédito y de débito según la Providencia SNAT/2011/00071 y la SNAT/2024/000102 | **Sí**: servidor, base de datos y pantalla |
| **Tasas** (`src/Fx/*`, `07_fx_module.sql`) | Tasa BCV del día (automática, con respaldo) y tasas a mano | Sí |
| **Facturero** (no fiscal) | Crear, guardar e imprimir facturas simples que entran solas al tablero | **No**: el programa lo carga desde un archivo empaquetado que no viene en el ZIP. Para traerlo hace falta ese archivo |
| **Facturación** (ciclo del día 5) | Qué se le cobra a cada cliente en el mes según su plan | Depende de los clientes y planes de mi.agora. En el ERP eso ya existe con otro origen: los planes de cobro de cada venta (Facturación → cobros del mes) |

### Lo que hace la facturación fiscal (y conviene conservar tal cual)

- **Datos fiscales del emisor**, uno por empresa: razón social, RIF, domicilio fiscal, contribuyente especial y modalidad. La modalidad es **imprenta digital** o **formas libres**, con los datos de la imprenta. También guarda la serie, las alícuotas de IVA (general 16 %, reducida 8 %, lujo 15 %), el IGTF (3 %, opcional) y las leyendas.
- **Rangos de números de control** de formas libres entregados por la imprenta: dos dígitos más el número secuencial. Se marcan como activos, agotados o anulados.
- **Documentos FAC, NC y ND** con renglones:
  - Cantidades en milésimas y montos en céntimos: sin errores de redondeo.
  - Cada renglón lleva su tipo de IVA: general, reducido, lujo o exento.
- **Borrador → Emitida → Anulada**:
  - El borrador no gasta número. Al emitir se asigna el correlativo, consecutivo y sin saltos por tipo y serie.
  - Una vez emitido, **el documento no se modifica**: se anula conservando su número, o se corrige con una nota que cita la factura afectada.
  - La anulación y las notas piden un **motivo escrito**, que queda en la auditoría.
- **Operaciones en divisas:** guardan el monto en la moneda, la **tasa BCV** del día y el **equivalente en bolívares**. Si no hay tasa BCV de los últimos 5 días, no deja emitir.
- **Inmutabilidad doble:**
  - Disparadores en la base de datos impiden cambiar un documento emitido, aunque alguien entre por phpMyAdmin.
  - Una **cadena de huellas** (cada documento firma al anterior) se puede **verificar** desde la pantalla.
- **Número de control de la imprenta digital:** se registra a mano después de emitir. No hay conexión automática con ninguna imprenta.
- **Representación imprimible** (HTML) con copia y sello de fecha.
- **Libro de ventas** del mes, descargable en CSV.
- Permisos propios: `facturas.ver`, `facturas.emitir`, `facturas.anular`, `fiscal.configurar` y `libro.ventas`.

### Lo que hay que adaptar al ERP

| mi.agora | Agora New App |
|---|---|
| `org_id` (empresa) | `tenant_id` (subcuenta). **Cada subcuenta es su propio emisor** con su RIF |
| Tablas sin prefijo | Tablas `agora_fiscal_*`, `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` |
| Migraciones con disparadores (`DELIMITER`) | El instalador del ERP solo ejecuta DDL simple: hay que enseñarle a crear disparadores. En CyberPanel puede faltar el permiso para crearlos. Si falta, la inmutabilidad la hacen la aplicación y la cadena de huellas, y Diagnóstico lo avisa |
| Clientes de mi.agora (con RIF y dirección) | En el ERP los clientes vienen de las ventas (nombre y documento) y de los contactos de la cuenta. Hay que guardar los **datos fiscales del cliente** (RIF y domicilio fiscal) y proponerlos al facturar de nuevo |
| Roles de mi.agora | Roles del ERP en `Auth::POLICY` (ver la decisión 3) |
| Tareas programadas de mi.agora | `servidor/bin/worker.php` (cron) trae la tasa BCV cada día; se puede escribir a mano si falla |
| PDF | El PDF del ERP (`Services/Pdf.php`) solo escribe texto. La factura se imprime desde su representación HTML («Imprimir / Guardar como PDF»), como en mi.agora |

---

## 2. Cómo queda el menú

Categoría nueva **«Facturación»**, aparte de «Ventas y facturación», que pasa a llamarse **«Ventas»**:

| Submódulo | Qué es | Origen |
|---|---|---|
| **Cobros** | Lo que toca cobrar en el mes según el plan de cobro de cada venta: pagados, pendientes, vencidos y «Marcar pagado». Con reporte, también el registro de los libros mensuales | El actual «Facturación» del ERP, con otro nombre: el cliente ya lo llama «Cobros» |
| **Facturas** | Facturas fiscales y notas de crédito y débito: borradores, emitir, imprimir, registrar el número de control, anular y crear una nota | Facturación fiscal de mi.agora |
| **Libro de ventas** | El libro del mes para el contador, en Excel o CSV, con totales de bases, IVA e IGTF | mi.agora |
| **Datos fiscales** | Datos del emisor, imprenta, rangos de números de control, impuestos y verificación de la cadena. Solo administración | mi.agora |
| **Tasas del día** | Tasa BCV automática, historial y tasa a mano | mi.agora (solo la parte BCV, que es la que exige la factura) |

Cada submódulo sigue el criterio del ERP (`CLAUDE.md` de New App):
- Tarjetas con «Editar tarjetas» y gráficos al final.
- Se edita donde se ve; en el teléfono no se desborda.
- Confirmaciones con el aviso breve.

Tarjetas propuestas para Facturas:
- Facturado en el mes (en divisas y en Bs).
- IVA débito fiscal.
- Documentos emitidos.
- Borradores sin emitir.
- Anulados.
- Notas de crédito.
- IGTF.
- Facturas sin número de control.

### Unión con lo que ya existe

- **«Facturar» desde una venta o un cobro:** abre un borrador con el cliente, el producto, el monto y la moneda ya puestos. Solo falta revisar y emitir.
- **Cobros muestra si cada cobro ya tiene factura** (número y enlace) y ofrece «Facturar» a los que no.
- **Productos y servicios:** cada producto guarda su tipo de IVA (general, reducido, lujo o exento), para que la factura lo traiga solo.
- **Auditoría:** emitir, anular, notas y cambios de datos fiscales quedan registrados con quién, cuándo y por qué.

---

## 3. Fases

| Fase | Qué | Versión |
|---|---|---|
| **0 · Decisiones** | Las respuestas del punto 4 y, si es posible, la revisión de un contador | — |
| **1 · Menú** | Categoría «Facturación», «Ventas y facturación» pasa a «Ventas» y «Facturación» pasa a «Cobros» (rutas viejas redirigen). Sin funciones nuevas | 2.11.0 |
| **2 · Base fiscal (servidor)** | Migración `007_facturacion.sql` (tablas, disparadores si se puede, permisos). Se adaptan `Money`, `Calculator`, `Documents`, `LibroVentas`, `Representation` y las tasas BCV al ERP (subcuenta, roles y auditoría del ERP). Rutas `workspace/fiscal/*` | 2.12.0 |
| **3 · Pantallas** | Datos fiscales, Facturas (lista, borrador, emitir, imprimir, número de control, anular, notas), Libro de ventas y Tasas del día. Teléfono y escritorio | 2.12.0 |
| **4 · Unión con Ventas y Cobros** | «Facturar» desde la venta y el cobro, factura visible en Cobros, IVA por producto, tarjetas y gráficos | 2.12.x |
| **5 · Datos de mi.agora y piloto** | Si ya se factura con mi.agora: traer los documentos emitidos como históricos (`legacy`) para **seguir el mismo correlativo sin saltos**. Piloto en tests con una subcuenta | 2.13.0 |

### Pruebas que acompañan cada fase

- Cálculos: IVA por alícuota, exento, descuentos, IGTF, equivalente en Bs con la tasa del día y redondeos en céntimos.
- Correlativo sin saltos: dos emisiones al mismo tiempo no repiten ni saltan número; un borrador borrado no gasta número.
- Inmutabilidad: un documento emitido no cambia ni por la API ni por la base (si hay disparadores); la cadena de huellas se verifica y detecta una alteración.
- Anulación y notas: motivo obligatorio, la nota cita la factura y no deja acreditar más de lo facturado.
- Sin tasa BCV no se emite, y el aviso dice cómo cargarla.
- Libro de ventas: los totales cuadran con los documentos del mes.
- Permisos: cada rol solo hace lo suyo, y la consulta no ve botones.
- Pantallas en 390 px y 1280 px, sin textos técnicos.

---

## 4. Decisiones del usuario ❓

1. **Modalidad de emisión:** ¿tienen contratada una **imprenta digital** autorizada (¿cuál?) o usan **formas libres** con rangos de números de control? Si la imprenta tiene una API, se puede conectar más adelante; mientras tanto, el número de control se registra a mano, como en mi.agora.
2. **Quién factura:** ¿todas las subcuentas o solo algunas (por ejemplo, la agencia y ASOCIM)? Propuesta: se activa por subcuenta al completar sus datos fiscales.
3. **Roles.** Propuesta:
   - Emitir y crear notas: ADMIN y SUPERVISOR.
   - Anular: solo ADMIN.
   - Datos fiscales: solo ADMIN.
   - Libro de ventas: ADMIN y SUPERVISOR.
   - EJECUTIVO: solo prepara borradores.
   - CONSULTA: solo ve.
4. **Continuidad:** ¿ya emiten facturas fiscales con mi.agora? Si es así, hay que traer esos documentos para que el correlativo siga sin saltos. Para eso hace falta exportarlos de mi.agora (no uso los respaldos del ZIP sin tu permiso).
5. **Facturero no fiscal** (facturas simples y presupuestos): ¿lo quieren también? Si es así, envíame el archivo del facturero, que no viene en el ZIP.
6. **Moneda de las facturas:** ¿en dólares con su equivalente en Bs (lo habitual), en Bs o en ambas?

## 5. Riesgos y cuidados

- **Cumplimiento:** el módulo sigue la norma que cita mi.agora, pero **un contador debe validarlo** antes de facturar de verdad, sobre todo la representación impresa, las leyendas y el libro de ventas.
- **Imprenta digital:** con la Providencia 0102, el número de control lo asigna la imprenta. Mientras no haya conexión con ella, Ágora lleva el correlativo y registra el control a mano. Hay que confirmar con la imprenta que ese flujo es válido.
- **Permiso para disparadores en CyberPanel:** si el usuario de la base no puede crearlos, la inmutabilidad queda en la aplicación y en la cadena de huellas, y Diagnóstico lo avisa.
- **Tasa BCV:** la página del BCV falla a veces. Hay un respaldo y la carga a mano, y no se emite con una tasa de más de 5 días.
- **El ZIP de mi.agora trae `.env` con claves, respaldos de la base y registros.** No se copian al repositorio ni al paquete; solo se usa el código.
