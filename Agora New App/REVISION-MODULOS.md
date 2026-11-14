# Agora New App · revisión módulo por módulo (2.3.3)

Hecha el 14 de noviembre de 2026 sobre la 2.3.3, en local, con la subcuenta de prueba ASOCIM conectada a una cuenta simulada. Se abrieron las 27 pantallas de subcuenta como administradora (escritorio y teléfono) y como ejecutivo (teléfono), y las 8 de agencia como dueña.

**Resultado técnico:** ninguna pantalla dio error de programa ni se desbordó a lo ancho. Lo que sigue son problemas de diseño, de función y de redundancia. No se corrigió nada en esta revisión: es la lista para decidir.

**Cómo leerla:** «Visto» = lo vi en pantalla. «Según el código» = lo leí en el programa pero no lo probé con la cuenta real.

## Lo más importante (resumen)

| N.º | Problema | Tipo | Gravedad |
|---|---|---|---|
| A | «Agregar manual» y «Excel» en Contactos y Oportunidades guardan en la base de Ágora, no en la cuenta. Con la subcuenta conectada, la lista se lee de la cuenta: lo agregado a mano no aparece. | Función | Alta |
| B | Hay dos mundos de datos mezclados sin avisar: lo que viene en vivo de la cuenta (Bandeja, Contactos, Oportunidades, ARP Comercial) y lo que viene del reporte mensual importado (Gestión Comercial, Equipo, Ventas, Facturación, Costos, Tendencias). Los números de uno y otro no coinciden ni tienen por qué. | Función | Alta |
| C | El cruce entre la persona de Ágora y su usuario en la cuenta es solo por correo y es invisible: si no coincide, el ejecutivo no ve nada y nadie le dice por qué. | Función | Alta |
| D | Al ejecutivo se le muestran 7 módulos que su rol no puede abrir. Entra y ve «No se pudo cargar · Tu rol no permite esta función · Intentar de nuevo». | Diseño | Alta |
| E | Tres tableros para lo mismo: Inicio (Tu tablero), Gestión Comercial y ARP Comercial. | Redundancia | Media |
| F | Dos formas de importar datos: Ajustes → «Datos reales» (JSON) y Migración de datos (Excel). | Redundancia | Media |
| G | Textos viejos o técnicos a la vista: Ajustes nombra al proveedor y dice que la sincronización «aún no está habilitada»; Ayuda dice «Versión 1.4.3» y habla de «la demo»; Auditoría y Registro muestran códigos (`subcuenta.cobros`, `invoice`, `report`, `sesion`). | Diseño | Media |

## Menú y navegación

- **Tres caminos al mismo sitio.** En escritorio «Inicio» está en la barra de arriba, en el menú de la izquierda y en el logo. «Auditoría» está arriba y dentro de Administración. (Visto)
- **El menú resalta otro nombre.** Al abrir Catálogo, el menú resalta «Productos y servicios»; son dos pestañas de la misma pantalla con dos direcciones distintas. (Visto)
- **«Con convenio» se titula «Contactos».** Ya existe un módulo Contactos en Clientes y atención; el título confunde. (Visto)
- **El selector de mes aparece y desaparece.** Está en los módulos del reporte y no en los que leen en vivo. Es correcto, pero nada lo explica. (Visto)
- **El pie de página dice «Contratos · EJECUTIVO · Ágora · equipo».** Texto interno, con el rol en mayúsculas. (Visto)
- **Teléfono:** la barra inferior (Inicio, Módulos, Buscar, Cuenta) funciona. Para el ejecutivo sería más útil que «Inicio» lo lleve a la Bandeja.

## Módulo por módulo

### General

**Inicio (centro)**
- Diseño: si la subcuenta no tiene logo, el encabezado muestra el logo de Ágora dos veces. (Visto)
- Función: «Tu tablero» lee en vivo (conversaciones, oportunidades, ganadas, tareas). «Indicadores clave» sale vacío («—», «Sin meta») hasta que alguien configure metas; ocupa media pantalla sin decir nada. (Visto)
- Redundancia: repite cifras de ARP Comercial.

### Ventas y facturación

**Gestión Comercial**
- Función: todo sale del reporte mensual importado. En una subcuenta nueva conectada estará vacío hasta importar un reporte. (Según el código)
- Diseño: el aviso «¿Cómo corrijo estos datos?» se repite en 8 pantallas y ocupa la primera fila. Mejor un enlace pequeño. (Visto)

**ARP Comercial**
- Función: es el tablero en vivo y el que respeta «lo mío» del ejecutivo. Funciona. (Visto)
- Diseño: el nombre «ARP Comercial» no dice qué es; el título de la pantalla es «Así va tu cuenta». En teléfono mide casi 7.000 píxeles de alto.
- Redundancia: con Inicio y Gestión Comercial.

