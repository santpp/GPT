<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\Records;
/*
 * 2.1.17 · Embudos e indicadores (KPI) por subcuenta. Sin migraciones.
 *  - workspace/embudos       GET/PUT. Los embudos siguen siendo registros kind 'pipeline' con la forma
 *                            {id,name,stages:[{id,name}]} que leen Oportunidades y el ARP; cada etapa suma
 *                            metadatos que esos módulos ignoran: color, probabilidad, tipo, sla y kpi.
 *  - workspace/indicadores   GET/PUT. Ajuste `indicadores.config` (lista de KPI) + metas del mes, que se
 *                            guardan en el mismo ajuste `board.<periodo>` que usa el ARP (Configuración mensual
 *                            del tablero comercial): no hay dos lugares para la misma meta.
 */
final class Embudos {
 private const ID='/^[A-Za-z0-9_-]{1,80}$/D';
 public const TIPOS=['abierta','ganada','perdida'];
 public const KPI_ETAPA=['','lead','cita','cierre'];
 public const COLORES=['#6B7A99','#2F6FDE','#0E9F8F','#D98E04','#7C4DDB','#D2337A','#1F9D55','#C2410C'];
 /** Métricas que la app ya calcula (el cliente las lee de sus módulos). clave => [nombre, formato, sentido]. */
 public const FUENTES=[
  'ventasMes'=>['Ventas del mes','monto','mas'],'cierresMes'=>['Cierres del mes','numero','mas'],'ingresos'=>['Ingresos por ventas ganadas','monto','mas'],
  'gananciaMes'=>['Ganancia del mes','monto','mas'],'leadsMes'=>['Leads del mes','numero','mas'],'leadsDia'=>['Leads de hoy','numero','mas'],
  'conversion'=>['Conversión de leads','porcentaje','mas'],'tasaCierre'=>['Tasa de cierre','porcentaje','mas'],'costoLead'=>['Costo por lead','monto','menos'],
  'costoCierre'=>['Costo por cierre','monto','menos'],'inversionAds'=>['Inversión en anuncios','monto','menos'],'retornoAds'=>['Retorno por cada 100 invertidos','numero','mas'],
  'ticketPromedio'=>['Ticket promedio','monto','mas'],'cicloVenta'=>['Días para cerrar una venta','numero','menos'],'tiempoRespuesta'=>['Tiempo de respuesta (minutos)','numero','menos'],
  'sinLeer'=>['Conversaciones sin leer','numero','menos'],'conversaciones'=>['Conversaciones','numero','mas'],'contactos'=>['Contactos','numero','mas'],
  'oportunidadesAbiertas'=>['Oportunidades abiertas','numero','mas'],'valorAbierto'=>['Dinero en juego','monto','mas'],
  'leadsEmbudo'=>['Oportunidades en etapas de lead','numero','mas'],'citas'=>['Oportunidades en etapas de cita','numero','mas'],'cierresEmbudo'=>['Oportunidades en etapas de cierre','numero','mas'],
  'tareasPendientes'=>['Tareas pendientes','numero','menos'],'tareasVencidas'=>['Tareas vencidas','numero','menos'],
  'conveniosPorFirmar'=>['Convenios por firmar','numero','menos'],'cobrosPendientes'=>['Cobros pendientes','monto','menos'],
 ];
 public const FORMATOS=['monto','numero','porcentaje'];
 public const PERIODOS=['dia','semana','mes','trimestre','anio'];
 private const DEFECTO=['ventasMes','leadsMes','conversion','costoLead','ticketPromedio','tiempoRespuesta','oportunidadesAbiertas','tareasVencidas'];

