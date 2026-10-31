"""Asignar la conversación: sólo administradores de la subcuenta; el responsable no se edita para los demás."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
TEAM=[{'id':'ana','name':'Ana Ventas'},{'id':'luis','name':'Luis Ventas'}]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for who,admin in [('administrador',True),('vendedor',False)]:
        for width in ([390,1440] if admin else [1440]):
            page=b.new_context(viewport={'width':width,'height':900},service_workers='block').new_page()
            errors=[];sent=[];page.on('pageerror',lambda e:errors.append(str(e)))
            def api(route):
                req=route.request;path=urlparse(req.url).path
                if path=='/api/me':data={'user':{'role':'administrator' if admin else 'team','email':'a@b.c','identity_type':'native' if admin else 'external','capabilities':{'viewConversations':True,'sendMessages':True,'editContacts':True,'viewOpportunities':True,'editOpportunities':True}},'csrf':'t','passkeys':1}
                elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
                elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'María Cliente','lastMessageType':'TYPE_WHATSAPP','assignedTo':'luis'}],'total':1}
                elif path=='/api/workspace/messages':data={'items':[],'hasMore':False}
                elif path=='/api/workspace/contact':data={'contact':{'id':'k0','name':'María Cliente','assignedTo':'luis','tags':[],'customFields':[]},'canEdit':True,'canAssign':admin,'assignees':TEAM if admin else [],'channels':[]}
                elif path=='/api/workspace/assign':
                    assert req.headers['x-csrf-token']=='t';sent.append(req.post_data_json);data={'assigned':True,'assignedTo':'ana','assignedName':'Ana Ventas','opportunities':{'updated':2,'failed':0}}
                else:data={'items':[]}
                route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
            page.route('**/api/**',api)
            page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
            page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
            page.wait_for_selector('#live-contact-toggle');page.wait_for_timeout(1200)
            if not admin:
                assert page.locator('#live-assign').is_hidden()
                page.locator('#live-contact-toggle').click();page.wait_for_selector('#live-edit-contact')
                page.locator('#live-edit-contact').click();page.wait_for_selector('#live-contact-form')
                assert page.locator('#live-contact-form [name=assignedTo]').count()==0
                print('PASS a seller sees no «Asignar» and the contact form has no responsible to change')
            else:
                page.wait_for_selector('#live-assign:not([hidden])')
                page.locator('#live-assign').click();page.wait_for_selector('#assign-form')
                assert 'oportunidades abiertas' in page.locator('#dialog-content').inner_text()
                assert page.locator('#assign-form [name=assignedTo]').input_value()=='luis'
                page.locator('#assign-form button.primary').click()
                assert 'ya es la responsable' in page.locator('#assign-result').inner_text() and not sent
                page.select_option('#assign-form [name=assignedTo]','ana');page.locator('#assign-form button.primary').click()
                page.wait_for_function("!document.querySelector('#action-dialog').open")
                assert sent[-1]['assignedTo']=='ana' and len(sent[-1]['requestId'])>=16
                assert 'Asignada a Ana Ventas' in page.locator('#toast').inner_text() and '2 oportunidades abiertas' in page.locator('#toast').inner_text()
                print(f'PASS at {width}px an administrator assigns it, and is told the open opportunities moved too')
            assert not errors,errors
    print('PASS no JS errors')
    b.close()
