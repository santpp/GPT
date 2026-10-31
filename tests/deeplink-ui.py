"""Un aviso lleva a su conversación: /conversations/{id}?c={subcuenta}, aunque no esté entre las últimas."""
import json,os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];asked=[];inbox_for=[];page.on('pageerror',lambda e:errors.append(str(e)))
    def api(route):
        req=route.request;u=urlparse(req.url);q=parse_qs(u.query);path=u.path
        if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'},{'id':2,'name':'Otra'}]}
        elif path=='/api/workspace/inbox':inbox_for.append(q['connectionId'][0]);data={'limit':20,'items':[{'id':'c-last','contactId':'k1','contactName':'Ana Última','lastMessageType':'TYPE_SMS'}],'total':1}
        elif path=='/api/workspace/conversation':
            asked.append((q['connectionId'][0],q['conversationId'][0]))
            if q['conversationId'][0]=='only-in-2':
                if q['connectionId'][0]!='2':route.fulfill(status=404,content_type='application/json',body=json.dumps({'error':'x'}));return
                data={'conversation':{'id':'only-in-2','contactId':'k5','contactName':'Luis Otra Cuenta','lastMessageType':'TYPE_SMS'}};route.fulfill(status=200,content_type='application/json',body=json.dumps(data));return
            if q['conversationId'][0]=='gone':route.fulfill(status=404,content_type='application/json',body=json.dumps({'error':'Esa conversación ya no está disponible.'}));return
            data={'conversation':{'id':'maria-1','contactId':'k9','contactName':'María Cliente','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}}
        elif path=='/api/workspace/messages':data={'items':[],'hasMore':False}
        elif path=='/api/workspace/contact':data={'contact':{'name':'María Cliente','tags':[],'customFields':[]},'canEdit':False,'channels':[]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/conversations/maria-1?c=2')
    page.wait_for_selector('.live-chat-header h2:has-text("María Cliente")')
    assert asked==[('2','maria-1')],asked
    assert '2' in inbox_for and page.locator('#account').input_value()=='2'
    assert page.url.endswith('/bandeja')
    print('PASS a notice of another sub-account switches to it and opens that very conversation, though it is not among the latest')
    page.goto(BASE+'/conversations/only-in-2');page.wait_for_selector('.live-chat-header h2:has-text("Luis Otra Cuenta")',timeout=15000)
    assert page.locator('#account').input_value()=='2'
    print('PASS a notice without its sub-account is searched in the others, and opens in the right one')
    page.goto(BASE+'/conversations/gone?c=1');page.wait_for_selector('#live-feedback')
    page.wait_for_function("document.querySelector('#live-feedback').textContent.includes('ya no está disponible')")
    assert page.locator('.live-chat-header').count()==0
    print('PASS if the conversation is gone, it says so instead of opening something else')
    assert not errors,errors
    print('PASS no JS errors')
    b.close()
