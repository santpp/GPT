"""Real sign-in gate, global subaccount switch and the real screens; no sample data."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':1440,'height':900},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    state={'logged':False}
    def api(route):
        path=route.request.url.split('/api/')[1]
        status=200
        if path=='me':
            if not state['logged']: status=401;data={'error':'Inicia sesión'}
            else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
        elif path=='login':
            state['logged']=True;data={'csrf':'t'}
        elif path.startswith('workspace/connections'):
            data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'},{'id':2,'name':'Cuenta B','location_id':'b','status':'connected'}]}
        elif path.startswith('workspace/inbox'):
            data={'syncIntervalSeconds':120,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None}
        elif path.startswith('workspace/sync'): data={'synced':True}
        elif path.startswith('workspace/contacts'):
            data={'items':[{'id':'c1','name':'Andreina Del Nogal','email':'a@x.test','phone':'+58414','companyName':None,'assignedName':'Maria Carvallo','tags':['VIP'],'dateAdded':None}],'total':1,'query':None,'restricted':False,'partial':False}
        elif path.startswith('workspace/dashboard'):
            data={'restricted':False,'conversations':{'total':47,'unread':5},'contacts':812,'opportunities':{'count':3,'value':3500.5,'stages':[{'name':'Pipeline comercial · Propuesta enviada','count':1,'value':2400.0}],'sampled':False},'readAt':'2026-01-01T10:00:00+00:00'}
        elif path.startswith('workspace/board'):
            data={'pipelines':[{'id':'p1','name':'Pipeline comercial'}],'pipelineId':'p1','stages':[{'id':'s1','name':'Nuevo lead','items':[{'id':'o1','name':'Proyecto X','contactName':'Andreina','monetaryValue':2400,'assignedName':'Maria'}],'total':1,'value':2400.0}],'total':1,'restricted':False,'partial':False}
        elif path=='admin/diagnostics': data={'ready':True,'version':'0.11.0','checks':[]}
        elif path.startswith('admin/integrations'): data={'items':[],'accessChannel':{'connectionId':None,'ready':False}}
        elif path=='logout': state['logged']=False;data={'ok':True}
        else: data={'items':[]}
        route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('#signin:not([hidden])')
    assert page.locator('#app').is_hidden(), 'the workspace must stay hidden before signing in'
    body=page.locator('body').inner_text()
    for word in ['demostración','ejemplo','Demo']:
        assert word not in body, f'sample wording still on the sign-in screen: {word}'
    print('PASS sign-in gate shown, workspace hidden, no sample wording')
    page.locator('.admin-login summary').click()
    page.locator('#login-form [name="email"]').fill('admin@example.test')
    page.locator('#login-form [name="password"]').fill('secret')
    page.locator('#login-form button').click()
    page.wait_for_selector('#app:not([hidden])')
    page.wait_for_selector('#account option',state='attached')
    assert page.locator('#account').input_value()=='1'
    assert 'Cuenta A' in page.locator('#account-name').inner_text()
    print('PASS signing in enters the workspace with the account selector ready')
    nav=page.locator('.sidebar nav').inner_text()
    assert 'Calendario' not in nav and 'Mis tareas' not in nav, nav
    assert 'Conversaciones' in nav and 'Contactos' in nav and 'Oportunidades' in nav and 'Conexiones' in nav and 'Tablero' in nav
    print('PASS menu offers only the real screens')
    page.locator('[data-view="contacts"]').click()
    page.wait_for_selector('.record-row',state='attached')
    assert 'Andreina Del Nogal' in page.locator('#contact-results').inner_text()
    assert page.url.endswith('/contactos')
    print('PASS contacts screen shows real account data')
    page.locator('[data-view="board"]').click()
    page.wait_for_selector('.board-card',state='attached')
    board=page.locator('#view').inner_text()
    assert 'Pipeline comercial' in board and 'Proyecto X' in board and '2.400' in board
    assert page.url.endswith('/oportunidades')
    print('PASS opportunity board shows real stages and cards')
    page.locator('[data-view="dashboard"]').click()
    page.wait_for_selector('.board-tile',state='attached')
    board_view=page.locator('#view').inner_text()
    assert '47' in board_view and 'Sin leer' in board_view and '812' in board_view
    assert 'Pipeline comercial · Propuesta enviada' in board_view
    assert page.url.endswith('/tablero')
    print('PASS the board screen shows what the account answered')
    page.locator('[data-view="inbox"]').click()
    page.wait_for_selector('#live-list',state='attached')
    toolbar=page.locator('.live-toolbar').inner_text()
    assert 'Actualizar' not in toolbar, toolbar
    assert page.locator('#sync-live').count()==0 and page.locator('#live-connection').count()==0
    print('PASS inbox has no manual refresh and no dropdown of its own')
    assert page.locator('.breadcrumbs img.brand-dot[src="/assets/agora-mark.svg"]').count()==1 and 'Á' not in page.locator('.breadcrumbs').inner_text()
    assert page.locator('.sidebar .nav svg.nav-icon').count()==page.locator('.sidebar .nav').count()
    assert 'caché' not in page.locator('#toast').inner_text().lower()
    print('PASS the header carries the Ágora mark, the menu draws its own icons, and no technical notice appears')
    page.select_option('#account','2')
    page.wait_for_timeout(600)
    assert 'Cuenta B' in page.locator('#account-name').inner_text()
    print('PASS the header switch changes the account for the whole workspace')
    page.locator('#signout').click()
    page.wait_for_selector('#signin:not([hidden])')
    print('PASS signing out returns to the sign-in screen')
    assert not errors, errors
    print('PASS no JS errors')
    b.close()
