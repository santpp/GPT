#!/usr/bin/env python3
"""Agora New App 2.13.0 · Correo de Ágora, código al entrar, equipo de confianza y «¿Olvidaste tu clave?».
- Solo el dueño elige por qué subcuenta salen los correos (Agencia → Correo de Ágora) y puede probar el envío.
- Con el canal elegido, entrar pide un código de 6 dígitos que llega por correo; sin «Recuérdame» la sesión es normal.
- Con «Recuérdame», la sesión dura 14 días y el navegador queda de confianza: no vuelve a pedir el código (cookie, no IP).
- «¿Olvidaste tu clave?» manda un código y permite elegir una clave nueva; cierra las sesiones y los equipos de confianza.
- Respuestas iguales exista o no el correo. Los códigos caducan, se usan una vez y tienen intentos limitados.
Requiere la API local en :8090 y la cuenta simulada (lee el «buzón» de la cuenta simulada). Deja el canal apagado al terminar.
Uso: python3 tests/acceso-correo.py"""
import sys,subprocess,json,urllib.request,http.cookiejar,re,time
B='http://127.0.0.1:8090/api/';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def limpiar():subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['sent']
def ultimo_codigo():
    m=re.search(r'(\d{6})',buzon()[-1].get('message',''));return m.group(1) if m else None
class C:
    def __init__(s):
        s.cj=http.cookiejar.CookieJar();s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj));s.ws=None;s.call('session/csrf')
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
    def cookie(s,name):return next((c for c in s.cj if c.name==name),None)
    def entrar(s,u,pw,**k):
        st,d=s.call('login','POST',{'correo':u,'clave':pw,**k});s.ws=d.get('activeTenantId');return st,d
P='Agora-Pruebas-2026!'
limpiar();duena=C();duena.entrar('duena@example.test','Clave-Local-2026!')
try:
    # ---- canal (solo dueño)
    limpiar();adm=C();adm.entrar('admin.asocim@agora.test',P)
    ok(adm.call('agency/correo')[0]==403,'solo el dueño ve y cambia el correo de Ágora')
    st,d=duena.call('agency/correo')
    ok(st==200 and any(o['id']==2 for o in d.get('opciones',[])),f'el dueño ve las subcuentas conectadas para enviar correos {[o["nombre"] for o in d.get("opciones",[])]}')
    st,d=duena.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':True})
    ok(st==200 and d.get('connectionId')==2 and d.get('codigoAlEntrar') is True,'el dueño elige la subcuenta por la que salen los correos')
    antes=len(buzon());st,d=duena.call('agency/correo/probar','POST',{})
    ok(st==200 and len(buzon())==antes+1 and buzon()[-1].get('type')=='Email' and 'Prueba' in buzon()[-1].get('subject',''),'«Probar envío» manda un correo por la cuenta elegida')
    # ---- código al entrar
    limpiar();e=C();st,d=e.entrar('ejecutivo1.asocim@agora.test',P,recordar=True)
    ok(st==200 and d.get('codigo') is True and not d.get('usuario') and '•' in d.get('destino','') and not e.cookie('agora_session'),f'con el canal, la clave correcta pide un código y aún no abre sesión ({d.get("destino")})')
    cod=ultimo_codigo();ok(cod is not None,'el código llega por correo (buzón de la cuenta simulada)')
    st,_=e.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':'000000' if cod!='000000' else '111111','recordar':True})
    ok(st==401,'un código equivocado no entra')
    ok(e.call('login/reenviar','POST',{'desafio':d['desafio']})[0]==429,'no se puede pedir otro código antes de un minuto')
    st,d2=e.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':cod,'recordar':True})
    ses,dev=e.cookie('agora_session'),e.cookie('agora_device')
    dias=(ses.expires-time.time())/86400 if ses and ses.expires else 0
    ok(st==200 and d2.get('usuario') and ses and dev and 13.5<dias<14.5,f'el código correcto abre la sesión de 14 días y marca el equipo de confianza ({dias:.1f} días)')
    ok(e.call('login/codigo','POST',{'desafio':d['desafio'],'codigo':cod})[0]==401,'el código no se puede usar dos veces')
    e.call('logout','POST',{});limpiar();st,d3=e.entrar('ejecutivo1.asocim@agora.test',P,recordar=True)
    ok(st==200 and d3.get('usuario') and not d3.get('codigo'),'en el equipo de confianza no vuelve a pedir el código')
    limpiar();f=C();st,d4=f.entrar('ejecutivo1.asocim@agora.test',P)
    ok(st==200 and d4.get('codigo') is True,'otro navegador sí pide el código')
    st,d5=f.call('login/codigo','POST',{'desafio':d4['desafio'],'codigo':ultimo_codigo()})
    ok(st==200 and not f.cookie('agora_device') and f.cookie('agora_session') and not f.cookie('agora_session').expires,'sin «Recuérdame»: sesión normal y sin marcar el equipo')
    limpiar();st,_=C().entrar('ejecutivo1.asocim@agora.test','clave-mala-123456')
    ok(st==401,'con la clave equivocada no se manda ningún código')
    # ---- ¿Olvidaste tu clave?
    limpiar();g=C();antes=len(buzon())
    st,o1=g.call('clave/olvido','POST',{'correo':'ejecutivo2.asocim@agora.test'});st2,o2=g.call('clave/olvido','POST',{'correo':'nadie@agora.test'})
    ok(st==200 and st2==200 and o1==o2 and len(buzon())==antes+1,'«¿Olvidaste tu clave?» responde igual exista o no el correo, y solo envía al que existe')
    cod=ultimo_codigo();NUEVA='Nueva-Clave-Prueba-2026!'
    ok(g.call('clave/restablecer','POST',{'correo':'ejecutivo2.asocim@agora.test','codigo':'123456' if cod!='123456' else '654321','clave':NUEVA})[0]==401,'un código equivocado no cambia la clave')
    ok(g.call('clave/restablecer','POST',{'correo':'ejecutivo2.asocim@agora.test','codigo':cod,'clave':'corta'})[0] in (400,422),'la clave nueva debe cumplir el largo mínimo')
    st,_=g.call('clave/restablecer','POST',{'correo':'ejecutivo2.asocim@agora.test','codigo':cod,'clave':NUEVA})
    ok(st==200,'con el código correcto se elige una clave nueva')
    limpiar();ok(C().entrar('ejecutivo2.asocim@agora.test',P)[0]==401 and C().entrar('ejecutivo2.asocim@agora.test',NUEVA)[1].get('codigo') is True,'la clave vieja deja de servir y la nueva funciona (y pide su código)')
    # vuelve a la clave de las pruebas
    limpiar();g.call('clave/olvido','POST',{'correo':'ejecutivo2.asocim@agora.test'});ok(g.call('clave/restablecer','POST',{'correo':'ejecutivo2.asocim@agora.test','codigo':ultimo_codigo(),'clave':P})[0]==200,'se restablece la clave de las pruebas')
    ok(e.call('yo')[0] in(401,200) and e.entrar('ejecutivo1.asocim@agora.test',P,recordar=True)[1].get('usuario'),'el equipo de confianza de otra persona no se afecta')
finally:
    limpiar();st,d=duena.call('agency/correo','PUT',{'connectionId':None})
    ok(st==200 and d.get('connectionId') is None,'el canal queda apagado al terminar (las demás pruebas entran sin código)')
    limpiar();ok(C().entrar('ejecutivo1.asocim@agora.test',P)[1].get('usuario'),'sin canal elegido, se entra con la clave como antes')
print(n,'comprobaciones')
