# Actualizar Ágora a 1.10.37

Arreglo: **Conexiones** mostraba «No se ha podido completar la operación» después de instalar la 1.10.36. Incluye 1.10.17 a 1.10.36.

## Pasos

1. Extraer `agora-update-1.10.37.zip` sobre las carpetas existentes.
2. Abrir Ágora: al arrancar crea solas las tablas de las plantillas por flujos.
3. El pie debe decir **v1.10.37**, y Conexiones vuelve a mostrar las subcuentas.

## Qué estaba pasando

- La 1.10.36 traía dos tablas nuevas (`whatsapp_flows` y `whatsapp_flow_sends`). Ágora solo aplica sus migraciones cuando su revisión de arranque echa en falta alguna tabla, y esa revisión no incluía las nuevas. En un servidor ya instalado las tablas no se creaban, y la lista de Conexiones, que cuenta los flujos de cada subcuenta, fallaba.
- En las pruebas locales no se vio porque la propia prueba creaba las tablas.

## Qué cambia

- La revisión de arranque comprueba las dos tablas; si faltan, aplica la migración sola (es idempotente).
- Prueba: `tests/whatsapp-flows.php` borra las tablas y comprueba que el arranque las crea (falla con la 1.10.36).
