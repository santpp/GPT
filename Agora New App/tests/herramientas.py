"""2.9.5 · Las mismas herramientas en todas las vistas de Ventas y facturación: botón «Excel» arriba con el mismo menú,
barra Columnas/Excel en la tabla, sin CSV sueltos ni búsquedas repetidas, y la descarga baja solo lo filtrado.
Requiere la API local en :8090 con ASOCIM. Uso: python3 tests/herramientas.py"""
import sys,zipfile,re
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1
with sync_playwright() as p:
    b=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg=b.new_context(viewport={'width':1280,'height':900},service_workers='block',accept_downloads=True).new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(2800)
    pg.goto(B+'/agencia');pg.wait_for_timeout(2500);pg.keyboard.press('Escape')
    pg.evaluate("document.querySelectorAll('#layer .modal').forEach(m=>m.closest('#layer').innerHTML='')")
    bt=pg.locator('article:has-text("ASOCIM") [data-agency-open], .agency-card:has-text("ASOCIM") [data-agency-open]')
    if bt.count():bt.first.click();pg.wait_for_timeout(2500)
    def vista(path,tab=None):
        pg.goto(B+path);pg.wait_for_timeout(4000)
        if tab and pg.locator(f'[data-vg-tab={tab}]').count():pg.locator(f'[data-vg-tab={tab}]').click();pg.wait_for_timeout(1500)
    for nombre,path,tab in [('Ventas · reporte','/ventas','reporte'),('Ventas · registradas','/ventas','registradas'),('Facturación','/facturacion',None),('Productos','/productos',None),('Catálogo','/catalogo',None)]:
        vista(path,tab)
        ok(pg.locator('.mod-h .mg-hbtn').count()==1,f'{nombre}: botón «Excel» arriba')
        pg.locator('.mod-h .mg-hbtn').click();pg.wait_for_selector('.mg-hpop')
        ops=pg.locator('.mg-hpop [data-op]').evaluate_all('l=>l.map(x=>x.dataset.op)')
        ok('vista' in ops and ('campos' in ops or 'columnas' in ops),f'{nombre}: el menú trae «Descargar lo que ves» y las columnas ({ops})')
        pg.keyboard.press('Escape');pg.mouse.click(5,5)
        ok(pg.locator('[data-csv],[data-vg-csv],[data-est-xls]').count()==0,f'{nombre}: sin botones CSV sueltos')
        bar=pg.locator('.xt-bar').first
        ok(bar.locator('.xt-cols').count()==1 and bar.locator('.xt-xls').count()==1,f'{nombre}: la tabla trae Columnas y Excel')
        dup=pg.locator('[data-filtros-propios] .xt-q').count()
        ok(dup==0,f'{nombre}: sin buscador repetido en la tabla')
    # Ventas registradas: filtro por vendedor y la descarga baja solo lo filtrado
    vista('/ventas','registradas')
    vends=pg.locator('[data-vg-vend] option').evaluate_all('l=>l.map(o=>o.value).filter(Boolean)')
    ok(len(vends)>=1,'Ventas registradas: filtro por vendedor')
    pg.select_option('[data-vg-vend]',vends[0]);pg.wait_for_timeout(800)
    filas=pg.locator('.vg-tabla table tbody tr').count()
    pg.locator('.mod-h .mg-hbtn').click();pg.wait_for_selector('.mg-hpop [data-op=vista]')
    with pg.expect_download() as dl:pg.click('.mg-hpop [data-op=vista]')
    x=zipfile.ZipFile(dl.value.path()).read('xl/worksheets/sheet1.xml').decode()
    ok(len(re.findall(r'<row ',x))-1==filas,f'Ventas registradas: el Excel trae solo las {filas} filas filtradas')
    ok(pg.locator('[data-vg-limpiar]').count()==1,'Ventas registradas: «Quitar filtros» aparece con un filtro puesto')
    ok(not errs,f'sin errores de pantalla {errs}')
    b.close()
print(n,'comprobaciones')
