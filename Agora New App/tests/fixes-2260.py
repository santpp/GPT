#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.26.0 · Plantillas de WhatsApp como dice la documentación de la cuenta (el usuario no lograba verlas):
 - Se piden con originId = identificador de la subcuenta (Location ID), no con el número de WhatsApp. El número ya no hace falta.
 - Si no llega ninguna de WhatsApp, se piden todas (sin filtro) para saber si la conexión lee plantillas, y se explica.
 - Al enviar una plantilla se manda el campo documentado templateId; el número que envía es opcional.
La cuenta simulada responde 422 si originId no es el Location ID, como la real. Requiere la API local en :8090.
Desde 2.27.0 las plantillas se envían por flujos de trabajo (ver fixes-2270.py): aquí solo queda «Activar protección».
Uso: python3 tests/fixes-2260.py"""
import os,json,urllib.request,uuid
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def fake(path,body=None):
    r=urllib.request.Request('http://127.0.0.1:8099'+path,data=json.dumps(body).encode() if body is not None else None,method='POST' if body is not None else 'GET',headers={'Authorization':'Bearer pit-prueba','Content-Type':'application/json'})
    with urllib.request.urlopen(r) as x:return json.loads(x.read())
# 2.27.0: la lectura de plantillas se cambió por flujos de trabajo (tests/fixes-2270.py); de esta versión queda la corrección de «Activar protección».
limpiar();adm=C('admin.asocim@agora.test')
try:
    # ---------- encontrado en el repaso: «Activar protección» de facturación respondía 500 ----------
    import subprocess
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','DROP TRIGGER IF EXISTS trg_afline_before_delete'])
    st,r=adm.call('workspace/fiscal/proteccion','POST',{})
    hay=subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','agora_erp','-e',"select count(*) from information_schema.triggers where trigger_schema=database() and trigger_name='trg_afline_before_delete'"],capture_output=True,text=True).stdout.strip()
    ok(st==200 and r.get('ok') is True and hay=='1','«Activar protección» vuelve a poner lo que falta y responde bien (antes daba 500 aunque la instalaba)')
finally:
    limpiar()
print(n,'comprobaciones')
