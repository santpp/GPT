#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.19.2 · Cobertura del diccionario de errores (pedido del usuario: «verifica todos los endpoints y acciones que se
guardan y si sus errores están en el diccionario»). Recorre las 155 rutas (tests/rutas-api.json) con los cuatro métodos y datos vacíos,
como administración, ejecutivo, dueña y sin sesión, y exige:
- todo error trae su código AG-ÁREA-NNN y el código existe en el diccionario;
- ningún error interno (5xx) salvo los dos avisos de «no habilitado» (devices/test y workspace/sync);
- un guardado vacío no borra las cifras del mes ni el catálogo de productos; un JSON roto es un 400 con código.
Se salta «olvidar el token de Consumos» (DELETE admin/consumption/token), que sí borra. Requiere la API local y la cuenta simulada."""
import time,os,json,subprocess,urllib.request
src=open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'acceso-correo.py'),encoding='utf-8').read()
exec(src[src.index('import sys'):src.index("limpiar();duena=C()")])
RAIZ=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..')
rutas=json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'rutas-api.json')))
acceso={'login','login/codigo','login/reenviar','clave/olvido','clave/restablecer','clave','yo','me'}
AVISOS={'devices/test','workspace/sync'};SALTAR={('admin/consumption/token','DELETE')}
res=[];k=[0]
def golpe(c,quien,r,m,body=None):
    k[0]+=1
    if k[0]%40==0:limpiar()
    st,d=c.call(r,m,None if m=='GET' else ({} if body is None else body))
    res.append((quien,r,m,st,d if isinstance(d,dict) else {}))
limpiar();adm=C();adm.entrar('admin.asocim@agora.test',P)
limpiar();due=C();due.entrar('duena@example.test','Clave-Local-2026!')
limpiar();eje=C();eje.entrar('ejecutivo1.asocim@agora.test',P)
antes_mes=adm.call('workspace/board-month?period=2026-11')[1].get('settings');antes_prod=adm.call('workspace/productos')[1]
for r in rutas:
    if r in acceso:continue
    c,q=(due,'dueña') if r.startswith(('agency/','admin/')) else (adm,'admin')
    for m in ('GET','POST','PUT','DELETE'):
        if (r,m) not in SALTAR:golpe(c,q,r,m)
    if not r.startswith(('agency/','admin/')):
        for m in ('POST','PUT','DELETE'):golpe(eje,'ejecutivo',r,m)
anon=C()
for r in sorted(acceso)+['workspace/ventas','agency/accounts','workspace/send']:
    for m in ('GET','POST'):golpe(anon,'sin sesión',r,m)
errores=[x for x in res if x[3]>=400]
sin=[x[:4] for x in errores if not x[4].get('codigo')]
ok(len(res)>=900 and not sin,f'{len(res)} llamadas: los {len(errores)} errores traen su código {sin[:3]}')
cods=sorted({x[4]['codigo'] for x in errores})
php="require $argv[2].'/servidor/app/src/Core/Errores.php';foreach(json_decode($argv[1],true) as $c)if(!\\Agora\\Core\\Errores::explicar($c))echo $c,' ';"
desc=subprocess.run(['php','-r',php,json.dumps(cods),RAIZ],capture_output=True,text=True).stdout.split()
ok(not desc,f'los {len(cods)} códigos distintos están en el diccionario, con su razón {desc[:5]}')
g=[x[:4] for x in errores if x[3]>=500 and x[1] not in AVISOS]
ok(not g,f'ningún error interno con datos vacíos {g[:5]}')
def leer_mes():
    # Tras cientos de llamadas la cuenta puede pedir esperar (429 con retryAfter, tope de llamadas por segundo): se espera y se vuelve a leer.
    for _ in range(8):
        st,d=adm.call('workspace/board-month?period=2026-11')
        if st!=429:return d
        time.sleep(min(10,int(d.get('retryAfter') or 2)+1))
    return d
ok(leer_mes().get('settings')==antes_mes,'un guardado vacío no tocó las cifras del mes')
p2=adm.call('workspace/productos')[1];ok(p2.get('productos')==antes_prod.get('productos') and p2.get('rubros')==antes_prod.get('rubros'),'un guardado vacío no tocó el catálogo de productos')
st,d=adm.call('workspace/board-month?period=2026-11','POST',{'period':'2026-11'});ok(st==422 and d.get('codigo')=='AG-GES-422','cifras del mes vacías: 422 con su código')
st,d=adm.call('workspace/productos','PUT',{});ok(st==422 and d.get('codigo')=='AG-GES-422','productos sin listas: 422 con su código')
csrf=next((x.value for x in adm.cj if x.name=='agora_csrf'),'')
rq=urllib.request.Request(B+'workspace/opportunity/new?connectionId=2',data=b'{roto',method='POST',headers={'Content-Type':'application/json','X-CSRF-Token':csrf,'X-Workspace-ID':str(adm.ws)})
try:adm.op.open(rq);st=200;d={}
except urllib.error.HTTPError as e:st=e.code;d=json.loads(e.read() or b'{}')
ok(st in(400,422) and str(d.get('codigo','')).startswith('AG-CTA-4'),f'datos mal formados a la cuenta: se rechazan con su código ({st} {d.get("codigo")})')
print(n,'comprobaciones')
