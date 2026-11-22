-- Agora New App 2.18.15 · Registro de accesos con código (pedido del usuario): quién pidió o usó un código de 6 dígitos,
-- desde qué IP y navegador y de qué subcuenta. Nunca guarda el código. Lo ven solo los dueños (Agencia, Accesos con código).
CREATE TABLE IF NOT EXISTS agora_access_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  evento VARCHAR(30) NOT NULL,
  uso VARCHAR(10) NOT NULL DEFAULT '',
  user_id BIGINT UNSIGNED NULL,
  correo VARCHAR(190) NOT NULL DEFAULT '',
  tenant_id BIGINT UNSIGNED NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  navegador VARCHAR(160) NOT NULL DEFAULT '',
  KEY creado (created_at),
  KEY por_ip (ip),
  KEY por_usuario (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
