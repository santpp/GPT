-- Agora New App 2.23.1 · Base y tablas en utf8mb4. CyberPanel crea la base en latin1 y las primeras tablas no decían su juego de
-- caracteres: quedaban en latin1 (no guardan emojis ni «→»). Al aplicarse esta migración, Installer::utf8mb4() pone la base en
-- utf8mb4 y convierte columna por columna las tablas que no lo estén (respeta las columnas ASCII del motor). Aquí no hay DDL.
DO 0
