# Plan · Plantillas de WhatsApp por flujos de trabajo (Agora New App)

Estado: **plan, por aprobar** (28 nov 2026). Sustituye a la lectura directa de plantillas (2.25.0–2.26.0): la cuenta no
entrega las plantillas aprobadas por Meta por su API pública (el pedido de plantillas solo devuelve textos rápidos de SMS y correo).

## Idea
Cada plantilla de Meta se envía desde **un flujo de trabajo de la cuenta** («Enviar WhatsApp» con esa plantilla).
Ágora no envía la plantilla: **mete al contacto en el flujo** y la cuenta hace el resto.

Verificado en la documentación local:
- Listar flujos: `GET /workflows/?locationId=…` (permiso `workflows.readonly`): id, nombre, estado (draft/published).
- Meter un contacto: `POST /contacts/{contactId}/workflow/{workflowId}` (permiso `contacts.write`, ya lo tenemos), cuerpo opcional `eventStartTime`.
- Sacarlo: `DELETE` en la misma ruta.

## Qué hace la agencia en la cuenta (una vez por plantilla)
1. Crear un flujo «Ágora · nombre de la plantilla» con la acción «Enviar WhatsApp» → elegir la plantilla aprobada.
2. Activar **«Permitir volver a entrar»** (si no, el segundo envío al mismo contacto se ignora en silencio).
3. Publicarlo. No necesita disparador: Ágora mete al contacto directamente.
4. Dar el permiso `workflows.readonly` al token de la subcuenta.

## Fases

| Fase | Qué | Prueba principal |
|---|---|---|
| **1 · Conectar flujos** | Agencia → subcuenta: la sección de WhatsApp pasa a «Plantillas por flujo». «Cargar flujos» lista los publicados; la administración marca cuáles son plantillas y les pone nombre visible y una vista previa del texto (la cuenta no lo da). Migración idempotente `agora_wa_flujos` (utf8mb4). Se quita la lectura vieja de plantillas | Lista con la cuenta simulada; borradores no se pueden elegir; sin permiso → mensaje claro |
| **2 · Enviar desde la Bandeja** | Botón «Enviar plantilla» en la conversación y en la ficha del contacto: elige la plantilla, confirma y Ágora mete al contacto en el flujo. Con la ventana de 24 h cerrada, el cuadro de texto lo ofrece solo | El contacto entra al flujo correcto; roles sin permiso no ven el botón |
| **3 · Seguimiento** | Registro de cada envío (quién, cuándo, plantilla, contacto). Tras unos segundos Ágora relee la conversación: «Enviada» si aparece el mensaje, «Pedida a la cuenta, aún no aparece» si no. Freno de doble envío (misma plantilla al mismo contacto en 10 min) y tope por minuto | Doble clic no duplica; el estado cambia cuando llega el mensaje |
| **4 · Datos variables** (opcional) | Si la plantilla lleva datos que cambian (monto, fecha de cita), Ágora los escribe antes en campos del contacto y el flujo los usa | El flujo recibe los valores correctos |

Cada fase es una versión con su prueba (`tests/fixes-XYZ.py`), anotada en NOVEDADES, ERRORES y docs/roadmap.
La guía para la agencia (pasos 1 a 4 de arriba) va en el manual y en `INSTALAR-CYBERPANEL.md`.

## Riesgos
- Ágora no ve si Meta rechazó el envío: solo ve si el mensaje aparece en la conversación (fase 3).
- Si alguien despublica o borra el flujo, el envío falla con un aviso claro y la plantilla se marca «revisar».
- Mobile no cambia (se decide y entrega aparte).
