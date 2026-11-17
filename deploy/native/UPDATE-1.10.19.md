# Actualizar Ágora a 1.10.19

Contactos «Creados hoy» y cifras del Tablero que llevan a su módulo. Incluye 1.10.17 y 1.10.18.

## Pasos

1. Extraer `agora-update-1.10.19.zip` sobre las carpetas existentes. Si vienes de 1.10.17 o anterior, la tabla de respuestas guardadas (1.10.18) se crea sola.
2. El pie debe decir **v1.10.19**.

## Qué cambia

- **Contactos: pestañas «Todos» y «Creados hoy».** «Creados hoy» lista los contactos dados de alta hoy en la cuenta (con la hora del teléfono), del más nuevo al más viejo. Quien solo ve lo suyo ve solo los suyos.
- **Tablero: cada cifra lleva a su módulo**, ya filtrado:
  - Leads de hoy y Mis leads de hoy → Contactos con «Creados hoy».
  - Conversaciones → Conversaciones; Sin leer → «No leídas»; Esperando respuesta → «Sin respuesta».
  - Contactos → Contactos. Oportunidades abiertas y cada etapa del embudo → Oportunidades.
  - Ventas ganadas del mes y Mis ventas del mes → Oportunidades, pestaña «Ganadas».
- **Según permisos:** una cifra solo es enlace si la persona puede abrir ese módulo, y las cifras siguen siendo las de cada quien (un vendedor ve solo lo suyo).
- Pruebas: `tests/contacts-today.php` (5) y `tests/board-links-ui.py`.
