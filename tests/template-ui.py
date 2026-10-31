"""Plantillas de WhatsApp en el chat: aviso de 24 horas, vista previa, variables y envío."""
import json,os,datetime
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
OLD=(datetime.datetime.now(datetime.timezone.utc)-datetime.timedelta(days=2)).isoformat().replace('+00:00','Z')
TEMPLATES=[
 {'id':'tpl-1','name':'bienvenida','language':'es','category':'MARKETING','header':None,'body':'¡Hola {{contact.name}}! Bienvenido a ASOCIM.','footer':'ASOCIM','buttons':['Quiero info'],
  'variables':{'header':[],'body':[{'token':'contact.name','fixed':'{{contact.name}}','label':'nombre del contacto'}]},'unsupported':None},
 {'id':'tpl-2','name':'cita_recordatorio','language':'es','category':'UTILITY','header':None,'body':'Tu cita es el {{1}} a las {{2}}.','footer':None,'buttons':[],
  'variables':{'header':[],'body':[{'token':'1','fixed':None,'label':'Variable 1'},{'token':'2','fixed':None,'label':'Variable 2'}]},'unsupported':None},
 {'id':'tpl-4','name':'catalogo','language':'es','category':'MARKETING','header':None,'body':'Mira el catálogo','footer':None,'buttons':[],'variables':{'header':[],'body':[]},'unsupported':'media_header'}]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 for width in [390,1440]:
  page=browser.new_context(viewport={'width':width,'height':900},service_workers='block').new_page()
  sent=[];errors=[];reads=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  def api(route):
   req=route.request;path=urlparse(req.url).path
   if path=='/api/me':data={'user':{'role':'administrator'},'csrf':'csrf-test'}
   elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
   elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Raissa Moreno','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
   elif path=='/api/workspace/messages':data={'items':[{'id':'m1','direction':'inbound','body':'Hola, ¿info?','dateAdded':OLD,'messageType':'TYPE_WHATSAPP'}],'hasMore':False}
   elif path=='/api/workspace/contact':data={'contact':{'name':'Raissa Moreno','phone':'+34642795214','tags':[],'customFields':[]},'canEdit':False,
          'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None},{'type':'SMS','label':'SMS','available':True,'needsSubject':False,'reason':None}]}
   elif path=='/api/workspace/templates':reads.append(1);data={'items':TEMPLATES,'configured':True}
   elif path=='/api/workspace/send':
    body=req.post_data_json;sent.append(body);assert req.headers['x-csrf-token']=='csrf-test'
    data={'state':'accepted','messageId':'msg-t'+str(len(sent)),'deliveryStatus':'pending'}
   else:data={'items':[]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
  page.route('**/api/**',api)
  page.goto('http://127.0.0.1:8080/');page.wait_for_selector('[data-live-id]')
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  page.wait_for_selector('#live-window-hint:not([hidden])')
  assert 'plantilla aprobada' in page.locator('#live-window-hint').inner_text()
  print('PASS more than 24 hours after the contact wrote, the chat says only an approved template can go')
  page.locator('#live-template-toggle').click();page.wait_for_selector('#tpl-select')
  assert page.locator('#live-reply').is_hidden(),'while choosing a template the free text box steps aside'
  options=page.locator('#tpl-select option').all_inner_texts()
  assert 'bienvenida · Promoción' in options and 'cita recordatorio · Servicio' in options
  print('PASS the approved templates are offered by readable name and kind')
  page.select_option('#tpl-select','tpl-1')
  preview=page.locator('.tpl-preview').inner_text()
  assert 'nombre del contacto' in preview and 'Quiero info' in preview and '{{' not in preview
  assert page.locator('[data-tpl-token]').count()==0
  print('PASS the preview names the fields the account fills, with no raw markers, and asks nothing for them')
  page.select_option('#tpl-select','tpl-2')
  assert page.locator('#tpl-send').is_disabled(),'nothing goes while a variable is empty'
  page.locator('[data-tpl-token="1"]').fill('jueves 5')
  assert page.locator('#tpl-send').is_disabled()
  page.locator('[data-tpl-token="2"]').fill('17:00')
  assert page.locator('#tpl-send').is_enabled()
  print('PASS the send button waits until every variable is written')
  assert 'jueves 5' in page.locator('.tpl-preview').inner_text()
  print('PASS what is typed appears in the preview as it is written')
  page.select_option('#tpl-select','tpl-4')
  assert page.locator('#tpl-send').is_disabled() and 'desde la cuenta' in page.locator('.tpl-panel').inner_text()
  print('PASS a template with an image is shown but not sent half-built')
  page.select_option('#tpl-select','tpl-2')
  page.locator('[data-tpl-token="1"]').fill('jueves 5');page.locator('[data-tpl-token="2"]').fill('17:00')
  sent.clear();page.locator('#tpl-send').click()
  page.wait_for_function("document.querySelector('#live-send-status').textContent.includes('Plantilla aceptada')")
  body=sent[-1]
  assert body['channel']=='WhatsApp' and body['template']=={'id':'tpl-2','values':{'body':{'1':'jueves 5','2':'17:00'}}} and len(body['requestId'])>=16
  assert 'toNumber' not in json.dumps(body) and 'fromNumberId' not in json.dumps(body),'the numbers are the server\'s business, not the page\'s'
  assert 'Tu cita es el jueves 5 a las 17:00.' in page.locator('#live-log').inner_text()
  assert page.locator('#live-reply').is_visible() and page.locator('.tpl-panel').count()==0
  print('PASS the template is sent with only its choice and values, and appears in the chat')
  assert len(reads)==1,'the list is read once per chat'
  page.locator('[data-channel="SMS"]').click()
  assert page.locator('#live-template-toggle').is_hidden() and page.locator('#live-window-hint').is_hidden()
  print('PASS on SMS there is no template button and no WhatsApp notice')
  if width==390:page.locator('[data-channel="WhatsApp"]').click();page.locator('#live-template-toggle').click();page.wait_for_selector('#tpl-select');page.select_option('#tpl-select','tpl-1');page.screenshot(path='/tmp/template-phone.png')
  assert not errors,errors
 print('PASS no JS errors')
 browser.close()
