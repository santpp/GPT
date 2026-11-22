<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http};
/**
 * Agora New App · enlace entre la persona de Ágora y su usuario en la cuenta conectada de la subcuenta.
 *
 * - El equipo de la cuenta lo copia el motor al conectar o al pulsar «Actualizar equipo» (tabla external_users).
 * - Enlace explícito: agora_user_links (lo elige quien administra). Sin enlace explícito se deduce por correo.
 * - De ese enlace depende qué ve un ejecutivo: solo lo asignado a su usuario de la cuenta.
 */
final class UserLinks {
 public function __construct(private DB $db){}
 /** Usuarios activos de la cuenta de esta subcuenta. Vacío si aún no está conectada. */
 public function team(int $tenant):array{
  try{$rows=$this->db->all('SELECT external_user_id,email,display_name,role_name,role_type,synced_at FROM external_users WHERE connection_id=? AND active=1 ORDER BY display_name LIMIT 500',[$tenant]);}
  catch(\PDOException){return [];}
  return array_map(fn($r)=>['id'=>(string)$r['external_user_id'],'nombre'=>(string)($r['display_name']?:$r['email']),'correo'=>(string)$r['email'],'rol'=>(string)($r['role_name']?:$r['role_type']),'sincronizado'=>$r['synced_at']],$rows);
 }
 /** Enlaces de varias personas a la vez: user_id => ['id','nombre','correo','auto'] o null. */
 public function forUsers(int $tenant,array $users):array{
  $team=$this->team($tenant);$porId=[];$porCorreo=[];foreach($team as $t){$porId[$t['id']]=$t;if($t['correo']!=='')$porCorreo[mb_strtolower($t['correo'])]??=$t;}
  $explicit=[];try{foreach($this->db->all('SELECT user_id,external_user_id FROM agora_user_links WHERE tenant_id=?',[$tenant]) as $r)$explicit[(int)$r['user_id']]=(string)$r['external_user_id'];}catch(\PDOException){}
  $tomados=array_flip($explicit);$out=[];
  foreach($users as $u){$id=(int)$u['id'];$e=null;
   if(isset($explicit[$id])&&isset($porId[$explicit[$id]]))$e=$porId[$explicit[$id]]+['auto'=>false];
   elseif(!isset($explicit[$id])){$t=$porCorreo[mb_strtolower((string)$u['email'])]??null;if($t&&!isset($tomados[$t['id']]))$e=$t+['auto'=>true];}
   $out[$id]=$e?['id'=>$e['id'],'nombre'=>$e['nombre'],'correo'=>$e['correo'],'auto'=>$e['auto']]:null;}
  return $out;
 }
 public function forUser(int $tenant,array $user):?array{return $this->forUsers($tenant,[$user])[(int)$user['id']];}
 /** Guarda o quita el enlace explícito. $external vacío = volver a deducirlo por correo. */
 public function set(int $tenant,int $userId,string $external):?array{
  $u=$this->db->one('SELECT id,email FROM agora_users WHERE id=? AND local_tenant_id=?',[$userId,$tenant]);if(!$u)Http::fail('Usuario no encontrado en esta subcuenta.',404);
  if($external===''){$this->db->run('DELETE FROM agora_user_links WHERE tenant_id=? AND user_id=?',[$tenant,$userId]);return $this->forUser($tenant,$u);}
  if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$external))Http::fail('Usuario de la cuenta inválido.');
  $ok=false;foreach($this->team($tenant) as $t)if($t['id']===$external)$ok=true;if(!$ok)Http::fail('Ese usuario no está en el equipo de la cuenta. Pulsa «Actualizar equipo» y vuelve a intentarlo.',404);
  $otro=$this->db->one('SELECT l.user_id,u.name FROM agora_user_links l JOIN agora_users u ON u.id=l.user_id WHERE l.tenant_id=? AND l.external_user_id=? AND l.user_id<>?',[$tenant,$external,$userId]);
  if($otro)Http::fail('Ese usuario de la cuenta ya está enlazado con '.$otro['name'].'. Quítale el enlace primero.',409);
  $this->db->run('DELETE FROM agora_user_links WHERE tenant_id=? AND user_id=?',[$tenant,$userId]);
  $this->db->run('INSERT INTO agora_user_links(tenant_id,user_id,external_user_id) VALUES(?,?,?)',[$tenant,$userId,$external]);
  return $this->forUser($tenant,$u);
 }
}
