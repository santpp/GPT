#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App · Campañas de Meta (Ad Manager de la cuenta conectada), solo lectura.
Necesita lo mismo que tests/motor-puente.py y haberlo corrido antes (deja ASOCIM conectada).
Uso: python3 tests/campanas.py"""
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
a=C('admin.asocim@agora.test');s=C('supervisor.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test')
st,d=a.call('workspace/campanas')
ok(st==200 and d['estado']=='ok' and len(d['campanas'])==2,'la administradora ve las campañas de Meta del mes')
c=d['campanas'][0]
ok(c['nombre']=='Diplomado de enero · Leads' and c['inversion']==412.35 and c['resultados']==86 and c['estado']=='active','cada campaña trae inversión, resultados y estado, aunque vengan anidados')
ok(c['costoPorResultado']==4.79,'el costo por resultado se calcula cuando la cuenta no lo da')
ok(d['totales']['inversion']==508.45 and d['totales']['resultados']==100,'los totales suman las campañas, redondeados')
ok(d['campanas'][0]['inversion']>=d['campanas'][1]['inversion'],'van ordenadas de mayor a menor inversión')
st,d=a.call('workspace/campanas?mes=2099-01')
ok(st in(400,422),'un mes que todavía no empieza se rechaza')
ok(s.call('workspace/campanas')[0]==200,'la supervisora también las ve')
ok(e.call('workspace/campanas')[0]==403,'un ejecutivo no ve la inversión en anuncios')
ok('diagnostico' not in d,'el detalle técnico no se muestra a quien no es dueño de Ágora')
import os
open('/tmp/fake-ghl-sin-anuncios','w').close()
try:
    st,d=a.call('workspace/campanas?mes=2026-10')
    ok(st==200 and d['estado']=='sin_integracion' and d['integracion'] is False,'sin cuenta publicitaria conectada (la cuenta responde 404) se dice exactamente eso')
finally:os.remove('/tmp/fake-ghl-sin-anuncios')
open('/tmp/fake-ghl-token-vencido','w').close()
try:
    st,d=a.call('workspace/campanas?mes=2026-08')
    ok(st==200 and d['estado']=='reconectar','si Meta no entrega el acceso de la cuenta publicitaria (422), se pide volver a conectarla')
finally:os.remove('/tmp/fake-ghl-token-vencido')
st,d=a.call(f'workspace/inbox?connectionId={a.ws}')
ok(st==200,'leer las campañas no frena la bandeja')
print(f'{n} comprobaciones')
