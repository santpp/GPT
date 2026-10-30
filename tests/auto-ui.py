"""Deterministic clock: pulse-driven refresh, drafts kept, hidden-tab pause, quota wait, cleanup."""
import json
import os
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
 page=browser.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page()
 page.clock.install()
 calls=[];inbox=[0];message_calls=[0];pulses=[0];beat={'token':'t1','unread':0,'reason':None,'retryAfter':0};errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 def api(route):
  path=route.request.url.split('/api/')[1];calls.append(path)
  if path=='me':data={'user':{'role':'administrator'},'csrf':'test'}
  elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta de prueba'}]}
  elif path.startswith('workspace/pulse'):
   pulses[0]+=1
   data={'token':beat['token'],'unread':beat['unread'],'syncedAt':'2026-10-28 19:00:00','intervalSeconds':10,
         'refreshed':True,'reason':beat['reason'],'retryAfter':beat['retryAfter']}
  elif path.startswith('workspace/inbox?'):
   inbox[0]+=1
   data={'syncIntervalSeconds':60,'limit':20,'items':[{'id':'c1','contactId':'contact','contactName':'Persona de prueba','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Actualización '+str(inbox[0]),'unreadCount':beat['unread'],'lastMessageDate':'1730000000000'}],
         'total':1,'syncedAt':'2026-10-28 19:00:00','nextCursor':None,'hasMore':False}
  elif path.startswith('workspace/messages?'):
   message_calls[0]+=1;data={'items':[{'id':'m'+str(message_calls[0]),'body':'Mensaje '+str(message_calls[0]),'dateAdded':'2026-10-28T19:00:00Z'}],'hasMore':False}
  elif path.startswith('workspace/sync?connectionId=1'):data={'synced':{'count':1}}
  elif path=='admin/diagnostics':data={'ready':True,'checks':[]}
  else:data={'items':[]}
  route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
 page.route('**/api/**',api)
 page.goto('http://127.0.0.1:8080/')
 page.wait_for_selector('[data-live-id]');page.locator('[data-live-id]').click()
 page.wait_for_selector('#live-log .bubble');page.locator('#live-reply').fill('Mi borrador')

 # A quiet account: the screen keeps asking Ágora and reads nothing from it.
 quiet_messages=message_calls[0];quiet_inbox=inbox[0];before_pulses=pulses[0]
 for _ in range(3):
  page.clock.fast_forward(6000);page.wait_for_timeout(400)
 assert pulses[0]>before_pulses, 'the pulse must keep beating'
 assert message_calls[0]==quiet_messages and inbox[0]==quiet_inbox, 'nothing changed, so nothing was read again'

 # Something changes: the list and the open chat refresh themselves.
 beat['token']='t2';beat['unread']=3
 for _ in range(3):
  page.clock.fast_forward(6000);page.wait_for_timeout(400)
 page.wait_for_function("document.querySelector('#live-list').textContent.includes('Actualización 2')")
 page.wait_for_function("document.querySelectorAll('#live-log .bubble').length===2")
 assert page.locator('#live-reply').input_value()=='Mi borrador', 'a refresh must not lose what is being written'
 page.wait_for_function("document.querySelector('#nav-unread').textContent==='3'")
 assert page.locator('.page-heading').is_hidden() and page.locator('.page-footer').is_hidden()

 # The safety net still asks for the open conversation even with no change.
 # With a frozen clock only one timer completes per jump, so a few are needed.
 steady=message_calls[0]
 for _ in range(4):
  if message_calls[0]>steady: break
  page.clock.fast_forward(61000);page.wait_for_timeout(500)
 assert message_calls[0]>steady, 'the slow safety net must still run'

 # A hidden tab asks nothing at all.
 page.evaluate("window.testHidden=true;Object.defineProperty(document,'hidden',{get:()=>window.testHidden,configurable:true});document.dispatchEvent(new Event('visibilitychange'))")
 asleep=pulses[0];page.clock.fast_forward(180000);assert pulses[0]==asleep, 'a hidden screen must not poll'
 page.evaluate("window.testHidden=false;document.dispatchEvent(new Event('visibilitychange'))")
 for _ in range(3):
  if pulses[0]>asleep: break
  page.clock.fast_forward(3000);page.wait_for_timeout(400)
 assert pulses[0]>asleep, 'coming back resumes at once'

 # A paused budget is reported and nothing is forced.
 beat['reason']='paused';beat['retryAfter']=180
 for _ in range(4):
  page.clock.fast_forward(6000);page.wait_for_timeout(400)
 page.wait_for_function("document.querySelector('#live-auto-status').textContent.includes('espera')")

 # Leaving the screen stops everything.
 page.locator('button[data-view="settings"]').click();page.wait_for_selector('#add-integration')
 stopped=pulses[0];page.clock.fast_forward(300000);assert pulses[0]==stopped, 'another screen must not keep the pulse alive'
 assert not errors,errors
 print('PASS live refresh only when something changed, draft kept, slow safety net, hidden-tab pause, quota wait and navigation cleanup; no hero/footer')
 browser.close()
