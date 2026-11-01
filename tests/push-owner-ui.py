"""Una persona que entra en un navegador donde ya había avisos de otra los recibe a su nombre: el navegador los re-registra al entrar."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
posted=[]
INIT="""
(()=>{
 const fake={endpoint:'https://push.example.test/send/abcdef123456',toJSON(){return {endpoint:this.endpoint,keys:{p256dh:'B'.repeat(30),auth:'A'.repeat(14)}}},getKey(){return new Uint8Array(8)},unsubscribe:async()=>true};
 Object.defineProperty(Notification,'permission',{get:()=>'granted'});
 const reg={pushManager:{getSubscription:async()=>fake,subscribe:async()=>fake}};
 Object.defineProperty(navigator,'serviceWorker',{value:{ready:Promise.resolve(reg),register:async()=>reg,addEventListener(){},getRegistration:async()=>reg},configurable:true});
})();
"""
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':1200,'height':800},service_workers='block').new_page()
    page.add_init_script(INIT)
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/devices':posted.append((req.method,req.post_data_json));route.fulfill(status=200,content_type='application/json',body='{"registered":true}');return
        if path=='/api/me':data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True}},'csrf':'tok','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}],'reason':None}
        else:data={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api);page.goto(BASE+'/tablero');page.wait_for_selector('#app:not([hidden])');page.wait_for_timeout(600)
    assert posted and posted[0][0]=='POST' and posted[0][1]['token']=='https://push.example.test/send/abcdef123456',posted
    print('PASS on entering, the notices already on this browser are registered to the person who just came in')
    b.close()
