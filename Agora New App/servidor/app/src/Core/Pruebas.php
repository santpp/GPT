<?php
declare(strict_types=1);
namespace Agora\Core;
/*
 * MODO PRUEBAS (2.1.14). Se activa solo cuando NO existe servidor/config/config.php.
 * - Base SQLite en servidor/storage/agora-pruebas.sqlite: no hay que crear ninguna base de datos.
 * - Al primer uso crea el esquema, el usuario admin (clave 123456789) y todos los datos sintéticos.
 * - La clave interna (app_key) se genera sola y se guarda en servidor/storage/.
 * Para pasar a producción: crear config.php con MySQL (ver INSTALAR-CYBERPANEL.md); el modo pruebas se apaga solo.
 */
final class Pruebas {
 public const ESQUEMA = 'sqlite-esquema-1';
 public const ADMIN = 'admin@agora.test';
 public const CLAVE = '123456789';
 public static function storage(): string { return dirname(__DIR__, 3) . '/storage'; }
 public static function config(): array {
  $dir = self::storage();
  if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $keyFile = $dir . '/pruebas-app-key';
  if (!is_file($keyFile)) { @file_put_contents($keyFile, bin2hex(random_bytes(32)), LOCK_EX); @chmod($keyFile, 0600); }
  $key = trim((string)@file_get_contents($keyFile));
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  $host = preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/D', (string)($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'localhost';
  return [
   'env' => 'pruebas', 'modo_pruebas' => true,
   'app_url' => ($https ? 'https' : 'http') . '://' . $host,
   'app_key' => $key,
   'db' => ['driver' => 'sqlite', 'path' => $dir . '/agora-pruebas.sqlite'],
   'install_token' => '', 'session_seconds' => 28800, 'remember_seconds' => 604800,
   'cookie_secure' => $https, 'page_size' => 100, 'ghl' => ['tenants' => []],
   'signing_enabled' => true,
  ];
 }
 /** Crea el esquema, el admin y los datos sintéticos la primera vez. Seguro ante peticiones simultáneas. */
 public static function preparar(DB $db, array $config): void {
  if (!$db->sqlite() || self::lista($db)) return;
  $lock = fopen(self::storage() . '/pruebas.lock', 'c');
  if ($lock) flock($lock, LOCK_EX);
  try {
   if (self::lista($db)) return;
   Installer::migrate($db);
   if (!$db->one("SELECT id FROM agora_users WHERE role='MASTER'")) {
    $db->run("INSERT INTO agora_users(name,email,password_hash,role) VALUES(?,?,?,'MASTER')", ['Administrador de pruebas', self::ADMIN, password_hash(self::CLAVE, PASSWORD_DEFAULT)]);
   }
   if (!$db->one('SELECT id FROM agora_tenants LIMIT 1')) Sinteticos::sembrar($db, $config, self::CLAVE);
   $db->saveSetting(0, 'pruebas.listo', ['fecha' => gmdate('Y-m-d H:i:s')]);
  } finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
 }
 private static function lista(DB $db): bool {
  try { return (bool)$db->one("SELECT 1 ok FROM agora_settings WHERE tenant_id=0 AND setting_key='pruebas.listo'"); }
  catch (\PDOException) { return false; }
 }
 /** Reinicia el ambiente de pruebas: borra el archivo SQLite; se vuelve a crear solo en la próxima visita. */
 public static function reiniciar(): void {
  foreach (['agora-pruebas.sqlite', 'agora-pruebas.sqlite-wal', 'agora-pruebas.sqlite-shm'] as $f) @unlink(self::storage() . '/' . $f);
 }
}
