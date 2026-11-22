#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.1 · Pedido del usuario: los widgets van dentro de los tableros, ordenables junto con las tarjetas, en todos
los módulos y submódulos. Cada uno tiene un solo «Editar Dashboard» y desde ahí se llega a los widgets.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-2181.py"""
import os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
RUTAS={'bandeja':'/bandeja','contactos':'/contactos','convenios':'/contratos','conConvenio':'/con-convenio','registro':'/registro','libroVentas':'/libro-ventas','datosFiscales':'/datos-fiscales','tasasBcv':'/tasas','inicio':'/inicio','comercial':'/comercial','oportunidades':'/oportunidades','tareas':'/tareas','equipo':'/equipo','publicidad':'/publicidad','ventas':'/ventas','facturacion':'/cobros','costos':'/costos','catalogo':'/catalogo','tendencias':'/tendencias','calidad':'/calidad','facturas':'/facturas','redes':'/redes','productos':'/productos'}
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg=entrar(br,'admin.asocim@agora.test')
    for m,r in RUTAS.items():
        pg.goto(B+r);pg.wait_for_timeout(4000)
        nb=pg.get_by_role('button',name='Editar Dashboard').count()
        ok_=''
        if nb:
            pg.get_by_role('button',name='Editar Dashboard').first.click();pg.wait_for_timeout(800)
            ok_='dash' if pg.locator('.wg-dash').count() else ('editor' if pg.locator('[data-wg-buscar]').count() else 'nada')
            pg.keyboard.press('Escape');pg.wait_for_timeout(300)
        ok(nb==1 and ok_ in ('dash','editor'),f'{m}: un solo «Editar Dashboard» y desde ahí se llega a los widgets ({ok_})')
    ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}');br.close()
print(n,'comprobaciones')
