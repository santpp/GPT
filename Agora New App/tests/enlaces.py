#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App · enlace de cada persona con su usuario de la cuenta conectada.
Necesita lo mismo que tests/motor-puente.py y haberlo corrido antes (deja ASOCIM conectada).
Uso: python3 tests/enlaces.py"""
import json,urllib.request,http.cookiejar
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
a=C('admin.asocim@agora.test');e1=C('ejecutivo1.asocim@agora.test');e2=C('ejecutivo2.asocim@agora.test');cid=a.ws
st,d=a.call('workspace/usuarios/equipo','POST',{})
ok(st==200 and {t['id'] for t in d['items']}>={'u-admin','u-paola'},'«Actualizar equipo» trae las personas de la cuenta')
us={u['correo']:u for u in a.call('workspace/usuarios')[1]['items']}
ok(us['ejecutivo1.asocim@agora.test']['enlace']['id']=='u-paola' and us['ejecutivo1.asocim@agora.test']['enlace']['auto'] is True,'quien tiene el mismo correo queda enlazado solo')
ok(us['ejecutivo2.asocim@agora.test']['enlace'] is None,'quien no tiene el mismo correo sale «Sin enlazar»')
ok(e2.call('workspace/branding')[1]['enlace'] is None,'la pantalla del ejecutivo sin enlace lo sabe (para avisarle)')
st,d=e2.call(f'workspace/inbox?connectionId={cid}')
ok(st in(200,403) and not d.get('items'),'un ejecutivo sin enlace no ve ninguna conversación')
id2=us['ejecutivo2.asocim@agora.test']['id']
ok(e1.call('workspace/usuarios/enlace','POST',{'id':id2,'enlace':'u-admin'})[0]==403,'un ejecutivo no puede cambiar enlaces')
ok(a.call('workspace/usuarios/enlace','POST',{'id':id2,'enlace':'no-existe'})[0]==404,'no se enlaza con alguien que no está en la cuenta')
st,d=a.call('workspace/usuarios/enlace','POST',{'id':id2,'enlace':'u-admin'})
ok(st==200 and d['enlace']['id']=='u-admin' and d['enlace']['auto'] is False,'la administradora enlaza al ejecutivo con una persona de la cuenta, aunque el correo sea otro')
st,d=e2.call(f'workspace/inbox?connectionId={cid}')
ok(st==200 and d.get('items') and all(i.get('assignedTo')=='u-admin' for i in d['items']),'desde ese momento ve solo lo asignado a esa persona')
id1=us['ejecutivo1.asocim@agora.test']['id']
ok(a.call('workspace/usuarios/enlace','POST',{'id':id1,'enlace':'u-admin'})[0]==409,'la misma persona de la cuenta no se enlaza con dos usuarios')
st,d=a.call('workspace/usuarios/enlace','POST',{'id':id2,'enlace':''})
ok(st==200 and d['enlace'] is None,'quitar el enlace lo devuelve a automático')
ok(not e2.call(f'workspace/inbox?connectionId={cid}')[1].get('items'),'y vuelve a no ver nada')
st,d=e1.call(f'workspace/inbox?connectionId={cid}')
ok(st==200 and d.get('items') and all(i.get('assignedTo')=='u-paola' for i in d['items']),'el enlace automático por correo sigue funcionando')
print(f'{n} comprobaciones')
