"""A new version must be able to take over: only visible text the person changed holds it back."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':412,'height':915},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    state={'logged':False}
    def api(route,request):
        path=request.url.split('/api/')[1];status=200
        if path=='me':
            if not state['logged']: status=401;data={'error':'Inicia sesión'}
            else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
        elif path=='login': state['logged']=True;data={'csrf':'t'}
        elif path.startswith('workspace/connections'): data={'items':[{'id':1,'name':'ASOCIM','location_id':'a','status':'connected'}]}
        elif path.startswith('workspace/inbox'):
            data={'syncIntervalSeconds':120,'limit':20,'items':[{'id':'c1','contactName':'Freddy','lastMessageBody':'Hola','lastMessageType':'TYPE_WHATSAPP','contactId':'ct','locationId':'a','unreadCount':0,'lastMessageDate':'1730000000000'}],
                  'total':1,'syncedAt':None,'partial':False,'state':None,'nextCursor':None,'hasMore':False}
        elif path.startswith('workspace/messages'): data={'items':[],'hasMore':False,'lastMessageId':None}
        elif path.startswith('workspace/dashboard'): data={'restricted':False,'conversations':{'total':1,'unread':0},'contacts':None,'opportunities':None,'readAt':None}
        elif path.startswith('workspace/board-month'): data={'error':'x'}
        elif path.startswith('workspace/pulse'): data={'token':'t','unread':0,'intervalSeconds':10,'refreshed':False,'reason':None,'retryAfter':0,'syncedAt':None}
        else: data={'items':[]}
        route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('#signin:not([hidden])')
    page.locator('.admin-login summary').click()
    page.locator('#login-form [name="email"]').fill('admin@example.test')
    page.locator('#login-form [name="password"]').fill('una-contraseña')
    page.locator('#login-form button').click()
    page.wait_for_selector('#app:not([hidden])')
    # The sign-in form is hidden but still holds what was typed into it.
    assert page.locator('#login-form [name="password"]').input_value()=='una-contraseña'
    assert page.evaluate('agoraHasUnsentText()') is False, 'a hidden sign-in form must not hold a new version back'
    print('PASS after signing in, the hidden sign-in form does not hold a new version back')
    page.locator('[data-view="dashboard"]').click()
    page.wait_for_selector('#dashboard-body',state='attached')
    page.wait_for_timeout(500)
    assert page.evaluate('agoraHasUnsentText()') is False, 'the board must not hold a new version back'
    print('PASS the board, with its month picker, does not hold a new version back')
    page.locator('[data-view="inbox"]').click()
    page.wait_for_selector('[data-live-id]')
    page.locator('[data-live-id]').click()
    page.wait_for_selector('#live-reply')
    page.locator('#live-reply').fill('Un mensaje a medias')
    assert page.evaluate('agoraHasUnsentText()') is True, 'a message being written must hold the new version back'
    print('PASS a message being written still holds it back, so nothing typed is lost')
    page.locator('#live-reply').fill('')
    assert page.evaluate('agoraHasUnsentText()') is False
    print('PASS and once it is sent or cleared, the new version can take over')
    assert not errors, errors
    b.close()
