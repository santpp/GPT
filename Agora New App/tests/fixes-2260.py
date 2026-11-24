#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.26.0 · Plantillas de WhatsApp como dice la documentación de la cuenta (el usuario no lograba verlas):
 - Se piden con originId = identificador de la subcuenta (Location ID), no con el número de WhatsApp. El número ya no hace falta.
 - Si no llega ninguna de WhatsApp, se piden todas (sin filtro) para saber si la conexión lee plantillas, y se explica.
 - Al enviar una plantilla se manda el campo documentado templateId; el número que envía es opcional.
La cuenta simulada responde 422 si originId no es el Location ID, como la real. Requiere la API local en :8090.
Uso: python3 tests/fixes-2260.py"""
import os,json,urllib.request,uuid
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def fake(path,body=None):
    r=urllib.request.Request('http://127.0.0.1:8099'+path,data=json.dumps(body).encode() if body is not None else None,method='POST' if body is not None else 'GET',headers={'Authorization':'Bearer pit-prueba','Content-Type':'application/json'})
    with urllib.request.urlopen(r) as x:return json.loads(x.read())
limpiar();duena=C('duena@example.test','Clave-Local-2026!')
antes=duena.call('agency/accounts/whatsapp?accountId=2')[1]
try:
    duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':None})
    st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2})
    ok(st==200 and [t['nombre'] for t in r['plantillas']]==['bienvenida','seguimiento_cita'],'sin número guardado, «Comprobar» trae las plantillas aprobadas')
    ok(r['diagnostico']['origin']=='location','se piden con el identificador de la subcuenta (la cuenta simulada rechaza cualquier otro, como la real)')
    fake('/__sin_wa',{'on':True})
    try:
        st,r=duena.call('agency/accounts/whatsapp','POST',{'accountId':2})
        ok(st==200 and r['diagnostico']['received']==0 and r['diagnostico']['otras']==1,'si no hay de WhatsApp, prueba sin filtro y sabe que la conexión sí lee plantillas (1 de SMS)')
    finally:fake('/__sin_wa',{'on':False})
    # ---------- enviar una plantilla desde la Bandeja ----------
    limpiar();adm=C('admin.asocim@agora.test')
    adm.call('workspace/contact?connectionId=2&conversationId=c0')
    st,t=adm.call('workspace/templates?connectionId=2&refresh=1');tpl=next((x for x in t.get('items',[]) if x['name']=='bienvenida'),None)
    ok(st==200 and t.get('configured') is True and tpl and tpl['id']=='wa-tpl-bienvenida','la Bandeja ve las plantillas sin número guardado, con su identificador')
    nb=len(fake('/__sent')['sent'])
    st,r=adm.call('workspace/send?connectionId=2&conversationId=c0','POST',{'channel':'WhatsApp','requestId':uuid.uuid4().hex,'template':{'id':'wa-tpl-bienvenida','values':{'body':{'1':'Lucía'}}}})
    env=fake('/__sent')['sent']
    ok(st==200 and len(env)==nb+1,f'se envía la plantilla ({st} {r if st!=200 else ""})')
    p=env[-1]
    ok(p.get('type')=='WhatsApp' and p.get('templateId')=='wa-tpl-bienvenida' and p.get('contactId'),'el envío lleva el campo documentado templateId')
    ok('Hola Lucía' in (p.get('message') or '') and 'fromNumberId' not in (p.get('whatsapp') or {}),'con la variable rellena y sin número (la cuenta usa el suyo)')
    duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':'1234567890'})
    nb=len(fake('/__sent')['sent'])
    adm.call('workspace/send?connectionId=2&conversationId=c0','POST',{'channel':'WhatsApp','requestId':uuid.uuid4().hex,'template':{'id':'wa-tpl-bienvenida','values':{'body':{'1':'Lucía'}}}})
    ok((fake('/__sent')['sent'][-1].get('whatsapp') or {}).get('fromNumberId')=='1234567890','con número guardado, se envía desde ese número')
    # ---------- encontrado en el repaso: «Activar protección» de facturación respondía 500 ----------
    import subprocess
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','DROP TRIGGER IF EXISTS trg_afline_before_delete'])
    st,r=adm.call('workspace/fiscal/proteccion','POST',{})
    hay=subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','agora_erp','-e',"select count(*) from information_schema.triggers where trigger_schema=database() and trigger_name='trg_afline_before_delete'"],capture_output=True,text=True).stdout.strip()
    ok(st==200 and r.get('ok') is True and hay=='1','«Activar protección» vuelve a poner lo que falta y responde bien (antes daba 500 aunque la instalaba)')
finally:
    limpiar();duena.call('agency/accounts/whatsapp','PUT',{'accountId':2,'numberId':antes.get('numberId')})
print(n,'comprobaciones')
