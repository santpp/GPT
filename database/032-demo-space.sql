-- La subcuenta de demostración: una conexión con datos ficticios que se sirven desde Ágora,
-- sin llamar nunca a la cuenta de origen. Sirve para enseñar la app a clientes.
ALTER TABLE ghl_connections ADD COLUMN IF NOT EXISTS is_demo BOOLEAN NOT NULL DEFAULT FALSE;
