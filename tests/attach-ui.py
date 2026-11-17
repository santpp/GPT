"""1.10.17 · Bandeja: adjuntar archivos (WhatsApp y demás) y respuestas guardadas (snippets)."""
import json,os,tempfile
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
tmp=tempfile.mkdtemp();pdf=os.path.join(tmp,'folleto.pdf');open(pdf,'wb').write(b'%PDF-1.4\n%%EOF\n')
exe=os.path.join(tmp,'programa.exe');open(exe,'wb').write(b'MZ')
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 for width in [390,1440]:
  page=browser.new_context(viewport={'width':width,'height':844},service_workers='block').new_page()
  sent=[];uploads=[];errors=[];saved=[];store=[];page.on('pageerror',lambda e:errors.append(str(e)))
  def api(route):
   req=route.request;path=urlparse(req.url).path
   if path=='/api/me':data={'user':{'role':'administrator'},'csrf':'csrf-test'}
   elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
   elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Pérez','lastMessageType':'TYPE_WHATSAPP','lastMessageDate':'2099-01-01T00:00:00Z'}],'total':1}
   elif path=='/api/workspace/messages':data={'items':[{'id':'in1','body':'Hola','direction':'inbound','dateAdded':'2099-01-01T00:00:00Z','messageType':'TYPE_WHATSAPP'}],'hasMore':False,
       'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None}]}
   elif path=='/api/workspace/upload':
    assert req.method=='POST' and req.headers['x-csrf-token']=='csrf-test' and 'multipart/form-data' in req.headers['content-type'],req.headers
    assert 'channel=WhatsApp' in req.url,req.url
    uploads.append(1);data={'url':'https://storage.example.test/folleto.pdf','name':'folleto.pdf','size':16,'type':'application/pdf'}
   elif path=='/api/workspace/snippets' and req.method=='POST':
    assert req.headers['x-csrf-token']=='csrf-test';saved.append(req.post_data_json);data={'id':'q9','saved':True}
    store.append({'id':'q9','name':req.post_data_json['name'],'text':req.post_data_json['body'],'body':req.post_data_json['body'],'missing':[],'attachments':req.post_data_json['attachments'],'source':'mine','editable':True,'shared':False})
   elif path=='/api/workspace/snippets':
    data={'items':list(store),'canShare':True,'accountError':None}
   elif path=='/api/workspace/send':
    body=req.post_data_json;sent.append(body);data={'state':'accepted','messageId':'m'+str(len(sent)),'deliveryStatus':'pending'}
   else:data={'items':[]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
  page.route('**/api/**',api)
  page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  page.wait_for_selector('#live-attach')
  # Un tipo no admitido no se sube.
  page.set_input_files('#live-file',exe)
  page.wait_for_selector('.live-file.error')
  assert 'Tipo no admitido' in page.locator('.live-file.error').inner_text() and not uploads
  page.locator('.live-file-x').click()
  print('PASS un tipo de archivo no admitido se avisa y no se sube')
  page.set_input_files('#live-file',pdf)
  page.wait_for_selector('.live-file.ready')
  assert uploads and 'folleto.pdf' in page.locator('#live-files').inner_text()
  # Un adjunto solo, sin texto, se puede enviar.
  page.locator('#live-send').click()
  page.wait_for_function('()=>document.querySelector("#live-send-status").textContent.includes("aceptado")')
  assert sent[-1]['attachments']==['https://storage.example.test/folleto.pdf'] and sent[-1]['message']=='' and sent[-1]['channel']=='WhatsApp',sent[-1]
  assert page.locator('#live-files').is_hidden()
  print('PASS se adjunta un PDF, se sube por nuestro servidor y se envía por WhatsApp sin texto')
  # 1.10.18 · Sin respuestas: el aviso no dice dónde se crean, y se crea una propia con lo escrito
  page.fill('#live-reply','Gracias por escribirnos, {{contact.first_name}}.')
  page.locator('#live-snippets-toggle').click();page.wait_for_selector('#snip-new')
  t=page.locator('.snip-panel').inner_text()
  assert 'Todavía no hay respuestas guardadas' in t and 'Marketing' not in t and 'Snippets' not in t,t
  page.locator('#snip-new').click();page.wait_for_selector('#snip-form')
  assert page.locator('#snip-form [name=body]').input_value()=='Gracias por escribirnos, {{contact.first_name}}.'
  page.fill('#snip-form [name=name]','Gracias');page.locator('#snip-form [data-field="{{user.first_name}}"]').click()
  page.locator('#snip-save').click();page.wait_for_selector('[data-snip="q9"]')
  assert saved[-1]['name']=='Gracias' and saved[-1]['body'].endswith('{{user.first_name}}') and saved[-1]['shared'] is False and saved[-1]['attachments']==[],saved[-1]
  assert 'tuyas' in page.locator('.snip-panel').inner_text().lower() and page.locator('[data-snip-edit="q9"]').count()==1
  page.locator('#snip-close').click();page.fill('#live-reply','')
  print('PASS sin respuestas guardadas no dice dónde se crean; cada quien crea la suya con lo que escribió y un dato insertado')
  store[:]=[{'id':'s1','name':'Bienvenida','text':'Hola Ana, te envío la información.','missing':[],'attachments':['https://storage.example.test/programa.pdf'],'source':'account','editable':False,'shared':True},
            {'id':'s2','name':'Precio','text':'El precio es {{custom_values.precio}}','missing':['{{custom_values.precio}}'],'attachments':[],'source':'team','editable':False,'shared':True}]
  # Respuestas guardadas
  page.locator('#live-snippets-toggle').click()
  page.wait_for_selector('[data-snip="s1"]')
  page.fill('#snip-q','precio');assert page.locator('[data-snip]').count()==1
  page.fill('#snip-q','')
  page.locator('[data-snip="s1"]').click()
  assert page.locator('#live-reply').input_value()=='Hola Ana, te envío la información.' and 'programa.pdf' in page.locator('#live-files').inner_text()
  page.locator('#live-send').click()
  for _ in range(50):
   if len(sent)>=2:break
   page.wait_for_timeout(100)
  assert sent[-1]['message']=='Hola Ana, te envío la información.' and sent[-1]['attachments']==['https://storage.example.test/programa.pdf'],sent[-1]
  print('PASS una respuesta guardada pone su texto ya rellenado y su archivo, y se envía')
  n=len(sent)
  page.locator('#live-snippets-toggle').click();page.wait_for_selector('[data-snip="s2"]');page.locator('[data-snip="s2"]').click()
  assert 'Completa antes de enviar' in page.locator('#live-send-status').inner_text()
  page.locator('#live-send').click();page.wait_for_timeout(300)
  assert len(sent)==n and 'campos sin completar' in page.locator('#live-send-status').inner_text()
  print('PASS si a la respuesta le falta un dato {{…}}, lo dice y no deja enviar')
  if os.getenv('SHOT'):
   page.set_input_files('#live-file',pdf);page.wait_for_selector('.live-file.ready')
   page.locator('#live-snippets-toggle').click();page.wait_for_selector('[data-snip="s1"]')
   page.locator('#live-composer').scroll_into_view_if_needed()
   page.screenshot(path=os.path.join(os.getenv('SHOT'),f'attach-{width}.png'))
  for word in ['GoHighLevel','GHL','LeadConnector','templates','originId']:
   assert word not in page.locator('#live-composer').inner_text(),word
  assert not errors,errors
 print('PASS no JS errors, sin nombres técnicos')
 browser.close()
