#!/usr/bin/env python3
"""Agora New App 2.12.3 y 2.12.4 · Pedidos del usuario (20 nov, tarde):
1 TODOS los módulos: cada grupo de cifras es UNA tarjeta blanca con las cifras separadas por líneas (sin tarjetas sueltas ni huecos).
2 Sin «carnaval»: los íconos de los módulos y los gráficos usan solo vino y azul profundo (y sus tonos); nada de oro ni verde en el menú.
3 Los gráficos de ventas van en Gestión Comercial, no en el Tablero comercial.
6 2.12.8 · Estilo de las cifras (referencia del usuario): centradas, descripción breve, «Editar» arriba a la derecha, sin huecos.
5 2.12.4 · Armonía jerárquica: todas las cajas con radio 16 px, paneles con relleno 20 × 24 px y 16 px entre cajas seguidas.
4 «Editar Dashboard» en todo módulo con cifras (también el Tablero y las vistas del reporte mensual): elegir qué ver y cambiar el orden.
Requiere la API local en :8090. Uso: python3 tests/fixes-2123.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
R=['/inicio','/comercial','/ventas','/cobros','/facturas','/tasas','/equipo','/publicidad','/costos','/tendencias','/calidad','/redes']
CAJA="""()=>[...document.querySelectorAll('main .ag-resumen-grid, main .g-hero, main .grid')].filter(g=>g.offsetParent&&g.querySelector(':scope > .micro, :scope > .ag-resumen, :scope > .hero, :scope > .grid > .micro')).map(g=>{const c=getComputedStyle(g);
  const kids=[...g.querySelectorAll(':scope > .micro, :scope > .ag-resumen, :scope > .hero, :scope > .grid > .micro')].filter(k=>k.offsetParent);
  return {cls:g.className,gap:c.rowGap,borde:c.borderTopWidth,celdas:kids.every(k=>getComputedStyle(k).borderTopLeftRadius==='0px'&&getComputedStyle(k).backgroundColor==='rgba(0, 0, 0, 0)')}})"""
TIENE="""()=>[...document.querySelectorAll('main button')].some(b=>b.offsetParent&&/Editar Dashboard/.test(b.textContent))"""
CIFRAS="""()=>[...document.querySelectorAll('main .micro, main .ag-resumen, main .g-hero > .hero')].filter(e=>e.offsetParent).length"""
ESTILO="""()=>{const out=[];const cel=[...document.querySelectorAll('main .ag-resumen-grid > .ag-resumen, main .g-hero > .hero, main .grid:has(> .micro) > .micro, main .inicio-resumen-c')].filter(e=>e.offsetParent);
  cel.forEach(c=>{const r=c.getBoundingClientRect();const l=c.querySelector('.ag-resumen-t, :scope > h3, .t-label, .inicio-resumen-l');if(l){const b=l.getBoundingClientRect();if(Math.abs((b.left+b.right)/2-(r.left+r.right)/2)>14)out.push('rótulo no está centrado: '+l.textContent.trim().slice(0,20))}
   const ic=c.querySelector(':scope > .cel-acc');if(ic&&ic.offsetParent&&innerWidth>600){const b=ic.getBoundingClientRect();if(r.right-b.right>30||b.top-r.top>30)out.push('ícono fuera de la esquina: '+(l?.textContent||'').trim().slice(0,20))}});
  document.querySelectorAll('main .ag-resumen-grid, main .g-hero, main .grid:has(> .micro), main .inicio-resumen-g').forEach(g=>{if(!g.offsetParent)return;const k=[...g.children].filter(e=>e.offsetParent&&e.getBoundingClientRect().height>20);if(k.length<2)return;const R=g.getBoundingClientRect();
   const bot=Math.max(...k.map(e=>e.getBoundingClientRect().bottom));const ult=k.filter(e=>Math.abs(e.getBoundingClientRect().bottom-bot)<3);const der=Math.max(...ult.map(e=>e.getBoundingClientRect().right));if(R.right-der>30)out.push('hueco en la última fila de '+g.className.slice(0,30))});return out}"""
def entrar(br,u,pw,vp,ws=None):
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(pw);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    if ws:pg.evaluate("async id=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:id})})}",ws)
    return pg,errs
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for u,pw,ws,quien in [('admin.asocim@agora.test','Agora-Pruebas-2026!',None,'con reporte'),('duena@example.test','Clave-Local-2026!',1,'sin reporte')]:
        pg,errs=entrar(br,u,pw,{'width':1280,'height':900},ws)
        sueltas=[];sinBoton=[]
        for r in R:
            pg.goto(B+r);pg.wait_for_timeout(4000)
            for g in pg.evaluate(CAJA):
                if not (g['gap'] in ('0px','normal') and g['borde']=='1px' and g['celdas']):sueltas.append(f"{r}: {g['cls'][:40]}")
            if pg.evaluate(CIFRAS)>=2 and not pg.evaluate(TIENE):sinBoton.append(r)
        ok(not sueltas,f'{quien}: todas las cifras van en una sola tarjeta con líneas {sueltas[:5]}')
        ritmo=[];RIT="""()=>{const m=document.querySelector('main#app');const W=m.getBoundingClientRect().width;const box=e=>{if(!e.offsetParent)return false;const c=getComputedStyle(e);const r=e.getBoundingClientRect();return parseFloat(c.borderTopLeftRadius)>=8&&(c.borderTopStyle!=='none'&&parseFloat(c.borderTopWidth)>0||c.boxShadow!=='none')&&/255, 255, 255/.test(c.backgroundColor)&&r.width>W*0.42&&r.height>40};
          const top=[...m.querySelectorAll('*')].filter(e=>{if(!box(e))return false;let p=e.parentElement;while(p&&p!==m){if(box(p))return false;p=p.parentElement}return true}).sort((a,b)=>a.getBoundingClientRect().top-b.getBoundingClientRect().top);const out=[];
          top.forEach((e,i)=>{const c=getComputedStyle(e);if(Math.round(parseFloat(c.borderTopLeftRadius))!==16)out.push('radio '+Math.round(parseFloat(c.borderTopLeftRadius))+' '+e.className);if(e.classList.contains('panel')&&c.paddingLeft!==(innerWidth>600?'24px':'16px'))out.push('relleno '+c.paddingLeft+' '+e.className);
            if(i&&e.previousElementSibling===top[i-1]){const a=top[i-1].getBoundingClientRect(),b=e.getBoundingClientRect();const g=Math.round(b.top-a.bottom);if(b.top>a.bottom-2&&Math.abs(a.left-b.left)<4&&g<40&&g!==16&&!(g===12&&e.tagName==='TR'))out.push('hueco '+g+' '+e.className)}});return out}"""
        for r in R:
            pg.goto(B+r);pg.wait_for_timeout(3000);ritmo+=[f'{r}: {x[:60]}' for x in pg.evaluate(RIT)]
        ok(not ritmo,f'{quien}: 2.12.4 · mismo radio, relleno y espacio entre cajas en todos los módulos {ritmo[:6]}')
        ok(not sinBoton,f'{quien}: todo módulo con cifras tiene «Editar Dashboard» {sinBoton}')
        colores=pg.evaluate("[...new Set([...document.querySelectorAll('.side .mdm-ic, .acc-b .mdm-ic')].filter(e=>e.offsetParent).map(e=>getComputedStyle(e).backgroundColor))]")
        ok(not any(c in colores for c in ['rgb(201, 151, 31)','rgb(52, 199, 89)','rgb(250, 189, 75)']) and 4<=len(colores)<=7,f'{quien}: 2.12.7 · el menú usa los tonos de la marca por sección, sin oro ni verde {colores}')
        # 2.12.6 · estilo de las cifras: a la izquierda, ícono de trazado arriba a la derecha y sin huecos en la última fila
        est=[]
        for r in ['/comercial','/ventas','/costos','/centro']:
            pg.goto(B+r);pg.wait_for_timeout(4500)
            est+=[f'{r}: {x}' for x in pg.evaluate(ESTILO)]
        ok(not est,f'{quien}: 2.12.8 · cifras centradas, «Editar» arriba a la derecha y sin huecos {est[:5]}')
        ok(not errs,f'{quien}: sin errores {errs}');pg.context.close()
    # ---- Tablero: sin gráficos, y «Editar Dashboard» que ordena y oculta
    pg,errs=entrar(br,'admin.asocim@agora.test','Agora-Pruebas-2026!',{'width':1280,'height':900})
    pg.goto(B+'/comercial');pg.wait_for_timeout(7000)
    ok(pg.locator('#view [data-vt]').count()==0 and 'Tendencia del año' not in pg.inner_text('main'),'el Tablero ya no trae los gráficos de ventas (van en Gestión Comercial)')
    pg.click('[data-tg-editar]');pg.wait_for_selector('.tarj-ed .pz-item')
    nombres=pg.evaluate("[...document.querySelectorAll('.tarj-ed .pz-item span:not(.pz-flechas)')].map(s=>s.textContent.trim()).filter(Boolean)")
    ok(len(nombres)>=6,f'el editor lista las cifras del Tablero ({len(nombres)})')
    segundo=nombres[1]
    pg.locator('.tarj-ed [data-tg-mov="1|-1"]').click();pg.wait_for_timeout(200)
    oculto=nombres[2];pg.locator('.tarj-ed [data-tg-on="2"]').uncheck()
    pg.click('[data-tg-ok]');pg.wait_for_timeout(2500)
    est=pg.evaluate("""(n)=>{const t=[...document.querySelectorAll('#view .g-hero > .hero, #view .g-hero .micro, #view .grid:has(> .micro) > .micro')];const f=x=>t.find(e=>(e.querySelector(':scope > h3, :scope > div > .t-label')?.textContent||'').trim().startsWith(x));return {o:f(n[1])?.style.order, oc:f(n[2])?.style.display}}""",[nombres[0],segundo,oculto])
    ok(est['o']=='0' and est['oc']=='none',f'«Editar Dashboard» cambia el orden ({segundo} sube) y oculta ({oculto}) {est}')
    pg.reload();pg.wait_for_timeout(7000)
    est2=pg.evaluate("""(n)=>{const t=[...document.querySelectorAll('#view .g-hero > .hero, #view .g-hero .micro, #view .grid:has(> .micro) > .micro')];const f=x=>t.find(e=>(e.querySelector(':scope > h3, :scope > div > .t-label')?.textContent||'').trim().startsWith(x));return f(n)?.style.display}""",oculto)
    ok(est2=='none','la elección se guarda en el servidor (sigue al volver a entrar)')
    pg.click('[data-tg-editar]');pg.wait_for_selector('[data-tg-def]');pg.click('[data-tg-def]');pg.wait_for_timeout(2500)
    ok(pg.evaluate("[...document.querySelectorAll('#view .micro')].every(e=>e.style.display!=='none')"),'«Restaurar» vuelve a mostrar todo')
    ok(not errs,f'Tablero: sin errores {errs}');pg.context.close()
    # ---- teléfono: sin desbordes
    pg,errs=entrar(br,'admin.asocim@agora.test','Agora-Pruebas-2026!',{'width':390,'height':844})
    malos=[]
    for r in R:
        pg.goto(B+r);pg.wait_for_timeout(3500)
        if pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'):malos.append(r)
    ok(not malos,f'teléfono: ningún módulo se desborda {malos}')
    # 2.12.5 · el teléfono cumple el mismo ritmo: radio 16, paneles con 16 px, 16 entre cajas (12 entre fichas) e íconos de sección iguales
    ritmo=[];iconos=set()
    for r in R:
        pg.goto(B+r);pg.wait_for_timeout(3000);ritmo+=[f'{r}: {x[:60]}' for x in pg.evaluate(RIT)]
        iconos|=set(pg.evaluate("[...document.querySelectorAll('main .arp-sec-ic, main .arp-ph-ic, main .pf-sec-h > span:first-child')].filter(e=>e.offsetParent).map(e=>Math.round(e.getBoundingClientRect().width))"))
    ok(not ritmo,f'teléfono: 2.12.5 · mismo radio, relleno y espacios que en el ordenador {ritmo[:6]}')
    ok(len(iconos)<=1,f'teléfono: todos los íconos de sección del mismo tamaño {iconos}')
    est=[]
    for r in ['/comercial','/ventas','/costos','/centro']:
        pg.goto(B+r);pg.wait_for_timeout(4500);est+=[f'{r}: {x}' for x in pg.evaluate(ESTILO)]
    ok(not est,f'teléfono: 2.12.6 · mismo estilo de las cifras {est[:5]}')
    ok(not errs,f'teléfono: sin errores {errs}');br.close()
print(n,'comprobaciones')
