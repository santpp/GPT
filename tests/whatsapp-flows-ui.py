"""1.10.36 · Plantillas de WhatsApp por flujos de trabajo: el propietario elige flujos en Conexiones (varios, con buscador);
en la Bandeja se pide la plantilla, se avisa que se pidió y, al aparecer en la conversación, que salió. Teléfono y escritorio."""
import json,os,datetime,re
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
OLD=(datetime.datetime.now(datetime.timezone.utc)-datetime.timedelta(days=2)).isoformat().replace('+00:00','Z')
NOW=lambda:datetime.datetime.now(datetime.timezone.utc).isoformat().replace('+00:00','Z')
CUENTA=[{'id':'wf-bienvenida','nombre':'Ágora · Bienvenida WhatsApp','estado':'publicado'},{'id':'wf-cita','nombre':'Ágora · Recordatorio de cita','estado':'publicado'}]+[{'id':f'wf-r{i:03d}','nombre':f'Seguimiento automático {i:03d}','estado':'publicado'} for i in range(1,61)]+[{'id':'wf-borrador','nombre':'Promoción (borrador)','estado':'borrador'}]
def esperar(page,sel,texto,ms):
 for _ in range(ms//250):
  if page.locator(sel).count() and texto in page.locator(sel).inner_text():return True
  page.wait_for_timeout(250)
 return False
n=0
def ok(c,m):
 global n
 assert c,m;n+=1;print('PASS',m)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 for width in [390,1440]:
  # ---------- Conexiones: el propietario elige los flujos ----------
  page=browser.new_context(viewport={'width':width,'height':900},service_workers='block').new_page()
  errors=[];saved=[];chosen=[{'id':'wf-bienvenida','nombre':'Bienvenida','nombreCuenta':'Ágora · Bienvenida WhatsApp','texto':'¡Hola! Gracias por escribirnos.','estado':'published'}]
  page.on('pageerror',lambda e:errors.append(str(e)))
  def api(route):
   req=route.request;path=urlparse(req.url).path;data={'items':[]}
   if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':1}
   elif path=='/api/admin/diagnostics':data={'ready':True,'version':'1.10.36','checks':[]}
   elif path=='/api/admin/integrations':data={'items':[{'id':1,'name':'ASOCIM','location_id':'l','status':'connected','sync_interval_seconds':120,'whatsapp_flows':len(chosen)}],'accessChannel':{'connectionId':None,'ready':False}}
   elif path=='/api/admin/integrations/1/whatsapp' and req.method=='GET':data={'flujos':chosen,'resumen':{'pedidas':4,'vistas':3},'maximo':40}
   elif path=='/api/admin/integrations/1/whatsapp/flows':data={'cuenta':CUENTA,'total':len(CUENTA),'buscar':''}
   elif path=='/api/admin/integrations/1/whatsapp' and req.method=='PUT':
    saved.append(req.post_data_json);chosen[:]=[{**x,'nombreCuenta':next(w['nombre'] for w in CUENTA if w['id']==x['id']),'estado':'published'} for x in req.post_data_json['flujos']];data={'flujos':chosen,'resumen':{'pedidas':4,'vistas':3},'maximo':40}
   elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
  page.route('**/api/**',api)
  page.goto(BASE+'/conexiones');page.wait_for_selector('[data-whatsapp]',timeout=15000)
  ok('Plantillas de WhatsApp: 1 flujo' in page.locator('.integration-card').first.inner_text(),f'{width}px: la tarjeta de la subcuenta dice cuántas plantillas tiene')
  page.locator('[data-whatsapp]').first.click();page.wait_for_selector('#wa-elegidos .wa-fila')
  ok(page.locator('#wa-elegidos [data-wa-nombre]').input_value()=='Bienvenida' and 'Últimos 30 días: 4 plantillas pedidas, 3' in page.locator('#wa-res').inner_text(),f'{width}px: el cuadro muestra las elegidas y el resumen')
  page.locator('#wa-cargar').click();page.wait_for_selector('.wa-lista')
  ok('63 flujos en la cuenta' in page.locator('#wa-cuenta').inner_text(),f'{width}px: «Cargar flujos» los lista')
  page.fill('#wa-q','RECORDATORIO');page.wait_for_timeout(200)
  ok(page.locator('.wa-lista li').count()==1 and '1 de 63' in page.locator('#wa-cuenta').inner_text(),f'{width}px: el buscador filtra por nombre, sin importar mayúsculas')
  page.locator('[data-wa-id="wf-cita"]').check();page.fill('#wa-q','borrador');page.wait_for_timeout(200)
  ok(page.locator('[data-wa-id="wf-borrador"]').is_disabled(),f'{width}px: los borradores no se pueden marcar')
  page.locator('#wa-elegidos .wa-fila >> nth=1 >> [data-wa-nombre]').fill('Cita');page.locator('#wa-guardar').click()
  ok(esperar(page,'#wa-res','Guardado: la Bandeja ofrece 2 plantillas',5000) and saved[-1]['flujos']==[{'id':'wf-bienvenida','nombre':'Bienvenida','texto':'¡Hola! Gracias por escribirnos.'},{'id':'wf-cita','nombre':'Cita','texto':''}],f'{width}px: «Guardar» manda la elección con sus nombres')
  ok(page.evaluate('document.documentElement.scrollWidth')<=width and not re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',page.locator('#dialog-content').inner_text()),f'{width}px: sin desborde ni nombres del proveedor')
  if width==390:page.screenshot(path=os.getenv('SHOT','/tmp')+'/m-wa-conexiones.png')
  ok(not errors,errors);page.close()
  # ---------- Bandeja: pedir la plantilla ----------
  page=browser.new_context(viewport={'width':width,'height':900},service_workers='block').new_page()
  errors=[];sent=[];msgs=[{'id':'m1','direction':'inbound','body':'Hola, ¿info?','dateAdded':OLD,'messageType':'TYPE_WHATSAPP'}];fresh=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  ITEMS=[{'id':'flujo:wf-bienvenida','name':'Bienvenida','language':'','category':'','header':'','body':'¡Hola! Gracias por escribirnos.','footer':'','buttons':[],'variables':{'header':[],'body':[]},'preview':True,'unsupported':False,'flow':True},
         {'id':'flujo:wf-cita','name':'Cita','language':'','category':'','header':'','body':'','footer':'','buttons':[],'variables':{'header':[],'body':[]},'preview':False,'unsupported':False,'flow':True}]
  def api2(route):
   req=route.request;u=urlparse(req.url);path=u.path;data={'items':[]}
   if path=='/api/me':data={'user':{'role':'team','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True}},'csrf':'csrf-test'}
   elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
   elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Raissa Moreno','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
   elif path=='/api/workspace/messages':
    if 'refresh=1' in u.query:fresh.append(1)
    if sent and len(fresh)>=1 and len(msgs)==1:msgs.append({'id':'wfmsg1','direction':'outbound','body':'¡Hola! Gracias por escribirnos.','dateAdded':NOW(),'messageType':'TYPE_WHATSAPP'})
    data={'items':list(msgs),'hasMore':False,'plantillas':{'vistas':[{'label':'Bienvenida','messageId':'wfmsg1'}] if len(msgs)>1 else [],'pendientes':[] if len(msgs)>1 else ([{'label':'Bienvenida','requestedAt':NOW()}] if sent else [])}}
   elif path=='/api/workspace/contact':data={'contact':{'name':'Raissa Moreno','phone':'+34642795214','tags':[],'customFields':[]},'canEdit':False,'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None}]}
   elif path=='/api/workspace/templates':data={'items':ITEMS,'configured':True,'flows':True}
   elif path=='/api/workspace/send':sent.append(req.post_data_json);data={'state':'requested','flow':True,'messageId':None,'deliveryStatus':'requested','requestId':req.post_data_json['requestId'],'label':'Bienvenida'}
   route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
  page.route('**/api/**',api2)
  page.goto(BASE+'/');page.wait_for_selector('[data-live-id]')
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()');page.wait_for_selector('#live-window-hint:not([hidden])')
  page.locator('#live-template-toggle').click();page.wait_for_selector('#tpl-select')
  ok(page.locator('#tpl-select option').all_inner_texts()[1:]==['Bienvenida','Cita'],f'{width}px: «Usar plantilla» ofrece las plantillas elegidas por el propietario')
  page.select_option('#tpl-select','flujo:wf-cita');ok('no tiene vista previa' in page.locator('.tpl-preview').inner_text(),f'{width}px: sin texto, lo dice')
  page.select_option('#tpl-select','flujo:wf-bienvenida');ok('Gracias por escribirnos' in page.locator('.tpl-preview').inner_text() and 'La cuenta envía la plantilla' in page.locator('.tpl-panel').inner_text(),f'{width}px: con texto, vista previa')
  page.locator('#tpl-send').click()
  ok(esperar(page,'#live-send-status','pedida a la cuenta',5000) and sent[-1]['template']=={'id':'flujo:wf-bienvenida','values':{}} and sent[-1]['channel']=='WhatsApp',f'{width}px: al enviar, avisa que se pidió a la cuenta')
  ok(esperar(page,'#live-send-status','Plantilla enviada: «Bienvenida»',20000) and '¡Hola! Gracias por escribirnos.' in page.locator('#live-log').inner_text(),f'{width}px: a los pocos segundos, «Plantilla enviada» y el mensaje en la conversación')
  ok(page.evaluate('document.documentElement.scrollWidth')<=width and not errors,f'{width}px: sin desborde ni errores {errors[:2]}')
  page.close()
 browser.close()
print(n,'comprobaciones')
