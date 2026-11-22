#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.13.0 · Pantallas de acceso: código por correo, «¿Olvidaste tu clave?» y «Correo de Ágora» de la agencia.
Ordenador y teléfono. Requiere la API local en :8090 y la cuenta simulada. Deja el canal apagado y la clave de pruebas al terminar.
Uso: python3 tests/acceso-ui.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0;P='Agora-Pruebas-2026!'
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def codigo():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'})
    return re.search(r'(\d{6})',json.loads(urllib.request.urlopen(r).read())['sent'][-1].get('message','')).group(1)
class C:
    def __init__(s,u,pw):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.call('session/csrf');s.call('login','POST',{'correo':u,'clave':pw})
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        r=urllib.request.Request(B+'/api/'+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers={'Content-Type':'application/json','X-CSRF-Token':csrf})
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            try:return e.code,json.loads(e.read() or b'{}')
            except Exception:return e.code,{}
def pagina(br,vp):
    limpiar();pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    # 2.18.13: la entrada tapa la página hasta que termina de cargar; las pruebas esperan a que se vaya antes de tocar.
    ir=pg.goto
    def goto(url,**k):
        r=ir(url,**k)
        try:pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
        except Exception:pass
        pg.wait_for_timeout(600)
        if pg.locator('#layer .scrim').count():pg.keyboard.press('Escape');pg.wait_for_timeout(400)  # guía de bienvenida de la primera visita
        return r
    pg.goto=goto;pg.goto(B+'/entrar');pg.wait_for_timeout(1500);return pg,errs
limpiar();duena=C('duena@example.test','Clave-Local-2026!')
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        # ---- la agencia: solo el dueño ve «Correo de Ágora» y lo elige
        pg,errs=pagina(br,{'width':1280,'height':900})
        pg.locator('#lg-mail').fill('duena@example.test');pg.locator('#lg-pw').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(B+'/agencia');pg.wait_for_selector('#agency-correo:not([hidden])',timeout=15000)
        ok('Correo de Ágora' in pg.inner_text('#agency-correo'),'el dueño ve «Correo de Ágora» en la agencia')
        pg.click('[data-correo-editar]');pg.wait_for_selector('.modal select[name=conn]')
        pg.select_option('.modal select[name=conn]','2');pg.click('.modal [type=submit]');pg.wait_for_timeout(2500)
        ok('Sale por' in pg.inner_text('#agency-correo') and 'ASOCIM' in pg.inner_text('#agency-correo'),'elige la subcuenta que envía y queda a la vista')
        pg.click('[data-correo-probar]');pg.wait_for_timeout(2500)
        ok('Prueba enviada' in pg.inner_text('body'),'«Probar envío» confirma la prueba')
        ok(not errs,f'agencia sin errores {errs}');pg.context.close()
        # ---- entrar con código (ordenador)
        pg,errs=pagina(br,{'width':1280,'height':900})
        pg.locator('#lg-mail').fill('ejecutivo1.asocim@agora.test');pg.locator('#lg-pw').fill(P);pg.click('.lg-remember');pg.keyboard.press('Enter')
        pg.wait_for_selector('.lg-paso .lg-otp',timeout=15000)
        ok('Revisa tu correo' in pg.inner_text('.lg-card') and '•' in pg.inner_text('.lg-card'),'tras la clave aparece «Revisa tu correo» con el correo oculto')
        pg.fill('.lg-paso .lg-otp','000000' if codigo()!='000000' else '111111');pg.wait_for_timeout(1800)
        ok('no coincide' in pg.inner_text('.lg-paso') or 'no es válido' in pg.inner_text('.lg-paso'),'un código equivocado se avisa en la misma tarjeta')
        pg.fill('.lg-paso .lg-otp',codigo());pg.wait_for_timeout(4500)
        ok('/entrar' not in pg.url and pg.locator('#lg-mail').count()==0,'con el código correcto entra al sistema (6 dígitos y entra solo)')
        ok(not errs,f'entrar sin errores {errs}');pg.context.close()
        # ---- ¿Olvidaste tu clave? (teléfono)
        pg,errs=pagina(br,{'width':390,'height':844})
        pg.locator('#lg-mail').fill('ejecutivo2.asocim@agora.test');pg.click('.lg-forgot');pg.wait_for_selector('.lg-paso input[name=correo]')
        ok(pg.input_value('.lg-paso input[name=correo]')=='ejecutivo2.asocim@agora.test','«¿Olvidaste tu clave?» abre su paso con el correo ya escrito')
        pg.click('.lg-paso .lg-go');pg.wait_for_selector('.lg-paso input[name=clave2]',timeout=15000)
        pg.fill('.lg-paso .lg-otp',codigo());pg.fill('.lg-paso input[name=clave]','Nueva-Clave-UI-2026!');pg.fill('.lg-paso input[name=clave2]','otra-cosa-distinta')
        pg.click('.lg-paso .lg-go');pg.wait_for_timeout(600)
        ok('no coinciden' in pg.inner_text('.lg-paso'),'avisa si las dos claves no coinciden')
        pg.fill('.lg-paso input[name=clave2]','Nueva-Clave-UI-2026!');pg.click('.lg-paso .lg-go');pg.wait_for_timeout(2500)
        ok(pg.locator('.lg-paso').count()==0 and 'Entra con tu clave nueva' in pg.inner_text('.lg-card') and pg.input_value('#lg-mail')=='ejecutivo2.asocim@agora.test','al cambiarla vuelve a «Inicia sesión» con el aviso y el correo puesto')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'teléfono: los pasos no se desbordan')
        ok(not errs,f'teléfono sin errores {errs}');pg.context.close()
        # vuelve a la clave de pruebas
        limpiar();g=C('x@x.test','x');g.call('clave/olvido','POST',{'correo':'ejecutivo2.asocim@agora.test'})
        ok(g.call('clave/restablecer','POST',{'correo':'ejecutivo2.asocim@agora.test','codigo':codigo(),'clave':P})[0]==200,'se restablece la clave de pruebas')
        # ---- quien no es dueño no ve el selector
        limpiar();adm=C('admin.asocim@agora.test',P)
        st,d=adm.call('login','POST',{'correo':'admin.asocim@agora.test','clave':P})
        if d.get('desafio'):adm.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':codigo()})
        st,_=adm.call('agency/correo')
        ok(st==403,'quien no es dueño no puede leer ni cambiar el correo de Ágora')
    finally:
        limpiar();duena.call('agency/correo','PUT',{'connectionId':None});br.close()
print(n,'comprobaciones')
