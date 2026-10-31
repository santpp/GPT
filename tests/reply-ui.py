"""Citar un mensaje: botón en cada burbuja, tarjeta sobre el cuadro, envío con la cita y cita dentro de la burbuja."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for width in [390,1440]:
        page=b.new_context(viewport={'width':width,'height':900},service_workers='block',has_touch=width==390).new_page()
        errors=[];sent=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def api(route):
            req=route.request;path=urlparse(req.url).path
            if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','capabilities':{}},'csrf':'t','passkeys':1}
            elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
            elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'María Cliente','lastMessageType':'TYPE_WHATSAPP'},{'id':'c1','contactId':'k1','contactName':'Correo Cliente','lastMessageType':'TYPE_EMAIL'}],'total':2}
            elif path=='/api/workspace/messages':data={'items':[
                {'id':'m1','direction':'inbound','body':'¿Cuánto cuesta el curso de inglés intensivo?','dateAdded':'2026-10-31T10:00:00Z','messageType':'TYPE_WHATSAPP'},
                {'id':'m2','direction':'outbound','body':'Hola, te cuento ahora','dateAdded':'2026-10-31T10:02:00Z','messageType':'TYPE_WHATSAPP','status':'delivered'},
                {'id':'m3','direction':'outbound','body':'Cuesta 120 al mes','dateAdded':'2026-10-31T10:03:00Z','messageType':'TYPE_WHATSAPP','status':'delivered','replyTo':{'id':'m1','snippet':'¿Cuánto cuesta el curso de inglés intensivo?','direction':'inbound'}}],'hasMore':False}
            elif path=='/api/workspace/contact':data={'contact':{'name':'María','tags':[],'customFields':[]},'canEdit':False,'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None}]}
            elif path=='/api/workspace/send':
                assert req.headers['x-csrf-token']=='t';sent.append(req.post_data_json);data={'state':'accepted','messageId':'m4','deliveryStatus':'pending'}
            else:data={'items':[]}
            route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id="c0"]')
        page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()');page.wait_for_selector('.bubble[data-mid="m1"]')
        assert page.locator('.bubble-quote').inner_text().startswith('Contacto') and 'curso de inglés' in page.locator('.bubble-quote').inner_text()
        print(f'PASS at {width}px a message that quoted another shows the quote inside its bubble')
        page.locator('.bubble[data-mid="m1"] .bubble-reply').evaluate('(n)=>n.click()')
        page.wait_for_selector('.quote-card')
        assert 'Respondes a este mensaje' in page.locator('.quote-card').inner_text() and 'curso de inglés' in page.locator('.quote-card').inner_text()
        print(f'PASS at {width}px tapping reply puts that message above the writing box')
        page.fill('#live-reply','Cuesta 120 al mes');page.locator('#live-send').click()
        page.wait_for_function("document.querySelector('#live-reply').value===''")
        assert sent[-1]['replyTo']=='m1' and sent[-1]['message']=='Cuesta 120 al mes'
        assert page.locator('.quote-card').count()==0
        assert page.locator('.bubble[data-mid="m4"] .bubble-quote').count()==1
        print(f'PASS at {width}px it is sent quoting that message, and the sent message shows its quote')
        page.locator('.bubble[data-mid="m2"] .bubble-reply').evaluate('(n)=>n.click()');page.wait_for_selector('.quote-card')
        assert 'tu mensaje' in page.locator('.quote-card').inner_text()
        page.locator('#quote-clear').click()
        assert page.locator('.quote-card').count()==0
        page.fill('#live-reply','Sin citar');page.locator('#live-send').click();page.wait_for_function("document.querySelector('#live-reply').value===''")
        assert 'replyTo' not in sent[-1]
        print(f'PASS at {width}px the quote can be dropped, and then nothing is quoted')
        if width==1440:
            page.locator('[data-live-id="c1"]').evaluate('(n)=>n.click()');page.wait_for_timeout(800)
            page.goto(BASE+'/bandeja')
        assert not errors,errors
    print('PASS no JS errors')
    b.close()
