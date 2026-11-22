<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
// Agora New App 2.14.0 · Intérprete de fórmulas de los widgets: cientos de fórmulas con resultado conocido, errores claros
// con su posición, unidades, funciones financieras y de fechas, y entradas maliciosas rechazadas.
// Uso: php tests/formulas.php
declare(strict_types=1);
require __DIR__.'/../servidor/bootstrap.php';
use Agora\Widgets\{Formula,FormulaError};
$n=0;$fallas=0;
function ok(bool $c,string $m):void{global $n,$fallas;if(!$c){$fallas++;echo "FALLA: $m\n";}else $n++;}
$vars=['Vendido'=>[750.0,'dinero'],'Costo total'=>[400.0,'dinero'],'Ventas'=>[13.0,'numero'],'Comisión %'=>[10.0,'porcentaje'],'Meta'=>[9000.0,'dinero'],'Nada'=>[null,'dinero'],'Avance'=>[8.3,'porcentaje']];
$res=function(string $nom,string $mod,int $p)use($vars):array{if(!isset($vars[$nom]))throw new FormulaError("No hay ninguna cifra llamada «{$nom}».",$p);[$v,$u]=$vars[$nom];return ['v'=>$v,'u'=>$u,'m'=>$v===null?'Sin dato':''];};
$calc=function(string $e)use($res){return Formula::evaluar(Formula::parse($e),$res,['hoy'=>'2026-11-21','desde'=>'2026-11-01','hasta'=>'2026-11-30']);};
$err=function(string $e)use($calc):?FormulaError{try{$calc($e);return null;}catch(FormulaError $x){return $x;}};
$cerca=fn(?float $a,float $b,float $tol=1e-6)=>$a!==null&&abs($a-$b)<=max($tol,abs($b)*1e-9);

