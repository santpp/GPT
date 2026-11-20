#!/usr/bin/env python3
"""Agora New App 2.12.1 · Pedidos del usuario (20 nov, tarde):
1 Formulario de factura: «Moneda y tasa» antes de los productos (sin el campo fijo «Moneda: Bolívares»), tasa con todos sus decimales,
  el equivalente en Bs de cada línea y un total que cuadra a la vista (base + IVA = total) con lo que calcula el servidor.
2 Todos los tableros con 4 cifras por fila en el ordenador: Tablero comercial y «Resumen de hoy» como los demás.
Requiere la API local en :8090 y haber corrido tests/fixes-2120.py (tasas del día). Uso: python3 tests/fixes-2121.py"""
import sys,subprocess,re
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
num=lambda t:float(re.sub(r'[^\d,]','',t).replace(',','.'))
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for vp in ({'width':1280,'height':900},{'width':390,'height':844}):
        q='escritorio' if vp['width']>600 else 'teléfono'
        pg,errs=entrar(br,vp)
        pg.goto(B+'/facturas');pg.wait_for_timeout(3500);pg.click('[data-fc-nueva]');pg.wait_for_selector('.fc-form')
        orden=pg.evaluate("[...document.querySelectorAll('.fc-form legend')].map(l=>l.innerText.trim())")
        ok(orden.index('Moneda y tasa')<orden.index('Productos o servicios'),f'{q}: «Moneda y tasa» va antes de los productos {orden}')
        ok('Bolívares (Bs.)' not in pg.evaluate("[...document.querySelectorAll('.fc-form input[disabled]')].map(i=>i.value).join()"),f'{q}: ya no está el campo fijo «Moneda: Bolívares»')
        pg.select_option('.fc-form [name=refmon]','USD');pg.fill('.fc-linea [data-k=descripcion]','Extensión');pg.fill('.fc-linea [data-k=precio]','55');pg.wait_for_timeout(400)
        eq=pg.inner_text('[data-fc-eq]');tot=num(pg.inner_text('[data-total]'));des=pg.inner_text('[data-desglose]')
        if vp['width']>600:
            pg.fill('.fc-form [name=nombre]','Cliente Cuadre');pg.locator('.fc-form [name=tipo][value=SIM]').check() if pg.locator('.fc-form [name=tipo][value=SIM]').count() else None
            with pg.expect_response(lambda r:'/api/workspace/facturas' in r.url and r.request.method=='POST') as rr:pg.click('.fc-form [data-modo=borrador]')
            dd=rr.value.json();srv=dd.get('totales',{}).get('total')
            ok(srv is not None and abs(srv-tot)<0.005,f'el total que guarda el servidor es el mismo que se ve ({srv} = {tot})')
            subprocess.run(['true']);pg.wait_for_timeout(800)
            pg.goto(B+'/facturas');pg.wait_for_timeout(3000);pg.click('[data-fc-nueva]');pg.wait_for_selector('.fc-form')
            pg.select_option('.fc-form [name=refmon]','USD');pg.fill('.fc-linea [data-k=descripcion]','Extensión');pg.fill('.fc-linea [data-k=precio]','55');pg.wait_for_timeout(400)
        b,i=[num(x) for x in re.findall(r'Bs\. ([\d.,]+)',des)[:2]]
        ok('Precio (US$)' in pg.inner_text('.fc-linea') and '= Bs.' in eq,f'{q}: cada línea dice su moneda y su equivalente en Bs ({eq})')
        ok(abs(b+i-tot)<0.005,f'{q}: base + IVA = total ({b} + {i} = {tot})')
        ok('1 US$ = Bs.' in pg.inner_text('[data-fc-tasa-txt]'),f'{q}: la tasa se explica con todos sus decimales')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{q}: el formulario no se desborda')
        pg.keyboard.press('Escape');pg.wait_for_timeout(300)
        pg.goto(B+'/comercial');pg.wait_for_timeout(6500)
        cols=pg.evaluate("[...new Set([...document.querySelectorAll('#view .g-hero')[0].querySelectorAll('.hero,.micro')].map(e=>Math.round(e.getBoundingClientRect().left)))].length")
        ok(cols==(4 if vp['width']>600 else cols),f'{q}: el Tablero comercial pone 4 por fila ({cols})')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{q}: el Tablero no se desborda')
        if vp['width']>600:
            pg.goto(B+'/centro');pg.wait_for_timeout(4000)
            g=pg.evaluate("getComputedStyle(document.querySelector('.tb-inicio .inicio-resumen-g')).gridTemplateColumns.split(' ').length")
            ok(g==4,f'«Resumen de hoy» con 4 columnas ({g})')
        ok(not errs,f'{q}: sin errores {errs}');pg.context.close()
    br.close()
print(n,'comprobaciones')
