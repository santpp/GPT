<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
final class Metrics {
 public function __construct(public DB $db,public Auth $a){}
 private function rows(string $kind,?string $status=null,?string $period=null):array{
  $where='tenant_id=? AND kind=?';$args=[$this->a->tenant,$kind];if($this->a->assignedOnly()){$where.=' AND owner_id=?';$args[]=$this->a->user['id'];}if($status!==null){$where.=' AND status=?';$args[]=$status;}if($period!==null){$where.=' AND period=?';$args[]=$period;}
  return $this->db->one("SELECT COUNT(*) n,COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.monetaryValue')) AS DECIMAL(18,2))),0) value,COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.unreadCount')) AS UNSIGNED)),0) unread FROM agora_records WHERE $where",$args);
 }
 /** 2.9.0: con la cuenta conectada, las ganadas del mes salen de la cuenta (espejo), no de copias locales. */
 private function ganadas(string $period):array{
  $t=(int)$this->a->tenant;
  if(!\Agora\Core\MotorMobile::conectada($this->db,$t))return $this->rows('opportunity','won',$period);
  $rows=\Agora\Core\MotorMobile::ganadasMes($this->db,$this->a->config,$t,$period);
  if($this->a->assignedOnly()){$mio=(new UserLinks($this->db))->forUser($t,['id'=>$this->a->user['id'],'email'=>$this->a->user['email']])['id']??'-';$rows=array_filter($rows,fn($r)=>$r['asignado_a']===$mio);}
  return ['n'=>count($rows),'value'=>array_sum(array_map(fn($r)=>(float)$r['valor'],$rows)),'unread'=>0];
 }
 public function dashboard():array{$c=$this->rows('conversation');$o=$this->rows('opportunity','open');return ['restricted'=>$this->a->assignedOnly(),'readAt'=>gmdate('c'),'conversations'=>['total'=>(int)$c['n'],'unread'=>(int)$c['unread'],'waiting'=>null],'contacts'=>(int)$this->rows('contact')['n'],'leads'=>['today'=>null,'mine'=>null,'linked'=>true],'opportunities'=>['count'=>(int)$o['n'],'value'=>(float)$o['value'],'sampled'=>false,'stages'=>[]],'from'=>$_GET['from']??null,'to'=>$_GET['to']??null];}
 public function won():array{$p=(new Records($this->db,$this->a))->page('opportunity',['status'=>'won']);$tot=$this->rows('opportunity','won');$p['items']=array_map(fn($o)=>$o+['value'=>(float)($o['monetaryValue']??0),'missing'=>empty($o['monetaryValue'])],$p['items']);return $p+['totals'=>['count'=>(int)$tot['n'],'sum'=>(float)$tot['value'],'missing'=>count(array_filter($p['items'],fn($o)=>$o['missing']))],'bySeller'=>[],'canSeeAll'=>!$this->a->assignedOnly(),'canEdit'=>!in_array($this->a->user['role'],['USUARIO','CONSULTA','VISOR'])];}
 public function month():array{$period=Http::period($_GET['period']??gmdate('Y-m'));$s=$this->db->setting($this->a->tenant,'board.'.$period,[]);$won=$this->ganadas($period);$total=(float)$won['value'];$goal=(float)($s['teamGoal']??0);return ['period'=>$period,'canEdit'=>in_array($this->a->user['role'],['MASTER','SUPERADMIN','ADMIN']),'commission'=>['kind'=>'percent','amount'=>null],'missing'=>array_values(array_filter(['ads',isset($s['fixedCosts'])&&$s['fixedCosts']!==''&&$s['fixedCosts']!==null?null:'fixedCosts',$goal?null:'teamGoal'])),'sales'=>['closings'=>(int)$won['n'],'total'=>$total,'counted'=>(int)$won['n'],'partial'=>false],'mine'=>null,'goal'=>['target'=>$goal?:null,'progress'=>$goal?$total/$goal*100:null,'missing'=>$goal?max(0,$goal-$total):null],'settings'=>$s+['goals'=>(object)[],'green'=>100,'amber'=>70,'closingWord'=>'cierres'],'team'=>[],'profitability'=>['sales'=>$total,'ads'=>null,'adsFromAccount'=>null,'adsOther'=>$s['otherAds']??null,'adsReason'=>'not_connected','commissions'=>null,'profit'=>null,'fixedCosts'=>$s['fixedCosts']??null,'net'=>null,'costPerClosing'=>null,'returnPerHundred'=>null,'marketingCost'=>null,'marginPerUnit'=>null]];}
}
