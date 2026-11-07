# Actualizar Ágora a 1.10.2

Nueva pestaña **Consumos** (sólo para tu usuario): lo que gasta cada subcuenta este mes y su plan activo, leído con el token de la agencia.

## Pasos

1. Extraer `agora-update-1.10.2.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.2**.
3. Abrir **Consumos**. En «Conexión de agencia» escribe el **Company ID** (Agency View → Settings → Company) y pega el **token de agencia** (Private Integration con el permiso `saas/company.read`). El token se guarda cifrado en el servidor y no vuelve a mostrarse: sólo sus últimos cuatro caracteres.

## Qué hace

- **Consumos del mes:** subcuenta (o todas), mes y método (POST o GET). Por subcuenta: plan activo, estado y precio, saldo, gasto, recargas, en qué se gasta, día por día y últimos movimientos.
- **Probar y depurar:** cuatro botones —transacciones por **POST**, transacciones por **GET**, saldo y plan de una subcuenta— que enseñan la respuesta tal cual (estado HTTP, cabeceras, campos y cuerpo) con el token tachado. Con eso se ve cuál de las dos formas contesta tu cuenta y cómo se llaman los campos.
- Si la forma elegida responde 404, 405 o 422, el informe prueba la otra automáticamente.
- El gasto se separa de las recargas por el signo del monto y por el tipo; lo que no se entiende se cuenta aparte («sin clasificar») en vez de adivinarlo.
- Lee de 1000 en 1000 movimientos (hasta 20 páginas); el plan y el saldo se guardan una hora.

## Importante

- El listado de transacciones **no está en la documentación local**; lo de «POST con `Version: v3`» viene de una fuente externa. Por eso los botones de prueba: si responde 404 o 403, el informe lo dirá y se ajusta con lo que devuelva.
- La subcuenta se reconoce por el `locationId` de la conexión: sólo salen las conectadas a Ágora; el resto se resume en una línea.
