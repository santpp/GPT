"""Ventas ganadas: la alerta a los 4 segundos de entrar (y sólo al editar), su escalada, y la pestaña Ganadas."""
import json,os,time
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def make(alert_state,list_state,calls,posts):
    def api(route):
        req=route.request;u=urlparse(req.url);q=parse_qs(u.query);path=u.path
        if path=='/api/me':data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True,'viewOpportunities':True,'editOpportunities':True}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/workspace/won-alert':calls.append(time.time());data=alert_state()
        elif path=='/api/workspace/won':data=list_state()
        elif path.startswith('/api/workspace/opportunity/'):posts.append(req.post_data_json);data={'state':'accepted','id':q['opportunityId'][0]}
        elif path=='/api/workspace/pipelines':data={'pipelines':[],'assignees':[],'canEdit':True}
        else:data={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    return api
ITEMS=[{'id':'o1','name':'Curso A','contactName':'Pedro','assignedName':'Ana','days':3,'value':0,'missing':True},{'id':'o2','name':'Curso B','contactName':'María','assignedName':'Ana','days':1,'value':0,'missing':True}]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    # --- ventana normal
    items=list(ITEMS);calls=[];posts=[]
    def alert():return {'month':'2026-11','count':len(items),'items':items,'oldestDays':3,'days':7,'escalate':False,'canEdit':True,'team':None}
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.route('**/api/**',make(alert,lambda:{},calls,posts))
    t0=time.time();page.goto(BASE+'/bandeja');page.wait_for_selector('#app:not([hidden])')
    page.wait_for_timeout(2500);assert not calls,'nothing is asked in the first seconds'
    page.wait_for_selector('#won-dialog[open]',timeout=8000)
    assert 3.5<=calls[0]-t0<=8,calls[0]-t0
    assert page.locator('#won-later').count()==1 and page.locator('.won-item').count()==2
    print('PASS the alert is asked for about 4 seconds after entering, and opens a window with the sales to complete')
    page.locator('#won-later').click();assert not page.locator('#won-dialog').evaluate('d=>d.open')
    assert page.locator('#won-banner').is_visible() and '2 ventas' in page.locator('#won-banner').inner_text()
    assert page.locator('button[data-view="board"]').first.get_attribute('data-count')=='2'
    print('PASS closing it leaves a banner and a counter on the Opportunities tab')
    n=len(calls);page.wait_for_timeout(2500);assert len(calls)==n,'no polling'
    page.locator('#won-banner-go').click();page.wait_for_selector('#won-dialog[open]')
    items.pop(0)
    page.locator('.won-item input').first.fill('250');page.locator('.won-item .primary').first.click();page.wait_for_timeout(600)
    assert posts and posts[-1]['monetaryValue']==250 and len(posts[-1]['requestId'])>=16
    page.wait_for_timeout(2200)
    assert len(calls)==n+1 and '1 venta' in page.locator('#won-banner').inner_text(),(len(calls),n)
    print('PASS saving an amount writes it, and the alert is asked again once because an opportunity was edited')
    items.clear();page.locator('#won-dialog [data-won-form]').count()
    # --- escalada
    items2=[dict(ITEMS[0],days=9)];calls2=[];posts2=[]
    def alert2():return {'month':'2026-11','count':len(items2),'items':items2,'oldestDays':9 if items2 else 0,'days':7,'escalate':bool(items2),'canEdit':True,'team':None}
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',make(alert2,lambda:{},calls2,posts2))
    page.goto(BASE+'/bandeja');page.wait_for_selector('#won-dialog[open]',timeout=9000)
    assert page.locator('#won-later').count()==0 and 'Llevas 9 días' in page.locator('#won-dialog').inner_text() and page.locator('.won-banner.urgent').count()==1
    page.keyboard.press('Escape');page.keyboard.press('Escape');page.wait_for_timeout(300)
    assert page.locator('#won-dialog').evaluate('d=>d.open'),'an urgent alert cannot be closed with Escape'
    items2.clear();page.locator('.won-item input').fill('90');page.locator('.won-item .primary').click()
    page.wait_for_function("document.querySelector('#won-dialog').textContent.includes('Listo')",timeout=5000)
    page.wait_for_function("!document.querySelector('#won-dialog').open",timeout=5000)
    print('PASS after the set days the window cannot be closed until every sale has its amount, then it thanks and closes')
    # --- pestaña Ganadas
    def won_list():return {'period':'2026-11','items':[dict(ITEMS[0],value=0,missing=True,wonAt='2026-11-01T10:00:00Z'),{'id':'o3','name':'Curso C','contactName':'Raúl','assignedName':'Ana','value':1250.5,'missing':False,'wonAt':'2026-11-01T09:00:00Z'}],'totals':{'count':2,'sum':1250.5,'missing':1},'bySeller':[],'canSeeAll':False,'partial':False,'canEdit':True}
    calls3=[];posts3=[]
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',make(lambda:{'month':'2026-11','count':0,'items':[],'oldestDays':0,'days':7,'escalate':False,'canEdit':True,'team':None},won_list,calls3,posts3))
    page.goto(BASE+'/oportunidades');page.wait_for_selector('.opp-tabs')
    assert page.locator('[data-opp-tab="funnels"].on').count()==1
    page.locator('[data-opp-tab="won"]').click();page.wait_for_selector('.won-row')
    text=page.locator('#opp-pane').inner_text()
    assert 'Ventas ganadas' in text and '1.250,50' in text and 'Sin monto' in text and page.locator('.won-row.missing').count()==1
    assert page.locator('#won-month option').first.inner_text().split(' ')[0].isalpha()
    page.locator('.won-inline input').fill('300');page.locator('.won-inline .primary').click();page.wait_for_timeout(500)
    assert posts3 and posts3[-1]['monetaryValue']==300
    print('PASS the Won tab shows the month, the total sold, those without an amount first, and lets the amount be written in place')
    assert not errors,errors
    b.close()
