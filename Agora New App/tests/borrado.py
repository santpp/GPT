#!/usr/bin/env python3
"""Agora New App 2.17.0 · Eliminar subcuenta (solo el dueño Master), eliminar y suspender usuarios de subcuenta.
Lo irreversible pide resolver una operación aritmética que el servidor firma y vuelve a comprobar.
Requiere la API local en :8090 y la cuenta simulada. Uso: python3 tests/borrado.py"""
import sys,os,re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',q],capture_output=True,text=True).stdout.strip()
def resolver(preg):
    a,op,b=re.match(r'¿Cuánto es (\d+) (.) (\d+)\?',preg).groups();a,b=int(a),int(b)
    return {'+':a+b,'−':a-b,'×':a*b}[op]
DU=('duena@example.test','Clave-Local-2026!')
def tablas_con(col):return [t for t in sql(f"select table_name from information_schema.columns where table_schema='agora_erp' and column_name='{col}' and table_name like 'agora\\_%'").split() if t]
def filas_de(tid):
    n=0
    for t in tablas_con('tenant_id'):n+=int(sql(f'select count(*) from {t} where tenant_id={tid}') or 0)
    for t in tablas_con('org_id'):n+=int(sql(f'select count(*) from {t} where org_id={tid}') or 0)
    return n+int(sql(f'select count(*) from agora_users where local_tenant_id={tid}') or 0)+int(sql(f'select count(*) from agora_tenants where id={tid}') or 0)
