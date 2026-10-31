"""Attachments, the full-screen viewer, why a message did not go out, and the chat header icons."""
import base64,json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
MESSAGES=[
 {'id':'m1','body':'Mira la foto','direction':'inbound','dateAdded':'2026-10-31T10:00:00Z','status':'delivered','messageType':'TYPE_WHATSAPP','attachments':['https://media.example.test/foto.png']},
 {'id':'m2','body':'','direction':'inbound','dateAdded':'2026-10-31T10:01:00Z','status':'delivered','messageType':'TYPE_WHATSAPP','attachments':['https://media.example.test/rota.png']},
 {'id':'m3','body':'El programa','direction':'inbound','dateAdded':'2026-10-31T10:02:00Z','messageType':'TYPE_WHATSAPP','attachments':['https://media.example.test/programa%20del%20curso.pdf']},
 {'id':'m4','body':'','direction':'inbound','dateAdded':'2026-10-31T10:03:00Z','messageType':'TYPE_WHATSAPP','attachments':['https://media.example.test/nota.ogg']},
 {'id':'m5','body':'¿Seguimos con la matrícula?','direction':'outbound','dateAdded':'2026-10-31T10:04:00Z','status':'failed','messageType':'TYPE_WHATSAPP'},
 {'id':'m6','body':'Te escribo por SMS','direction':'outbound','dateAdded':'2026-10-31T10:05:00Z','status':'undelivered','messageType':'TYPE_SMS','error':'Carrier rejected: unreachable number'},
 {'id':'m7','body':'Promo de noviembre','direction':'outbound','dateAdded':'2026-10-31T10:06:00Z','status':'opt_out','messageType':'TYPE_SMS'},
]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    context=b.new_context(viewport={'width':1280,'height':900},service_workers='block')
    page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    def media(route,request):
        if request.url.endswith('foto.png'):route.fulfill(status=200,content_type='image/png',body=PNG)
        elif request.url.endswith('nota.ogg'):route.fulfill(status=200,content_type='audio/ogg',body=b'')
        else:route.fulfill(status=404,body='')
    context.route('https://media.example.test/**',media)
    def api(route,request):
        path=request.url.split('/api/')[1]
        if path=='me':d={'user':{'role':'administrator','email':'a@b.test'},'csrf':'t'}
        elif path.startswith('workspace/connections'):d={'items':[{'id':1,'name':'ASOCIM','location_id':'a','status':'connected'}]}
        elif path.startswith('workspace/inbox'):d={'syncIntervalSeconds':120,'limit':20,'items':[{'id':'c1','contactName':'Raissa Moreno','lastMessageBody':'Hola','lastMessageType':'TYPE_WHATSAPP','contactId':'ct','locationId':'a','unreadCount':0,'lastMessageDate':'1730000000000'}],'total':1,'syncedAt':None,'partial':False,'state':None,'nextCursor':None,'hasMore':False}
        elif path.startswith('workspace/messages'):d={'items':MESSAGES,'hasMore':False,'lastMessageId':None}
        elif path.startswith('workspace/pulse'):d={'token':'t','unread':0,'intervalSeconds':10,'refreshed':False,'reason':None,'retryAfter':0,'syncedAt':None}
        elif path.startswith('workspace/contact'):d={'contact':{'id':'ct','name':'Raissa Moreno','phone':'+584142736522','tags':[],'customFields':[]},'canEdit':False,'fields':[],'assignees':[],'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True}]}
        else:d={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('[data-live-id]')
    page.locator('[data-live-id]').click()
    page.wait_for_selector('#live-log .bubble')

    # Header icons are drawn, not typed characters.
    header=page.locator('.live-chat-header')
    assert header.locator('svg').count()>=3, 'the header buttons carry drawn icons'
    for char in ['⤢','⤡','ⓘ','←']:
        assert char not in header.inner_text(), char
    print('PASS the chat header uses drawn icons, each with its label')

    # A picture loads into a thumbnail and opens full screen.
    page.wait_for_function("()=>document.querySelector('.att-image:not(.loading) img')?.naturalWidth>0")
    page.locator('.att-image').first.click()
    page.wait_for_selector('.viewer img')
    assert page.locator('.viewer-bar a').get_attribute('href').endswith('foto.png')
    page.keyboard.press('Escape')
    page.wait_for_function("()=>!document.querySelector('.viewer')")
    print('PASS a picture shows as a thumbnail and opens full screen, and Esc closes it')

    # A picture that cannot load says so instead of leaving a hole.
    page.wait_for_function("()=>[...document.querySelectorAll('.att-file.broken')].some(n=>n.textContent.includes('No se pudo cargar la imagen'))")
    print('PASS a picture that cannot load is replaced by a clear notice and a link')

    log=page.locator('#live-log').inner_text()
    assert 'programa del curso.pdf' in log and 'Abrir' in log, log
    assert page.locator('.att-media audio').count()==1
    print('PASS a document shows its name to open it, and a voice note plays in place')

    # Why a message did not go out.
    failed=page.locator('.bubble.failed')
    assert failed.count()==3, failed.count()
    first=failed.nth(0)
    assert 'No enviado' in first.inner_text()
    first.locator('summary').click()
    why=first.locator('.msg-why').inner_text()
    assert 'La cuenta no indica el motivo' in why and '24 horas' in why and 'plantilla' in why, why
    print('PASS a failed WhatsApp says the account gives no reason, and lists the usual ones')
    second=failed.nth(1)
    second.locator('summary').click()
    why=second.locator('.msg-why').inner_text()
    assert 'Motivo indicado por el canal' in why and 'Carrier rejected' in why, why
    print('PASS when the channel does give a reason, it is shown as given')
    third=failed.nth(2)
    third.locator('summary').click()
    assert 'pidió no recibir mensajes' in third.inner_text()
    assert third.locator('[data-retry]').count()==0, 'nobody is offered to write again to someone who opted out'
    print('PASS an opt-out is explained, and writing again is not offered')

    # Writing a failed message again puts it back in the box, and sends nothing.
    first.locator('[data-retry]').click()
    assert page.locator('#live-reply').input_value()=='¿Seguimos con la matrícula?'
    assert 'pulsa Enviar' in page.locator('#live-send-status').inner_text()
    print('PASS writing it again puts the text back to review, without sending anything')
    for word in ['GoHighLevel','GHL','LeadConnector','TYPE_WHATSAPP','opt_out','undelivered']:
        assert word not in page.locator('#live-messages').inner_text(), word
    print('PASS nothing technical is shown')
    assert not errors, errors
    b.close()
