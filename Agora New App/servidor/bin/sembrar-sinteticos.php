<?php
/*
 * Ágora · Datos sintéticos (solo PRUEBAS, no producción).
 *
 * En el modo pruebas (sin config.php, base SQLite) NO hace falta: los datos se cargan solos al primer arranque.
 * Este comando sirve para cargarlos o borrarlos a mano en un servidor de pruebas con MySQL.
 *
 * Uso (AlmaLinux 9 + CyberPanel):
 *   /usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --confirmo-pruebas [--clave=...]   (por defecto: 123456789 en modo SQLite, Agora-Pruebas-2026! con MySQL)
 *   /usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --borrar --confirmo-pruebas
 *   /usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --reiniciar-pruebas   (solo modo SQLite: borra la base y la vuelve a crear)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

use Agora\Core\{DB, Pruebas, Sinteticos};

$args = array_slice($argv, 1);
$opt = static function (string $n) use ($args): ?string { foreach ($args as $a) if (str_starts_with($a, "--$n=")) return substr($a, strlen($n) + 3); return null; };
$flag = static fn(string $n): bool => in_array("--$n", $args, true);
$say = static function (string $t): void { fwrite(STDOUT, $t . PHP_EOL); };

try {
 if ($flag('reiniciar-pruebas')) {
  if (!defined('AGORA_MODO_PRUEBAS')) throw new RuntimeException('Solo funciona en el modo pruebas (sin config.php).');
  Pruebas::reiniciar();
  $db = new DB($config['db']); Pruebas::preparar($db, $config);
  $say('Base de pruebas creada de nuevo. Usuario admin · clave ' . Pruebas::CLAVE);
  exit(0);
 }
 if (!$flag('confirmo-pruebas')) { fwrite(STDERR, "Este comando es solo para pruebas. Agrega --confirmo-pruebas para continuar.\n"); exit(2); }
 $db = new DB($config['db']);
 if ($flag('borrar')) { $n = Sinteticos::borrar($db); $say("Subcuentas sintéticas borradas: $n."); exit(0); }
 $clave = $opt('clave') ?? (defined('AGORA_MODO_PRUEBAS') ? Pruebas::CLAVE : 'Agora-Pruebas-2026!'); // con MySQL la clave mínima es de 12 caracteres
 $res = Sinteticos::sembrar($db, $config, $clave, $say);
 $say('');
 foreach ($res as $e) { $say($e['empresa'] . ':'); foreach ($e['equipo'] as $u) $say('  ' . str_pad($u['rol'], 12) . $u['correo']); }
 $say("Clave de todos: $clave");
} catch (Throwable $e) { fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL); exit(1); }