**Ventas**
- Función: dos pestañas con dos orígenes, «Ventas registradas» (lo ganado en Oportunidades) y «Reporte del mes» (hojas importadas). Al ejecutivo le abre «Ventas registradas» con «Agregar manual», y además intenta leer el reporte y recibe un rechazo que no ve. (Visto)
- Diseño: tabla ancha; en teléfono hay que deslizar mucho a los lados.

**Facturación**
- Función: solo lectura del libro importado. No factura. El nombre promete más de lo que hace. (Visto)

**Productos y servicios / Catálogo**
- Redundancia: «Catálogo» (del reporte, solo lectura) y «Productos y servicios» (editable) muestran casi lo mismo. Sobra una vez que se usa «Traer del Catálogo». (Visto)
- Diseño: botones «Nuevo producto» y «Agregar manual» juntos, que hacen lo mismo.

### Clientes y atención

**Bandeja de conversaciones**
- Función: en vivo. Corregida en 2.3.3 (ficha, buscador). Es la versión anterior de la de Ágora Mobile: le falta la búsqueda por teléfono y correo en el servidor, la línea de «asignada a» y los cambios de diseño de la 1.10.x. (Según el código)
- Diseño: en teléfono el título y la descripción ocupan un tercio de la pantalla antes de la lista. En Ágora Mobile se ocultan. (Visto)

**Contactos**
- Función: problema A. «Agregar manual» y «Excel» no escriben en la cuenta. (Según el código)
- Diseño: mucha pantalla vacía en escritorio; el buscador es angosto. (Visto)

**Oportunidades**
- Función: en vivo, con Embudos y Ganadas. Mismo problema A con «Agregar manual». (Según el código)
- Diseño: en teléfono las columnas se cortan a la derecha sin señal de que se deslizan. En Ágora Mobile ya se resolvió con el selector de etapa. (Visto)

**Tareas**
- Función: son tareas de Ágora, no las de la cuenta. La ficha del contacto en la Bandeja muestra las tareas de la cuenta. Son dos listas distintas con el mismo nombre. (Según el código)
- Redundancia: «Nueva tarea» y «Agregar manual» juntos. (Visto)
- Diseño: la columna «Contacto» sale vacía («—») y el contacto va escrito debajo del título. (Visto)

### Gestión y resultados

**Equipo, Embudo y publicidad, Costos, Tendencias, Calidad de datos**
- Función: los cinco dependen del reporte mensual importado, con columnas pensadas para ASOCIM («asesora», «inscritos», «1.ª cuota», «Meta ADS»). Para otra empresa no sirven tal cual. (Visto)
- Función: «Embudo y publicidad» no lee los anuncios de la cuenta; «Costo por contacto» dice «sin datos». (Visto)
- Diseño: «Calidad de datos» habla de celdas de Excel («4.544 celdas incorporadas»). Es una herramienta de quien importa, no del cliente; debería ir en Administración.
- Redundancia: «Equipo» (rendimiento por asesora) y «Usuarios y roles» se confunden por el nombre.

### Contratos

**Contratos, Con convenio, Registro**
- Función: funcionan con datos de Ágora. La firma por enlace sigue apagada en `config.php`. (Según el código)
- Diseño: «Registro» muestra la acción en inglés y en crudo («agencia», «invoice», «report») y «Sin nombre» en rojo en casi todas las filas. (Visto)
- Redundancia: «Registro» (de convenios) y «Auditoría» (de todo) son dos bitácoras. La segunda ya incluye a la primera.

### Administración

**Auditoría**
- Diseño: la columna «Qué hizo» muestra códigos (`subcuenta.cobros`, `facturas.importadas`, `creo`). Ya existe la tabla de textos legibles; aquí no se usa. (Visto)

**Configuración mensual**
- Función: metas y umbrales del reporte. Solo sirve si se usa el reporte importado. (Visto)

**Usuarios y roles**
- Función: problema C. La columna «De dónde» dice «Del sistema» para todos; no dice si la persona está enlazada con su usuario de la cuenta. (Visto)
- Diseño: la tarjeta «SuperAdmin 0» sobra dentro de una subcuenta.

**Ajustes**
- Diseño: textos viejos (problema G). «Datos reales» con un selector de archivo sin estilo. (Visto)
- Redundancia: importador JSON frente a Migración de datos (problema F). Botones «Administrar usuarios» y «Mi perfil» que ya están en el menú.

**Personalizar**
- Función: permite editar embudos y etapas propios de Ágora («Ventas · 5 etapas · 60 oportunidades»). Con la subcuenta conectada, los embudos reales son los de la cuenta. Hay dos juegos de embudos. (Visto)

**Ayuda**
- Diseño: dice «Versión 1.4.3» y «El HTML de prueba contiene datos ficticios». Hay que reescribirla. (Visto)

**Migración de datos**
- Función: plantillas de Excel por módulo. Carga en la base de Ágora (ver problema A). (Según el código)

