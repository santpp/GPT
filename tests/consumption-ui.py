"""1.10.2: la pestaña Consumos (sólo propietaria): conexión de agencia sin mostrar el token, informe por subcuenta y botones de prueba POST/GET."""
import json,os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
CONNS=[{'id':1,'name':'Clínica Sol','location_id':'locA','status':'connected'},{'id':2,'name':'Academia Luna','location_id':'locB','status':'connected'}]
REPORT={'month':'2026-11','method':'POST','pages':1,'partial':False,'readAt':'2026-11-07T12:00:00Z','subcuentas':2,'totales':{'gasto':16.5,'recargas':50},'locations':[
 {'locationId':'locA','locationName':'Clínica Sol','gasto':12.5,'recargas':50,'movimientos':9,'sinClasificar':0,'categorias':[{'tipo':'sms','monto':10},{'tipo':'whatsapp','monto':2.5}],'dias':[{'dia':'2026-11-02','monto':10},{'dia':'2026-11-03','monto':2.5}],'ultimos':[{'at':'2026-11-03T10:00:00Z','tipo':'whatsapp','descripcion':'','direccion':'gasto','monto':2.5}],'plan':{'plan':'Plan Pro','estado':'active','precio':{'monto':97,'moneda':'USD','cada':'month'},'errores':[]}},
 {'locationId':'agencia','tipo':'agencia','locationName':'Agencia · movimientos propios','gasto':1,'recargas':0,'movimientos':1,'sinClasificar':0,'categorias':[{'tipo':'domainpurchase','monto':1}],'dias':[],'ultimos':[{'at':'2026-11-02T10:00:00Z','tipo':'domainPurchase','descripcion':'','direccion':'gasto','monto':1}],'plan':None},
 {'locationId':'locB','locationName':'Academia Luna','gasto':4,'recargas':0,'movimientos':1,'sinClasificar':0,'categorias':[],'dias':[],'ultimos':[],'plan':{'plan':None,'estado':None,'errores':['suscripción: HTTP 404']}}]}