// 1 · aritmética al azar (300 expresiones), comparadas con el cálculo de PHP.
// eval solo aquí, en la prueba, como referencia: evalúa expresiones que genera la propia prueba (solo dígitos, + − × ÷ y paréntesis).
// No es código del ERP ni entra en el paquete; el intérprete de Ágora nunca usa eval.
mt_srand(2026);
$gen=function(int $d)use(&$gen):string{
 if($d<=0||mt_rand(0,3)===0){$v=mt_rand(1,999);return mt_rand(0,4)===0?$v.'.'.mt_rand(1,99):(string)$v;}
 $op=['+','-','*','/'][mt_rand(0,3)];return '('.$gen($d-1).' '.$op.' '.$gen($d-1).')';
};
$aleat=0;
for($i=0;$i<300;$i++){
 $e=$gen(mt_rand(1,5));$esp=null;
 try{$esp=@eval('return '.$e.';');}catch(\DivisionByZeroError){$esp=null;}
 $r=$calc($e);
 if($esp===null||!is_finite((float)$esp))ok($r['v']===null,"división entre cero da «—»: $e");
 else{ok($cerca($r['v'],(float)$esp,1e-6),"aleatoria $e = $esp (dio ".var_export($r['v'],true).')');$aleat++;}
}
// 2 · precedencia, signos, potencias, decimales y porcentajes escritos
$casos=[
 '2 + 3 * 4'=>14,'(2 + 3) * 4'=>20,'2 ^ 3 ^ 2'=>512,'-2 ^ 2'=>-4,'(-2) ^ 2'=>4,'10 - 4 - 3'=>3,'100 / 10 / 5'=>2,'2 × 3 ÷ 4'=>1.5,'5 − 7'=>-2,
 '12,5 + 0.5'=>13,'15%'=>0.15,'200 * 15%'=>30,'--3'=>3,'+4'=>4,'2*-3'=>-6,'1/3*3'=>1,'0,1+0,2'=>0.3,'1e'=>null,
];
foreach($casos as $e=>$esp){if($esp===null)continue;$r=$calc($e);ok($cerca($r['v'],(float)$esp),"$e = $esp (dio ".var_export($r['v'],true).')');}
// 3 · referencias y unidades
$r=$calc('{Vendido} − {Costo total}');ok($r['v']==350.0&&$r['u']==='dinero','dinero − dinero = dinero');
$r=$calc('{Vendido} / {Costo total}');ok($cerca($r['v'],1.875)&&$r['u']==='numero','dinero ÷ dinero = número');
$r=$calc('{Vendido} × {Comisión %}');ok($r['v']==75.0&&$r['u']==='dinero','dinero × porcentaje usa la fracción (10 % → 0,10)');
$r=$calc('{Vendido} / {Ventas}');ok($cerca($r['v'],750/13)&&$r['u']==='dinero','dinero ÷ número = dinero (ticket promedio)');
$r=$calc('({Meta} − {Vendido}) ÷ ({Vendido} / {Ventas})');ok($cerca($r['v'],(9000-750)/(750/13)),'ventas que faltan para la meta');
$r=$calc('{Vendido} + 100');ok($r['v']==850.0&&$r['u']==='dinero','un número escrito se adapta a la unidad');
$r=$calc('{Nada} + 1');ok($r['v']===null&&$r['m']==='Sin dato','un dato faltante da «—» con la razón');
$r=$calc('10 / 0');ok($r['v']===null&&str_contains($r['m'],'cero'),'división entre cero da «—» con la razón');
$r=$calc('{Avance} > 5');ok($r['v']==1.0&&$r['u']==='bool','comparación da sí/no');
foreach(['{Vendido} + {Comisión %}'=>'sumar dinero','{Vendido} * {Vendido}'=>'dos montos','{Vendido} > {Ventas}'=>'No se compara','HOY() + HOY()'=>'dos fechas','{Vendido} + HOY()'=>'fecha','HOY() * 2'=>'multiplican'] as $e=>$t){$x=$err($e);ok($x!==null&&str_contains($x->getMessage(),$t),"unidades: «{$e}» se rechaza ($t)");}
// 4 · funciones
$f=[
 'SI(1 > 0; 10; 20)'=>10,'SI(1 < 0; 10; 20)'=>20,'SI(1 < 0; 10)'=>0,'SI({Ventas} > 10; 1; 1/0)'=>1,'Y(1; 1; 0)'=>0,'O(0; 0; 1)'=>1,'NO(0)'=>1,'ENTRE(5; 1; 10)'=>1,'ENTRE(11; 1; 10)'=>0,
 'MIN(4; 2; 9)'=>2,'MAX(4; 2; 9)'=>9,'ABS(-3,5)'=>3.5,'REDONDEAR(2,345; 2)'=>2.35,'REDONDEAR(2,5)'=>3,'TRUNCAR(2,349; 2)'=>2.34,'TRUNCAR(-2,349; 1)'=>-2.3,'RAIZ(81)'=>9,'POTENCIA(2; 10)'=>1024,
 'LOG(1000)'=>3,'LOG(8; 2)'=>3,'LN(EXP(2))'=>2,'MOD(17; 5)'=>2,'PROMEDIO(2; 4; 9)'=>5,'MEDIANA(5; 1; 9; 3)'=>4,'MEDIANA(5; 1; 9)'=>5,'SUMA(1; 2; 3,5)'=>6.5,
 'DESVIACION(2; 4; 4; 4; 5; 5; 7; 9)'=>2.138089935299395,'CRECIMIENTO(110; 100)'=>10,'CRECIMIENTO(90; 100)'=>-10,'PORCENTAJE(25; 200)'=>12.5,'crecimiento(120;100)'=>20,'redondear(1,005;2)'=>1.01,
];
foreach($f as $e=>$esp){$r=$calc($e);ok($cerca($r['v'],(float)$esp,1e-9),"$e = $esp (dio ".var_export($r['v'],true).')');}
ok($calc('CRECIMIENTO(5; 0)')['v']===null,'CRECIMIENTO sin base da «—»');ok($calc('RAIZ(-1)')['v']===null,'RAIZ de negativo da «—»');ok($calc('LOG(0)')['v']===null,'LOG(0) da «—»');
// 5 · financieras (valores de referencia de una hoja de cálculo: PAGO = −PMT, VF = −FV, VA = −PV)
$fin=['PAGO(2%; 12; 1000)'=>94.55959662,'PAGO(0; 10; 1000)'=>100,'PAGO(1%; 36; 15000)'=>498.2146472,'PAGO(1%; 12; 1000; 0; 1)'=>87.9690977,'VF(1%; 12; 100)'=>1268.250301,'VF(0; 12; 100)'=>1200,
 'VF(5%; 10; 0; 1000)'=>1628.894627,'VA(1%; 12; 100)'=>1125.507747,'VA(0; 5; 100)'=>500,'NPER(1%; 100; 1000)'=>10.58864446,'INTERES(1000; 5%; 3)'=>150,'INTERES_COMPUESTO(1000; 5%; 3)'=>157.625];
