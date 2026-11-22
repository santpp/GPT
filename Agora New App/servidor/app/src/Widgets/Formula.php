<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Widgets;

/** Error de una fórmula, con la posición (desde 0) donde está el problema para señalarlo en el editor. */
final class FormulaError extends \RuntimeException {
 public function __construct(string $m,public int $pos=-1){parent::__construct($m);}
}

/**
 * Agora New App 2.14.0 · Intérprete de fórmulas de los widgets.
 * Se lee el texto, se convierte en un árbol y se calcula. Nunca se ejecuta texto como código (sin eval).
 * Sintaxis (como una hoja de cálculo en español):
 *   números 1500 · 12,5 · 12.5 · 15% (= 0,15)   referencias {Vendido} · {Vendido · mes anterior}
 *   + − × ÷ (también - * /) · potencia ^ · comparaciones = <> > < >= <= (≥ ≤ ≠) · paréntesis · argumentos con ;
 * Unidades: dinero, numero, porcentaje (escala 0–100), fecha (días), bool. Un número escrito no tiene unidad y se adapta.
 * Un porcentaje que se multiplica o divide con otra cosa se usa como fracción ({Vendido} × {Comisión %} con 10 → × 0,10).
 * Faltan datos o se divide entre cero → el resultado es «—» con la razón (valor null), nunca un error.
 */
final class Formula {
 public const MAX_LEN=500, MAX_DEPTH=40, MAX_NODES=400;
 private array $t=[];private int $i=0;private int $depth=0;private int $nodes=0;

 /** Convierte el texto en árbol. Lanza FormulaError con la posición. */
 public static function parse(string $src):array{
  if(trim($src)==='')throw new FormulaError('Escribe una fórmula.',0);
  if(mb_strlen($src)>self::MAX_LEN)throw new FormulaError('La fórmula es muy larga (máximo '.self::MAX_LEN.' caracteres).',self::MAX_LEN);
  $p=new self();$p->t=self::tokens($src);$p->i=0;
  $ast=$p->comparacion();
  if($p->i<count($p->t)){$tk=$p->t[$p->i];throw new FormulaError($tk['v']===')'?'Sobra un paréntesis de cierre.':'No se esperaba «'.$tk['v'].'» aquí.',$tk['p']);}
  return $ast;
 }

 /* ---------- lectura del texto ---------- */
 private static function tokens(string $s):array{
  $out=[];$n=mb_strlen($s);$c=fn(int $k)=>$k<$n?mb_substr($s,$k,1):'';$k=0;
  while($k<$n){
   $ch=$c($k);
   if(preg_match('/\s/u',$ch)){$k++;continue;}
   if(preg_match('/[0-9]/',$ch)){
    $st=$k;$num='';while(preg_match('/[0-9]/',$c($k))){$num.=$c($k);$k++;}
    if(($c($k)===','||$c($k)==='.')&&preg_match('/[0-9]/',$c($k+1))){$num.='.';$k++;while(preg_match('/[0-9]/',$c($k))){$num.=$c($k);$k++;}}
    $v=(float)$num;if($c($k)==='%'){$v/=100;$k++;}
    $out[]=['k'=>'num','v'=>$v,'p'=>$st];continue;
   }
   if($ch==='{'){
    $st=$k;$k++;$txt='';while($k<$n&&$c($k)!=='}'){if($c($k)==='{')throw new FormulaError('Falta cerrar «}» antes de abrir otra llave.',$k);$txt.=$c($k);$k++;}
    if($k>=$n)throw new FormulaError('Falta cerrar la llave «}».',$st);
    $k++;$partes=array_map('trim',explode('·',$txt,2));$nombre=$partes[0];
    if($nombre===''||mb_strlen($nombre)>80)throw new FormulaError('Escribe el nombre de una cifra o variable dentro de las llaves.',$st);
    $out[]=['k'=>'ref','v'=>$nombre,'mod'=>$partes[1]??'','p'=>$st];continue;
   }
   if(preg_match('/[\p{L}_]/u',$ch)){
    $st=$k;$w='';while(preg_match('/[\p{L}\p{N}_]/u',$c($k))){$w.=$c($k);$k++;}
    $out[]=['k'=>'id','v'=>self::norm($w),'raw'=>$w,'p'=>$st];continue;
   }
   $dos=$ch.$c($k+1);
   if(in_array($dos,['>=','<=','<>','!='],true)){$out[]=['k'=>'op','v'=>['>='=>'>=','<='=>'<=','<>'=>'<>','!='=>'<>'][$dos],'p'=>$k];$k+=2;continue;}
   $map=['+'=>'+','-'=>'-','−'=>'-','*'=>'*','×'=>'*','/'=>'/','÷'=>'/','^'=>'^','='=>'=','>'=>'>','<'=>'<','≥'=>'>=','≤'=>'<=','≠'=>'<>','('=>'(',')'=>')',';'=>';'];
   if(isset($map[$ch])){$out[]=['k'=>in_array($map[$ch],['(',')',';'],true)?$map[$ch]:'op','v'=>$map[$ch],'p'=>$k];$k++;continue;}
   throw new FormulaError('No se reconoce «'.$ch.'». Usa números, {cifras}, funciones y + − × ÷ ^.',$k);
  }
  return $out;
 }
 public static function norm(string $w):string{$w=mb_strtoupper($w);return strtr($w,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);}

