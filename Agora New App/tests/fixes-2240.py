#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.24.0 · Pedidos del usuario:
 1. Sin botón para crear usuarios de agencia (los dueños se crean por terminal).
 2. Ágora no pone ni genera claves: desde Agencia también se invita (correo + código), y «restablecer» manda un enlace para que la
    persona cree una clave nueva (su clave actual sigue sirviendo). Las claves de los dueños, solo por terminal.
 3. Ficha del contacto (Contactos y Bandeja) con el lenguaje del ERP: títulos en tipo oración, «Correo» y «Embudo», cabecera que no
    corta el nombre, sin desborde en el teléfono.
Requiere la API local en :8090 y la cuenta simulada. Deja el canal de correo apagado y borra las personas de prueba.
Uso: python3 tests/fixes-2240.py"""
import os,json,subprocess,urllib.request,re as _re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','-r','agora_erp','-e',q],capture_output=True,text=True).stdout.strip()
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'});return json.loads(urllib.request.urlopen(r).read())['sent']
def borrar():sql("delete from agora_user_links where user_id in (select id from agora_users where email like '%.inv2240@agora.test');delete from agora_users where email like '%.inv2240@agora.test'")
borrar();limpiar()
duena=C('duena@example.test','Clave-Local-2026!');duena.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':False})
SHOT=os.environ.get('SHOT','/tmp')
try:
    # ---------- API: invitar desde Agencia, sin clave ----------
    st,r=duena.call('agency/users','POST',{'accountId':2,'nombre':'Agencia Invitada','correo':'ag.inv2240@agora.test','rol':'EJECUTIVO','clave':'Intento-De-Clave-2026!'})
    ok(st==200 and r['invitacion']['enviada'],'desde Agencia se invita a una persona de la subcuenta y le llega el correo')
    ok(sql("select password_hash='!invitacion' from agora_users where email='ag.inv2240@agora.test'")=='1','no se guarda ninguna clave, aunque la manden: queda como invitación pendiente')
    ok(sql("select local_tenant_id from agora_users where email='ag.inv2240@agora.test'")=='2','queda en la subcuenta elegida')
    st,l=duena.call('agency/usuarios');u=next(x for x in l['items'] if x['correo']=='ag.inv2240@agora.test')
    ok(u['invitacion'] is True,'Agencia → Gestión de usuarios la marca como «Invitación pendiente»')
    # ---------- API: «restablecer» = enlace, no clave ----------
    eje=next(x for x in l['items'] if x['correo']=='ejecutivo2.asocim@agora.test')
    antes=sql(f"select password_hash from agora_users where id={eje['id']}");nb=len(buzon())
    st,d=duena.call('agency/usuarios/clave','POST',{'id':eje['id'],'generar':True})
    ok(st==200 and d['tipo']=='clave' and d['invitacion']['enviada'] and 'temporal' not in d,'«restablecer» ya no genera clave: manda el enlace para crear una nueva')
    ok(sql(f"select password_hash from agora_users where id={eje['id']}")==antes,'su clave actual no se toca (sigue entrando hasta que cree la nueva)')
    m=buzon()[-1];ok(len(buzon())==nb+1 and 'Crear una clave nueva' in (m.get('html','')+m.get('message','')) and '/entrar?invitacion=ejecutivo2.asocim%40agora.test' in (m.get('html','')+m.get('message','')),'el correo trae «Crear una clave nueva» con su enlace')
    st,d=duena.call('agency/usuarios/clave','POST',{'id':u['id']});ok(st==200 and d['tipo']=='invitacion','a quien no creó su clave se le reenvía la invitación')
    socio=[x for x in l['items'] if x['rol'] in('MASTER','SUPERADMIN') and not x.get('yo')]
    if socio:ok(duena.call('agency/usuarios/clave','POST',{'id':socio[0]['id']})[0]==403,'la clave de un dueño sigue siendo solo por terminal')
    limpiar();adm=C('admin.asocim@agora.test')
    st,d=adm.call('workspace/usuarios/invitacion','POST',{'id':eje['id']});ok(st==200 and d['tipo']=='clave','en la subcuenta, Administración también manda el enlace para crear una clave nueva')
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w,h in ((1280,800),(390,844)):
                # ---------- Agencia: sin «usuario de agencia», invitar sin clave ----------
                pg=entrar(br,'duena@example.test',w,h) if False else None
                limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
                pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
                pg.goto(B+'/agencia/usuarios');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('[data-usr-nuevo]',timeout=15000)
                t=pg.inner_text('main#app')
                ok(pg.locator('[data-team-new]').count()==0 and 'Usuario de agencia' not in t and 'Agregar usuario de agencia' not in t,f'{w}px: Agencia ya no tiene botón para crear usuarios de agencia')
                pg.click('[data-usr-nuevo]');pg.wait_for_selector('#layer form',timeout=8000)
                ok(pg.locator('#layer input[type=password], #layer input[name=clave]').count()==0 and 'Invitar persona' in pg.inner_text('#layer'),f'{w}px: «Invitar persona» desde Agencia no pide clave')
                if w==1280:pg.locator('#layer .modal, #layer [role=dialog]').first.screenshot(path=SHOT+'/ag-invitar.png')
                pg.keyboard.press('Escape');pg.wait_for_timeout(300)
                if pg.locator('#layer [data-close]').count():pg.locator('#layer [data-close]').first.click();pg.wait_for_timeout(300)
                pg.locator(f'[data-usr-editar="{eje["id"]}"]').first.click();pg.wait_for_selector('#layer form',timeout=8000)
                ok(pg.locator('#layer input[name=clave], [data-usr-generar]').count()==0 and pg.locator('[data-usr-enlace]').count()==1,f'{w}px: al editar, no hay campo de clave ni «generar»: solo «Enviar enlace para crear una clave nueva»')
                ok(not _re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',pg.inner_text('body')) and not pg.errs,f'{w}px: sin nombres del proveedor ni errores {pg.errs[:2]}')
                pg.close()
                # ---------- Ficha del contacto ----------
                pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/contactos');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('.record-row')
                pg.locator('.record-row').first.click();pg.wait_for_selector('#contact-record .record-header h2',timeout=10000);pg.wait_for_timeout(800)
                tt=pg.evaluate("[...document.querySelectorAll('#contact-record .section-label')].map(e=>[e.textContent.trim(),getComputedStyle(e).textTransform])")
                ok(tt and all(x[1]=='none' for x in tt),f'{w}px Contactos: títulos en tipo oración, sin mayúsculas {[x[0] for x in tt]}')
                ok(pg.evaluate("(()=>{const a=[...document.querySelectorAll('#contact-record .record-actions button')];return a.length>0&&a.every(b=>b.getBoundingClientRect().height<48)})()"),f'{w}px Contactos: los botones de la ficha no se parten en dos líneas')
                ok('Sin valores todavía' not in pg.inner_text('#contact-record'),f'{w}px Contactos: sin «Campos personalizados / Sin valores todavía» repetido')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w,f'{w}px Contactos: sin desborde')
                if w==1280:pg.locator('#contact-record').screenshot(path=SHOT+'/ficha-contactos.png')
                pg.goto(B+'/bandeja');pg.wait_for_selector('[data-live-id="c0"]');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                pg.locator('[data-live-id="c0"]').first.click();pg.wait_for_selector('#live-contact-toggle');pg.click('#live-contact-toggle');pg.wait_for_selector('.live-contact-panel .lp-quien h2',timeout=10000);pg.wait_for_timeout(800)
                txt=pg.inner_text('.live-contact-panel')
                ok('Correo' in txt and not _re.search(r'\bEmail\b',txt) and 'Pipeline' not in txt and 'Contexto del contacto' not in txt.replace('\n',' '),f'{w}px Bandeja: «Correo» y «Embudo», sin el rótulo repetido')
                tt=pg.evaluate("[...document.querySelectorAll('.live-contact-panel .section-label')].map(e=>getComputedStyle(e).textTransform)")
                ok(tt and all(x=='none' for x in tt),f'{w}px Bandeja: títulos en tipo oración')
                ok(pg.evaluate("(()=>{const h=document.querySelector('.live-contact-panel .lp-quien h2');return h.scrollWidth<=h.clientWidth+1})()"),f'{w}px Bandeja: el nombre no se corta')
                ok(pg.locator('.live-contact-panel .live-profile-controls button',has_text='Asignar').count()==1,f'{w}px Bandeja: «Asignar» sigue en la ficha')
                pr=pg.evaluate("(()=>{const p=document.querySelector('.live-contact-panel').getBoundingClientRect();return [p.left,p.right]})()")
                ok(pr[0]>=-1 and pr[1]<=w+1 and pg.evaluate('document.documentElement.scrollWidth')<=w,f'{w}px Bandeja: la ficha cabe en la pantalla {pr}')
                if w==390:pg.screenshot(path=SHOT+'/ficha-bandeja-tel.png')
                ok(not pg.errs,f'{w}px: sin errores {pg.errs[:2]}')
                pg.close()
        finally:br.close()
finally:
    limpiar();duena.call('agency/correo','PUT',{'connectionId':None});borrar()
print(n,'comprobaciones')
