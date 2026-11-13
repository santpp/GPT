#!/usr/bin/env python3
"""Agora New App · el motor de Ágora Mobile detrás de la bandeja, contactos, oportunidades y consumos.
Necesita, en desarrollo: la API en http://127.0.0.1:8090 (APP_ENV=development, AGORA_GHL_FAKE=http://127.0.0.1:8099),
la cuenta simulada (python3 tests/fake-ghl.py 8099) y los datos sintéticos sembrados (bin/sembrar-sinteticos.php).
Uso: python3 tests/motor-puente.py CORREO_DUEÑA CLAVE_DUEÑA"""
import json,sys,uuid,urllib.request,http.cookiejar
B='http://127.0.0.1:8090/api/'
n=0
def ok(c,m):
    global n
    if not c:raise SystemExit('FALLA: '+m)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':user,'clave':pw});ok(st==200,f'{user} entra');s.ws=d.get('activeTenantId')
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
o=C(sys.argv[1],sys.argv[2])
st,d=o.call('agency/accounts');aso=[a for a in d['items'] if 'ASOCIM' in a['nombre']][0]
TOKEN='pit-0f1e2d3c-aaaa-bbbb-cccc-1234567890ab'
st,_=o.call('agency/accounts','PUT',{'id':aso['id'],'nombre':aso['nombre'],'subcuenta':aso['subcuenta'],'codigo':aso['codigo'],'conexion':{'locationId':'LOCasocim000000001','token':TOKEN}})
ok(st==200,'la dueña conecta ASOCIM con su Location ID y su token')
st,d=o.call('agency/accounts/conexion/probar','POST',{'id':aso['id']})
ok(d.get('estado')=='conectada' and (d.get('motor') or {}).get('sincronizada'),'«Probar conexión» conecta y prepara el motor con el equipo de la cuenta')
ok(TOKEN not in json.dumps(d),'el token no vuelve a la pantalla')
a=C('admin.asocim@agora.test','Agora-Pruebas-2026!');e=C('ejecutivo1.asocim@agora.test','Agora-Pruebas-2026!');cid=a.ws
st,d=a.call(f'workspace/inbox?connectionId={cid}');ids=[i['id'] for i in d.get('items',[])]
ok(st==200 and set(ids)>={'c0','c1','c2'},'la administradora ve la bandeja en vivo de toda la subcuenta')
st,d=e.call(f'workspace/inbox?connectionId={cid}');mine=[i for i in d.get('items',[])]
ok(st==200 and mine and all(i.get('assignedTo')=='u-paola' for i in mine),'el ejecutivo ve solo las conversaciones asignadas a él')
ok(e.call(f'workspace/messages?connectionId={cid}&conversationId=c1')[0]==404,'una conversación de otra persona no se abre')
st,d=e.call(f'workspace/contacts?connectionId={cid}')
ok(st==200 and all(i.get('assignedTo') in (None,'u-paola') for i in d.get('items',[])) and d.get('restricted'),'sus contactos son solo los suyos')
st,d=e.call(f'workspace/board?connectionId={cid}')
ok(st==200 and all(c.get('assignedTo')=='u-paola' for s in d.get('stages',[]) for c in s.get('items',[])),'su embudo muestra solo sus oportunidades')
rid=uuid.uuid4().hex
st,d=e.call(f'workspace/send?connectionId={cid}&conversationId=c0','POST',{'message':'Te confirmo el sábado','channel':'WhatsApp','requestId':rid})
ok(st==200 and d.get('messageId'),'el ejecutivo responde por WhatsApp')
st,d2=e.call(f'workspace/send?connectionId={cid}&conversationId=c0','POST',{'message':'Te confirmo el sábado','channel':'WhatsApp','requestId':rid})
ok(d2.get('messageId')==d.get('messageId'),'pulsar enviar dos veces no manda el mensaje dos veces')
ok(e.call(f'workspace/assign?connectionId={cid}&conversationId=c0','POST',{'assignedTo':'u-admin','requestId':uuid.uuid4().hex})[0]==403,'el ejecutivo no puede reasignar')
st,d=a.call(f'workspace/assign?connectionId={cid}&conversationId=c3','POST',{'assignedTo':'u-paola','requestId':uuid.uuid4().hex})
ok(st==200 and d.get('assignedName')=='Paola Rojas','la administradora asigna una conversación y su contacto')
ok(a.call(f'workspace/inbox?connectionId={int(cid)+1}')[0] in (403,409),'pedir otra subcuenta con la cabecera de esta se rechaza')
st,d=o.call('admin/consumption','GET')
ok(st==200,'la dueña abre Consumos')
ok(a.call('admin/consumption')[0]==403,'una administradora de subcuenta no ve Consumos')
print(f'{n} comprobaciones')
