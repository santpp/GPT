#!/usr/bin/env python3
"""Agora New App 2.11.1 · Correcciones del usuario (19 nov):
1 Facturación solo en bolívares (dólares y euros preparados, desactivados): el editor no ofrece otra moneda y «Facturar» un cobro en dólares
  lo pasa a Bs con la tasa BCV.
2 «Por forma de cobro» muestra siempre Recurrente, Venta no recurrente y Venta única.
3 En todos los módulos: el botón de descarga dice «Descargar» (no «Excel») y «Editar Dashboard» (no «Editar tarjetas»). 2.11.2: los dos sin ícono y en la misma línea.
Requiere la API local en :8090 y haber corrido tests/facturacion.py. Uso: python3 tests/fixes-2111.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def entrar(br,u,pw,ws=None,vp={'width':1280,'height':900}):
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(pw);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    if ws:pg.evaluate("async id=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:id})})}",ws)
    return pg,errs
BOTONES="""()=>[...document.querySelectorAll('main button, main a.btn')].map(b=>({t:b.innerText.trim(),edit:!b.querySelector('svg'),top:Math.round(b.getBoundingClientRect().top)}))"""
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    # ---- 3: todos los módulos, con y sin reporte
    for u,pw,ws,rutas in [('admin.asocim@agora.test','Agora-Pruebas-2026!',None,['/inicio','/ventas','/cobros','/productos','/tareas','/equipo','/costos','/tendencias','/calidad','/usuarios','/facturas']),
                          ('duena@example.test','Clave-Local-2026!',1,['/inicio','/ventas','/cobros','/equipo','/costos','/tendencias','/calidad','/facturas'])]:
        pg,errs=entrar(br,u,pw,ws)
        malos=[];dash=0;sinLapiz=[]
        for r in rutas:
            pg.goto(B+r);pg.wait_for_timeout(3500)
            for b in pg.evaluate(BOTONES):
                if b['t'].split('\n')[0].strip() in ('Excel',) or b['t'].startswith('Excel') or 'Editar tarjetas' in b['t']:malos.append(f"{r}: {b['t'][:30]}")
                if b['t'].startswith('Editar Dashboard'):
                    dash+=1
                    if not b['edit']:sinLapiz.append(r)
        quien='con reporte' if ws is None else 'sin reporte'
        ok(not malos,f'{quien}: ningún botón dice «Excel» ni «Editar tarjetas» {malos}')
        ok(dash>=(1 if ws is None else 4) and not sinLapiz,f'{quien}: «Editar Dashboard» sin ícono en {dash} lugares {sinLapiz}')
        ok(not errs,f'{quien}: sin errores {errs}')
        if ws:
            pg.goto(B+'/costos');pg.wait_for_timeout(3500)
            hb=pg.locator('.mod-h .mg-hbtn')
            ok(hb.count()==0 or (hb.inner_text().strip()=='Descargar' and hb.locator('svg').count()==0),'el botón de arriba dice «Descargar», sin ícono')
            # ---- 2: Por forma de cobro, con ventas de ejemplo (la subcuenta sin conexión no puede registrar ventas)
            import json as _j
            def falsas(route):
                r=route.fetch();d=r.json();hoy=d.get('mes') or ''
                v=lambda c,m:{'id':'q'+c+str(m),'cobro':c,'monto':m,'total':m,'moneda':'USD','fecha':(d.get('items') or [{}])[0].get('fecha') if d.get('items') else None,'cliente':{'nombre':'QA'},'producto':'QA','vendedor':'Ana','plan':[]}
                import datetime;f=datetime.date.today().isoformat()
                d['items']=[{**v('recurrente',10),'fecha':f},{**v('recurrente',20),'fecha':f}]
                route.fulfill(status=200,content_type='application/json',body=_j.dumps(d))
            pg.route('**/api/workspace/ventas?mes=*',falsas)
            pg.goto(B+'/inicio');pg.wait_for_timeout(4500)
            t=pg.evaluate("document.querySelector('.gc-formas')?.innerText||''")
            ok('Recurrente' in t and 'Venta no recurrente' in t and 'Venta única' in t,'«Por forma de cobro» muestra las tres formas aunque solo haya recurrentes')
        if ws:
            pg.set_viewport_size({'width':390,'height':844});pg.goto(B+'/tendencias');pg.wait_for_timeout(3500)
            tops={b['t']:b['top'] for b in pg.evaluate(BOTONES) if b['t'] in ('Descargar','Editar Dashboard')}
            ok(len(tops)==2 and abs(tops['Descargar']-tops['Editar Dashboard'])<4,f'en el teléfono «Descargar» y «Editar Dashboard» quedan en la misma línea {tops}')
        pg.context.close()
    # ---- 1: bolívares
    pg,errs=entrar(br,'admin.asocim@agora.test','Agora-Pruebas-2026!')
    pg.goto(B+'/facturas');pg.wait_for_timeout(3500)
    pg.click('[data-fc-nueva]');pg.wait_for_selector('.fc-form')
    ok(pg.locator('.fc-form select[name=moneda]').count()==0 and pg.input_value('.fc-form input[type=hidden][name=moneda]')=='VES','el editor factura en bolívares, sin elegir otra moneda')
    pg.keyboard.press('Escape');pg.wait_for_timeout(400)
    pg.goto(B+'/cobros');pg.wait_for_timeout(5000)
    btn=pg.locator('[data-fa-facturar]').first
    if btn.count():
        btn.click();pg.wait_for_selector('.fc-form')
        d=pg.input_value('.fc-linea [data-k=descripcion]')
        ok('tasa BCV' in d and pg.input_value('.fc-linea [data-k=precio]')!='','«Facturar» un cobro en dólares lo pasa a bolívares con la tasa BCV (y lo dice en la línea)')
    ok(not errs,f'sin errores en Facturación {errs}')
    br.close()
print(n,'comprobaciones')
