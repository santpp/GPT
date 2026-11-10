"""«Entrar como»: la propietaria elige un usuario, ve la franja de aviso y vuelve a su vista."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
state={'viewing':False};sent=[]
OWNER={'role':'administrator','email':'owner@example.test','identity_type':'native','capabilities':{}}
ANA={'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True,'assignedDataOnly':True}}
def api(route):
    req=route.request;path=req.url.split('/api/')[1].split('?')[0]
    if req.method!='GET':sent.append((req.method,path,req.post_data))
    if path=='me':
        data={'user':ANA if state['viewing'] else OWNER,'csrf':'t','passkeys':0,'canViewAs':True,
              'viewAs':{'name':'Ana <b>Ventas</b>','email':'ana@example.test','connectionId':1,'connection':'ASOCIM'} if state['viewing'] else None}
    elif path=='security':data={'passkeys':[],'sessions':[],'team':None,'lockMinutes':None,'lockChoices':[5,15,30,60],'deviceRemembered':True}
    elif path=='admin/view-as' and req.method=='GET':
        data={'connections':[{'id':1,'name':'ASOCIM','users':[{'id':7,'name':'Ana <b>Ventas</b>','email':'ana@example.test','admin':False},{'id':8,'name':'Jefa','email':'jefa@example.test','admin':True}]}]}
    elif path=='admin/view-as':state['viewing']=True;data={'viewAs':{'name':'Ana'}}
    elif path=='view-as/stop':state['viewing']=False;data={'stopped':True}
    elif path=='workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.route('**/api/**',api);page.goto(BASE+'/');page.wait_for_selector('#app:not([hidden])')
    assert page.locator('#view-as-bar').count()==0
    page.locator('#profile-top').click();page.wait_for_selector('#view-as-open')
    page.locator('#view-as-open').click();page.wait_for_selector('[data-view-as="7"]')
    assert 'administra la subcuenta' in page.locator('.view-as-list').inner_text() and 've sólo lo asignado' in page.locator('.view-as-list').inner_text()
    assert page.locator('.view-as-list b').first.inner_text()=='Ana <b>Ventas</b>','names are shown as text, never as markup'
    print('PASS the owner sees the users of each sub-account, and what each can see')
    page.locator('[data-view-as="7"]').click();page.wait_for_selector('#view-as-bar')
    assert any(m=='POST' and pth=='admin/view-as' and json.loads(body)=={'userId':7} for m,pth,body in sent)
    bar=page.locator('#view-as-bar').inner_text()
    assert 'Viendo como' in bar and 'ASOCIM' in bar and 'sólo lectura' in bar
    over=page.evaluate('document.documentElement.scrollWidth-document.documentElement.clientWidth')
    assert over<=0,over
    assert page.locator('#nav-more').is_hidden(),'a seller has no «Más»: only their tabs'
    assert not any(pth=='devices' for _,pth,_ in sent),'viewing as someone registers no device for notifications'
    print('PASS after choosing, the app reloads as that user with a read-only notice that fits the phone')
    page.locator('#view-as-stop').click();page.wait_for_selector('#app:not([hidden])')
    page.wait_for_function("!document.getElementById('view-as-bar')")
    assert any(pth=='view-as/stop' for _,pth,_ in sent)
    print('PASS «Volver a mi vista» returns to the owner')
    assert not errors,errors
    b.close()
