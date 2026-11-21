#!/usr/bin/env python3
"""Agora New App 2.14.0 · Widgets, fase 1 y motor de fórmulas (sin cambios visibles):
- Coincidencia: cada métrica del diccionario da EXACTAMENTE la cifra de su fuente (la misma ruta que usan las tarjetas),
  con cada rol (administración, ejecutivo, consulta) y en una subcuenta sin reporte ni cuenta conectada.
- Permisos: solo administración (Administración, Supervisión, dueños) crea fórmulas y variables; el resto solo ve valores.
- Fórmulas guardadas: usan métricas, otras fórmulas y variables por mes; «mes anterior»; vueltas, nombres repetidos y errores rechazados.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/widgets-api.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,urllib.parse,re,os
B='http://127.0.0.1:8090/api/';n=0;P='Agora-Pruebas-2026!'
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
class C:
    def __init__(s,u,pw,ws=None):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None;s.call('session/csrf')
        st,d=s.call('login','POST',{'correo':u,'clave':pw});s.ws=ws or d.get('activeTenantId')
        if ws:s.call('workspace/active','POST',{'connectionId':ws})
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(B+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            try:return e.code,json.loads(e.read() or b'{}')
            except Exception:return e.code,{}
# el diccionario, leído del código (para comparar con la ruta de cada fuente)
src=open(os.path.join(os.path.dirname(__file__),'..','servidor','app','src','Widgets','Metricas.php'),encoding='utf-8').read()
DIC={m[0]:(m[1],m[2],m[3]) for m in re.findall(r"'([a-z_]+\.[a-z_]+)'=>\['[^']+','([a-z]+)','([a-z]+)','([^']+)'",src)}
FU={'mes':('workspace/board-month',{'period':'{p}'}),'ventas':('workspace/ventas',{'mes':'{p}'}),'cuenta':('workspace/dashboard',{'connectionId':'{c}'}),'dia':('workspace/dia',{'connectionId':'{c}'}),'ganadas':('workspace/won',{'connectionId':'{c}'}),'tareas':('workspace/tareas',{})}
def campo(d,ruta):
    for k in ruta.split('.'):
        if not isinstance(d,dict) or k not in d:return None
        d=d[k]
    return d
def coincide(cl,quien,periodo='2026-11',moneda='USD'):
    st,cat=cl.call('workspace/metricas/catalogo');ids=[m['id'] for m in cat.get('metricas',[])]
    ok(st==200 and len(ids)==len(DIC)>=30,f'{quien}: catálogo con {len(ids)} métricas')
    st,v=cl.call('workspace/metricas?periodo='+periodo+'&ids='+urllib.parse.quote(','.join(ids)));val=v.get('valores',{})
    fuentes={};malas=[]
    for id_,(u,f,ruta) in DIC.items():
        if f not in fuentes:
            r,q=FU[f];q={k:x.replace('{p}',periodo).replace('{c}',str(cl.ws)) for k,x in q.items()}
            fuentes[f]=cl.call(r+('?'+urllib.parse.urlencode(q) if q else ''))
        sst,d=fuentes[f];esperado=campo(d,ruta.replace('{moneda}',moneda)) if sst==200 else None
        dado=val.get(id_,{}).get('valor')
        if f=='ventas' and u=='dinero' and esperado is None and sst==200:esperado=0
        if not ((esperado is None and dado is None) or (esperado is not None and dado is not None and abs(float(esperado)-float(dado))<1e-9)):malas.append((id_,esperado,dado))
        if dado is None and not val.get(id_,{}).get('motivo'):malas.append((id_,'sin motivo',None))
    ok(not malas,f'{quien}: cada métrica da exactamente la cifra de su fuente {malas[:4]}')
    return val
limpiar();adm=C('admin.asocim@agora.test',P);coincide(adm,'administración (ASOCIM, con cuenta conectada)')
limpiar();eje=C('ejecutivo1.asocim@agora.test',P);ve=coincide(eje,'ejecutivo (solo lo suyo)')
limpiar();con=C('consulta.asocim@agora.test',P);coincide(con,'consulta')
limpiar();duena=C('duena@example.test','Clave-Local-2026!',ws=1);vd=coincide(duena,'subcuenta sin reporte ni cuenta conectada')
ok(vd['dia.leads']['valor'] is None and vd['dia.leads']['motivo'],'sin cuenta conectada, las cifras de la cuenta del día dan «—» con la razón')
# ---- permisos
for cl,q in [(eje,'ejecutivo'),(con,'consulta')]:
    ok(cl.call('workspace/metricas/catalogo')[1].get('puedeEditar') is False,f'{q}: el catálogo dice que no puede editar')
    ok(cl.call('workspace/formulas','POST',{'nombre':'X','expresion':'1+1'})[0]==403 and cl.call('workspace/formulas/probar','POST',{'expresion':'1'})[0]==403 and cl.call('workspace/variables','PUT',{'nombre':'X','valor':1})[0]==403,f'{q}: no crea fórmulas, no las prueba ni guarda variables')
limpiar();sup=C('supervisor.asocim@agora.test',P)
ok(sup.call('workspace/metricas/catalogo')[1].get('puedeEditar') is True,'supervisión sí puede crear (decisión: solo administración)')
# ---- fórmulas y variables (administración)
limpiar();adm=C('admin.asocim@agora.test',P)
for f in adm.call('workspace/formulas')[1].get('formulas',[]):
    if f['nombre'].startswith('Prueba '):adm.call('workspace/formulas','DELETE',{'id':f['id']})
ok(adm.call('workspace/variables','PUT',{'nombre':'Prueba sueldo','valor':500,'unidad':'dinero'})[0]==200,'variable con valor general')
ok(adm.call('workspace/variables','PUT',{'nombre':'Prueba sueldo','valor':600,'unidad':'dinero','periodo':'2026-11'})[0]==200,'la misma variable con otro valor para noviembre')
ok(adm.call('workspace/variables','PUT',{'nombre':'Prueba sueldo','valor':1,'unidad':'porcentaje','periodo':'2026-10'})[0]==409,'una variable no cambia de tipo entre meses')
st,a=adm.call('workspace/formulas','POST',{'nombre':'Prueba margen','expresion':'{Vendido del mes} - {Costos fijos}','formato':'dinero'})
ok(st==200 and a['prueba']['ok'] and abs(a['prueba']['valor']-(750-40.5))<1e-9,f'«Prueba margen» se guarda y su vista previa da {a.get("prueba",{}).get("valor")}')
st,b=adm.call('workspace/formulas','POST',{'nombre':'Prueba margen %','expresion':'PORCENTAJE({Prueba margen}; {Vendido del mes})','formato':'porcentaje'})
ok(st==200,'una fórmula puede usar otra')
st,c=adm.call('workspace/formulas','POST',{'nombre':'Prueba neto','expresion':'{Prueba margen} - {Prueba sueldo}'})
st,v=adm.call(f'workspace/metricas?periodo=2026-11&ids=f:{a["formula"]["id"]},f:{b["formula"]["id"]},f:{c["formula"]["id"]}');x=v['valores']
ok(abs(x[f'f:{a["formula"]["id"]}']['valor']-709.5)<1e-9 and x[f'f:{a["formula"]["id"]}']['unidad']=='dinero','el widget de la fórmula da el mismo valor que su vista previa')
ok(abs(x[f'f:{b["formula"]["id"]}']['valor']-94.6)<1e-6 and x[f'f:{b["formula"]["id"]}']['unidad']=='porcentaje','Margen % = 94,6 % (con formato porcentaje)')
ok(abs(x[f'f:{c["formula"]["id"]}']['valor']-109.5)<1e-9,'en noviembre usa el sueldo de noviembre (600)')
st,v=adm.call(f'workspace/metricas?periodo=2026-10&ids=f:{c["formula"]["id"]},mes.vendido,mes.costos_fijos');y=v['valores']
esp=(y['mes.vendido']['valor'] or 0)-(y['mes.costos_fijos']['valor'] or 0)-500 if y['mes.vendido']['valor'] is not None and y['mes.costos_fijos']['valor'] is not None else None
ok((esp is None and y[f'f:{c["formula"]["id"]}']['valor'] is None) or abs(y[f'f:{c["formula"]["id"]}']['valor']-esp)<1e-9,'en otro mes usa el valor general de la variable (500)')
st,p=adm.call('workspace/formulas/probar','POST',{'expresion':'{Vendido del mes} - {Vendido del mes · mes anterior}','periodo':'2026-11'})
ok(st==200 and p['ok'] and p['usa']==['Vendido del mes','Vendido del mes · mes anterior'],'«mes anterior» se entiende y lista lo que usa')
st,p=adm.call('workspace/formulas/probar','POST',{'expresion':'{Contactos · mes anterior}'})
ok(not p['ok'] and 'es de hoy' in p['error'],'una cifra de hoy no tiene mes anterior: se explica')
st,p=adm.call('workspace/formulas/probar','POST',{'expresion':'{Vendido del mes} + {Avance de la meta}'})
ok(not p['ok'] and p['pos']==18 and 'porcentaje' in p['error'],'el error de unidades señala dónde está')
st,_=adm.call('workspace/formulas','PUT',{'id':a['formula']['id'],'nombre':'Prueba margen','expresion':'{Prueba margen %} * 2'})
ok(st==422,'una fórmula no puede dar vueltas (A usa B y B usa A)')
ok(adm.call('workspace/formulas','DELETE',{'id':a['formula']['id']})[0]==409,'no se borra una fórmula que usa otra')
ok(adm.call('workspace/formulas','POST',{'nombre':'Vendido del mes','expresion':'1'})[0]==409,'una fórmula no puede llamarse como una cifra del sistema')
ok(adm.call('workspace/formulas','POST',{'nombre':'Prueba sueldo','expresion':'1'})[0]==409,'una fórmula no puede llamarse como una variable')
ok(adm.call('workspace/variables','PUT',{'nombre':'Prueba margen','valor':1})[0]==409,'una variable no puede llamarse como una fórmula')
st,e=adm.call('workspace/formulas','POST',{'nombre':'Prueba mala','expresion':'SI(1;'})
ok(st==422 and 'error' in e.get('error',''),'una fórmula con error no se guarda y dice por qué')
ok(adm.call('workspace/formulas','POST',{'nombre':'<b>x</b>','expresion':'1'})[0]==422,'el nombre no acepta HTML')
ok(adm.call('workspace/metricas?ids='+','.join(['mes.vendido']*1+[f'x{i}' for i in range(45)]))[0]==422,'tope de 40 cifras por pedido')
ok(eje.call(f'workspace/metricas?periodo=2026-11&ids=f:{a["formula"]["id"]}')[1]['valores'][f'f:{a["formula"]["id"]}']['valor'] is not None,'quien no edita igual ve los valores de las fórmulas del equipo')
# ---- tableros por módulo (2.15.0)
adm.call('workspace/tableros?modulo=ventas','DELETE',{'modulo':'ventas'})
st,t=adm.call('workspace/tableros?modulo=ventas')
ok(st==200 and t['widgets']==[] and t['puedeEditar'] is True,'un módulo empieza sin widgets y administración puede editarlo')
W=lambda **k:{'id':k.get('id','w1'),'fuente':k.get('fuente','metrica'),'ref':k.get('ref','mes.vendido'),'titulo':k.get('titulo',''),'tamano':'chico','periodo':k.get('periodo','modulo'),'comparar':k.get('comparar',''),'formato':'auto','decimales':2,'semaforo':k.get('semaforo',{'activo':False})}
st,r=adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':t['version'],'widgets':[W(),W(id='w2',fuente='formula',ref=a['formula']['id'],comparar='mes_anterior',semaforo={'activo':True,'verde':500,'ambar':300,'mejor':'mas'})]})
ok(st==200 and len(r['widgets'])==2 and r['version']>t['version'],f'se guardan dos widgets (cifra y fórmula) y sube la versión ({st})')
st,_=adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':t['version'],'widgets':[W()]})
ok(st==409,'guardar sobre una versión vieja da conflicto (otra persona cambió el tablero)')
ok(adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':r['version'],'widgets':[W(ref='no.existe')]})[0]==422,'no acepta una cifra que no existe')
ok(adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':r['version'],'widgets':[W(fuente='formula',ref=999999)]})[0]==422,'no acepta una fórmula que no existe')
ok(adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':r['version'],'widgets':[W(id='w%d'%i) for i in range(25)]})[0]==422,'tope de 24 widgets por tablero')
ok(adm.call('workspace/tableros','PUT',{'modulo':'<script>','version':r['version'],'widgets':[W()]})[0]==422,'no acepta un módulo inventado')
st,x=adm.call('workspace/tableros','PUT',{'modulo':'ventas','version':r['version'],'widgets':[W(titulo='<img src=x onerror=alert(1)>')]})
ok(st==422 or '<' not in json.dumps(x.get('widgets',[])),'el título no guarda HTML')
st,t2=adm.call('workspace/tableros?modulo=ventas');r=t2
ok(adm.call('workspace/formulas','DELETE',{'id':a['formula']['id']})[0]==409,'no se borra una fórmula que usa un tablero')
for cl,q in [(eje,'ejecutivo'),(con,'consulta')]:
    st,v=cl.call('workspace/tableros?modulo=ventas')
    ok(st==200 and v['puedeEditar'] is False and len(v['widgets'])==len(t2['widgets']),f'{q} ve el tablero del equipo pero no lo edita')
    ok(cl.call('workspace/tableros','PUT',{'modulo':'ventas','version':t2['version'],'widgets':[]})[0]==403,f'{q} no puede cambiar el tablero')
    ok(cl.call('workspace/tableros?modulo=ventas','DELETE',{'modulo':'ventas'})[0]==403,f'{q} no puede restablecer el tablero')
ok(adm.call('workspace/tableros?modulo=ventas','DELETE',{'modulo':'ventas'})[0]==200 and adm.call('workspace/tableros?modulo=ventas')[1]['widgets']==[],'restablecer deja el módulo sin widgets')
# limpieza
for f in (c,b,a):adm.call('workspace/formulas','DELETE',{'id':f['formula']['id']})
adm.call('workspace/variables','DELETE',{'nombre':'Prueba sueldo','periodo':''});adm.call('workspace/variables','DELETE',{'nombre':'Prueba sueldo','periodo':'2026-11'})
ok(not [f for f in adm.call('workspace/formulas')[1]['formulas'] if f['nombre'].startswith('Prueba ')],'queda todo limpio')
print(n,'comprobaciones')
