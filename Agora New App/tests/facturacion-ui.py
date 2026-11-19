#!/usr/bin/env python3
"""Agora New App 2.11.0 · Facturación (pantallas).
1 El menú tiene la categoría «Facturación» (Cobros, Facturas, Libro de ventas, Datos fiscales, Tasas del día) y «Ventas» ya no dice «y facturación».
2 Sin datos fiscales (subcuenta 1): factura simple desde el teléfono, emitida e impresa «sin validez fiscal», sin número de control.
3 Con datos fiscales (ASOCIM): «Facturar» desde un cobro abre la factura con el cliente y el monto; se emite fiscal y el cobro muestra su número.
4 Roles: la ejecutiva factura pero no ve Libro ni Datos fiscales; la consulta no ve «Nueva factura». Sin desbordes ni errores.
Requiere la API local en :8090 y haber corrido tests/facturacion.py (deja a ASOCIM con datos fiscales y tasa del día). Uso: python3 tests/facturacion-ui.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def entrar(p,u,pw,vp,ws=None):
    limpiar()
    pg=p.new_context(viewport=vp,service_workers='block').new_page();errs=[]
    pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.on('response',lambda r:errs.append(f'{r.status} {r.url}') if r.status>=400 and '/api/' in r.url and not any(x in r.url for x in ['/api/yo','/api/me','/api/ui/texts']) else None)
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(pw);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    if ws:pg.evaluate("async id=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:id})})}",ws)
    return pg,errs
txt=lambda pg:pg.evaluate("document.querySelector('main').innerText")
P='Agora-Pruebas-2026!'
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    # ---- 1 y 2: dueña, subcuenta sin datos fiscales, teléfono
    pg,errs=entrar(br,'duena@example.test','Clave-Local-2026!',{'width':390,'height':844},1)
    pg.goto(B+'/modulos/facturacion');pg.wait_for_timeout(3500);t=txt(pg)
    ok(all(x in t for x in ['Cobros','Facturas','Libro de ventas','Datos fiscales','Tasas del día']),'«Facturación» es una categoría con Cobros, Facturas, Libro de ventas, Datos fiscales y Tasas del día')
    pg.goto(B+'/modulos/ventas');pg.wait_for_timeout(3000);ok('Ventas y facturación' not in pg.evaluate('document.body.innerText'),'«Ventas y facturación» pasa a llamarse «Ventas»')
    pg.goto(B+'/facturacion');pg.wait_for_timeout(3500);ok('Cobros' in txt(pg),'la dirección vieja /facturacion abre Cobros')
    pg.goto(B+'/facturas');pg.wait_for_timeout(3500);t=txt(pg)
    ok('todavía no tiene datos fiscales' in t,'sin datos fiscales, Facturas avisa que solo emite facturas simples')
    pg.click('[data-fc-nueva]');pg.wait_for_selector('.fc-form')
    ok(pg.locator('.fc-form [name=tipo]').count()==0,'sin datos fiscales no se ofrece la factura fiscal')
    pg.click('.fc-form [data-modo=emitir]');pg.wait_for_timeout(500);ok('nombre' in pg.inner_text('.fc-form .fld-err'),'el formulario pide el nombre del cliente antes de enviar')
    pg.fill('.fc-form [name=nombre]','María Gómez');pg.fill('.fc-linea [data-k=descripcion]','Mensualidad');pg.fill('.fc-linea [data-k=precio]','40,50')
    ok('40,50' in pg.inner_text('[data-total]'),'el total se ve mientras se escribe')
    ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'en el teléfono el editor no se desborda')
    pg.click('.fc-form [data-modo=emitir]');pg.wait_for_selector('.fc-ver',timeout=10000)
    v=pg.inner_text('.fc-ver');ok('Emitida' in v and 'Falta control' not in v and 'número de control' not in v,'la factura simple se emite sin pedir número de control')
    pg.click('[data-a=imprimir]');pg.wait_for_selector('.fc-print iframe');pg.wait_for_timeout(800)
    imp=pg.frame_locator('.fc-print iframe').locator('body').inner_text()
    ok('FACTURA SIMPLE' in imp.upper() and 'sin validez fiscal' in imp.lower() and 'RIF / C.I.' not in imp,'la impresión dice «sin validez fiscal» y no muestra RIF vacío')
    ok(not errs,f'sin errores en el teléfono {errs}');pg.context.close()
    # ---- 3: ASOCIM con datos fiscales, «Facturar» desde un cobro
    pg,errs=entrar(br,'admin.asocim@agora.test',P,{'width':1280,'height':900})
    pg.goto(B+'/cobros');pg.wait_for_timeout(5000)
    btn=pg.locator('[data-fa-facturar]').first
    ok(btn.count()==1,'Cobros ofrece «Facturar» en cada cobro sin factura')
    venta=btn.get_attribute('data-fa-facturar');cuota=btn.get_attribute('data-n')
    btn.click();pg.wait_for_selector('.fc-form')
    ok(pg.input_value('.fc-form [name=nombre]')!='' and pg.input_value('.fc-linea [data-k=precio]')!='','la factura abre con el cliente y el monto del cobro')
    ok(pg.locator('.fc-form [name=tipo][value=FAC]').is_checked(),'con datos fiscales, la factura fiscal viene elegida')
    pg.fill('.fc-form [name=rif]','V-12345678');pg.fill('.fc-form [name=direccion]','Caracas')
    pg.click('.fc-form [data-modo=emitir]');pg.wait_for_selector('.fc-ver',timeout=10000)
    v=pg.inner_text('.fc-ver');ok('Falta control' in v and 'Bs.' in v,'se emite fiscal en bolívares: queda pendiente el número de control')
    pg.keyboard.press('Escape');pg.goto(B+'/cobros');pg.wait_for_timeout(5000)
    ok(pg.locator(f'[data-fa-facturar="{venta}"][data-n="{cuota}"]').count()==0 and pg.locator('[data-fc-ver]').count()>=1,'el cobro facturado muestra su factura en vez de «Facturar»')
    pg.goto(B+'/facturas');pg.wait_for_timeout(4000);t=txt(pg)
    ok('IVA del mes' in t and 'Sin número de control' in t,'Facturas mide lo facturado, el IVA y lo que falta por número de control')
    pg.goto(B+'/libro-ventas');pg.wait_for_timeout(4000);t=txt(pg)
    ok('IVA débito fiscal' in t and 'base general' in t.lower() and 'total_bs' not in t,'el Libro de ventas muestra sus columnas con nombres claros')
    pg.goto(B+'/datos-fiscales');pg.wait_for_timeout(4000);ok('Lista para facturar fiscalmente' in txt(pg),'Datos fiscales confirma que la subcuenta puede facturar')
    pg.click('[data-df-verif]');pg.wait_for_timeout(2500);ok('todo en orden' in txt(pg),'la verificación de la cadena sale en orden')
    ok(not errs,f'sin errores para la administración {errs}');pg.context.close()
    # ---- 4: roles
    pg,errs=entrar(br,'ejecutivo1.asocim@agora.test',P,{'width':390,'height':844})
    pg.goto(B+'/modulos/facturacion');pg.wait_for_timeout(3500);t=txt(pg)
    ok('Facturas' in t and 'Libro de ventas' not in t and 'Datos fiscales' not in t,'la ejecutiva ve Facturas, pero no el Libro de ventas ni los Datos fiscales')
    pg.goto(B+'/facturas');pg.wait_for_timeout(3500);ok(pg.locator('[data-fc-nueva]').count()==1,'la ejecutiva puede crear facturas')
    ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'Facturas no se desborda en el teléfono')
    ok(not errs,f'sin errores para la ejecutiva {errs}');pg.context.close()
    pg,errs=entrar(br,'consulta.asocim@agora.test',P,{'width':1280,'height':900})
    pg.goto(B+'/facturas');pg.wait_for_timeout(3500);ok(pg.locator('[data-fc-nueva]').count()==0,'la consulta ve Facturas sin «Nueva factura»')
    pg.context.close();br.close()
print(n,'comprobaciones')
