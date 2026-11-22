#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.0 · Lo que reportó el usuario:
[1] «Correo de Ágora» no listaba la subcuenta: solo salían las conexiones ya probadas. Ahora salen también las guardadas sin probar
    y se prueban al guardar.
[2] Los widgets se crean y editan desde «Editar Dashboard» de cada módulo (no con un botón suelto ni en Inicio).
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-218.py"""
import sys,os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',q],capture_output=True,text=True).stdout.strip()
limpiar();d=C('duena@example.test','Clave-Local-2026!')
try:
    sql("update agora_ghl_connections set status='sin_probar' where tenant_id=2")
    st,c=d.call('agency/correo');nombres=[o['nombre'] for o in c.get('opciones',[])]
    ok(st==200 and any(n.startswith('ASOCIM') and 'se prueba al guardar' in n for n in nombres),f'[1] una subcuenta con conexión guardada sin probar aparece en el selector {nombres}')
    st,r=d.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':False})
    ok(st==200 and sql('select status from agora_ghl_connections where tenant_id=2')=='conectada','[1] al guardarla se prueba la conexión y queda conectada')
    st,r=d.call('agency/correo','PUT',{'connectionId':4})
    ok(st==422 and 'no responde' in r.get('error',''),f'[1] una conexión que no responde no se elige y se explica ({r.get("error","")[:60]})')
    ok(d.call('agency/correo','PUT',{'connectionId':999})[0]==422,'[1] una subcuenta sin conexión no se elige')
finally:
    sql("update agora_ghl_connections set status='conectada' where tenant_id=2");d.call('agency/correo','PUT',{'connectionId':None})
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test');pg.goto(B+'/centro');pg.wait_for_timeout(5000)
        ok(pg.locator('section.wg, section.wg-g, [data-wg-abrir], [data-wg-dashbtn]').count()==0,'[2] Inicio no tiene widgets ni botón para crearlos')
        for m in ('ventas','equipo'):
            pg.goto(B+'/'+m);pg.wait_for_timeout(5000)
            ok(pg.get_by_role('button',name='Editar Dashboard').count()==1 and pg.locator('[data-wg-abrir]').count()==0,f'[2] {m}: un solo «Editar Dashboard» y ningún botón suelto de widgets')
            pg.get_by_role('button',name='Editar Dashboard').first.click();pg.wait_for_timeout(800)
            ok(pg.locator('.wg-dash [data-wg-desde-dash]').count()==1 or pg.locator('[data-wg-buscar]').count()==1,f'[2] {m}: desde «Editar Dashboard» se llega a los widgets')
            pg.keyboard.press('Escape');pg.wait_for_timeout(300)
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        limpiar();pg=br.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('ejecutivo1.asocim@agora.test');pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(B+'/equipo');pg.wait_for_timeout(4000)
        ok(pg.locator('[data-wg-dashbtn]').count()==0,'[2] quien no edita no ve el botón de widgets')
    finally:
        br.close()
print(n,'comprobaciones')
