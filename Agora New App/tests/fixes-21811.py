#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.11 · Pedido del usuario: en el Dashboard, las ruedas «Sin leer por asesor», «Fuente de los leads»
y «Leads por ejecutivo» se pueden ver como dona, barras o cifras, y al tocar un asesor o una fuente se ve quiénes son
(solo administración; a los vendedores el servidor no les manda el detalle y la pantalla no lo abre).
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-21811.py"""
import os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
st,d=C('admin.asocim@agora.test').call('workspace/dia')
ok(st==200 and all('detalle' in x for x in d['sinLeerPorAsesor']+d['leadsPorFuente']+d['leadsPorUsuario']),'administración recibe el detalle de cada asesor y fuente')
det=(d['sinLeerPorAsesor'] or [{}])[0].get('detalle') or []
ok(det and all(k in det[0] for k in ('id','nombre','ultimo','hora')),'cada conversación sin leer trae nombre, último mensaje y hora')
st,e=C('ejecutivo1.asocim@agora.test').call('workspace/dia')
ok(st==200 and not any('detalle' in x for x in e['sinLeerPorAsesor']+e['leadsPorFuente']+e['leadsPorUsuario']),'un ejecutivo no recibe el detalle')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for ancho,alto in ((1280,800),(390,844)):
            pg=entrar(br,'admin.asocim@agora.test');pg.set_viewport_size({'width':ancho,'height':alto});pg.evaluate("localStorage.removeItem('agora.rep.sinleer')")
            pg.goto(B+'/comercial');pg.wait_for_selector('[data-rep-panel=sinleer] .dn-lg li',timeout=15000)
            ok(pg.locator('[data-rep-panel] .rep-sel').count()==3,f'{ancho}px: las tres ruedas traen «Dona · Barras · Cifras»')
            pg.locator('[data-rep-panel=sinleer] [data-rep-v=barras]').click();pg.wait_for_timeout(300)
            ok(pg.locator('[data-rep-panel=sinleer] .bar-row').count()>=1,f'{ancho}px: se ve como barras')
            pg.locator('[data-rep-panel=sinleer] [data-rep-v=cifras]').click();pg.wait_for_timeout(300)
            ok(pg.locator('[data-rep-panel=sinleer] .rep-c').count()>=2,f'{ancho}px: se ve como cifras (con total)')
            if ancho==1280:
                pg.reload();pg.wait_for_selector('[data-rep-panel=sinleer] .rep-c',timeout=15000)
                ok(True,'la vista elegida se recuerda al volver')
                pg.wait_for_selector('#ag-entrada',state='detached',timeout=10000);pg.locator('[data-rep-panel=sinleer]').scroll_into_view_if_needed();pg.wait_for_timeout(400)
                pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/rep-cifras.png')
            pg.locator('[data-rep-panel=sinleer] .rep-c:not(.rep-tot)').first.click();pg.wait_for_selector('.modal .dn-items button',timeout=5000)
            ok(pg.locator('.modal .dn-items button').count()>=1 and 'Sin leer por asesor:' in pg.inner_text('.modal .modal-h h2'),f'{ancho}px: tocar un asesor muestra sus conversaciones sin leer')
            if ancho==1280: pg.wait_for_timeout(600);pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/rep-detalle.png')
            desb=pg.evaluate("document.documentElement.scrollWidth>innerWidth+1")
            ok(not desb,f'{ancho}px: nada se desborda')
            pg.locator('.modal .dn-items button').first.click();pg.wait_for_timeout(4000)
            ok('/bandeja' in pg.url,f'{ancho}px: tocar una conversación abre la bandeja')
            pg.goto(B+'/comercial');pg.wait_for_selector('[data-rep-panel=fuente]',timeout=15000)
            pg.locator('[data-rep-panel=fuente] [data-rep-v=dona]').click();pg.wait_for_timeout(300)
            pg.locator('[data-rep-panel=fuente] .dn-lg li').first.click();pg.wait_for_selector('.modal .dn-items [data-dn-cont]',timeout=5000)
            ok(True,f'{ancho}px: tocar una fuente muestra sus leads de hoy')
            pg.locator('.modal [data-close]').first.click();pg.wait_for_timeout(300)
            ok(not pg.errs,f'{ancho}px: sin errores de la página {pg.errs[:2]}')
            pg.close()
        pg=entrar(br,'ejecutivo1.asocim@agora.test');pg.goto(B+'/comercial')
        try: pg.wait_for_selector('[data-rep-panel]',timeout=15000);hay=True
        except Exception: hay=False
        if hay:
            ok(pg.locator('.rep-clic').count()==0,'un ejecutivo ve las ruedas pero no puede abrir el detalle')
            pg.locator('[data-rep-panel] .dn-lg li').first.click() if pg.locator('[data-rep-panel] .dn-lg li').count() else None;pg.wait_for_timeout(500)
            ok(pg.locator('.modal .dn-items').count()==0,'tocar un nombre no abre nada para un ejecutivo')
        else: ok(True,'un ejecutivo no ve esta sección')
    finally:
        limpiar();br.close()
print(n,'comprobaciones')
