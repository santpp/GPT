"""Novedades: las escribe el administrador en Conexiones y cada persona las ve una vez al entrar."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
ITEM={'id':7,'title':'Ágora 1.1','body':"Incluye:\n- Comisión por subcuenta\n- Avisos que abren su conversación\n\nGracias por usarla.",'linkLabel':'Ver el tablero','linkUrl':'/tablero','at':'2026-10-31T10:00:00+00:00'}
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    # 1) Una persona del equipo entra: ve la ventana una vez.
    ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block');page=ctx.new_page()
    errors=[];reads=[];shown=[True];page.on('pageerror',lambda e:errors.append(str(e)))
    def team(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True}},'csrf':'t','passkeys':1}
        elif path=='/api/announcements':data={'items':[ITEM] if shown[0] else []}
        elif path=='/api/announcements/7/read':reads.append(req.headers.get('x-csrf-token'));shown[0]=False;data={'read':True}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
        elif path=='/api/workspace/inbox':data={'limit':20,'items':[],'total':0}
        elif path=='/api/workspace/dashboard':data={'restricted':True,'conversations':{'total':0,'unread':0},'contacts':None,'leads':None,'opportunities':None,'readAt':'2026-10-31T10:00:00+00:00'}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',team)
    try:page.evaluate("localStorage.setItem('agora-welcome-later',String(Date.now()))")
    except Exception:pass
    page.goto(BASE+'/');page.wait_for_selector('.announce-title',timeout=15000)
    text=page.locator('#dialog-content').inner_text()
    assert 'Ágora 1.1' in text and 'Comisión por subcuenta' in text and page.locator('.announce-body ul li').count()==2 and 'Gracias por usarla.' in text
    print('PASS on entering, a novelty appears with its title, its list and its paragraph')
    page.locator('[data-announce-link]').click()
    page.wait_for_function("!document.querySelector('#action-dialog').open")
    assert reads==['t'] and page.locator('#breadcrumb').inner_text()=='Tablero'
    print('PASS its button goes to that screen, and closing it marks it as read')
    page.reload();page.wait_for_timeout(2500)
    assert page.locator('.announce-title').count()==0
    print('PASS the next time, it does not appear again')
    ctx.close()
    # 2) El administrador la escribe en Conexiones.
    ctx=b.new_context(viewport={'width':390,'height':900},service_workers='block');page=ctx.new_page()
    posted=[];listed=[]
    def admin(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':1}
        elif path=='/api/admin/diagnostics':data={'ready':True,'version':'1.1.0','checks':[]}
        elif path=='/api/admin/integrations':data={'items':[{'id':1,'name':'ASOCIM','location_id':'l','status':'connected','sync_interval_seconds':120}],'accessChannel':{'connectionId':None,'ready':False}}
        elif path=='/api/admin/announcements' and req.method=='POST':posted.append(req.post_data_json);listed.append({'id':9,'title':req.post_data_json['title'],'body':'x','active':True,'seen':0,'at':'2026-10-31T10:00:00+00:00','expiresAt':None});data={'id':9}
        elif path=='/api/admin/announcements':data={'items':listed}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',admin)
    page.goto(BASE+'/conexiones');page.wait_for_selector('#announce-form',timeout=15000)
    page.fill('#announce-form [name=title]','Nueva versión');page.fill('#announce-form [name=body]','Incluye:\n- Algo nuevo')
    page.locator('#announce-preview').click();page.wait_for_selector('.announce-title')
    assert 'VISTA PREVIA' in page.locator('#dialog-content').inner_text() and page.locator('.announce-body li').count()==1
    page.locator('#announce-ok').click()
    print('PASS the administrator can preview how it will look before publishing')
    page.fill('#announce-form [name=linkLabel]','Ver');page.fill('#announce-form [name=linkUrl]','/tablero')
    page.locator('#announce-form button.primary').click()
    page.wait_for_selector('[data-announce-remove]')
    assert posted and posted[0]['title']=='Nueva versión' and posted[0]['linkUrl']=='/tablero'
    assert 'la han visto 0 personas' in page.locator('#announce-panel').inner_text()
    print('PASS publishing it adds it to the list, with how many people have seen it')
    assert not errors,errors
    print('PASS no JS errors')
    b.close()
