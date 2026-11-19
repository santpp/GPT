# Plan · Widgets editables y widgets personalizados con fórmulas (Agora New App)

Pedido del usuario (19 nov 2026):
- Widgets editables a conveniencia y personalizables, «tan personalizables o más que la cuenta».
- Crear widgets propios con operaciones aritméticas y algebraicas, las que sean.

Estado: **plan, sin código.** Las decisiones marcadas con ❓ son del usuario.

---

## 1. Lo que ya existe (punto de partida)

| Pieza | Qué hace hoy | Límite |
|---|---|---|
| **«Editar Dashboard»** (`tarjEditar`) | En 9 módulos se eligen y ordenan tarjetas de un catálogo fijo: unas 70 en total (Ventas, Gestión Comercial, Cobros, Facturas, Costos, Equipo, Tendencias, Calidad, Inicio). Se guarda «mi vista» o «la del equipo» | No se cambia el período, el filtro, el formato ni el tamaño. No se crean tarjetas nuevas |
| **Indicadores** (Personalizar → Indicadores) | KPI con fuente (ventas, cierres, leads, conversión, costo por lead…), meta, semáforo y una fórmula simple: **A ÷ B** o **A − B** | Solo dos métricas y dos operaciones |
| **Gráficos** (Gestión Comercial) | «Ventas del mes» y «Tendencia del año», que se eligen y ordenan | Tipos y datos fijos |
| **Reporte mensual** | Cifras del archivo importado (ASOCIM) | Esas vistas no tienen «Editar Dashboard» |

Cada catálogo calcula sus cifras en la pantalla, con su propia lógica. Para que los widgets sean libres hace falta una sola fuente de cifras.

## 2. Qué se va a poder hacer

En cualquier módulo con cifras, «Editar Dashboard» abre un **editor de tablero** que permite:

1. **Agregar, quitar, duplicar, ordenar** (arrastrando) y **cambiar el tamaño** de cada widget: chico, ancho o grande.
2. **Configurar cada widget:**
   - **Qué mide:** cualquier métrica del diccionario (punto 3).
   - **Período:** hoy, semana, mes, trimestre, año, últimos N días o un rango de fechas. Puede seguir el mes elegido arriba o tener uno fijo.
   - **Comparar con:** el período anterior, el mismo período del año pasado o la meta. Muestra la flecha y el % de cambio.
   - **Filtros:** vendedor, producto o rubro, forma de cobro, canal, estado, moneda.
   - **Cálculo:** suma, conteo, promedio, mínimo, máximo, mediana o únicos (por ejemplo, clientes distintos).
   - **Formato:** dinero (Bs, US$, €), número, %, decimales, abreviado («12,5 mil»).
   - **Semáforo:** verde, ámbar y rojo según umbrales, y si «más es mejor» o «menos es mejor».
   - **Título, ícono, color y nota** de ayuda.
3. **Tipos de widget:**
   - Cifra.
   - Cifra con comparación.
   - Barra de avance hacia la meta.
   - Gráfico de línea, barras, área o dona.
   - Tabla de los primeros N (por ejemplo, top 5 vendedores).
   - Lista (por ejemplo, cobros que vencen).
4. **Widgets personalizados con fórmula** (punto 4).
5. **Ver el detalle:** al tocar una cifra se ven las filas que la forman, y se pueden descargar.
6. **Vistas:**
   - «Mi tablero» (solo para mí).
   - «Tablero del equipo» (lo publica la administración).
   - **Plantillas** (los dueños pueden publicar una para todas las subcuentas o por rubro, por ejemplo educación).
   - «Restablecer» vuelve a la vista por defecto.

En el teléfono todo se apila en una columna, sin desbordes. Los gráficos siguen yendo **al final del módulo**.

## 3. Diccionario de métricas (la base)

Cada cifra que hoy calcula una tarjeta pasa a ser una **métrica con nombre** que el servidor sabe calcular. Por ejemplo:

