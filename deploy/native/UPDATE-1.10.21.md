# Actualizar Ágora a 1.10.21

Botón «Contratos»: la página de contratos de una subcuenta, dentro de Ágora. Incluye 1.10.17 a 1.10.20.

## Pasos

1. Extraer `agora-update-1.10.21.zip` sobre las carpetas existentes.
2. **Hay un campo nuevo** en la base (`contracts_url`, migración `033-contracts-page.sql`). Se crea solo al primer uso.
3. Copiar también el `.htaccess` del paquete (añade la ruta `/contratos`). En OpenLiteSpeed: `systemctl restart lsws`.
4. El pie debe decir **v1.10.21**.
5. Activarlo: Conexiones → la subcuenta → «Contratos» → marcar «Mostrar «Contratos» en esta subcuenta» y la dirección (por defecto contratos.agoradigital.app) → Guardar.

## Qué cambia

- **Contratos en «Más»** (en el ordenador, en la barra lateral) con su ícono propio, solo en la subcuenta que lo tiene activado. **Lo ven todos los de esa subcuenta**: propietaria, administradores y vendedores. A un vendedor, «Más» le aparece cuando tiene algo dentro.
- La página se abre dentro de Ágora (con `?embed=1` para que oculte su propia barra) y con el enlace «Abrir en pantalla completa» de respaldo.
- Para que se vea por dentro, la página de contratos debe permitirlo (cabecera `frame-ancestors` y cookie de sesión `SameSite=None; Secure; Partitioned`). Está en `docs/guia-diseno-contratos.txt`. Hasta entonces, se usa «Abrir en pantalla completa».
- Sin entrada automática: cada persona entra en contratos con su propio acceso de esa página.
- Prueba: `tests/contracts-ui.py`.
