"""Deterministic clock tests for foreground synchronization and cancellation."""
import json
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=browser.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page()
 page.clock.install();calls=[];syncs=[0];message_calls=[0];mode=['ok'];errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 def api(route):
  path=route.request.url.split('/api/')[1];calls.append(path)
  if path=='me':data={'user':{'role':'administrator'},'csrf':'test'}
  elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta de prueba'}]}
  elif path.startswith('workspace/inbox?'):data={'syncIntervalSeconds':60,'limit':20,'items':[{'id':'c1','contactId':'contact','contactName':'Persona de prueba','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Actualización '+str(syncs[0]),'unreadCount':syncs[0]}],'total':1,'syncedAt':'2026-10-28 19:00:00'}
  elif path.startswith('workspace/messages?'):
   message_calls[0]+=1;data={'items':[{'id':'m'+str(message_calls[0]),'body':'Mensaje '+str(message_calls[0]),'dateAdded':'2026-10-28T19:00:00Z'}],'hasMore':False}
  elif path.startswith('workspace/sync?connectionId=1'):
   syncs[0]+=1
   if mode[0]=='quota':route.fulfill(status=429,content_type='application/json',body=json.dumps({'error':'Pausa','retryAfter':180}));return
   data={'synced':{'count':1}}
  elif path=='admin/diagnostics':data={'ready':True,'checks':[]}
  else:data={'items':[]}
  route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
 page.route('**/api/**',api)
 page.goto('http://127.0.0.1:8080/')
 page.wait_for_selector('[data-live-id]');page.locator('[data-live-id]').click()
 page.wait_for_selector('#live-log .bubble');page.locator('#live-reply').fill('Mi borrador')
 before_messages=message_calls[0];page.clock.fast_forward(11000)
 page.wait_for_function('(count)=>document.querySelectorAll("#live-log .bubble").length>count',arg=before_messages)
 assert message_calls[0]==before_messages+1
 assert page.locator('#live-reply').input_value()=='Mi borrador'
 assert page.locator('.page-heading').is_hidden() and page.locator('.page-footer').is_hidden()
 page.clock.fast_forward(61000)
 page.wait_for_function("document.querySelector('#live-list').textContent.includes('Actualización 1')")
 page.clock.fast_forward(2000)
 page.wait_for_function("document.querySelectorAll('#live-log .bubble').length===2")
 assert page.locator('#live-reply').input_value()=='Mi borrador'
 assert page.locator('#live-log').is_visible()
 page.evaluate("window.testHidden=true;Object.defineProperty(document,'hidden',{get:()=>window.testHidden,configurable:true});document.dispatchEvent(new Event('visibilitychange'))")
 before=syncs[0];page.clock.fast_forward(180000);assert syncs[0]==before
 page.evaluate("window.testHidden=false;document.dispatchEvent(new Event('visibilitychange'))")
 page.clock.fast_forward(1500);page.wait_for_function("document.querySelector('#live-list').textContent.includes('Actualización 2')")
 mode[0]='quota';page.clock.fast_forward(61000)
 page.wait_for_function("document.querySelector('#live-auto-status').textContent.includes('espera')")
 before=syncs[0];page.clock.fast_forward(120000);assert syncs[0]==before
 # Visibility changes must not bypass Retry-After.
 page.evaluate("document.dispatchEvent(new Event('visibilitychange'))")
 page.clock.fast_forward(1000);assert syncs[0]==before
 page.locator('button[data-view="settings"]').click();page.wait_for_selector('#add-integration')
 page.clock.fast_forward(300000);assert syncs[0]==before
 assert not errors,errors
 print('PASS 10-second active chat, automatic inbox sync, draft preservation, hidden-tab pause, Retry-After and navigation cleanup; no hero/footer')
 browser.close()