 /* ---------- árbol (descenso recursivo con precedencias) ---------- */
 private function pk(int $o=0):?array{return $this->t[$this->i+$o]??null;}
 private function nodo(array $n):array{if(++$this->nodes>self::MAX_NODES)throw new FormulaError('La fórmula es demasiado compleja.',0);return $n;}
 private function hondo(int $p):void{if(++$this->depth>self::MAX_DEPTH)throw new FormulaError('Hay demasiados paréntesis o funciones anidadas.',$p);}
 private function comparacion():array{
  $l=$this->suma();$t=$this->pk();
  if($t&&$t['k']==='op'&&in_array($t['v'],['=','<>','>','<','>=','<='],true)){$this->i++;$r=$this->suma();
   $n=$this->pk();if($n&&$n['k']==='op'&&in_array($n['v'],['=','<>','>','<','>=','<='],true))throw new FormulaError('Para unir dos comparaciones usa Y(…) u O(…).',$n['p']);
   return $this->nodo(['b',$t['v'],$l,$r,$t['p']]);}
  return $l;
 }
 private function suma():array{$l=$this->producto();while(($t=$this->pk())&&$t['k']==='op'&&in_array($t['v'],['+','-'],true)){$this->i++;$l=$this->nodo(['b',$t['v'],$l,$this->producto(),$t['p']]);}return $l;}
 private function producto():array{$l=$this->unario();while(($t=$this->pk())&&$t['k']==='op'&&in_array($t['v'],['*','/'],true)){$this->i++;$l=$this->nodo(['b',$t['v'],$l,$this->unario(),$t['p']]);}return $l;}
 private function unario():array{$t=$this->pk();if($t&&$t['k']==='op'&&in_array($t['v'],['+','-'],true)){$this->i++;$this->hondo($t['p']);$x=$this->unario();$this->depth--;return $t['v']==='-'?$this->nodo(['u','-',$x,$t['p']]):$x;}return $this->potencia();}
 private function potencia():array{$b=$this->primario();$t=$this->pk();if($t&&$t['k']==='op'&&$t['v']==='^'){$this->i++;$this->hondo($t['p']);$e=$this->unario();$this->depth--;return $this->nodo(['b','^',$b,$e,$t['p']]);}return $b;}
 private function primario():array{
  $t=$this->pk();if(!$t){$u=end($this->t);throw new FormulaError('La fórmula termina antes de tiempo.',$u?$u['p']+1:0);}
  if($t['k']==='num'){$this->i++;return $this->nodo(['n',$t['v']]);}
  if($t['k']==='ref'){$this->i++;return $this->nodo(['r',$t['v'],$t['mod'],$t['p']]);}
  if($t['k']==='('){$this->i++;$this->hondo($t['p']);$x=$this->comparacion();$this->depth--;$c=$this->pk();if(!$c||$c['k']!==')')throw new FormulaError('Falta cerrar un paréntesis.',$t['p']);$this->i++;return $x;}
  if($t['k']==='id'){
   $this->i++;$nom=$t['v'];if(!isset(self::FUNCIONES[$nom]))throw new FormulaError('No existe la función «'.$t['raw'].'». Para una cifra o variable escríbela entre llaves: {'.$t['raw'].'}.',$t['p']);
   $a=$this->pk();if(!$a||$a['k']!=='(')throw new FormulaError('Después de '.$t['raw'].' van paréntesis, por ejemplo '.$t['raw'].'(…).',$t['p']);
   $this->i++;$this->hondo($a['p']);$args=[];
   if(($c=$this->pk())&&$c['k']===')'){$this->i++;}
   else{while(true){$args[]=$this->comparacion();$c=$this->pk();if($c&&$c['k']===';'){$this->i++;continue;}if($c&&$c['k']===')'){$this->i++;break;}throw new FormulaError('Separa los datos de '.$t['raw'].' con «;» y ciérrala con «)».',$c['p']??$t['p']);}}
   $this->depth--;[$min,$max]=self::FUNCIONES[$nom];
   if(count($args)<$min||($max>=0&&count($args)>$max))throw new FormulaError($t['raw'].' necesita '.($min===$max?$min:($max<0?"al menos $min":"entre $min y $max")).' dato'.(($max<0?$min:$max)===1?'':'s').'.',$t['p']);
   return $this->nodo(['f',$nom,$args,$t['p']]);
  }
  if($t['k']===')')throw new FormulaError('Falta un dato antes de «)».',$t['p']);
  throw new FormulaError('Falta un dato antes de «'.$t['v'].'».',$t['p']);
 }

