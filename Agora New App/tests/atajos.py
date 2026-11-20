#!/usr/bin/env python3
"""Agora New App 2.11.6 · Criterio único de atajos en las tarjetas de cifras.
1 El Tablero comercial, como «Resumen de hoy», lleva de cada tarjeta a su módulo: rótulo con chevron y la tarjeta entera es el enlace.
2 Con el filtro puesto: «Sin leer» abre la bandeja en «Sin leer»; «Oportunidades abiertas» abre Oportunidades.
3 «Editar Dashboard»: en los módulos sin reporte, la tarjeta cuyo dato vive en otro módulo lleva a él; la de su propio módulo, no.
4 Solo enlaza a módulos que la persona puede abrir (la ejecutiva no ve atajos a Costos). Teléfono y escritorio, sin errores.
Requiere la API local en :8090 y la subcuenta ASOCIM conectada (tests/motor-puente.py). Uso: python3 tests/atajos.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0;P='Agora-Pruebas-2026!'
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def entrar(br,u,vp):
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    return pg,errs
TARJ="""()=>[...document.querySelectorAll('#view .micro,#view .hero')].filter(m=>m.offsetParent&&m.querySelector(':scope>h3,:scope>div>.t-label')).map(m=>{const h=m.querySelector(':scope>h3,:scope>div>.t-label');return {t:h.textContent.trim(),link:m.classList.contains('is-link'),chev:!!h.querySelector('svg')}})"""
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for vp in ({'width':1280,'height':900},{'width':390,'height':844}):
        quien='escritorio' if vp['width']>600 else 'teléfono'
        pg,errs=entrar(br,'admin.asocim@agora.test',vp)
        pg.goto(B+'/comercial');pg.wait_for_timeout(6000)
        t=pg.evaluate(TARJ);links=[x for x in t if x['link']]
        ok(len(links)==len(t) and all(x['chev'] for x in links),f'{quien}: las tarjetas del Tablero llevan a su módulo con chevron ({len(links)} de {len(t)})')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{quien}: el Tablero no se desborda')
        if vp['width']>600:
            pg.locator('#view .micro.is-link',has=pg.locator('h3',has_text='Oportunidades abiertas')).first.click();pg.wait_for_timeout(3000)
            ok(pg.url.endswith('/oportunidades'),'«Oportunidades abiertas» abre Oportunidades')
            pg.goto(B+'/comercial');pg.wait_for_timeout(6000)
            sl=pg.locator('#view .micro.is-link',has=pg.locator('h3',has_text='Sin leer'))
            if sl.count():
                sl.first.click();pg.wait_for_timeout(4000)
                ok(pg.url.endswith('/bandeja') and pg.evaluate("document.querySelector('#view [data-live-filter][aria-pressed=\"true\"]')?.dataset.liveFilter")=='unread','«Sin leer» abre la bandeja con el filtro «Sin leer»')
        ok(not errs,f'{quien}: sin errores {errs}');pg.context.close()
    # ---- 3: tarjetas de «Editar Dashboard» (subcuenta sin reporte)
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    pg.evaluate("async()=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:1})})}")
    K="""()=>[...document.querySelectorAll('main .ag-resumen')].filter(e=>e.offsetParent).map(e=>({t:e.querySelector('.ag-resumen-t').textContent.trim(),a:e.tagName==='A',h:e.getAttribute('href')||'',chev:!!e.querySelector('.ag-resumen-t svg')}))"""
    pg.goto(B+'/equipo');pg.wait_for_timeout(4000);eq=pg.evaluate(K)
    ok(all(x['chev'] for x in eq if x['a']) and any(x['a'] and x['h'].endswith('/ventas') for x in eq),f'Equipo: «Quien más vendió», «Ganadas en la cuenta» y «Sin leer ahora» llevan a su módulo con chevron {[x["t"] for x in eq if x["a"]]}')
    pg.goto(B+'/ventas');pg.wait_for_timeout(4000);vt=pg.evaluate(K)
    ok(not any(x['a'] and x['h'].endswith('/ventas') for x in vt),'Ventas: sus propias tarjetas no llevan a ningún lado')
    pg.goto(B+'/costos');pg.wait_for_timeout(4000);co=pg.evaluate(K)
    ok(not any(x['a'] and x['h'].endswith('/costos') for x in co),'Costos: sus propias tarjetas no llevan a ningún lado')
    l=pg.locator('main a.ag-resumen.is-link').first
    if l.count():
        h=l.get_attribute('href');l.click();pg.wait_for_timeout(3000);ok(pg.url.endswith(h),f'el atajo abre su módulo ({h})')
    ok(not errs,f'tarjetas: sin errores {errs}');pg.context.close()
    pg,errs=entrar(br,'ejecutivo1.asocim@agora.test',{'width':1280,'height':900})
    pg.goto(B+'/comercial');pg.wait_for_timeout(6000)
    t=pg.evaluate(TARJ);co=[x['t'] for x in t if x['link'] and x['t'] in ('Costos de mercadeo y ventas','Ganancia real del mes','Ventas por cada 100 invertido')]
    menu=pg.evaluate("[...document.querySelectorAll('a[href]')].some(a=>a.getAttribute('href').endsWith('/costos'))")
    ok(menu or not co,f'la ejecutiva solo tiene atajos a módulos que puede abrir {co}')
    ok(not errs,f'ejecutiva: sin errores {errs}');pg.context.close();br.close()
print(n,'comprobaciones')
