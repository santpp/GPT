# Actualizar Ágora a 1.10.0

Pantalla de mantenimiento, modo «Texto grande», encabezado alineado y varias correcciones.

## Pasos

1. Extraer `agora-update-1.10.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. **Volver a subir el PDF** `releases/manual-vendedores.pdf` a `public_html/assets/manual-vendedores.pdf` (ahora incluye el texto grande y el perfil). Es opcional hasta que quieras que el PDF esté al día.
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.0**.

## Qué cambia

- **Pantalla de mantenimiento:** «Oops! Algo ocurre. El servidor puede estar tomando una siesta o dándose un masaje de mantenimiento. Contacta a Ágora para más información». Aparece sola cuando el servidor no responde o cuando el propietario pone Ágora en pausa, y se quita sola al volver (cada 15 segundos, o con «Reintentar»). Un fallo suelto mientras el servidor responde no la muestra.
- **Pausa del propietario:** en «Mi cuenta» (solo la cuenta propietaria) hay un interruptor «Poner Ágora en mantenimiento». Mientras esté activo, el equipo ve la pantalla y el propietario sigue entrando con normalidad.
- **Texto grande:** en «Mi cuenta» hay una casilla que agranda la letra (15 %) y la pone toda en negrita. Se guarda en cada dispositivo. Está explicado en el manual y en el tutorial (paso «Tu perfil»).
- **Encabezado:** el logo, el título de la pantalla y la cuenta quedan en la misma línea; antes el título quedaba más abajo.
- **Corrección importante:** recargar la página del manual (`/ayuda`) daba error 404 en el servidor real porque faltaba en `.htaccess`. Ya está, y una prueba nueva (`tests/routes.php`) vigila que no vuelva a pasar con ninguna pantalla.
- **Pruebas:** `native-delivery` usaba por defecto un paquete antiguo (0.8.0) y fallaba sin razón; ahora usa el paquete más reciente. `announce-ui` no volvió a fallar en 6 corridas seguidas.

## Revisión de seguridad (barrido dirigido)

Revisado: consultas SQL (todas con parámetros; los nombres de tabla del respaldo salen de la propia base), HTML construido en el navegador (los datos van escapados), cookies de sesión (HttpOnly, Secure fuera de desarrollo, SameSite Strict), origen y CSRF en toda escritura, y ausencia de claves o secretos en el repositorio. Pendiente de decidir (no se cambió por no poder probarlo en el servidor real): cabeceras `X-Frame-Options` y `Content-Security-Policy` en el servidor nativo; hoy sólo existen en la configuración de Caddy.
