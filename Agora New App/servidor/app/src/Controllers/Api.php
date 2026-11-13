<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\{Records,Metrics};
final class Api {
 public Auth $a;public Users $u;public Agency $ag;public Workspace $w;public Contracts $c;public Records $r;public Metrics $metrics;public Personalizar $per;
 public function __construct(public DB $db,public array $config){$this->a=new Auth($db,$config);$this->u=new Users($db,$this->a);$this->ag=new Agency($db,$this->a);$this->w=new Workspace($db,$this->a);$this->c=new Contracts($db,$this->a);$this->r=$this->w->records;$this->metrics=new Metrics($db,$this->a);$this->per=new Personalizar($db,$this->a);}
 public function dispatch(string $p,string $m,array $b):mixed{
  if($m==='GET'&&$p==='health')return ['status'=>'ok','version'=>'2.3.0'];
  if($m==='GET'&&$p==='session/csrf')return ['csrf'=>$this->a->csrf()];
  if($m==='GET'&&$p==='status')return ['maintenance'=>false];
  if($m==='POST'&&$p==='login')return $this->a->login($b);
  if($p==='sign'&&in_array($m,['GET','POST']))return $this->c->publicSign($m,$b);
  $this->a->requireUser();
  if($m==='POST'&&$p==='logout')return $this->a->logout();
  if($m==='GET'&&$p==='yo')return ['usuario'=>$this->a->publicUser($this->a->user)];
  if($m==='GET'&&$p==='me')return $this->a->me();
  if($m==='POST'&&$p==='clave')return $this->u->password($b);
  if($p==='profile'&&in_array($m,['GET','PUT']))return $this->u->profile($m,$b);
  if($p==='ui/texts'&&in_array($m,['GET','PUT','DELETE']))return $this->ag->texts($m,$b);
  if($p==='workspace/connections'&&$m==='GET')return ['items'=>array_map(fn($t)=>['id'=>(int)$t['id'],'name'=>$t['subaccount'],'currency'=>$t['currency']],$this->a->tenants()),'reason'=>null];
  if($p==='workspace/active'&&$m==='POST'){$id=Http::integer($b['connectionId']??0,1,PHP_INT_MAX);$this->a->authorizeTenant($id);$this->db->run('UPDATE agora_sessions SET active_tenant_id=? WHERE token_hash=?',[$id,$this->a->session['token_hash']]);return ['ok'=>true,'accountId'=>$id];}
  if($p==='agency/accounts'&&in_array($m,['GET','POST','PUT']))return $this->ag->accounts($m,$b);
  if($p==='agency/billing'&&$m==='PUT')return $this->ag->billing($b);
  if($p==='agency/accounts/conexion/probar'&&$m==='POST')return $this->ag->ghlTest($b);
  if($p==='agency/team'&&in_array($m,['GET','POST','PUT']))return $m==='GET'?$this->u->listing(true):$this->u->save($b,true,$m==='PUT');
  if($p==='agency/activity'&&$m==='GET')return $this->ag->audit(true);
  // 2.1.17 · gestión unificada de usuarios (Master y Superadmin editan a cualquiera a su alcance)
  if(str_starts_with($p,'agency/usuarios'))return (new UsuariosAgencia($this->db,$this->a))->route($p,$m,$b);
  if($p==='agency/users'){$this->a->agency();$id=Http::integer($b['accountId']??$_GET['accountId']??0,1,PHP_INT_MAX);$this->a->authorizeTenant($id,true);$this->a->tenant=$id;if($m==='GET')return $this->u->listing(false);if($m==='POST')return $this->u->save($b,false);if($m==='DELETE')return $this->u->revoke(Http::integer($b['userId']??$_GET['userId']??0,1,PHP_INT_MAX));}
  if($p==='agency/studio'){$this->a->agency();$id=Http::integer($b['tenantId']??$_GET['tenantId']??$_SERVER['HTTP_X_WORKSPACE_ID']??0,1,PHP_INT_MAX);$this->a->authorizeTenant($id);$this->a->tenant=$id;if($m==='GET')return ['configured'=>false,'items'=>$this->r->page('draft')['items'],'audit'=>[]];if($m==='POST'){$prompt=Http::text($b,'prompt',8000);$uid=bin2hex(random_bytes(12));$d=['id'=>$uid,'prompt'=>$prompt,'state'=>'draft','created_at'=>gmdate('c')];$this->r->save('draft',$uid,$d,'Solicitud IA','draft');return ['ok'=>true,'item'=>$d,'executed'=>false];}}
  // Every tenant endpoint must carry a per-tab workspace identifier, including reads.
  if(preg_match('~^contratos/[a-f0-9]{24}/pdf/(previa|enviado|firmado)$~D',$p)&&$m==='GET'&&!isset($_SERVER['HTTP_X_WORKSPACE_ID']))$_SERVER['HTTP_X_WORKSPACE_ID']=(string)($_GET['workspace']??'');
  $this->a->workspace(in_array($p,['workspace/branding','admin/maintenance'],true));
  $permission='datos.consultar';
  if(str_starts_with($p,'workspace/usuarios')||$p==='usuarios'||str_starts_with($p,'usuarios/')||$p==='workspace/permisos')$permission='usuarios.administrar';
  elseif(str_starts_with($p,'admin/')||in_array($p,['workspace/import','workspace/config-mensual','workspace/sync']))$permission='ajustes.editar';
  elseif($p==='workspace/branding'&&$m!=='GET')$permission='ajustes.editar';
  elseif(in_array($p,['workspace/productos','workspace/personalizar','workspace/etiquetas'],true)&&$m!=='GET')$permission='ajustes.editar';
  elseif(in_array($p,['workspace/auditoria','registro']))$permission='auditoria.ver';
  elseif(in_array($p,['workspace/reporte','workspace/observaciones']))$permission='equipo.metricas';
  elseif($p==='workspace/resumen')$permission='resumen.ver';
  elseif($p==='workspace/assign')$permission='conversaciones.reasignar';
  elseif($m!=='GET'&&str_starts_with($p,'contratos'))$permission='convenios.enviar';
  elseif($m!=='GET')$permission='conversaciones.atender';
  $this->a->permit($permission);
  if($p==='workspace/permisos'||$p==='workspace/usuarios/permisos')return $this->u->permissions($p,$m,$b);
  if($p==='workspace/branding'&&in_array($m,['GET','POST']))return $this->ag->branding($m,$b);
  if($p==='admin/maintenance'&&$m==='POST'){$this->a->manage();$this->db->run('UPDATE agora_tenants SET status=?,revision=revision+1 WHERE id=?',[($b['on']??false)?'inactiva':'activa',$this->a->tenant]);$this->a->audit('estado.actualizado','ajuste',(string)$this->a->tenant);return ['maintenance'=>(bool)($b['on']??false)];}
  if(in_array($p,['workspace/usuarios','usuarios'])&&in_array($m,['GET','POST']))return $m==='GET'?$this->u->listing(false):$this->u->save($b,false);
  if(preg_match('~^(workspace/usuarios/(rol|estado|nombre|clave)|usuarios/(rol|clave|activar|desactivar))$~D',$p)&&$m==='POST'){if(str_ends_with($p,'activar'))$b['activo']=!str_ends_with($p,'desactivar');return $this->u->save($b,false,true);}
  // 2.1.19 · tablas editables: columnas, filas agregadas a mano y carga masiva de cada tabla
  if(preg_match('~^workspace/tablas(/|$)~',$p))return (new Tablas($this->db,$this->a))->route($p,$m,$b);
  // 2.1.17 · campos personalizados y migración masiva
  if(preg_match('~^workspace/(campos|migracion)(/|$)~',$p))return (new Migracion($this->db,$this->a,$this->r))->route($p,$m,$b);
  // ---
  // 2.1.17 · venta ganada
  if(preg_match('~^workspace/ventas(/|$)~',$p))return (new Ventas($this->db,$this->a,$this->r))->route($p,$m,$b);
  // ---
  // 2.1.17 · embudos e indicadores (KPI)
  if(preg_match('~^workspace/(embudos|capi|indicadores)(/|$)~',$p))return (new Embudos($this->db,$this->a,$this->r))->route($p,$m,$b);
  // ---
  // 2.1.17 · equipo experiencia: edición de tareas
  if(preg_match('~^workspace/tareas/[a-z0-9_]{1,80}$~D',$p)&&$m==='PUT')return (new Experiencia($this->db,$this->a,$this->r))->route($p,$m,$b);
  // ---
  if($p==='workspace/productos'&&in_array($m,['GET','PUT']))return $this->per->productos($m,$b);
  if($p==='workspace/personalizar'&&in_array($m,['GET','PUT']))return $this->per->personal($m,$b);
  if($p==='workspace/etiquetas'&&$m==='PUT')return $this->per->etiquetas($b);
  if($p==='workspace/tareas'&&in_array($m,['GET','POST']))return $this->w->tasks($m,$b);
  if(preg_match('~^workspace/tareas/([a-z0-9_]+)/estado$~D',$p,$match)&&$m==='POST')return $this->w->tasks($m,$b,$match[1]);
  if($p==='workspace/reporte'&&$m==='GET')return $this->w->reports();
  if($p==='workspace/import'&&$m==='POST')return $this->w->import($b);
  if($p==='workspace/config-mensual'&&$m==='PUT')return $this->w->configuration($b);
  if($p==='workspace/observaciones'&&$m==='POST')return $this->w->observation($b);
  if(in_array($p,['workspace/auditoria','registro'])&&$m==='GET')return $this->ag->audit(false);
  if($p==='workspace/auditoria'&&$m==='POST'){$this->a->audit('cliente:'.Http::text($b,'accion',90),'interfaz','',['detalle'=>Http::text($b,'detalle',1000,false)]);return ['ok'=>true];}
  if($p==='contratos'&&$m==='GET')return $this->c->listing();
  if($p==='contratos'&&$m==='POST')return $this->c->create($b);
  if($p==='contratos/calculo'&&$m==='POST')return Contracts::calculate($b);
  if(preg_match('~^contratos/([a-f0-9]{24})(?:/(enviar|enlace|revocar|editar|cliente))?$~D',$p,$match)){if($m==='GET'&&!isset($match[2]))return $this->c->detail($match[1]);if($m==='DELETE'&&!isset($match[2]))return $this->c->mutate($match[1],'delete',$b);if($m==='POST'&&isset($match[2]))return $this->c->mutate($match[1],$match[2],$b);}
  if(preg_match('~^contratos/([a-f0-9]{24})/pdf/(previa|enviado|firmado)$~D',$p,$match)&&$m==='GET'){$d=$this->c->detail($match[1]);if($match[2]==='firmado'&&$d['estado']!=='FIRMADO')Http::fail('No existe documento firmado.',404);header('Content-Type: application/pdf');header('Cache-Control: no-store');header('Content-Disposition: inline; filename="convenio-'.$match[1].'.pdf"');echo Contracts::document($d);exit;}
  if($p==='ghl/contactos/buscar'&&$m==='POST'){$_GET['query']=$b['q']??$b['query']??$b['busqueda']??'';$page=$this->r->page('contact');return ['contactos'=>array_map(fn($c)=>['id'=>$c['id'],'nombre'=>$c['name']??trim(($c['firstName']??'').' '.($c['lastName']??'')),'correo'=>$c['email']??'','telefono'=>$c['phone']??'','cedula'=>$c['cedula']??''],$page['items'])];}
  if($p==='workspace/contacts'&&$m==='GET')return $this->r->page('contact');
  if($p==='workspace/record'&&in_array($m,['GET','POST']))return $this->w->contacts($m,$b);
  if($p==='workspace/pipelines'&&$m==='GET')return ['pipelines'=>$this->r->page('pipeline')['items'],'assignees'=>$this->w->assignees()];
  if($p==='workspace/board'&&$m==='GET')return $this->w->board();
  if($p==='workspace/opportunities'&&$m==='GET')return $this->r->page('opportunity')+['pipelines'=>$this->r->page('pipeline')['items'],'assignees'=>$this->w->assignees()];
  if(preg_match('~^workspace/opportunity/(new|edit|status)$~D',$p,$match)&&$m==='POST')return $this->w->opportunity($b,$match[1]);
  if($p==='workspace/won'&&$m==='GET')return $this->metrics->won();
  if($p==='workspace/dashboard'&&$m==='GET')return $this->metrics->dashboard();
  if($p==='workspace/board-month'){if($m==='GET')return $this->metrics->month();if($m==='POST'){$this->a->manage();$period=Http::period($_GET['period']??$b['period']??gmdate('Y-m'));if(strlen(DB::json($b))>10000)Http::fail('Configuración demasiado grande.');$this->db->saveSetting($this->a->tenant,'board.'.$period,$b);$this->a->audit('metas.actualizadas','ajuste',$period);return ['saved'=>true];}}
  if($p==='workspace/resumen'&&$m==='GET')return ['mrr'=>null,'mrrAnterior'=>null,'clientesActivos'=>null,'recaudado'=>null,'arr'=>null,'caja'=>null,'churn'=>null,'churnPct'=>null,'egresos'=>null,'inversion'=>null,'serie'=>[],'espacios'=>[$this->ag->branding('GET',[])],'superadmins'=>[],'porVencer'=>[]];
  if($p==='workspace/ads'&&$m==='GET')return ['estado'=>'not_connected'];
  if($p==='workspace/dia'&&$m==='GET'){$d=$this->metrics->dashboard();return ['conversacionesDia'=>null,'atendidas'=>null,'sinLeer'=>$d['conversations']['unread'],'sinLeerPorAsesor'=>[],'slaIncumplido'=>null,'slaPromedio'=>'Sin medición','slaPromedioSeg'=>null,'slaMetaTexto'=>'Sin configurar','peorRespuesta'=>'Sin medición','leads'=>null,'leadsPorFuente'=>[],'leadsPorUsuario'=>[]];}
  if($p==='workspace/eficacia'&&$m==='GET')return ['pipelines'=>$this->r->page('pipeline')['items'],'usuarios'=>$this->w->assignees(),'porUsuario'=>[],'duracionPromedioDias'=>null,'duracionTexto'=>'Sin medición','valorTotal'=>null,'cerradas'=>null,'ticket'=>null,'velocidad'=>null,'tasaCierre'=>null];
  return $this->other($p,$m,$b);
 }
 private function other(string $p,string $m,array $b):mixed{
  if($m==='GET'&&$p==='workspace/pulse'){$d=$this->metrics->dashboard();return ['token'=>(string)$this->db->one('SELECT revision FROM agora_tenants WHERE id=?',[$this->a->tenant])['revision'],'unread'=>$d['conversations']['unread'],'reason'=>null,'retryAfter'=>60];}
  if($m==='GET'&&$p==='workspace/inbox')return $this->r->page('conversation')+['unread'=>$this->metrics->dashboard()['conversations']['unread'],'channels'=>['TYPE_WHATSAPP'=>'WhatsApp','TYPE_SMS'=>'SMS','TYPE_EMAIL'=>'Email'],'reminders'=>(new Messaging($this->db,$this->a))->reminders(),'syncIntervalSeconds'=>300,'syncedAt'=>$this->db->setting($this->a->tenant,'ghl.synced',null),'state'=>[]];
  if($m==='GET'&&$p==='workspace/conversation')return ['conversation'=>$this->r->payload($this->r->get('conversation',(string)($_GET['conversationId']??'')))];
  if(in_array($p,['workspace/messages','workspace/contact','workspace/activity','workspace/assign','workspace/read','workspace/send','workspace/reminders'])||str_starts_with($p,'workspace/activity/'))return (new Messaging($this->db,$this->a))->handle($p,$m,$b);
  if($p==='workspace/won-alert'&&$m==='GET'){$w=$this->metrics->won();return ['count'=>$w['totals']['missing'],'escalate'=>false,'canEdit'=>$w['canEdit'],'team'=>['missing'=>$w['totals']['missing'],'bySeller'=>[]]];}
  if($p==='workspace/self'&&$m==='GET')return ['options'=>$this->w->assignees(),'linked'=>(string)$this->a->user['id'],'detected'=>(string)$this->a->user['id']];
  if($p==='workspace/self'&&$m==='PUT'){if((string)($b['userId']??$b['id']??'')!==(string)$this->a->user['id'])Http::fail('La vinculación debe configurarse por administración.',403);return ['ok'=>true];}
  if($m==='GET'&&$p==='workspace/tags')return ['items'=>$this->db->setting($this->a->tenant,'tags',[])];
  if($m==='GET'&&$p==='workspace/contact-fields')return ['items'=>[],'folders'=>[]];
  if($m==='GET'&&$p==='workspace/templates')return ['configured'=>false,'items'=>[],'error'=>'Plantillas externas no configuradas.'];
  if($p==='workspace/sync')Http::fail('La sincronización externa no está habilitada. Importa los datos desde Ajustes.',503);
  if($m==='GET'&&$p==='admin/diagnostics'){$this->a->manage();return ['ready'=>true,'database'=>'connected','ghlConfigured'=>(new \Agora\Services\GhlConnection($this->db,$this->config))->status((int)$this->a->tenant)['configurada']];}
  // 2.1.19 · Ajustes → Cuentas conectadas: preferencias que se guardan y mensajes claros donde falta la conexión externa
  if(preg_match('~^admin/(integrations/\d+(/.*)?|integrations|access-channel(/test)?|cron-url|installation/encryption)$~D',$p)&&!($m==='GET'&&$p==='admin/integrations'))return (new Integraciones($this->db,$this->a,$this->config))->route($p,$m,$b);
  if($m==='GET'&&$p==='admin/integrations'){$this->a->manage();return ['items'=>[['id'=>$this->a->tenant,'name'=>$this->ag->branding('GET',[])['nombre'],'status'=>(new \Agora\Services\GhlConnection($this->db,$this->config))->status((int)$this->a->tenant)['configurada']?'configured':'not_connected','currency_code'=>(string)($this->db->one('SELECT currency FROM agora_tenants WHERE id=?',[$this->a->tenant])['currency']??'USD'),'currency'=>(string)($this->db->one('SELECT currency FROM agora_tenants WHERE id=?',[$this->a->tenant])['currency']??'USD'),'sync_interval_seconds'=>300]+Integraciones::extra($this->db,(int)$this->a->tenant)],'settings'=>[]];}
  if(preg_match('~^admin/integrations/\d+/commission$~D',$p)&&$m==='GET'){$this->a->manage();return ['current'=>null,'history'=>[]];}
  if($p==='admin/announcements'){$this->a->manage();if($m==='GET')return $this->r->page('announcement');if($m==='POST'){$id=bin2hex(random_bytes(12));$d=['id'=>$id,'title'=>Http::text($b,'title',190),'body'=>Http::text($b,'body',4000,false),'at'=>gmdate('c'),'seen'=>0];$this->r->save('announcement',$id,$d,$d['title']);return ['id'=>$id];}}
  if(preg_match('~^admin/announcements/([a-z0-9]+)$~D',$p,$x)&&$m==='DELETE'){$this->a->manage();$this->r->delete('announcement',$x[1]);return ['ok'=>true];}
  if($m==='GET'&&$p==='devices')return ['items'=>[],'configured'=>false,'vapidPublicKey'=>null];
  if($p==='devices/test'||str_starts_with($p,'passkeys')||str_starts_with($p,'team-login'))Http::fail('Esta integración requiere configuración adicional. Usa acceso por contraseña.',503);
  Http::fail('Ruta o método no implementado: '.$p,404);
 }
}
