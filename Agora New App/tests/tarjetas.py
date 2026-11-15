#!/usr/bin/env python3
"""Agora New App · Tarjetas a elección en una vista (Ventas registradas).
Necesita lo mismo que tests/motor-puente.py y haberlo corrido antes (deja ASOCIM conectada).
Uso: python3 tests/redes.py"""
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
a=C('admin.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test');c=C('consulta.asocim@agora.test')
for x in (a,e,c):x.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':None})
a.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':None,'equipo':True})
st,d=e.call('workspace/preferencias?vista=ventas')
ok(st==200 and d['mia'] is None and d['equipo'] is None,'sin elección, la vista usa las tarjetas de fábrica')
st,d=e.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['vendido','ticket','vendedor']})
ok(st==200 and d['mia']['tarjetas']==['vendido','ticket','vendedor'],'un ejecutivo elige sus tarjetas y su orden')
st,d=c.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['cantidad']})
ok(st==200,'también quien solo consulta puede elegir su vista')
ok(c.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['cantidad'],'equipo':True})[0]==403,'solo la administración guarda la vista del equipo')
st,d=a.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['cantidad','mensual','vencido'],'equipo':True})
ok(st==200 and d['equipo']['tarjetas']==['cantidad','mensual','vencido'],'la administradora guarda la vista del equipo')
st,d=e.call('workspace/preferencias?vista=ventas')
ok(d['mia']['tarjetas']==['vendido','ticket','vendedor'] and d['equipo']['tarjetas'][1]=='mensual','cada quien conserva la suya y ve cuál es la del equipo')
ok(e.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['<script>']})[0] in (400,422),'una tarjeta con nombre inválido se rechaza')
ok(e.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':['a'+str(i)*2 for i in range(25)]})[0] in (400,422),'no se aceptan listas enormes')
ok(e.call('workspace/preferencias?vista=../x')[0] in (400,422),'una vista con nombre raro se rechaza')
st,d=e.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':None})
ok(st==200 and d['mia'] is None,'«Volver a la vista del equipo» borra la elección propia')
a.call('workspace/preferencias','PUT',{'vista':'ventas','tarjetas':None,'equipo':True})
# 2.8.5 · «Registro» (Contratos) muestra solo lo que pasa con convenios; Auditoría, todo.
st,d=a.call('registro')
ok(st==200 and all(x['modulo']=='contract' for x in d['items']),'«Registro» muestra solo movimientos de convenios')
st,d2=a.call('workspace/auditoria')
ok(st==200 and len({x['modulo'] for x in d2['items']})>1,'«Auditoría» sigue mostrando todo')
ok(e.call('registro')[0]==403,'un ejecutivo no ve el registro')
print(f'{n} comprobaciones')
