# Actualizar Ágora a 1.10.1

Texto más grande y negro, y el canal de origen a la vista. Salió de las quejas de los vendedores.

## Pasos

1. Extraer `agora-update-1.10.1.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.1**.
3. Opcional: volver a subir `releases/manual-vendedores.pdf` a `public_html/assets/manual-vendedores.pdf` (capturas con el nuevo tamaño de letra).

## Qué cambia

- **Texto más grande en toda la app** (+20 %) y **negro**: ninguna letra baja de 11,5 px, y los grises verdosos que costaban leer pasan a un gris oscuro. El tipo de letra y la estética no cambian.
- **Mensajes y cuadro de respuesta** a 17 px y en negro (antes 13–14 px). El modo «Texto grande» de «Mi cuenta» sube aún más (20 px) y pone todo en negrita.
- **Canal de origen a la vista:** cada conversación de la lista y el encabezado del chat muestran el canal con su marca y su color: WhatsApp verde, Instagram rosa, Facebook (Messenger) azul; SMS y correo en negro.
- **Corrección:** al abrir una conversación con el cuadro de respuesta alto, a veces la lista de mensajes no quedaba en el último mensaje. Ya queda siempre al final.
- Pruebas nuevas: `tests/readability-ui.py`.