```
ventas.vendido      «Vendido»            dinero   suma      filtros: vendedor, producto, forma de cobro, moneda
ventas.cantidad     «Ventas»             número   conteo
cobros.cobrado      «Cobrado»            dinero   suma      filtros: estado, vendedor
facturas.iva        «IVA del mes»        dinero   suma
costos.total        «Costo total»        dinero   suma
cuenta.leads        «Leads»              número   conteo    (de la cuenta conectada)
anuncios.inversion  «Inversión en anuncios» dinero
```

- **El servidor calcula todo.** Así las cifras son las mismas en todos los módulos y respetan los permisos: un vendedor solo suma lo suyo. Ruta: `workspace/metricas` (varias métricas, período y filtros en una sola llamada, con caché de unos segundos).
- **La primera prueba de esta fase es de coincidencia:** cada una de las ~70 tarjetas actuales da exactamente la misma cifra que su métrica.
- **Monedas:** una métrica en dinero no mezcla monedas. Se elige la moneda o se convierte con la tasa BCV, y el widget lo dice.

## 4. Widgets con fórmula (aritmética y álgebra)

### Qué se puede escribir

Una fórmula usa métricas y **variables propias** (constantes con nombre que se editan por mes, como la meta, el sueldo o la comisión). Ejemplos:

```
{Vendido} − {Costo total}                                → Margen
({Vendido} − {Costo total}) ÷ {Vendido} × 100             → Margen %
({Meta} − {Vendido}) ÷ {Ticket promedio}                  → Ventas que faltan para la meta
SI({Cobros vencidos} > 0; {Cobros vencidos} ÷ {A cobrar}; 0)
CRECIMIENTO({Vendido}; {Vendido · mes anterior})          → % de cambio
RAIZ({Ventas} × {Ticket promedio}) · POTENCIA({x}; 2) · REDONDEAR({y}; 2)
```

- **Operadores:** `+ − × ÷`, potencia `^`, módulo, paréntesis sin límite práctico, y comparaciones `> < = ≥ ≤ ≠` con `Y`, `O`, `NO`.
- **Funciones:**
  - Matemáticas: SI, MIN, MAX, ABS, REDONDEAR, TRUNCAR, RAIZ, POTENCIA, LOG, LN, EXP.
  - Estadísticas: PROMEDIO, MEDIANA, DESVIACIÓN.
  - De negocio: CRECIMIENTO (% de cambio), PORCENTAJE, ENTRE.
  - Lista abierta: se agregan funciones sin cambiar el diseño.
- **Una métrica con su período o filtro:** `{Vendido · año pasado}`, `{Ventas · vendedor = Ana}`.
- **Despejar:** con variables propias y álgebra se responden preguntas como «¿cuánto tengo que vender para llegar al margen X?». Más adelante puede llegar un «buscar objetivo» automático (punto 7).

### Cómo se hace seguro y exacto

- **Intérprete propio en el servidor** (PHP): se lee la fórmula, se convierte en un árbol y se calcula. **Nunca se ejecuta texto como código** (nada de `eval`).
- **Límites:** largo máximo de la fórmula y profundidad de paréntesis, para que nadie trabe el servidor.
- **Decimales exactos para el dinero:** no se usan números de coma flotante en los totales.
- **Unidades:** Bs − Bs = Bs; Bs ÷ Bs = número o %; Bs + % es un error que se explica antes de guardar.
- **División entre cero** y datos faltantes muestran «—» con la razón, nunca un error.
- **Editor** con autocompletar de métricas y variables, colores en la fórmula, mensaje de error que señala dónde está el problema y **vista previa con el resultado real** antes de guardar.
- **Una fórmula puede usar otra:** por ejemplo, Margen % usa Margen. Se detectan las vueltas (A usa B y B usa A).

## 5. Dónde se guarda

Tabla nueva `agora_dashboards` (utf8mb4, migración idempotente) con estos campos:
- subcuenta, módulo, alcance (personal, equipo o plantilla), dueño;
- la configuración en JSON;
- número de versión e historial, para deshacer.

