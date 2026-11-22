#!/usr/bin/env python3
"""Agora New App 2.18.10 · Pedido del usuario: el «Editar» de cada tarjeta edita SOLO esa tarjeta.
Tarjetas del sistema: mostrar u ocultar y su lugar (Al principio, Subir, Bajar, Al final). Widgets: solo la configuración de ese widget.
Y en el editor de widgets ya no se copia de otro módulo: se ve la lista de cifras predeterminadas.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-21810.py"""
import os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
a=limpiar_datos()
W={'id':'solo0001','tipo':'cifra','fuente':'metrica','ref':'mes.vendido','titulo':'Widget de prueba','nota':'','tamano':'chico','periodo':'modulo','comparar':'','formato':'auto','decimales':2,'semaforo':{'activo':False}}
ok(a.call('workspace/tableros','PUT',{'modulo':'ventas','version':0,'widgets':[W,dict(W,id='solo0002',titulo='Otro widget',ref='mes.cierres')]})[0]==200,'tablero con dos widgets')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test');pg.goto(B+'/ventas');pg.wait_for_timeout(6000)
        cel=pg.locator('main#app .cel:not([data-wgc])').nth(2);nombre=cel.locator('h3, .ag-resumen-t, .t-label').first.inner_text().strip()
        cel.locator('[data-cel-ed]').click();pg.wait_for_selector('.modal.tarj-una .pz-foco',timeout=8000)
        vis=pg.evaluate("[...document.querySelectorAll('.modal .tarj-ed .pz-item')].filter(x=>x.offsetParent).length")
        ok(vis==1 and pg.inner_text('.modal .modal-h h2').startswith('Editar «'),f'«Editar» de «{nombre}» muestra solo esa tarjeta ({vis} a la vista)')
        ok(pg.locator('.tarj-una-acc [data-una]').count()==4,'trae «Al principio», «Subir», «Bajar» y «Al final»')
        pg.click('.tarj-una-acc [data-una="-99"]');pg.wait_for_timeout(300)
        pg.locator('.modal [data-tarj-ok], .modal [data-tg-ok]').first.click();pg.wait_for_timeout(5000)
        prim=pg.evaluate("(()=>{const g=document.querySelector('main#app .cel').parentElement;const cs=[...g.children].filter(c=>c.classList.contains('cel')&&getComputedStyle(c).display!=='none').sort((a,b)=>(+getComputedStyle(a).order)-(+getComputedStyle(b).order)||0);return cs.map(c=>(c.querySelector('h3, .ag-resumen-t, .t-label')?.textContent||'').trim())})()")
        ok(prim and prim[0]==nombre,f'«Al principio» la deja primera en el tablero {prim[:3]}')
        pg.locator('.wg-cel[data-wgc]',has_text='Widget de prueba').locator('[data-wg-ed]').click();pg.wait_for_selector('.wg-ed.wg-solo',timeout=8000)
        vis=pg.evaluate("[...document.querySelectorAll('.wg-solo .wg-item')].filter(x=>x.offsetParent).length")
        ok(vis==1 and 'Widget de prueba' in pg.inner_text('.modal .modal-h h2') and pg.locator('.wg-solo [data-k=tipo]').count()==1,'«Editar» de un widget abre solo ese widget, con su configuración')
        pg.fill('.wg-solo .wg-item.is-open [data-k=titulo]','Widget cambiado');pg.click('[data-wg-guardar]');pg.wait_for_timeout(5000)
        t=[w['titulo'] for w in a.call('workspace/tableros?modulo=ventas')[1]['widgets']]
        ok(t==['Widget cambiado','Otro widget'],f'se guarda el cambio y el otro widget queda igual {t}')
        pg.get_by_role('button',name='Editar Dashboard').first.click();pg.wait_for_timeout(700);pg.click('[data-wg-desde-dash]');pg.wait_for_selector('[data-wg-buscar]')
        ok(pg.locator('[data-wg-copiar]').count()==0 and pg.locator('[data-wg-pre]').count()>=30,'el editor de widgets ya no copia de otro módulo: muestra la lista de cifras predeterminadas')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
    finally:
        limpiar_datos();limpiar();C('admin.asocim@agora.test').call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':None,'equipo':False});br.close()
print(n,'comprobaciones')
