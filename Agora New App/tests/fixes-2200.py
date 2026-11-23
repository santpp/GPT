#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.20.0 · Deploy (pedido del usuario): correos de convenios y facturas, notificaciones dentro de Ágora y el cron único.
- Convenio: al prepararlo se envía el enlace de firma al cliente desde su subcuenta; la firma avisa a administración.
- Factura: «Enviar por correo» con enlace público personal; el cliente la ve sin sesión; la primera vez avisa a quien la envió.
- Campana: notificaciones por persona, nuevas y marcar leídas; reportes y accesos sospechosos avisan a los dueños.
- Cron (bin/cron.php): worker + pasada de la Bandeja de Mobile + notificación de mensajes nuevos; diagnóstico dice cuándo corrió.
Requiere la API local (signing_enabled en la configuración local), la cuenta simulada y ASOCIM conectada. Uso: python3 tests/fixes-2200.py"""
import os,re,time,json,subprocess,urllib.request
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
RAIZ=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..');SRV=os.path.join(RAIZ,'servidor')
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'});return json.loads(urllib.request.urlopen(r).read())['sent']
def db(sql):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',sql],capture_output=True,text=True).stdout.strip()
def cron():return subprocess.run(['php',os.path.join(SRV,'bin/cron.php'),'--once'],capture_output=True,text=True,env=dict(os.environ,AGORA_GHL_FAKE='http://127.0.0.1:8099',APP_ENV='development'),timeout=120).stdout
a=C('admin.asocim@agora.test')
# ---- convenio por correo
st,c=a.call('contratos','POST',{'cliente':{'nombre':'María Prueba Correo','correo':'maria.prueba@ejemplo.test','cedula':'V-12345678','telefono':''},'curso':{'nombre':'Curso de prueba','cuota_mensual':100,'meses':3,'mes_inicio':'2026-12','cuotas_por_mes':1,'cargos':[]}})
ok(st==200 and c.get('uid'),f'se crea un convenio de prueba ({st} {str(c)[:80]})')
antes=len(buzon());st,r=a.call(f"contratos/{c['uid']}/enviar",'POST',{})
ok(st==200 and r.get('delivery')=='correo' and r.get('correo')=='maria.prueba@ejemplo.test','preparar el convenio lo envía por correo al cliente')
m=buzon()[-1];ok(len(buzon())==antes+1 and 'firmar' in m.get('subject','').lower() and 'Revisar y firmar el convenio' in m.get('html','') and r['enlace'] in m.get('html',''),'el correo trae el botón «Revisar y firmar» con el enlace')
ok(r.get('via')=='subcuenta','sale por la propia subcuenta del cliente')
st,d=a.call(f"contratos/{c['uid']}");ok(d.get('ultimoEnvio',{}).get('enviado') is True,'el convenio recuerda a quién y cuándo se envió')
tok=r['enlace'].split('#')[1]
s2=C('ejecutivo1.asocim@agora.test')
import http.cookiejar
jar=http.cookiejar.CookieJar();anon=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def pub_get(path):
    try:
        with anon.open(B+'/api/'+path) as x:return x.status,json.loads(x.read())
    except urllib.error.HTTPError as e:return e.code,json.loads(e.read() or b'{}')
def pub_post(path,body):
    pub_get('session/csrf');csrf=next((c.value for c in jar if c.name=='agora_csrf'),'')
    rq=urllib.request.Request(B+'/api/'+path,data=json.dumps(body).encode(),method='POST',headers={'Content-Type':'application/json','X-CSRF-Token':csrf})
    try:
        with anon.open(rq) as x:return x.status,json.loads(x.read())
    except urllib.error.HTTPError as e:return e.code,json.loads(e.read() or b'{}')
# ---- factura por correo
uid=db("select uid from agora_fiscal_documents where org_id=2 and status='EMITIDA' order by id limit 1")
ok(uid!='','hay una factura emitida para probar')
st,r=a.call('workspace/facturas/enviar','POST',{'uid':uid});ok(st==422,'sin correo del cliente pide escribirlo')
st,r=a.call('workspace/facturas/enviar','POST',{'uid':uid,'correo':'cliente.factura@ejemplo.test'})
ok(st==200 and r.get('enviado') and r.get('enlace','').count('/factura.html#')==1,'la factura se envía por correo con su enlace')
m=buzon()[-1];ok('Ver la factura' in m.get('html','') and r['enlace'] in m.get('html',''),'el correo trae el botón «Ver la factura»')
ftok=r['enlace'].split('#')[1]
st,p=pub_get('factura-publica?t='+ftok);ok(st==200 and '<' in p.get('html','') and p.get('numero'),'el cliente ve la factura sin sesión')
ok(pub_get('factura-publica?t='+'0'*64)[0]==404 and pub_get('factura-publica?t=malo')[0]==404,'un enlace inventado o roto no abre nada')
st,e=a.call('workspace/facturas/envios?uid='+uid);ok(e['items'][0]['visto'] and e['items'][0]['vistas']>=1,'en «Enviar por correo» se ve que el cliente la abrió')
st,nt=a.call('notificaciones');ok(any(x['tipo']=='factura.vista' for x in nt['items']),'quien la envió recibe el aviso «El cliente abrió la factura»')
# ---- notificaciones
ids=[x['id'] for x in nt['items'] if not x['leida']]
ok(nt['noLeidas']>=1,'la campana cuenta las no leídas')
ok(a.call('notificaciones/leidas','POST',{'ids':ids[:1]})[0]==200 and a.call('notificaciones')[1]['noLeidas']==nt['noLeidas']-1,'marcar una como leída baja el contador')
ok(a.call('notificaciones/leidas','POST',{})[0]==422,'marcar sin decir cuáles se rechaza')
ok(a.call('notificaciones/leidas','POST',{'todas':True})[0]==200 and a.call('notificaciones')[1]['noLeidas']==0,'marcar todas como leídas')
ok(not any(x['tipo']=='factura.vista' for x in s2.call('notificaciones')[1]['items']),'cada persona ve solo sus notificaciones')
st,sg=pub_post('sign',{'token':tok,'accept':True,'name':'María Prueba Correo'})
ok(st==200,f'el cliente firma el convenio ({st} {str(sg)[:80]})')
ok(any('Convenio firmado' in x['titulo'] for x in a.call('notificaciones')[1]['items']),'la firma avisa a administración')
s2.call('reportes','POST',{'descripcion':'Prueba de aviso de reporte'})
d=C('duena@example.test','Clave-Local-2026!');ok(any(x['tipo']=='reporte' for x in d.call('notificaciones')[1]['items']),'un reporte de problema avisa a los dueños')
# ---- cron
cron();subprocess.run(['curl','-s','-X','POST','-H','Authorization: Bearer pit-prueba','-H','Content-Type: application/json','-d','{"conversationId":"c0","body":"Mensaje de la prueba del cron"}','http://127.0.0.1:8099/__entrante'],capture_output=True)
db("update ghl_sync_state set pulse_next_at=0,request_not_before=0");out=cron()
ok('worker bien' in out and '1 pasada' in out,f'el cron corre: {out.strip()}')
ej=C('ejecutivo1.asocim@agora.test');n2=ej.call('notificaciones')[1]['items']
ok(any(x['texto']=='Mensaje de la prueba del cron' and x['enlace']=='/bandeja?c=c0' for x in n2),'un mensaje nuevo del cliente llega como notificación a quien tiene la conversación')
dg=subprocess.run(['php',os.path.join(SRV,'bin/diagnostico.php')],capture_output=True,text=True).stdout
ok(re.search(r'Última corrida\s+hace \d+ s',dg),'el diagnóstico dice cuándo corrió el cron')
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        for w,h in ((1280,800),(390,844)):
            pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda x:errs.append(str(x)))
            pg.goto(B+'/factura.html#'+ftok);pg.wait_for_selector('iframe',timeout=10000);pg.wait_for_timeout(800)
            ok('Factura N.º' in pg.inner_text('h1') and pg.locator('#imp').is_visible() and '#' not in pg.url,f'{w}px: la página pública muestra la factura con «Imprimir o guardar en PDF» y quita el token de la dirección')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not errs,f'{w}px: sin desborde ni errores {errs[:2]}')
            if w==1280:pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/factura-publica.png')
            pg.close()
            subprocess.run(['curl','-s','-X','POST','-H','Authorization: Bearer pit-prueba','-H','Content-Type: application/json','-d','{"conversationId":"c0","body":"Aviso en pantalla '+str(w)+'"}','http://127.0.0.1:8099/__entrante'],capture_output=True)
            pg=entrar(br,'ejecutivo1.asocim@agora.test',w,h);pg.goto(B+'/centro');pg.wait_for_selector('#b-notif',timeout=20000);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_timeout(1500)
            db("update ghl_sync_state set pulse_next_at=0,request_not_before=0");cron()
            pg.evaluate("document.dispatchEvent(new Event('visibilitychange'))");pg.wait_for_timeout(2500)
            ok(pg.locator('#b-notif .notif-n:not([hidden])').count()==1 and 'Nuevo mensaje' in pg.inner_text('#toast'),f'{w}px: llega el aviso y la campana muestra cuántas hay sin leer')
            pg.click('#b-notif');pg.wait_for_selector('.pop.ntf .ntf-i',timeout=5000)
            if w==1280:pg.screenshot(path=os.environ.get('SHOT','/tmp')+'/campana.png')
            pg.locator('.pop.ntf .ntf-i.nueva').first.click();pg.wait_for_timeout(3500)
            ok('/bandeja' in pg.url,f'{w}px: tocar la notificación abre la conversación en la Bandeja')
            ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
            pg.close()
    finally:
        br.close()
print(n,'comprobaciones')
