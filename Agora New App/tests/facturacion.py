#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.11.0 · Facturación (servidor): factura simple sin datos fiscales; factura fiscal con datos fiscales, tasa BCV,
correlativo sin saltos (también con emisiones al mismo tiempo), número de control de la imprenta, notas, anulación con motivo,
inmutabilidad en la base, cadena de huellas, libro de ventas y permisos por rol (los vendedores facturan; anular es de administración).
Requiere la API local en :8090. Usa la subcuenta ASOCIM (2). Deja sus datos fiscales como estaban; los documentos de prueba quedan
(una factura emitida no se borra: así es la norma). Uso: python3 tests/facturacion.py"""
import json,sys,os,subprocess,datetime,threading,random,http.cookiejar,urllib.request
B='http://127.0.0.1:8090';API=B+'/api/';n=0
SOCK='/tmp/mdb/run/mysql.sock'
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
class C:
    def __init__(s,user,pw):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None
        s.call('session/csrf');st,d=s.call('login','POST',{'correo':user,'clave':pw})
        if st!=200:raise SystemExit(f'FALLA: {user} no entra ({st}) {d}')
        s.ws=d.get('activeTenantId')
    def call(s,path,method='GET',body=None):
        csrf=next((c.value for c in s.cj if c.name=='agora_csrf'),'')
        h={'Content-Type':'application/json','X-CSRF-Token':csrf}
        if s.ws:h['X-Workspace-ID']=str(s.ws)
        r=urllib.request.Request(API+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers=h)
        try:
            with s.op.open(r,timeout=60) as x:return x.status,json.loads(x.read() or b'{}')
        except urllib.error.HTTPError as e:
            t=e.read()
            try:return e.code,json.loads(t)
            except Exception:return e.code,{'raw':t[:200]}
def sql(q):return subprocess.run(['mysql','--socket='+SOCK,'agora_erp','-N','-e',q],capture_output=True,text=True)
P='Agora-Pruebas-2026!'
adm=C('admin.asocim@agora.test',P);eje=C('ejecutivo1.asocim@agora.test',P);con=C('consulta.asocim@agora.test',P);sup=C('supervisor.asocim@agora.test',P)
T=adm.ws
st,perfil0=adm.call('workspace/fiscal/perfil')
sql(f"DELETE FROM agora_fiscal_tasas WHERE org_id={T}")
linea=lambda d,p,imp='G':{'descripcion':d,'cantidad':1,'precio':p,'impuesto':imp}
cliente={'nombre':'Cliente Prueba C.A.','rif':'J-31234567-8','direccion':'Av. Principal, Caracas'}
try:
    # ---- sin datos fiscales: solo la factura simple
    sql(f"DELETE FROM agora_fiscal_profiles WHERE org_id={T}")
    st,f=adm.call('workspace/fiscal');ok(st==200 and f['fiscal'] is False and f['puede']['emitir'],'sin datos fiscales la subcuenta no es fiscal, pero puede facturar')
    st,d=adm.call('workspace/facturas','POST',{'tipo':'SIM','moneda':'VES','cliente':{'nombre':'Ana Pérez'},'lineas':[linea('Mensualidad',40,'E')]})
    ok(st==200 and d['estado']=='BORRADOR' and d['tipo']=='SIM','se crea un borrador de factura simple (sin RIF ni domicilio)')
    st,e=adm.call('workspace/facturas/emitir','POST',{'uid':d['uid']})
    ok(st==200 and e['estado']=='EMITIDA' and e['numero_texto'] and e['control'] is None,f'la factura simple se emite sin datos fiscales ni tasa (N.° {e.get("numero_texto")})')
    st,v=adm.call('workspace/facturas/ver?uid='+d['uid'])
    ok(st==200 and 'Documento sin validez fiscal' in v['html'] and 'N.° de control' not in v['html'],'la factura simple impresa dice «sin validez fiscal» y no lleva número de control')
    st,d=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cliente,'lineas':[linea('Diplomado',100)]})
    st,e=adm.call('workspace/facturas/emitir','POST',{'uid':d['uid']});ok(st==409 and 'datos fiscales' in e.get('error',''),'sin datos fiscales, la factura fiscal no se emite y lo explica')
    fac_pend=d['uid']
    # ---- datos fiscales (solo administración)
    pf={'razon_social':'Asociación de Prueba','rif':'J-30123456-7','domicilio':'Calle 1, Caracas 1010','modo':'IMPRENTA_DIGITAL','imprenta':{'nombre':'SERDIMPRE','rif':'J-29876543-1','providencia':'SNAT/2024/000001','fecha':'2024-12-20'}}
    st,r=eje.call('workspace/fiscal/perfil','PUT',pf);ok(st==403,'un vendedor no cambia los datos fiscales')
    st,r=adm.call('workspace/fiscal/perfil','PUT',pf);ok(st==200 and r['completo'],'la administración completa los datos fiscales (imprenta digital SERDIMPRE)')
    # ---- tasa BCV
    sql("DELETE FROM agora_fiscal_fx WHERE day>=CURDATE()-INTERVAL 6 DAY")
    st,r=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'USD','cliente':cliente,'lineas':[linea('En dólares',10)]});ok(st==422 and 'solo en bolívares' in r.get('error',''),'2.11.1 · por ahora se factura solo en bolívares: una factura en dólares se rechaza')
    st,f=adm.call('workspace/fiscal');ok(f.get('monedas')==['VES'],'la subcuenta factura en bolívares (dólares y euros quedan listos para activarse)')
    hoy=subprocess.run(['php','-r','echo (new DateTimeImmutable("now",new DateTimeZone("America/Caracas")))->format("Y-m-d");'],capture_output=True,text=True).stdout
    st,r=con.call('workspace/fiscal/tasas','POST',{'fecha':hoy,'tasa':'245,5'});ok(st==403,'la consulta no escribe tasas')
    st,r=adm.call('workspace/fiscal/tasas','POST',{'fecha':hoy,'tasa':'245,5'});ok(st==200 and r['vigente'] and abs(r['vigente']['tasa']-245.5)<1e-6,'la tasa BCV de hoy se escribe a mano (245,5)')
    sql(f"DELETE FROM agora_fiscal_tasas WHERE org_id={T}")
    st,e=adm.call('workspace/facturas/emitir','POST',{'uid':fac_pend})
    ok(st==200 and e['moneda']=='VES','la factura en bolívares se emite aunque falte la tasa BCV')
    adm.call('workspace/fiscal/tasas','POST',{'fecha':hoy,'tasa':'245,5'})  # para la prueba de pantalla (cobros en dólares → Bs)
    ok(st==200 and e['estado']=='EMITIDA' and e['control_pendiente'] and abs(e['totales']['iva_general']-16)<1e-6 and abs(e['totales']['total']-116)<1e-6,'la factura fiscal se emite: IVA 16 % y control pendiente de la imprenta')
    ok(e['totales']['total_bs'] is None or abs(e['totales']['total_bs']-116)<0.01,'en bolívares el total es el mismo, sin conversión')
    fac=e
    cn=random.randint(100000,99999999)
    st,r=adm.call('workspace/facturas/control','POST',{'uid':fac['uid'],'control':f'00-{cn}'});ok(st==200 and r['control']==f'00-{cn:08d}',f'se registra el número de control de la imprenta (00-{cn:08d})')
    st,r=adm.call('workspace/facturas/control','POST',{'uid':fac['uid'],'control':'00-124'});ok(st==409,'el número de control no se cambia una vez puesto')
    # ---- inmutable en la base
    r=sql(f"UPDATE agora_fiscal_documents SET total_cents=1 WHERE uid='{fac['uid']}'");ok('INMUTABLE' in r.stderr,'la base de datos impide cambiar una factura emitida (aunque se entre directo)')
    r=sql(f"DELETE FROM agora_fiscal_documents WHERE uid='{fac['uid']}'");ok('INMUTABLE' in r.stderr,'y también borrarla')
    st,r=adm.call('workspace/facturas/doc?uid='+fac['uid'],'PUT',{'cliente':cliente,'lineas':[linea('Otro',1)]});ok(st==409,'por la app tampoco se edita: se corrige con una nota')
    # ---- correlativo sin saltos, también al mismo tiempo
    uids=[adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cliente,'lineas':[linea(f'Curso {i}',10)]})[1]['uid'] for i in range(5)]
    borrado=adm.call('workspace/facturas','POST',{'tipo':'FAC','cliente':cliente,'lineas':[linea('Se borra',5)]})[1]['uid']
    st,r=adm.call('workspace/facturas/doc?uid='+borrado,'DELETE');ok(st==200,'un borrador se puede borrar (no gasta número)')
    nums=[];hs=[threading.Thread(target=lambda u=u:nums.append(adm.call('workspace/facturas/emitir','POST',{'uid':u})[1].get('numero'))) for u in uids]
    [h.start() for h in hs];[h.join() for h in hs]
    ok(None not in nums and sorted(nums)==list(range(min(nums),min(nums)+5)) and min(nums)==fac['numero']+1,f'5 facturas emitidas al mismo tiempo: números seguidos y sin repetir {sorted(nums)}')
    # ---- notas y anulación
    st,r=eje.call('workspace/facturas/nota','POST',{'uid':fac['uid'],'tipo':'NC','motivo':'Descuento acordado','lineas':[linea('Descuento',10)]});ok(st==403,'un vendedor no emite notas de crédito')
    st,r=adm.call('workspace/facturas/nota','POST',{'uid':fac['uid'],'tipo':'NC','motivo':'no','lineas':[linea('Descuento',10)]});ok(st==422,'la nota pide un motivo escrito')
    st,r=adm.call('workspace/facturas/nota','POST',{'uid':fac['uid'],'tipo':'NC','motivo':'Descuento acordado con el cliente','lineas':[linea('Descuento',500)]});ok(st==422 and 'supera' in r.get('error',''),'una nota de crédito no puede acreditar más de lo facturado')
    st,nc=adm.call('workspace/facturas/nota','POST',{'uid':fac['uid'],'tipo':'NC','motivo':'Descuento acordado con el cliente','lineas':[linea('Descuento',10)]})
    ok(st==200 and nc['tipo']=='NC' and nc['estado']=='EMITIDA' and nc['afecta']['numero']==fac['numero_texto'],'la nota de crédito se emite y cita la factura afectada')
    st,r=adm.call('workspace/facturas/anular','POST',{'uid':fac['uid'],'motivo':'Error en el cliente'});ok(st==409,'una factura con notas no se anula')
    otra=[u for u in uids][0]
    st,r=eje.call('workspace/facturas/anular','POST',{'uid':otra,'motivo':'Error en el monto'});ok(st==403,'un vendedor no anula')
    st,r=sup.call('workspace/facturas/anular','POST',{'uid':otra,'motivo':'Error en el monto'});ok(st==200 and r['estado']=='ANULADA' and r['numero_texto'],'la supervisión anula con motivo y el número se conserva')
    # ---- vendedores facturan; solo ven lo suyo; la consulta no factura
    st,d=eje.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cliente,'lineas':[linea('Inscripción',50)]})
    st,e=eje.call('workspace/facturas/emitir','POST',{'uid':d['uid']});ok(st==200 and e['estado']=='EMITIDA','un vendedor prepara y emite su factura fiscal')
    st,l=eje.call('workspace/facturas');ok(st==200 and l.get('documentos') and all(x['creado_por']==e['creado_por'] for x in l['documentos']),'el vendedor solo ve las facturas que preparó')
    st,r=eje.call('workspace/facturas/ver?uid='+fac['uid']);ok(st==403,'y no abre las de otra persona')
    st,r=con.call('workspace/facturas','POST',{'tipo':'SIM','cliente':{'nombre':'X Y'},'lineas':[linea('A',1)]});ok(st==403,'la consulta no factura')
    # ---- verificación y libro
    st,v=adm.call('workspace/fiscal/verificar');ok(st==200 and v['integro'],f'la cadena de huellas se verifica ({v.get("documentos")} documentos, sin saltos)')
    st,r=eje.call('workspace/libro-ventas?periodo='+hoy[:7]);ok(st==403,'el libro de ventas no es de los vendedores')
    st,lb=adm.call('workspace/libro-ventas?periodo='+hoy[:7])
    ok(st==200 and lb['csv'] and 'Factura simple' not in lb['csv'],'el libro de ventas sale en CSV y no incluye facturas simples')
    st,cl=adm.call('workspace/fiscal/clientes?q=Prueba');ok(st==200 and any(x['rif']=='J-31234567-8' for x in cl['items']),'el RIF y el domicilio del cliente quedan guardados para la próxima factura')
finally:
    if perfil0.get('configurado'):adm.call('workspace/fiscal/perfil','PUT',{k:perfil0[k] for k in ['razon_social','rif','domicilio','modo','imprenta','serie','iva','igtf','leyendas']})
print(n,'comprobaciones')
