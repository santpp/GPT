# Actualizar Ágora a 1.10.11

Diseño ordenado y toques de 44 px, aviso para instalar la app en cada visita y manual al día.

## Pasos

1. Extraer `agora-update-1.10.11.zip` sobre las carpetas existentes. No hay migraciones.
2. Subir también el manual nuevo: `manual-vendedores.pdf` a `public_html/assets/manual-vendedores.pdf` (va aparte del paquete).
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.11**.

## Qué cambia

- **Sistema de diseño:** valores con nombre (esquinas `--r-sm/md/lg/pill`, toque mínimo `--tap` de 44 px). Botones de las tarjetas, desplegables, círculo de la cuenta, campana y pestañas pasan a 44 px; las tarjetas usan la misma esquina y el monto sale en rojo Ágora (en oscuro, su tono claro).
- **Instalar la app:** en un teléfono donde Ágora no está instalada, una franja arriba lo pide en cada visita, con **Instalar** (Android) o **Cómo instalarla** (pasos para iPhone y Android). «Ahora no» la calla sólo hasta la próxima visita. No sale en el ordenador ni dentro de la app instalada.
- **Manual de vendedores:** cuatro pantallas (no cinco), buscador por nombre, teléfono o correo, Contactos con total y «Ver más», sección nueva de **Embudos** (desplegables, tarjeta, Conversación, Ficha, Mover a…) con captura nueva, «Mis leads», franja de instalación y una pregunta frecuente. Todas las capturas y el PDF regenerados.
- Prueba nueva: `tests/install-banner-ui.py`.
