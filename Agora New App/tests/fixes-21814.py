#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.14 · Plantilla de los correos de Ágora (pedido del usuario):
con el logo y el código listo para copiarlo en el teléfono (asunto, vista previa y primera frase con el código, como lo reconocen Gmail
y los avisos del teléfono para ofrecer «Copiar código»; el código se selecciona entero con un toque).
Requiere la API local en :8090 y la cuenta simulada. Deja el canal apagado al terminar. Uso: python3 tests/fixes-21814.py"""
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
import os
RAIZ=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..')
def plantilla(logo):
    php="""$src=file_get_contents($argv[1]);preg_match('/private static function template\\(.*?\\n    \\}\\n/s',$src,$m);eval('class T{'.str_replace('private static','public static',$m[0]).'}');echo T::template('Termina de entrar a Ágora','Escribe este código.','541244','Caduca en 10 minutos.',$argv[2]==='-'?null:$argv[2]);"""
    return subprocess.run(['php','-r',php,os.path.join(RAIZ,'servidor/motor/src/AccessChannel.php'),logo],capture_output=True,text=True).stdout
h=plantilla('https://tests.agoradigital.app/assets/correo/agora-logo-correo.png')
ok('<img src="https://tests.agoradigital.app/assets/correo/agora-logo-correo.png"' in h and 'alt="Ágora"' in h,'con dirección https el correo lleva el logo (PNG público)')
ok(os.path.isfile(os.path.join(RAIZ,'public_html/assets/correo/agora-logo-correo.png')),'el logo del correo va en el paquete')
ok('<img' not in plantilla('http://127.0.0.1:8090/x.png') and '>Ágora</span>' in plantilla('-'),'sin https no hay imagen rota: se escribe «Ágora»')
ok('display:none' in h and 'Tu código de acceso es 541244' in h,'la vista previa (lo que se ve en el aviso) empieza con el código')
ok('user-select:all' in h and '>541244</span>' in h,'el código va solo, en un bloque que se selecciona entero con un toque')
ok('<script' not in h.lower() and 'GoHighLevel' not in h and 'LeadConnector' not in h,'sin código ejecutable ni nombres del proveedor')
limpiar();duena=C();duena.entrar('duena@example.test','Clave-Local-2026!')
try:
    st,d=duena.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':True});ok(st==200,'canal de correo encendido para la prueba')
    limpiar();e=C();st,d=e.entrar('ejecutivo1.asocim@agora.test',P)
    m=buzon()[-1];cod=re.search(r'(\d{6})',m.get('message','')).group(1)
    ok(m.get('subject','').startswith(cod+' es tu código de acceso a Ágora'),f'el asunto empieza con el código ({m.get("subject")})')
    ok(m.get('message','').startswith('Tu código de acceso es '+cod),'el texto simple empieza con «Tu código de acceso es …»')
    ok('Termina de entrar a Ágora' in m.get('html','') and 'user-select:all' in m.get('html',''),'el correo que sale usa la plantilla nueva')
finally:
    limpiar();st,d=duena.call('agency/correo','PUT',{'connectionId':None})
    ok(st==200 and d.get('connectionId') is None,'el canal queda apagado al terminar')
print(n,'comprobaciones')
