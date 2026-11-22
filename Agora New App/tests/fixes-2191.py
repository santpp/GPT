#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.19.1 · Bandeja (pedido del usuario): cada mensaje saliente dice quién lo envió.
Las iniciales de la persona (si lo envió desde Ágora o desde la cuenta) o un ícono de automatización (flujos, campañas, envíos
masivos, integraciones). Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/fixes-2191.py"""
import os,uuid
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
a=C('admin.asocim@agora.test');cid=2
st,d=a.call(f'workspace/messages?connectionId={cid}&conversationId=c0')
ok(st==200 and d.get('equipo',{}).get('u-admin')=='Valentina Castillo','al leer la conversación llegan los nombres del equipo de la cuenta')
out=[m for m in d['items'] if m['direction']=='outbound']
ok(any(m.get('userId')=='u-admin' for m in out),'el saliente de la cuenta trae su usuario')
rid=uuid.uuid4().hex
st,r=a.call(f'workspace/send?connectionId={cid}&conversationId=c0','POST',{'message':'Te escribo desde Ágora','channel':'WhatsApp','requestId':rid})
ok(st==200 and r.get('messageId'),'se envía un mensaje desde Ágora')
st,d=a.call(f'workspace/messages?connectionId={cid}&conversationId=c0&refresh=1')
nombre=d.get('autores',{}).get(r['messageId'])
ok(nombre and nombre.strip()!='','el servidor recuerda quién envió desde Ágora ('+str(nombre)+')')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for w,h in ((1280,800),(390,844)):
            pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/bandeja');pg.wait_for_selector('[data-live-id="c0"]',timeout=20000)
            pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
            pg.locator('[data-live-id="c0"]').first.click();pg.wait_for_selector('#live-log .bubble.out .msg-who',timeout=15000);pg.wait_for_timeout(800)
            quien=pg.evaluate("[...document.querySelectorAll('#live-log .bubble.out')].map(b=>{const q=b.querySelector('.msg-who');return [b.textContent.slice(0,40),q?q.textContent.trim():null,q?q.getAttribute('title'):null,q?q.className:null]})")
            ok(any(q[1]=='VC' and 'Valentina Castillo' in q[2] for q in quien),f'{w}px: el saliente del usuario de la cuenta muestra sus iniciales (VC) {quien[:2]}')
            ok(any('Te escribo desde Ágora' in q[0] and q[1]==''.join(p[0] for p in [nombre.split()[0],nombre.split()[-1]]).upper() and 'Enviado por '+nombre in q[2] and 'msg-auto' not in q[3] for q in quien),f'{w}px: el enviado desde Ágora muestra las iniciales de quien lo envió')
            ok(all(q[1] is not None for q in quien),f'{w}px: todos los salientes dicen quién los envió')
            if not pg.locator('[data-live-id="c1"]').first.is_visible():pg.goto(B+'/bandeja');pg.wait_for_selector('[data-live-id="c1"]',state='visible',timeout=20000)
            pg.locator('[data-live-id="c1"]').first.click();pg.wait_for_selector('#live-log .bubble.out .msg-auto',timeout=15000)
            t=pg.locator('#live-log .bubble.out .msg-auto').first
            ok(t.locator('svg').count()==1 and 'automatización' in t.get_attribute('title'),f'{w}px: el saliente de una automatización muestra el ícono de automatización')
            ok(pg.locator('#live-log .bubble.in .msg-who, #live-log .bubble:not(.out) .msg-who').count()==0,f'{w}px: los entrantes no llevan marca')
            if w==1280:pg.locator('[data-live-id="c0"]').first.click();pg.wait_for_timeout(1500);pg.locator('#live-log').screenshot(path=os.environ.get('SHOT','/tmp')+'/quien.png')
            txt=pg.inner_text('main#app');ok(not re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',txt),f'{w}px: sin nombres del proveedor')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
            pg.close()
    finally:
        br.close()
print(n,'comprobaciones')
