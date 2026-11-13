<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\Records;
/* 2.1.17 · equipo experiencia · edición de datos que faltaba en la subcuenta.
   PUT workspace/tareas/{id}: corrige título, detalle, fecha, hora y estado de una tarea.
   Respeta la subcuenta activa, la propiedad del registro (EJECUTIVO y consulta solo lo suyo),
   la versión del registro (409 si cambió) y deja auditoría (Records::save registra "edito"). */
final class Experiencia {
 public function __construct(public DB $db,public Auth $auth,public Records $records){}
 public function route(string $p,string $m,array $b):array{
  if(preg_match('~^workspace/tareas/([a-z0-9_]{1,80})$~D',$p,$x)&&$m==='PUT')return $this->tarea($x[1],$b);
  Http::fail('Ruta no disponible.',404);
 }
 private function tarea(string $id,array $b):array{
  $this->auth->write();
  $r=$this->records->get('task',$id,true);$d=$this->records->payload($r);
  $title=Http::text($b,'titulo',250);
  $date=Http::text($b,'vence',10,false);
  if($date!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))))Http::fail('Fecha inválida.');
  $hour=Http::text($b,'hora',5,false);
  if($hour!==''&&!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$hour))Http::fail('Hora inválida.');
  $status=(string)($b['estado']??($d['estado']??'pendiente'));
  if(!in_array($status,['pendiente','hecha'],true))Http::fail('Estado de tarea inválido.');
  $expected=isset($b['version'])?Http::integer($b['version'],1,PHP_INT_MAX):null;
  $d['titulo']=$title;$d['detalle']=Http::text($b,'detalle',2000,false);$d['vence']=$date?:null;$d['hora']=$hour;$d['estado']=$status;
  unset($d['version']);
  $saved=$this->records->save('task',$id,$d,$title,$status,substr($date,0,7),null,$expected);
  return ['ok'=>true,'tarea'=>$saved];
 }
}
