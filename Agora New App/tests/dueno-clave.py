#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.13.1 · La clave de una cuenta de dueño (Master o Superadmin) solo se cambia por terminal
(php servidor/bin/cambiar-clave.php correo). Ni «Cambiar mi clave», ni «¿Olvidaste tu clave?», ni el Master desde Usuarios.
Los demás usuarios siguen igual. Requiere la API local en :8090 y la cuenta simulada. Deja el canal de correo apagado.
Uso: python3 tests/dueno-clave.py"""
import sys,subprocess,json,urllib.request,http.cookiejar
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0;P='Agora-Pruebas-2026!';D='Clave-Local-2026!'
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['sent']
class C:
    def __init__(s,u=None,pw=None):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.call('session/csrf')
        if u:s.call('login','POST',{'correo':u,'clave':pw})
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        r=urllib.request.Request(B+'/api/'+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers={'Content-Type':'application/json','X-CSRF-Token':csrf})
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            try:return e.code,json.loads(e.read() or b'{}')
            except Exception:return e.code,{}
limpiar();duena=C('duena@example.test',D)
st,d=duena.call('clave','POST',{'actual':D,'nueva':'Otra-Clave-Larga-2026!'})
ok(st==403 and 'terminal' in d.get('error',''),'el Master no cambia su clave desde la web (lo manda a la terminal)')
limpiar();ok(C('duena@example.test',D).call('yo')[0]==200,'su clave sigue siendo la misma')
socio=[u for u in duena.call('agency/usuarios')[1].get('items',duena.call('agency/usuarios')[1].get('usuarios',[])) if u.get('rol')=='SUPERADMIN']
if socio:
    st,d=duena.call('agency/usuarios/clave','POST',{'id':socio[0]['id'],'generar':True})
    ok(st==403 and 'terminal' in d.get('error',''),'el Master tampoco cambia la clave de otro dueño desde Usuarios')
try:
    limpiar();duena.call('agency/correo','PUT',{'connectionId':2})
    antes=len(buzon())
    g=C();st,o1=g.call('clave/olvido','POST',{'correo':'duena@example.test'});st2,o2=g.call('clave/olvido','POST',{'correo':'nadie@agora.test'})
    ok(st==200 and o1==o2 and len(buzon())==antes,'«¿Olvidaste tu clave?» no manda código a un dueño (y responde igual que a cualquiera)')
    ok(g.call('clave/restablecer','POST',{'correo':'duena@example.test','codigo':'123456','clave':'Otra-Clave-Larga-2026!'})[0]==401,'tampoco se puede restablecer la clave de un dueño')
    limpiar();g.call('clave/olvido','POST',{'correo':'ejecutivo2.asocim@agora.test'})
    ok(len(buzon())==antes+1,'para los demás usuarios «¿Olvidaste tu clave?» sigue funcionando')
finally:
    limpiar();duena.call('agency/correo','PUT',{'connectionId':None})
limpiar();e=C('ejecutivo1.asocim@agora.test',P)
ok(e.call('clave','POST',{'actual':'mala-clave-123456','nueva':'Otra-Clave-Larga-2026!'})[0]==403,'un ejecutivo sigue pudiendo usar «Cambiar mi clave» (aquí con la clave actual equivocada)')
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    limpiar();pg=br.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda x:errs.append(str(x)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('#lg-mail').fill('duena@example.test');pg.locator('#lg-pw').fill(D);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
    pg.goto(B+'/perfil');pg.wait_for_timeout(3000)
    t=pg.inner_text('main')
    ok(pg.locator('[data-pf-clave]').count()==0 and 'terminal' in t,'en el perfil del dueño no hay «Cambiar clave»: dice que se cambia por terminal')
    ok(not errs,f'sin errores {errs}');br.close()
print(n,'comprobaciones')
