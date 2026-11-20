"""1.10.34 · La app nunca borra la suscripción de avisos del teléfono al volver a Ágora.
Con el servidor diciendo «registrado», «no registrado» o fallando, heal() no llama a unsubscribe() ni pide otra suscripción;
si el servidor no la tiene, registra la misma. Solo crea una si el teléfono no tiene ninguna."""
import json,os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
MOCK="""(()=>{const sub={endpoint:'https://fcm.googleapis.com/fcm/send/abc123',toJSON(){return {keys:{p256dh:'B'.repeat(80),auth:'A'.repeat(22)}}},unsubscribe(){window.__log.push('unsubscribe');return Promise.resolve(true)},getKey(){return new ArrayBuffer(8)}};
 window.__log=[];window.__has=true;
 const pm={getSubscription(){return Promise.resolve(window.__has?sub:null)},subscribe(){window.__log.push('subscribe');window.__has=true;return Promise.resolve(sub)}};
 Object.defineProperty(window,'PushManager',{value:function(){},configurable:true});
 Object.defineProperty(window,'Notification',{value:{permission:'granted',requestPermission:()=>Promise.resolve('granted')},configurable:true});
 Object.defineProperty(navigator,'serviceWorker',{value:{ready:Promise.resolve({pushManager:pm}),register:()=>Promise.resolve({}),addEventListener(){},controller:null},configurable:true});})()"""
n=0
def ok(c,m):
    global n
    assert c,m
    n+=1;print('PASS',m)
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for caso,resp in [('registrado',(200,{'registered':True})),('no registrado',(200,{'registered':False})),('servidor con error',(403,{'error':'Acceso restringido'})),('sin suscripción',None)]:
        ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block');ctx.add_init_script(MOCK)
        pg=ctx.new_page();pg.set_default_timeout(10000);posts=[]
        def make(resp):
          def api(route):
            r=route.request;u=urlparse(r.url)
            if u.path=='/api/devices' and r.method=='GET':st,d=resp or (200,{'registered':False})
            elif u.path=='/api/devices' and r.method=='POST':posts.append(r.post_data_json['token']);st,d=200,{'registered':True}
            elif u.path=='/api/push-key':st,d=200,{'ready':True,'key':'BPx'+'A'*84}
            else:st,d=200,{}
            route.fulfill(status=st,content_type='application/json',body=json.dumps(d))
          return api
        pg.route('**/api/**',make(resp));pg.goto(BASE+'/assets/theme.js')
        if resp is None:pg.evaluate("window.__has=false")
        r=pg.evaluate("async()=>{const m=await import('/assets/push.js');return await m.heal('t')}")
        log=pg.evaluate("window.__log")
        ok('unsubscribe' not in log,f'{caso}: nunca borra la suscripción del teléfono ({r})')
        if caso=='registrado':ok(r=='ok' and not posts and 'subscribe' not in log,'registrado: no hace nada')
        if caso=='no registrado':ok(r=='renewed' and posts==['https://fcm.googleapis.com/fcm/send/abc123'] and 'subscribe' not in log,'no registrado: registra la misma suscripción, sin pedir otra')
        if caso=='servidor con error':ok(r=='failed' and not posts and 'subscribe' not in log,'si no se pudo preguntar, no toca nada')
        if caso=='sin suscripción':ok(r=='renewed' and log==['subscribe'] and len(posts)==1,'sin suscripción: crea una y la registra')
        ctx.close()
    b.close()
print(n,'comprobaciones')