**Mi perfil**
- Bien. El enlace «Volver al inicio» sobra: ya está el menú.

### Agencia (solo dueños)

**Subcuentas:** bien tras la 2.3.1. La guía de bienvenida se abre sola y tapa los botones hasta cerrarla. (Visto)

**Centro de mando (Resumen):** útil. «Tareas de agencia» propone «Anotar cobro» para subcuentas de prueba. «Seguridad de la agencia» lista «entro» (sin tilde) por cada inicio de sesión. (Visto)

**Gestión de usuarios:** bien. Es la única pantalla que crea usuarios de cualquier subcuenta; duplica «Usuarios y roles» de cada subcuenta.

**Registro de actividad:** la tabla muestra `sesion`, `[]` y «Estado: →» vacío en cada inicio de sesión. Los inicios de sesión deberían ir aparte o resumidos. Se desborda a la derecha (la columna IP se corta). (Visto)

**Consumos:** funciona con el token de agencia. (Visto contra la cuenta simulada)

**Estudio:** dice «Publicación al servidor y conexión IA pendientes de integración» y guarda en el navegador. No está terminado; conviene ocultarlo. (Visto)

## Lo que ve un ejecutivo

- Inicio le dice «24 herramientas, 17 habilitadas para tu rol», pero el menú le muestra las 24.
- Gestión Comercial, Equipo, Embudo y publicidad, Facturación, Costos, Catálogo, Tendencias y Calidad de datos: «No se pudo cargar. Tu rol no permite esta función», con un botón «Intentar de nuevo» que nunca va a funcionar.
- Auditoría, Configuración, Usuarios, Ajustes, Personalizar y Migración: pantalla de «sin acceso», correcta, pero siguen en el menú.
- Lo que sí usa: Bandeja, Contactos, Oportunidades, Tareas, ARP Comercial, Ventas registradas, Contratos. Son 7 de 24.

**Propuesta:** el menú muestra solo lo que el rol puede abrir.

## Cómo se cruza hoy la persona de Ágora con su usuario en la cuenta

1. Al guardar o probar la conexión de la subcuenta, Ágora copia la lista de usuarios de esa cuenta (nombre, correo, rol y permisos).
2. Cuando alguien abre la Bandeja, Contactos u Oportunidades, Ágora busca en esa lista un usuario activo con **el mismo correo** con el que la persona entra a Ágora (sin distinguir mayúsculas).
3. Si lo encuentra y la persona tiene rol restringido (ejecutivo), ve solo lo asignado a ese usuario, con los permisos que tenga en la cuenta.
4. Si no lo encuentra y es ejecutivo, no ve nada y no puede escribir. No hay aviso.
5. Administradores y supervisores ven toda la subcuenta aunque no tengan usuario en la cuenta.

**Debilidades:**
- Es invisible: en «Usuarios y roles» no se sabe quién está enlazado.
- Un correo distinto (alias, otro dominio, un error al escribir) rompe el cruce en silencio.
- Los nombres pueden no coincidir (en la prueba, «Paola González» en Ágora es «Paola Rojas» en la cuenta) y nada lo señala.
- Un usuario nuevo en la cuenta no existe para Ágora hasta volver a sincronizar.

**Propuesta: enlace explícito, con el correo como sugerencia**
1. En «Usuarios y roles», columna nueva «Usuario en la cuenta»: «Enlazado con Paola Rojas» o «Sin enlazar».
2. Al crear o editar una persona, un selector con los usuarios de la cuenta. Ágora preselecciona el que tenga el mismo correo; el administrador puede elegir otro.
3. Botón «Traer del equipo de la cuenta»: crea en Ágora las personas que existen en la cuenta, ya enlazadas, y solo falta darles rol y contraseña.
4. El enlace se guarda por subcuenta (id del usuario de la cuenta), así el correo de Ágora puede ser distinto.
5. Si un ejecutivo entra sin enlace, ve un aviso: «Tu usuario no está enlazado con la cuenta. Pídeselo a tu administrador».
6. La lista de usuarios de la cuenta se refresca sola una vez al día y con un botón «Actualizar equipo».

## Orden sugerido de trabajo

1. Enlace explícito de usuarios (problema C).
2. Menú por rol (problema D).
3. «Agregar manual» y «Excel» escriben en la cuenta cuando la subcuenta está conectada, o se ocultan (problema A).
4. Bandeja, Contactos y Oportunidades al día con Ágora Mobile 1.10.14.
5. Textos: Ajustes, Ayuda, Auditoría, Registro, pie de página (problema G).
6. Decidir qué se hace con los módulos del reporte mensual (problema B): dejarlos solo para ASOCIM, o esconderlos por tipo de cuenta.
7. Quitar duplicados: un solo tablero de inicio, un solo importador, Catálogo dentro de Productos, Estudio oculto (problemas E y F).
