#!/usr/bin/env python3
"""Agora New App · los permisos por rol funcionan en el servidor y la pantalla recibe los mismos.
Necesita lo mismo que tests/motor-puente.py (API en :8090 con datos sintéticos y cuenta simulada).
Uso: python3 tests/permisos.py CORREO_DUEÑA CLAVE_DUEÑA"""
import json,sys,urllib.request,http.cookiejar
B='http://127.0.0.1:8090/api/';n=0
def ok(c,m):
    global n
    if not c:raise SystemExit('FALLA: '+m)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw):
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
P='Agora-Pruebas-2026!'
R={r:C(f'{r}.asocim@agora.test',P) for r in ['admin','supervisor','ejecutivo1','consulta']}
# Qué puede cada rol: función -> (ruta, método, roles que SÍ)
T=[('ver datos de la subcuenta','workspace/branding','GET',['admin','supervisor','ejecutivo1','consulta']),
   ('ver cifras del equipo (reporte mensual)','workspace/reporte','GET',['admin','supervisor']),
   ('ver la auditoría','workspace/auditoria','GET',['admin','supervisor']),
   ('administrar usuarios','workspace/usuarios','GET',['admin']),
   ('ver permisos por rol','workspace/permisos','GET',['admin']),
   ('editar ajustes (diagnóstico)','admin/diagnostics','GET',['admin']),
   ('ver el resumen de agencia','workspace/resumen','GET',[]),
   ('ver consumos de la agencia','admin/consumption','GET',[])]
for nombre,ruta,met,si in T:
    for rol,c in R.items():
        st,_=c.call(ruta,met)
        if rol in si:ok(st==200,f'{rol} puede {nombre}')
        else:ok(st==403,f'{rol} NO puede {nombre} (403)')
# Lo que recibe la pantalla coincide con lo que el servidor aplica.
for rol,c in R.items():
    p=c.call('workspace/branding')[1].get('permisos') or {}
    ok(p.get('equipo.metricas')==(rol in('admin','supervisor')) and p.get('usuarios.administrar')==(rol=='admin') and p.get('auditoria.ver')==(rol in('admin','supervisor')) and p.get('ajustes.editar')==(rol=='admin') and p.get('datos.consultar') is True,f'la pantalla de {rol} recibe sus permisos reales')
ok(R['ejecutivo1'].call('workspace/branding')[1]['permisos']['soloAsignado'] is True and R['admin'].call('workspace/branding')[1]['permisos']['soloAsignado'] is False,'el ejecutivo queda marcado como «solo lo asignado»; la administradora no')
# Escritura: la consulta no escribe; el ejecutivo no reasigna.
cid=R['admin'].ws
ok(R['consulta'].call(f'workspace/send?connectionId={cid}&conversationId=c0','POST',{'message':'x','channel':'WhatsApp','requestId':'p'*32})[0]==403,'el rol de consulta no puede enviar mensajes')
ok(R['ejecutivo1'].call(f'workspace/assign?connectionId={cid}&conversationId=c0','POST',{'assignedTo':'u-admin','requestId':'q'*32})[0]==403,'el ejecutivo no puede reasignar')
# Un permiso retirado a una persona la bloquea de verdad y la pantalla se entera.
o=C(sys.argv[1],sys.argv[2]);o.ws=cid
us=R['admin'].call('workspace/usuarios')[1];sup=[u for u in (us.get('items') or us.get('usuarios') or []) if u.get('correo')=='supervisor.asocim@agora.test'][0]
st,_=R['admin'].call('workspace/usuarios/permisos','POST',{'id':sup['id'],'permisos':{'auditoria.ver':False}})
ok(st==200,'la administradora le retira la auditoría a la supervisora')
ok(R['supervisor'].call('workspace/auditoria')[0]==403,'la supervisora ya no puede ver la auditoría')
ok(R['supervisor'].call('workspace/branding')[1]['permisos']['auditoria.ver'] is False,'y su menú deja de recibir ese permiso')
st,_=R['admin'].call('workspace/usuarios/permisos','POST',{'id':sup['id'],'permisos':{}})
ok(st==200 and R['supervisor'].call('workspace/auditoria')[0]==200,'al devolverle el permiso vuelve a entrar')
# Aislamiento entre subcuentas.
c=R['admin'];c2=C('admin.asocim@agora.test',P);c2.ws=int(cid)+1
ok(c2.call('workspace/branding')[0]==403,'una administradora no entra a otra subcuenta')
print(f'{n} comprobaciones')
