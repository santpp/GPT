"""Selector de subcuenta: sólo cuando hay dónde elegir; con una se entra directo; sin ninguna se explica por qué."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def run(browser,items,reason,role='team'):
    page=browser.new_context(viewport={'width':1200,'height':800},service_workers='block').new_page()
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/me':data={'user':{'role':role,'email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':bool(items)}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':items,'reason':reason}
        else:data={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api);page.goto(BASE+'/tablero');page.wait_for_selector('#app:not([hidden])');page.wait_for_timeout(400)
    return page
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    one=run(b,[{'id':1,'name':'ASOCIM'}],None)
    assert one.locator('#account').is_hidden() and 'ASOCIM' in one.locator('.account-switch').inner_text()
    print('PASS with a single sub-account there is no selector: the name is shown and the person is already in it')
    two=run(b,[{'id':1,'name':'ASOCIM'},{'id':2,'name':'Otra'}],None)
    assert two.locator('#account').is_visible() and two.locator('#account option').count()==2
    print('PASS with several sub-accounts the selector is there')
    none=run(b,[],'no_permission')
    assert none.locator('.account-switch').is_hidden()
    assert 'no tiene activadas las conversaciones' in none.locator('#view').inner_text() and 'Conexiones' not in none.locator('#view').inner_text()
    print('PASS with none, a sub-account user is told why, not to add an account from Connections')
    nomember=run(b,[],'no_membership')
    assert 'no está asociado' in nomember.locator('#view').inner_text()
    admin=run(b,[],None,role='administrator')
    assert 'Conexiones' in admin.locator('#view').inner_text()
    print('PASS the administrator is still pointed to Connections')
    b.close()
