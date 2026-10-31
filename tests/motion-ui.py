"""Movimiento: la conversación y la ficha entran deslizando en el móvil, la lista vuelve por la izquierda, y nada se mueve con «reducir movimiento»."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def api(route):
    path=urlparse(route.request.url).path
    if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'viewContacts':True}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
    elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Ruiz','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
    elif path=='/api/workspace/messages':data={'items':[{'id':'m%d'%i,'body':'Mensaje %d'%i,'direction':'inbound','dateAdded':'2026-10-31T10:%02d:00Z'%i} for i in range(30)],'hasMore':False}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
name=lambda page,sel:page.evaluate(f"getComputedStyle(document.querySelector('{sel}')).animationName")
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for reduce in ['no-preference','reduce']:
        page=b.new_context(viewport={'width':390,'height':844},service_workers='block',reduced_motion=reduce).new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
        page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
        page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()');page.wait_for_selector('.bubble')
        want='none' if reduce=='reduce' else 'slide-in-right'
        assert name(page,'#live-messages')==want,(reduce,name(page,'#live-messages'))
        page.wait_for_timeout(500)
        assert page.evaluate("(()=>{const l=document.querySelector('#live-log');return l.scrollHeight-l.scrollTop-l.clientHeight<4})()"),'opens at the last message'
        page.locator('#live-contact-toggle').click()
        want='none' if reduce=='reduce' else 'slide-in-right'
        assert name(page,'.live-contact-panel')==want,(reduce,name(page,'.live-contact-panel'))
        page.evaluate("document.querySelector('#live-contact-toggle').click()");page.locator('#live-back').click()
        want='none' if reduce=='reduce' else 'slide-in-left'
        assert name(page,'.live-sidebar')==want,(reduce,name(page,'.live-sidebar'))
        assert not errors,errors
    print('PASS the conversation and the contact card slide in on a phone, the list returns from the left, and reduced motion keeps everything still')
    b.close()
