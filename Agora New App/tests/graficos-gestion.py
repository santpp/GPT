"""2.9.7–2.9.9 · Gráficos de ventas en Gestión Comercial: los mismos del Tablero, elegibles con «Editar gráficos».
Con reporte mensual sale por defecto la tendencia del año (la cabecera del reporte ya trae el mes); se puede sumar «Ventas del mes».
Van al final del módulo (2.9.9). Quien no ve cifras del equipo (ejecutivo) no los ve. Requiere la API local en :8090 con ASOCIM. Uso: python3 tests/graficos-gestion.py"""
import sys
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
def entrar(b,u,vp=(1280,800)):
    pg=b.new_context(viewport={'width':vp[0],'height':vp[1]},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(f'{u}.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    pg.goto(B+'/inicio');pg.wait_for_timeout(6000);return pg
def alFinal(pg):
    return pg.evaluate("""()=>{const g=document.querySelector('[data-gc-graf]');if(!g)return false;const b=g.getBoundingClientRect().bottom;
      return [...document.querySelectorAll('main section.panel, main .panel, main .ag-resumen-grid')].filter(e=>!g.contains(e)).every(e=>e.getBoundingClientRect().bottom<=b+1)}""")
paneles=lambda pg:pg.locator('[data-gc-graf] .vt-panel h2').all_inner_texts()
with sync_playwright() as p:
    b=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg=entrar(b,'admin')
    ok(pg.locator('[data-gc-graf]').count()==1,'Gestión Comercial trae la sección «Gráficos de ventas»')
    ok(alFinal(pg),'los gráficos van al final del módulo')
    ok(paneles(pg)==['Tendencia del año'],f'con reporte, por defecto solo la tendencia del año ({paneles(pg)})')
    pg.click('[data-gc-graf-ed]');pg.wait_for_timeout(600)
    for c in pg.locator('[data-tarj]').all():
        if not c.is_checked():c.check()
    pg.click('[data-tarj-ok]');pg.wait_for_timeout(4000)
    ok(paneles(pg)==['Ventas del mes','Tendencia del año'],'«Editar gráficos» suma «Ventas del mes»')
    pg.reload();pg.wait_for_timeout(6000)
    ok(len(paneles(pg))==2,'la elección se guarda')
    pg.click('[data-gc-graf-ed]');pg.wait_for_timeout(600);pg.click('[data-tarj-def]');pg.wait_for_timeout(4000)
    ok(paneles(pg)==['Tendencia del año'],'«Restaurar» vuelve a la vista de fábrica')
    t=pg.locator('[data-gc-graf]').inner_text()
    ok('undefined' not in t and 'NaN' not in t and 'null' not in t,'sin textos vacíos en los gráficos')
    ok(not pg.errs,f'sin errores de pantalla {pg.errs}')
    pg.context.close()
    pg=entrar(b,'admin',(390,844))
    ok(pg.locator('[data-gc-graf] .vt-panel').count()>=1 and not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),'en el teléfono se ven sin desbordar')
    pg.context.close()
    pg=entrar(b,'ejecutivo1')
    ok(pg.locator('[data-gc-graf]').count()==0,'el ejecutivo no ve los gráficos del equipo')
    pg.context.close()
    b.close()
print(n,'comprobaciones')
