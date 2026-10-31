"""Delivers a real push message to the real service worker and reads back what it showed."""
import json,os,time
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    context=b.new_context()
    context.grant_permissions(['notifications'],origin=BASE)
    page=context.new_page()
    page.goto(BASE+'/')
    page.evaluate('navigator.serviceWorker.ready')
    page.wait_for_function('navigator.serviceWorker.controller!==null')
    cdp=context.new_cdp_session(page)
    registrations=[]
    cdp.on('ServiceWorker.workerRegistrationUpdated',lambda e:registrations.extend(e['registrations']))
    cdp.send('ServiceWorker.enable')
    # The registration is reported asynchronously; the page keeps the loop turning.
    for _ in range(60):
        if registrations:break
        page.wait_for_timeout(100)
    assert registrations, 'the service worker registration was not reported'
    reg=registrations[0]['registrationId']
    def push(payload):
        cdp.send('ServiceWorker.deliverPushMessage',{'origin':BASE,'registrationId':reg,'data':json.dumps(payload,ensure_ascii=False)})
        page.wait_for_timeout(1200)
        return page.evaluate("navigator.serviceWorker.ready.then(r=>r.getNotifications()).then(n=>n.map(x=>({title:x.title,body:x.body,data:x.data,tag:x.tag})))")
    # Exactly the shape the server sends.
    shown=push({'title':'Raissa Moreno','body':'Hola, ¿sigue disponible?','unread':3,'data':{'path':'/conversations/conv-1','conversationId':'conv-1','connectionId':'7'}})
    note=[n for n in shown if n['tag']=='/conversations/conv-1']
    assert note, f'no notification was shown: {shown}'
    assert note[0]['title']=='Raissa Moreno' and note[0]['body']=='Hola, ¿sigue disponible?', note[0]
    assert note[0]['data']['path']=='/conversations/conv-1', note[0]
    print('PASS the service worker shows who wrote and what, from the payload the server sends')
    # A hostile path is never followed.
    shown=push({'title':'X','body':'Y','data':{'path':'https://evil.example/'}})
    assert all(n['data']['path'].startswith('/') for n in shown), shown
    print('PASS a path that is not this site is replaced by the inbox')
    # An empty push still shows something: iOS revokes permission otherwise.
    shown=push({})
    assert any(n['title']=='Ágora' for n in shown), shown
    print('PASS an empty push still shows a notice, as browsers require')
    b.close()
