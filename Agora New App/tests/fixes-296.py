#!/usr/bin/env python3
"""Agora New App 2.9.6 · Arreglos de la revisión completa del 18 nov.
1 Con la subcuenta conectada, Tareas no ofrece plantilla ni «Cargar desde Excel» (antes abría Migración en Productos).
2 Las ventas empiezan desde el 1/1/2026 (crear, editar e importar).
3 Una mensualidad sin fin siempre tiene cobros por delante.  4 Editarla no pierde cobros pagados después del 12.
5 «Nuevo convenio» solo lleva a contratos.agoradigital.app.  7-8 Tablero comercial sin contradicciones ni «null».
12 Excel: «0.750» es 0,75 y «2026» no es una fecha.
Requiere la API local en :8090 con ASOCIM conectada (tests/motor-puente.py). No es repetible sin límite: crea ventas de prueba.
Uso: python3 tests/fixes-296.py"""
import json,sys,os,subprocess,datetime,http.cookiejar,urllib.request
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';API=B+'/api/';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':user,'clave':pw})
        if st!=200:raise SystemExit(f'FALLA: {user} no entra ({st})')
        s.ws=d.get('activeTenantId')
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
P='Agora-Pruebas-2026!'
a=C('admin.asocim@agora.test',P);su=C('supervisor.asocim@agora.test',P)
hoy=datetime.date.today().isoformat()
def payload(v):
    p={k:v.get(k) for k in ['id','version','productoId','producto','cobro','monto','moneda','cuotas','frecuencia','fechaInicio','fecha','diaCobro','vendedorId','vendedor','notas','cantidad']}
    p['cliente']=v.get('cliente') or {'nombre':'X'}
    return {k:x for k,x in p.items() if x is not None}
def venta(i):
    st,d=a.call('workspace/ventas');return [x for x in d['items'] if x['id']==i][0]

# ---- 2 y 3: importar ventas
tag=datetime.datetime.now().strftime('%H%M%S')
st,d=a.call('workspace/migracion/importar','POST',{'modulo':'ventas','modo':'crear','filas':[{'id':f'qa-2025-{tag}','fecha':'2025-12-20','cliente':'QA Vieja','producto':'Mensualidad QA','monto':'40','cobro':'recurrente','frecuencia':'mensual','fechaInicio':'2025-12-20'}]})
ok(st==200 and d['creados']==0 and any('1 de enero de 2026' in e['mensaje'] for e in d['errores']),'importar una venta que empieza en 2025 se rechaza con un mensaje claro')
st,d=a.call('workspace/migracion/importar','POST',{'modulo':'ventas','modo':'crear','filas':[{'id':f'qa-sf-{tag}','fecha':'2026-01-05','cliente':'QA Sin fin','producto':'Mensualidad QA','monto':'40','cobro':'recurrente','frecuencia':'mensual','fechaInicio':'2026-01-05'}]})
ok(st==200 and d['creados']==1,'importar una mensualidad desde enero de 2026 funciona')
v=venta(f'qa-sf-{tag}')
ok(v.get('sinFin') and sum(1 for c in v['plan'] if c['fecha']>=hoy)>=3,f'la mensualidad importada tiene cobros por delante ({len(v["plan"])} cobros)')
ok(all(c['estado']=='pagado' for c in v['plan'] if c['fecha']<hoy) and all(c['estado']!='pagado' for c in v['plan'] if c['fecha']>hoy),'los cobros pasados quedan pagados y los futuros pendientes')
p=payload(v);p['fechaInicio']='2025-12-31'
st,d=a.call('workspace/ventas','PUT',p);ok(st==422 and '1 de enero de 2026' in d.get('error',''),'editar con inicio en 2025 se rechaza')
p=payload(venta(f'qa-sf-{tag}'));p['notas']='solo la nota'
st,d=a.call('workspace/ventas','PUT',p);ok(st==200,'editar solo la nota de una venta de 2026 funciona')

# ---- 4: sin fin con más de 12 pagados
v=venta(f'qa-sf-{tag}')
for c in v['plan'][:15]:
    if c['estado']!='pagado':a.call('workspace/ventas/cobro','POST',{'ventaId':v['id'],'n':c['n'],'pagado':True})
