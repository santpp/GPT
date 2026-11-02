"""Selector de canal desplegable (una línea) y «Mías» oculto cuando todo lo que se ve ya es lo asignado."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
CH=[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False},{'type':'SMS','label':'SMS','available':True,'needsSubject':False},{'type':'IG','label':'Instagram','available':False,'reason':'needs_thread','needsSubject':False},{'type':'Email','label':'Email','available':False,'reason':'needs_email','needsSubject':True}]
def make(restricted):
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Ruiz','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1,'restricted':restricted}
        elif path=='/api/workspace/messages':data={'items':[{'id':'m1','body':'Hola','direction':'inbound','dateAdded':'2026-11-02T10:00:00Z'}],'hasMore':False,'channels':CH}
        elif path=='/api/workspace/contact':data={'contact':{'id':'k0','name':'Ana Ruiz','tags':[],'customFields':[]},'channels':CH,'canEdit':False,'fields':[],'assignees':[]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    return api
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',make(True))
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
    assert page.locator('#live-filter-mine').is_hidden(),'a seller who only sees their own does not need «Mías»'
    print('PASS a seller who only sees what is assigned to them has no redundant «Mías» tab')
    page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()');page.wait_for_selector('.channel-current')
    assert page.locator('.channel-list').is_hidden() and 'WhatsApp' in page.locator('.channel-current').inner_text()
    assert page.locator('#live-channel-row').bounding_box()['height']<70,page.locator('#live-channel-row').bounding_box()
    print('PASS the channel picker is one compact button, not two rows of pills')
    page.locator('.channel-current').click();assert page.locator('.channel-list').is_visible()
    assert page.locator('[data-channel="IG"]').is_disabled() and 'escribió primero' in page.locator('[data-channel="IG"]').inner_text()
    page.locator('[data-channel="SMS"]').click();page.wait_for_timeout(200)
    assert page.locator('.channel-list').is_hidden() and 'SMS' in page.locator('.channel-current').inner_text()
    page.locator('.channel-current').click();page.keyboard.press('Escape');assert page.locator('.channel-list').is_hidden()
    page.locator('.channel-current').click();page.locator('#live-reply').click();assert page.locator('.channel-list').is_hidden()
    print('PASS it opens to the list with the reasons for those unavailable, choosing one closes it, and Escape or tapping outside closes it too')
    page2=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page();page2.route('**/api/**',make(False))
    page2.goto(BASE+'/bandeja');page2.wait_for_selector('[data-live-id]');page2.wait_for_timeout(300)
    assert page2.locator('#live-filter-mine').is_visible()
    print('PASS an administrator keeps «Mías»')
    assert not errors,errors
    b.close()
