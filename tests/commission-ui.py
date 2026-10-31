"""Comisión por subcuenta: sólo desde Conexiones, porcentaje o monto fijo por cierre, desde este mes."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':900},service_workers='block').new_page()
    errors=[];puts=[];page.on('pageerror',lambda e:errors.append(str(e)))
    state={'current':{'kind':'percent','amount':10.0,'since':None},'history':[]}
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':1}
        elif path=='/api/admin/diagnostics':data={'ready':True,'version':'1.1.0','checks':[]}
        elif path=='/api/admin/integrations':data={'items':[{'id':1,'name':'Otra subcuenta','location_id':'l','status':'connected','sync_interval_seconds':120}],'accessChannel':{'connectionId':None,'ready':False}}
        elif path=='/api/admin/integrations/1/commission':
            if req.method=='PUT':
                assert req.headers['x-csrf-token']=='t';puts.append(req.post_data_json)
                state['current']={'kind':req.post_data_json['kind'],'amount':float(req.post_data_json['amount']),'since':'2026-10'};state['history']=[dict(state['current'])]
            data=dict(state)
        elif path=='/api/admin/announcements':data={'items':[]}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Otra subcuenta'}]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/conexiones');page.wait_for_selector('[data-commission]')
    page.locator('[data-commission]').click();page.wait_for_selector('#commission-form')
    text=page.locator('#dialog-content').inner_text()
    assert 'Sólo tú puedes cambiarla' in text and 'los meses anteriores conservan' in text and '10 % de la venta' in text
    print('PASS the administrator sees the current commission and that a change only applies from this month')
    page.select_option('#commission-form [name=kind]','per_closing')
    assert page.locator('#commission-unit').inner_text()=='Monto por cierre'
    page.fill('#commission-form [name=amount]','5');page.locator('#commission-form button.primary').click()
    page.wait_for_function("document.querySelector('#commission-result').textContent.includes('Guardado')")
    assert puts==[{'kind':'per_closing','amount':'5'}]
    assert '5,00 por cada cierre' in page.locator('#dialog-content').inner_text() and 'Cambios anteriores' in page.locator('#dialog-content').inner_text()
    print('PASS a fixed amount per closing is saved, and the history keeps the change')
    assert not errors,errors
    print('PASS no JS errors')
    b.close()
