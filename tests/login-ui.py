"""Passwordless team login uses synchronized email and keeps administration hidden."""
import json
import os
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 page=browser.new_context(viewport={'width':390,'height':844},service_workers='block').new_page();signed=[False];calls=[]
 def api(route):
  request=route.request;path=request.url.split('/api/')[1];status=200
  if path=='me':
   if signed[0]:data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True,'configureConnections':False}},'csrf':'team-csrf'}
   else:data={'error':'Inicia sesión'};status=401
  elif path=='team-login/request':calls.append(path);data={'accepted':True,'message':'Si el correo pertenece a un usuario activo, recibirá un código de acceso.'};status=202
  elif path=='team-login/verify':
   calls.append(path);assert request.post_data_json=={'email':'ana@example.test','code':'123456'};signed[0]=True;data={'csrf':'team-csrf'}
  elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta del equipo','location_id':'loc','status':'connected'}]}
  elif path.startswith('workspace/inbox'):data={'syncIntervalSeconds':120,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None}
  else:data={'items':[]}
  route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
 page.route('**/api/**',api);page.goto(os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')+'/')
 page.wait_for_selector('#signin:not([hidden])')
 assert page.locator('#app').is_hidden(), 'the workspace stays closed until the code is verified'
 page.locator('#team-login-request [name=email]').fill('ana@example.test');page.locator('#team-login-request button').click()
 page.locator('#team-login-verify [name=code]').fill('123456');page.locator('#team-login-verify button').click()
 page.wait_for_selector('#app:not([hidden])')
 page.wait_for_function("document.querySelector('#user-email')?.textContent.includes('ana@example.test')")
 assert calls==['team-login/request','team-login/verify']
 assert page.locator('button[data-view=settings]').is_hidden(), 'a team member never sees the administration entry'
 assert page.locator('button[data-view=inbox]').is_visible()
 visible=page.locator('body').inner_text()
 assert 'GoHighLevel' not in visible and 'LeadConnector' not in visible
 print('PASS synchronized email login, one-time code, team inbox navigation and hidden administration')
 browser.close()
