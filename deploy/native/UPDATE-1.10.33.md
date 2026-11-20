# Actualizar Ágora a 1.10.33

Cambio: Consumos dice de qué subcuenta es cada gasto, también el que la cuenta cobra a la agencia sin decirlo (por ejemplo, WhatsApp). Incluye 1.10.17 a 1.10.32.

## Pasos

1. Extraer `agora-update-1.10.33.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.33**.
3. Sin cambios en la base de datos: las reglas se guardan donde ya se guarda la conexión de la agencia.

## Qué cambia (Más → Consumos, solo la persona propietaria)

- **Búsqueda completa:** el identificador de la subcuenta se busca en todos los datos del cargo, no solo en el campo de siempre. Si la cuenta lo manda en otro lugar, el cargo pasa solo a su subcuenta.
- **«¿De qué subcuenta es el gasto sin identificar?»:** muestra los datos que traen los cargos sin subcuenta (por ejemplo, el número de WhatsApp de cada línea) y cuánto suma cada valor. Se elige la subcuenta y «Asignar»: queda una regla que suma esos cargos a esa subcuenta, en este mes y en los demás. «Quitar» la deshace. El total del mes no cambia: solo se reparte.
- Los tipos se leen en palabras («WhatsApp · marketing», «SMS») y sin la referencia técnica de cada mensaje.
- El grupo de la agencia se llama «Agencia · sin subcuenta identificada».
- Pruebas nuevas: `tests/consumption-reparto.php` y `tests/consumption-reparto-ui.py`.
