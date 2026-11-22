# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""2.9.4 · Menú principal: la franja «Ágora Digital App / Hola», «Ir a Dashboard», cifras iguales y etiquetas que llevan a cada herramienta.
Requiere la API local en :8090 con la subcuenta ASOCIM. Uso: python3 tests/menu-principal.py"""
import sys
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1
with sync_playwright() as p:
    b=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for W,H in [(1280,800),(390,844)]:
        pg=b.new_context(viewport={'width':W,'height':H},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
        pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(2800)
        pg.goto(B+'/agencia');pg.wait_for_timeout(2500);pg.keyboard.press('Escape')
        pg.evaluate("document.querySelectorAll('#layer .modal').forEach(m=>m.closest('#layer').innerHTML='')")
        bt=pg.locator('article:has-text("ASOCIM") [data-agency-open], .agency-card:has-text("ASOCIM") [data-agency-open]')
        if bt.count():bt.first.click();pg.wait_for_timeout(2500)
        pg.goto(B+'/centro');pg.wait_for_timeout(3500)
        f=pg.locator('.home-franja .home-brand').inner_text()
        ok('Digital App' in f and 'Hola,' in f and '/' in f,f'franja «Ágora Digital App / Hola» ({W})')
        ver=pg.locator('.inicio-resumen-ver')
        ok(ver.inner_text().strip()==('Ir a Dashboard' if W>600 else 'Dashboard'),f'botón del tablero dice «{ver.inner_text().strip()}» ({W})')
        ed=pg.locator('[data-ir-editar]');est=lambda l:l.evaluate("e=>{const c=getComputedStyle(e);return [c.backgroundColor,c.borderRadius,c.height,c.fontSize]}")
        ok(est(ver)==est(ed),f'2.12.9 · «Dashboard» y «Editar Dashboard» con el mismo estilo de botón {est(ver)} {est(ed)}')
        ok('completo' not in pg.locator('.operations-home').inner_text().lower(),'sin la palabra «completo»')
        cs=pg.locator('.inicio-resumen-c');xs=[cs.nth(i).bounding_box() for i in range(cs.count())]
        ls=[cs.nth(i).locator('.inicio-resumen-l .ic').count() for i in range(cs.count())]
        ok(all(l==1 for l in ls),'todas las cifras llevan el mismo título con flecha')
        vs=[cs.nth(i).locator('.inicio-resumen-v').bounding_box() for i in range(cs.count())]
        lb=[cs.nth(i).locator('.inicio-resumen-l').bounding_box() for i in range(cs.count())]
        ok(all(v and c and l and v['y']>=l['y']+l['height']-1 and abs((v['x']+v['width']/2)-(c['x']+c['width']/2))<14 for v,c,l in zip(vs,xs,lb)),f'2.12.8 · el número va debajo del título y centrado en todas, también en el teléfono ({W})')
        if W>600:
            ch=pg.locator('.mod-tile-subs a',has_text='Cobros').first;ch.click();pg.wait_for_timeout(1500)
            ok(pg.url.endswith('/cobros'),'2.11.0 · la etiqueta «Cobros» (categoría Facturación) lleva directo a Cobros')
            pg.goto(B+'/centro');pg.wait_for_timeout(2500)
            pg.locator('.mod-tile',has_text='Clientes y atención').locator('h3').click(force=True);pg.wait_for_timeout(1200)
            ok('/modulos/' in pg.url,'el resto de la tarjeta lleva al módulo')
        ok(not errs,f'sin errores de pantalla {errs}')
    b.close()
print(n,'comprobaciones')
