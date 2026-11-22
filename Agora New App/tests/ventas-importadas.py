#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App · Ventas cargadas por Excel (Migración): plan de cobros y edición sin oportunidad.
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
import datetime,random
hoy=datetime.date.today()
def mes(n):
    y,m=hoy.year,hoy.month+n
    while m<1:y-=1;m+=12
    while m>12:y+=1;m-=12
    return datetime.date(y,m,min(hoy.day,28)).isoformat()
a=C('admin.asocim@agora.test');e=C('ejecutivo1.asocim@agora.test')
nombre='Importada '+str(random.randint(10000,99999))
fila={'fecha':mes(-2),'cliente':nombre,'producto':'CIPRO','monto':62.5,'cobro':'Recurrente','frecuencia':'Mensual','vendedor':'Lisbella Moreno'}
st,r=a.call('workspace/migracion/importar','POST',{'modulo':'ventas','modo':'crear','filas':[fila,{**fila,'cliente':nombre+' B','cobro':'Pago único','monto':300,'cuotas':3,'fecha':mes(-1)}]})
ok(st==200 and r.get('creados')==2,'Migración importa dos ventas por Excel')
st,d=a.call('workspace/ventas');vs={v['cliente']['nombre']:v for v in d['items'] if v.get('cliente',{}).get('nombre','').startswith(nombre)}
v=vs[nombre];hoyS=hoy.isoformat()
ok(v.get('plan') and all(c['estado']=='pagado' for c in v['plan'] if c['fecha']<hoyS) and all(c['estado']=='pendiente' for c in v['plan'] if c['fecha']>=hoyS),'la venta importada nace con su plan: lo anterior a hoy, pagado; lo que viene, pendiente')
ok(v['estado']=='al_dia' and v.get('sinFin'),'una mensualidad sin fin queda al día')
u=vs[nombre+' B']
ok(len(u['plan'])==3 and round(sum(c['monto'] for c in u['plan']),2)==300,'un pago único en 3 cuotas se reparte en 3 cobros que suman el total')
st,ctx=a.call('workspace/ventas/contexto?ventaId='+v['id'])
ok(st==200 and ctx.get('sinOportunidad') and ctx['venta']['producto']=='CIPRO','«Editar venta» abre sin oportunidad')
body={'id':v['id'],'version':v['version'],'cliente':{'nombre':nombre,'documento':'','correo':'','telefono':'','direccion':'','empresa':''},'productoId':'','producto':'CIPRO','cobro':'recurrente','monto':65,'moneda':'USD','frecuencia':'mensual','cuotas':None,'cantidad':1,'fechaInicio':v['fechaInicio'],'diaCobro':None,'vendedorId':None,'vendedor':'Lisbella Moreno','fecha':v['fecha'],'notas':'','customFields':[]}
st,r=a.call('workspace/ventas','PUT',body)
ok(st==200 and r['venta']['monto']==65 and r['venta']['producto']=='CIPRO' and r['venta']['vendedor']=='Lisbella Moreno','se guarda con el producto y el vendedor escritos, sin oportunidad')
ok(all(c['estado']=='pagado' for c in r['venta']['plan'] if c['fecha']<hoyS),'al editar se conservan los cobros ya pagados')
st,d2=a.call('workspace/ventas');vv=[x for x in d2['items'] if x['id']==v['id']][0];st,d3=a.call('workspace/ventas');ok([x['version'] for x in d3['items'] if x['id']==v['id']]==[vv['version']] and vv['vendedor']=='Lisbella Moreno','leer otra vez no vuelve a tocar la venta y el vendedor sigue siendo el del Excel')
ok(e.call('workspace/ventas','PUT',{**body,'vendedorId':999999})[0] in (400,403,404,409,422),'un ejecutivo no puede tocar una venta que no es suya')
print(f'{n} comprobaciones')
