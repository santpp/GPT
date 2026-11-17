"""2.9.1 · Catálogo → Estudiantes: filtro por vendedor y cédula, Excel solo con lo filtrado, estado a mano y recurrencia de activos.
Requiere la API local en :8090 con la subcuenta ASOCIM (motor-puente.py antes) y ventas registradas (venta-cuenta.py / ventas-importadas.py).
2.9.2: solo con rubro de educación. La prueba pone el rubro de ASOCIM en la base local (socket de MariaDB de pruebas).
Uso: python3 tests/estudiantes.py"""
SQL = lambda q: subprocess.run(['mariadb', '-S', '/tmp/mdb/run/mysql.sock', 'agora_erp', '-N', '-e', q], check=True, capture_output=True, text=True).stdout.strip()
import io, json, sys, zipfile, re, subprocess, urllib.request
from playwright.sync_api import sync_playwright
B = 'http://127.0.0.1:8090'
ok = 0
def check(c, m):
    global ok
    if not c: print('FALLA:', m); sys.exit(1)
    ok += 1
def filas_xlsx(path):
    z = zipfile.ZipFile(path); x = z.read('xl/worksheets/sheet1.xml').decode()
    return len(re.findall(r'<row ', x))
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 800}, service_workers='block', accept_downloads=True); pg = ctx.new_page()
    errs = []; pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(B + '/entrar'); pg.wait_for_timeout(1200)
    pg.locator('input').first.fill('duena@example.test'); pg.locator('input[type=password]').fill('Clave-Local-2026!'); pg.keyboard.press('Enter'); pg.wait_for_timeout(2800)
    pg.goto(B + '/agencia'); pg.wait_for_timeout(2500)
    pg.keyboard.press('Escape'); pg.evaluate("document.querySelectorAll('#layer .modal').forEach(m=>m.closest('#layer').innerHTML='')")
    bt = pg.locator('article:has-text("ASOCIM") [data-agency-open], .agency-card:has-text("ASOCIM") [data-agency-open]')
    if bt.count(): bt.first.click(); pg.wait_for_timeout(2500)
    SQL("UPDATE agora_tenants SET industry='Comercio' WHERE subaccount LIKE 'ASOCIM%'")
    pg.goto(B + '/agencia'); pg.wait_for_timeout(2500)
    pg.keyboard.press('Escape'); pg.evaluate("document.querySelectorAll('#layer .modal').forEach(m=>m.closest('#layer').innerHTML='')")
    if bt.count(): bt.first.click(); pg.wait_for_timeout(2500)
    pg.goto(B + '/catalogo'); pg.wait_for_timeout(4500)
    check(pg.locator('.est-v').count() == 0 and pg.locator('.mod-h').count() >= 1, 'subcuenta que no es de educación: Catálogo sin Estudiantes')
    vid0 = pg.evaluate("fetch('/api/workspace/ventas',{credentials:'same-origin'}).then(r=>r.json()).then(d=>d.items[0].id)")
    st0 = pg.evaluate("fetch('/api/workspace/ventas/alumno',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify({ventaId:'" + vid0 + "',estado:'pausa'})}).then(r=>r.status)")
    check(st0 == 403, f'sin educación, el estado de estudiante se rechaza ({st0})')
    SQL("UPDATE agora_tenants SET industry='Educación' WHERE subaccount LIKE 'ASOCIM%'")
    pg.goto(B + '/agencia'); pg.wait_for_timeout(2500)
    pg.keyboard.press('Escape'); pg.evaluate("document.querySelectorAll('#layer .modal').forEach(m=>m.closest('#layer').innerHTML='')")
    if bt.count(): bt.first.click(); pg.wait_for_timeout(2500)
    pg.goto(B + '/catalogo'); pg.wait_for_timeout(4500)
    check(pg.locator('.est-v').count() == 1, 'subcuenta de educación: Catálogo muestra Estudiantes')
    d = pg.evaluate("fetch('/api/workspace/ventas',{credentials:'same-origin'}).then(r=>r.json())")
    items = d['items']; check(len(items) >= 3, f'hay ventas ({len(items)})')
    vend = lambda v: (v.get('vendedor') or '').strip() or 'Sin vendedor'
    cuenta = {}
    for v in items: cuenta[vend(v)] = cuenta.get(vend(v), 0) + 1
    elegido = max(cuenta, key=cuenta.get); n = cuenta[elegido]
    check(n < len(items), 'el vendedor elegido no tiene todas las ventas')
    pg.select_option('[data-est-vend]', elegido); pg.wait_for_timeout(800)
    filas = pg.locator('.est-t tbody tr').count()
    check(filas == n, f'filtro por vendedor: {filas} filas = {n}')
    check(f'{n} ' in pg.locator('.est-n').inner_text(), 'dice cuántos estudiantes quedan con los filtros')
    pg.click('.mg-hbtn');pg.wait_for_selector('.mg-hpop [data-op=vista]')
    with pg.expect_download() as dl: pg.click('.mg-hpop [data-op=vista]')
    ruta = dl.value.path(); r = filas_xlsx(ruta)
    check(r == n + 1 + 3, f'Excel trae solo lo filtrado: {r - 4} de {len(items)} (encabezado + {n} + totales)')
    # Cédula
    doc = next((v['cliente'].get('documento') for v in items if (v.get('cliente') or {}).get('documento')), None)
    pg.click('[data-est-limpiar]'); pg.wait_for_timeout(600)
    if doc:
        pg.fill('[data-est-doc]', doc); pg.wait_for_timeout(900)
        nd = sum(1 for v in items if doc.lower() in re.sub(r'[^0-9a-z]', '', ((v.get('cliente') or {}).get('documento') or '').lower()))
        check(pg.locator('.est-t tbody tr').count() == nd, f'filtro por cédula {doc}: {nd}')
        pg.click('[data-est-limpiar]'); pg.wait_for_timeout(600)
    else:
        check(True, 'sin cédulas en las ventas de prueba')
    # Estado a mano
    rec0 = pg.locator('.est-kpis .ag-resumen').nth(1).inner_text()
    vid = pg.locator('[data-est-alumno]').first.get_attribute('data-est-alumno')
    pg.locator('[data-est-alumno]').first.click(); pg.wait_for_timeout(300)
    pg.click('.est-pop [data-op=pausa]'); pg.wait_for_timeout(1500)
    d2 = pg.evaluate("fetch('/api/workspace/ventas',{credentials:'same-origin'}).then(r=>r.json())")
    v2 = next(v for v in d2['items'] if v['id'] == vid)
    check(v2.get('alumno') == 'pausa', 'el estado queda guardado en la venta')
    check(pg.locator('[data-est-f=pausa]').inner_text().endswith('1') or 'En pausa · ' in pg.locator('[data-est-f=pausa]').inner_text(), 'chip En pausa cuenta')
    act = [v for v in d2['items'] if v.get('alumno', 'activo') == 'activo']
    def mens(v):
        m = float(v.get('monto') or 0); q = int(v.get('cuotas') or 0); f = {'semanal': 52/12, 'quincenal': 2, 'mensual': 1, 'trimestral': 1/3, 'semestral': 1/6, 'anual': 1/12}.get(v.get('frecuencia') or '', 1)
        return m * f if v.get('cobro') == 'recurrente' else (m / q * f if v.get('cobro') == 'unico' and q > 1 else 0)
    esperado = sum(mens(v) for v in act)
    txt = pg.locator('.est-kpis .ag-resumen').nth(1).inner_text()
    num = float(re.search(r'([\d.]+,\d\d)', txt).group(1).replace('.', '').replace(',', '.'))
    check(abs(num - esperado) < 0.02, f'recurrencia solo de activos: {num} = {esperado:.2f}')
    pg.locator(f'[data-est-alumno="{vid}"]').click(); pg.wait_for_timeout(300); pg.click('.est-pop [data-op=activo]'); pg.wait_for_timeout(1500)
    d3 = pg.evaluate("fetch('/api/workspace/ventas',{credentials:'same-origin'}).then(r=>r.json())")
    check(next(v for v in d3['items'] if v['id'] == vid).get('alumno') == 'activo', 'vuelve a activo')
    st = pg.evaluate("fetch('/api/workspace/ventas/alumno',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify({ventaId:'" + vid + "',estado:'otro'})}).then(r=>r.status)")
    check(st in (400, 422, 403), f'estado inválido rechazado ({st})')
    check(pg.locator('.est-tot').count() == 1, 'totales generales abajo')
    check(not errs, f'sin errores de pantalla {errs}')
    b.close()
print(ok, 'comprobaciones')
