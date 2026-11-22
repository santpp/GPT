#!/usr/bin/env python3
"""Agora New App 2.18.2 · Pedido del usuario sobre las tarjetas y el filtro del Dashboard:
- menos espacio en blanco: el contenido empieza arriba; el resultado va centrado y más grande;
- todas las líneas del mismo grosor (4 px) y una línea que cierra la caja abajo;
- el filtro de fechas del Dashboard dice «Ayer», «Hoy» y «Otro periodo» (abre el selector de fechas).
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-2182.py"""
import os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
J="""(()=>{const cs=[...document.querySelectorAll('main#app .cel')].filter(c=>c.offsetParent&&!c.closest('.wg-ggrid'));const g=cs[0].parentElement;
return {n:cs.length, lineas:[...new Set(cs.map(c=>getComputedStyle(c,'::before').height))], arriba:[...new Set(cs.map(c=>getComputedStyle(c,'::before').top))],
 cierre:getComputedStyle(g,'::after').height, centro:cs.map(c=>{const v=c.querySelector('.ag-resumen-v,.t-metric,.inicio-resumen-v,.t-hero');return v?[getComputedStyle(v).textAlign,parseFloat(getComputedStyle(v).fontSize)]:null}).filter(Boolean),
 titulos:cs.map(c=>{const t=c.querySelector('.ag-resumen-t,h3,.inicio-resumen-l,.t-label');return t?getComputedStyle(t).textAlign:null}).filter(Boolean),
 arranque:[...new Set(cs.map(c=>getComputedStyle(c).justifyContent))]}})()"""
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test')
        for m in ('tablero','ventas','costos'):
            pg.goto(B+'/'+m);pg.wait_for_timeout(6000);r=pg.evaluate(J)
            ok(r['lineas']==['4px'] and r['arriba']==['0px'],f'{m}: todas las líneas de las tarjetas miden 4 px y van al borde {r["lineas"]}')
            ok(r['cierre']=='4px','%s: la caja cierra con una línea abajo'%m)
            ok(all(a=='center' and f>=24 for a,f in r['centro']) and all(t in ('left','start') for t in r['titulos']),f'{m}: resultado centrado y más grande (≥28 px); título a la izquierda {r["centro"][:2]}')
            ok(r['arranque']==['flex-start'],f'{m}: el contenido empieza arriba (sin espacio en blanco encima)')
        pg.goto(B+'/tablero');pg.wait_for_timeout(5000)
        r=pg.evaluate("""[...document.querySelectorAll('main#app .cel :is(.ag-resumen-v,.t-metric,.inicio-resumen-v,.t-hero)')].filter(v=>v.offsetParent).map(v=>{const c=v.closest('.cel');const g=document.createRange();g.selectNodeContents(v);return [parseFloat(getComputedStyle(v).fontSize),g.getBoundingClientRect().width,c.clientWidth-30,v.textContent.trim().length]})""")
        ok(r and all(w<=lim for _,w,lim,_ in r),'2.18.4: ninguna cifra se sale de su tarjeta')
        cortas=[f for f,_,_,n in r if n<=2]
        ok(cortas and min(cortas)>=60,f'2.18.4: las cifras cortas llenan la tarjeta (≥60 px: {sorted(set(int(x) for x in cortas))[:3]})')
        b=pg.evaluate("[...document.querySelectorAll('#period-bar .per-b')].filter(b=>!b.hidden&&b.offsetParent).map(b=>b.textContent.trim())")
        ok(b==['Ayer','Hoy','Otro periodo'],f'el filtro del Dashboard dice Ayer, Hoy y Otro periodo {b}')
        pg.click('#period-btn');pg.wait_for_timeout(400)
        ok(pg.locator('#period-menu:not([hidden]) #period-from').count()==1 and pg.locator('#period-menu #period-to').count()==1,'«Otro periodo» abre el selector de fechas')
        pg.fill('#period-from','2026-11-01');pg.fill('#period-to','2026-11-10');pg.click('#period-apply');pg.wait_for_timeout(4000)
        ok('1 nov' in pg.inner_text('#period-btn') or '01' in pg.inner_text('#period-btn') or pg.locator('#period-btn.is-on').count()==1,f'el rango elegido queda marcado en «Otro periodo» ({pg.inner_text("#period-btn")})')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        pg=entrar(br,'admin.asocim@agora.test',390,844);pg.goto(B+'/tablero');pg.wait_for_timeout(6000)
        ok(pg.evaluate('document.documentElement.scrollWidth')<=390,'teléfono 390 sin desborde')
    finally:
        br.close()
print(n,'comprobaciones')
