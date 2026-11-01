"""Sin respuesta: una pestaña en la bandeja (las que esperan, las más antiguas primero, con el tiempo) y un acceso desde el tablero."""
import json,os,time
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
asked=[]
now=int(time.time()*1000)
def api(route):
    u=urlparse(route.request.url);q=parse_qs(u.query);path=u.path
    if path=='/api/me':data={'user':{'role':'administrator','email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
    elif path=='/api/workspace/inbox':
        asked.append(u.query)
        items=[{'id':'new','contactName':'Reciente','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola','lastMessageDirection':'inbound','lastMessageDate':now-5*60000},
               {'id':'old','contactName':'Antigua','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'¿Sigue?','lastMessageDirection':'inbound','lastMessageDate':now-3*3600000}]
        data={'limit':20,'items':items,'total':2}
    elif path=='/api/workspace/dashboard':data={'restricted':False,'conversations':{'total':9,'unread':2,'waiting':4},'contacts':None,'leads':{'today':1,'mine':None,'linked':False,'range':{}},'opportunities':None,'readAt':'2026-11-01T20:00:00+00:00'}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
    page.locator('[data-live-filter="waiting"]').click();page.wait_for_function("document.querySelector('.waiting')")
    assert any('direction=inbound' in q for q in asked),asked
    ids=page.locator('[data-live-id]').evaluate_all("els=>els.map(e=>e.dataset.liveId)")
    assert ids==['old','new'],ids
    assert 'Espera 3 h' in page.locator('[data-live-id="old"]').inner_text() and 'Espera 5 min' in page.locator('[data-live-id="new"]').inner_text()
    assert page.locator('[data-live-id="old"] .waiting.late').count()==1
    print('PASS the «Sin respuesta» tab asks for conversations whose last message is the contact\'s, oldest first, with how long they have waited')
    page.goto(BASE+'/tablero');page.wait_for_selector('.board-tile')
    assert 'Esperando respuesta' in page.locator('#dashboard-body').inner_text()
    page.locator('[data-go-waiting]').click();page.wait_for_selector('[data-live-filter="waiting"].active')
    assert any('direction=inbound' in q for q in asked[-2:])
    print('PASS the board shows how many are waiting and opens the list already filtered')
    assert not errors,errors
    b.close()
