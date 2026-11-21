#!/usr/bin/env python3
"""Agora New App 2.12.14 · Fixes menores pendientes + cabeceras de seguridad para el despliegue.
- Cabeceras: la API manda las suyas (sin marco, CSP cerrada, aislamiento); la página sale con su CSP (también en local) y
  ninguna pantalla rompe la política; el .htaccess declara HSTS, permisos del navegador y bloquea archivos de trabajo.
- M2 /modulos/general lleva al Inicio. M3 Consulta sin enlace: solo el aviso de enlace. M5 Excel de Usuarios con el nombre del rol.
- M8 La búsqueda de contactos (Buscar en la cartera) usa el texto buscado.
Requiere la API local en :8090. Uso: python3 tests/fixes-21214.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re,os
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
class C:
    def __init__(s,u,pw):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj))
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':u,'clave':pw});s.ws=d.get('activeTenantId')
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if getattr(s,'ws',None):h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(B+'/api/'+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:return e.code,{}
# ---- cabeceras
h=urllib.request.urlopen(B+'/api/health').headers
ok(h.get('X-Frame-Options')=='DENY' and "default-src 'none'" in (h.get('Content-Security-Policy') or '') and h.get('Cross-Origin-Resource-Policy')=='same-origin' and not h.get('X-Powered-By'),'la API manda sus cabeceras de seguridad y no dice qué servidor usa')
h=urllib.request.urlopen(B+'/centro').headers
ok("frame-ancestors 'self'" in (h.get('Content-Security-Policy') or '') and "object-src 'none'" in h.get('Content-Security-Policy',''),'la página sale con su política de contenido')
ht=open(os.path.join(os.path.dirname(__file__),'..','public_html','.htaccess')).read()
ok(all(x in ht for x in ['Strict-Transport-Security','Permissions-Policy','Cross-Origin-Opener-Policy','X-Content-Type-Options','unset X-Powered-By']) and re.search(r'RewriteRule .*md\|sql',ht),'el .htaccess declara HSTS, permisos, aislamiento y bloquea archivos de trabajo')
# ---- M5 y M8 por la API
limpiar();a=C('admin.asocim@agora.test','Agora-Pruebas-2026!')
st,d=a.call('workspace/migracion/exportar?modulo=usuarios')
roles={str(v) for f in d.get('filas',[]) for k,v in f.items() if 'rol' in k.lower()}
ok(st==200 and roles and not roles&{'ADMIN','SUPERVISOR','EJECUTIVO','CONSULTA'},f'M5 · el Excel de Usuarios trae el nombre del rol {sorted(roles)[:4]}')
st,todos=a.call('ghl/contactos/buscar','POST',{'consulta':''});st2,uno=a.call('ghl/contactos/buscar','POST',{'consulta':'Camila'})
ok(st2==200 and len(uno.get('contactos',[]))<len(todos.get('contactos',[])) and all('Camila' in (c.get('nombre') or '') for c in uno.get('contactos',[])),'M8 · «Buscar en la cartera» usa el texto buscado')
# ---- pantalla
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    limpiar();pg=br.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page();msgs=[]
    pg.on('console',lambda m:msgs.append(m.text) if 'Content Security Policy' in m.text else None);pg.on('pageerror',lambda e:msgs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    pg.goto(B+'/modulos/general');pg.wait_for_timeout(3000)
    ok('0 submódulos' not in pg.inner_text('main') and pg.locator('main .inicio-resumen, main .operations-home').count()>0,'M2 · /modulos/general lleva al Inicio')
    for r in ['/centro','/comercial','/bandeja','/contactos','/ventas','/facturas','/costos','/usuarios']:
        pg.goto(B+r);pg.wait_for_timeout(2500)
    ok(not msgs,f'ninguna pantalla rompe la política de contenido ni da errores {msgs[:3]}');pg.context.close()
    limpiar();pg=br.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page()
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('consulta.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    sinEnlace=pg.evaluate("!!(window.ESPACIO||null)")
    pg.goto(B+'/bandeja');pg.wait_for_timeout(4000)
    aviso=pg.locator('.aviso-enlace').count()
    rep=pg.evaluate("[...document.querySelectorAll('main *')].filter(e=>!e.children.length&&e.offsetParent&&/^No tienes permiso para consultar esta cuenta/.test(e.textContent.trim())).length")
    ok(not aviso or rep==0,f'M3 · con el aviso de enlace no se repite «No tienes permiso…» (aviso {aviso}, repetidos {rep})')
    br.close()
print(n,'comprobaciones')
