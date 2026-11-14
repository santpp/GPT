#!/usr/bin/env python3
"""Agora New App · Redes sociales (Social Planner de la cuenta conectada).
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
a=C('admin.asocim@agora.test');s=C('supervisor.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test');c=C('consulta.asocim@agora.test')
st,d=a.call('workspace/redes')
ok(st==200 and {x['red'] for x in d['cuentas']}>={'Facebook','Instagram'},'la administradora ve las redes conectadas en la cuenta')
ok(any(x['vencida'] for x in d['cuentas']),'una conexión vencida se marca como vencida')
ok(d.get('cifras') and d['cifras']['impresiones']==2350,'las cifras de los últimos días vienen de la cuenta')
ok(e.call('workspace/redes')[0]==403,'un ejecutivo (solo ve lo suyo) no entra a las redes de la empresa')
st,d=a.call('workspace/redes/publicaciones?tipo=failed')
ok(st==200 and d['items'] and d['items'][0]['error'],'las publicaciones con error traen el motivo')
st,d=a.call('workspace/redes')
ok(len(d['cifras'].get('porRed') or [])==2,'las cifras vienen también separadas por red')
for ruta,met,body in [('workspace/redes/publicaciones','POST',{'texto':'x','cuentas':['acc-fb'],'borrador':True}),('workspace/redes/publicaciones/post-pub','DELETE',None)]:
    ok(a.call(ruta,met,body)[0] in (404,405),f'desde Ágora no se publica ni se borra ({met})')
st,d=s.call('workspace/redes')
ok(st==200 and d.get('cifras'),'la supervisora ve las redes y sus cifras')
st,d=c.call('workspace/redes')
ok(st==403,'el rol de consulta (solo ve lo suyo) no las ve')
st,d=a.call(f'workspace/inbox?connectionId={a.ws}')
ok(st==200,'leer las redes no frena la bandeja')
print(f'{n} comprobaciones')
