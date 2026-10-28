"""Run with Playwright + Chromium installed, against the local development server."""
import os
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH', '/usr/bin/chromium'), args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 390, 'height': 844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(os.getenv('TEST_BASE_URL', 'http://127.0.0.1:8080/'))
    page.evaluate('navigator.serviceWorker.ready')
    page.wait_for_function('navigator.serviceWorker.controller !== null')
    page.locator('[data-filter="unread"]').click()
    assert page.locator('.conversation-item').count() == 4
    page.locator('#search').fill('Lucía')
    assert page.locator('.conversation-item').count() == 1
    page.locator('#search').fill('')
    page.locator('[data-filter="all"]').click()
    for view in ['contacts', 'pipeline', 'calendar', 'tasks', 'inbox']:
        page.locator(f'[data-view="{view}"]').click()
        assert page.evaluate('document.documentElement.scrollWidth') == 390
    page.get_by_role('button', name='Abrir conversación de Lucía Martínez').click()
    page.locator('#message-input').fill('Prueba de respuesta')
    page.locator('button.send').click()
    assert 'Prueba de respuesta' in page.locator('#messages').inner_text()
    page.locator('#context-toggle').click()
    assert page.locator('#contact-panel').is_visible()
    page.locator('#close-context').click()
    context.set_offline(True)
    page.reload()
    page.wait_for_load_state('domcontentloaded')
    assert page.locator('#page-title').inner_text() == 'Cada conversación cuenta.'
    assert 'Sin conexión' in page.locator('#network').inner_text()
    assert not errors, errors
    print('PASS filters, search, 5 views, mobile overflow, conversation, composer, context, offline shell; no JS errors')
    context2=browser.new_context(viewport={'width':390,'height':844}, service_workers='block')
    admin=context2.new_page()
    items=[]
    def mock(route):
        request=route.request
        path=request.url.split('/api/')[1]
        if path=='me':
            response={'user':{'role':'administrator'},'csrf':'test-csrf'}
        elif path=='admin/diagnostics':
            response={'ready':True,'version':'0.2.1','checks':[]}
        elif request.method=='POST' and path=='admin/integrations':
            data=request.post_data_json
            assert data['token']=='pit-ui-test-not-a-real-token'
            assert request.headers['x-csrf-token']=='test-csrf'
            items.append({'id':1,'name':data['name'],'location_id':data['locationId'],'sync_interval_seconds':120,'status':'untested','last_checked_at':None,'tokenConfigured':True})
            response={'saved':True,'id':1,'tokenConfigured':True}
        else:
            response={'items':items,'syncImplemented':False}
        import json
        route.fulfill(status=200,content_type='application/json',body=json.dumps(response))
    admin.route('**/api/**',mock)
    admin.goto(os.getenv('TEST_BASE_URL', 'http://127.0.0.1:8080/'))
    admin.locator('[data-view="settings"]').click()
    admin.locator('#add-integration').click()
    admin.locator('[name="name"]').fill('Subcuenta de prueba')
    admin.locator('[name="locationId"]').fill('loc-test')
    admin.locator('[name="token"]').fill('pit-ui-test-not-a-real-token')
    admin.get_by_role('button',name='Guardar en el servidor').click()
    admin.wait_for_selector('.integration-card')
    assert admin.locator('#action-dialog').evaluate('(d)=>!d.open')
    assert admin.locator('[name="token"]').input_value()==''
    assert admin.evaluate('localStorage.length')==0
    assert 'pit-ui-test-not-a-real-token' not in admin.locator('body').inner_text()
    assert admin.evaluate('document.documentElement.scrollWidth')==390
    print('PASS admin settings form, CSRF header, cleared token and no localStorage (mock API; real API covered separately)')
    context3=browser.new_context(viewport={'width':390,'height':844}, service_workers='block')
    live=context3.new_page()
    def live_mock(route):
        import json
        path=route.request.url.split('/api/')[1]
        if path=='me':response={'user':{'role':'administrator'},'csrf':'test-csrf'}
        elif path=='workspace/connections':response={'items':[{'id':1,'name':'Test location','location_id':'loc-test'}]}
        elif path.startswith('workspace/inbox?'):response={'items':[{'id':'conv-test','contactName':'Contacto de prueba API','lastMessageBody':'<img src=x onerror=alert(1)>','unreadCount':2}],'total':25,'partial':True,'syncedAt':'2026-10-28 10:00:00','state':None}
        elif path.startswith('workspace/messages?'):response={'items':[{'id':'message-1','body':'<script>window.injected=true</script>','direction':'inbound','dateAdded':'2026-10-28T10:00:00Z','status':'delivered'}],'hasMore':True}
        else:response={'synced':{'count':1,'total':25,'partial':True}}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(response))
    live.route('**/api/**',live_mock)
    live.goto(os.getenv('TEST_BASE_URL', 'http://127.0.0.1:8080/'))
    live.locator('[data-view="live"]').click()
    live.wait_for_selector('[data-live-id]')
    assert 'Mostrando 1 de 25' in live.locator('#live-feedback').inner_text()
    assert live.locator('#live-list img').count()==0
    live.locator('[data-live-id]').click()
    live.wait_for_selector('.live-message-log')
    assert live.locator('#live-messages script').count()==0
    assert live.evaluate('window.injected === undefined')
    assert live.evaluate('document.documentElement.scrollWidth')==390
    print('PASS connected inbox, partial-state notice, messages and escaped upstream content (mock API)')
    import re
    for visible in [admin,live]:
        assert not re.search(r'gohighlevel|leadconnector|\bghl\b', visible.locator('body').inner_text(), re.I)
    for fixture_role in ['seller','supervisor']:
        restricted=browser.new_context(service_workers='block').new_page()
        restricted.route('**/api/me',lambda route,_request,role=fixture_role:route.fulfill(status=200,content_type='application/json',body='{"user":{"role":"'+role+'"},"csrf":"test"}'))
        restricted.goto(os.getenv('TEST_BASE_URL','http://127.0.0.1:8080/'))
        restricted.wait_for_timeout(100)
        assert restricted.locator('[data-view="settings"]').is_hidden()
    print('PASS white-label visible UI and restricted administration navigation')
    setup_page=browser.new_context(service_workers='block').new_page()
    prepared=[False]
    def setup_mock(route):
        import json
        path=route.request.url.split('/api/')[1]
        if path=='me':response={'user':{'role':'administrator'},'csrf':'test-csrf'}
        elif path=='admin/diagnostics':response={'ready':prepared[0],'version':'0.2.1','checks':[{'name':'encryption','ready':prepared[0],'code':None if prepared[0] else 'encryption_key_missing','message':'Falta preparar la protección.'}]}
        elif path=='admin/installation/encryption':
            assert route.request.method=='POST' and route.request.headers['x-csrf-token']=='test-csrf'
            prepared[0]=True;response={'prepared':True}
        else:response={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(response))
    setup_page.route('**/api/**',setup_mock)
    setup_page.goto(os.getenv('TEST_BASE_URL','http://127.0.0.1:8080/'))
    setup_page.locator('[data-view="settings"]').click()
    setup_page.wait_for_selector('#prepare-encryption')
    assert setup_page.locator('#add-integration').count()==0
    setup_page.locator('#prepare-encryption').click()
    setup_page.wait_for_selector('#add-integration')
    print('PASS setup preflight blocks credential entry and allows safe initial preparation (mock API)')


    # Direct URLs work without relying on a visible administrative menu.
    login_page=browser.new_context(service_workers='block').new_page()
    signed_in=[False]
    def direct_mock(route):
        import json
        path=route.request.url.split('/api/')[1]
        if path=='login':signed_in[0]=True;response={'csrf':'direct-test'};status=200
        elif path=='me':response={'user':{'role':'administrator'},'csrf':'direct-test'} if signed_in[0] else {'error':'Inicia sesión'};status=200 if signed_in[0] else 401
        elif path=='admin/diagnostics':response={'ready':True,'version':'0.2.2','checks':[]};status=200
        else:response={'items':[]};status=200
        route.fulfill(status=status,content_type='application/json',body=json.dumps(response))
    login_page.route('**/api/**',direct_mock)
    base=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080/').rstrip('/')
    login_page.goto(base+'/conexiones')
    login_page.locator('#settings-login').click()
    login_page.locator('[name="email"]').fill('admin@example.test')
    login_page.locator('[name="password"]').fill('dummy-test-password')
    login_page.locator('#login-form button').click()
    login_page.wait_for_selector('#add-integration')
    assert login_page.locator('#action-dialog').evaluate('(d)=>!d.open')
    assert login_page.url.endswith('/conexiones')
    login_page.reload();login_page.wait_for_selector('#add-integration')
    login_page.goto(base+'/#conexiones');login_page.wait_for_selector('#add-integration')
    print('PASS direct connections URL, login return, reload and hash fallback')
    browser.close()
