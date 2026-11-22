#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App · las tareas creadas en Ágora se escriben en la cuenta y no salen dobles.
Necesita lo mismo que tests/motor-puente.py y haberlo corrido antes (deja ASOCIM conectada).
Uso: python3 tests/tareas.py"""
import json,uuid,urllib.request,http.cookiejar
B='http://127.0.0.1:8090/api/';n=0
def ok(c,m):
    global n
    if not c:raise SystemExit('FALLA: '+m)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw='Agora-Pruebas-2026!'):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':user,'clave':pw})
        if st!=200:raise SystemExit(f'FALLA: {user} no entra ({st})')
        s.ws=d.get('activeTenantId')
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(B+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            t=e.read()
            try:return e.code,json.loads(t)
            except Exception:return e.code,{}
def cuenta():
    r=urllib.request.Request('http://127.0.0.1:8099/__tasks',headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['tasks']
a=C('admin.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test');cid=a.ws
antes=len(cuenta())
def locales(c):
    out,cur=[],0
    while True:
        d=c.call(f'workspace/tareas?cursor={cur}')[1];out+=d.get('items',[])
        if not d.get('hasMore') or not d.get('nextCursor'):return out
        cur=d['nextCursor']
ok(a.call('workspace/tareas','POST',{'titulo':'Sin contacto','vence':'2026-12-01','hora':'10:00'})[0] in (400,422),'en una subcuenta conectada no se guarda una tarea sin contacto')
ok(len(cuenta())==antes,'y nada llega a la cuenta')
rid=uuid.uuid4().hex
st,d=a.call('workspace/tareas','POST',{'titulo':'Llamar para confirmar','detalle':'Antes del viernes','vence':'2026-12-01','hora':'10:00','tz':'-04:00','contactId':'k1','contacto':'Marcos Ortega','requestId':rid})
ok(st==200 and d.get('id'),'la administradora crea una tarea en Ágora con su contacto')
tid=d['id'];c=cuenta()
ok(len(c)==antes+1 and c[-1]['contactId']=='k1' and c[-1]['title']=='Llamar para confirmar' and c[-1]['dueDate']=='2026-12-01T14:00:00Z','la tarea queda escrita en la ficha del contacto en la cuenta, con su hora local convertida')
st,d2=a.call('workspace/tareas','POST',{'titulo':'Llamar para confirmar','detalle':'Antes del viernes','vence':'2026-12-01','hora':'10:00','tz':'-04:00','contactId':'k1','contacto':'Marcos Ortega','requestId':rid})
ok(len(cuenta())==antes+1 and d2.get('id')==tid,'pulsar guardar dos veces no crea dos tareas')
mia=[t for t in locales(a) if t.get('id')==tid]
ok(len(mia)==1 and mia[0].get('contacto')=='Marcos Ortega' and mia[0].get('cuentaTareaId')==c[-1]['id'],'en Tareas sale una sola vez, con el nombre del contacto')
ok(a.call(f'workspace/tareas/{tid}/estado','POST',{'estado':'hecha'})[0]==200 and cuenta()[-1]['completed'] is True,'marcarla como hecha en Ágora la completa en la cuenta')
ok(a.call(f'workspace/tareas/{tid}/estado','POST',{'estado':'pendiente'})[0]==200 and cuenta()[-1]['completed'] is False,'reabrirla en Ágora la reabre en la cuenta')
st,_=a.call(f'workspace/tareas/{tid}','PUT',{'titulo':'Llamar y enviar propuesta','detalle':'','vence':'2026-12-02','hora':'09:30','tz':'-04:00'})
ok(st==200 and cuenta()[-1]['title']=='Llamar y enviar propuesta' and cuenta()[-1]['dueDate']=='2026-12-02T13:30:00Z','corregir título y fecha en Ágora los corrige en la cuenta')
# Desde la ficha del contacto (Bandeja): una sola tarea en los dos sitios.
n0=len(locales(a))
st,d=a.call(f'workspace/activity/task?connectionId={cid}&conversationId=c0','POST',{'title':'Enviar brochure','dueDate':'2026-12-03T15:00:00Z','requestId':uuid.uuid4().hex})
ok(st==200 and d.get('id'),'crear una tarea desde la ficha del contacto la escribe en la cuenta')
l=locales(a);nueva=[t for t in l if t.get('cuentaTareaId')==d['id']]
ok(len(l)==n0+1 and len(nueva)==1 and nueva[0]['titulo']=='Enviar brochure','y aparece una sola vez en el módulo Tareas')
st,_=a.call(f'workspace/activity/task-state?connectionId={cid}&conversationId=c0','POST',{'taskId':d['id'],'completed':True,'requestId':uuid.uuid4().hex})
ok(st==200 and [t for t in locales(a) if t.get('cuentaTareaId')==d['id']][0]['estado']=='hecha','completarla desde la ficha la marca hecha también en Tareas')
# Un ejecutivo solo crea tareas en contactos asignados a él.
ok(e.call('workspace/tareas','POST',{'titulo':'Ajena','vence':'2026-12-01','contactId':'k1','contacto':'Marcos Ortega','requestId':uuid.uuid4().hex})[0]==403,'un ejecutivo no crea tareas en un contacto que no es suyo')
st,d=e.call('workspace/tareas','POST',{'titulo':'Seguimiento','vence':'2026-12-01','contactId':'k0','contacto':'Lucía Fernández','requestId':uuid.uuid4().hex})
ok(st==200 and cuenta()[-1]['contactId']=='k0','sí las crea en sus contactos')
ok(all(t.get('id')!=tid for t in locales(e)),'y en Tareas ve solo las suyas')
print(f'{n} comprobaciones')
