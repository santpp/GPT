#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.27.0 · Plantillas de WhatsApp por flujos de trabajo de la cuenta (pedido del usuario).
 1. Solo los dueños eligen, en Agencia → datos de la subcuenta, qué flujos envían plantillas (varios, con buscador por nombre).
 2. Lo envían todos los que pueden escribir en la Bandeja: Ágora mete al contacto en el flujo y la cuenta envía la plantilla.
 3. Seguimiento: cada pedido queda anotado, no se repite por doble clic ni antes de 10 minutos, y se marca cuando el mensaje aparece.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Deja la subcuenta sin flujos elegidos.
Uso: python3 tests/fixes-2270.py"""
import os,json,uuid,subprocess,urllib.request,re as _re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
SHOT=os.environ.get('SHOT','/tmp')
def sql(q):subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e',q],check=True)
def fake(path,body=None):
    r=urllib.request.Request('http://127.0.0.1:8099'+path,data=json.dumps(body).encode() if body is not None else None,method='POST' if body is not None else 'GET',headers={'Content-Type':'application/json','Authorization':'Bearer pit-pruebas'})
    with urllib.request.urlopen(r) as x:return json.loads(x.read())
rid=lambda:uuid.uuid4().hex
def esperar(pg,sel,texto,ms):
    for _ in range(ms//250):
        if pg.locator(sel).count() and texto in pg.inner_text(sel):return True
        pg.wait_for_timeout(250)
    return False
sql('update ghl_sync_state set request_not_before=0, failures=0');fake('/__flujos',{'limpiar':True})
limpiar();duena=C('duena@example.test','Clave-Local-2026!')
duena.call('agency/accounts/whatsapp?accountId=2') # instala las tablas nuevas del motor
sql('delete from whatsapp_flow_sends')
try:
    # ---------- 1. Agencia: elegir flujos (solo dueños) ----------
    ok(duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[]})[0]==200,'se puede dejar la subcuenta sin plantillas')
    st,r=duena.call('agency/accounts/whatsapp?accountId=2');ok(st==200 and r['flujos']==[] and r['maximo']==40,'sin flujos elegidos al empezar')
    st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2,'buscar':''})
    ok(st==200 and r['total']==63 and r['cuenta'][0]['estado']=='publicado' and r['cuenta'][-1]['estado']=='borrador','«Cargar flujos» trae los 63 de la cuenta, los borradores al final')
    st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2,'buscar':'recordatorio'});ok(st==200 and [w['id'] for w in r['cuenta']]==['wf-cita'],'el servidor también busca por nombre')
    st,r=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[{'id':'wf-borrador'}]});ok(st==422 and 'borrador' in r.get('error',''),'un borrador no se puede elegir')
    st,r=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[{'id':'wf-no-existe'}]});ok(st==422,'un flujo que no está en la cuenta no se puede elegir')
    st,r=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[{'id':'wf-bienvenida','nombre':'Bienvenida','texto':'¡Hola! Gracias por escribirnos.'},{'id':'wf-cita','nombre':'<b>Cita</b>'},{'id':'wf-bienvenida'}]})
    ok(st==200 and [(f['id'],f['nombre']) for f in r['flujos']]==[('wf-bienvenida','Bienvenida'),('wf-cita','Cita')],'se eligen varios: nombre visible propio, sin HTML y sin repetidos')
    ok(r['flujos'][1]['nombreCuenta']=='Ágora · Recordatorio de cita' and r['flujos'][1]['texto'] is None,'se guarda el nombre que tiene en la cuenta; el texto es opcional')
    st,_=duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[{'id':f'wf-relleno-{i:03d}'} for i in range(1,42)]});ok(st==422,'como mucho 40 flujos por subcuenta')
    limpiar();adm=C('admin.asocim@agora.test')
    ok(adm.call('agency/accounts/whatsapp?accountId=2')[0]==403 and adm.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[]})[0]==403,'la administración de la subcuenta no puede elegir flujos (solo dueños)')
    ok(adm.call('admin/integrations/2/whatsapp')[0] in (403,404,410),'la configuración vieja de WhatsApp ya no se usa')
    # ---------- 2. Bandeja: lo envían todos los que pueden escribir ----------
    st,t=adm.call('workspace/templates?connectionId=2');items=t.get('items',[])
    ok(st==200 and [x['id'] for x in items]==['flujo:wf-bienvenida','flujo:wf-cita'] and items[0]['flow'] and items[0]['preview'] and not items[1]['preview'],'la Bandeja ofrece los flujos elegidos como plantillas (con vista previa si hay texto)')
    limpiar();eje=C('ejecutivo1.asocim@agora.test');st,t=eje.call('workspace/templates?connectionId=2');ok(st==200 and len(t.get('items',[]))==2,'un ejecutivo que escribe también las ve')
    limpiar();con=C('consulta.asocim@agora.test');ok(con.call('workspace/templates?connectionId=2')[0]==403,'consulta (solo lectura) no')
    q1=rid();body={'channel':'WhatsApp','template':{'id':'flujo:wf-bienvenida','values':{}},'requestId':q1}
    st,r=eje.call('workspace/send?connectionId=2&conversationId=c0','POST',body)
    ok(st==200 and r.get('state')=='requested' and r.get('flow') and r.get('label')=='Bienvenida','enviar: Ágora pide la plantilla a la cuenta')
    f=fake('/__flujos');ok([(x['contactId'],x['workflowId']) for x in f['flujos']]==[('k0','wf-bienvenida')] and 'eventStartTime' in f['flujos'][0]['body'],'la cuenta recibe al contacto de la conversación en el flujo correcto')
    st,r2=eje.call('workspace/send?connectionId=2&conversationId=c0','POST',body);ok(st==200 and r2['requestId']==q1 and len(fake('/__flujos')['flujos'])==1,'el mismo pedido repetido (doble clic) no lo envía dos veces')
    st,r=eje.call('workspace/send?connectionId=2&conversationId=c0','POST',{**body,'requestId':rid()});ok(st==409 and r.get('code')=='repeat' and 'minutos' in r.get('error',''),'la misma plantilla al mismo contacto no antes de 10 minutos')
    st,r=adm.call('workspace/send?connectionId=2&conversationId=c1','POST',{**body,'channel':'SMS','requestId':rid()});ok(st==422 and r.get('code')=='unsupported_channel','las plantillas solo salen por WhatsApp')
    st,r=eje.call('workspace/send?connectionId=2&conversationId=c0','POST',{**body,'template':{'id':'flujo:wf-relleno-001','values':{}},'requestId':rid()});ok(st==422 and r.get('code')=='template_unknown','un flujo que el dueño no eligió no se puede usar')
    st,r=con.call('workspace/send?connectionId=2&conversationId=c0','POST',{**body,'requestId':rid()});ok(st==403,'consulta no puede enviar')
    st,r=adm.call('workspace/send?connectionId=2&conversationId=c0','POST',{**body,'template':{'id':'flujo:wf-cita','values':{}},'requestId':rid()});ok(st==200 and r['state']=='requested','otra plantilla al mismo contacto sí')
    # ---------- 3. Seguimiento ----------
    st,m=adm.call('workspace/messages?connectionId=2&conversationId=c0&refresh=1')
    p=m.get('plantillas',{});ok(st==200 and [v['label'] for v in p.get('vistas',[])]==['Bienvenida'] and [x['label'] for x in p.get('pendientes',[])]==['Cita'],f'al leer la conversación: «Bienvenida» ya salió, «Cita» aún no aparece ({p})')
    ok(str(m.get('autores',{}).get(p['vistas'][0]['messageId'],'')).startswith('Paola'),'el mensaje de la plantilla queda a nombre de quien la pidió')
    st,r=duena.call('agency/accounts/whatsapp?accountId=2');ok(r['resumen']=={'pedidas':2,'vistas':1},'Agencia ve cuántas se pidieron y cuántas ya aparecieron (30 días)')
    fake('/__flujos',{'sin_permiso':True});st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2,'buscar':''});fake('/__flujos',{'sin_permiso':False})
    ok(st==424 and 'permiso de flujos' in r.get('error',''),'sin el permiso de flujos, un aviso claro de qué falta')
    sql('update ghl_sync_state set request_not_before=0, failures=0')
    ok(not _re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL|workflow',json.dumps(r,ensure_ascii=False)),'los avisos no nombran al proveedor ni términos técnicos')
    # ---------- Pantallas ----------
    sql('delete from whatsapp_flow_sends')
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w,h in ((1280,800),(390,844)):
                limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
                pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
                pg.goto(B+'/agencia/subcuenta/2');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('[data-acc-editar]',timeout=15000)
                pg.click('[data-acc-editar]');pg.wait_for_selector('#bx-whatsapp .wa-fila',timeout=10000)
                ok(pg.locator('#bx-whatsapp .wa-fila').count()==2 and pg.input_value('#bx-whatsapp .wa-fila >> nth=0 >> [data-wa-nombre]')=='Bienvenida',f'{w}px: la ficha de la subcuenta muestra las plantillas elegidas')
                pg.click('#bx-whatsapp [data-wa-cargar]');pg.wait_for_selector('#bx-whatsapp .wa-lista',timeout=15000)
                ok('63 flujos en la cuenta' in pg.inner_text('#bx-whatsapp [data-wa-cuenta]'),f'{w}px: «Cargar flujos» los lista')
                pg.fill('#bx-whatsapp [data-wa-q]','SEGUIMIENTO automatico 00');pg.wait_for_timeout(300)
                ok(pg.locator('#bx-whatsapp .wa-lista li').count()==9 and '9 de 63' in pg.inner_text('#bx-whatsapp [data-wa-cuenta]'),f'{w}px: el buscador filtra por nombre (sin importar mayúsculas ni tildes)')
                pg.locator('#bx-whatsapp [data-wa-id="wf-relleno-003"]').check();pg.wait_for_timeout(200)
                ok(pg.locator('#bx-whatsapp .wa-fila').count()==3,f'{w}px: marcar un flujo lo agrega a las plantillas')
                pg.fill('#bx-whatsapp [data-wa-q]','borrador');pg.wait_for_timeout(300)
                ok(pg.locator('#bx-whatsapp [data-wa-id="wf-borrador"]').is_disabled(),f'{w}px: los borradores se ven pero no se pueden marcar')
                pg.locator('#bx-whatsapp .wa-fila >> nth=2 >> [data-wa-nombre]').fill('Seguimiento 3');pg.click('#bx-whatsapp [data-wa-guardar]');pg.wait_for_timeout(1500)
                st,r=duena.call('agency/accounts/whatsapp?accountId=2');ok([f['nombre'] for f in r['flujos']]==['Bienvenida','Cita','Seguimiento 3'],f'{w}px: «Guardar plantillas» guarda la elección')
                if w==1280:pg.locator('#bx-whatsapp').screenshot(path=SHOT+'/wa-flujos.png')
                else:pg.locator('#bx-whatsapp').screenshot(path=SHOT+'/wa-flujos-tel.png')
                pg.locator('#bx-whatsapp [data-wa-quitar="2"]').click();pg.click('#bx-whatsapp [data-wa-guardar]');pg.wait_for_timeout(1500)
                ok(len(duena.call('agency/accounts/whatsapp?accountId=2')[1]['flujos'])==2,f'{w}px: «Quitar» la saca de las plantillas')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                txt=pg.inner_text('#bx-whatsapp');ok(not _re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',txt),f'{w}px: sin nombres del proveedor')
                pg.close()
                # Bandeja: un ejecutivo pide la plantilla y ve cuando sale
                sql('delete from whatsapp_flow_sends')
                pg=entrar(br,'ejecutivo1.asocim@agora.test',w,h);pg.goto(B+'/bandeja');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                pg.locator('[data-live-id="c2"]').first.click();pg.wait_for_selector('#live-template-toggle',state='attached',timeout=15000);pg.wait_for_timeout(1500)
                pg.click('#live-template-toggle');pg.wait_for_selector('#tpl-select',timeout=10000)
                ok(pg.locator('#tpl-select option').all_inner_texts()[1:]==['Bienvenida','Cita'],f'{w}px: «Usar plantilla» ofrece las plantillas de la subcuenta')
                pg.select_option('#tpl-select','flujo:wf-bienvenida');pg.wait_for_timeout(300)
                ok('Gracias por escribirnos' in pg.inner_text('.tpl-preview'),f'{w}px: con su vista previa')
                if w==390:pg.screenshot(path=SHOT+'/wa-bandeja-tel.png')
                pg.click('#tpl-send');ok(esperar(pg,'#live-send-status','pedida a la cuenta',10000),f'{w}px: avisa que la plantilla se pidió a la cuenta')
                ok(fake('/__flujos')['flujos'][-1]=={'contactId':'k2','workflowId':'wf-bienvenida','body':fake('/__flujos')['flujos'][-1]['body']},f'{w}px: la cuenta recibe el pedido')
                ok(esperar(pg,'#live-send-status','Plantilla enviada',30000),f'{w}px: a los pocos segundos dice «Plantilla enviada»')
                ok('¡Hola! Gracias por escribirnos.' in pg.inner_text('#live-log'),f'{w}px: y el mensaje aparece en la conversación')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                pg.close()
        finally:br.close()
finally:
    limpiar();sql('delete from whatsapp_flow_sends');fake('/__flujos',{'sin_permiso':False});duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'flujos':[]})
print(n,'comprobaciones')
