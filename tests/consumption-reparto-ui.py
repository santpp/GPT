"""1.10.33 · Consumos: «¿De qué subcuenta es el gasto sin identificar?». Muestra las pistas, «Asignar» guarda la regla y vuelve a pedir el informe,
«Quitar» la borra; sin referencias técnicas ni desborde en el teléfono (390 px)."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
CONNS=[{'id':1,'name':'Clínica Sol','location_id':'locA','status':'connected'},{'id':2,'name':'Academia Luna','location_id':'locB','status':'connected'}]
def report(reglas):
    return {'month':'2026-11','pages':1,'partial':False,'readAt':'2026-11-20T12:00:00Z','subcuentas':2,'totales':{'gasto':3.47,'recargas':50},'porRegla':20 if reglas else 0,'reglas':reglas,
     'locations':[{'locationId':'locA','locationName':'Clínica Sol','gasto':1.25,'recargas':0,'movimientos':1,'sinClasificar':0,'categorias':[{'tipo':'SMS','monto':1.25}],'dias':[],'ultimos':[],'plan':{'plan':None,'estado':None,'errores':[]}},
      {'locationId':'agencia','tipo':'agencia','locationName':'Agencia · sin subcuenta identificada','gasto':2.22,'recargas':50,'movimientos':31,'sinClasificar':0,'categorias':[{'tipo':'WhatsApp · marketing','monto':2.22}],'dias':[],
       'ultimos':[{'at':'2026-11-12T10:00:00Z','tipo':'WhatsApp · marketing','descripcion':'','direccion':'gasto','monto':0.074}]}],
     'pistas':{'movimientos':30,'monto':2.22,'unicos':['ref'],'campos':[{'campo':'meta.phoneNumber','movimientos':30,'distintos':2,'valores':[{'valor':'+584140000001','texto':'+584140000001','movimientos':20,'monto':1.48},{'valor':'+584240000002','texto':'+584240000002','movimientos':10,'monto':0.74}]}]}}
def make(calls,state):
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':d={'user':{'role':'administrator','email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':d={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
        elif path=='/api/admin/consumption':d={'companyId':'COMP1','tokenSaved':True,'tokenHint':'AB12','connections':CONNS,'limits':{}}
        elif path=='/api/admin/consumption/report':calls.append(('report',None));d=report(state['reglas'])
        elif path=='/api/admin/consumption/reglas':
            b=req.post_data_json;calls.append((req.method,b))
            if req.method=='POST':state['reglas']=[{'campo':b['campo'],'valor':b['valor'],'locationId':b['locationId']}]
            else:state['reglas']=[]
            d={'reglas':state['reglas']}
        else:d={'items':[],'limit':20,'total':0}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
    return api
n=0
def ok(c,m):
    global n
    assert c,m
    n+=1;print('PASS',m)
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for vp in ({'width':1100,'height':900},{'width':390,'height':844}):
        ctx=b.new_context(viewport=vp,service_workers='block');ctx.add_init_script("try{localStorage.setItem('agora-tour-done','1')}catch(e){}")
        pg=ctx.new_page();errs=[];calls=[];st={'reglas':[]};pg.on('pageerror',lambda e:errs.append(str(e)));pg.route('**/api/**',make(calls,st))
        pg.goto(BASE+'/consumos');pg.wait_for_selector('.cons');pg.locator('#cons-run').click();pg.wait_for_selector('.cons-atrib')
        t=pg.locator('.cons-atrib').inner_text();quien='ordenador' if vp['width']>600 else 'teléfono'
        ok('¿De qué subcuenta es el gasto sin identificar?' in t and '+584140000001' in t and 'Número' in t and 'wamid' not in pg.locator('#cons-out').inner_text(),f'{quien}: el bloque muestra los números para asignar, sin referencias técnicas')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{quien}: sin desborde')
        fila=pg.locator('.cons-atrib tr',has_text='+584140000001');fila.locator('[data-reg-add]').click()
        ok('Elige la subcuenta' in pg.locator('[data-reg-msg]').inner_text(),f'{quien}: sin elegir subcuenta, avisa')
        fila.locator('select').select_option('locA');fila.locator('[data-reg-add]').click();pg.wait_for_selector('.cons-reglas')
        ok(('POST',{'campo':'meta.phoneNumber','valor':'+584140000001','locationId':'locA'}) in calls and 'Clínica Sol' in pg.locator('.cons-reglas').inner_text(),f'{quien}: «Asignar» guarda la regla y el informe se recalcula')
        ok('20 cargos se sumaron' in pg.locator('.cons-atrib').inner_text(),f'{quien}: dice cuántos cargos entraron por la regla')
        pg.locator('.cons-reglas [data-reg-del]').click();pg.wait_for_timeout(800)
        ok(pg.locator('.cons-reglas').count()==0 and any(c[0]=='DELETE' for c in calls),f'{quien}: «Quitar» borra la regla')
        ok(not errs,f'{quien}: sin errores {errs}');ctx.close()
    b.close()
print(n,'comprobaciones')