 /** Las cifras y variables que usa (para validar y para pedirlas todas juntas). */
 public static function refs(array $n):array{
  return match($n[0]){'r'=>[[$n[1],$n[2],$n[3]]],'u'=>self::refs($n[2]),'b'=>array_merge(self::refs($n[2]),self::refs($n[3])),'f'=>array_merge(...array_map([self::class,'refs'],$n[2]?:[['n',0]])),default=>[]};
 }

 /* ---------- funciones: nombre => [mínimo, máximo (−1 = sin tope)] ---------- */
 public const FUNCIONES=[
  'SI'=>[2,3],'Y'=>[1,-1],'O'=>[1,-1],'NO'=>[1,1],'ENTRE'=>[3,3],
  'MIN'=>[1,-1],'MAX'=>[1,-1],'ABS'=>[1,1],'REDONDEAR'=>[1,2],'TRUNCAR'=>[1,2],'RAIZ'=>[1,1],'POTENCIA'=>[2,2],'LOG'=>[1,2],'LN'=>[1,1],'EXP'=>[1,1],'MOD'=>[2,2],
  'PROMEDIO'=>[1,-1],'MEDIANA'=>[1,-1],'DESVIACION'=>[2,-1],'SUMA'=>[1,-1],
  'CRECIMIENTO'=>[2,2],'PORCENTAJE'=>[2,2],
  // financieras (la tasa por período, como fracción o con %: 2% = 0,02)
  'PAGO'=>[3,5],'VF'=>[3,5],'VA'=>[3,5],'NPER'=>[3,3],'INTERES'=>[3,3],'INTERES_COMPUESTO'=>[3,3],
  // fechas (una fecha es un día; restar fechas da días)
  'HOY'=>[0,0],'FECHA'=>[3,3],'DIA'=>[1,1],'MES'=>[1,1],'ANO'=>[1,1],'DIAS'=>[2,2],'SEMANA'=>[1,1],'DIAS_MES'=>[0,1],'DIAS_RESTANTES_MES'=>[0,1],
  'INICIO_MES'=>[0,1],'FIN_MES'=>[0,1],'DIAS_HABILES'=>[2,2],'INICIO_PERIODO'=>[0,0],'FIN_PERIODO'=>[0,0],
 ];
 /** Ayuda para el editor: firma y ejemplo de cada función. */
 public const AYUDA=[
  'SI'=>'SI(condición; si sí; si no)','Y'=>'Y(a; b; …)','O'=>'O(a; b; …)','NO'=>'NO(a)','ENTRE'=>'ENTRE(valor; desde; hasta)',
  'MIN'=>'MIN(a; b; …)','MAX'=>'MAX(a; b; …)','ABS'=>'ABS(x)','REDONDEAR'=>'REDONDEAR(x; decimales)','TRUNCAR'=>'TRUNCAR(x; decimales)','RAIZ'=>'RAIZ(x)','POTENCIA'=>'POTENCIA(base; exponente)',
  'LOG'=>'LOG(x; base = 10)','LN'=>'LN(x)','EXP'=>'EXP(x)','MOD'=>'MOD(a; b)','PROMEDIO'=>'PROMEDIO(a; b; …)','MEDIANA'=>'MEDIANA(a; b; …)','DESVIACION'=>'DESVIACION(a; b; …)','SUMA'=>'SUMA(a; b; …)',
  'CRECIMIENTO'=>'CRECIMIENTO(actual; anterior) → % de cambio','PORCENTAJE'=>'PORCENTAJE(parte; total) → %',
  'PAGO'=>'PAGO(tasa; períodos; monto; final; al inicio) → cuota','VF'=>'VF(tasa; períodos; pago; inicial; al inicio) → valor futuro','VA'=>'VA(tasa; períodos; pago; final; al inicio) → valor actual',
  'NPER'=>'NPER(tasa; pago; monto) → cuántos pagos','INTERES'=>'INTERES(capital; tasa; períodos) → interés simple','INTERES_COMPUESTO'=>'INTERES_COMPUESTO(capital; tasa; períodos)',
  'HOY'=>'HOY()','FECHA'=>'FECHA(año; mes; día)','DIA'=>'DIA(fecha)','MES'=>'MES(fecha)','ANO'=>'AÑO(fecha)','DIAS'=>'DIAS(fin; inicio)','SEMANA'=>'SEMANA(fecha)',
  'DIAS_MES'=>'DIAS_MES(fecha = hoy)','DIAS_RESTANTES_MES'=>'DIAS_RESTANTES_MES(fecha = hoy)','INICIO_MES'=>'INICIO_MES(fecha = hoy)','FIN_MES'=>'FIN_MES(fecha = hoy)',
  'DIAS_HABILES'=>'DIAS_HABILES(desde; hasta)','INICIO_PERIODO'=>'INICIO_PERIODO()','FIN_PERIODO'=>'FIN_PERIODO()',
 ];

