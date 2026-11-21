#!/usr/bin/env python3
"""Agora New App 2.12.6 · Celdas vivas (idea del usuario, en todos los módulos):
1 Cada cifra lleva su línea fina gris; la línea roja con brillo aparece solo al pasar (2.12.9). Cifras centradas, sin íconos.
2 Cada cifra tiene su botón «Editar» solo con la palabra (contorno vino) que al pasar se llena de vino con letra blanca
  y abre «Editar Dashboard» con esa cifra marcada.
3 Ordenador y teléfono. Requiere la API local en :8090. Uso: python3 tests/fixes-2126.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
VINO='rgb(173, 16, 60)'
LINEA="""(i)=>{const c=document.querySelectorAll('main .cel')[i];const b=getComputedStyle(c,'::before');return [b.backgroundColor,b.boxShadow,b.opacity]}"""
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
        faltan=[];total=0
        for r in ['/comercial','/ventas','/costos','/centro','/publicidad','/cobros']:
            pg.goto(B+r);pg.wait_for_timeout(5000)
            d=pg.evaluate("[document.querySelectorAll('main .cel').length,document.querySelectorAll('main .cel [data-cel-ed]').length]")
            total+=d[0]
            if d[0]!=d[1]:faltan.append(f'{r} {d}')
        ok(total>=8 and not faltan,f'{quien}: toda cifra tiene su línea y su botón «Editar» {faltan}')
        pg.goto(B+'/costos');pg.wait_for_timeout(5000)
        pg.mouse.move(5,5);pg.wait_for_timeout(500)
        a=pg.evaluate(LINEA,1)
        pg.locator('main .cel').nth(1).hover();pg.wait_for_timeout(600)
        b=pg.evaluate(LINEA,1)
        ok(a[2]=='0' and b[0]==VINO and b[1]!='none' and b[2]=='1',f'{quien}: 2.12.9 · sin línea roja en reposo (solo la fina gris); al pasar aparece vino con brillo {a} → {b}')
        sv=pg.evaluate("[...document.querySelectorAll('main [data-cel-ed]')].filter(e=>e.querySelector('svg')||e.textContent.trim()!=='Editar').length")
        ok(sv==0,f'{quien}: 2.12.8 · «Editar» va solo con la palabra, sin ícono ({sv})')
        cen=pg.evaluate("""[...document.querySelectorAll('main .cel')].filter(c=>c.offsetParent).filter(c=>{const v=c.querySelector('.ag-resumen-v, .t-metric, .inicio-resumen-v, .t-hero');if(!v)return false;const r=c.getBoundingClientRect(),b=v.getBoundingClientRect();return Math.abs((b.left+b.right)/2-(r.left+r.right)/2)>12}).length""")
        ok(cen==0,f'{quien}: 2.12.8 · las cifras van centradas ({cen} fuera)')
        ed=pg.locator('main .cel').nth(1).locator('[data-cel-ed]')
        c0=ed.evaluate("e=>[getComputedStyle(e).backgroundColor,getComputedStyle(e).color]")
        ed.hover();pg.wait_for_timeout(600)
        c1=ed.evaluate("e=>[getComputedStyle(e).backgroundColor,getComputedStyle(e).color]")
        ok(c0[1]==VINO and c1==[VINO,'rgb(255, 255, 255)'],f'{quien}: «Editar» vino con contorno y, al pasar, fondo vino y letra blanca {c0} → {c1}')
        nombre=pg.evaluate("(()=>{const c=document.querySelectorAll('main .cel')[1];return (c.querySelector('.ag-resumen-t, :scope > h3, .t-label')?.textContent||'').trim()})()")
        ed.click();pg.wait_for_timeout(1500)
        foco=pg.evaluate("document.querySelector('.tarj-ed .pz-foco')?.textContent?.trim()||''")
        ok(foco.startswith(nombre[:10]),f'{quien}: «Editar» abre «Editar Dashboard» con «{nombre}» marcada ({foco[:30]})')
        dentro=[]
        for r in ['/centro','/inicio','/comercial','/ventas','/cobros','/facturas','/costos','/equipo','/publicidad','/tendencias','/calidad','/redes']:
            pg.goto(B+r);pg.wait_for_timeout(3500)
            dentro+=[f'{r}: {x}' for x in pg.evaluate("""()=>[...document.querySelectorAll('main button, main a.btn')].filter(b=>b.offsetParent&&/Editar Dashboard|^Dashboard|Ir a Dashboard/.test(b.textContent.trim())).map(b=>{let p=b.parentElement;while(p&&p.id!=='app'){const c=getComputedStyle(p);if(/255, 255, 255/.test(c.backgroundColor)&&parseFloat(c.borderTopWidth)>0)return b.textContent.trim();p=p.parentElement}return null}).filter(Boolean)""")]
        if ws is None:
            frases=[]
            for r in ['/bandeja','/contactos','/oportunidades','/tareas','/ventas','/costos','/equipo']:
                pg.goto(B+r);pg.wait_for_timeout(3000)
                if pg.evaluate("[...document.querySelectorAll('main .mod-h .lede')].some(e=>e.offsetParent&&e.id!=='dashboard-feedback')"):frases.append(r)
            ok(not frases,f'2.12.12 · sin frases obvias bajo el título de los módulos {frases}')
            pg.goto(B+'/centro');pg.wait_for_timeout(3500)
            ok(pg.evaluate("!!document.querySelector('main .operations-lede')?.offsetParent"),'2.12.12 · el Inicio conserva su descripción')
            ic=pg.evaluate("(()=>{const f=t=>[...document.querySelectorAll('.side .acc-b, .acc-b')].find(b=>b.textContent.includes(t))?.querySelector('.mdm-ic svg')?.innerHTML||'';return [f('Gestión y resultados'),f('Administración')]})()")
            ok('rect' in ic[0] and 'M3 3v18h18' in ic[0] and 'M12.22 2' in ic[1],'2.12.12 · Gestión y resultados con ícono de bolsa (velas) y Administración con engranaje')
        ok(not dentro,f'{quien}: 2.12.11 · «Editar Dashboard» y «Dashboard» van fuera de las tarjetas en todos los módulos {dentro}')
        pg.goto(B+'/centro');pg.wait_for_timeout(4000);pg.keyboard.press('m');pg.wait_for_timeout(1000)
        col=pg.evaluate("[...new Set([...document.querySelectorAll('.mdm-i .mdm-ic')].filter(e=>e.offsetParent).map(e=>getComputedStyle(e).backgroundColor))]")
        ok(len(col)>=6,f'{quien}: 2.12.10 · el menú de módulos usa un color por categoría (también el dueño con subcuenta abierta) {col}')
        pg.keyboard.press('Escape')
        ok(not errs,f'{quien}: sin errores {errs}');pg.context.close()
    pg,errs=entrar(br,'admin.asocim@agora.test','Agora-Pruebas-2026!',{'width':390,'height':844})
    malos=[]
    for r in ['/comercial','/costos','/centro']:
        pg.goto(B+r);pg.wait_for_timeout(4500)
        malos+=[f'{r}: {x}' for x in pg.evaluate("""()=>[...document.querySelectorAll('main .cel')].filter(c=>c.offsetParent).flatMap(c=>{const r=c.getBoundingClientRect(),a=c.querySelector('.cel-acc');if(!a)return ['sin botón'];const b=a.getBoundingClientRect();const l=c.querySelector('.ag-resumen-t, :scope > h3, .inicio-resumen-l');const o=[];if(b.right>r.right+1)o.push('botón fuera');if(l){const t=l.getBoundingClientRect();if(t.right-parseFloat(getComputedStyle(l).paddingRight)>b.left+2&&t.left+parseFloat(getComputedStyle(l).paddingLeft)<b.right-2&&t.top<b.bottom-2&&t.bottom>b.top+2)o.push('rótulo bajo el botón: '+l.textContent.trim().slice(0,15))}return o})""")]
        if pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'):malos.append(r+' se desborda')
    ok(not malos,f'teléfono: botón y rótulo caben sin taparse {malos[:5]}')
    ok(not errs,f'teléfono: sin errores {errs}');br.close()
print(n,'comprobaciones')
