"""1.10.0: pantalla de mantenimiento (pausa o servidor caído), y el modo de texto grande."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def make(state):
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/status':
            if state['down']:route.fulfill(status=503,content_type='application/json',body='{"error":"x"}');return
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'maintenance':state['paused']}));return
        if path=='/api/me' and state['paused']:
            route.fulfill(status=503,content_type='application/json',body=json.dumps({'error':'Ágora está en mantenimiento.','code':'maintenance'}));return
        if path=='/api/me' and state['mode']=='gateway':
            route.fulfill(status=502,content_type='text/html',body='<html>Bad gateway</html>');return
        if path=='/api/me' and state['mode']=='abort':route.abort();return
        if path=='/api/me' and state['mode']=='bug':
            route.fulfill(status=503,content_type='application/json',body=json.dumps({'error':'x','code':'internal_error'}));return
        if path=='/api/me':data={'user':{'role':'administrator','email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/security':data={'passkeys':[],'sessions':[],'lockChoices':[5],'deviceRemembered':False}
        elif path=='/api/admin/maintenance':data={'on':state['paused'],'since':None}
        else:data={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    return api
TEXT=("Oops! Algo ocurre.","El servidor puede estar tomando una siesta o dándose un masaje de mantenimiento.","Contacta a Ágora para más información")
with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    def open_page(state):
        c=b.new_context(viewport={'width':390,'height':844},service_workers='block');c.add_init_script("try{localStorage.setItem('agora-tour-done','1')}catch(e){}")
        p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.route('**/api/**',make(state));return p,errors
    # --- pausa del propietario: el equipo ve la pantalla con el texto acordado
    state={'paused':True,'down':False,'mode':'ok'};page,errs=open_page(state)
    page.goto(BASE+'/bandeja');page.wait_for_selector('#maintenance',timeout=8000)
    text=page.locator('#maintenance').inner_text()
    for part in TEXT:assert part in text,(part,text)
    print('PASS when Ágora is paused the team sees the maintenance screen with the agreed text')
    state['paused']=False;page.locator('#maintenance-retry').click();page.wait_for_selector('#app:not([hidden])',timeout=8000)
    assert page.locator('#maintenance').count()==0
    print('PASS and it goes away by itself (or with «Reintentar») once the pause ends')
    # --- servidor caído (el cuerpo de error 502) y red cortada con el servidor sin responder
    for mode in ('gateway','abort'):
        state={'paused':False,'down':True,'mode':mode};page,e2=open_page(state)
        page.goto(BASE+'/bandeja');page.wait_for_selector('#maintenance',timeout=8000)
        print('PASS a server that does not answer ('+mode+') shows the maintenance screen')
    # --- un fallo suelto de la aplicación NO debe mostrar la pantalla si el servidor sí responde
    state={'paused':False,'down':False,'mode':'bug'};page,e3=open_page(state)
    page.goto(BASE+'/bandeja');page.wait_for_timeout(2500)
    assert page.locator('#maintenance').count()==0
    print('PASS a single failure while the server answers is not mistaken for maintenance')
    # --- texto grande: casilla en Mi cuenta, se recuerda y se aplica antes de pintar
    state={'paused':False,'down':False,'mode':'ok'};page,e4=open_page(state)
    page.goto(BASE+'/bandeja');page.wait_for_selector('#app:not([hidden])')
    base=page.evaluate("parseFloat(getComputedStyle(document.querySelector('.nav')).fontSize)")
    page.locator('#profile-top').click();page.wait_for_selector('#text-large')
    assert page.locator('#maintenance-on').count()==1,'the owner sees the maintenance switch'
    page.locator('#maintenance-on').wait_for(state='attached');page.wait_for_function("!document.querySelector('#maintenance-on').disabled")
    page.locator('#text-large').check();page.wait_for_timeout(200)
    assert page.evaluate("document.documentElement.dataset.text")=='large'
    weight=page.evaluate("getComputedStyle(document.querySelector('#text-large + span small')||document.body).fontWeight");assert int(weight)>=700,weight
    page.keyboard.press('Escape');page.wait_for_timeout(200)
    big=page.evaluate("parseFloat(getComputedStyle(document.querySelector('.nav')).fontSize)");assert big>base*1.1,(base,big)
    print('PASS «Texto grande» makes the letters bigger and bold')
    page.reload();page.wait_for_selector('#app:not([hidden])')
    assert page.evaluate("document.documentElement.dataset.text")=='large'
    print('PASS and it is remembered on this device')
    page.locator('#profile-top').click();page.wait_for_selector('#text-large');page.locator('#text-large').uncheck();page.wait_for_timeout(200)
    assert page.evaluate("document.documentElement.dataset.text")is None
    print('PASS unticking it goes back to normal')
    assert not (errs+e2+e3+e4),(errs,e2,e3,e4)
    b.close()
