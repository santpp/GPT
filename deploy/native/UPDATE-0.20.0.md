# Actualizar Ágora a 0.20.0

Tablero nuevo: **rentabilidad del mes** y **desempeño del equipo de ventas**. Además, el total de contactos estaba mal y ya está corregido, y se arregla una pantalla en blanco en el móvil.

## Pasos

1. Extraer `agora-update-0.20.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos se actualiza sola. No hay cambios de configuración ni permisos nuevos.

## La pantalla en blanco del móvil

Si ampliabas una conversación con el botón de ampliar (modo foco) y luego cerrabas la app, al volver a entrar la preferencia se recuperaba **aunque no hubiera ninguna conversación abierta**: eso escondía el menú y la lista y dejaba una pantalla casi vacía, sin forma de salir salvo reinstalando. Corregido: el modo foco sólo se aplica al abrir una conversación. Cubierto con una prueba en pantalla de teléfono para que no vuelva.

## El total de contactos

Estaba mal y la causa es concreta: la lista de contactos de la cuenta devuelve un campo que no es el total, sino el tamaño de la página, y yo lo mostraba como si fuera el total. Ahora se pregunta a la búsqueda de contactos, que sí da un total, y **si no llega un número utilizable no se muestra cifra**, en lugar de enseñar una falsa.

## Rentabilidad del mes

Arriba del tablero, con un selector de mes:

- **Ganancia después de publicidad y comisiones**, y cuánto queda de cada unidad vendida.
- Una cascada que va de **ventas** a **ganancia**, restando publicidad y comisiones por el camino.
- Tarjetas: ganancia real (ya descontados los costos fijos), costo por cierre, ventas por cada 100 invertido, costos de mercadeo y ventas, y comisiones del equipo.

Lo vendido sale de la cuenta: son las oportunidades **ganadas** dentro del mes, leídas con el filtro de fechas que publica la cuenta, página a página.

## Desempeño del equipo

- Avance del equipo frente a su meta, con cuántas personas la lograron, cuántas están cerca y cuántas necesitan apoyo.
- Una fila por asesora: cierres, meta, vendido, diferencia, avance y estado, ordenadas por lo vendido.
- Lo cerrado sin responsable aparece aparte, no desaparece.

## Las cifras que tú pones

Publicidad, costos fijos, comisión y metas **no existen en la cuenta**: los escribes tú, mes a mes, con el botón **Cifras del mes** (sólo el administrador). Ahí también eliges cómo llamar a cada cierre —«estudiantes», «clientes», «pólizas»— y los umbrales del semáforo (verde desde 100 %, ámbar desde 70 % por defecto).

Mientras falte alguna, el tablero **dice cuál falta** en lugar de enseñar un cero que parezca un resultado.

## Comprobado

556 comprobaciones contra base de datos real, 25 nuevas del tablero del mes: el rango exacto que se pide a la cuenta, la suma sólo de lo propio, la paginación de un mes grande, el reparto por asesora, el semáforo, lo cerrado sin responsable, y que un integrante limitado a sus datos sólo se ve a sí mismo. Nueve comprobaciones nuevas de interfaz, incluidas las de 390 px.
