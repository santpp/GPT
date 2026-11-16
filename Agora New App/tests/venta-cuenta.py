#!/usr/bin/env python3
"""Agora New App · Venta ganada con la cuenta conectada: la oportunidad queda ganada en la cuenta, la venta en Ágora, sin duplicados; espejo de contactos y oportunidades.
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
import uuid,datetime
FAKE='http://127.0.0.1:8099'
def cuenta(path):
    with urllib.request.urlopen(urllib.request.Request(FAKE+path,headers={'Authorization':'Bearer pit-0f1e2d3c-aaaa-bbbb-cccc-1234567890ab','Version':'2021-07-28'})) as r:return json.loads(r.read())
a=C('admin.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test')
# Una oportunidad abierta de la cuenta que todavía no tenga venta (la prueba se puede repetir).
OID=None
for o in ['o0','o1','o3']:
    st,ctx=a.call(f'workspace/ventas/contexto?oportunidadId={o}')
    if st==200 and not ctx.get('venta'):OID=o;break
if OID is None:raise SystemExit('Todas las oportunidades de prueba ya tienen venta: reinicia la base de pruebas (las ventas no se duplican, como debe ser).')
ok(st==200 and ctx.get('enCuenta') and ctx['oportunidad']['id']==OID and ctx['contacto'] and ctx['contacto']['email'],'«Venta ganada» abre con la oportunidad y el contacto leídos de la cuenta')
ok(ctx.get('venta') is None,'todavía no tiene venta')
prods=ctx['catalogo']['productos'];ok(len(prods)>0,'hay productos en el catálogo para la prueba')
p=next((x for x in prods if x.get('cobro')=='unico'),prods[0])
hoy=datetime.date.today().isoformat()
body={'oportunidadId':OID,'contactoId':ctx['contacto']['id'],'cliente':{'nombre':ctx['contacto']['name'],'documento':'','correo':'cliente@ejemplo.test','telefono':'','direccion':'','empresa':''},'productoId':p['id'],'cobro':'unico','monto':275,'moneda':'USD','frecuencia':None,'cuotas':1,'cantidad':1,'fechaInicio':hoy,'diaCobro':None,'vendedorId':None,'fecha':hoy,'notas':'','customFields':[]}
st,r=a.call('workspace/ventas','POST',body)
ok(st==200 and r['venta']['oportunidadId']==OID and r['venta']['contactoId']==ctx['contacto']['id'] and r['venta'].get('oportunidadEnCuenta'),'la venta queda en Ágora unida a la oportunidad y al contacto de la cuenta')
op=cuenta(f'/opportunities/{OID}')['opportunity']
ok(op['status']=='won' and float(op['monetaryValue'])==275,'en la cuenta, la oportunidad quedó ganada con el monto de la venta')
ok(a.call('workspace/ventas','POST',body)[0]==409,'una segunda venta para la misma oportunidad se rechaza (sin duplicados)')
st,ctx2=a.call(f'workspace/ventas/contexto?oportunidadId={OID}')
ok(st==200 and ctx2.get('venta') and ctx2['venta']['id']==r['venta']['id'],'si se vuelve a abrir, ofrece editar la misma venta')
tareas=[t for t in cuenta('/__tasks')['tasks'] if t['contactId']==ctx['contacto']['id'] and 'bienvenida' in (t.get('title') or '').lower()]
ok(len(tareas)>=1 and r['venta']['tareas'],'las tareas de seguimiento se escriben en la cuenta')
st,v=a.call('workspace/ventas')
pend={x['id'] for x in v.get('pendientes',[])}
ok('o2' in pend and OID not in pend,'«ventas por completar» muestra las ganadas de la cuenta sin venta (y no la que ya la tiene)')
st,m=a.call('workspace/board-month?period='+hoy[:7])
ok(st==200 and m['sales']['closings']>=1 and m['sales']['total']>=275,'la meta del mes cuenta las ganadas de la cuenta')
body2={**body,'id':r['venta']['id'],'version':r['venta'].get('version',1),'monto':300}
st,ver=a.call('workspace/ventas?mes='+hoy[:7]);vv=[x for x in ver['items'] if x['id']==r['venta']['id']][0];body2['version']=vv['version']
st,r2=a.call('workspace/ventas','PUT',body2)
ok(st==200 and float(cuenta(f'/opportunities/{OID}')['opportunity']['monetaryValue'])==300,'al editar el monto de la venta, también cambia en la cuenta')
ok(e.call('workspace/ventas/contexto?oportunidadId=o2')[0]==403,'un ejecutivo no abre la venta de una oportunidad que no es suya')
st,c=a.call(f'workspace/contacts?connectionId={a.ws}')
ok(st==200 and c.get('items'),'leer la lista de contactos (queda en el espejo)')
nombre='Importada enlace '+uuid.uuid4().hex[:6]
st,r3=a.call('workspace/migracion/importar','POST',{'modulo':'ventas','modo':'crear','filas':[{'fecha':hoy,'cliente':nombre,'correo':'marcos@ejemplo.test','producto':'CIN','monto':85,'cobro':'Pago único'}]})
st,v=a.call('workspace/ventas');x=[i for i in v['items'] if i.get('cliente',{}).get('nombre')==nombre]
ok(x and x[0].get('contactoId')=='k1','una venta importada con el correo de un contacto de la cuenta queda unida a ese contacto (sin duplicar el cliente)')
print(f'{n} comprobaciones')
