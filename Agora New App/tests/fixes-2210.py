#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.21.0 · Pedidos del usuario:
 1. Bandeja: «Asignar» sale de la cabecera de la conversación y queda solo en la ficha del contacto; «¿Venta ganada?» se queda;
    nuevo «Lead perdido» con ventana de confirmación que deja la oportunidad perdida en la cuenta (y anota el motivo).
 2. Tutoriales de bienvenida en Bandeja, Contactos, Oportunidades, Tareas, Dashboard, Gestión Comercial y Ventas:
    se abren solos la primera vez, se saltan o se terminan, no vuelven a salir y se repiten desde el menú de la cuenta.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Deja la oportunidad o3 como estaba.
Uso: python3 tests/fixes-2210.py"""
import os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
a=C('admin.asocim@agora.test');cid=2
def estado(op):
    st,d=a.call(f'workspace/opportunities?connectionId={cid}&conversationId=c3')
    return next((o['status'] for o in d.get('items',[]) if o['id']==op),None)
ok(estado('o3')=='open','la oportunidad de prueba empieza abierta')
SHOT=os.environ.get('SHOT','/tmp')
MODS={'bandeja':'/bandeja','contactos':'/contactos','oportunidades':'/oportunidades','tareas':'/tareas','comercial':'/comercial','inicio':'/inicio','ventas':'/ventas'}
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for w,h in ((1280,800),(390,844)):
            # ---------- 1. Bandeja ----------
            pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/bandeja');pg.wait_for_selector('[data-live-id="c3"]',timeout=20000)
            pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
            pg.locator('[data-live-id="c3"]').first.click();pg.wait_for_selector('#live-lost',state='visible',timeout=15000)
            ok(pg.locator('#live-assign').count()==0,f'{w}px: la cabecera de la conversación ya no tiene «Asignar»')
            ok(pg.locator('#live-won').is_visible(),f'{w}px: «¿Venta ganada?» sigue en la cabecera')
            # Asignar vive en la ficha
            if not pg.locator('.live-profile-controls').first.is_visible():pg.locator('#live-contact-toggle').click();pg.wait_for_timeout(600)
            asig=pg.locator('.live-profile-controls button',has_text='Asignar')
            ok(asig.count()>=1 and asig.first.is_visible(),f'{w}px: «Asignar» está en la ficha del contacto')
            asig.first.click();pg.wait_for_timeout(700)
            ok(pg.locator('#layer select, #layer [role=dialog], dialog[open]').count()>0,f'{w}px: «Asignar» de la ficha abre la ventana para elegir persona')
            pg.keyboard.press('Escape');pg.wait_for_timeout(400)
            if pg.locator('#layer [data-close]').count():pg.locator('#layer [data-close]').first.click();pg.wait_for_timeout(300)
            if pg.locator('#live-profile-close').first.is_visible():pg.locator('#live-profile-close').first.click();pg.wait_for_timeout(500)
            if w==1280:pg.locator('.live-chat-header').screenshot(path=SHOT+'/cabecera.png')
            nb=pg.evaluate("(()=>{const h=document.querySelector('.live-chat-header h2').getBoundingClientRect(),b=document.querySelector('#live-won').getBoundingClientRect();return h.right<=b.left+1})()")
            ok(nb,f'{w}px: el nombre no se monta sobre los botones')
            # Lead perdido: cancelar no cambia nada
            pg.locator('#live-lost').click();pg.wait_for_selector('.lost-form',timeout=15000)
            ok('¿Dar el lead por perdido?' in pg.inner_text('#layer'),f'{w}px: «Lead perdido» pide confirmación')
            ok(pg.locator('.lost-form select[name=op] option[value="o3"]').count()==1,f'{w}px: la ventana ofrece la oportunidad abierta del contacto')
            if w==1280:pg.locator('#layer .modal, #layer [role=dialog]').first.screenshot(path=SHOT+'/perdido.png')
            pg.locator('.lost-form [data-close]').first.click();pg.wait_for_timeout(600)
            ok(estado('o3')=='open',f'{w}px: al cancelar la oportunidad sigue abierta')
            if w==1280:
                pg.locator('#live-lost').click();pg.wait_for_selector('.lost-form',timeout=15000)
                pg.select_option('.lost-form select[name=op]','o3');pg.fill('.lost-form textarea[name=motivo]','Eligió otra opción')
                pg.locator('.lost-form [type=submit]').click();pg.wait_for_selector('.lost-form',state='detached',timeout=15000);pg.wait_for_timeout(800)
                ok(estado('o3')=='lost','al confirmar, la oportunidad queda perdida en la cuenta')
                ok('Lead marcado como perdido' in pg.inner_text('#toast'),'avisa «Lead marcado como perdido.»')
                st,r=a.call(f'workspace/opportunity/edit?connectionId={cid}&opportunityId=o3','POST',{'status':'open','requestId':__import__('uuid').uuid4().hex})
                ok(st==200 and estado('o3')=='open','se deja la oportunidad abierta otra vez (limpieza)')
            txt=pg.inner_text('main#app');ok(not re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',txt),f'{w}px: sin nombres del proveedor')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
            pg.close()
            # ---------- 2. Tutoriales ----------
            pg=entrar(br,'admin.asocim@agora.test',w,h)
            pg.evaluate("localStorage.setItem('agora.tutorial.auto','1');Object.keys(localStorage).filter(k=>k.startsWith('agora.tutorial.')&&k!=='agora.tutorial.auto').forEach(k=>localStorage.removeItem(k))")
            for mod,ruta in MODS.items():
                pg.goto(B+ruta);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                try:pg.wait_for_selector('#ag-tour .tour-card',timeout=20000);abierto=True
                except Exception:abierto=False
                ok(abierto,f'{w}px {mod}: el tutorial se abre solo la primera vez')
                if not abierto:continue
                total=int(re.search(r'de (\d+)',pg.inner_text('#ag-tour .tour-n')).group(1))
                ok(total>=4,f'{w}px {mod}: tiene {total} pasos')
                vistos=[];malos=[]
                for i in range(total):
                    pg.wait_for_timeout(350)
                    vistos.append(pg.inner_text('#ag-tour h3'))
                    caja=pg.locator('#ag-tour .tour-card').bounding_box()
                    if not caja or caja['x']<0 or caja['y']<0 or caja['x']+caja['width']>w+1 or caja['y']+caja['height']>h+1:malos.append(vistos[-1])
                    if w==1280 and mod=='bandeja' and i==1:pg.screenshot(path=SHOT+'/tour-bandeja.png')
                    if w==390 and mod=='oportunidades' and i==1:pg.screenshot(path=SHOT+'/tour-opp-tel.png')
                    if i<total-1:pg.locator('#ag-tour .tour-sig').click()
                ok(not malos,f'{w}px {mod}: la tarjeta siempre cabe en la pantalla {malos}')
                ok(len(set(vistos))==len(vistos),f'{w}px {mod}: cada paso es distinto ({" · ".join(vistos)})')
                ok(pg.inner_text('#ag-tour .tour-sig')=='Terminar',f'{w}px {mod}: el último paso dice «Terminar»')
                pg.locator('#ag-tour .tour-sig').click();pg.wait_for_timeout(400)
                ok(pg.locator('#ag-tour').count()==0,f'{w}px {mod}: al terminar se cierra')
                if mod=='bandeja':
                    pg.goto(B+'/contactos');pg.wait_for_timeout(300);pg.goto(B+ruta);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_timeout(4000)
                    ok(pg.locator('#ag-tour').count()==0,f'{w}px: no vuelve a salir en un módulo ya visto')
            # Saltar con Escape y repetirlo desde el menú
            pg.evaluate("Object.keys(localStorage).filter(k=>k.startsWith('agora.tutorial.')&&k.endsWith('.tareas')).forEach(k=>localStorage.removeItem(k))")
            pg.goto(B+'/tareas');pg.wait_for_selector('#ag-tour .tour-card',timeout=20000);pg.keyboard.press('Escape');pg.wait_for_timeout(300)
            ok(pg.locator('#ag-tour').count()==0,f'{w}px: Escape lo salta')
            pg.locator('#b-acct').click();pg.wait_for_selector('[data-tutorial]',timeout=8000)
            pg.locator('[data-tutorial]').click();pg.wait_for_selector('#ag-tour .tour-card',timeout=8000)
            ok('Paso 1 de' in pg.inner_text('#ag-tour .tour-n'),f'{w}px: «Ver el tutorial de esta pantalla» lo repite desde el principio')
            pg.locator('#ag-tour .tour-saltar').click();pg.wait_for_timeout(300)
            ok(pg.locator('#ag-tour').count()==0 and not pg.errs,f'{w}px: «Saltar» lo cierra, sin errores {pg.errs[:2]}')
            pg.close()
            # Sin la marca de pruebas, las demás pruebas automáticas no lo ven
            pg=entrar(br,'admin.asocim@agora.test',w,h);pg.evaluate("localStorage.clear()");pg.goto(B+'/contactos');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_timeout(4000)
            ok(pg.locator('#ag-tour').count()==0,f'{w}px: no tapa las pantallas de las pruebas automáticas')
            pg.close()
    finally:
        br.close()
print(n,'comprobaciones')
