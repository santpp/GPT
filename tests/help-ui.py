"""1.9.0: tutorial una sola vez por dispositivo (sólo vendedores), el logo como botón de ayuda, el manual y el orden de las ventanas."""
import json,os,time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
CH=[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False},{'type':'SMS','label':'SMS','available':True,'needsSubject':False}]
ITEMS=[{'id':'o1','name':'Curso A','contactName':'Pedro','assignedName':'Ana','days':3,'value':0,'missing':True}]
def make(seller,calls):
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/me':
            caps={'viewConversations':True,'sendMessages':True,'viewOpportunities':True,'editOpportunities':True}
            if seller:caps['assignedDataOnly']=True
            data={'user':{'role':'team' if seller else 'administrator','email':'ana@example.test','identity_type':'external' if seller else 'native','capabilities':caps},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/workspace/won-alert':calls.append(time.time());data={'month':'2026-11','count':1,'items':ITEMS,'oldestDays':3,'days':7,'escalate':False,'canEdit':True,'team':None}
        elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Ruiz','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
        elif path=='/api/workspace/messages':data={'items':[{'id':'m1','body':'Hola','direction':'inbound','dateAdded':'2026-11-02T10:00:00Z'}],'hasMore':False,'channels':CH}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    return api
def ctx(b,seller=True,calls=None,state=None):
    c=b.new_context(viewport={'width':390,'height':844},service_workers='block',storage_state=state)
    p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.route('**/api/**',make(seller,calls if calls is not None else []));return c,p,errors
with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    # --- el vendedor ve el tutorial la primera vez, y antes que la alerta de ventas
    calls=[];c,page,errors=ctx(b,True,calls)
    page.goto(BASE+'/bandeja');page.wait_for_selector('.tour-card h2',timeout=8000)
    assert 'Bienvenida' in page.locator('.tour-card h2').inner_text() and '1 de' in page.locator('.tour-count').inner_text()
    page.wait_for_timeout(5500);assert page.locator('#won-dialog[open]').count()==0,'the won-sales window waits for the tutorial'
    print('PASS a seller sees the tutorial on the first visit, and the won-sales window waits its turn')
    box=page.locator('.tour-card').bounding_box();assert box['x']>=0 and box['x']+box['width']<=390 and box['y']>=0 and box['y']+box['height']<=844,box
    print('PASS the tutorial card fits inside the screen')
    seen=[]
    for _ in range(8):
        seen.append(page.locator('.tour-card h2').inner_text())
        if page.locator('.tour-card button.primary').inner_text()=='Terminar':break
        page.locator('.tour-card button.primary').click();page.wait_for_timeout(450)
    assert 'Conversaciones' in seen and '¿Necesitas ayuda?' in seen[-1],seen
    page.locator('.tour-card button.primary').click()
    page.wait_for_selector('#won-dialog[open]',timeout=8000)
    print('PASS finishing the tutorial hands the screen to the next window (the won-sales alert)')
    assert page.evaluate("localStorage.getItem('agora-tour-done')")
    state=c.storage_state();page.close();c.close()
    # --- ya no vuelve a salir en ese dispositivo
    c,page,errors2=ctx(b,True,[],state);page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]');page.wait_for_timeout(1500)
    assert page.locator('#tour').count()==0
    print('PASS the tutorial does not appear again on that device')
    # --- omitir con Escape también cuenta como visto
    c3,p3,e3=ctx(b,True);p3.goto(BASE+'/bandeja');p3.wait_for_selector('.tour-card h2');p3.keyboard.press('Escape');p3.wait_for_timeout(400)
    assert p3.locator('#tour').count()==0 and p3.evaluate("localStorage.getItem('agora-tour-done')")
    print('PASS skipping it with Escape counts as seen')
    # --- el logo es el botón de ayuda
    page.wait_for_selector('#won-dialog[open]',timeout=8000);page.locator('#won-later').click();page.wait_for_timeout(300)
    page.locator('.brand-help').click();assert page.locator('.help-menu').is_visible()
    items=[t.strip() for t in page.locator('.help-menu [role=menuitem]').all_inner_texts()];assert items==['Ver el manual','Repetir el tutorial','Descargar el PDF'],items
    assert page.locator('.help-menu a').get_attribute('href').endswith('manual-vendedores.pdf')
    page.keyboard.press('Escape');assert page.locator('.help-menu').count()==0
    print('PASS the logo opens a menu with the manual, the tutorial and the PDF')
    page.locator('.brand-help').click();page.locator('[data-act="tour"]').click();page.wait_for_selector('.tour-card h2',timeout=4000)
    page.keyboard.press('Escape')
    page.locator('.brand-help').click();page.locator('[data-act="manual"]').click();page.wait_for_selector('.manual h2')
    text=page.locator('.manual').inner_text()
    assert page.url.endswith('/ayuda') and 'botón de ayuda' in text and 'Ver el manual' in text
    for bad in ('GoHighLevel','LeadConnector','GHL','/conversations','scope'):assert bad.lower() not in text.lower(),bad
    imgs=page.locator('.manual img');n=imgs.count();assert n>=8
    page.wait_for_timeout(800)
    assert all(imgs.nth(i).evaluate('i=>i.complete&&i.naturalWidth>0') for i in range(n)),'every screenshot loads'
    assert page.locator('.manual-actions a').get_attribute('href').endswith('manual-vendedores.pdf')
    print('PASS the manual is a page of the app, explains the logo, mentions no provider, and its screenshots load')
    r=page.request.get(BASE+page.locator('.manual-actions a').get_attribute('href'));assert r.status==200 and r.body()[:4]==b'%PDF'
    print('PASS the PDF is served')
    # --- quien administra no ve el tutorial solo
    ca,pa,ea=ctx(b,False,[]);pa.goto(BASE+'/bandeja');pa.wait_for_selector('[data-live-id]');pa.wait_for_timeout(1500)
    assert pa.locator('#tour').count()==0
    print('PASS administrators do not get the tutorial automatically')
    # --- el canal elegido conserva su color
    pa.locator('[data-live-id="c0"]').evaluate('n=>n.click()');pa.wait_for_selector('.channel-current')
    colour=pa.locator('.channel-current').evaluate('e=>getComputedStyle(e).backgroundColor');assert colour!='rgb(255, 255, 255)',colour
    assert pa.locator('.channel-current').get_attribute('data-ch')=='WhatsApp'
    print('PASS the chosen channel keeps its colour')
    assert not (errors+errors2+e3+ea),(errors,errors2,e3,ea)
    b.close()
