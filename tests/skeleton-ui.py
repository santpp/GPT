"""Carga: mientras llegan los datos se ven esqueletos (no textos «Cargando…») y desaparecen al llegar."""
import json,os,time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for scheme in ['light','dark']:
        page=b.new_context(viewport={'width':390,'height':844},service_workers='block',color_scheme=scheme).new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def api(route):
            path=urlparse(route.request.url).path
            if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'viewContacts':True}},'csrf':'t','passkeys':0}
            elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
            else:
                time.sleep(1.2)
                if path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Ruiz','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
                elif path=='/api/workspace/dashboard':data={'restricted':True,'conversations':{'total':4,'unread':1},'contacts':None,'leads':{'today':3,'mine':27,'linked':True},'opportunities':None,'readAt':'2026-10-31T20:00:00+00:00'}
                else:data={'items':[]}
            route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/bandeja');page.wait_for_selector('.sk-wrap',timeout=5000)
        assert 'Cargando conversaciones' not in page.locator('#app').inner_text()
        page.screenshot(path=f'/tmp/sk-list-{scheme}.png')
        page.wait_for_selector('[data-live-id]');assert page.locator('.sk-wrap').count()==0
        page.locator('[data-view="dashboard"]').click();page.wait_for_selector('.sk-tiles',timeout=5000)
        page.screenshot(path=f'/tmp/sk-board-{scheme}.png')
        page.wait_for_selector('.updated-at:not([hidden])',timeout=8000)
        assert 'actualizado a las' in page.locator('.updated-at').inner_text().lower() and page.locator('.sk-wrap').count()==0
        page.screenshot(path=f'/tmp/sk-board-done-{scheme}.png')
        assert not errors,errors
    print('PASS skeletons appear while loading, in light and dark, and give way to the content')
    b.close()
