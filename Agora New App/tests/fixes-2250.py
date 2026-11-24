#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.25.0 · Pedidos del usuario:
 1. El manual del ERP dentro de Ayuda (con su PDF para descargar).
 2. Ícono de Ayuda en la cabecera: repetir el tutorial de la pantalla, ver el manual y descargarlo.
 3. Plantillas de WhatsApp en la ficha de la subcuenta (Agencia): guardar el número y comprobar con la cuenta, para verificar la conexión.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada; plantillas con el número 1234567890). Deja el número como estaba.
Uso: python3 tests/fixes-2250.py"""
import os,json,urllib.request,re as _re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
limpiar();duena=C('duena@example.test','Clave-Local-2026!')
antes=duena.call('agency/accounts/whatsapp?accountId=2')[1]
SHOT=os.environ.get('SHOT','/tmp')
try:
    # ---------- API: plantillas de WhatsApp de la subcuenta ----------
    st,r=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':None,'wabaId':None})
    ok(st==200 and r['numberId'] is None,'se puede dejar la subcuenta sin número')
    st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2});ok(st==409 and 'Escríbelo y guarda' in r.get('error',''),'comprobar sin número explica qué falta')
    ok(duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':'abc'})[0]==422,'el identificador del número solo admite cifras')
    st,r=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':'1234567890','wabaId':None});ok(st==200 and r['numberId']=='1234567890','se guarda el número en la conexión de la subcuenta')
    st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2})
    ok(st==200 and [t['nombre'] for t in r['plantillas']]==['bienvenida','seguimiento_cita'],'«Comprobar» pide las plantillas a la cuenta y devuelve las aprobadas')
    ok(r['diagnostico']['received']==3 and r['diagnostico']['dropped']=={'status_rejected':1},'y dice cuántas llegaron y cuáles quedaron fuera (1 rechazada)')
    limpiar();adm=C('admin.asocim@agora.test')
    ok(adm.call('agency/accounts/whatsapp?accountId=2')[0]==403,'solo los dueños usan esta ruta de Agencia')
    st,t=adm.call('workspace/templates?connectionId=2&refresh=1');nombres=[x.get('name') for x in (t.get('items') or t.get('templates') or [])]
    ok(st==200 and 'bienvenida' in nombres,f'la Bandeja ya ofrece esas plantillas ({nombres})')
    # ---------- El PDF va en el paquete ----------
    with urllib.request.urlopen(B+'/assets/manual-erp.pdf') as x:ok(x.status==200 and x.read(5)==b'%PDF-','el manual en PDF se descarga desde la app')
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w,h in ((1280,800),(390,844)):
                pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/bandeja');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('#b-ayuda',timeout=10000)
                ok(pg.locator('#b-ayuda').is_visible(),f'{w}px: el ícono de Ayuda está en la cabecera')
                pg.click('#b-ayuda');pg.wait_for_selector('.ayuda-pop',timeout=5000)
                t=pg.inner_text('.ayuda-pop')
                ok('Ver el tutorial de esta pantalla' in t and 'Ver el manual' in t and 'Descargar el manual (PDF)' in t,f'{w}px: Ayuda ofrece tutorial, manual y PDF')
                if w==390:pg.screenshot(path=SHOT+'/ayuda-pop-tel.png')
                pg.click('.ayuda-pop [data-ay="tutorial"]');pg.wait_for_selector('#ag-tour .tour-card',timeout=8000)
                ok('Paso 1 de' in pg.inner_text('#ag-tour .tour-n'),f'{w}px: «Ver el tutorial» lo abre desde el principio')
                pg.keyboard.press('Escape');pg.wait_for_timeout(300)
                pg.goto(B+'/cobros');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.click('#b-ayuda');pg.wait_for_selector('.ayuda-pop')
                ok(pg.locator('.ayuda-pop [data-ay="tutorial"]').count()==0 and 'no tiene tutorial' in pg.inner_text('.ayuda-pop'),f'{w}px: donde no hay tutorial, lo dice en vez de ofrecerlo')
                pg.click('.ayuda-pop [data-ay="manual"]');pg.wait_for_selector('#view .manual h1',timeout=15000);pg.wait_for_timeout(1200)
                ok('/ayuda' in pg.url and pg.inner_text('#view .manual h1')=='Agora Digital App - ERP',f'{w}px: «Ver el manual» abre Ayuda con el manual del ERP')
                imgs=pg.evaluate("[...document.querySelectorAll('#view .manual img')].map(i=>[i.getAttribute('src'),i.naturalWidth])")
                ok(len(imgs)>=14 and all(x[1]>0 for x in imgs),f'{w}px: las capturas del manual cargan ({len(imgs)})')
                ok(pg.locator('main#app .mod-h a[href$="manual-erp.pdf"]',has_text='Descargar').count()==1 and pg.locator('#view .manual-actions').count()==0,f'{w}px: Ayuda tiene un solo «Descargar» (el PDF) en la línea del título')
                txt=pg.inner_text('#view');ok('vendedores' not in txt.lower() and not _re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',txt),f'{w}px: es el manual del ERP, sin la guía vieja ni nombres del proveedor')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                if w==1280:pg.screenshot(path=SHOT+'/ayuda-manual.png')
                pg.close()
                # ---------- Agencia → subcuenta: plantillas de WhatsApp ----------
                limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
                pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
                pg.goto(B+'/agencia/subcuenta/2');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('[data-acc-editar]',timeout=15000)
                pg.click('[data-acc-editar]');pg.wait_for_selector('#bx-whatsapp',timeout=10000);pg.wait_for_timeout(1200)
                ok(pg.input_value('#bx-whatsapp [data-wa-numero]')=='1234567890',f'{w}px: la ficha de la subcuenta muestra el número guardado')
                pg.click('#bx-whatsapp [data-wa-comprobar]');pg.wait_for_selector('#bx-whatsapp .wa-chips',timeout=15000)
                t=pg.inner_text('#bx-whatsapp')
                ok('bienvenida (es)' in t and 'se pueden usar' in t and 'rechazadas' in t,f'{w}px: «Comprobar plantillas» muestra las que se pueden usar y las rechazadas')
                if w==1280:pg.locator('#bx-whatsapp').screenshot(path=SHOT+'/wa-subcuenta.png')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                pg.close()
        finally:br.close()
finally:
    limpiar();duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':antes.get('numberId'),'wabaId':antes.get('accountId')})
print(n,'comprobaciones')