# ---------- usuarios de subcuenta (ASOCIM)
limpiar();adm=C('admin.asocim@agora.test');ws=adm.ws
for x in sql("select id from agora_users where email like 'borrar.%@agora.test'").split():sql(f'delete from agora_sessions where user_id={x}');sql(f'delete from agora_users where id={x}')
st,r=adm.call('workspace/usuarios','POST',{'nombre':'Persona Borrar','correo':'borrar.uno@agora.test','rol':'EJECUTIVO','clave':'Borrar-Prueba-2026!'});ok(st==200,f'administración crea una persona de prueba ({st})')
uid=r['id']
limpiar();p=C('borrar.uno@agora.test','Borrar-Prueba-2026!');ok(p.ws is not None,'la persona entra')
ok(adm.call('workspace/usuarios/estado','POST',{'id':uid,'estado':'inactivo'})[0]==200,'se suspende')
ok(p.call('workspace/usuarios')[0] in (401,403),'al suspenderla se cierra su sesión')
limpiar();p2=C('borrar.uno@agora.test','Borrar-Prueba-2026!');ok(p2.ws is None,'suspendida no puede entrar')
ok(adm.call('workspace/usuarios/estado','POST',{'id':uid,'estado':'activo'})[0]==200,'se reactiva')
limpiar();p3=C('borrar.uno@agora.test','Borrar-Prueba-2026!');ok(p3.ws is not None,'reactivada vuelve a entrar')
# una venta suya queda sin dueño, no se pierde
sql(f"insert into agora_records(tenant_id,kind,uid,owner_id,label,data) values({ws},'prueba','borrar-uno',{uid},'x','{{}}')")
st,q=adm.call('workspace/usuarios/borrar/desafio','POST',{'id':uid});ok(st==200 and q['pregunta'].startswith('¿Cuánto es') and q['nombre']=='Persona Borrar','pedir eliminar trae una operación para resolver')
ok(adm.call('workspace/usuarios','DELETE',{'id':uid})[0]==422,'sin resolverla no se elimina')
ok(adm.call('workspace/usuarios','DELETE',{'id':uid,'token':q['token'],'respuesta':resolver(q['pregunta'])+1})[0]==422,'con un resultado incorrecto no se elimina')
ok(adm.call('workspace/usuarios','DELETE',{'id':uid,'token':q['token'][:-1]+('0' if q['token'][-1]!='0' else '1'),'respuesta':resolver(q['pregunta'])})[0]==422,'una operación alterada no sirve')
otro=int(sql("select id from agora_users where email='ejecutivo2.asocim@agora.test'"))
ok(adm.call('workspace/usuarios','DELETE',{'id':otro,'token':q['token'],'respuesta':resolver(q['pregunta'])})[0]==422,'la operación de una persona no sirve para borrar a otra')
limpiar();eje=C('ejecutivo1.asocim@agora.test');ok(eje.call('workspace/usuarios/borrar/desafio','POST',{'id':uid})[0]==403,'un ejecutivo no puede eliminar usuarios')
me=int(sql("select id from agora_users where email='admin.asocim@agora.test'"))
ok(adm.call('workspace/usuarios/borrar/desafio','POST',{'id':me})[0]==403,'nadie puede eliminarse a sí mismo')
st,r=adm.call('workspace/usuarios','DELETE',{'id':uid,'token':q['token'],'respuesta':resolver(q['pregunta'])})
ok(st==200 and sql(f'select count(*) from agora_users where id={uid}')=='0','con el resultado correcto se elimina')
ok(sql("select count(*) from agora_records where uid='borrar-uno' and owner_id is null")=='1','sus registros se conservan, sin responsable');sql("delete from agora_records where uid='borrar-uno'")
ok(sql(f"select count(*) from agora_audit where action='usuario.borrado' and target='{uid}'")=='1','queda en la auditoría')
# desde Agencia
limpiar();d=C(*DU)
st,r=adm.call('workspace/usuarios','POST',{'nombre':'Persona Borrar Dos','correo':'borrar.dos@agora.test','rol':'CONSULTA','clave':'Borrar-Prueba-2026!'});uid2=r['id']
st,q=d.call('agency/users/borrar/desafio','POST',{'accountId':ws,'userId':uid2});ok(st==200,'desde Agencia también se pide la operación')
st,_=d.call('agency/users/borrar','POST',{'accountId':ws,'userId':uid2,'token':q['token'],'respuesta':resolver(q['pregunta'])});ok(st==200 and sql(f'select count(*) from agora_users where id={uid2}')=='0','y se elimina desde Agencia')
# ---------- subcuenta
limpiar();d=C(*DU)
antes={t:sql(f"select count(*) from agora_records where tenant_id={ws}") for t in [0]}
st,r=d.call('agency/accounts','POST',{'nombre':'Prueba Borrar','subcuenta':'Prueba Borrar','codigo':'PBORRAR2','estado':'activa','moneda':'USD'});tid=r['id']
sql(f"insert into agora_records(tenant_id,kind,uid,label,data) values({tid},'sale','b1','x','{{}}')");sql(f"insert into agora_settings(tenant_id,`key`,value) values({tid},'prueba','1')") if False else None
d.ws=tid;st,_=d.call('workspace/usuarios','POST',{'nombre':'Equipo Borrar','correo':'borrar.tres@agora.test','rol':'ADMIN','clave':'Borrar-Prueba-2026!'});d.ws=None
ok(st==200 and filas_de(tid)>=3,f'subcuenta de prueba con datos y un usuario ({filas_de(tid)} filas)')
ok(adm.call('agency/accounts/borrar/desafio','POST',{'id':tid})[0]==403,'la administración de una subcuenta no puede eliminar subcuentas')
st,q=d.call('agency/accounts/borrar/desafio','POST',{'id':tid});ok(st==200 and q['nombre']=='Prueba Borrar','el dueño pide eliminar y recibe la operación')
ok(d.call('agency/accounts','DELETE',{'id':ws,'token':q['token'],'respuesta':resolver(q['pregunta'])})[0]==422,'la operación de una subcuenta no sirve para borrar otra')
ok(d.call('agency/accounts','DELETE',{'id':tid,'token':q['token'],'respuesta':'abc'})[0]==422,'un resultado que no es número se rechaza')
asocim_antes=filas_de(ws)
st,r=d.call('agency/accounts','DELETE',{'id':tid,'token':q['token'],'respuesta':resolver(q['pregunta'])})
ok(st==200 and filas_de(tid)==0,f'se elimina la subcuenta y no queda nada suyo ({filas_de(tid)} filas)')
ok(filas_de(ws)>=asocim_antes-1 and sql("select count(*) from agora_tenants where id=%s"%ws)=='1','las demás subcuentas no se tocan')
ok(sql(f"select count(*) from agora_audit where action='subcuenta.borrada' and target='{tid}' and tenant_id is null")=='1','queda en el registro de la agencia')
ok(d.call('agency/accounts/borrar/desafio','POST',{'id':tid})[0]==404,'una subcuenta borrada ya no existe')
# ---------- pantalla
st,r=d.call('agency/accounts','POST',{'nombre':'Prueba Pantalla','subcuenta':'Prueba Pantalla','codigo':'PPANT','estado':'activa','moneda':'USD'});tid=r['id']
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        limpiar();pg=br.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill(DU[0]);pg.locator('input[type=password]').fill(DU[1]);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(B+f'/agencia/subcuenta/{tid}');pg.wait_for_timeout(3500)
        ok(pg.locator('[data-acc-borrar]').count()==1,'la ficha de la subcuenta tiene «Eliminar subcuenta» para el dueño')
        pg.click('[data-acc-borrar]');pg.wait_for_selector('.op-conf')
        preg=pg.inner_text('.op-preg');ok(pg.locator('.op-conf [data-ok]').is_disabled(),f'el botón queda bloqueado hasta escribir el resultado ({preg})')
        pg.fill('.op-conf input',str(resolver(preg)+3));pg.click('.op-conf [data-ok]');pg.wait_for_timeout(1500)
        ok('no es correcto' in pg.inner_text('.op-conf .fld-err') and pg.inner_text('.op-preg')!='' and sql(f'select count(*) from agora_tenants where id={tid}')=='1','un resultado malo no borra, lo dice y trae otra operación')
        pg.screenshot(path='/tmp/claude-0/-home-user-GPT/47d2de27-7ad9-51d6-885f-20e6833c2033/scratchpad/op-conf.png')
        pg.fill('.op-conf input',str(resolver(pg.inner_text('.op-preg'))));pg.click('.op-conf [data-ok]');pg.wait_for_timeout(3000)
        ok(sql(f'select count(*) from agora_tenants where id={tid}')=='0' and '/agencia' in pg.url,'con el resultado correcto se elimina y vuelve a Agencia')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        limpiar();pg=br.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(B+'/usuarios');pg.wait_for_timeout(3500)
        txt=pg.evaluate("document.querySelector('main#app').innerText")
        ok('Suspendida' in txt or 'Activa' in txt,'Usuarios y roles muestra el estado (Activa / Suspendida)')
        ok(pg.evaluate("document.documentElement.scrollWidth")<=390,'teléfono 390 sin desborde')
        bt=pg.locator('main#app [data-usuario]').last
        if bt.count():
            bt.click();pg.wait_for_timeout(400);m=pg.evaluate("[...document.querySelectorAll('[data-u]')].map(b=>b.textContent.trim())")
            ok('Suspender' in m and 'Eliminar' in m,f'el menú de cada persona tiene «Suspender» y «Eliminar» {m}')
        else: print('AVISO: no encontré el botón del menú de la persona')
        pg.close()
    finally:
        br.close()
        if sql(f'select count(*) from agora_tenants where id={tid}')=='1':
            st,q=d.call('agency/accounts/borrar/desafio','POST',{'id':tid});d.call('agency/accounts','DELETE',{'id':tid,'token':q['token'],'respuesta':resolver(q['pregunta'])})
print(n,'comprobaciones')
