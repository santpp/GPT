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
    browser.close()
