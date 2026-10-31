# Actualizar Ágora a 1.5.3

Campos personalizados agrupados por carpetas.

## Pasos

1. Extraer `agora-update-1.5.3.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.5.3**.

## Qué cambia

- La cuenta indica a qué carpeta pertenece cada campo, así que la ficha y el editor los agrupan por carpeta, en el orden de la cuenta.
- Ágora intenta leer el nombre de cada carpeta. Si la cuenta no lo entrega, se muestran como «Carpeta 1», «Carpeta 2»… y se nombran una sola vez en **Conexiones → Carpetas de campos** (cada carpeta se reconoce por sus campos de muestra). Los nombres quedan guardados.
- Se dejó de consultar un endpoint que la cuenta rechazaba.