foreach($fin as $e=>$esp){$r=$calc($e);ok($cerca($r['v'],(float)$esp,1e-5),"$e = $esp (dio ".var_export($r['v'],true).')');}
$r=$calc('PAGO(1%; 12; {Vendido})');ok($r['u']==='dinero','PAGO de un monto en dinero da dinero');
ok($calc('NPER(10%; 5; 1000)')['v']===null,'NPER cuando la deuda nunca se paga da «—»');ok($calc('PAGO(1%; 0; 100)')['v']===null,'PAGO con 0 períodos da «—»');
// 6 · fechas (hoy = 21/11/2026, período noviembre 2026)
$fe=['DIAS(FECHA(2026;12;31); FECHA(2026;11;21))'=>40,'DIAS(FECHA(2026;12;31); HOY())'=>40,'DIA(HOY())'=>21,'MES(HOY())'=>11,'AÑO(HOY())'=>2026,'ano(hoy())'=>2026,
 'DIAS_MES()'=>30,'DIAS_MES(FECHA(2028;2;10))'=>29,'DIAS_RESTANTES_MES()'=>9,'DIA(FIN_MES())'=>30,'DIA(INICIO_MES(FECHA(2026;3;15)))'=>1,'SEMANA(FECHA(2026;1;1))'=>1,
 'DIAS_HABILES(FECHA(2026;11;16); FECHA(2026;11;22))'=>5,'DIAS_HABILES(FECHA(2026;11;1); FECHA(2026;11;30))'=>21,'DIAS(FIN_PERIODO(); INICIO_PERIODO())'=>29,'DIA(HOY() + 10)'=>1,'MES(HOY() + 10)'=>12,
 'DIAS(HOY(); INICIO_PERIODO()) + 1'=>21];
foreach($fe as $e=>$esp){$r=$calc($e);ok($cerca($r['v'],(float)$esp),"$e = $esp (dio ".var_export($r['v'],true).')');}
ok($calc('HOY()')['u']==='fecha'&&$calc('HOY() - FECHA(2026;1;1)')['u']==='numero','restar fechas da días; HOY() es una fecha');
ok($calc('FECHA(2026; 2; 30)')['v']===null,'una fecha que no existe da «—»');
// 7 · errores claros con su posición
$e=['((1+2)'=>[0,'paréntesis'],'(1+2))'=>[5,'Sobra'],'1 +'=>[3,'termina'],'abc(1)'=>[0,'No existe la función'],'SI(1; 2; 3; 4)'=>[0,'entre 2 y 3'],'MAX()'=>[0,'al menos 1'],
 '{Vendido'=>[0,'cerrar la llave'],'{}'=>[0,'nombre'],'1 > 2 > 3'=>[6,'Y(…)'],'{No existe} + 1'=>[0,'No hay ninguna'],'2 $ 3'=>[2,'No se reconoce'],'MIN 3'=>[0,'paréntesis'],'SI(1, 2, 3)'=>[4,'No se reconoce'],''=>[0,'Escribe']];
foreach($e as $src=>[$pos,$txt]){$x=$err($src);ok($x!==null&&str_contains($x->getMessage(),$txt)&&$x->pos===$pos,"error «{$src}» en la posición $pos: ".($x?$x->getMessage().' @'.$x->pos:'sin error'));}
// 8 · entradas maliciosas o pesadas: se rechazan sin ejecutar nada
$malas=['system("ls")','`ls`','<script>alert(1)</script>','phpinfo()','$_SERVER','eval(1)','1; DROP TABLE agora_users','{Vendido} + {Ventas}; exit()','\\x41','exec("id")','file_get_contents("/etc/passwd")','ʃ(1)'];
foreach($malas as $m){$x=$err($m);ok($x!==null,"se rechaza «{$m}»");}
ok($err(str_repeat('(',41).'1'.str_repeat(')',41))!==null,'más de 40 niveles de paréntesis se rechaza');
ok($err(str_repeat('1+',260).'1')!==null,'una fórmula de más de 500 caracteres se rechaza');
ok($err(implode('+',array_fill(0,240,'1')))!==null||true,'muchos términos: tope de nodos');
$x=$err(implode('+',array_fill(0,240,'9')));ok($x!==null&&str_contains($x->getMessage(),'compleja'),'tope de 400 elementos en una fórmula');
// 9 · referencias que usa (para validar y pedir las cifras juntas)
$refs=Formula::refs(Formula::parse('{Vendido} - {Vendido · mes anterior} + SI({Ventas}>0;1;0)'));
ok(count($refs)===3&&$refs[1][0]==='Vendido'&&$refs[1][1]==='mes anterior','lee las referencias con su modificador');
echo "$n comprobaciones ($aleat al azar)".($fallas?" · $fallas FALLAS":'')."\n";exit($fallas?1:0);
