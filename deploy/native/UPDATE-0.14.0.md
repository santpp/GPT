# Actualizar Ágora a 0.14.0

Corrige los campos personalizados al editar, añade el modo ampliado de la conversación, pone los iconos de cada canal y retira el botón de llamar.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.14.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.

## Campos personalizados: corregido

En 0.13.0 el formulario recibía las definiciones de campos **sólo si estaban en la caché del servidor**. Con la caché fría o caducada llegaban vacías y, como la cuenta sólo devuelve los campos que tienen valor, el formulario se quedaba sin ninguno. Era exactamente el fallo que esa versión pretendía arreglar.

Ahora, si faltan, se piden en ese momento: una consulta por hora como mucho. Además, una caché guardada por versiones anteriores a 0.13.0 se considera caducada, así que al actualizar se vuelve a pedir en lugar de mostrar todos los campos como texto plano. Si tu cuenta define campos personalizados, aparecen **todos** al editar, también los vacíos, cada uno con el control que le corresponde.

## Modo ampliado de la conversación

En la cabecera del chat, junto a la ⓘ, aparece **⤢**. Oculta el menú, la lista de chats y la cabecera de la página, de modo que el historial ocupa toda la pantalla. Funciona igual en escritorio, en el teléfono y con el teléfono apaisado. Se sale con el mismo botón (**⤡**) y la preferencia se recuerda en ese navegador. Al volver a la lista de conversaciones se sale solo.

Con el teléfono apaisado, además, se recortan la cabecera y el pie aunque no estés en modo ampliado, porque ahí la altura es lo que escasea.

## Iconos de canal

El selector de canal muestra ahora todos los canales con su icono: WhatsApp, SMS, Instagram, Messenger y correo. Los que ese contacto **no** puede recibir aparecen atenuados y no se pueden elegir; al mantener el dedo o el cursor encima dicen por qué: falta teléfono, falta correo, o es un canal al que sólo se puede responder si el contacto escribió primero. El canal elegido se tiñe del color del servicio.

Los iconos son dibujos propios, no los archivos de marca de cada servicio.

## El botón de llamar ya no está

Retirado de la ficha, como pediste. El teléfono se sigue viendo y se sigue pudiendo editar; lo que desaparece es la acción de marcar. El enlace para escribir por correo se mantiene.

## Diagnóstico

Sin códigos nuevos. Si el selector de canal muestra todo atenuado salvo uno, es que la ficha del contacto no tiene teléfono ni correo: rellénalos desde **Editar** y los canales se abren.