def make(owner,calls,state):
    def api(route):
        req=route.request;u=urlparse(req.url);path=u.path;q=parse_qs(u.query)
        if path=='/api/me':d={'user':{'role':'administrator' if owner else 'team','email':'a@example.test','identity_type':'native' if owner else 'external','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':d={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/admin/consumption':d={'companyId':state['company'],'tokenSaved':state['saved'],'tokenHint':'AB12' if state['saved'] else '','connections':CONNS,'limits':{}}
        elif path=='/api/admin/consumption/settings':
            b=req.post_data_json;calls.append(('settings',b));state['company']=b.get('companyId',state['company']);state['saved']=state['saved'] or bool(b.get('token'));d={'companyId':state['company'],'tokenSaved':state['saved'],'tokenHint':'AB12'}
        elif path=='/api/admin/consumption/report':calls.append(('report',{k:v[0] for k,v in q.items()}));d=REPORT
        elif path=='/api/admin/consumption/test':
            b=req.post_data_json;calls.append(('test',b))
            d={'request':{'method':b['method'],'url':'https://x/saas/companies/{companyId}/wallet-transactions','version':'v3'},'status':404 if b['method']=='GET' else 200,'ms':120,'headers':{'x-ratelimit-remaining':'99'},'error':None,'analysis':{'topLevelKeys':['transactions'],'rows':2,'firstRowKeys':['amount','locationId'],'classified':{'gasto':1,'recarga':1,'unknown':0},'withLocation':2,'withAmount':2,'firstNormalized':None} if b['method']=='POST' else None,'body':'{\n "message": "Cannot GET"\n}' if b['method']=='GET' else '{\n "transactions": []\n}'}
        else:d={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
    return api
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    def page(owner,state):
        ctx=b.new_context(viewport={'width':1100,'height':900},service_workers='block');ctx.add_init_script("try{localStorage.setItem('agora-tour-done','1')}catch(e){}")
        pg=ctx.new_page();errs=[];calls=[];pg.on('pageerror',lambda e:errs.append(str(e)));pg.route('**/api/**',make(owner,calls,state));return pg,calls,errs
    st={'company':'','saved':False}
    pg,calls,errs=page(True,st)
    pg.goto(BASE+'/consumos');pg.wait_for_selector('.cons')
    assert pg.locator('button[data-view="consumption"]').first.is_visible()
    assert pg.locator('input[name=token]').input_value()=='' and pg.locator('input[name=token]').get_attribute('type')=='password'
    print('PASS the tab opens for the owner, with the token field empty and hidden')
    pg.locator('input[name=companyId]').fill('COMP123456');pg.locator('input[name=token]').fill('pit-abc-1234567890-AB12');pg.locator('#cons-form .primary').click();pg.wait_for_function("document.querySelector('#cons-conn').innerText.includes('Conectada')")
    assert pg.locator('input[name=token]').input_value()=='' and 'AB12' in pg.locator('input[name=token]').get_attribute('placeholder') and 'pit-abc' not in pg.content()
    assert ('settings',{'companyId':'COMP123456','token':'pit-abc-1234567890-AB12'}) in calls
    print('PASS saving shows only the last four characters, never the token')
    assert pg.locator('[data-dbg]').count()==0 and pg.locator('#cons-method').count()==0
    print('PASS the debugging buttons and the method selector are gone')
    pg.locator('#cons-sub').select_option('locA');pg.locator('#cons-run').click();pg.wait_for_selector('.cons-card')
    r=[c for c in calls if c[0]=='report'][-1][1];assert r['locationId']=='locA' and r['month'] and 'method' not in r
    txt=pg.locator('#cons-out').inner_text();assert 'Plan Pro (active)' in txt and '16,50' in txt and 'Clínica Sol' in txt and 'Academia Luna' in txt and 'Sin plan de SaaS' in txt and 'Cuenta de la agencia' in txt and 'Agencia · movimientos propios' in txt
    print('PASS the report lists each sub-account with its plan and spend; the selector travels to the server')
    pg.locator('.cons-open').first.click();pg.wait_for_selector('.cons-sheet')
    sheet=pg.locator('.cons-sheet').inner_text();assert 'Reporte de consumos' in sheet and 'Clínica Sol' in sheet and 'sms' in sheet and 'whatsapp' in sheet and 'Plan Pro' in sheet and pg.locator('.cons-split').count()==1 and pg.locator('.cons-days span').count()==2
    print('PASS each sub-account opens a report sheet with its plan, breakdown, daily chart and movements')
    with pg.expect_download() as d:pg.locator('#cons-view [data-txt]').click()
    dl=d.value;assert dl.suggested_filename=='Consumos-'+REPORT['month']+'-Clinica-Sol.txt' or dl.suggested_filename.startswith('Consumos-') and dl.suggested_filename.endswith('Clinica-Sol.txt'),dl.suggested_filename
    body=open(dl.path(),encoding='utf-8-sig').read();assert 'REPORTE DE CONSUMOS' in body and 'Clínica Sol' in body and 'Plan Pro (active)' in body and 'whatsapp' in body and 'pit-abc' not in body
    print('PASS it downloads as a .txt with the whole report')
    pg.evaluate("window.print=()=>{window.__printed=document.body.classList.contains('cons-printing')&&document.querySelector('#cons-print .cons-sheet')!==null}");pg.locator('#cons-view [data-pdf]').click();pg.wait_for_function('window.__printed===true')
    print('PASS and "Guardar como PDF" prepares the sheet for printing')
    pg.locator('[data-back]').click();assert pg.locator('#cons-main').is_visible()
    pg.locator('#cons-sub').select_option('');pg.locator('#cons-run').click();pg.wait_for_selector('.cons-card')
    with pg.expect_download() as d2:pg.locator('#cons-out [data-txt]').click()
    assert d2.value.suggested_filename=='Consumos-2026-11.txt' or d2.value.suggested_filename.startswith('Consumos-')
    print('PASS everything can be exported too')
    assert not errs,errs
    pg2,calls2,e2=page(False,{'company':'','saved':False});pg2.goto(BASE+'/bandeja');pg2.wait_for_selector('#app:not([hidden])');pg2.wait_for_timeout(500)
    assert pg2.locator('button[data-view="consumption"]').first.is_hidden()
    print('PASS the tab is not offered to anyone who is not the owner')
    b.close()
