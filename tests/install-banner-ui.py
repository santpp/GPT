"""Insistir en instalar la app: en un teléfono sin instalarla, una franja en cada visita; «Ahora no» sólo la calla en esta visita."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def api(route):
    path=route.request.url.split('/api/')[1].split('?')[0]
    if path=='me':data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True}},'csrf':'t','passkeys':0}
    elif path=='workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
INIT="localStorage.setItem('agora-tour-done','1');localStorage.setItem('agora-welcome-later',String(Date.now()))"
STANDALONE="const m=window.matchMedia.bind(window);window.matchMedia=q=>q.includes('display-mode: standalone')?{matches:true,media:q,addEventListener(){},removeEventListener(){},addListener(){},removeListener(){}}:m(q)"
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for name in ('iPhone 13','Pixel 7'):
        ctx=b.new_context(**p.devices[name],service_workers='block');ctx.add_init_script(INIT)
        pg=ctx.new_page();errors=[];pg.on('pageerror',lambda e:errors.append(str(e)));pg.route('**/api/**',api)
        pg.goto(BASE+'/tablero');pg.wait_for_selector('#push-banner.install-banner:not([hidden])')
        assert 'no está instalada en este teléfono' in pg.locator('#push-banner').inner_text()
        pg.locator('#install-go').click();pg.wait_for_selector('#install-steps:not([hidden])')
        steps=pg.locator('#install-steps').inner_text()
        assert ('Añadir a pantalla de inicio' in steps) and (('Compartir' in steps) if name.startswith('iPhone') else ('Instalar aplicación' in steps)),steps
        print(f'PASS {name}: a phone without the app sees the install strip, with the steps for its system')
        pg.locator('#install-later').click();assert pg.locator('#push-banner').is_hidden()
        pg.reload();pg.wait_for_selector('#app:not([hidden])');pg.wait_for_timeout(800)
        assert not pg.locator('#push-banner.install-banner').is_visible(),'«Ahora no» keeps it quiet during this visit'
        again=ctx.new_page();again.route('**/api/**',api);again.goto(BASE+'/tablero');again.wait_for_selector('#push-banner.install-banner:not([hidden])')
        print(f'PASS {name}: «Ahora no» lasts only this visit; the next one asks again')
        assert not errors,errors;ctx.close()
    # Instalada, o en el ordenador: no se dice nada.
    ctx=b.new_context(**p.devices['iPhone 13'],service_workers='block');ctx.add_init_script(INIT);ctx.add_init_script(STANDALONE)
    pg=ctx.new_page();pg.route('**/api/**',api);pg.goto(BASE+'/tablero');pg.wait_for_selector('#app:not([hidden])');pg.wait_for_timeout(1200)
    assert not pg.locator('#push-banner.install-banner').is_visible(),'opened from the installed app: no strip';ctx.close()
    ctx=b.new_context(viewport={'width':1280,'height':800},service_workers='block');ctx.add_init_script(INIT)
    pg=ctx.new_page();pg.route('**/api/**',api);pg.goto(BASE+'/tablero');pg.wait_for_selector('#app:not([hidden])');pg.wait_for_timeout(1200)
    assert not pg.locator('#push-banner.install-banner').is_visible(),'on a computer: no strip';ctx.close()
    print('PASS once installed, or on a computer, nothing is said')
    b.close()
