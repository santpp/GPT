# Actualizar Ágora a 1.10.8

Las listas de cada vendedor son de verdad las suyas, con su total real.

## Pasos

1. Extraer `agora-update-1.10.8.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.8**.

## Qué cambia

- **Contactos (vendedores):** antes se pedían los 50 más recientes de toda la subcuenta y se quedaban los del vendedor (por eso salían, por ejemplo, 18). Ahora se piden a la cuenta ya filtrados por su usuario: «120 contactos asignados a ti · se muestran 50, los más recientes primero», con **Ver más**. El buscador busca dentro de los suyos.
- Si la cuenta no aceptara ese filtro, Ágora vuelve a lo de antes y lo dice: «18 contactos tuyos entre los más recientes de la cuenta». Nunca se muestran contactos de otra persona.
- **Oportunidades y tablero (vendedores):** las oportunidades abiertas también se piden ya filtradas por su usuario, en lugar de buscarlas entre las 100 más recientes de la cuenta.
- Conversaciones ya se pedían filtradas; no cambia.
- Pruebas: `tests/directories.php` y `tests/dashboard.php` ampliadas; nueva `tests/contacts-more-ui.py`.
