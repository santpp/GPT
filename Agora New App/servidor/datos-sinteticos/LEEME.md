# Datos sintéticos · servidor de pruebas

Esta carpeta es solo para **testeo**. Nada de lo que hay aquí corresponde a clientes, personas ni cifras reales.

| Archivo | Para qué sirve |
|---|---|
| `asocim-48-meses.csv` / `.json` | 48 meses de cifras mensuales ficticias de ASOCIM (ingresos, costos, publicidad, clientes, convenios…). Sirven para revisar gráficos y tendencias o para importarlas a mano. |
| `streamingnet-48-meses.csv` / `.json` | Lo mismo para StreamingNet. |
| `reporte-ejemplo.json` | Reporte comercial de un mes con el esquema completo del tablero. |
| `facturas-ejemplo.json` | Tabla de facturas de ejemplo (148 filas). |

## En el ambiente de pruebas se cargan solos

Si el sitio no tiene `servidor/config/config.php`, Ágora usa SQLite y al primer arranque crea todo: el usuario **admin** (clave **123456789**) y dos subcuentas ficticias, ASOCIM y StreamingNet (códigos `SIN-ASO` y `SIN-STR`). Cada una trae:

- 6 personas: admin, supervisor, 3 ejecutivos y consulta. Usuario `rol.empresa` (por ejemplo `admin.asocim`, `ejecutivo2.streamingnet`), correo completo `rol.empresa@agora.test`, misma clave 123456789.
- 40 contactos, un embudo de ventas y 60 oportunidades (abiertas, ganadas y perdidas).
- 12 tareas, 6 convenios (la mitad por firmar), 6 meses de reportes y la tabla de facturas.
- Cobros, plan y fecha de corte: StreamingNet vence en 5 días para que se vea la alerta.

Todo pasa por las mismas validaciones y la misma auditoría que la interfaz. Para empezar de cero, borra `servidor/storage/agora-pruebas.sqlite*`.

## Con un servidor de pruebas en MySQL

```bash
/usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --confirmo-pruebas
/usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --borrar --confirmo-pruebas
```

El primero los carga (clave por defecto `Agora-Pruebas-2026!`, o la que indiques con `--clave=`). El segundo borra solo `SIN-ASO` y `SIN-STR` con su equipo y sus datos; no toca otras subcuentas y conserva la auditoría.

> Antes de pasar a producción con datos reales, ejecuta `--borrar` (si usaste MySQL) y elimina esta carpeta y `servidor/bin/sembrar-sinteticos.php`.
