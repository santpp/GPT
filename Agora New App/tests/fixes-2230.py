#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.23.0 · «Enviar reporte» en el propio mensaje de error (pedido del usuario).
Cuando sale un mensaje de error de una falla registrada (con código), al lado aparece «Enviar reporte»: con un toque se manda el
reporte con la pantalla, la versión y los últimos errores (código y referencia), sin escribir nada.
Casos: error de un formulario, pantalla que no carga, aviso flotante, Bandeja; y que NO sale en validaciones ni en la entrada.
Requiere la API local en :8090 y la cuenta simulada. Borra los reportes y personas de prueba. Uso: python3 tests/fixes-2230.py"""
import os,json,subprocess
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','-r','agora_erp','-e',q],capture_output=True,text=True).stdout.strip()
inicio=int(sql('select coalesce(max(id),0) from agora_error_reports') or 0)
def reportes():return [json.loads(x) for x in sql(f"select json_object('id',id,'d',descripcion,'e',errores,'p',pantalla,'v',version_app) from agora_error_reports where id>{inicio} order by id").splitlines() if x]
def falla500(route):route.fulfill(status=500,content_type='application/json',body=json.dumps({'error':'Falla de prueba del servidor','codigo':'AG-SIS-500','requestId':'prueba2230'}))
limpiar();adm=C('admin.asocim@agora.test')
st,r=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Pendiente Prueba','correo':'pend.inv2230@agora.test','rol':'EJECUTIVO'});pend=r.get('id')
SHOT=os.environ.get('SHOT','/tmp')
try:
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w,h in ((1280,800),(390,844)):
                antes=len(reportes())
                pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/usuarios');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('[data-usuario-nuevo]',timeout=15000)
                # 1. Validación de la pantalla (sin falla detrás): sin botón
                pg.click('[data-usuario-nuevo]');pg.wait_for_selector('#layer form');pg.click('#layer [type=submit]');pg.wait_for_timeout(700)
                ok(pg.inner_text('#layer .fld-err').strip()!='' and pg.locator('#layer [data-rp-auto]').count()==0,f'{w}px: un aviso de validación (sin falla) no lleva «Enviar reporte»')
                # 2. Error del servidor en un formulario: con botón
                pg.fill('#layer input[name=nombre]','Repetida Prueba');pg.fill('#layer input[name=correo]','admin.asocim@agora.test')
                if pg.locator('#layer input[name=enCuenta]').count():pg.uncheck('#layer input[name=enCuenta]')
                pg.click('#layer [type=submit]');pg.wait_for_selector('#layer .fld-err [data-rp-auto]',timeout=8000)
                ok('Ya hay una persona con ese correo' in pg.inner_text('#layer .fld-err'),f'{w}px: el error del servidor sale en el formulario con «Enviar reporte» al lado')
                if w==1280:pg.locator('#layer .modal, #layer [role=dialog]').first.screenshot(path=SHOT+'/rp-form.png')
                pg.click('#layer [data-rp-auto]');pg.wait_for_selector('#layer .fld-err .rp-ok',timeout=8000)
                t=pg.inner_text('#layer .fld-err .rp-ok');rs=reportes()
                ok(t.startswith('Reporte enviado · R-') and len(rs)==antes+1,f'{w}px: con un toque se envía y dice su referencia ({t})')
                ult=rs[-1];errs=json.loads(ult['e']) if isinstance(ult['e'],str) else ult['e']
                ok('Ya hay una persona con ese correo' in ult['d'] and ult['d'].startswith('Enviado desde el mensaje de error') and ult['p']=='/usuarios' and ult['v'],f'{w}px: el reporte lleva el mensaje, la pantalla y la versión')
                ok(any(e.get('codigo','').startswith('AG-USR-409') for e in errs),f'{w}px: y el error con su código ({[e.get("codigo") for e in errs][-2:]})')
                pg.keyboard.press('Escape');pg.wait_for_timeout(300)
                if pg.locator('#layer [data-close]').count():pg.locator('#layer [data-close]').first.click();pg.wait_for_timeout(300)
                # 3. Aviso flotante (toast) de una acción que falla
                pg.route('**/api/workspace/usuarios/invitacion',falla500)
                pg.locator(f'[data-usuario="{pend}"]').first.click();pg.wait_for_selector('[data-u="invitar"]');pg.click('[data-u="invitar"]')
                pg.wait_for_selector('#toast [data-rp-auto]',timeout=8000)
                ok('Falla de prueba del servidor' in pg.inner_text('#toast'),f'{w}px: el aviso flotante del error trae «Enviar reporte»')
                if w==390:pg.screenshot(path=SHOT+'/rp-toast.png')
                pg.click('#toast [data-rp-auto]');pg.wait_for_selector('#toast .rp-ok',timeout=8000)
                ok(len(reportes())==antes+2,f'{w}px: desde el aviso flotante también se envía')
                pg.unroute('**/api/workspace/usuarios/invitacion')
                # 4. Pantalla que no carga
                pg.route('**/api/workspace/tareas*',falla500);pg.goto(B+'/tareas');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                pg.wait_for_selector('[data-rp-auto]',timeout=12000)
                ok(pg.locator('main#app [data-rp-auto]').count()>=1,f'{w}px: «No se pudo cargar» trae «Enviar reporte»')
                if w==1280:pg.locator('main#app').screenshot(path=SHOT+'/rp-pantalla.png')
                pg.unroute('**/api/workspace/tareas*')
                # 5. Bandeja (pantallas de Mobile)
                pg.route('**/api/workspace/inbox*',falla500);pg.goto(B+'/bandeja');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                try:pg.wait_for_selector('main#app [data-rp-auto]',timeout=15000);hay=True
                except Exception:hay=False
                ok(hay,f'{w}px: en la Bandeja, el error de carga trae «Enviar reporte»')
                pg.unroute('**/api/workspace/inbox*')
                ok(not pg.errs and pg.evaluate('document.documentElement.scrollWidth')<=w,f'{w}px: sin desborde ni errores de pantalla {pg.errs[:2]}')
                pg.close()
            # 6. En la entrada (sin sesión), una clave equivocada no lleva botón
            limpiar();pg=br.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
            pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill('Clave-Equivocada-1');pg.keyboard.press('Enter');pg.wait_for_timeout(2500)
            ok(pg.locator('[data-rp-auto]').count()==0,'en la entrada, una clave equivocada no lleva «Enviar reporte»')
            pg.close()
        finally:br.close()
finally:
    limpiar();sql(f"delete from agora_error_reports where id>{inicio};delete from agora_users where email like '%.inv2230@agora.test'")
print(n,'comprobaciones')
