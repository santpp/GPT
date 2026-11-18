"""1.10.21 · Contratos: botón en «Más» (y barra lateral) solo en la subcuenta que lo tiene; lo ven todos (propietaria y vendedores);
la página se abre dentro de Ágora con ?embed=1, y se activa por subcuenta en Conexiones."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
URL='https://contratos.example.test/'
def run(p,role,width):
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':width,'height':844},service_workers='block').new_page()
    errors=[];puts=[];page.on('pageerror',lambda e:errors.append(str(e)))
    conns=[{'id':1,'name':'Con contratos','contracts_url':URL},{'id':2,'name':'Sin contratos','contracts_url':None}]
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':data={'user':{'role':role,'email':'a@example.test','identity_type':'native' if role=='administrator' else 'external','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':conns,'reason':None}
        elif path=='/api/admin/diagnostics':data={'ready':True,'version':'x','checks':[]}
        elif path=='/api/admin/integrations':data={'items':[{'id':2,'name':'Sin contratos','location_id':'l2','currency':'USD','contracts_url':None,'status':'connected','externalUsers':0,'tokenConfigured':True}],'syncWindows':[20,50,100],'accessChannel':{'connectionId':None,'ready':False}}
        elif path=='/api/admin/integrations/2/contracts':
            puts.append(req.post_data_json);u=req.post_data_json.get('url');data={'url':('https://'+u) if u else None}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.route('https://contratos.example.test/**',lambda r:r.fulfill(status=200,content_type='text/html',body='<html><body><h1>Contratos</h1></body></html>'))
    page.add_init_script("localStorage.setItem('agora-connection','1')")
    page.goto(BASE+'/tablero');page.wait_for_selector('#app:not([hidden])');page.wait_for_timeout(800)
    return b,page,errors,puts
with sync_playwright() as p:
    for role in ['administrator','team']:
        b,page,errors,puts=run(p,role,390)
        assert page.locator('#nav-more').is_visible(),role
        assert page.locator('.sidebar nav .nav[data-view="contracts"]').is_hidden(),'en el teléfono, Contratos no va en la barra de abajo'
        page.locator('#nav-more').click();page.wait_for_selector('#more-sheet:not([hidden])')
        items=page.locator('#more-sheet [data-more]').all_inner_texts()
        assert 'Contratos' in items,(role,items)
        page.locator('#more-sheet [data-more="contracts"]').click();page.wait_for_selector('.contracts-frame')
        src=page.locator('.contracts-frame').get_attribute('src')
        assert src.startswith(URL) and 'embed=1' in src,src
        assert page.url.endswith('/contratos')
        page.wait_for_selector('.contracts-full.loaded')
        box=page.locator('.contracts-frame').bounding_box()
        assert box['width']>=389 and box['height']>=800,box
        assert page.locator('.sidebar').is_hidden() or not page.locator('.sidebar').is_visible()
        assert page.locator('text=Abrir en pantalla completa').count()==0
        print(f'PASS {role}: «Más» → «Contratos» abre la página a pantalla completa (?embed=1), sin la barra de Ágora')
        page.locator('.contracts-back').click();page.wait_for_timeout(500)
        assert page.url.endswith('/tablero') and page.locator('.sidebar').is_visible(),page.url
        print(f'PASS {role}: el botón flotante devuelve a donde estaba, con la app completa')
        # Otra subcuenta sin contratos: el botón desaparece; a un vendedor sin nada más, «Más» también.
        page.select_option('#account','2');page.wait_for_timeout(800)
        assert not page.url.endswith('/contratos')
        if role=='team':assert page.locator('#nav-more').is_hidden()
        else:
            page.locator('#nav-more').click();page.wait_for_selector('#more-sheet:not([hidden])')
            assert 'Contratos' not in page.locator('#more-sheet [data-more]').all_inner_texts();page.keyboard.press('Escape')
        print(f'PASS {role}: en una subcuenta sin contratos no aparece el botón')
        assert not errors,errors;b.close()
    # Escritorio: en la barra lateral. Conexiones: se activa por subcuenta.
    b,page,errors,puts=run(p,'administrator',1280)
    assert page.locator('.sidebar nav .nav[data-view="contracts"]').is_visible()
    page.goto(BASE+'/conexiones');page.wait_for_selector('[data-contracts="2"]');page.locator('[data-contracts="2"]').click()
    page.wait_for_selector('#contracts-form');page.check('#contracts-form [name=on]');page.locator('#contracts-form button.primary').click()
    page.wait_for_function("document.querySelector('#contracts-result').textContent.includes('Activado')")
    assert puts[-1]=={'url':'contratos.agoradigital.app'},puts
    print('PASS escritorio: «Contratos» en la barra lateral; en Conexiones se activa por subcuenta con su dirección')
    assert not errors,errors;b.close()
print('PASS contracts')
