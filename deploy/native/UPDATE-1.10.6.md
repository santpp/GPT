# Actualizar Ágora a 1.10.6

Oportunidades (Embudos) rehechas y búsqueda por nombre, teléfono o correo. «Ganadas» no cambia.

## Pasos

1. Extraer `agora-update-1.10.6.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.6**.

## Qué cambia

- **Etapas:** una tira de chips con el número de cada etapa (tocar uno salta a ella) y cada etapa se pliega con su resumen.
- **Tarjetas compactas:** tocar la tarjeta abre la edición (ya no hay botón «Editar»). Atajos de **Conversación** y **Ficha** del contacto, y **Mover a…** para cambiar de etapa en un toque.
- **Filtros:** por responsable y «Sin monto», con «Quitar filtros».
- **Buscadores:** Conversaciones, Contactos y Oportunidades buscan por nombre, teléfono o correo (el teléfono vale con espacios, guiones o paréntesis).
- Sin tiempo en la etapa ni avisos nuevos (descartados).
- Prueba nueva: `tests/embudos-ui.py`.
