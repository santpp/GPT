<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Widgets;

/**
 * Agora New App 2.14.0 · Diccionario de métricas de los widgets.
 * Cada métrica NO se recalcula: dice de qué ruta de la API y de qué campo sale su número, y el servidor llama esa misma
 * ruta por dentro (con el mismo usuario y sus permisos). Así un widget da exactamente la cifra de la tarjeta de hoy.
 * `{moneda}` en la ruta del campo se cambia por la moneda de la subcuenta.
 */
final class Metricas {
 /** fuente => [ruta, parámetros (con {periodo} y {conexion}), ¿depende del período?, qué la pide] */
 public const FUENTES=[
  'mes'=>['workspace/board-month',['period'=>'{periodo}'],true,'Cifras del mes'],
  'ventas'=>['workspace/ventas',['mes'=>'{periodo}'],true,'Ventas registradas'],
  'cuenta'=>['workspace/dashboard',['connectionId'=>'{conexion}'],false,'La cuenta (hoy)'],
  'dia'=>['workspace/dia',['connectionId'=>'{conexion}'],false,'La cuenta (hoy)'],
  'ganadas'=>['workspace/won',['connectionId'=>'{conexion}'],false,'Oportunidades ganadas'],
  'tareas'=>['workspace/tareas',[],false,'Tareas'],
  'reporte'=>['workspace/reporte',[],true,'Reporte mensual'], // 2.16.0 · fase 6: cifras del reporte importado (en centavos)
 ];
 /** id => [nombre, unidad, fuente, campo, descripción] */
 public const LISTA=[
  'mes.vendido'=>['Vendido del mes','dinero','mes','sales.total','Lo ganado en el mes (cuenta y Ágora).'],
  'mes.cierres'=>['Cierres del mes','numero','mes','sales.closings','Ventas cerradas en el mes.'],
  'mes.meta'=>['Meta del mes','dinero','mes','goal.target','La meta del equipo para el mes.'],
  'mes.avance'=>['Avance de la meta','porcentaje','mes','goal.progress','Lo vendido frente a la meta.'],
  'mes.falta'=>['Falta para la meta','dinero','mes','goal.missing','Lo que falta vender para la meta.'],
  'mes.mis_ventas'=>['Mis ventas del mes','dinero','mes','mine.sold','Lo que vendiste tú en el mes.'],
  'mes.mis_cierres'=>['Mis cierres del mes','numero','mes','mine.closings','Tus ventas cerradas en el mes.'],
  'mes.anuncios'=>['Inversión en anuncios','dinero','mes','profitability.ads','Lo invertido en publicidad en el mes.'],
  'mes.comisiones'=>['Comisiones del equipo','dinero','mes','profitability.commissions','Comisiones del mes según las ventas.'],
  'mes.ganancia'=>['Ganancia después de publicidad y comisiones','dinero','mes','profitability.profit','Ventas menos publicidad y comisiones.'],
  'mes.costos_fijos'=>['Costos fijos','dinero','mes','profitability.fixedCosts','Costos fijos del mes.'],
  'mes.ganancia_real'=>['Ganancia real del mes','dinero','mes','profitability.net','Después de los costos fijos.'],
  'mes.costo_cierre'=>['Costo por cierre','dinero','mes','profitability.costPerClosing','Inversión por venta cerrada.'],
  'mes.retorno'=>['Ventas por cada 100 invertido','numero','mes','profitability.returnPerHundred','Retorno de lo invertido.'],
  'mes.mercadeo'=>['Costo de mercadeo y ventas','dinero','mes','profitability.marketingCost','Publicidad y comisiones del mes.'],
  'ventas.cantidad'=>['Ventas registradas','numero','ventas','resumen.cantidad','Ventas registradas en Ágora en el mes.'],
  'ventas.vendido'=>['Vendido registrado','dinero','ventas','resumen.vendido.{moneda}','Monto de las ventas registradas del mes.'],
  'ventas.cobrado'=>['Cobrado','dinero','ventas','resumen.cobrado.{moneda}','Lo cobrado de las ventas del mes.'],
  'ventas.por_cobrar'=>['Por cobrar','dinero','ventas','resumen.porCobrar.{moneda}','Lo que falta cobrar.'],
  'ventas.vencido'=>['Cobros vencidos','dinero','ventas','resumen.vencido.{moneda}','Cobros que ya vencieron.'],
  'cuenta.conversaciones'=>['Conversaciones','numero','cuenta','conversations.total','Conversaciones de la cuenta.'],
  'cuenta.sin_leer'=>['Conversaciones sin leer','numero','cuenta','conversations.unread','Las que nadie ha abierto.'],
  'cuenta.esperando'=>['Esperando respuesta','numero','cuenta','conversations.waiting','Donde el cliente escribió último.'],
  'cuenta.contactos'=>['Contactos','numero','cuenta','contacts','Contactos de la cuenta.'],
  'cuenta.leads_hoy'=>['Leads de hoy','numero','cuenta','leads.today','Contactos nuevos de hoy.'],
  'cuenta.oportunidades'=>['Oportunidades abiertas','numero','cuenta','opportunities.count','Negocios en curso.'],
  'cuenta.en_juego'=>['Valor en juego','dinero','cuenta','opportunities.value','Valor de las oportunidades abiertas.'],
  'dia.conversaciones'=>['Conversaciones del día','numero','dia','conversacionesDia','Conversaciones con actividad hoy.'],
  'dia.atendidas'=>['Conversaciones atendidas','numero','dia','atendidas','Con respuesta del equipo hoy.'],
  'dia.fuera_tiempo'=>['Fuera de tiempo','numero','dia','slaIncumplido','Pasaron la meta de respuesta.'],
  'dia.respuesta_seg'=>['Tiempo de respuesta (segundos)','numero','dia','slaPromedioSeg','Lo que tarda en promedio la primera respuesta.'],
  'dia.leads'=>['Leads del día','numero','dia','leads','Contactos nuevos del día.'],
  'ganadas.cantidad'=>['Oportunidades ganadas','numero','ganadas','totals.count','Ganadas en la cuenta.'],
  'ganadas.valor'=>['Valor ganado','dinero','ganadas','totals.sum','Valor de las ganadas.'],
  'ganadas.sin_monto'=>['Ganadas sin monto','numero','ganadas','totals.missing','Ganadas a las que les falta el monto.'],
  'tareas.pendientes'=>['Tareas pendientes','numero','tareas','resumen.pendientes','Seguimientos por hacer.'],
  'tareas.hechas'=>['Tareas hechas','numero','tareas','resumen.hechas','Seguimientos cerrados.'],
  // 2.16.0 · fase 6: del reporte mensual importado. El sexto dato divide (el reporte guarda centavos).
  'rep.vendido'=>['Vendido según el reporte','dinero','reporte','reports.{periodo}.gc.total.vendido','Monto total vendido en las hojas de las asesoras.',100],
  'rep.inscritos'=>['Inscritos del reporte','numero','reporte','reports.{periodo}.gc.total.inscritos','Inscritos del mes según el reporte.'],
  'rep.recurrencia'=>['Recurrencia del reporte','dinero','reporte','reports.{periodo}.gc.total.recurrencia','Recurrencia del mes según el reporte.',100],
  'rep.cuota1'=>['Primera cuota del reporte','dinero','reporte','reports.{periodo}.gc.total.cuota1','Suma de las primeras cuotas.',100],
  'rep.entradas'=>['Entradas del reporte','dinero','reporte','reports.{periodo}.gc.total.entradas','Entradas cobradas según el reporte.',100],
  'rep.pendiente'=>['Pendiente por cobrar del reporte','dinero','reporte','reports.{periodo}.gc.total.pendiente','Lo que falta por cobrar según el reporte.',100],
  'rep.comisiones'=>['Comisiones (10 %) del reporte','dinero','reporte','reports.{periodo}.gc.total.com10','Comisiones del departamento sobre la primera cuota.',100],
  'rep.anuncios'=>['Inversión en campañas del reporte','dinero','reporte','reports.{periodo}.ads.total.inversion','Inversión de las campañas cargadas en el reporte.',100],
  'rep.resultados'=>['Resultados de campañas','numero','reporte','reports.{periodo}.ads.total.resultados','Resultados (mensajes, contactos) de las campañas.'],
  'rep.ventas_campanas'=>['Ventas por campañas','numero','reporte','reports.{periodo}.ads.total.ventas','Ventas atribuidas a las campañas.'],
  'rep.cpl'=>['Costo por resultado del reporte','dinero','reporte','reports.{periodo}.ads.total.cpl','Inversión dividida entre resultados.',100],
  'rep.costos_fijos'=>['Gastos fijos del reporte','dinero','reporte','reports.{periodo}.costs.fijos.total','Nómina más herramientas y servicios.',100],
  'rep.costos_variables'=>['Gastos variables del reporte','dinero','reporte','reports.{periodo}.costs.variables.total','Inversión y comisiones del mes.',100],
  'rep.nomina'=>['Nómina del reporte','dinero','reporte','reports.{periodo}.costs.nomina.total','Total de la nómina del departamento.',100],
 ];
 public static function clave(string $nombre):string{return mb_substr(preg_replace('/\s+/u',' ',trim(Formula::norm($nombre))),0,60);}
 /** id de la métrica por su nombre (sin importar mayúsculas ni acentos), o null. */
 public static function porNombre(string $nombre):?string{static $idx=null;if($idx===null){$idx=[];foreach(self::LISTA as $id=>$m)$idx[self::clave($m[0])]=$id;}return $idx[self::clave($nombre)]??null;}
 /** Lee un campo con puntos («goal.progress») de una respuesta. */
 public static function campo(mixed $d,string $ruta):mixed{foreach(explode('.',$ruta) as $k){if(is_object($d))$d=(array)$d;if(!is_array($d)||!array_key_exists($k,$d))return null;$d=$d[$k];}return $d;}
}
