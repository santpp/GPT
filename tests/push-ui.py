"""Turning notifications on from the browser, and what iOS needs first."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
# A real 65-byte public point, as the server publishes it.
KEY='BAWSxVDFmt3P_YhsCTwrSksJA0PAZ69-MV3CUucK6vLJp2iR2r8cEIDBLYnrcEGLAwVsDI7m6Y21xBcWveAI_Z0'

def session(b,*,apple=False,standalone=False,ready=True,permission='default'):
    context=b.new_context(viewport={'width':1440,'height':900},service_workers='block',
                          user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15' if apple else None)
    page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    devices=[];state={'logged':False}
    # A browser whose push service is simulated: the page never reaches a real one.
    page.add_init_script("""
      window.__pushState={permission:'%s',subscribed:false,subscribeCalls:[],unsubscribed:0};
      Object.defineProperty(window,'Notification',{configurable:true,value:class {
        static get permission(){return window.__pushState.permission}
        static async requestPermission(){window.__pushState.permission=window.__pushState.asked||'granted';return window.__pushState.permission}
      }});
      window.PushManager=function(){};
      const subscription={endpoint:'https://push.example.test/suscripcion-simulada-0123456789',
        toJSON:()=>({keys:{p256dh:'%s',auth:'bXktYXV0aC1zZWNyZXQtMTY='}}),
        getKey:()=>new Uint8Array(16),
        unsubscribe:async()=>{window.__pushState.subscribed=false;window.__pushState.unsubscribed++;return true}};
      const manager={
        getSubscription:async()=>window.__pushState.subscribed?subscription:null,
        subscribe:async(options)=>{window.__pushState.subscribeCalls.push({userVisibleOnly:options.userVisibleOnly,keyLength:options.applicationServerKey?.length});window.__pushState.subscribed=true;return subscription}};
      Object.defineProperty(navigator,'serviceWorker',{configurable:true,value:{
        ready:Promise.resolve({pushManager:manager}),
        register:async()=>({pushManager:manager,addEventListener(){},installing:null,waiting:null,update:async()=>{}}),
        addEventListener(){},controller:null}});
      %s
    """%(permission,'BPk7'+'a'*80,'Object.defineProperty(navigator,"standalone",{value:true,configurable:true});' if standalone else ''))
    def api(route,request):
        path=request.url.split('/api/')[1];status=200
        if path=='me':
            if not state['logged']: status=401;data={'error':'Inicia sesión'}
            else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
        elif path=='login': state['logged']=True;data={'csrf':'t'}
        elif path=='push-key': data={'key':KEY,'ready':ready}
        elif path.startswith('devices'):
            devices.append({'method':request.method,'body':json.loads(request.post_data or '{}')})
            data={'registered':request.method=='POST','notifications':True}
        elif path.startswith('workspace/connections'):
            data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'}]}
        elif path.startswith('workspace/inbox'):
            data={'syncIntervalSeconds':120,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None,'nextCursor':None,'hasMore':False}
        elif path.startswith('workspace/pulse'):
            data={'token':'t','unread':0,'syncedAt':None,'intervalSeconds':10,'refreshed':False,'reason':None,'retryAfter':0}
        else: data={'items':[]}
        route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('#signin:not([hidden])')
    page.locator('.admin-login summary').click()
    page.locator('#login-form [name="email"]').fill('admin@example.test')
    page.locator('#login-form [name="password"]').fill('secret')
    page.locator('#login-form button').click()
    page.wait_for_selector('#app:not([hidden])')
    page.wait_for_selector('#push-toggle:not([hidden])')
    return page,errors,devices,context

with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])

    # A normal browser: one tap turns notifications on.
    page,errors,devices,context=session(b)
    assert 'Activar avisos' in page.locator('#push-toggle').inner_text()
    page.locator('#push-toggle').click()
    page.wait_for_function("()=>document.querySelector('#push-toggle').dataset.state==='on'")
    assert len(devices)==1 and devices[0]['method']=='POST', devices
    body=devices[0]['body']
    assert body['platform']=='web' and body['token'].startswith('https://push.example.test/'), body
    assert body['keys']['p256dh'] and body['keys']['auth'], body
    assert body['label'], 'the device says what it is, for the diagnostics'
    print('PASS one tap subscribes this browser and hands Ágora only the address and its keys')
    call=page.evaluate("window.__pushState.subscribeCalls[0]")
    assert call['userVisibleOnly'] is True, call
    assert call['keyLength']==65, f'the server key must travel as the 65 bytes a subscription needs: {call}'
    print('PASS it subscribes as the specification requires, with the key the server published')
    page.locator('#push-toggle').click()
    page.wait_for_function("()=>document.querySelector('#push-toggle').dataset.state==='off'")
    assert any(d['method']=='DELETE' for d in devices), devices
    assert page.evaluate("window.__pushState.unsubscribed")==1
    print('PASS and tapping again stops this device being notified, here and in the browser')
    assert not errors, errors
    context.close()

    # iPhone in Safari, not yet on the home screen: it must explain, never ask.
    page,errors,devices,context=session(b,apple=True)
    assert page.locator('#push-toggle').get_attribute('data-state')=='needs-install'
    assert 'pantalla de inicio' in page.locator('#push-toggle').inner_text()
    page.locator('#push-toggle').click()
    page.wait_for_selector('#toast:not([hidden])')
    assert 'Compartir' in page.locator('#toast').inner_text()
    assert devices==[], 'nothing may be registered before the app is installed'
    assert page.evaluate("window.__pushState.permission")=='default', 'the permission must not be asked on iOS before installing'
    print('PASS on an iPhone in Safari it explains how to install instead of burning the only prompt')
    assert not errors, errors
    context.close()

    # The same iPhone, already added to the home screen.
    page,errors,devices,context=session(b,apple=True,standalone=True)
    assert page.locator('#push-toggle').get_attribute('data-state')=='off'
    page.locator('#push-toggle').click()
    page.wait_for_function("()=>document.querySelector('#push-toggle').dataset.state==='on'")
    assert devices and devices[0]['body']['platform']=='web'
    assert 'instalada' in devices[0]['body']['label'], devices[0]
    print('PASS once added to the home screen the same iPhone subscribes normally')
    assert not errors, errors
    context.close()

    # A blocked permission must be explained, not retried in a loop.
    page,errors,devices,context=session(b,permission='denied')
    assert page.locator('#push-toggle').get_attribute('data-state')=='blocked'
    page.locator('#push-toggle').click()
    page.wait_for_selector('#toast:not([hidden])')
    assert 'bloquead' in page.locator('#toast').inner_text().lower()
    assert devices==[], devices
    print('PASS a blocked permission is explained instead of asked again')
    assert not errors, errors
    context.close()

    # A server with no keys yet must say so rather than fail silently.
    page,errors,devices,context=session(b,ready=False)
    page.locator('#push-toggle').click()
    page.wait_for_selector('#toast:not([hidden])')
    assert 'servidor' in page.locator('#toast').inner_text()
    assert devices==[], devices
    print('PASS and a server without its keys says so instead of failing quietly')
    assert not errors, errors
    context.close()
    b.close()

# --- the delivery chain in Connections, and the test notice result staying put ---
with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    context=b.new_context(viewport={'width':412,'height':915},service_workers='block')
    page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    state={'logged':False}
    def api(route,request):
        path=request.url.split('/api/')[1];status=200
        if path=='me':
            if not state['logged']: status=401;data={'error':'Inicia sesión'}
            else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
        elif path=='login': state['logged']=True;data={'csrf':'t'}
        elif path=='admin/diagnostics': data={'ready':True,'version':'0.21.0','checks':[]}
        elif path.startswith('admin/integrations'): data={'items':[],'accessChannel':{'connectionId':None,'ready':False}}
        elif path=='devices/test': data={'results':[{'device':1,'label':'Android (instalada)','platform':'web','result':'sent'}]}
        elif path=='admin/cron-url':
            state['cron']=request.method=='POST'
            if request.method=='POST':
                if (json.loads(request.post_data or '{}').get('password'))!='clave-correcta':
                    route.fulfill(status=403,content_type='application/json',body=json.dumps({'error':'La contraseña no es correcta.','code':'password_mismatch'}));return
                data={'enabled':True,'endpoint':'https://nueva.agoradigital.app/api/cron','header':'X-Agora-Key','key':'ab'*32,'url':'https://nueva.agoradigital.app/api/cron/'+'ab'*32}
            else: data={'enabled':False}
        elif path=='devices': data={'items':[{'id':1,'platform':'web','label':'Android (instalada)','created_at':'2026-10-31 08:00:00','last_seen_at':'2026-10-31 08:00:00','last_sent_at':None}],
            'browser':True,'android':False,'queue':{'pending':2,'sent':0,'failed':1,'lastError':'unavailable: El servicio de avisos del navegador respondió 503.'},
            'delivery':{'at':'2020-01-01T00:00:00Z','sent':0},'discovery':None,'externalCheck':'2026-10-31T10:00:00Z' if state.get('cron') else None}
        elif path.startswith('workspace/connections'): data={'items':[]}
        else: data={'items':[]}
        route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('#signin:not([hidden])')
    page.locator('.admin-login summary').click()
    page.locator('#login-form [name="email"]').fill('admin@example.test')
    page.locator('#login-form [name="password"]').fill('secret')
    page.locator('#login-form button').click()
    page.wait_for_selector('#app:not([hidden])')
    page.locator('[data-view="settings"]').evaluate('(n)=>n.click()')
    page.wait_for_selector('.push-chain',state='attached')
    chain=page.locator('.push-chain').inner_text()
    assert 'no se está ejecutando en el servidor' in chain, chain
    assert 'La hace el cron de revisión' in chain, chain
    assert '1 fallidos' in chain and 'respondió 503' in chain, chain
    print('PASS the device list says which link of the chain is broken, in words')
    page.locator('#push-test').click()
    page.wait_for_function("()=>(document.querySelector('#push-test-result')?.textContent||'').includes('enviado')")
    page.wait_for_timeout(800)
    assert 'enviado' in page.locator('#push-test-result').inner_text(), 'the result must survive the panel being redrawn'
    print('PASS the test notice result stays on screen after the panel refreshes')
    # The server cron is not running: Connections offers the check from outside.
    assert 'La revisión no está corriendo en el servidor' in page.locator('.external-check').inner_text()
    page.locator('#cron-new').click()
    page.wait_for_selector('#cron-confirm')
    page.locator('#cron-confirm [name=password]').fill('equivocada');page.locator('#cron-confirm button.primary').click()
    page.wait_for_function("document.querySelector('#cron-confirm-result').textContent.includes('contraseña')")
    assert page.locator('#cron-key').count()==0
    print('PASS the key is only created after typing the password again')
    page.locator('#cron-confirm [name=password]').fill('clave-correcta');page.locator('#cron-confirm button.primary').click()
    page.wait_for_selector('#cron-key',state='attached')
    page.wait_for_timeout(600)
    assert page.locator('#cron-key').input_value()=='ab'*32 and page.locator('#cron-header').input_value()=='X-Agora-Key' and page.locator('#cron-endpoint').input_value().endswith('/api/cron'), 'the key travels in a header, apart from the address'
    box=page.locator('.external-check').inner_text()
    assert 'no se volverá a mostrar' in box and 'cada minuto' in box and 'Cabeceras' in box, box
    print('PASS when the server cron does not run, the owner can create the outside check, with the steps')
    page.locator('#cron-done').click()
    page.wait_for_selector('#cron-off',state='attached')
    assert page.locator('#cron-key').count()==0 and page.locator('#cron-url').count()==0, 'once acknowledged, the key is not shown again'
    print('PASS the address is shown once, and afterwards it can only be changed or turned off')
    assert not errors, errors
    context.close();b.close()
