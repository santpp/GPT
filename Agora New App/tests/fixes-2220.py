#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.22.0 · Invitar personas y crearlas en la cuenta con su rol (pedido del usuario).
 - Ágora envía una invitación por correo; la persona crea su clave la primera vez con un código («Crea tu clave»).
 - Con la subcuenta conectada, se crea también en la cuenta con los permisos de su rol y queda enlazada; si su correo ya está en la
   cuenta, solo se enlaza y se ajustan los permisos.
 - Reparto aprobado: Administración = admin con todo; Supervisión = ve todo sin ajustes ni pagos; Ejecutivo = solo lo asignado
   (conversaciones, contactos, oportunidades); Consulta = solo lo asignado, contactos y oportunidades, sin conversaciones.
 - Cambiar el rol ajusta los permisos; suspender o eliminar le QUITA los permisos en la cuenta pero no la borra (opción B).
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Deja el canal de correo apagado y borra las personas de prueba.
Uso: python3 tests/fixes-2220.py"""
import os,re,json,urllib.request,subprocess
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def buzon():
    r=urllib.request.Request('http://127.0.0.1:8099/__sent',headers={'Authorization':'Bearer pit-prueba'})
    return json.loads(urllib.request.urlopen(r).read())['sent']
def ultimo_codigo():
    m=re.search(r'(\d{6})',buzon()[-1].get('message',''));return m.group(1) if m else None
def intento(u,pw):
    c=C.__new__(C);c.cj=http.cookiejar.CookieJar();c.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(c.cj));c.ws=None;c.call('session/csrf')
    return c.call('login','POST',{'correo':u,'clave':pw})
def fake(path,method='GET',body=None):
    r=urllib.request.Request('http://127.0.0.1:8099'+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers={'Authorization':'Bearer pit-prueba','Content-Type':'application/json'})
    try:
        with urllib.request.urlopen(r) as x:return json.loads(x.read())
    except urllib.error.HTTPError as e:return json.loads(e.read() or b'{}')
def en_cuenta(correo):return next((u for u in fake('/__usuarios')['users'] if u['email'].lower()==correo.lower()),None)
def encendidos(u):return sorted(k for k,v in (u or {}).get('permissions',{}).items() if v)
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','agora_erp','-e',q],capture_output=True,text=True).stdout
def borrar_pruebas():sql("delete from agora_user_links where user_id in (select id from agora_users where email like '%.inv%@agora.test');delete from agora_users where email like '%.inv%@agora.test'")
def resolver(q):
    a,op,b=re.search(r'(\d+)\s*([+×x*−-])\s*(\d+)',q).groups();a,b=int(a),int(b)
    return a+b if op=='+' else a*b if op in '×x*' else a-b
borrar_pruebas();limpiar()
duena=C('duena@example.test','Clave-Local-2026!')
st,_=duena.call('agency/correo','PUT',{'connectionId':2,'codigoAlEntrar':False})
ok(st==200,'el dueño deja el Correo de Ágora saliendo por ASOCIM (sin código al entrar, para la prueba)')
SHOT=os.environ.get('SHOT','/tmp')
try:
    limpiar();adm=C('admin.asocim@agora.test')
    # ---------- invitar y crear en la cuenta ----------
    st,r=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Lucas Prueba','correo':'lucas.inv1@agora.test','rol':'EJECUTIVO','enCuenta':True})
    ok(st==200 and r['invitacion']['enviada'] is True,'la invitación sale por correo')
    ok(r['cuenta']['ok'] and r['cuenta']['accion']=='creada',f'la persona se crea también en la cuenta {r["cuenta"]}')
    ult=buzon()[-1];msg=ult.get('message','')+ult.get('html','')
    ok('/entrar?invitacion=lucas.inv1%40agora.test' in msg and 'Crear mi clave' in msg,'el correo trae el botón «Crear mi clave» con su enlace')
    u=en_cuenta('lucas.inv1@agora.test');p=(u or {}).get('permissions',{})
    ok(u and u['roles']['role']=='user' and u['_companyId']=='comp-asocim' and u['roles']['locationIds']==['LOCasocim000000001'] and u['_lang']=='es','en la cuenta: usuario de la subcuenta, en español, con la agencia que dice la cuenta')
    ok(p.get('assignedDataOnly') is True and p.get('conversationsEnabled') and p.get('contactsEnabled') and p.get('opportunitiesEnabled') and not p.get('settingsEnabled') and not p.get('paymentsEnabled'),f'Ejecutivo = solo lo asignado: conversaciones, contactos y oportunidades ({encendidos(u)})')
    ok(sql("select company_id from agora_ghl_connections where tenant_id=2").strip()=='comp-asocim','la agencia de la subcuenta queda guardada para la próxima vez')
    st,l=adm.call('workspace/usuarios');yo=next(x for x in l['items'] if x['correo']=='lucas.inv1@agora.test')
    ok(yo['invitacion'] is True and yo['enlace'] and yo['enlace']['id']==u['id'],'en Usuarios y roles: «Invitación pendiente» y enlazada con su persona de la cuenta')
    ok('password' not in json.dumps(l) and '!invitacion' not in json.dumps(l),'la lista no muestra claves ni marcas internas')
    limpiar();ok(intento('lucas.inv1@agora.test','Cualquier-Clave-2026!')[0]==401,'con la invitación pendiente no se entra con ninguna clave')
    ok(adm.call('workspace/usuarios/invitar','POST',{'nombre':'Otro','correo':'lucas.inv1@agora.test','rol':'EJECUTIVO'})[0]==409,'no se invita dos veces el mismo correo')
    # ---------- reparto de roles ----------
    lid=r['id']
    st,x=adm.call('workspace/usuarios/rol','POST',{'id':lid,'rol':'SUPERVISOR'});p=en_cuenta('lucas.inv1@agora.test')['permissions']
    ok(st==200 and x['cuenta']['ok'] and p['assignedDataOnly'] is False and p['conversationsEnabled'] and p['campaignsEnabled'] and not p['settingsEnabled'] and not p['paymentsEnabled'],'Supervisión = ve todo, sin ajustes ni pagos')
    adm.call('workspace/usuarios/rol','POST',{'id':lid,'rol':'ADMIN'});u=en_cuenta('lucas.inv1@agora.test')
    ok(u['roles']['role']=='admin' and all(v for k,v in u['permissions'].items() if k not in('assignedDataOnly','campaignsReadOnly','workflowsReadOnly')),'Administración = administrador con todo')
    st,x=adm.call('workspace/usuarios/estado','POST',{'id':lid,'estado':'inactivo'});u=en_cuenta('lucas.inv1@agora.test')
    ok(st==200 and u and encendidos(u)==['assignedDataOnly'] and 'sigue creada' in x['cuenta']['mensaje'],'suspender le quita los permisos en la cuenta, sin borrarla (opción B)')
    adm.call('workspace/usuarios/estado','POST',{'id':lid,'estado':'activo'});ok(len(encendidos(en_cuenta('lucas.inv1@agora.test')))>20,'reactivar le devuelve los permisos de su rol')
    st,r2=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Carla Consulta','correo':'carla.inv2@agora.test','rol':'CONSULTA','enCuenta':True});p=en_cuenta('carla.inv2@agora.test')['permissions']
    ok(r2['cuenta']['ok'] and p['assignedDataOnly'] and p['contactsEnabled'] and p['opportunitiesEnabled'] and not p['conversationsEnabled'],'Consulta = solo lo asignado, contactos y oportunidades, sin conversaciones')
    # ---------- ya estaba en la cuenta ----------
    fake('/users/','POST',{'companyId':'comp-asocim','firstName':'Rosa','lastName':'Externa','email':'rosa.inv3@agora.test','password':'x','type':'account','role':'user','locationIds':['LOCasocim000000001'],'permissions':{}})
    adm.call('workspace/usuarios/equipo','POST',{})
    antes=len(fake('/__usuarios')['users'])
    st,r3=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Rosa Externa','correo':'rosa.inv3@agora.test','rol':'EJECUTIVO','enCuenta':True})
    ok(r3['cuenta']['accion']=='enlazada' and len(fake('/__usuarios')['users'])==antes,'si el correo ya está en la cuenta, no se duplica: se enlaza')
    ok(en_cuenta('rosa.inv3@agora.test')['permissions'].get('conversationsEnabled') is True,'y se le ajustan los permisos de su rol')
    # ---------- sin apellido, sin cuenta, sin permiso ----------
    st,r4=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Solonombre','correo':'solo.inv4@agora.test','rol':'EJECUTIVO','enCuenta':True})
    ok(st==200 and r4['cuenta']['ok'] is False and 'apellido' in r4['cuenta']['mensaje'] and not en_cuenta('solo.inv4@agora.test'),'sin apellido: queda en Ágora y la pantalla explica que la cuenta pide nombre y apellido')
    st,r5=adm.call('workspace/usuarios/invitar','POST',{'nombre':'Sin Cuenta','correo':'sin.inv5@agora.test','rol':'EJECUTIVO','enCuenta':False})
    ok(st==200 and r5['cuenta'] is None and not en_cuenta('sin.inv5@agora.test'),'sin marcar la casilla no toca la cuenta')
    st,r6=adm.call('workspace/usuarios/cuenta','POST',{'id':r5['id']});ok(st==200 and r6.get('accion')=='creada' and en_cuenta('sin.inv5@agora.test'),f'«Crear en la cuenta» la crea después {st} {r6}')
    limpiar();eje=C('ejecutivo1.asocim@agora.test')
    ok(eje.call('workspace/usuarios/invitar','POST',{'nombre':'No Debe','correo':'no.inv6@agora.test','rol':'ADMIN'})[0]==403,'un ejecutivo no puede invitar')
    ok(adm.call('workspace/usuarios/invitar','POST',{'nombre':'Rol Raro','correo':'raro.inv7@agora.test','rol':'MASTER'})[0]==403,'no se puede invitar como dueño')
    st,x=adm.call('workspace/usuarios/invitacion','POST',{'id':lid});ok(st==200 and x['invitacion']['enviada'],'«Reenviar invitación» la vuelve a enviar')
    # ---------- crear la clave desde el enlace (pantalla) ----------
    CLAVE='Clave-Invitada-2026!'
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w,h in ((390,844),):
                limpiar();pg=br.new_context(viewport={'width':w,'height':h},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
                pg.goto(B+'/entrar?invitacion=lucas.inv1%40agora.test');pg.wait_for_selector('.lg-paso',timeout=15000)
                ok(pg.inner_text('.lg-paso h2')=='Crea tu clave' and pg.input_value('.lg-paso input[name=correo]')=='lucas.inv1@agora.test',f'{w}px: el enlace abre «Crea tu clave» con su correo puesto')
                ok('invitacion' not in pg.url,f'{w}px: el correo no se queda en la barra de direcciones')
                pg.screenshot(path=SHOT+'/crea-clave.png')
                pg.click('.lg-paso .lg-go');pg.wait_for_selector('.lg-paso input[name=codigo]',timeout=10000)
                cod=ultimo_codigo();pg.fill('.lg-paso input[name=codigo]',cod);pg.fill('.lg-paso input[name=clave]',CLAVE);pg.fill('.lg-paso input[name=clave2]',CLAVE)
                ok(pg.inner_text('.lg-paso .lg-go').strip().startswith('Crear clave'),f'{w}px: el botón dice «Crear clave»')
                pg.click('.lg-paso .lg-go');pg.wait_for_selector('.lg-ok',timeout=10000)
                ok('Ya puedes entrar' in pg.inner_text('.lg-ok'),f'{w}px: confirma que ya puede entrar')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                pg.close()
            limpiar();st,d=intento('lucas.inv1@agora.test',CLAVE);ok(st==200 and d.get('usuario'),'con la clave creada entra')
            st,l=adm.call('workspace/usuarios');ok(next(x for x in l['items'] if x['correo']=='lucas.inv1@agora.test')['invitacion'] is False,'deja de estar «Invitación pendiente»')
            st,x=adm.call('workspace/usuarios/invitacion','POST',{'id':lid});ok(st==200 and x.get('tipo')=='clave','con su clave creada, ya no es invitación: se le manda el enlace para crear una clave nueva (2.24.0)')
            # ---------- pantalla de Usuarios y roles ----------
            for w,h in ((1280,800),(390,844)):
                pg=entrar(br,'admin.asocim@agora.test',w,h);pg.goto(B+'/usuarios');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000);pg.wait_for_selector('[data-usuario-nuevo]',timeout=15000)
                ok('Invitación pendiente' in pg.inner_text('main#app'),f'{w}px: la lista marca «Invitación pendiente»')
                pg.click('[data-usuario-nuevo]');pg.wait_for_selector('#layer form',timeout=8000)
                ok(pg.locator('#layer input[name=clave]').count()==0 and pg.locator('#layer input[name=enCuenta]').is_checked(),f'{w}px: «Invitar persona» no pide clave y trae marcada «Crearla también en la cuenta»')
                if w==1280:pg.locator('#layer .modal, #layer [role=dialog]').first.screenshot(path=SHOT+'/invitar.png')
                pg.fill('#layer input[name=nombre]','Pantalla Invitada');pg.fill('#layer input[name=correo]',f'pantalla{w}.inv8@agora.test')
                pg.click('#layer [type=submit]');pg.wait_for_selector('.inv-res',timeout=15000)
                t=pg.inner_text('#layer');ok('Invitación enviada' in t and 'Creada en la cuenta' in t,f'{w}px: el resultado dice que salió la invitación y que se creó en la cuenta')
                if w==1280:pg.locator('#layer .modal, #layer [role=dialog]').first.screenshot(path=SHOT+'/invitada.png')
                pg.click('#layer [data-close]');pg.wait_for_timeout(500)
                txt=pg.inner_text('main#app');ok(not re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',txt),f'{w}px: sin nombres del proveedor')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w and not pg.errs,f'{w}px: sin desborde ni errores {pg.errs[:2]}')
                pg.close()
        finally:br.close()
    # ---------- eliminar: opción B ----------
    st,q=adm.call('workspace/usuarios/borrar/desafio','POST',{'id':r2['id']})
    st,x=adm.call('workspace/usuarios','DELETE',{'id':r2['id'],'token':q['token'],'respuesta':resolver(q['pregunta'])})
    u=en_cuenta('carla.inv2@agora.test');ok(st==200 and u and encendidos(u)==['assignedDataOnly'],'eliminar de Ágora la deja creada en la cuenta, sin permisos (opción B)')
finally:
    limpiar();duena.call('agency/correo','PUT',{'connectionId':None});borrar_pruebas()
    ok(sql("select count(*) from agora_users where email like '%.inv%@agora.test'").strip()=='0','se borran las personas de prueba y el canal queda apagado')
print(n,'comprobaciones')
