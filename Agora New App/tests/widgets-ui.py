#!/usr/bin/env python3
"""Agora New App 2.15.0 · Widgets del equipo en pantalla: editor de tableros y editor de fórmulas.
Administración agrega una cifra y una fórmula (autocompletar, vista previa, error marcado), compara y pone semáforo;
lo guardado lo ven igual ejecutivo y consulta, sin poder editar. Teléfono 390 sin desborde.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/widgets-ui.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';P='Agora-Pruebas-2026!';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
class C:
    def __init__(s,u,pw=P):
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
def entrar(br,u,w=1280,h=800):
    limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page()
    pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    return pg
def limpiar_datos():
    limpiar();a=C('admin.asocim@agora.test')
    for m in ('ventas','centro'):a.call(f'workspace/tableros?modulo={m}','DELETE',{'modulo':m})
    for f in a.call('workspace/formulas')[1].get('formulas',[]):
        if f['nombre'].startswith('Prueba UI'):a.call('workspace/formulas','DELETE',{'id':f['id']})
    return a
adm=limpiar_datos()
vendido=adm.call('workspace/metricas?periodo=2026-11&ids=mes.vendido')[1]['valores']['mes.vendido']['valor']
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test')
        pg.goto(B+'/ventas');pg.wait_for_timeout(4500)
        ok(pg.locator('section.wg [data-wg-abrir]').inner_text().strip()=='Agregar widgets','administración ve «Agregar widgets» en un módulo sin widgets')
        ok(pg.evaluate("document.querySelector('section.wg').previousElementSibling.querySelector('.cel')!==null"),'los widgets van justo después de las cifras del módulo')
        pg.click('[data-wg-abrir]');pg.wait_for_selector('[data-wg-nuevo]')
        pg.select_option('[data-wg-nuevo]','mes.vendido');pg.wait_for_timeout(300)
        pg.fill('[data-k=titulo]','Prueba vendido');pg.select_option('[data-k=comparar]','mes_anterior');pg.check('[data-k=sem]');pg.wait_for_timeout(200)
        pg.fill('[data-k=verde]','100');pg.fill('[data-k=ambar]','50')
        # nueva fórmula desde el editor de tableros
        pg.click('[data-wg-formula]');pg.wait_for_selector('textarea[name=expresion]')
        pg.fill('input[name=nombre]','Prueba UI margen')
        ta=pg.locator('textarea[name=expresion]');ta.click();ta.type('{vendido d');pg.wait_for_timeout(300)
        ac=pg.evaluate("[...document.querySelectorAll('.wg-ac li b')].map(x=>x.textContent)")
        ok(ac and ac[0]=='Vendido del mes',f'al escribir «{{» se sugieren cifras ({ac[:3]})')
        pg.keyboard.press('Enter');ta.type(' - {costos fij');pg.keyboard.press('Enter');pg.wait_for_timeout(1300)
        ok(ta.input_value()=='{Vendido del mes} - {Costos fijos}','el autocompletar escribe el nombre completo entre llaves')
        ok(pg.locator('.wg-fx-ver .fx-ref').count()==2 and pg.locator('.wg-fx-ver .fx-op').count()>=1,'la fórmula se ve con colores (cifras y operaciones)')
        prev=pg.inner_text('.wg-prev')
        ok('709,50' in prev,f'vista previa en vivo con el resultado ({prev})')
        ta.fill('{Vendido del mes} - (');pg.wait_for_timeout(1200)
        ok(pg.locator('.wg-prev.is-err').count()==1 and pg.locator('.wg-fx-ver .fx-err').count()==1,f'un error se explica y se marca dónde está ({pg.inner_text(".wg-prev")})')
        ta.fill('');pg.select_option('[data-fn]','REDONDEAR');pg.wait_for_timeout(200)
        ok(ta.input_value()=='REDONDEAR()','«Insertar función» pone la función con sus paréntesis')
        ta.fill('{Vendido del mes} - {Costos fijos}');pg.select_option('select[name=formato]','dinero');pg.wait_for_timeout(500)
        pg.click('.wg-fx [type=submit]');pg.wait_for_selector('[data-wg-nuevo]');pg.wait_for_timeout(500)
        items=pg.evaluate("[...document.querySelectorAll('.wg-item b')].map(x=>x.textContent)")
        ok(items==['Prueba vendido','Prueba UI margen'],f'la fórmula nueva vuelve al editor de tableros ya agregada, sin perder lo anterior ({items})')
        pg.click('.wg-item[data-i="1"] [data-mv="-1"]');pg.wait_for_timeout(200)
        ok(pg.evaluate("[...document.querySelectorAll('.wg-item b')].map(x=>x.textContent)")[0]=='Prueba UI margen','se puede reordenar')
        pg.click('[data-wg-guardar]');pg.wait_for_timeout(5000)
        cel=pg.evaluate("[...document.querySelectorAll('section.wg .wg-cel')].map(c=>[c.querySelector('.ag-resumen-t').textContent,c.querySelector('.ag-resumen-v').textContent,c.innerText])")
        ok(len(cel)==2 and cel[0][0]=='Prueba UI margen' and '709,50' in cel[0][1],f'el tablero muestra la fórmula con su valor ({cel[:1]})')
        ok(re.sub(r'[^\d,]','',cel[1][1]).replace(',','.')==f'{vendido:.2f}','el widget de la cifra da lo mismo que el sistema')
        ok(('frente a el mes anterior' in cel[1][2] or 'Sin dato para comparar' in cel[1][2]) and 'En meta' in cel[1][2],'comparación con el mes anterior y semáforo')
        ok(pg.locator('section.wg [data-wg-ed]').count()==2 and pg.locator('section.wg [data-wg-abrir]').inner_text().strip()=='Editar widgets','cada widget lleva «Editar» y el botón pasa a «Editar widgets»')
        pg.click('section.wg [data-wg-ed] >> nth=1');pg.wait_for_selector('.wg-item.is-open')
        ok(pg.locator('.wg-item.is-open b').inner_text()=='Prueba vendido','«Editar» de un widget abre el editor en ese widget')
        pg.click('.modal [data-close] >> nth=-1');pg.wait_for_timeout(400)
        # fórmula en uso no se borra
        pg.click('section.wg [data-wg-abrir]');pg.wait_for_selector('[data-wg-formulas]');pg.click('[data-wg-formulas]');pg.wait_for_selector('.wg-gestor')
        ok('Prueba UI margen' in pg.inner_text('.wg-gestor'),'«Fórmulas y variables» lista las fórmulas')
        pg.fill('.wg-var input[name=nombre]','Prueba UI meta');pg.fill('.wg-var input[name=valor]','1500');pg.click('.wg-var [type=submit]');pg.wait_for_timeout(1500)
        ok('Prueba UI meta' in pg.inner_text('.wg-gestor'),'se crea una variable desde la pantalla')
        pg.click('[data-var-borrar^="Prueba UI meta"]');pg.wait_for_timeout(1500)
        ok('Prueba UI meta' not in pg.inner_text('.wg-gestor'),'y se quita')
        pg.click('[data-fx]');pg.wait_for_selector('[data-borrar]');pg.click('[data-borrar]');pg.wait_for_selector('[data-ok]');pg.click('[data-ok]');pg.wait_for_timeout(1500)
        ok('tablero' in pg.inner_text('.modal .fld-err').lower(),f'una fórmula que usa un tablero no se borra y se explica ({pg.inner_text(".modal .fld-err")})')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        limpiar();C('admin.asocim@agora.test').call('workspace/tableros','PUT',{'modulo':'centro','version':0,'widgets':[{'id':'c1','fuente':'metrica','ref':'mes.vendido','titulo':'','tamano':'ancho','periodo':'modulo','comparar':'','formato':'auto','decimales':2,'semaforo':{'activo':False}},{'id':'c2','fuente':'metrica','ref':'mes.cierres','titulo':'','tamano':'chico','periodo':'actual','comparar':'','formato':'auto','decimales':0,'semaforo':{'activo':False}}]})
        for u,q,m in [('ejecutivo1.asocim@agora.test','ejecutivo','ventas'),('consulta.asocim@agora.test','consulta','centro')]:
            pg=entrar(br,u);pg.goto(B+'/'+m);pg.wait_for_timeout(5500)
            ok(pg.locator('section.wg .wg-cel').count()==2 and pg.locator('section.wg [data-wg-abrir], section.wg [data-wg-ed]').count()==0,f'{q} ve los widgets del equipo ({m}) sin botones para editar')
            pg.close()
        pg=entrar(br,'admin.asocim@agora.test',390,844);pg.goto(B+'/ventas');pg.wait_for_timeout(5000)
        r=pg.evaluate("(()=>{const s=document.querySelector('section.wg');const b=s.getBoundingClientRect();return [Math.round(b.right),document.documentElement.scrollWidth,s.querySelectorAll('.wg-cel').length]})()")
        ok(r[2]==2 and r[0]<=390 and r[1]<=390,f'teléfono 390: los widgets caben sin desborde {r}')
        pg.click('section.wg [data-wg-abrir]');pg.wait_for_selector('.wg-item');pg.click('.wg-item [data-cfg] >> nth=0');pg.wait_for_timeout(300)
        ok(pg.evaluate("document.documentElement.scrollWidth<=390 && document.querySelector('.modal').getBoundingClientRect().right<=391"),'teléfono 390: el editor cabe sin desborde')
        pg.screenshot(path='/tmp/wg-ui-390.png');pg.close()
    finally:
        limpiar_datos();br.close()
ok(not [f for f in C('admin.asocim@agora.test').call('workspace/formulas')[1]['formulas'] if f['nombre'].startswith('Prueba UI')],'queda todo limpio')
print(n,'comprobaciones')
