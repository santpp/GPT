#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.15 · Accesos con código (pedido del usuario): el dueño ve quién pidió o usó un código de 6 dígitos,
de qué subcuenta, desde qué IP y navegador. Nunca el código. Solo dueños (Master y Superadmin).
Requiere la API local en :8090 y la cuenta simulada. Deja el canal apagado al terminar. Uso: python3 tests/fixes-21815.py"""
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
import os
from playwright.sync_api import sync_playwright
W='http://127.0.0.1:8090'
def db(sql):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',sql],capture_output=True,text=True).stdout.strip()
def ultimo_codigo():
    m=re.search(r'(\d{6})',buzon()[-1].get('message',''));return m.group(1) if m else None
ok('utf8mb4_unicode_ci' in db("show create table agora_access_log"),'la tabla nueva usa utf8mb4_unicode_ci')
db("delete from agora_access_log")
limpiar();duena=C();duena.entrar('duena@example.test','Clave-Local-2026!')
codigos=[]
try:
    ok(duena.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':True})[0]==200,'canal de correo encendido para la prueba')
    limpiar();e=C();st,d=e.entrar('ejecutivo1.asocim@agora.test',P);cod=ultimo_codigo();codigos.append(cod)
    malo='000000' if cod!='000000' else '111111'
    e.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':malo})
    db("update agora_access_codes set sent_at=UTC_TIMESTAMP()-INTERVAL 2 MINUTE where used_at is null")
    ok(e.call('login/reenviar','POST',{'desafio':d['desafio']})[0]==200,'pide otro código');cod2=ultimo_codigo();codigos.append(cod2)
    ok(e.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':cod2})[0]==200,'entra con el código')
    limpiar();g=C();g.call('clave/olvido','POST',{'correo':'nadie.existe@ejemplo.test'});g.call('clave/olvido','POST',{'correo':'duena@example.test'})
    st,r=duena.call('agency/accesos?dias=1');its=r.get('items',[])
    ev=[x['evento'] for x in its]
    ok(st==200 and ev[:6]==['dueno_bloqueado','sin_cuenta','correcto','reenviado','incorrecto','enviado'],f'quedan anotados en orden: enviado, equivocado, otro código, correcto, clave de un correo sin cuenta y de un dueño {ev}')
    x=next(i for i in its if i['evento']=='enviado')
    ok(x['ip']=='127.0.0.1' and x['correo']=='ejecutivo1.asocim@agora.test' and x['subcuenta'] and 'ASOCIM' in x['subcuenta'].upper() and x['nombre'],f'cada uno dice persona, correo, subcuenta e IP ({x["nombre"]}, {x["subcuenta"]}, {x["ip"]})')
    ok(next(i for i in its if i['evento']=='sin_cuenta')['correo']=='nadie.existe@ejemplo.test','se ve qué correo sin cuenta se intentó')
    ok(next(i for i in its if i['evento']=='dueno_bloqueado')['subcuenta']=='Agencia','el intento sobre la clave de un dueño se marca como de la agencia')
    crudo=json.dumps(r)
    ok(all(c and c not in crudo for c in codigos) and 'code_hash' not in crudo,'nunca aparece el código ni su huella')
    ok(r['ips'] and r['ips'][0]['ip']=='127.0.0.1' and r['ips'][0]['malos']>=1,f'resumen de IP con pedidos y códigos equivocados {r["ips"][:1]}')
    # Seguridad (arreglo de esta versión): los intentos equivocados se guardan aunque la respuesta sea un error.
    limpiar();e2=C();st,d=e2.entrar('ejecutivo2.asocim@agora.test',P);bueno=ultimo_codigo()
    for i in range(5):e2.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':'%06d'%((int(bueno)+1+i)%1000000)})
    ok(db("select attempts from agora_access_codes where challenge_hash=sha2('"+d['desafio']+"',256)")=='5','los 5 intentos equivocados quedan contados (antes se deshacían y el tope no servía)')
    st,x=e2.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':bueno})
    ok(st==401,'después de 5 intentos equivocados ni el código correcto sirve: hay que pedir uno nuevo')
    limpiar();e3=C()
    for i in range(31):st,x=e3.call('login/codigo','POST',{'desafio':'a'*48,'codigo':'123456'})
    ok(st==429,'la misma IP no puede probar códigos sin fin (límite por IP)')
    ok(duena.call('agency/accesos?dias=1&evento=invalido')[1]['items'],'los intentos con un pedido que no existe quedan como «Código sin pedido válido»')
    limpiar()
    st,r2=duena.call('agency/accesos?dias=1&evento=incorrecto')
    ok(st==200 and r2['items'] and all(i['evento']=='incorrecto' for i in r2['items']),'filtra por lo que pasó')
    st,r3=duena.call('agency/accesos?dias=1&q=nadie.existe')
    ok(st==200 and len(r3['items'])==1,'busca por correo, persona, subcuenta o IP')
    ok(duena.call('agency/accesos?evento=<script>')[0]==422,'un filtro raro se rechaza')
finally:
    limpiar();st,d=duena.call('agency/correo','PUT',{'connectionId':None})
    ok(st==200 and d.get('connectionId') is None,'el canal queda apagado (las demás pruebas entran sin código)')
limpiar();adm=C();adm.entrar('admin.asocim@agora.test',P)
ok(adm.call('agency/accesos')[0]==403,'la administración de una subcuenta no lo ve (solo dueños)')
limpiar();ej=C();ej.entrar('ejecutivo1.asocim@agora.test',P)
ok(ej.call('agency/accesos')[0]==403,'un ejecutivo tampoco')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for w,h in ((1280,800),(390,844)):
            limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda x:errs.append(str(x)))
            pg.goto(W+'/entrar');pg.wait_for_timeout(1500);pg.locator('#lg-mail').fill('duena@example.test');pg.locator('#lg-pw').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
            pg.goto(W+'/agencia');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_timeout(800)
            if pg.locator('#layer .scrim').count():pg.keyboard.press('Escape');pg.wait_for_timeout(400)
            ok(pg.locator('a[href$="/agencia/accesos"]').count()>=1,f'{w}px: en Agencia está «Accesos con código»')
            pg.goto(W+'/agencia/accesos');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('.acc-tw tbody tr',timeout=10000)
            txt=pg.inner_text('main#app')
            ok('Accesos con código' in txt and '127.0.0.1' in txt and 'Código equivocado' in txt and 'Pidió cambiar la clave de un dueño' in txt,f'{w}px: la lista se ve con IP y lo que pasó')
            ok(not re.search(r'GoHighLevel|LeadConnector|HighLevel|undefined|null',txt),f'{w}px: sin textos técnicos')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w,f'{w}px: nada se desborda')
            if w==1280:pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/accesos.png',full_page=False)
            pg.select_option('[data-acc-ev]','incorrecto');pg.wait_for_timeout(1500)
            ok(pg.locator('.acc-tw tbody tr').count()>=1 and all('Código equivocado' in t for t in pg.locator('.acc-tw tbody tr').all_inner_texts()),f'{w}px: el filtro funciona en pantalla')
            pg.select_option('[data-acc-ev]','');pg.wait_for_timeout(1200);pg.locator('[data-acc-ip]').first.click();pg.wait_for_timeout(1500)
            ok(pg.input_value('[data-acc-q]')=='127.0.0.1','tocar una IP muestra todo lo de esa IP')
            ok(not errs,f'{w}px: sin errores de la página {errs[:2]}')
            pg.close()
        limpiar();pg=br.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page()
        pg.goto(W+'/entrar');pg.wait_for_timeout(1500);pg.locator('#lg-mail').fill('admin.asocim@agora.test');pg.locator('#lg-pw').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(W+'/agencia/accesos');pg.wait_for_timeout(4000)
        ok(pg.locator('.acc-tw').count()==0,'la administración de una subcuenta no puede abrir la pantalla')
    finally:
        br.close()
print(n,'comprobaciones')