Las fórmulas y las variables propias van en `agora_widget_formulas` y `agora_widget_variables` (por subcuenta; las variables, por mes).

Las preferencias actuales («mi vista» y «la del equipo») se convierten solas al formato nuevo: nadie pierde su tablero.

## 6. Fases

| Fase | Qué | Prueba principal |
|---|---|---|
| **0 · Decisiones** | Respuestas del punto 8 | — |
| **1 · Diccionario de métricas** | Las ~70 cifras como métricas del servidor y la ruta `workspace/metricas`. Sin cambios visibles | Cada tarjeta coincide con su métrica, con cada rol |
| **2 · Widgets configurables** | El editor nuevo (agregar, arrastrar, tamaño, período, comparación, filtros, formato, semáforo) en los módulos que hoy tienen «Editar Dashboard». Migración de las preferencias | Teléfono y escritorio, permisos, migración sin pérdidas |
| **3 · Fórmulas** | Intérprete, variables propias y editor con vista previa. Los Indicadores actuales (A ÷ B, A − B) pasan a ser fórmulas | Cientos de fórmulas con resultado conocido, errores claros, inyecciones rechazadas |
| **4 · Gráficos configurables** | Línea, barras, área, dona y tabla top N en el mismo editor; los gráficos fijos pasan a ser widgets | Cifras del gráfico iguales a las del widget |
| **5 · Compartir y detalle** | Tablero del equipo, plantillas de los dueños por rubro, ver el detalle de una cifra y descargarlo | Quién ve y quién edita cada tablero |
| **6 · Módulos del reporte** | «Editar Dashboard» también en las vistas armadas con el reporte mensual (como ASOCIM) | Igual que la fase 2 |

Cada fase se entrega como una versión con sus pruebas y no rompe lo anterior.

## 7. Más adelante (no entra en estas fases)

- «Buscar objetivo»: Ágora despeja la variable que hace falta para llegar a un resultado.
- Alertas cuando un widget cruza un umbral (esto depende de los avisos al teléfono, que están en pausa).
- Widgets con datos de otras subcuentas para la agencia (consolidado).

## 8. Decisiones del usuario ❓

1. **Cuándo y cómo.** La decisión vigente dice «después de la Etapa 1 de la refactorización», y la refactorización quedó para después del lanzamiento.
   - **Recomiendo** construir los widgets **ya, en archivos separados** (`servidor/app/src/Widgets/` y `assets/widgets.js`), tocando `agora-app.js` solo para conectarlos. Así no se agranda el archivo que luego se va a separar, y la refactorización no los afecta.
   - La otra opción es esperar a la refactorización.
2. **Quién crea widgets con fórmula.** Propuesta:
   - Cualquiera crea widgets y fórmulas para **su** tablero.
   - La administración publica el **tablero del equipo** y las fórmulas compartidas.
   - Los dueños publican **plantillas** para todas las subcuentas.
   - La consulta solo mira.
3. **Plantillas por rubro** (educación, servicios…): ¿las quieren desde la fase 5?
4. **Funciones extra:** ¿hace falta alguna además de las del punto 4, como financieras (interés, cuota) o de fechas (días entre fechas)?
5. **Prioridad** frente a lo que está en curso (SERDIMPRE, divisas, pedidos del cliente).

## 9. Riesgos y cuidados

- **Cifras distintas entre módulos:** la fase 1 existe para evitarlo, con pruebas de coincidencia antes de mostrar nada nuevo.
- **Velocidad:** las métricas se piden juntas, en una sola llamada, con caché. Tope de unos 24 widgets por tablero.
- **Fórmulas maliciosas o pesadas:** intérprete propio, límites y sin ejecutar código.
- **Monedas mezcladas:** nunca se suman Bs con US$ sin convertir, y el widget dice con qué tasa convirtió.
- **Complejidad para el usuario:** el editor trae ejemplos («Margen», «Falta para la meta») que se agregan con un toque, y siempre se puede volver a «Restablecer».