v=venta(f'qa-sf-{tag}');pag=sum(1 for c in v['plan'] if c['estado']=='pagado')
st,d=a.call('workspace/ventas','PUT',payload(v))
pag2=sum(1 for c in d['venta']['plan'] if c['estado']=='pagado') if st==200 else -1
ok(pag>12 and pag2==pag,f'editar una mensualidad con {pag} cobros pagados los conserva ({pag2})')
ok(sum(1 for c in d['venta']['plan'] if c['fecha']>=hoy)>=3,'y sigue con cobros por delante')

# ---- 12: lectura de Excel en el servidor
php=subprocess.run(['php','-r','require "servidor/app/src/Controllers/Migracion.php";echo json_encode([Agora\\Controllers\\Migracion::numero("0.750"),Agora\\Controllers\\Migracion::numero("1.730,50 $"),Agora\\Controllers\\Migracion::numero("12.000"),Agora\\Controllers\\Migracion::fecha("2026"),Agora\\Controllers\\Migracion::fecha("13/11/2026"),Agora\\Controllers\\Migracion::fecha(46000)]);'],cwd=os.path.dirname(os.path.dirname(os.path.abspath(__file__))),capture_output=True,text=True)
r=json.loads(php.stdout)
ok(r[0]==0.75 and r[1]==1730.5 and r[2]==12000,'Excel: «0.750» es 0,75; «1.730,50 $» y «12.000» siguen bien')
ok(r[3] is None and r[4]=='2026-11-13' and r[5]=='2025-12-09','Excel: «2026» no es una fecha; 13/11/2026 y una fecha de Excel siguen bien')

# ---- 7 y 8: cifras del mes coherentes
mes=hoy[:7]
st,b=a.call(f'workspace/board-month?period={mes}')
pr=b.get('profitability',{})
ok(st==200 and pr.get('adsFromAccount') is not None,'Rentabilidad usa lo invertido en Meta que informa la cuenta')
st2,ads=a.call(f'workspace/ads?period={mes}')
ok(ads.get('estado')!='ok' or abs(float(ads['mes'])-float(pr['adsFromAccount']))<0.01,'la inversión de Rentabilidad es la misma de «Inversión en anuncios»')
ok(b.get('mine') is None or b['mine'].get('closings') is not None,'«Mis ventas del mes» nunca trae cifras vacías')
ok(len(b.get('team') or [])>0,'el equipo del mes se arma con las personas importadas de la cuenta')
ok(b.get('canEdit') is True,'la administradora edita las cifras del mes')
st,bs=su.call(f'workspace/board-month?period={mes}');ok(st==200 and bs.get('canEdit') is False,'la supervisora las ve sin editarlas')

# ---- pantallas
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg=br.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    pg.goto(B+'/tareas');pg.wait_for_timeout(4000)
    ops=[]
    if pg.locator('.mod-h .mg-hbtn').count():
        pg.locator('.mod-h .mg-hbtn').click();pg.wait_for_timeout(400);ops=pg.locator('.mg-hpop [data-op]').evaluate_all('l=>l.map(x=>x.dataset.op)');pg.keyboard.press('Escape')
    ok('cargar' not in ops and 'plantilla' not in ops,f'Tareas con la subcuenta conectada: sin plantilla ni carga por Excel ({ops})')
    pg.goto(B+'/contratos/nuevo');pg.wait_for_timeout(2500)
    ok(pg.locator('a[href="https://contratos.agoradigital.app"][target=_blank]').count()==1 and pg.locator('[data-form="buscar-nuevo"]').count()==0,'«Nuevo convenio» solo lleva a contratos.agoradigital.app')
    pg.goto(B+'/comercial');pg.wait_for_timeout(7000);pg.mouse.wheel(0,6000);pg.wait_for_timeout(3000)
    t=pg.evaluate("document.querySelector('main').innerText")
    ok('null' not in t,'el Tablero no muestra «null»')
    ok('La cuenta no ha informado lo invertido' not in t,'Rentabilidad no contradice a «Inversión en anuncios»')
    ok('Todavía no hay equipo importado' not in t,'el equipo del mes aparece')
    cp=t[t.find('Campañas por gasto'):t.find('Campañas por gasto')+400]
    ok('Sin movimiento' not in cp,'«Campañas por gasto» muestra las campañas de Meta')
    ok('según el reporte mensual' in t or 'según las ventas registradas' in t,'«Ventas del mes» dice de dónde sale la cifra')
    ok(not errs,f'sin errores de pantalla {errs}')
    br.close()
print(n,'comprobaciones')
