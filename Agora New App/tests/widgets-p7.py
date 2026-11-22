#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.18.0 · Punto 7 del plan de widgets y plantillas por rubro:
«Buscar objetivo», avisos de los widgets en Inicio, consolidado de la agencia y plantillas (incluidas y de los dueños).
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/widgets-p7.py"""
import sys,os,re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',q],capture_output=True,text=True).stdout.strip()
DU=('duena@example.test','Clave-Local-2026!')
FX=('Prueba utilidad','Prueba cuota','Margen del mes','Ticket promedio','Costo por inscrito','Cobrado sobre vendido')
def limpieza():
    for t in (1,2):
        sql(f"delete from agora_dashboards where tenant_id={t}")
        sql(f"delete from agora_widget_formulas where tenant_id={t} and nombre in ({','.join(repr(x) for x in FX)})")
        sql(f"delete from agora_widget_variables where tenant_id={t} and nombre='Prueba meta'")
    sql("delete from agora_widget_plantillas where nombre like 'Prueba %'")
limpieza()
limpiar();adm=C('admin.asocim@agora.test');ws=adm.ws
# ---------- Buscar objetivo
adm.call('workspace/variables','PUT',{'nombre':'Prueba meta','valor':1000,'unidad':'dinero'})
f1=adm.call('workspace/formulas','POST',{'nombre':'Prueba utilidad','expresion':'{Vendido del mes} - {Prueba meta}','formato':'dinero'})[1]['formula']
vendido=adm.call('workspace/metricas?periodo=2026-11&ids=mes.vendido')[1]['valores']['mes.vendido']['valor']
st,r=adm.call('workspace/formulas/objetivo','POST',{'id':f1['id'],'variable':'Prueba meta','meta':500,'periodo':'2026-11'})
ok(st==200 and abs(r['valor']-(vendido-500))<0.01 and abs(r['resultado']-500)<0.01 and r['actual']==1000,f'buscar objetivo despeja la variable: {r.get("valor")} (vendido {vendido} − x = 500)')
f2=adm.call('workspace/formulas','POST',{'nombre':'Prueba cuota','expresion':'PAGO(10%/12; {Prueba meta}; 5000)','formato':'dinero'})[1]['formula']
st,r=adm.call('workspace/formulas/objetivo','POST',{'id':f2['id'],'variable':'Prueba meta','meta':300,'periodo':'2026-11'})
ok(st==200 and 17.5<r['valor']<18.5 and abs(r['resultado']-300)<1,f'también con fórmulas no lineales (cuota de un préstamo: {r.get("valor")} meses)')
ok(adm.call('workspace/formulas/objetivo','POST',{'id':f2['id'],'variable':'Prueba meta','meta':10,'periodo':'2026-11'})[0]==422,'si ningún valor llega a la meta, lo dice')
ok(adm.call('workspace/formulas/objetivo','POST',{'id':f1['id'],'variable':'Vendido del mes','meta':10})[0]==422,'solo se despejan variables propias, no cifras del sistema')
ok(sql("select valor from agora_widget_variables where tenant_id=%s and nombre='Prueba meta' and periodo=''"%ws).startswith('1000'),'buscar objetivo no cambia nada por sí solo')
limpiar();eje=C('ejecutivo1.asocim@agora.test')
ok(eje.call('workspace/formulas/objetivo','POST',{'id':f1['id'],'variable':'Prueba meta','meta':5})[0]==403,'un ejecutivo no usa buscar objetivo')
# ---------- avisos
W=lambda i,r,**k:{'id':i,'tipo':'cifra','fuente':'metrica','ref':r,'titulo':k.get('t',''),'nota':'','tamano':'chico','periodo':'actual','comparar':'','formato':'auto','decimales':2,'semaforo':k.get('s',{'activo':False}),'alerta':k.get('a',False)}
sem={'activo':True,'verde':100,'ambar':70,'mejor':'mas'}
ok(adm.call('workspace/tableros','PUT',{'modulo':'comercial','version':0,'widgets':[W('aviso01','mes.avance',t='Avance',s=sem,a=True),W('aviso02','mes.vendido',s=sem,a=False),W('aviso03','mes.cierres',s={'activo':True,'verde':1,'ambar':0,'mejor':'mas'},a=True)]})[0]==200,'tablero con avisos')
ok(adm.call('workspace/tableros','PUT',{'modulo':'costos','version':0,'visible':'administracion','widgets':[W('aviso04','mes.avance',t='Avance privado',s=sem,a=True)]})[0]==200,'y uno solo para la administración')
st,al=adm.call('workspace/alertas?periodo=2026-11');t=[x['titulo'] for x in al['alertas']]
ok(st==200 and 'Avance' in t and 'Avance privado' in t and 'Vendido del mes' not in t and 'Cierres del mes' not in t,f'avisa solo de los widgets marcados que quedaron en Bajo o Cerca {t}')
ok(al['alertas'][0]['estado']=='bajo','los «Bajo» van primero')
ok('Avance privado' not in [x['titulo'] for x in eje.call('workspace/alertas?periodo=2026-11')[1]['alertas']],'un ejecutivo no ve los avisos de un tablero de la administración')
ok(adm.call('workspace/tableros','PUT',{'modulo':'comercial','version':1,'widgets':[W('aviso05','mes.vendido',tipo='linea',a=True)|{'tipo':'linea'}]})[1]['widgets'][0]['alerta'] is False,'un gráfico o una cifra sin semáforo no lleva aviso')
adm.call('workspace/tableros','PUT',{'modulo':'comercial','version':2,'widgets':[W('aviso01','mes.avance',t='Avance',s=sem,a=True)]})
# ---------- consolidado
limpiar();d=C(*DU)
st,c=d.call('agency/consolidado');ok(st==200 and any(x['id']=='mes.vendido' for x in c['catalogo']) and not any(x['id']=='cuenta.contactos' for x in c['catalogo']),'el consolidado ofrece solo cifras por mes')
st,c=d.call('agency/consolidado?periodo=2026-11&ids=mes.vendido,ventas.cobrado,rep.vendido')
fila=[x for x in c.get('filas',[]) if x['id']==ws]
mine=adm.call('workspace/metricas?periodo=2026-11&ids=mes.vendido,ventas.cobrado,rep.vendido')[1]['valores']
ok(st==200 and len(c['filas'])>=2 and fila and all(fila[0]['valores'][k]['valor']==mine[k]['valor'] for k in mine),f'cada subcuenta da las mismas cifras que dentro de ella ({len(c["filas"])} subcuentas)')
ok(d.call('agency/consolidado?ids=cuenta.contactos')[0]==422 and d.call('agency/consolidado?ids='+','.join(['mes.vendido','mes.cierres','mes.meta','mes.avance','mes.falta','ventas.cobrado','ventas.vencido','ventas.por_cobrar','rep.vendido']))[0]==422,'no acepta cifras de hoy ni más de 8')
ok(adm.call('agency/consolidado?ids=mes.vendido')[0]==403,'la administración de una subcuenta no ve el consolidado de la agencia')
ok(adm.call('workspace/metricas?periodo=2026-11&ids=mes.vendido')[0]==200,'después del consolidado todo sigue igual en la subcuenta')
# ---------- plantillas
st,pl=adm.call('workspace/plantillas');ids=[x['id'] for x in pl['plantillas']]
ok(st==200 and {'b:educacion','b:ventas','b:cobranza','b:atencion'}<=set(ids) and pl['puedeCrear'] is False and pl['puedeAplicar'] is True,'hay 4 plantillas incluidas; la administración las aplica pero no crea')
ok(adm.call('workspace/plantillas','POST',{'nombre':'Prueba x'})[0]==403 and eje.call('workspace/plantillas/aplicar','POST',{'id':'b:ventas'})[0]==403,'solo los dueños crean y solo la administración aplica')
st,r=adm.call('workspace/plantillas/aplicar','POST',{'id':'b:ventas','modo':'agregar'})
ok(st==200 and r['formulasCreadas']==2 and set(r['modulos'])=={'comercial'},f'aplicar «Ventas y metas» crea sus 2 fórmulas y los tableros {r}')
cen=adm.call('workspace/tableros?modulo=comercial')[1]['widgets']
ok(len(cen)==6 and cen[0]['ref']=='mes.avance','«agregar» suma a lo que ya había')
v=adm.call('workspace/metricas?periodo=2026-11&ids='+','.join('f:%s'%w['ref'] for w in cen if w['fuente']=='formula'))[1]['valores']
ok(all(x['valor'] is not None for x in v.values()),'las fórmulas de la plantilla calculan en la subcuenta')
st,r=adm.call('workspace/plantillas/aplicar','POST',{'id':'b:ventas','modo':'reemplazar'})
ok(st==200 and r['formulasCreadas']==0 and len(adm.call('workspace/tableros?modulo=comercial')[1]['widgets'])==5,'aplicarla otra vez no duplica fórmulas y «reemplazar» deja solo lo de la plantilla')
d.ws=ws;st,r=d.call('workspace/plantillas','POST',{'nombre':'Prueba academia','rubro':'Educación','descripcion':'De prueba'})
ok(st==200 and r['formulas']>=2,f'un dueño guarda los widgets de la subcuenta como plantilla {r}')
pid=r['id']
d.ws=1;st,r=d.call('workspace/plantillas/aplicar','POST',{'id':pid,'modo':'reemplazar'})
ok(st==200 and set(r['modulos'])>={'comercial'} and len(d.call('workspace/tableros?modulo=comercial')[1]['widgets'])==5,'y se aplica en otra subcuenta (Prueba SA) con sus fórmulas')
ok(d.call('workspace/plantillas','DELETE',{'id':'b:ventas'})[0]==403,'las incluidas en Ágora no se borran')
# ---------- pantalla
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test');pg.goto(B+'/centro');pg.wait_for_timeout(6000)
        av=pg.locator('section.wg-avisos')
        ok(av.count()==1 and not av.is_hidden() and 'Avance' in av.inner_text() and av.locator('a[href]').count()>=1,'Inicio muestra «Avisos de los widgets» con enlace al módulo')
        pg.goto(B+'/ventas');pg.wait_for_timeout(5000);abrir_ed(pg);pg.wait_for_selector('[data-wg-plantillas]');pg.click('[data-wg-plantillas]');pg.wait_for_selector('.wg-pls')
        ok(pg.locator('.wg-pl').count()>=4 and pg.locator('.wg-pl-nueva').count()==0,'el editor abre las plantillas (sin «guardar como plantilla» para la administración)')
        pg.click('.modal [data-volver]');pg.wait_for_selector('[data-wg-formulas]');pg.click('[data-wg-formulas]');pg.wait_for_selector('[data-fx-obj]')
        pg.locator('.wg-g-item',has_text='Prueba utilidad').locator('[data-fx-obj]').click();pg.wait_for_selector('.wg-obj')
        pg.fill('.wg-obj input[name=meta]','600');pg.select_option('.wg-obj select[name=variable]','Prueba meta');pg.click('.wg-obj [type=submit]');pg.wait_for_timeout(1500)
        txt=pg.inner_text('.wg-obj .wg-prev')
        ok('tiene que ser' in txt and 'Prueba meta' in txt,f'«Buscar objetivo» en pantalla: {txt[:90]}')
        pg.click('.wg-obj [data-guardar]');pg.wait_for_timeout(1500)
        ok(sql("select count(*) from agora_widget_variables where tenant_id=%s and nombre='Prueba meta' and periodo<>''"%ws)=='1','«Guardar ese valor» lo guarda solo para ese mes')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        pg=entrar(br,'admin.asocim@agora.test',390,844);pg.goto(B+'/centro');pg.wait_for_timeout(6000)
        ok(pg.evaluate('document.documentElement.scrollWidth')<=390 and pg.locator('section.wg-avisos .wg-av').count()>=1,'teléfono 390: los avisos caben sin desborde')
        pg.close()
        limpiar();pg=br.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page();pg.errs=[];pg.on('pageerror',lambda e:pg.errs.append(str(e)))
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill(DU[0]);pg.locator('input[type=password]').fill(DU[1]);pg.keyboard.press('Enter');pg.wait_for_timeout(3500)
        pg.goto(B+'/agencia/consolidado');pg.wait_for_timeout(6000)
        filas=pg.locator('.wg-con-tabla tbody tr').count()
        ok(filas>=2 and pg.locator('.wg-con-tabla tfoot tr').count()>=1 and pg.locator('.wg-con-g li').count()>=1,f'Agencia → Consolidado: una fila por subcuenta, total y gráfico al final ({filas} filas)')
        with pg.expect_download() as dl:pg.click('[data-con-csv]')
        ok(dl.value.suggested_filename.startswith('Consolidado'),'el consolidado se descarga para Excel')
        pg.screenshot(path='/tmp/claude-0/-home-user-GPT/47d2de27-7ad9-51d6-885f-20e6833c2033/scratchpad/consol.png')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
    finally:
        br.close();limpieza()
print(n,'comprobaciones')
