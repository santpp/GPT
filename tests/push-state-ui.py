"""Botón de avisos: campana según el estado, aviso de «actívalos» y recuperación cuando el permiso ya está dado."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def init(permission,has_sub):
    return """
(()=>{
 const fake={endpoint:'https://push.example.test/send/abcdef123456',toJSON(){return {endpoint:this.endpoint,keys:{p256dh:'B'.repeat(30),auth:'A'.repeat(14)}}},getKey(){return new Uint8Array(8)},unsubscribe:async()=>true};
 Object.defineProperty(Notification,'permission',{get:()=>'%s'});
 let sub=%s?fake:null;
 const reg={pushManager:{getSubscription:async()=>sub,subscribe:async()=>{sub=fake;return fake}}};
 Object.defineProperty(navigator,'serviceWorker',{value:{ready:Promise.resolve(reg),register:async()=>reg,addEventListener(){},getRegistration:async()=>reg},configurable:true});
})();
"""%(permission,'true' if has_sub else 'false')
def run(b,permission,has_sub,registered=True):
    posted=[]
    ctx=b.new_context(viewport={'width':1100,'height':800},service_workers='block');page=ctx.new_page()
    page.add_init_script(init(permission,has_sub))
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/devices' and req.method=='GET':data={'registered':registered}
        elif path=='/api/devices':posted.append(req.post_data_json);data={'registered':True}
        elif path=='/api/push-key':data={'ready':True,'key':'B'+'A'*86}
        elif path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}],'reason':None}
        else:data={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api);page.goto(BASE+'/tablero');page.wait_for_selector('#app:not([hidden])');page.wait_for_timeout(900)
    return page,posted
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page,posted=run(b,'default',False)
    assert page.locator('#push-toggle').get_attribute('data-state')=='off' and 'Activar' in page.locator('#push-label').inner_text()
    assert page.locator('#push-banner').is_visible() and 'desactivados' in page.locator('#push-banner').inner_text() and page.locator('#push-nudge-go').count()==1
    assert not posted,'without permission nothing is re-subscribed on its own'
    page.locator('#push-nudge-later').click();assert page.locator('#push-banner').is_hidden()
    print('PASS with notices off the button asks to turn them on and a notice says so, which can be dismissed')
    page,posted=run(b,'denied',False)
    assert page.locator('#push-toggle').get_attribute('data-state')=='blocked' and 'candado' in page.locator('#push-banner').inner_text() and page.locator('#push-nudge-go').count()==0
    print('PASS with notices blocked the notice explains how to allow them')
    page,posted=run(b,'granted',False)
    assert posted and posted[0]['token'].startswith('https://push.example.test/') and page.locator('#push-toggle').get_attribute('data-state')=='on'
    assert page.locator('#push-banner').is_hidden()
    print('PASS with permission already given but no subscription, the notices are rebuilt by themselves')
    page,posted=run(b,'granted',True,registered=True)
    assert not posted and page.locator('#push-toggle').get_attribute('data-state')=='on'
    print('PASS and nothing is touched when everything is already in order')
    b.close()
