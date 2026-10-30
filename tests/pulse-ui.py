"""Near-live updates driven by the pulse, the list growing past the window, and the Android shell registering its phone."""
import json,os,time
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')

def conversation(n,unread=0):
    return {'id':f'c{n}','contactName':f'Contacto {n}','lastMessageBody':f'Mensaje {n}','lastMessageType':'TYPE_WHATSAPP',
            'contactId':f'ct{n}','locationId':'a','unreadCount':unread,'lastMessageDate':str(1730000000000-n*1000)}

with sync_playwright() as b_pw:
    b=b_pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])

    def session(native=False):
        context=b.new_context(viewport={'width':1440,'height':900},service_workers='block')
        page=context.new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        calls=[];devices=[]
        state={'logged':False,'token':'token-a','unread':0,'page':1}
        if native:
            page.add_init_script("""
              window.__pushListeners={};
              window.Capacitor={isNativePlatform:()=>true,Plugins:{PushNotifications:{
                checkPermissions:async()=>({receive:'granted'}),
                requestPermissions:async()=>({receive:'granted'}),
                addListener:(name,fn)=>{window.__pushListeners[name]=fn;return {remove(){}}},
                register:async()=>{setTimeout(()=>window.__pushListeners.registration?.({value:'simulated-device-token-0123456789abcdef'}),10)},
                removeAllListeners:async()=>{},
              }}};
            """)
        def api(route,request):
            path=request.url.split('/api/')[1];status=200;calls.append(path)
            if path=='me':
                if not state['logged']: status=401;data={'error':'Inicia sesión'}
                else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
            elif path=='login': state['logged']=True;data={'csrf':'t'}
            elif path=='logout': state['logged']=False;data={'ok':True}
            elif path.startswith('devices'):
                devices.append({'method':request.method,'body':json.loads(request.post_data or '{}')})
                data={'registered':request.method=='POST','notifications':True}
            elif path.startswith('workspace/connections'):
                data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'}]}
            elif path.startswith('workspace/pulse'):
                data={'token':state['token'],'unread':state['unread'],'syncedAt':'2026-10-30 18:00:00','intervalSeconds':10,'refreshed':True,'reason':None,'retryAfter':0}
            elif path.startswith('workspace/inbox'):
                if 'startAfter=' in path:
                    items=[conversation(n) for n in range(21,24)]
                    data={'syncIntervalSeconds':120,'limit':20,'items':items,'total':23,'syncedAt':'2026-10-30 18:00:00',
                          'partial':False,'state':None,'unread':state['unread'],'nextCursor':None,'hasMore':False,'channels':{}}
                else:
                    items=[conversation(n,2 if n==1 and state['unread'] else 0) for n in range(1,21)]
                    data={'syncIntervalSeconds':120,'limit':20,'items':items,'total':23,'syncedAt':'2026-10-30 18:00:00',
                          'partial':True,'state':None,'unread':state['unread'],'nextCursor':'1730000020000','hasMore':True,'channels':{}}
            elif path.startswith('workspace/messages'): data={'items':[],'hasMore':False,'lastMessageId':None}
            elif path.startswith('workspace/sync'): data={'synced':True}
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
        return page,errors,calls,devices,state,context

    page,errors,calls,devices,state,context=session()
    page.wait_for_selector('[data-live-id]',state='attached')
    assert page.locator('[data-live-id]').count()==20
    page.wait_for_selector('#live-more',state='attached')
    print('PASS the list offers to continue past the window the account answered')
    page.locator('#live-more').click()
    page.wait_for_function('()=>document.querySelectorAll("[data-live-id]").length===23')
    assert page.locator('#live-more').count()==0, 'the end of the list offers nothing more'
    assert any('startAfter=1730000020000' in c for c in calls), calls
    print('PASS it continues after the position the account gave, and stops at the end')

    # The pulse must cost the account nothing until something really changes.
    before=[c for c in calls if c.startswith('workspace/inbox')]
    page.wait_for_function('()=>true')
    page.wait_for_timeout(7000)
    pulses=[c for c in calls if c.startswith('workspace/pulse')]
    after=[c for c in calls if c.startswith('workspace/inbox')]
    assert len(pulses)>=1, calls
    assert len(after)==len(before), f'an unchanged pulse must not reload anything: {after}'
    print('PASS while nothing changes the screen asks Ágora and lets the account be')

    state['token']='token-b';state['unread']=2
    page.wait_for_function('()=>{const b=document.querySelector("#nav-unread");return b&&!b.hidden&&b.textContent==="2"}',timeout=15000)
    print('PASS a change reaches the menu badge within seconds')
    page.wait_for_function('()=>document.querySelector(".live-conversation .unread")!==null',timeout=15000)
    assert len([c for c in calls if c.startswith('workspace/inbox')])>len(before)
    print('PASS and reloads the list by itself, with no button to press')
    status=page.locator('#live-auto-status').inner_text()
    assert 'vivo' in status, status
    for word in ['GoHighLevel','GHL','LeadConnector','pulse','token']:
        assert word not in page.locator('body').inner_text(), word
    print('PASS the screen says it is live without naming anything technical')
    assert not errors, errors
    context.close()

    # The Android shell registers the phone and releases it on sign-out.
    page,errors,calls,devices,state,context=session(native=True)
    page.wait_for_function('()=>window.agoraDeviceToken!==undefined',timeout=15000)
    assert devices and devices[0]['method']=='POST', devices
    assert devices[0]['body']=={'token':'simulated-device-token-0123456789abcdef','platform':'android'}, devices
    print('PASS inside the Android shell the phone registers itself for notifications')
    page.locator('#signout').click()
    page.wait_for_selector('#signin:not([hidden])')
    assert any(d['method']=='DELETE' for d in devices), devices
    print('PASS and signing out on that phone stops it being notified')
    assert not errors, errors
    context.close()
    b.close()
