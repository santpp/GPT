# Actualizar Ágora a 1.10.12

Sistema de diseño completo.

## Pasos

1. Extraer `agora-update-1.10.12.zip` sobre las carpetas existentes. No hay migraciones.
2. Subir también el manual: `manual-vendedores.pdf` a `public_html/assets/manual-vendedores.pdf` (va aparte).
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.12**.

## Qué cambia

- **Colores:** 154 tonos escritos a mano pasan a una paleta de 18 con nombre (más WhatsApp, Instagram y Facebook). Los casi iguales ahora son iguales; el texto sólo usa colores de texto.
- **Letra:** 28 tamaños pasan a una escala de 10; los más pequeños suben un punto.
- **Sombras:** 20 pasan a tres niveles.
- **Tablero del vendedor:** se quita «Tus cifras»; queda «Actualizado a las …». (El de la propietaria sigue diciendo «Cifras de la cuenta», que indica que es la cuenta entera.)
- Prueba nueva `tests/design-tokens.py`: falla si aparece un color, tamaño o sombra fuera del sistema.
- `docs/DESIGN.md` ampliado: paleta completa con claro y oscuro, escala de letra, sombras, estados de interacción, formularios, listas y vacíos, roles, rutas, cómo hacer la imagen de antes y después y lista de comprobación.
- Manual: capturas y PDF regenerados.
