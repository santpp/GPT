#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.10.3 · Costos se editan en Costos (informe del 19 nov: «No me deja editar. Tampoco los KPIs que debería medir»).
Sin reporte mensual: «Cargar costos» por concepto (fijo, variable, publicidad fuera de la cuenta), las tarjetas miden con eso
(costo total, fijos, margen), Configuración mensual muestra los totales sin pisarlos y el servidor valida lo que llega.
Además, criterio único: Calidad de datos también tiene «Editar tarjetas», y ningún módulo dice «Elegir cifras».
Requiere la API local en :8090. Usa la subcuenta 1 (sin reporte) con la dueña y deja sus cifras del mes como estaban.
Uso: python3 tests/costos-editar.py"""
import json,sys,datetime,http.cookiejar,urllib.request
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';API=B+'/api/';n=0;WS=1
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw,ws=None):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':user,'clave':pw})
        if st!=200:raise SystemExit(f'FALLA: {user} no entra ({st})')
        s.ws=d.get('activeTenantId')
        if ws:s.call('workspace/active','POST',{'connectionId':ws});s.ws=ws
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(API+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r,timeout=60) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            t=e.read()
            try:return e.code,json.loads(t)
            except Exception:return e.code,{}
mes=datetime.date.today().strftime('%Y-%m')
d=C('duena@example.test','Clave-Local-2026!',WS)
st,antes=d.call(f'workspace/board-month?period={mes}');antes=antes.get('settings') or {}
guardar=lambda body:d.call(f'workspace/board-month?period={mes}','POST',{**body,'period':mes})
try:
    # ---- servidor
    st,r=guardar({'costItems':[{'concepto':'','tipo':'fijo','monto':10}]});ok(st==422,'un costo sin concepto se rechaza')
    st,r=guardar({'costItems':[{'concepto':'Alquiler','tipo':'otro','monto':10}]});ok(st==422,'un tipo desconocido se rechaza')
    st,r=guardar({'costItems':[{'concepto':'Alquiler','tipo':'fijo','monto':-5}]});ok(st==422,'un monto negativo se rechaza')
    st,r=guardar({'costItems':[{'concepto':'<b>Alquiler</b>','tipo':'fijo','monto':'300.5'},{'concepto':'Google','tipo':'publicidad','monto':100}]})
    st,g=d.call(f'workspace/board-month?period={mes}');s=g.get('settings') or {}
    ok(s.get('fixedCosts')==300.5 and s.get('otherAds')==100 and len(s.get('costItems') or [])==2,'los totales del mes salen de los conceptos (fijos 300,5 · publicidad 100)')
    guardar({**antes,'costItems':None})
    # ---- pantalla
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        for nombre,vp in [('telefono',{'width':390,'height':844}),('escritorio',{'width':1280,'height':900})]:
            pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
            pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
            pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
            pg.evaluate("async id=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:id})})}",WS)
            pg.goto(B+'/costos');pg.wait_for_timeout(5000)
            if nombre=='telefono':
                ok(pg.locator('[data-co-editar]').count()==1,'Costos ofrece «Cargar costos» aquí mismo')
                pg.click('[data-co-editar]');pg.wait_for_selector('.co-fila')
                f=pg.locator('.co-fila').nth(0);f.locator('[data-k=concepto]').fill('Sueldos');f.locator('[data-k=monto]').fill('1.200,50')
                pg.click('[data-agregar]');f=pg.locator('.co-fila').nth(1);f.locator('[data-k=concepto]').fill('Comisiones');f.locator('[data-k=tipo]').select_option('variable');f.locator('[data-k=monto]').fill('80')
                ok('1.280,50' in pg.inner_text('[data-total]'),'el total del mes se ve mientras se escribe')
                ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'en el teléfono el editor no se desborda')
                pg.screenshot(path='/tmp/costos-editor.png')
                pg.click('.ag-cobros-form [type=submit]');pg.wait_for_timeout(2500)
                t=pg.evaluate("document.querySelector('main').innerText")
                ok('Costos de' in t and 'Sueldos' in t and 'Comisiones' in t,'los conceptos guardados se ven en «Costos del mes»')
                ok('1.280,50' in t,'la tarjeta «Costo total» mide los costos cargados')
                ok('Margen del mes' in t,'«Margen del mes» está entre las tarjetas')
                pg.screenshot(path='/tmp/costos-telefono.png',full_page=True)
                pg.goto(B+'/configuracion');pg.wait_for_timeout(4000)
                ok(pg.locator('input[name=fixedCosts][disabled]').count()==1 and 'Editar en Costos' in pg.evaluate("document.querySelector('main').innerText"),'Configuración mensual muestra los costos sin pisarlos y lleva a Costos')
            else:
                pg.click('[data-co-editar]');pg.wait_for_selector('.co-fila')
                ok(pg.locator('.co-fila').count()==2,'al volver a editar, los conceptos guardados están ahí')
                pg.keyboard.press('Escape')
                pg.goto(B+'/calidad');pg.wait_for_timeout(4000)
                ok(pg.locator('[data-ca-tarjetas]').count()==1,'Calidad de datos también tiene «Editar tarjetas»')
                pg.goto(B+'/inicio');pg.wait_for_timeout(5000)
                ok('Elegir cifras' not in pg.evaluate("document.body.innerText"),'ningún módulo dice «Elegir cifras»: es «Editar tarjetas» en todos')
            ok(not errs,f'sin errores de pantalla en {nombre} {errs}')
        br.close()
    st,r=C('supervisor.asocim@agora.test','Agora-Pruebas-2026!').call(f'workspace/board-month?period={mes}','POST',{'costItems':[],'period':mes})
    ok(st==403,'la supervisora no puede cambiar los costos')
finally:
    guardar({**antes,'costItems':None})
print(n,'comprobaciones')
