#!/usr/bin/env python3
"""Agora New App 2.12.13 · «¿Venta ganada?» como en Ventas (pedido del usuario, 21 nov):
1 Desde la conversación y desde la ficha del contacto (Contactos) el mismo paso: elegir la oportunidad (o una nueva), sin pedir el valor aparte.
2 Al marcarla ganada se abre el formulario de venta de Ventas (Cliente · Producto y cobro · Detalles · Resumen), también con «+ Nueva venta»
  (antes se perdía el id de la oportunidad nueva y no se abría).
3 Sin la ventana suelta «venta ganada sin monto» de la cuenta: en el ERP manda el formulario completo.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-21213.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def entrar(br,vp):
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    return pg,errs
def ganar(pg,tag):
    pg.wait_for_selector('.vgw-form',timeout=15000)
    ok('Valor' not in pg.locator('.modal').first.inner_text(),f'{tag}: el primer paso solo elige la oportunidad (sin pedir el valor aparte)')
    pg.select_option('.vgw-form select[name=op]','new');pg.click('.vgw-form [type=submit]')
    try: pg.wait_for_selector('.modal:has-text("Producto y cobro")',timeout=12000)
    except Exception: pass
    t=' '.join(m.inner_text() for m in pg.locator('.modal').all())
    ok('Producto y cobro' in t and 'Resumen' in t,f'{tag}: al ganarla se abre el formulario de venta de Ventas, también con «+ Nueva venta»')
    pg.keyboard.press('Escape');pg.wait_for_timeout(800)
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg,errs=entrar(br,{'width':1280,'height':900})
    pg.goto(B+'/bandeja');pg.wait_for_timeout(5000)
    ok(pg.locator('.modal:has-text("ganada sin monto"), dialog[open]:has-text("ganada sin monto")').count()==0 and not pg.locator('#won-banner:visible').count(),'sin la ventana suelta «venta ganada sin monto»')
    pg.locator('main :text-is("Camila Reyes"):visible').first.click();pg.wait_for_timeout(3000)
    pg.get_by_role('button',name='¿Venta ganada?').first.click();ganar(pg,'conversación')
    pg.goto(B+'/contactos');pg.wait_for_timeout(4500)
    pg.locator('main .record-row').nth(2).click();pg.wait_for_timeout(3000)
    ok('¿Venta ganada?' in pg.evaluate("[...document.querySelectorAll('.record-actions button')].map(b=>b.textContent.trim())"),'la ficha del contacto tiene «¿Venta ganada?»')
    pg.click('#record-win');ganar(pg,'ficha del contacto')
    ok(not errs,f'sin errores {errs}');pg.context.close()
    pg,errs=entrar(br,{'width':390,'height':844})
    pg.goto(B+'/contactos');pg.wait_for_timeout(4500);pg.locator('main .record-row').first.click();pg.wait_for_timeout(3000)
    ok(pg.locator('#record-win:visible').count()==1 and not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'teléfono: la ficha muestra «¿Venta ganada?» sin desbordes')
    ok(not errs,f'teléfono: sin errores {errs}');br.close()
print(n,'comprobaciones')
