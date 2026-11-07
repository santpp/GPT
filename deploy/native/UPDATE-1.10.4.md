# Actualizar Ágora a 1.10.4

Consumos muestra también los movimientos de la agencia.

## Pasos

1. Extraer `agora-update-1.10.4.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.4**.

## Qué cambia

- **Agencia · movimientos propios:** lo que la billetera registra sin subcuenta (compras de dominios, recargas de la agencia, etc.) sale como un bloque más, con su gasto, recargas, desglose, día por día y movimientos.
- **Subcuentas no conectadas a Ágora:** en vez de una línea suelta, cada una sale con su bloque y su identificador.
- **Selector:** «Todas (subcuentas y agencia)», «Sólo la agencia» o una subcuenta. Los totales incluyen todo; el contador «Subcuentas» cuenta sólo las conectadas.
- El reporte y los archivos .txt/PDF incluyen la agencia.
