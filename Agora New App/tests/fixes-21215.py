#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.12.15 · Pendientes medios para el despliegue:
[9]  El registro de actividad cuenta el detalle en palabras: sin JSON ni identificadores.
[10] Abrir una venta cuya oportunidad no existe en la cuenta no deja el Tablero «en espera».
[11] Las descargas de un ejecutivo quedan en Auditoría (sin poder ver la auditoría).
[13] Teléfono: el aviso «venta por completar» no tapa la barra de abajo.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/fixes-21215.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',q],capture_output=True,text=True).stdout.strip()
class C:
    def __init__(s,u,pw='Agora-Pruebas-2026!'):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':u,'clave':pw});s.ws=d.get('activeTenantId')
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(B+'/api/'+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            try:return e.code,json.loads(e.read() or b'{}')
            except Exception:return e.code,{}
# [9]
limpiar();d=C('duena@example.test','Clave-Local-2026!')
st,act=d.call('agency/activity')
crudos=[x.get('detalle') for x in act.get('items',[]) if re.search(r'[{\[]"|locationId|companyId|tokenCambiado|LOC[A-Za-z0-9]{6,}',str(x.get('detalle') or ''))]
ok(st==200 and not crudos,f'[9] el registro de actividad no muestra JSON ni identificadores {crudos[:2]}')
# [10]
limpiar();a=C('admin.asocim@agora.test')
st,_=a.call('workspace/ventas/contexto?oportunidadId=noexiste123')
st2,dash=a.call('workspace/dashboard?connectionId=%s'%a.ws)
ok(st==404 and st2==200 and 'espera' not in json.dumps(dash,ensure_ascii=False),f'[10] una oportunidad que no existe da 404 y el Tablero sigue respondiendo ({st}, {st2})')
# [11]
limpiar();e=C('ejecutivo1.asocim@agora.test')
antes=sql("select count(*) from agora_audit where action like 'cliente:descargo.%'")
st,_=e.call('workspace/auditoria','POST',{'accion':'descargo.excel','detalle':'Ventas · 3 filas'})
st2,_=e.call('workspace/auditoria','POST',{'accion':'otra.cosa','detalle':'x'})
st3,_=e.call('workspace/auditoria')
despues=sql("select count(*) from agora_audit where action like 'cliente:descargo.%'")
ok(st==200 and st2==403 and st3==403 and int(despues)==int(antes)+1,f'[11] la descarga del ejecutivo queda registrada; no puede registrar otras cosas ni ver la auditoría ({st},{st2},{st3})')
# [13]
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    limpiar();pg=br.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    pg.goto(B+'/centro');pg.wait_for_timeout(4000)
    pg.evaluate("""()=>{if(!document.querySelector('.vg-aviso'))document.body.insertAdjacentHTML('beforeend','<div class="vg-aviso"><span class="vg-aviso-ic"></span><span><b>Una venta ganada por completar</b></span></div>')}""")
    pg.wait_for_timeout(300)
    r=pg.evaluate("(()=>{const a=document.querySelector('.vg-aviso').getBoundingClientRect(),t=document.querySelector('.tabbar');if(!t||!t.getBoundingClientRect().height)return null;const b=t.getBoundingClientRect();return [Math.round(a.bottom),Math.round(b.top)]})()")
    ok(r is not None and r[0]<=r[1],f'[13] teléfono: el aviso queda encima de la barra de abajo, sin taparla {r}')
    br.close()
print(n,'comprobaciones')
