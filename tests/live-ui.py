"""Connected inbox regression checks with isolated, explicit mock data."""
import json
import os
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for width in [390,1440]:
        context=browser.new_context(viewport={'width':width,'height':844},service_workers='block')
        page=context.new_page();errors=[];calls=[];fail=[True];held=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        def api(route):
            path=route.request.url.split('/api/')[1];calls.append(path);status=200
            if path=='me':data={'user':{'role':'administrator'},'csrf':'test'}
            elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta uno'},{'id':2,'name':'Cuenta dos'}]}
            elif path.startswith('workspace/inbox?'):
                data={'items':[{'id':'c1','contactName':'Ángeles García','lastMessageBody':'Texto reciente','lastMessageType':'TYPE_INSTAGRAM','unreadCount':3},{'id':'c2','contactName':'Ana','lastMessageBody':'Otro mensaje','lastMessageType':'TYPE_WHATSAPP','unreadCount':0}],'total':40,'partial':True,'syncedAt':'2026-10-28 19:00:00'} if 'connectionId=1' in path else {'items':[],'total':0,'partial':False,'syncedAt':None}
            elif path.startswith('workspace/messages?'):
                if 'conversationId=c2' in path:
                    held.append(route);return
                if 'lastMessageId=m2' in path and fail[0]:
                    fail[0]=False;status=429;data={'error':'Consulta pausada','retryAfter':1}
                elif 'lastMessageId=m2' in path:
                    data={'items':[{'id':'m1','body':'Mensaje anterior','dateAdded':'2026-10-27T10:00:00Z','direction':'inbound'},{'id':'m2','body':'Texto reciente','dateAdded':'2026-10-28T10:00:00Z'}],'hasMore':False}
                else:data={'items':[{'id':'m2','body':'Texto reciente','dateAdded':'2026-10-28T10:00:00Z'},{'id':'m3','body':'<script>window.injected=true</script>','direction':'outbound','dateAdded':'2026-10-28T11:00:00Z'}],'hasMore':True,'lastMessageId':'m2'}
            elif path.startswith('workspace/contact?'):
                data={'contact':{'name':'Ángeles García','phone':'+34123456789','email':'angeles@example.test','source':'Formulario web','assignedName':'Ana Responsable','lastActivity':'2026-10-28T11:00:00Z','tags':['Interesada'],'customFields':[{'label':'Campo personalizado','value':'Consulta'}]}}
            elif path.startswith('workspace/contact-fields?'):
                data={'items':[]}
            else:data={'items':[]}
            route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(os.getenv('TEST_BASE_URL','http://127.0.0.1:8080/'))
        page.wait_for_selector('[data-live-id]')
        assert page.locator('button[data-view="live"]').is_visible()
        assert page.locator('button[data-view="inbox"]').get_attribute('class').endswith('active')
        assert page.locator('#inbox-view').is_hidden()
        assert 'Instagram' in page.locator('#live-list').inner_text()
        assert 'TYPE_' not in page.locator('#live-list').inner_text()
        count=len(calls)
        page.locator('#live-search').fill('angeles')
        assert page.locator('[data-live-id]').count()==1
        page.locator('#live-search').fill('')
        page.locator('[data-live-filter="unread"]').click()
        assert page.locator('[data-live-id]').count()==1 and len(calls)==count
        page.locator('[data-live-id="c1"]').click()
        page.wait_for_selector('#live-log .bubble')
        page.wait_for_selector('#live-contact-panel h2',state='attached')
        assert 'Ana Responsable' in page.locator('#live-contact-panel').inner_text()
        if width==390:
            page.locator('#live-contact-toggle').click();assert page.locator('#live-contact-panel').is_visible();page.locator('#live-profile-close').click()
        assert page.locator('#live-log script').count()==0
        assert page.evaluate('window.injected === undefined')
        page.locator('#live-older').click()
        page.wait_for_selector('#live-retry')
        assert page.locator('#live-log .bubble').count()==2
        page.locator('#live-retry').click()
        page.wait_for_function("document.querySelectorAll('#live-log .bubble').length===3")
        assert 'Mensaje anterior' in page.locator('#live-log .bubble').first.inner_text()
        assert page.locator('#live-older').is_hidden()
        assert page.evaluate('document.documentElement.scrollWidth')==width
        if width==390:
            assert page.locator('.live-sidebar').is_hidden()
            page.locator('#live-back').click()
            assert page.locator('.live-sidebar').is_visible()
        page.screenshot(path=f'/tmp/agora-030-{width}.png',full_page=True)
        page.locator('[data-live-filter="all"]').click()
        page.locator('[data-live-id="c2"]').click()
        page.wait_for_timeout(100)
        if width==390:page.locator('#live-back').click()
        page.locator('[data-live-id="c1"]').click()
        page.wait_for_selector('#live-log .bubble')
        assert held
        held.pop().fulfill(status=200,content_type='application/json',body=json.dumps({'items':[{'id':'stale','body':'WRONG CONVERSATION'}],'hasMore':False}))
        page.wait_for_timeout(100)
        assert 'WRONG CONVERSATION' not in page.locator('#live-messages').inner_text()
        if width==390:page.locator('#live-back').click()
        page.locator('#live-connection').select_option('2')
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===0")
        assert page.locator('#live-log').count()==0
        assert not errors,errors
        context.close()
        print(f'PASS {width}px: default real inbox, local search/filter, channel names, pagination/retry/dedup, XSS, mobile back and account change')
    browser.close()