 public function __construct(public DB $db,public Auth $auth,public Records $records){}
 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/embudos'&&$m==='GET'){$this->auth->manage();return $this->embudos();}
  if($p==='workspace/embudos'&&$m==='PUT'){$this->auth->manage();return $this->guardarEmbudos($b);}
  if($p==='workspace/indicadores'&&$m==='GET')return $this->indicadores();
  if($p==='workspace/indicadores'&&$m==='PUT'){$this->auth->manage();return $this->guardarIndicadores($b);}
  Http::fail('Ruta no disponible.',404);
 }

 /* ---------------- Embudos ---------------- */
 private function todos():array{
  $rows=$this->db->all("SELECT * FROM agora_records WHERE tenant_id=? AND kind='pipeline' ORDER BY seq LIMIT 50",[$this->auth->tenant]);
  return array_map(fn($r)=>$this->records->payload($r),$rows);
 }
 /** Completa los metadatos que falten, sin cambiar id ni nombre (así los embudos de 2.1.16 se leen igual). */
 public static function etapa(array $s,int $i,int $n):array{
  $tipo=in_array($s['tipo']??'',self::TIPOS,true)?$s['tipo']:'abierta';
  $prob=isset($s['probabilidad'])&&is_numeric($s['probabilidad'])?max(0,min(100,(int)$s['probabilidad'])):($tipo==='ganada'?100:($tipo==='perdida'?0:(int)round(($i+1)*100/max(1,$n+1))));
  $color=is_string($s['color']??null)&&preg_match('/^#[0-9A-Fa-f]{6}$/D',$s['color'])?strtoupper($s['color']):self::COLORES[$i%count(self::COLORES)];
  $sla=isset($s['sla'])&&$s['sla']!==''&&$s['sla']!==null&&is_numeric($s['sla'])?max(1,min(365,(int)$s['sla'])):null;
  $kpi=in_array($s['kpi']??'',self::KPI_ETAPA,true)?$s['kpi']:'';
  return ['id'=>(string)($s['id']??''),'name'=>(string)($s['name']??''),'color'=>$color,'probabilidad'=>$prob,'tipo'=>$tipo,'sla'=>$sla,'kpi'=>$kpi];
 }
 private function conteos():array{
  $rows=$this->db->all("SELECT JSON_UNQUOTE(JSON_EXTRACT(data,'$.pipelineId')) p,JSON_UNQUOTE(JSON_EXTRACT(data,'$.pipelineStageId')) s,status,COUNT(*) n,COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.monetaryValue')) AS DECIMAL(18,2))),0) v FROM agora_records WHERE tenant_id=? AND kind='opportunity' GROUP BY p,s,status",[$this->auth->tenant]);
  $o=[];foreach($rows as $r){$k=$r['p'].'|'.$r['s'];$o[$k]??=['n'=>0,'abiertas'=>0,'valor'=>0.0];$o[$k]['n']+=(int)$r['n'];$o[$k]['valor']+=(float)$r['v'];if($r['status']==='open')$o[$k]['abiertas']+=(int)$r['n'];}
  return $o;
 }
 public function embudos():array{
  $pipes=array_map(function($p){$st=array_values(array_filter($p['stages']??[],'is_array'));$n=count($st);return ['id'=>$p['id'],'name'=>$p['name']??'','stages'=>array_map(fn($s,$i)=>self::etapa($s,$i,$n),$st,array_keys($st)),'version'=>$p['version']??1];},$this->todos());
  return ['pipelines'=>$pipes,'conteos'=>(object)$this->conteos(),'colores'=>self::COLORES,'canEdit'=>true];
 }
 public function guardarEmbudos(array $b):array{
  $in=$b['pipelines']??null;if(!is_array($in)||!array_is_list($in)||!count($in))Http::fail('Debe quedar al menos un embudo.');if(count($in)>20)Http::fail('Hasta 20 embudos por subcuenta.');
  $nuevos=[];$nombres=[];
  foreach($in as $p){
   if(!is_array($p))Http::fail('Embudo inválido.');
   $id=(string)($p['id']??'');if($id==='')$id='pl_'.bin2hex(random_bytes(5));if(!preg_match(self::ID,$id))Http::fail('Identificador de embudo inválido.');if(isset($nuevos[$id]))Http::fail('Hay embudos repetidos.');
   $name=Http::text($p,'name',120);$k=mb_strtolower($name);if(isset($nombres[$k]))Http::fail("Ya hay un embudo llamado «{$name}».");$nombres[$k]=1;
   $st=$p['stages']??null;if(!is_array($st)||!array_is_list($st)||!count($st))Http::fail("«{$name}» necesita al menos una etapa.");if(count($st)>30)Http::fail("«{$name}» tiene más de 30 etapas.");
   $ids=[];$sn=[];$stages=[];$n=count($st);
   foreach($st as $i=>$s){
    if(!is_array($s))Http::fail('Etapa inválida.');$sid=(string)($s['id']??'');if($sid==='')$sid='st_'.bin2hex(random_bytes(5));if(!preg_match(self::ID,$sid))Http::fail('Identificador de etapa inválido.');
    if(isset($ids[$sid]))Http::fail("Hay etapas repetidas en «{$name}».");$ids[$sid]=1;
    $sname=Http::text($s,'name',120);$sk=mb_strtolower($sname);if(isset($sn[$sk]))Http::fail("La etapa «{$sname}» está repetida en «{$name}».");$sn[$sk]=1;
    if(isset($s['probabilidad'])&&$s['probabilidad']!==''&&$s['probabilidad']!==null)Http::integer($s['probabilidad'],0,100);
    if(isset($s['sla'])&&$s['sla']!==''&&$s['sla']!==null)Http::integer($s['sla'],1,365);
    if(isset($s['tipo'])&&!in_array($s['tipo'],self::TIPOS,true))Http::fail('Tipo de etapa inválido.');
    if(isset($s['kpi'])&&!in_array($s['kpi'],self::KPI_ETAPA,true))Http::fail('Marca de indicador inválida.');
    if(isset($s['color'])&&(!is_string($s['color'])||!preg_match('/^#[0-9A-Fa-f]{6}$/D',$s['color'])))Http::fail('Color inválido. Usa #RRGGBB.');
    $stages[]=self::etapa(['id'=>$sid,'name'=>$sname]+$s,$i,$n);
   }
   $nuevos[$id]=['id'=>$id,'name'=>$name,'stages'=>$stages];
  }
  // Etapas que desaparecen: si tienen oportunidades, hay que decir a dónde moverlas.
  $mover=is_array($b['mover']??null)?$b['mover']:[];
  $existe=fn(string $p,string $s)=>isset($nuevos[$p])&&in_array($s,array_column($nuevos[$p]['stages'],'id'),true);
  $antes=$this->todos();$conteo=$this->conteos();$traslados=[];$borrados=[];
  foreach($antes as $p){
   if(!isset($nuevos[$p['id']]))$borrados[]=$p['id'];
   foreach($p['stages']??[] as $s){
    $sid=(string)($s['id']??'');if($existe($p['id'],$sid))continue;
    $n=$conteo[$p['id'].'|'.$sid]['n']??0;if(!$n)continue;
    $destino=(string)($mover[$p['id'].'|'.$sid]??'');[$dp,$ds]=array_pad(explode('|',$destino,2),2,'');
    if(!$existe($dp,$ds))Http::fail('La etapa «'.($s['name']??$sid).'» tiene '.$n.' '.($n===1?'oportunidad':'oportunidades').'. Elige a qué etapa moverlas antes de borrarla.',409);
    $traslados[]=[$p['id'],$sid,$dp,$ds,$n];
   }
  }
  $movidas=0;
  $this->db->transaction(function()use($nuevos,$borrados,$traslados,&$movidas){
   foreach($traslados as [$op,$os,$dp,$ds]){
    $rows=$this->db->all("SELECT seq,data FROM agora_records WHERE tenant_id=? AND kind='opportunity' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.pipelineId'))=? AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.pipelineStageId'))=?",[$this->auth->tenant,$op,$os]);
    foreach($rows as $r){$d=json_decode($r['data'],true);$d['pipelineId']=$dp;$d['pipelineStageId']=$ds;$this->db->run('UPDATE agora_records SET data=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE seq=?',[DB::json($d),$r['seq']]);$movidas++;}
   }
   foreach($nuevos as $id=>$p)$this->records->save('pipeline',$id,$p,$p['name']);
   foreach($borrados as $id)$this->db->run("DELETE FROM agora_records WHERE tenant_id=? AND kind='pipeline' AND uid=?",[$this->auth->tenant,$id]);
  });
  $this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$this->auth->tenant]);
  $this->auth->audit('embudos.actualizados','oportunidades','',['antes'=>count($antes).' embudos','despues'=>count($nuevos).' embudos','etapas'=>array_sum(array_map(fn($p)=>count($p['stages']),$nuevos)),'eliminados'=>$borrados,'oportunidadesMovidas'=>$movidas]);
  return $this->embudos()+['ok'=>true,'movidas'=>$movidas];
 }

 /* ---------------- Indicadores (KPI) ---------------- */
 public static function kpiDefecto(string $f):array{[$n,$fmt,$sen]=self::FUENTES[$f];return ['id'=>$f,'fuente'=>$f,'nombre'=>$n,'activo'=>true,'inicio'=>in_array($f,['ventasMes','leadsMes','conversion','tareasVencidas'],true),'meta'=>null,'periodo'=>in_array($f,['leadsDia','tiempoRespuesta','sinLeer'],true)?'dia':'mes','formato'=>$fmt,'moneda'=>null,'verde'=>100,'ambar'=>70,'sentido'=>$sen,'formula'=>null];}
 private function mes(string $period):array{
  $s=$this->db->setting($this->auth->tenant,'board.'.$period,[]);if(!is_array($s))$s=[];
  return ['period'=>$period,'teamGoal'=>isset($s['teamGoal'])&&is_numeric($s['teamGoal'])?(float)$s['teamGoal']:null,'green'=>(float)($s['green']??100),'amber'=>(float)($s['amber']??70),'closingWord'=>(string)($s['closingWord']??'cierres'),'goals'=>(object)(is_array($s['goals']??null)?$s['goals']:[]),'otherAds'=>$s['otherAds']??null,'fixedCosts'=>$s['fixedCosts']??null,'guardado'=>(bool)$s];
 }
 public function indicadores():array{
  $period=isset($_GET['period'])?Http::period($_GET['period']):gmdate('Y-m');
  $c=$this->db->setting($this->auth->tenant,'indicadores.config',null);$mes=$this->mes($period);
  $kpis=is_array($c['kpis']??null)?$c['kpis']:array_map([self::class,'kpiDefecto'],self::DEFECTO);
  // La meta de ventas del mes y su semáforo viven en el ajuste del mes (los lee el ARP): se muestran aquí.
  foreach($kpis as &$k)if(($k['fuente']??'')==='ventasMes'){$k['meta']=$mes['teamGoal'];$k['verde']=$mes['green'];$k['ambar']=$mes['amber'];}unset($k);
  $fuentes=[];foreach(self::FUENTES as $id=>[$n,$f,$s])$fuentes[]=['id'=>$id,'nombre'=>$n,'formato'=>$f,'sentido'=>$s];
  return ['config'=>['kpis'=>$kpis,'actualizado'=>$c['actualizado']??null,'por'=>$c['por']??null],'porDefecto'=>!is_array($c),'fuentes'=>$fuentes,'mes'=>$mes,
   'asesores'=>$this->db->all("SELECT id,name FROM agora_users WHERE local_tenant_id=? AND active=1 AND role NOT IN ('CONSULTA','VISOR') ORDER BY name LIMIT 100",[$this->auth->tenant]),
   'canEdit'=>in_array($this->auth->user['role'],['MASTER','SUPERADMIN','ADMIN'],true)];
 }
 private static function num(mixed $v,float $min,float $max,string $campo):?float{if($v===null||$v==='')return null;if(!is_numeric($v)||$v<$min||$v>$max)Http::fail("Revisa «{$campo}».");return round((float)$v,2);}
 public static function kpi(mixed $k):array{
  if(!is_array($k))Http::fail('Indicador inválido.');
  $fuente=(string)($k['fuente']??'');if($fuente!=='personalizado'&&!isset(self::FUENTES[$fuente]))Http::fail('Fuente de indicador inválida.');
  $id=(string)($k['id']??'');if(!preg_match('/^[A-Za-z0-9_-]{1,40}$/D',$id))Http::fail('Identificador de indicador inválido.');
  $nombre=Http::text($k,'nombre',60);
  $formato=in_array($k['formato']??'',self::FORMATOS,true)?$k['formato']:Http::fail("Elige el formato de «{$nombre}».");
  $sentido=in_array($k['sentido']??'',['mas','menos'],true)?$k['sentido']:Http::fail("Indica si en «{$nombre}» más es mejor o menos es mejor.");
  $periodo=in_array($k['periodo']??'mes',self::PERIODOS,true)?($k['periodo']??'mes'):'mes';
  $verde=self::num($k['verde']??100,0,1000,'Verde desde')??100;$ambar=self::num($k['ambar']??70,0,1000,'Ámbar desde')??70;
  if($ambar>$verde)Http::fail("En «{$nombre}» el ámbar no puede ser mayor que el verde.");
  $moneda=$k['moneda']??null;if($moneda!==null&&$moneda!==''&&!in_array($moneda,Personalizar::MONEDAS,true))Http::fail('Moneda inválida.');
  $formula=null;
  if($fuente==='personalizado'){
   $f=$k['formula']??null;if(!is_array($f))Http::fail("Arma la fórmula de «{$nombre}».");
   $a=(string)($f['a']??'');$bb=(string)($f['b']??'');$op=(string)($f['op']??'');
   if(!isset(self::FUENTES[$a],self::FUENTES[$bb])||!in_array($op,['/','-'],true))Http::fail("La fórmula de «{$nombre}» debe ser métrica A ÷ métrica B o A − B.");
   $formula=['a'=>$a,'op'=>$op,'b'=>$bb,'porCien'=>($f['porCien']??false)===true];
  }
  return ['id'=>$id,'fuente'=>$fuente,'nombre'=>$nombre,'activo'=>($k['activo']??true)!==false,'inicio'=>($k['inicio']??false)===true,'meta'=>self::num($k['meta']??null,0,1000000000,'Meta de '.$nombre),'periodo'=>$periodo,'formato'=>$formato,'moneda'=>$moneda?:null,'verde'=>$verde,'ambar'=>$ambar,'sentido'=>$sentido,'formula'=>$formula];
 }
 public function guardarIndicadores(array $b):array{
  $t=(int)$this->auth->tenant;
  $in=$b['kpis']??null;if(!is_array($in)||!array_is_list($in)||count($in)>40)Http::fail('Hasta 40 indicadores.');
  $kpis=[];$ids=[];foreach($in as $k){$k=self::kpi($k);if(isset($ids[$k['id']]))Http::fail('Hay indicadores repetidos.');$ids[$k['id']]=1;$kpis[]=$k;}
  $period=isset($b['mes']['period'])?Http::period($b['mes']['period']):gmdate('Y-m');
  if(is_array($b['mes']??null)){
   $m=$b['mes'];$actual=$this->db->setting($t,'board.'.$period,[]);if(!is_array($actual))$actual=[];
   $nuevo=$actual;
   foreach(['teamGoal'=>'Meta del equipo','otherAds'=>'Otros gastos de publicidad','fixedCosts'=>'Costos fijos'] as $key=>$label)if(array_key_exists($key,$m)){$v=self::num($m[$key],0,1000000000,$label);if($v===null)unset($nuevo[$key]);else $nuevo[$key]=$v;}
   foreach(['green'=>'Verde desde','amber'=>'Ámbar desde'] as $key=>$label)if(array_key_exists($key,$m))$nuevo[$key]=self::num($m[$key],0,1000,$label)??($key==='green'?100:70);
   if(($nuevo['amber']??70)>($nuevo['green']??100))Http::fail('El ámbar del mes no puede ser mayor que el verde.');
   if(array_key_exists('closingWord',$m)){$w=Http::text($m,'closingWord',30,false);$nuevo['closingWord']=$w!==''?$w:'cierres';}
   if(isset($m['goals'])){if(!is_array($m['goals'])||count($m['goals'])>200)Http::fail('Metas por asesor inválidas.');$g=[];foreach($m['goals'] as $uid=>$v){if(!preg_match('/^[A-Za-z0-9_-]{1,40}$/D',(string)$uid))Http::fail('Asesor inválido.');$v=self::num($v,0,1000000000,'Meta por asesor');if($v!==null)$g[(string)$uid]=$v;}$nuevo['goals']=$g;}
   if(strlen(DB::json($nuevo))>10000)Http::fail('Configuración del mes demasiado grande.');
   if($nuevo!==$actual){$this->db->saveSetting($t,'board.'.$period,$nuevo);$this->auth->audit('metas.actualizadas','ajuste',$period,['desde'=>'indicadores']);}
  }
  // ventasMes: su meta y umbrales son los del mes (ver indicadores()); no se duplican aquí.
  foreach($kpis as &$k)if($k['fuente']==='ventasMes'){$k['meta']=null;}unset($k);
  $antes=$this->db->setting($t,'indicadores.config',null);
  $data=['kpis'=>$kpis,'actualizado'=>gmdate('c'),'por'=>$this->auth->user['name']??''];
  $this->db->saveSetting($t,'indicadores.config',$data);
  $this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$t]);
  $this->auth->audit('indicadores.actualizados','indicadores','',['antes'=>is_array($antes)?count($antes['kpis']??[]).' indicadores':'por defecto','despues'=>count($kpis).' indicadores','activos'=>count(array_filter($kpis,fn($k)=>$k['activo'])),'periodo'=>$period]);
  $_GET['period']=$period;
  return $this->indicadores()+['ok'=>true];
 }
}
