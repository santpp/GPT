# Actualizar Ágora a 1.1.0

Comisión por subcuenta desde Conexiones, administradores fuera de la lista de vendedores, de dónde sale cada cifra, avisos que abren su conversación y novedades que escribes tú.

## Pasos

1. Extraer `agora-update-1.1.0.zip` sobre las carpetas existentes. La base de datos se actualiza sola (tres tablas nuevas).
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.1.0**.

## Comisión por subcuenta (sólo tú, sólo en Conexiones)

- **Conexiones → Comisión**, en cada subcuenta: eliges **porcentaje de la venta** o **monto fijo por cada cierre**, y la cantidad.
- **Vale desde el mes en que lo cambias.** Los meses anteriores conservan la regla con la que se calcularon. El diálogo muestra el historial de cambios.
- Ya no se edita en «Cifras del mes» del tablero, y nadie más puede cambiarla.
- La ganancia, la comisión de cada vendedor y el «Costo por cierre» usan la regla de su subcuenta. Con monto fijo, la comisión es cierres × monto.
- Las subcuentas que no tengan regla siguen con el 10 % de siempre.

## Tablero

- **Sin administradores en la lista de vendedores:** quien tenga rol de administrador (de subcuenta o de agencia) ya no sale entre los vendedores. **Lo que cierren sigue contando** en el total del mes, la ganancia y las comisiones. Se actualiza con la sincronización del equipo (cada hora, o a mano en Conexiones → Equipo y permisos).
- **«¿De dónde salen estas cifras?»**, debajo de la rentabilidad: explica de dónde sale y cómo se calcula cada cifra, con la regla de comisión de esa subcuenta.

## Los avisos llevan a su conversación

- Al pulsar un aviso de un mensaje, Ágora abre **esa conversación**, no la bandeja. Si es de otra subcuenta, cambia a ella primero. Si la conversación ya no está disponible, lo dice.
- Funciona aunque la conversación no esté entre las últimas de la lista.
- El aviso del teléfono necesita el service worker nuevo: cierra y abre la app. El pie lo avisa si se queda atrás.

## Novedades que escribes tú

- **Conexiones → Novedades para el equipo:** escribes un título y un texto (las líneas que empiezan por «- » salen como lista), un botón opcional que lleva a una pantalla de Ágora (`/tablero`) o a una dirección `https://`, y cuánto tiempo se muestra.
- **Vista previa** antes de publicar.
- Cada persona de tu empresa la ve **una sola vez, en una ventana, al entrar**. Cerrarla de cualquier forma cuenta como vista.
- Verás cuántas personas la han visto, y puedes **retirarla** si aún no la han visto todos.
- Se escriben en lenguaje de cliente: no uses nombres técnicos de la cuenta.
