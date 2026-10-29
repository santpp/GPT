"""Mock network tests: real send UI, durable request keys, pagination size and drafts."""
import json
import os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 for width in [390,1440]:
  page=browser.new_context(viewport={'width':width,'height':844},service_workers='block').new_page()
  window=[20];sent=[];mode=['lost'];errors=[];held=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  def api(route):
   req=route.request;url=urlparse(req.url);path=url.path;query=parse_qs(url.query)
   if path=='/api/me':data={'user':{'role':'administrator'},'csrf':'csrf-test'}
   elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
   elif path=='/api/workspace/sync':
    assert req.method=='POST' and req.headers['x-csrf-token']=='csrf-test'
    window[0]=int(query['limit'][0]);data={'synced':{'count':window[0]}}
   elif path=='/api/workspace/inbox':data={'limit':window[0],'items':[{'id':'c'+str(i),'contactId':'contact'+str(i),'contactName':'Contacto '+str(i),'lastMessageType':'TYPE_EMAIL' if i==2 else 'TYPE_WHATSAPP'} for i in range(window[0])],'total':130,'syncedAt':'2026-10-28 19:00:00','partial':True}
   elif path=='/api/workspace/messages':data={'items':[],'hasMore':False}
   elif path=='/api/workspace/contact':
    data={'contact':{'name':'Contacto 0','phone':'+34123456789','email':'c0@example.test','tags':[],'customFields':[]},'canEdit':False,
          'channels':[{'type':'WhatsApp','label':'WhatsApp','needsSubject':False},{'type':'SMS','label':'SMS','needsSubject':False},{'type':'Email','label':'Email','needsSubject':True}]}
   elif path=='/api/workspace/send':
    body=req.post_data_json;sent.append(body)
    assert req.method=='POST' and req.headers['x-csrf-token']=='csrf-test'
    assert 'contactId' not in body
    if mode[0]=='lost':mode[0]='accept';route.abort();return
    if mode[0]=='hold':held.append(route);return
    if mode[0]=='reject':route.fulfill(status=422,content_type='application/json',body=json.dumps({'error':'Canal rechazó el envío','code':'send_rejected'}));return
    data={'state':'accepted','messageId':'msg1','deliveryStatus':'pending'}
   else:data={'items':[]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
  page.route('**/api/**',api)
  page.goto('http://127.0.0.1:8080/')
  page.wait_for_selector('[data-live-id]')
  for size in [50,100,20]:
   page.locator('#live-limit').select_option(str(size))
   page.wait_for_function('(size)=>document.querySelectorAll("[data-live-id]").length===size',arg=size)
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  page.locator('#live-reply').fill('Prueba sin duplicar')
  page.locator('#live-send').click()
  page.wait_for_function('!document.querySelector("#live-send").disabled')
  assert page.locator('#live-reply').input_value()=='Prueba sin duplicar'
  first=sent[0]['requestId']
  # Reload loses the draft but retains only the random send key/hash.
  stored=page.evaluate('JSON.stringify(sessionStorage)')
  assert 'Prueba sin duplicar' not in stored and first in stored
  page.reload();page.wait_for_selector('[data-live-id]')
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  page.locator('#live-reply').fill('Prueba sin duplicar')
  page.locator('#live-send').click()
  page.wait_for_function('document.querySelector("#live-reply").value===""')
  assert sent[-1]['requestId']==first
  assert 'Pendiente' in page.locator('#live-log').inner_text()
  # No Enter-to-send shortcut; drafts survive chat changes.
  page.locator('#live-reply').fill('Borrador')
  page.locator('#live-reply').press('Enter')
  assert len(sent)==2
  if width==390:page.locator('#live-back').click()
  page.locator('[data-live-id="c2"]').click()
  page.locator('#live-subject').fill('Asunto')
  page.locator('#live-reply').fill('Correo de prueba')
  mode[0]='reject';page.locator('#live-send').click()
  page.wait_for_function('document.querySelector("#live-send-status").textContent.includes("rechazó")')
  assert page.locator('#live-reply').input_value()=='Correo de prueba'
  assert sent[-1]['channel']=='Email' and sent[-1]['subject']=='Asunto'
  if width==390:page.locator('#live-back').click()
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  assert page.locator('#live-reply').input_value().startswith('Borrador')
  mode[0]='hold';page.locator('#live-send').click();page.wait_for_timeout(100)
  if width==390:page.locator('#live-back').click()
  page.locator('[data-live-id="c1"]').click();page.locator('#live-reply').fill('Otro borrador')
  assert held
  held.pop().fulfill(status=200,content_type='application/json',body=json.dumps({'state':'accepted','messageId':'msg2','deliveryStatus':'pending'}))
  page.wait_for_timeout(100)
  assert page.locator('#live-reply').input_value()=='Otro borrador'
  assert page.locator('#live-log .bubble').count()==0
  assert page.evaluate('document.documentElement.scrollWidth')==width
  page.screenshot(path=f'/tmp/agora-040-send-{width}.png',full_page=True)
  # The server decides which channels this contact can receive; the picker offers them.
  page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
  page.wait_for_selector('#live-composer [data-channel="SMS"]',state='attached')
  offered=page.locator('#live-composer [data-channel]').evaluate_all('ns=>ns.map(n=>n.dataset.channel)')
  assert offered==['WhatsApp','SMS','Email'], offered
  assert page.locator('#live-composer [data-channel="WhatsApp"]').evaluate('(n)=>n.classList.contains("selected")'), 'the thread channel starts selected'
  page.locator('#live-reply').evaluate("(n)=>{n.value='Te escribo por aqui';n.dispatchEvent(new Event('input'))}")
  page.locator('#live-composer [data-channel="Email"]').evaluate('(n)=>n.click()')
  page.wait_for_selector('#live-subject',state='attached')
  assert page.locator('#live-reply').input_value()=='Te escribo por aqui', 'switching channel keeps what was typed'
  page.locator('#live-composer [data-channel="SMS"]').evaluate('(n)=>n.click()')
  assert page.locator('#live-subject').count()==0, 'only email asks for a subject'
  before=len(sent)
  page.locator('#live-send').evaluate('(n)=>n.click()')
  page.wait_for_timeout(700)
  assert len(sent)>before and sent[-1]['channel']=='SMS', sent[-1:]
  assert sent[-1]['message']=='Te escribo por aqui'
  print(f'PASS {width}px: channel picker offers what the contact can receive, keeps the draft and sends on the chosen one')
  assert not errors,errors
  page.context.close()
  print(f'PASS {width}px: 20/50/100, send/CSRF, lost response/reload keeps key, pending status, drafts, email rejection and late send isolation')
 browser.close()
