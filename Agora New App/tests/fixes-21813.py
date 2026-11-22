#!/usr/bin/env python3
"""Agora New App 2.18.13 · Pedido del usuario (video del 22 nov):
1. La entrada se iba antes de que aparecieran los números (tope de 7 s y la cuenta real tarda más): ahora espera a que no haya esqueletos
   y la pantalla esté quieta, con tope de 20 s. Los esqueletos siguen en los cambios entre módulos.
2. Se veían las tarjetas «viejas» (sin arreglar) y luego se acomodaban: ahora se arreglan antes de pintarse.
3. Caché: la página se pide siempre fresca y, si el navegador abrió una versión vieja, se recarga sola una vez.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/fixes-21813.py"""
import os,time,json
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
RAIZ=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..','public_html')
sw=open(os.path.join(RAIZ,'sw.js'),encoding='utf-8').read()
ok("fetch(e.request,{cache:'no-store'})" in sw,'el trabajador pide la página sin la caché del navegador')
VIG="""window.__mal=0;window.__dob=0;window.__skf=0;(function(){const S='.ag-resumen-grid > .ag-resumen, .gc-caja-g > .ag-resumen, .g-hero > .hero, .g-hero .micro, .grid:has(> .micro) > .micro, .inicio-resumen-c';
 const mira=()=>{const m=document.querySelector('main#app');if(!m){requestAnimationFrame(mira);return}const e=document.getElementById('ag-entrada');if(e&&!e.classList.contains('is-sale')){requestAnimationFrame(mira);return}
  if(!window.__mod&&[...m.querySelectorAll('.sk,[data-placeholder]')].some(x=>x.offsetParent))window.__skf++;
  if([...m.querySelectorAll(S)].some(c=>!c.dataset.cel&&c.offsetParent))window.__mal++;
  if([...m.querySelectorAll('button')].filter(b=>/Editar Dashboard/.test(b.textContent)&&b.offsetParent&&!b.hasAttribute('data-wg-dashbtn')).length>1)window.__dob++;
  requestAnimationFrame(mira)};requestAnimationFrame(mira)})();"""
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for w,h in ((390,844),(1280,800)):
            pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/centro');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
            # Cuenta lenta, como la real del video: las cifras del día y del mes (las que vienen de la cuenta) tardan 7 s más.
            pg.add_init_script("(()=>{const f=window.fetch;window.fetch=function(u,o){const p=f.apply(this,arguments);const s=String(u&&u.url||u);if(/\\/api\\/workspace\\/(dia|board-month)/.test(s)&&!(o&&o.method&&o.method!=='GET'))return p.then(r=>new Promise(ok=>setTimeout(()=>ok(r),7000)));return p}})()")
            pg.add_init_script(VIG)
            t0=time.time();pg.goto(B+'/comercial',wait_until='domcontentloaded')
            pg.wait_for_selector('#ag-entrada',state='detached',timeout=40000);d=time.time()-t0
            sk=pg.locator('main#app .sk:visible, main#app [data-placeholder]').count()
            nums=pg.locator('main#app .cel .t-metric, main#app .cel .ag-resumen-v').count()
            ok(sk==0 and nums>=3 and d<20,f'{w}px: con la cuenta lenta, cuando se va ya están los números ({nums} cifras, {sk} esqueletos, {d:.1f} s)')
            ok(pg.evaluate('window.__skf')==0,f'{w}px: la entrada nunca se va dejando esqueletos a la vista ({pg.evaluate("window.__skf")} cuadros)')
            pg.wait_for_timeout(1500)
            ok(pg.evaluate('window.__mal')==0,f'{w}px: nunca se pinta una tarjeta sin arreglar ({pg.evaluate("window.__mal")} cuadros)')
            ok(pg.evaluate('window.__dob')==0,f'{w}px: nunca hay dos «Editar Dashboard» a la vez ({pg.evaluate("window.__dob")} cuadros)')
            # Cambio de módulo: sin entrada, con esqueleto, y sin tarjetas sin arreglar.
            pg.evaluate("window.__mal=0;window.__dob=0;window.__mod=1")
            pg.locator('a[href="/ventas"]').first.click() if pg.locator('a[href="/ventas"]:visible').count() else pg.evaluate("history.pushState(null,'','/ventas');dispatchEvent(new PopStateEvent('popstate'))")
            pg.wait_for_timeout(400)
            ok(pg.locator('#ag-entrada').count()==0,f'{w}px: al cambiar de módulo no sale la entrada')
            pg.wait_for_timeout(12000)
            ok(pg.evaluate('window.__mal')==0 and pg.evaluate('window.__dob')==0,f'{w}px: al cambiar de módulo tampoco se ven tarjetas sin arreglar ni botones dobles')
            ok(not pg.errs,f'{w}px: sin errores de la página {pg.errs[:2]}')
            pg.close()
        # Versión vieja abierta: se recarga sola una vez y no se queda recargando.
        pg=entrar(br,'admin.asocim@agora.test');cargas=[0]
        pg.on('framenavigated',lambda f: cargas.__setitem__(0,cargas[0]+1) if f==pg.main_frame else None)
        pg.route('**/version.json*',lambda r:r.fulfill(status=200,content_type='application/json',body=json.dumps({'version':'9.9.9'})))
        pg.goto(B+'/centro');pg.wait_for_timeout(9000)
        ok(cargas[0]==2,f'con una versión distinta en el servidor la página se recarga una sola vez ({cargas[0]} cargas)')
        pg.close()
    finally:
        limpiar();br.close()
print(n,'comprobaciones')
