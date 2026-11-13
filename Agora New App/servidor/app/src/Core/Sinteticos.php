<?php
declare(strict_types=1);
namespace Agora\Core;
use Agora\Controllers\Api;
/*
 * Datos sintéticos de Ágora (solo pruebas). Crea ASOCIM y StreamingNet con equipo, contactos, embudo,
 * oportunidades, tareas, convenios, reportes, facturas, cobros y fecha de corte. Todo pasa por la API
 * (mismas validaciones y auditoría que la interfaz). Lo usan el modo pruebas (automático) y
 * servidor/bin/sembrar-sinteticos.php (manual).
 */
final class Sinteticos {
 public const CODIGOS = ['SIN-ASO', 'SIN-STR'];

 /** @return list<array{empresa:string,equipo:list<array{rol:string,correo:string,nombre:string}>}> */
 public static function sembrar(DB $db, array $config, string $clave, ?callable $say = null): array {
  $say ??= static function (string $t): void {};
  $dir = dirname(__DIR__, 3) . '/datos-sinteticos';
  $master = $db->one("SELECT * FROM agora_users WHERE role='MASTER' AND active=1 ORDER BY id LIMIT 1");
  if (!$master) throw new \RuntimeException('No hay dueño MASTER. Instala primero.');
  if ($db->one('SELECT id FROM agora_tenants WHERE code IN (?,?) LIMIT 1', self::CODIGOS)) throw new \RuntimeException('Los datos sintéticos ya existen.');
  $api = new Api($db, $config);
  $api->a->user = $master;
  $_SERVER['REMOTE_ADDR'] ??= 'CLI';
  $getAntes = $_GET; $wsAntes = $_SERVER['HTTP_X_WORKSPACE_ID'] ?? null;
  mt_srand(2026);
  $call = function (string $path, string $method, array $body = [], ?int $tenant = null) use ($api, $db) {
   $_GET = [];
   if ($tenant) $_SERVER['HTTP_X_WORKSPACE_ID'] = (string)$tenant; else unset($_SERVER['HTTP_X_WORKSPACE_ID']);
   try { return $db->transaction(fn() => $api->dispatch($path, $method, $body)); }
   catch (HttpError $e) { throw new \RuntimeException("$method $path: " . $e->getMessage()); }
  };
  $pick = static fn(array $a) => $a[mt_rand(0, count($a) - 1)];
  $hoy = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
  $dia = static fn(int $d) => $hoy->modify(($d >= 0 ? '+' : '') . $d . ' days')->format('Y-m-d');
  $NOMBRES = ['María', 'José', 'Andreína', 'Luis', 'Carla', 'Gabriel', 'Valentina', 'Daniel', 'Rosa', 'Miguel', 'Fabiola', 'Jesús', 'Paola', 'Ricardo', 'Yuliana', 'Óscar', 'Mariana', 'Héctor', 'Daniela', 'Javier'];
  $APELLIDOS = ['Pérez', 'González', 'Rodríguez', 'Hernández', 'Martínez', 'Ramírez', 'Castillo', 'Montilla', 'Díaz', 'Morales', 'Rojas', 'Mendoza', 'Suárez', 'Carvajal', 'Peña'];
  $EMPRESAS = [
   ['codigo' => 'SIN-ASO', 'nombre' => 'ASOCIM', 'subcuenta' => 'ASOCIM · Admisiones', 'rubro' => 'Educación', 'slug' => 'asocim',
    'plan' => 'Operación anual', 'monto' => '285', 'vence' => $dia(120), 'servicios' => [['nombre' => 'Gestión de pauta', 'monto' => '600', 'frecuencia' => 'anual', 'activo' => true], ['nombre' => 'Landing de admisiones', 'monto' => '350', 'frecuencia' => 'unico', 'activo' => true]],
    'productos' => ['Diplomado en Gerencia', 'Curso de Inglés', 'Técnico en Administración', 'Programa de Contaduría'], 'etapas' => ['Nuevo', 'Contactado', 'Entrevista', 'Inscripción', 'Pago'],
    'perfil' => ['tipoCliente' => 'servicios', 'negocio' => 'servicios', 'cobro' => 'recurrente', 'frecuencia' => 'mensual', 'diaCobro' => 5, 'factura' => true],
    'catalogo' => ['rubros' => [['id' => 'r-dip', 'nombre' => 'Diplomados'], ['id' => 'r-cur', 'nombre' => 'Cursos cortos'], ['id' => 'r-tra', 'nombre' => 'Trámites']],
     'productos' => [['id' => 'p-1', 'rubroId' => 'r-dip', 'nombre' => 'Diplomado en Gerencia', 'codigo' => 'DIP-GER', 'cobro' => 'recurrente', 'precio' => 120, 'frecuencia' => 'mensual', 'duracion' => 6],
      ['id' => 'p-2', 'rubroId' => 'r-cur', 'nombre' => 'Curso de Inglés', 'codigo' => 'CUR-ING', 'cobro' => 'unico', 'precio' => 80],
      ['id' => 'p-3', 'rubroId' => 'r-tra', 'nombre' => 'Constancia de estudios', 'codigo' => 'CON-EST', 'tipo' => 'producto', 'cobro' => 'venta', 'precio' => 15]]]],
   ['codigo' => 'SIN-STR', 'nombre' => 'StreamingNet', 'subcuenta' => 'StreamingNet · Ventas', 'rubro' => 'Telecomunicaciones / ISP', 'slug' => 'streamingnet',
    'plan' => 'Comercial mensual', 'monto' => '180', 'vence' => $dia(5), 'servicios' => [['nombre' => 'Bot de WhatsApp', 'monto' => '45', 'frecuencia' => 'mensual', 'activo' => true]],
    'productos' => ['Fibra 100 Mbps', 'Fibra 300 Mbps', 'Plan Empresa', 'TV + Internet'], 'etapas' => ['Nuevo', 'Factibilidad', 'Propuesta', 'Instalación', 'Activo'],
    'perfil' => ['tipoCliente' => 'producto', 'negocio' => 'suscripciones', 'cobro' => 'recurrente', 'frecuencia' => 'mensual', 'diaCobro' => 1, 'factura' => true],
    'catalogo' => ['rubros' => [['id' => 'r-pla', 'nombre' => 'Planes de internet'], ['id' => 'r-equ', 'nombre' => 'Equipos'], ['id' => 'r-ser', 'nombre' => 'Servicios técnicos']],
     'productos' => [['id' => 'p-1', 'rubroId' => 'r-pla', 'nombre' => 'Fibra 100 Mbps', 'codigo' => 'FIB-100', 'cobro' => 'recurrente', 'precio' => 25, 'frecuencia' => 'mensual'],
      ['id' => 'p-2', 'rubroId' => 'r-pla', 'nombre' => 'Fibra 300 Mbps', 'codigo' => 'FIB-300', 'cobro' => 'recurrente', 'precio' => 40, 'frecuencia' => 'mensual'],
      ['id' => 'p-3', 'rubroId' => 'r-ser', 'nombre' => 'Instalación', 'codigo' => 'INST', 'cobro' => 'unico', 'precio' => 50],
      ['id' => 'p-4', 'rubroId' => 'r-equ', 'nombre' => 'Router WiFi 6', 'codigo' => 'RTR-W6', 'tipo' => 'producto', 'cobro' => 'venta', 'precio' => 60, 'stock' => 12]]]],
  ];
  $reporte = json_decode((string)@file_get_contents("$dir/reporte-ejemplo.json"), true);
  $facturas = json_decode((string)@file_get_contents("$dir/facturas-ejemplo.json"), true);
  $resumen = [];
  try {
   foreach ($EMPRESAS as $e) {
    $say("→ {$e['nombre']}");
    $t = (int)$call('agency/accounts', 'POST', ['nombre' => $e['nombre'], 'subcuenta' => $e['subcuenta'], 'codigo' => $e['codigo'], 'rubro' => $e['rubro'], 'moneda' => 'USD', 'estado' => 'activa', 'plan' => $e['plan'], 'vence' => $e['vence'], 'notas' => 'Datos sintéticos para pruebas. No es un cliente real.', 'perfil' => $e['perfil']])['id'];
    $call('agency/billing', 'PUT', ['id' => $t, 'nombrePlan' => $e['plan'], 'plan' => ['monto' => $e['monto'], 'frecuencia' => 'mensual'], 'servicios' => $e['servicios'], 'corte' => $e['vence']]);

    $equipo = [];
    foreach ([['ADMIN', 'admin'], ['SUPERVISOR', 'supervisor'], ['EJECUTIVO', 'ejecutivo1'], ['EJECUTIVO', 'ejecutivo2'], ['EJECUTIVO', 'ejecutivo3'], ['CONSULTA', 'consulta']] as $i => [$rol, $alias]) {
     $nombre = $NOMBRES[($i * 3 + strlen($e['slug'])) % count($NOMBRES)] . ' ' . $APELLIDOS[($i * 5 + strlen($e['slug'])) % count($APELLIDOS)];
     $correo = "$alias.{$e['slug']}@agora.test";
     $id = (int)$call('workspace/usuarios', 'POST', ['nombre' => $nombre, 'correo' => $correo, 'clave' => $clave, 'rol' => $rol], $t)['id'];
     $equipo[] = ['id' => $id, 'rol' => $rol, 'correo' => $correo, 'nombre' => $nombre];
    }
    $ejecutivos = array_values(array_filter($equipo, fn($u) => $u['rol'] === 'EJECUTIVO'));

    $contactos = [];
    for ($i = 1; $i <= 40; $i++) {
     $contactos[] = ['id' => sprintf('%s-c%03d', $e['slug'], $i), 'name' => $pick($NOMBRES) . ' ' . $pick($APELLIDOS) . ' ' . $pick($APELLIDOS), 'email' => sprintf('contacto%03d.%s@ejemplo.test', $i, $e['slug']), 'phone' => '+58414' . str_pad((string)mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT), 'tags' => [$pick(['Prospecto', 'Cliente', 'Referido', 'Redes sociales', 'WhatsApp'])], 'assignedTo' => $pick($ejecutivos)['id']];
    }
    $call('workspace/import', 'POST', ['kind' => 'contact', 'items' => $contactos], $t);
    $call('workspace/productos', 'PUT', $e['catalogo'], $t);

    $stages = [];
    foreach ($e['etapas'] as $i => $s) $stages[] = ['id' => 'e' . ($i + 1), 'name' => $s];
    $call('workspace/import', 'POST', ['kind' => 'pipeline', 'items' => [['id' => 'ventas', 'name' => 'Ventas', 'stages' => $stages]]], $t);
    $ops = [];
    for ($i = 1; $i <= 60; $i++) {
     $estado = $pick(['open', 'open', 'open', 'won', 'won', 'lost', 'abandoned']);
     $c = $pick($contactos);
     $ops[] = ['id' => sprintf('%s-o%03d', $e['slug'], $i), 'name' => $pick($e['productos']) . ' · ' . explode(' ', $c['name'])[0], 'contactId' => $c['id'], 'pipelineId' => 'ventas', 'pipelineStageId' => $estado === 'won' ? end($stages)['id'] : $pick($stages)['id'], 'monetaryValue' => mt_rand(40, 900) + mt_rand(0, 99) / 100, 'status' => $estado, 'assignedTo' => $c['assignedTo'], 'dateAdded' => $hoy->modify('-' . mt_rand(0, 180) . ' days')->format('Y-m-d') . 'T' . sprintf('%02d:%02d:00Z', mt_rand(8, 18), mt_rand(0, 59))];
    }
    $call('workspace/import', 'POST', ['kind' => 'opportunity', 'items' => $ops], $t);

    $tareas = ['Llamar para confirmar la inscripción', 'Enviar la propuesta por WhatsApp', 'Revisar los prospectos sin asignar', 'Confirmar el pago pendiente', 'Agendar la visita de seguimiento', 'Actualizar los datos del cliente', 'Enviar el convenio para firma', 'Preparar el reporte semanal'];
    $hechas = 0;
    foreach (range(0, 11) as $i) {
     $c = $pick($contactos);
     $id = $call('workspace/tareas', 'POST', ['titulo' => $pick($tareas), 'detalle' => 'Contacto: ' . $c['name'], 'vence' => $dia(mt_rand(-5, 14)), 'hora' => sprintf('%02d:%02d', mt_rand(8, 17), $pick([0, 30])), 'contactId' => $c['id'], 'responsableId' => $pick($ejecutivos)['id']], $t)['id'];
     if ($i % 4 === 0) { $call("workspace/tareas/$id/estado", 'POST', ['estado' => 'hecha'], $t); $hechas++; }
    }

    $convenios = 0;
    foreach (array_slice($contactos, 0, 6) as $i => $c) {
     try {
      $uid = $call('contratos', 'POST', ['cliente' => ['nombre' => $c['name'], 'correo' => $c['email'], 'cedula' => 'V-' . mt_rand(10000000, 30999999), 'telefono' => $c['phone']], 'curso' => ['cuota_mensual' => (string)mt_rand(25, 120), 'meses' => mt_rand(3, 12), 'mes_inicio' => $hoy->format('Y-m'), 'cuotas_por_mes' => 1]], $t)['uid'];
      $convenios++;
      if ($i % 2 === 0) { try { $call("contratos/$uid/enlace", 'POST', [], $t); } catch (\RuntimeException) { try { $call("contratos/$uid/enviar", 'POST', [], $t); } catch (\RuntimeException) { /* queda en borrador */ } } }
     } catch (\RuntimeException $x) { $say('   (convenios omitidos: ' . $x->getMessage() . ')'); break; }
    }

    $reportes = 0;
    if (is_array($reporte)) foreach ([5, 4, 3, 2, 1, 0] as $atras) {
     try { $call('workspace/import', 'POST', ['kind' => 'report', 'period' => $hoy->modify("first day of -$atras month")->format('Y-m'), 'data' => $reporte['data']], $t); $reportes++; }
     catch (\RuntimeException $x) { $say('   (reporte omitido: ' . $x->getMessage() . ')'); }
    }
    if (is_array($facturas)) { try { $call('workspace/import', 'POST', $facturas, $t); } catch (\RuntimeException $x) { $say('   (facturas omitidas: ' . $x->getMessage() . ')'); } }

    $say(sprintf('   %d personas · %d contactos · %d oportunidades · 12 tareas (%d hechas) · %d convenios · %d reportes', count($equipo), count($contactos), count($ops), $hechas, $convenios, $reportes));
    $resumen[] = ['empresa' => $e['nombre'], 'equipo' => $equipo];
   }
   return $resumen;
  } finally {
   $_GET = $getAntes;
   if ($wsAntes === null) unset($_SERVER['HTTP_X_WORKSPACE_ID']); else $_SERVER['HTTP_X_WORKSPACE_ID'] = $wsAntes;
  }
 }

 /** Borra solo las subcuentas sintéticas, con su equipo y sus datos. Devuelve cuántas borró. */
 public static function borrar(DB $db): int {
  $ids = array_map('intval', array_column($db->all('SELECT id FROM agora_tenants WHERE code IN (?,?)', self::CODIGOS), 'id'));
  if (!$ids) return 0;
  $in = implode(',', $ids);
  $db->transaction(function () use ($db, $in) {
   foreach (['agora_signatures', 'agora_jobs', 'agora_records', 'agora_settings', 'agora_idempotency', 'agora_assignments'] as $t) $db->run("DELETE FROM $t WHERE tenant_id IN ($in)");
   $db->run("DELETE FROM agora_sessions WHERE user_id IN (SELECT id FROM agora_users WHERE local_tenant_id IN ($in))");
   $db->run("DELETE FROM agora_users WHERE local_tenant_id IN ($in)");
   $db->run("DELETE FROM agora_tenants WHERE id IN ($in)");
  });
  return count($ids);
 }
}
