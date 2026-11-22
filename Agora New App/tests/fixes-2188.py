#!/usr/bin/env python3
"""Agora New App 2.18.8 · Pantalla de entrada (pedido del usuario, idea «Casa Momo» con el logo de Ágora):
aparece al abrir o recargar y al iniciar sesión; el logo entra pieza por pieza; se va cuando la pantalla terminó de cargar
(no antes de 0,9 s, nunca más de 7 s) abriéndose en dos. Inicio no tiene widgets (van en cada módulo).
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-2188.py"""
import os,time
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        limpiar();pg=br.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
        t0=time.time();pg.goto(B+'/entrar',wait_until='domcontentloaded')
        r=pg.evaluate("(()=>{const e=document.getElementById('ag-entrada');return e?[e.querySelectorAll('.age-iso :is(path,polygon)').length,e.querySelectorAll('.age-nombre :is(path,polygon)').length,getComputedStyle(e).position]:null})()")
        ok(r==[3,6,'fixed'],f'al abrir aparece la entrada con las piezas del logo (símbolo 3, nombre 6) {r}')
        pg.wait_for_selector('#ag-entrada',state='detached',timeout=9000);d=time.time()-t0
        ok(0.85<=d<=4,f'en la pantalla para entrar se va sola en cuanto está lista ({d:.1f} s)')
        pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(250)
        ok(pg.locator('#ag-entrada').count()==1,'al iniciar sesión vuelve a aparecer mientras carga la app')
        pg.wait_for_selector('#ag-entrada',state='detached',timeout=9000)
        ok(pg.locator('main#app [data-placeholder]').count()==0 and pg.locator('main#app').inner_text().strip()!='','se va cuando la app ya cargó (sin esqueletos de carga)')
        pg.reload(wait_until='domcontentloaded')
        ok(pg.locator('#ag-entrada').count()==1,'al recargar también aparece')
        pg.wait_for_selector('#ag-entrada.is-sale',state='attached',timeout=9000)
        r=pg.evaluate("[getComputedStyle(document.querySelector('#ag-entrada .age-izq')).transitionProperty, getComputedStyle(document.querySelector('#ag-entrada .age-der')).transitionProperty]")
        ok(all('transform' in x for x in r),f'al salir se abre en dos: el símbolo hacia la izquierda y el nombre hacia la derecha {r}')
        pg.wait_for_selector('#ag-entrada',state='detached',timeout=9000)
        pg.goto(B+'/centro');pg.wait_for_selector('#ag-entrada',state='detached',timeout=9000);pg.wait_for_timeout(3000)
        ok(pg.locator('section.wg [data-wg-abrir], .wg-cel, [data-wg-dashbtn]').count()==0 and 'Widgets del equipo' not in pg.inner_text('main#app'),'Inicio no tiene widgets ni botón para crearlos (van en cada módulo)')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        limpiar();pg=br.new_context(viewport={'width':390,'height':844},reduced_motion='reduce',service_workers='block').new_page()
        pg.goto(B+'/entrar',wait_until='domcontentloaded')
        an=pg.evaluate("getComputedStyle(document.querySelector('#ag-entrada .age-nombre path')).animationName")
        ok(an=='none','con «reducir movimiento» no hay animación (solo se desvanece)')
        pg.wait_for_selector('#ag-entrada',state='detached',timeout=9000)
        ok(pg.evaluate('document.documentElement.scrollWidth')<=390,'teléfono 390 sin desborde')
    finally:
        br.close()
print(n,'comprobaciones')