 /* ---------- cálculo ---------- */
 /**
  * $resolver(nombre, modificador, posición) devuelve ['v'=>float|null,'u'=>unidad,'m'=>motivo].
  * $ctx: ['hoy'=>'YYYY-MM-DD','desde'=>'YYYY-MM-DD','hasta'=>'YYYY-MM-DD'].
  * Devuelve ['v'=>float|null,'u'=>unidad,'m'=>motivo si v es null].
  */
 public static function evaluar(array $n,callable $resolver,array $ctx=[]):array{
  $r=self::ev($n,$resolver,$ctx);if($r['u']==='n')$r['u']='numero';
  if($r['v']!==null&&(is_nan($r['v'])||is_infinite($r['v'])))return ['v'=>null,'u'=>$r['u'],'m'=>'El resultado no es un número válido.'];
  return $r;
 }
 private static function val(?float $v,string $u,string $m=''):array{return ['v'=>$v,'u'=>$u,'m'=>$m];}
 private static function nulo(array ...$xs):?array{foreach($xs as $x)if($x['v']===null)return $x;return null;}
 private static function frac(array $x,array $otro):float{return $x['u']==='porcentaje'&&$otro['u']!=='porcentaje'?$x['v']/100:$x['v'];}
 private const NOMBRE_U=['dinero'=>'dinero','numero'=>'un número','porcentaje'=>'un porcentaje','fecha'=>'una fecha','bool'=>'un sí/no'];
 private static function ev(array $n,callable $res,array $ctx):array{
  switch($n[0]){
   case 'n':return self::val((float)$n[1],'n');
   case 'r':$r=$res($n[1],$n[2],$n[3]);return self::val($r['v']===null?null:(float)$r['v'],$r['u']??'numero',$r['m']??'Sin dato.');
   case 'u':$x=self::ev($n[2],$res,$ctx);if($x['u']==='fecha'||$x['u']==='bool')throw new FormulaError('No se le puede cambiar el signo a '.self::NOMBRE_U[$x['u']].'.',$n[3]);return self::val($x['v']===null?null:-$x['v'],$x['u'],$x['m']);
   case 'b':return self::binario($n,$res,$ctx);
   case 'f':return self::funcion($n,$res,$ctx);
  }
  throw new FormulaError('Fórmula no válida.',0);
 }
 private static function binario(array $n,callable $res,array $ctx):array{
  [, $op,$l,$r,$pos]=$n;$a=self::ev($l,$res,$ctx);$b=self::ev($r,$res,$ctx);$ua=$a['u'];$ub=$b['u'];
  $mal=fn(string $q)=>throw new FormulaError($q,$pos);
  if(in_array($op,['+','-'],true)){
   if($ua==='bool'||$ub==='bool')$mal('No se suman ni restan resultados de sí/no.');
   if($ua==='fecha'&&$ub==='fecha'){$u=$op==='-'?'numero':$mal('No se suman dos fechas. Para saber los días entre ellas, réstalas.');}
   elseif($ua==='fecha'||$ub==='fecha'){$otro=$ua==='fecha'?$ub:$ua;if(!in_array($otro,['n','numero'],true))$mal('A una fecha solo se le suman o restan días.');if($ub==='fecha'&&$op==='-')$mal('No se resta una fecha de un número.');$u='fecha';}
   elseif($ua==='n')$u=$ub;elseif($ub==='n')$u=$ua;
   elseif($ua!==$ub)$mal('No se puede '.($op==='+'?'sumar ':'restar ').self::NOMBRE_U[$ua].' con '.self::NOMBRE_U[$ub].'.');
   else $u=$ua;
   if($x=self::nulo($a,$b))return self::val(null,$u,$x['m']);
   return self::val($op==='+'?$a['v']+$b['v']:$a['v']-$b['v'],$u);
  }
  if(in_array($op,['*','/'],true)){
   if(in_array('fecha',[$ua,$ub],true)||in_array('bool',[$ua,$ub],true))$mal('Las fechas y los sí/no no se multiplican ni dividen.');
   if($op==='*'){
    if($ua==='dinero'&&$ub==='dinero')$mal('No se multiplican dos montos de dinero.');
    $u=$ua==='dinero'||$ub==='dinero'?'dinero':($ua==='porcentaje'&&$ub==='porcentaje'?'porcentaje':($ua==='n'?$ub:($ub==='n'?$ua:($ua==='porcentaje'?$ub:($ub==='porcentaje'?$ua:'numero')))));
    if($x=self::nulo($a,$b))return self::val(null,$u,$x['m']);
    $v=$ua==='porcentaje'&&$ub==='porcentaje'?$a['v']*$b['v']/100:self::frac($a,$b)*self::frac($b,$a);return self::val($v,$u);
   }
   $u=$ua==='dinero'&&$ub==='dinero'?'numero':($ua==='dinero'?'dinero':($ub==='n'?$ua:($ua==='n'&&$ub==='porcentaje'?'n':($ua===$ub?'numero':($ua==='porcentaje'?'numero':$ua)))));
   if($ua==='n'&&$ub==='dinero')$u='numero';
   if($x=self::nulo($a,$b))return self::val(null,$u,$x['m']);
   $den=self::frac($b,$a);if(abs($den)<1e-12)return self::val(null,$u,'División entre cero.');
   return self::val(self::frac($a,$b)/$den,$u);
  }
  if($op==='^'){
   if(!in_array($ub,['n','numero'],true))$mal('El exponente debe ser un número.');
   if(in_array($ua,['fecha','bool'],true))$mal('Las fechas y los sí/no no se elevan a una potencia.');
   if($x=self::nulo($a,$b))return self::val(null,$ua==='n'?'n':'numero',$x['m']);
   if($a['v']<0&&floor($b['v'])!=$b['v'])return self::val(null,'numero','Un número negativo no se eleva a un exponente con decimales.');
   if($a['v']==0.0&&$b['v']<0)return self::val(null,'numero','División entre cero.');
   return self::val($a['v']**$b['v'],$ua==='n'?'n':'numero');
  }
  // comparaciones
  if($ua!=='n'&&$ub!=='n'&&$ua!==$ub)$mal('No se compara '.self::NOMBRE_U[$ua].' con '.self::NOMBRE_U[$ub].'.');
  if($x=self::nulo($a,$b))return self::val(null,'bool',$x['m']);
  $va=round($a['v'],9);$vb=round($b['v'],9);
  $ok=match($op){'='=>$va==$vb,'<>'=>$va!=$vb,'>'=>$va>$vb,'<'=>$va<$vb,'>='=>$va>=$vb,'<='=>$va<=$vb};
  return self::val($ok?1.0:0.0,'bool');
 }
 private static function dia(string $ymd):float{return (float)intdiv((int)gmmktime(12,0,0,(int)substr($ymd,5,2),(int)substr($ymd,8,2),(int)substr($ymd,0,4)),86400);}
 private static function ymd(float $d):array{$t=(int)round($d)*86400+43200;return [(int)gmdate('Y',$t),(int)gmdate('n',$t),(int)gmdate('j',$t)];}
 private static function hoy(array $ctx):float{$h=(string)($ctx['hoy']??'');return self::dia(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$h)?$h:gmdate('Y-m-d'));}
 private static function funcion(array $n,callable $res,array $ctx):array{
  [, $f,$args,$pos]=$n;$mal=fn(string $q)=>throw new FormulaError($q,$pos);
  // SI evalúa solo la rama elegida (una división entre cero en la otra rama no importa)
  if($f==='SI'){$c=self::ev($args[0],$res,$ctx);if($c['v']===null)return self::val(null,'n',$c['m']);$x=$c['v']!=0.0?self::ev($args[1],$res,$ctx):(isset($args[2])?self::ev($args[2],$res,$ctx):self::val(0.0,'n'));return $x;}
  $xs=array_map(fn($a)=>self::ev($a,$res,$ctx),$args);
  $num=function(int $k,string $nombre='')use($xs,$mal,$f){$x=$xs[$k];if(in_array($x['u'],['fecha','bool'],true))$mal('El dato '.($k+1).' de '.$f.' debe ser un número'.($nombre?" ($nombre)":'').'.');return $x;};
  $fecha=function(int $k)use($xs,$mal,$f){$x=$xs[$k];if($x['u']!=='fecha')$mal('El dato '.($k+1).' de '.$f.' debe ser una fecha (por ejemplo HOY() o FECHA(2026; 11; 21)).');return $x;};
  $unidad=function()use($xs,$mal,$f):string{$u='n';foreach($xs as $x){if($x['u']==='n')continue;if($u==='n')$u=$x['u'];elseif($u!==$x['u'])$mal('Los datos de '.$f.' deben ser del mismo tipo.');}return $u;};
  if($x=self::nulo(...$xs)){$u=in_array($f,['Y','O','NO','ENTRE'],true)?'bool':(in_array($f,['CRECIMIENTO','PORCENTAJE'],true)?'porcentaje':'n');return self::val(null,$u,$x['m']);}
  $v=array_map(fn($x)=>$x['v'],$xs);
  switch($f){
   case 'Y':return self::val((float)!in_array(0.0,$v,true),'bool');
   case 'O':return self::val((float)(count(array_filter($v,fn($q)=>$q!=0.0))>0),'bool');
   case 'NO':return self::val($v[0]==0.0?1.0:0.0,'bool');
   case 'ENTRE':$unidad();return self::val((float)($v[0]>=min($v[1],$v[2])&&$v[0]<=max($v[1],$v[2])),'bool');
   case 'MIN':return self::val(min($v),$unidad());case 'MAX':return self::val(max($v),$unidad());
   case 'SUMA':$u=$unidad();if($u==='fecha')$mal('No se suman fechas.');return self::val(array_sum($v),$u);
   case 'PROMEDIO':$u=$unidad();return self::val(array_sum($v)/count($v),$u);
   case 'MEDIANA':$u=$unidad();sort($v);$c=count($v);return self::val($c%2?$v[intdiv($c,2)]:($v[$c/2-1]+$v[$c/2])/2,$u);
   case 'DESVIACION':$u=$unidad();$m=array_sum($v)/count($v);return self::val(sqrt(array_sum(array_map(fn($q)=>($q-$m)**2,$v))/(count($v)-1)),$u);
   case 'ABS':$num(0);return self::val(abs($v[0]),$xs[0]['u']);
   case 'REDONDEAR':case 'TRUNCAR':$num(0);$d=isset($v[1])?(int)$v[1]:0;if($d<0||$d>8)$mal('Los decimales van de 0 a 8.');$p=10**$d;
    return self::val($f==='REDONDEAR'?round($v[0],$d):($v[0]<0?ceil($v[0]*$p)/$p:floor($v[0]*$p)/$p),$xs[0]['u']);
   case 'RAIZ':$num(0);if($v[0]<0)return self::val(null,'numero','No hay raíz de un número negativo.');return self::val(sqrt($v[0]),$xs[0]['u']==='n'?'n':'numero');
   case 'POTENCIA':$num(0);$num(1);if($v[0]<0&&floor($v[1])!=$v[1])return self::val(null,'numero','Un número negativo no se eleva a un exponente con decimales.');if($v[0]==0.0&&$v[1]<0)return self::val(null,'numero','División entre cero.');return self::val($v[0]**$v[1],$xs[0]['u']==='n'?'n':'numero');
   case 'LOG':$num(0);$b=$v[1]??10.0;if($v[0]<=0||$b<=0||$b==1.0)return self::val(null,'numero','El logaritmo necesita números positivos (y una base distinta de 1).');return self::val(log($v[0],$b),'numero');
   case 'LN':$num(0);if($v[0]<=0)return self::val(null,'numero','El logaritmo necesita un número positivo.');return self::val(log($v[0]),'numero');
   case 'EXP':$num(0);if($v[0]>700)return self::val(null,'numero','El número es demasiado grande.');return self::val(exp($v[0]),'numero');
   case 'MOD':$num(0);$num(1);if($v[1]==0.0)return self::val(null,'numero','División entre cero.');return self::val(fmod($v[0],$v[1]),$xs[0]['u']==='n'?'n':$xs[0]['u']);
   case 'CRECIMIENTO':$unidad();if($v[1]==0.0)return self::val(null,'porcentaje','No hay base para comparar (el anterior es cero).');return self::val(($v[0]-$v[1])/abs($v[1])*100,'porcentaje');
   case 'PORCENTAJE':$unidad();if($v[1]==0.0)return self::val(null,'porcentaje','División entre cero.');return self::val($v[0]/$v[1]*100,'porcentaje');
   // ---- financieras
   case 'PAGO':case 'VF':case 'VA':
    foreach(array_keys($xs) as $k)$num($k);
    $t=self::frac($xs[0],['u'=>'x']);$np=$v[1];$x=$v[2];$extra=$v[3]??0.0;$tipo=($v[4]??0.0)!=0.0?1.0:0.0;
    if($np<=0)return self::val(null,'dinero','La cantidad de períodos debe ser mayor que cero.');
    $u=$xs[2]['u']==='dinero'||($xs[3]['u']??'')==='dinero'?'dinero':'n';
    if($f==='PAGO'){if(abs($t)<1e-12)return self::val(($x+$extra)/$np,$u);$q=(1+$t)**$np;return self::val($t*($x*$q+$extra)/(($q-1)*(1+$t*$tipo)),$u);}
    if($f==='VF'){$q=(1+$t)**$np;$fv=abs($t)<1e-12?$extra+$x*$np:$extra*$q+$x*(1+$t*$tipo)*($q-1)/$t;return self::val($fv,$u);}
    $q=(1+$t)**(-$np);$pv=abs($t)<1e-12?$x*$np+$extra:$x*(1+$t*$tipo)*(1-$q)/$t+$extra*$q;return self::val($pv,$u);
   case 'NPER':$num(0);$num(1);$num(2);$t=self::frac($xs[0],['u'=>'x']);$pmt=$v[1];$pv=$v[2];
    if($pmt<=0||$pv<0)return self::val(null,'numero','El pago debe ser mayor que cero.');
    if(abs($t)<1e-12)return self::val($pv/$pmt,'numero');if($pmt<=$t*$pv)return self::val(null,'numero','Con ese pago la deuda nunca se termina de pagar.');
    return self::val(log($pmt/($pmt-$t*$pv))/log(1+$t),'numero');
   case 'INTERES':$num(0);$num(1);$num(2);return self::val($v[0]*self::frac($xs[1],['u'=>'x'])*$v[2],$xs[0]['u']==='dinero'?'dinero':'n');
   case 'INTERES_COMPUESTO':$num(0);$num(1);$num(2);return self::val($v[0]*((1+self::frac($xs[1],['u'=>'x']))**$v[2]-1),$xs[0]['u']==='dinero'?'dinero':'n');
   // ---- fechas
   case 'HOY':return self::val(self::hoy($ctx),'fecha');
   case 'INICIO_PERIODO':case 'FIN_PERIODO':$k=$f==='INICIO_PERIODO'?'desde':'hasta';$d=(string)($ctx[$k]??'');return preg_match('/^\d{4}-\d{2}-\d{2}$/D',$d)?self::val(self::dia($d),'fecha'):self::val(null,'fecha','El widget no tiene un período elegido.');
   case 'FECHA':foreach([0,1,2] as $k)$num($k);[$y,$m,$d]=array_map('intval',$v);if($y<1900||$y>2200||$m<1||$m>12||$d<1||!checkdate($m,$d,$y))return self::val(null,'fecha','Esa fecha no existe.');return self::val(self::dia(sprintf('%04d-%02d-%02d',$y,$m,$d)),'fecha');
   case 'DIA':case 'MES':case 'ANO':$fecha(0);[$y,$m,$d]=self::ymd($v[0]);return self::val((float)($f==='DIA'?$d:($f==='MES'?$m:$y)),'numero');
   case 'SEMANA':$fecha(0);return self::val((float)gmdate('W',(int)round($v[0])*86400+43200),'numero');
   case 'DIAS':$fecha(0);$fecha(1);return self::val($v[0]-$v[1],'numero');
   case 'DIAS_MES':case 'DIAS_RESTANTES_MES':case 'INICIO_MES':case 'FIN_MES':
    $base=isset($xs[0])?($fecha(0))['v']:self::hoy($ctx);[$y,$m,$d]=self::ymd($base);$dm=cal_days_in_month_safe($m,$y);
    if($f==='DIAS_MES')return self::val((float)$dm,'numero');if($f==='DIAS_RESTANTES_MES')return self::val((float)($dm-$d),'numero');
    return self::val(self::dia(sprintf('%04d-%02d-%02d',$y,$m,$f==='INICIO_MES'?1:$dm)),'fecha');
   case 'DIAS_HABILES':$fecha(0);$fecha(1);$a=(int)round(min($v[0],$v[1]));$b=(int)round(max($v[0],$v[1]));if($b-$a>3700)$mal('El rango de fechas es demasiado largo.');
    $c=0;for($d=$a;$d<=$b;$d++){$w=(int)gmdate('N',$d*86400+43200);if($w<6)$c++;}return self::val((float)$c,'numero');
  }
  $mal('Función no disponible.');
 }
}
/** Días del mes sin depender de la extensión calendar. */
function cal_days_in_month_safe(int $m,int $y):int{return (int)gmdate('t',gmmktime(12,0,0,$m,1,$y));}
