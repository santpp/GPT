#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.19.0 · Depuración y velocidad (pedido del usuario):
diccionario de errores (cada error con código y razón), bitácora del servidor, «Reportar un problema», Agencia → Errores y reportes
(con coincidencias por referencia), ?debug=1 para dueños, diagnóstico por terminal, Dashboard sin consultas repetidas y con copia
de 30 s, Supervisor abre el detalle de las ruedas y copyright 2026-2027 solo en el código.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-2190.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re,time
B='http://127.0.0.1:8090/api/';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['sent']
def ultimo_codigo():
    m=re.search(r'(\d{6})',buzon()[-1].get('message',''));return m.group(1) if m else None
class C:
    def __init__(s):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None;s.call('session/csrf')
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(B+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            try:return e.code,json.loads(e.read() or b'{}')
            except Exception:return e.code,{}
    def cookie(s,name):return next((c for c in s.cj if c.name==name),None)
    def entrar(s,u,pw,**k):
        st,d=s.call('login','POST',{'correo':u,'clave':pw,**k});s.ws=d.get('activeTenantId');return st,d
P='Agora-Pruebas-2026!'
import os,collections
from playwright.sync_api import sync_playwright
W='http://127.0.0.1:8090';RAIZ=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..')
def db(sql):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',sql],capture_output=True,text=True).stdout.strip()
def cabecera(c,path):
    csrf=next((x.value for x in c.cj if x.name=='agora_csrf'),'');h={'X-CSRF-Token':csrf}
    if c.ws:h['X-Workspace-ID']=str(c.ws)
    r=urllib.request.Request(B+path,headers=h)
    with c.op.open(r) as x:return x.headers.get('X-Agora-Copia'),json.loads(x.read())
# ---- código fuente
falta=[]
for dp,dn,fn in os.walk(RAIZ):
    if '/.git' in dp or '/fonts' in dp or '/storage' in dp:continue
    for f in fn:
        if f.rsplit('.',1)[-1] in('php','js','css','html','py') and f!='config.php':
            if '© 2026-2027 Santiago Rafael Pérez Pérez' not in open(os.path.join(dp,f),encoding='utf-8').read()[:700]:falta.append(f)
ok(not falta,f'todo archivo de código propio lleva «© 2026-2027 Santiago Rafael Pérez Pérez» {falta[:3]}')
ok('2026-2027' in open(os.path.join(RAIZ,'LICENSE.txt'),encoding='utf-8').read(),'LICENSE.txt dice 2026-2027')
# ---- diccionario y bitácora
db("delete from agora_error_log");db("delete from agora_error_reports")
limpiar();st,x=C().call('login','POST',{'correo':'admin.asocim@agora.test','clave':'otra-clave-mala'})
ok(st==401 and x.get('codigo')=='AG-ACC-401A' and x.get('requestId'),f'cada error trae su código y su referencia ({x.get("codigo")})')
limpiar();adm=C();adm.entrar('admin.asocim@agora.test',P)
st,x=adm.call('agency/errores');ok(st==403 and x.get('codigo')=='AG-AGE-403','la administración de una subcuenta no ve los errores (solo dueños)')
st,x404=adm.call('workspace/no-existe');ok(x404.get('codigo')=='AG-GES-404R',f'error concreto con su letra ({x404.get("codigo")})')
ok(db("select codigo from agora_error_log where referencia='"+x404['requestId']+"'")=='AG-GES-404R','queda en la bitácora con su referencia')
limpiar();ej=C();ej.entrar('ejecutivo1.asocim@agora.test',P)
ok(ej.call('agency/errores')[0]==403,'un ejecutivo tampoco')
st,r=ej.call('reportes','POST',{'descripcion':'','errores':[]});ok(st==422,'un reporte vacío se rechaza con su explicación')
st,r=ej.call('reportes','POST',{'descripcion':'<script>alert(1)</script> No guarda la venta','pantalla':'/ventas','version':'2.19.0','dispositivo':'prueba',
  'errores':[{'codigo':x404['codigo'],'referencia':x404['requestId'],'mensaje':'x','ruta':'workspace/no-existe','hora':'2026-11-22T10:00:00Z','status':404},{'codigo':'MALO','referencia':'zz','mensaje':'y'}],'lentas':[{'ruta':'workspace/dia','ms':9100,'status':200}]})
ok(st==200 and r['referencia'].startswith('R-'),f'cualquier persona reporta un problema ({r.get("referencia")})')
limpiar();duena=C();duena.entrar('duena@example.test','Clave-Local-2026!')
st,d=duena.call('agency/errores?dias=1');rep=d['reportes'][0]
ok(st==200 and rep['nombre'] and rep['subcuenta'] and rep['descripcion'].startswith('<script>'),'el dueño ve el reporte con persona y subcuenta (el texto se guarda tal cual y la pantalla lo escapa)')
e0,e1=rep['errores']
ok(e0['servidor'] and e0['servidor']['referencia']==x404['requestId'] and e0['explicacion']['razon'],'el error del reporte coincide con el del servidor por su referencia y trae su razón')
ok(e1['codigo']=='AG-NAV-900' and e1['referencia']=='','un código o referencia inválidos se limpian')
ok(rep['lentas'][0]['ms']==9100,'van las consultas lentas')
ok(duena.call('agency/errores/reporte','PUT',{'id':rep['id'],'estado':'resuelto'})[1].get('estado')=='resuelto','se marca como resuelto')
ok(duena.call('agency/errores/reporte','PUT',{'id':rep['id'],'estado':'borrado'})[0]==422,'un estado raro se rechaza')
dic=duena.call('agency/errores/diccionario')[1]
ok(len(dic['especificos'])>=19 and len(dic['tipos'])>=15 and all(x['razon'] for x in dic['especificos']+dic['tipos']),'el diccionario tiene cada código con su razón')
ok(not any('HighLevel' in json.dumps(dic) or 'GHL' in json.dumps(dic) for _ in [0]),'el diccionario no nombra al proveedor')
# ---- velocidad: copia de 30 s por persona
c1,_=cabecera(adm,'workspace/dia?period=2026-11&date=2026-11-22');c2,_=cabecera(adm,'workspace/dia?date=2026-11-22&period=2026-11')
ok(c2=='1','la misma consulta (aunque cambie el orden) sale de la copia la segunda vez')
c3,_=cabecera(ej,'workspace/dia?period=2026-11&date=2026-11-22');ok(c3!='1','otra persona no recibe la copia de otra')
adm.call('workspace/preferencias','PUT',{'vista':'g_comercial','tarjetas':None,'equipo':False})
c4,_=cabecera(adm,'workspace/dia?period=2026-11&date=2026-11-22');ok(c4!='1','cualquier cambio guardado borra las copias de la subcuenta')
# ---- supervisor: detalle de las ruedas
limpiar();sup=C();sup.entrar('supervisor.asocim@agora.test',P)
st,dd=sup.call('workspace/dia');ok(all('detalle' in x for x in dd['sinLeerPorAsesor']),'el Supervisor recibe el detalle de las ruedas')
# ---- terminal
r=subprocess.run(['php',os.path.join(RAIZ,'servidor/bin/diagnostico.php')],capture_output=True,text=True)
ok('Diagnóstico de Ágora Digital App' in r.stdout and 'Migraciones cambiadas' in r.stdout and r.returncode==0,'el diagnóstico por terminal corre y no encuentra nada en rojo')
ok(not re.search(r'app_key|token_ciphertext|password',r.stdout,re.I),'el diagnóstico no muestra claves')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    def entra(u,pw_,w,h):
        limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda x:pg.errs.append(str(x)))
        pg.goto(W+'/entrar');pg.wait_for_timeout(1500);pg.locator('#lg-mail').fill(u);pg.locator('#lg-pw').fill(pw_);pg.keyboard.press('Enter');pg.wait_for_timeout(3500);return pg
    try:
        for w,h in ((1280,800),(390,844)):
            pg=entra('ejecutivo1.asocim@agora.test',P,w,h);red=[]
            pg.on('request',lambda q:red.append(q.url.split('/api/')[1].split('?')[0]) if '/api/workspace/' in q.url else None)
            pg.goto(W+'/comercial');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_timeout(1500)
            c=collections.Counter(x for x in red if x in('workspace/dashboard','workspace/board-month','workspace/dia','workspace/ads','workspace/eficacia'))
            ok(c and max(c.values())==1,f'{w}px: el Dashboard pide cada cifra de la cuenta una sola vez {dict(c)}')
            pg.evaluate("fetch('/api/workspace/no-existe-tampoco')");pg.wait_for_timeout(800)
            ok(pg.locator('#ag-debug').count()==0,f'{w}px: un ejecutivo no ve el panel de depuración')
            pg.locator('#b-acct').click();pg.wait_for_timeout(500)
            pg.locator('[data-reportar]').click();pg.wait_for_selector('.modal .rp',timeout=5000)
            ok('AG-GES-404' in pg.inner_text('.modal'),f'{w}px: «Reportar un problema» muestra los errores que se adjuntan con su código')
            pg.fill('.modal [name=descripcion]','Prueba de reporte desde la pantalla');pg.click('[data-rp-ok]');pg.wait_for_timeout(1500)
            ok(pg.locator('.modal .rp').count()==0 and 'Reporte enviado' in pg.inner_text('#toast'),f'{w}px: el reporte se envía')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
            pg.close()
            pg=entra('duena@example.test','Clave-Local-2026!',w,h)
            pg.goto(W+'/agencia/errores?debug=1');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('.er-rep',timeout=10000)
            if pg.locator('#layer .scrim').count():pg.keyboard.press('Escape')
            txt=pg.inner_text('main#app')
            ok('Prueba de reporte desde la pantalla' in txt and 'coincide con un error guardado en el servidor' in txt,f'{w}px: Agencia → Errores muestra el reporte y la coincidencia con el servidor')
            ok(pg.locator('.er-desc',has_text='<script>').count()>=1 and pg.evaluate("!document.querySelector('.er-desc script')"),f'{w}px: el texto del reporte se muestra escapado')
            ok(pg.locator('#ag-debug li').count()>=1,f'{w}px: con ?debug=1 el dueño ve el panel de tiempos')
            ok(pg.evaluate('AgoraDiag.errores.length')==0 and pg.locator('main#app [data-tg-editar], main#app [data-cel-ed]').count()==0,f'{w}px: la página de errores no provoca errores ni lleva «Editar Dashboard» {pg.evaluate("AgoraDiag.errores.map(e=>e.codigo)")}')
            pg.click('[data-er-pest=servidor]');pg.wait_for_timeout(1500);ok('AG-GES-404R' in pg.inner_text('main#app'),f'{w}px: pestaña de errores del servidor')
            pg.click('[data-er-pest=diccionario]');pg.wait_for_timeout(1500);ok('Código equivocado' in pg.inner_text('main#app') and 'Cómo se lee un código' in pg.inner_text('main#app'),f'{w}px: pestaña del diccionario')
            if w==1280:pg.click('[data-er-pest=reportes]');pg.wait_for_timeout(1500);pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/errores.png')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores en Agencia {pg.errs[:2]}')
            ok(pg.locator('body').inner_text().count('Santiago Rafael')==0,'el copyright no aparece en pantalla (solo en el código)')
            pg.close()
        pg=entra('supervisor.asocim@agora.test',P,1280,800);pg.goto(W+'/comercial');pg.wait_for_selector('[data-rep-panel=sinleer] .dn-lg li',timeout=20000);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
        pg.locator('[data-rep-panel=sinleer] .dn-lg li').first.click();pg.wait_for_selector('.modal .dn-items',timeout=5000)
        ok(True,'el Supervisor abre el detalle de un asesor en el Dashboard')
    finally:
        br.close()
print(n,'comprobaciones')
