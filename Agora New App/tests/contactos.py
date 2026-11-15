#!/usr/bin/env python3
"""Agora New App · «Nuevo contacto» escribe en la cuenta conectada.
Necesita lo mismo que tests/motor-puente.py y haberlo corrido antes (deja ASOCIM conectada).
Uso: python3 tests/contactos.py"""
import json,uuid,time,urllib.request,http.cookiejar
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
def cuenta(cid):
    r=urllib.request.Request('http://127.0.0.1:8099/contacts/'+cid,headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['contact']
a=C('admin.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test');e2=C('ejecutivo2.asocim@agora.test');c=C('consulta.asocim@agora.test');cid=a.ws
tel='+58412'+str(int(time.time()))[-7:]
ok(a.call('workspace/contactos/nuevo','POST',{'nombre':'Sin datos'})[0] in (400,422),'no se crea un contacto sin teléfono ni correo')
ok(a.call('workspace/contactos/nuevo','POST',{'nombre':'Malo','telefono':'abc'})[0] in (400,422),'un teléfono mal escrito se rechaza antes de llegar a la cuenta')
rid=uuid.uuid4().hex
st,d=a.call('workspace/contactos/nuevo','POST',{'nombre':'Andrea','apellido':'Salas','telefono':tel[:6]+' '+tel[6:9]+'-'+tel[9:],'correo':'','requestId':rid})
ok(st==200 and d.get('id'),'la administradora crea un contacto')
k=cuenta(d['id'])
ok(k['phone']==tel and k['contactName']=='Andrea Salas' and k.get('source')=='Ágora' and not k.get('assignedTo'),'queda en la cuenta, con el teléfono sin espacios ni guiones y sin asignar')
st,d2=a.call('workspace/contactos/nuevo','POST',{'nombre':'Andrea','apellido':'Salas','telefono':tel[:6]+' '+tel[6:9]+'-'+tel[9:],'correo':'','requestId':rid})
ok(st==200 and d2.get('id')==d['id'],'pulsar crear dos veces no crea dos contactos')
st,d3=a.call('workspace/contactos/nuevo','POST',{'nombre':'Otra','telefono':tel,'requestId':uuid.uuid4().hex})
ok(st==409 and 'ya exista' in (d3.get('error') or ''),'un teléfono que ya existe se explica en palabras y no se duplica')
time.sleep(7)  # la cuenta rechazó un duplicado: la pasarela espera unos segundos antes de la siguiente consulta
for _ in range(6):
    st,l=a.call(f'workspace/contacts?connectionId={cid}&query=Andrea')
    if st!=429:break
    time.sleep(4)
ok(st==200 and any(i['id']==d['id'] for i in l.get('items',[])),'el contacto nuevo aparece en Contactos')
time.sleep(1)
st,d4=e.call('workspace/contactos/nuevo','POST',{'nombre':'Lead','apellido':'Propio','correo':f'lead{int(time.time())}@ejemplo.test','requestId':uuid.uuid4().hex})
ok(st==200 and cuenta(d4['id'])['assignedTo']=='u-paola','el contacto que crea un ejecutivo queda asignado a él')
ok(e2.call('workspace/contactos/nuevo','POST',{'nombre':'X','correo':f'x{int(time.time())}@ejemplo.test','requestId':uuid.uuid4().hex})[0]==403,'un ejecutivo sin enlace con la cuenta no puede crear contactos')
ok(c.call('workspace/contactos/nuevo','POST',{'nombre':'X','correo':f'y{int(time.time())}@ejemplo.test','requestId':uuid.uuid4().hex})[0]==403,'el rol de consulta no puede crear contactos')
# 2.8.17 · filtro «Entraron» (fecha de alta en la cuenta)
nombres=lambda d:{x['name'] for x in d.get('items',[])}
st,d=a.call(f'workspace/contacts?connectionId={cid}&since=hoy&tz=UTC')
ok(st==200 and nombres(d)=={'Lucía Fernández'} and d.get('since')=='hoy','«Entraron hoy» trae solo los contactos dados de alta hoy')
st,d=a.call(f'workspace/contacts?connectionId={cid}&since=30d&tz=UTC')
ok(st==200 and nombres(d)>={'Lucía Fernández','Marcos Ortega','Camila Reyes'} and 'Pedro Navarro' not in nombres(d),'«30 días» deja fuera a los de hace más tiempo')
st,d=e.call(f'workspace/contacts?connectionId={cid}&since=30d&tz=UTC')
ok(st==200 and d.get('restricted') and all(x['assignedTo']=='u-paola' for x in d['items']),'un ejecutivo ve solo sus contactos también con el filtro')
print(f'{n} comprobaciones')
