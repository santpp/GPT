#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.28.0 · Widgets, etapa 2 (aprobadas por el usuario las fases 7, 9, 10 y 11):
 7. Filtros por widget (vendedor, embudo, etapa, producto, forma de cobro, moneda, campos personalizados…).
 9. Más fuentes: oportunidades por etapa y embudo, conversaciones, tareas por responsable, campos personalizados y plantillas de WhatsApp.
10. Tipos nuevos: ranking, embudo de conversión, meta con barra, mapa de calor y tarjeta con lista.
11. Constructor libre: «Widget de datos» (qué contar, cómo, agrupar y filtrar), sin fórmulas.
Las cifras coinciden con las de cada módulo; quien solo ve lo suyo solo cuenta lo suyo; nunca se suman monedas distintas.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/widgets-etapa2.py"""
import os,json,subprocess,re as _re
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
SHOT=os.environ.get('SHOT','/tmp');MES='2026-11'
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','-N','agora_erp','-e',q],capture_output=True,text=True,check=True).stdout
def Q(c,**k):
    st,r=c.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':{'op':'contar','rango':'periodo','filtros':[],**k},'periodo':MES}]})
    return r.get('resultados',{}).get('x',r) if st==200 else {'st':st,**r}
def W(i,tipo,q,**k):return {'id':i,'tipo':tipo,'fuente':'consulta','ref':{'op':'contar','rango':'periodo','filtros':[],**q},'titulo':k.pop('t',''),'tamano':k.pop('tam','ancho'),'periodo':k.pop('per',MES),**k}
adm=limpiar_datos()
try:
    # ---------- catálogo (fase 9: fuentes) ----------
    st,c=adm.call('workspace/consultas/catalogo');objs={o['id']:o for o in c['objetos']}
    ok(st==200 and list(objs)==['ventas','cobros','oportunidades','contactos','tareas','conversaciones','plantillas'],'se puede contar ventas, cuotas de cobro, oportunidades, contactos, tareas, conversaciones y plantillas de WhatsApp')
    ok([m['id'] for m in objs['ventas']['medidas']]==['monto'] and {'vendedor','producto','cobro','moneda'}<={x['id'] for x in objs['ventas']['campos']} and {'estado','vendedor'}<={x['id'] for x in objs['cobros']['campos']},'ventas y cuotas se suman por monto y se filtran por vendedor, producto, forma de cobro, moneda y estado')
    ok({'embudo','etapa','responsable'}<={x['id'] for x in objs['oportunidades']['campos']} and {'dia_semana','hora','mes'}<={x['id'] for x in objs['conversaciones']['campos']},'oportunidades por embudo y etapa; todo por día, mes, día de la semana y hora')
    ok([o['id'] for o in c['operaciones']]==['contar','sumar','promedio','minimo','maximo'],'operaciones: contar, sumar, promedio, mínimo y máximo (fase 11)')
    # ---------- las cifras coinciden con los módulos ----------
    v=adm.call(f'workspace/ventas?mes={MES}')[1];items=v['items']
    r=Q(adm,objeto='ventas');ok(r['valor']==len(items),f'«Contar ventas» del mes = las ventas del módulo ({r["valor"]} = {len(items)})')
    r=Q(adm,objeto='ventas',op='sumar',medida='monto');ok(abs(r['valor']-v['resumen']['vendido'].get('USD',0))<0.01 and r['moneda']=='USD',f'«Sumar monto» = «Vendido» del módulo ({r["valor"]} = {v["resumen"]["vendido"].get("USD")})')
    r=Q(adm,objeto='cobros',op='sumar',medida='monto',filtros=[{'campo':'estado','valores':['pagado']}]);ok(abs((r['valor'] or 0)-v['resumen']['cobrado'].get('USD',0))<0.01,f'«Cuotas pagadas» del mes = «Cobrado» del módulo ({r["valor"]})')
    r=Q(adm,objeto='cobros',op='sumar',medida='monto',filtros=[{'campo':'estado','valores':['pagado'],'excluir':True}]);ok(abs((r['valor'] or 0)-v['resumen']['porCobrar'].get('USD',0))<0.01,f'«Cuotas sin pagar» del mes = «Por cobrar» del módulo ({r["valor"]})')
    vend=next(x['vendedor'] for x in items if x.get('vendedor'))
    r=Q(adm,objeto='ventas',op='sumar',medida='monto',filtros=[{'campo':'vendedor','valores':[vend]}])
    ok(abs(r['valor']-sum(x['monto'] for x in items if x.get('vendedor')==vend))<0.01,f'fase 7: filtrar por vendedor da lo mismo que el módulo filtrado igual ({vend}: {r["valor"]})')
    r2=Q(adm,objeto='ventas',op='sumar',medida='monto',filtros=[{'campo':'vendedor','valores':[vend],'excluir':True}])
    ok(abs(r['valor']+r2['valor']-v['resumen']['vendido']['USD'])<0.01,'«no es» deja fuera ese vendedor (las dos partes suman el total)')
    r=Q(adm,objeto='ventas',op='sumar',medida='monto',agrupar='vendedor');ok(abs(sum(g['valor'] for g in r['grupos'])-r['valor'])<0.01 and r['grupos'][0]['valor']>=r['grupos'][-1]['valor'],'agrupado por vendedor: las partes suman el total, de mayor a menor')
    ops=adm.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':{'objeto':'oportunidades','op':'contar','rango':'todo','agrupar':'estado'},'periodo':MES}]})[1]['resultados']['x']
    n_op=int(sql("select count(*) from agora_cuenta_oportunidades where tenant_id=2").strip())
    ok(ops['valor']==n_op and {g['etiqueta'] for g in ops['grupos']}<={'Abierta','Ganada','Perdida','Abandonada','Sin dato'},f'oportunidades por estado, con nombres en español ({ops["valor"]})')
    st,t=adm.call('workspace/tareas');hechas=t['resumen']['hechas']
    r=adm.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':{'objeto':'tareas','op':'contar','rango':'todo','filtros':[{'campo':'estado','valores':['hecha']}]},'periodo':MES}]})[1]['resultados']['x']
    ok(r['valor']==hechas,f'tareas hechas = las del módulo Tareas ({r["valor"]} / {hechas})')
    # ---------- campos personalizados (fase 9) ----------
    sql("update agora_cuenta_contactos set datos=json_set(datos,'$.customFields',json_array(json_object('id','cf-carrera','value','Inglés'))) where tenant_id=2 and ghl_id in ('k0','k1')")
    st,c=adm.call('workspace/consultas/catalogo');cc=[x for x in next(o for o in c['objetos'] if o['id']=='contactos')['campos'] if x.get('personalizado')]
    ok([x['id'] for x in cc]==['cf:cf-carrera'],'los campos personalizados de los contactos aparecen para agrupar y filtrar')
    r=adm.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':{'objeto':'contactos','op':'contar','rango':'todo','filtros':[{'campo':'cf:cf-carrera','valores':['Inglés']}]},'periodo':MES}]})[1]['resultados']['x']
    ok(r['valor']==2,'y se filtra por su valor')
    # ---------- monedas ----------
    uid=sql(f"select uid from agora_records where tenant_id=2 and kind='venta' and json_unquote(json_extract(data,'$.fecha')) like '{MES}%' limit 1").strip()
    sql(f"insert into agora_records(tenant_id,kind,uid,owner_id,label,status,period,data) select tenant_id,kind,'prueba-ves',owner_id,label,status,period,json_set(data,'$.moneda','VES','$.monto',1000) from agora_records where tenant_id=2 and kind='venta' and uid='{uid}'")
    try:
        r=Q(adm,objeto='ventas',op='sumar',medida='monto');ok(r['valor'] is None and 'varias monedas' in r['motivo'],'nunca suma bolívares con dólares: pide filtrar o agrupar por moneda')
        r=Q(adm,objeto='ventas',op='sumar',medida='monto',agrupar='moneda');ok({g['clave'] for g in r['grupos']}=={'USD','VES'},'agrupado por moneda, cada una por separado')
        r=Q(adm,objeto='ventas',op='sumar',medida='monto',filtros=[{'campo':'moneda','valores':['VES']}]);ok(r['valor']==1000 and r['moneda']=='VES','filtrado por una moneda, suma en esa moneda')
    finally:sql("delete from agora_records where tenant_id=2 and uid='prueba-ves'")
    # ---------- cada rol ve solo lo suyo ----------
    limpiar();eje=C('ejecutivo1.asocim@agora.test');mias=eje.call(f'workspace/ventas?mes={MES}')[1]['items']
    r=Q(eje,objeto='ventas');ok(r['valor']==len(mias) and len(mias)<len(items),f'un ejecutivo solo cuenta sus ventas ({r["valor"]} de {len(items)})')
    mio=sql("select external_user_id from agora_user_links l join agora_users u on u.id=l.user_id where u.email='ejecutivo1.asocim@agora.test' limit 1").strip() or 'u-paola'
    r=eje.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':{'objeto':'oportunidades','op':'contar','rango':'todo'},'periodo':MES}]})[1]['resultados']['x']
    ok(r['valor']<n_op,f'y solo sus oportunidades ({r["valor"]} de {n_op})')
    ok(eje.call('workspace/tableros','PUT',{'modulo':'ventas','version':-1,'widgets':[W('a1b2c3d4','cifra',{'objeto':'ventas'})]})[0]==403,'un ejecutivo no crea widgets (solo administración)')
    # ---------- validaciones del servidor ----------
    def put(ws):return adm.call('workspace/tableros','PUT',{'modulo':'ventas','widgets':ws})
    ok(put([{'id':'x1y2z3w4','tipo':'ranking','fuente':'metrica','ref':'mes.vendido'}])[0]==422,'un ranking necesita un widget de datos')
    ok(put([W('x1y2z3w4','meta',{'objeto':'ventas'})])[0]==422,'la meta sin número no se guarda')
    ok(put([W('x1y2z3w4','cifra',{'objeto':'facturas'})])[0]==422,'no se puede contar algo que no existe')
    ok(put([W('x1y2z3w4','cifra',{'objeto':'ventas','filtros':[{'campo':'clave_secreta','valores':['x']}]})])[0]==422,'ni filtrar por un campo que no existe')
    ok(put([W('x1y2z3w4','embudo',{'objeto':'ventas'})])[0]==422,'el embudo cuenta oportunidades')
    # ---------- un tablero con todos los tipos nuevos ----------
    ws=[W('cifq0001','cifra',{'objeto':'ventas','op':'sumar','medida':'monto','filtros':[{'campo':'vendedor','valores':[vend]}]},t='Vendido por '+vend,tam='chico',comparar='mes_anterior'),
        W('rank0001','ranking',{'objeto':'ventas','op':'sumar','medida':'monto','agrupar':'vendedor'},t='Ranking de vendedores',top=5),
        W('embu0001','embudo',{'objeto':'oportunidades','rango':'todo','filtros':[{'campo':'embudo','valores':['p1']}]},t='Embudo de ventas'),
        {**W('meta0001','meta',{'objeto':'ventas','op':'sumar','medida':'monto'},t='Meta del mes'),'meta':10000},
        W('calo0001','calor',{'objeto':'conversaciones','rango':'todo'},t='Cuándo escriben'),
        W('list0001','lista',{'objeto':'ventas','op':'sumar','medida':'monto'},t='Últimas ventas',top=5),
        W('dona0001','dona',{'objeto':'ventas','agrupar':'producto'},t='Ventas por producto')]
    st,r=put(ws);ok(st==200 and [w['tipo'] for w in r['widgets']]==['cifra','ranking','embudo','meta','calor','lista','dona'],f'se guardan los tipos nuevos ({st} {r.get("error","")})')
    cal=next(w for w in r['widgets'] if w['tipo']=='calor');ok(cal['ref']['agrupar']=='dia_semana' and cal['ref']['agrupar2']=='hora','el mapa de calor cruza día de la semana y hora')
    emb=next(w for w in r['widgets'] if w['tipo']=='embudo')
    e=adm.call('workspace/consultas','POST',{'pedidos':[{'clave':'x','consulta':emb['ref'],'periodo':MES}]})[1]['resultados']['x']
    ok([g['etiqueta'] for g in e['grupos']][:3]==['Lead nuevo','Propuesta enviada','Negociación'],f'el embudo sigue el orden de las etapas ({[g["etiqueta"] for g in e["grupos"]]})')
    # ---------- pantallas ----------
    with sync_playwright() as pw:
        br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
        try:
            for w_,h_ in ((1280,900),(390,844)):
                pg=entrar(br,'admin.asocim@agora.test',w_,h_);pg.goto(B+'/ventas');pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
                pg.wait_for_selector('[data-wg-id="rank0001"] .wg-barrash.is-rank',timeout=40000);pg.wait_for_selector('[data-wg-id="list0001"] .wg-lista2',timeout=20000)
                txt=pg.inner_text('[data-wg-id="rank0001"]');ok(vend.split()[0] in txt and pg.locator('[data-wg-id="rank0001"] .wg-pos').count()>=2,f'{w_}px: el ranking muestra a los vendedores con su puesto')
                ok(pg.locator('[data-wg-id="embu0001"] .wg-embudo li').count()>=3 and 'de la etapa anterior' in pg.inner_text('[data-wg-id="embu0001"]'),f'{w_}px: el embudo muestra cada etapa y su paso a la siguiente')
                ok('de US$ 10.000,00' in pg.inner_text('[data-wg-id="meta0001"]') and pg.locator('[data-wg-id="meta0001"] [role=progressbar]').count()==1,f'{w_}px: la meta muestra su barra de avance')
                ok(pg.locator('[data-wg-id="calo0001"] table.wg-calor td').count()>=1,f'{w_}px: el mapa de calor se dibuja')
                ok(pg.locator('[data-wg-id="list0001"] .wg-lista2 li').count()==min(5,len(items)),f'{w_}px: la tarjeta con lista trae las más recientes (hasta 5)')
                ok(pg.locator('[data-wg-id="dona0001"] .wg-dona').count()==1,f'{w_}px: la dona reparte por producto')
                pg.wait_for_selector('[data-wg-id="cifq0001"]',timeout=15000);pg.wait_for_timeout(1200)
                ok('US$' in pg.inner_text('[data-wg-id="cifq0001"]'),f'{w_}px: la cifra filtrada está con las tarjetas del módulo')
                ok(pg.evaluate('document.documentElement.scrollWidth')<=w_ and not pg.errs,f'{w_}px: sin desborde ni errores {pg.errs[:2]}')
                pg.screenshot(path=f'{SHOT}/etapa2-{w_}.png',full_page=True)
                if w_==1280:
                    pg.click('[data-wg-id="rank0001"] [data-wg-det]');pg.wait_for_selector('.wg-detalle h3',timeout=10000)
                    t=pg.inner_text('.wg-detalle');ok('Qué cuenta' in t and 'Por vendedor' in t and 'Registros' in t,'«Ver detalle» explica qué cuenta, el reparto y los registros')
                    with pg.expect_download() as d:pg.click('.wg-detalle [data-wg-csv]')
                    ok(d.value.suggested_filename.endswith('.csv'),'y se descarga')
                    pg.keyboard.press('Escape');pg.wait_for_timeout(400)
                    # constructor libre en el editor
                    abrir_ed(pg);pg.click('[data-wg-datos]');pg.wait_for_selector('.wg-q')
                    pg.select_option('.wg-item.is-open [data-k=tipo]','ranking');pg.wait_for_timeout(300)
                    pg.select_option('.wg-q [data-q=op]','sumar');pg.wait_for_timeout(300)
                    ok(pg.locator('.wg-q [data-q=medida]').count()==1 and pg.locator('.wg-q [data-q=agrupar]').count()==1,'el constructor pide de qué sumar y por qué agrupar')
                    pg.click('.wg-q [data-f-nuevo]');pg.wait_for_selector('.wg-flt [data-fv]',timeout=10000)
                    pg.locator('.wg-flt [data-fv]').first.check()
                    pg.fill('.wg-item.is-open [data-k=titulo]','Prueba UI ranking')
                    pg.click('[data-wg-guardar]');pg.wait_for_timeout(2500)
                    t=adm.call('workspace/tableros?modulo=ventas')[1]['widgets'];nuevo=next((x for x in t if x['titulo']=='Prueba UI ranking'),None)
                    ok(nuevo and nuevo['tipo']=='ranking' and nuevo['ref']['op']=='sumar' and len(nuevo['ref']['filtros'])==1,f'«Widget de datos»: se arma sin fórmulas y se guarda con su filtro ({nuevo and nuevo["ref"]})')
                    ok(not _re.search(r'GoHighLevel|HighLevel|LeadConnector|GHL',pg.inner_text('body')),'sin nombres del proveedor')
                pg.close()
        finally:br.close()
finally:
    sql("update agora_cuenta_contactos set datos=json_remove(datos,'$.customFields') where tenant_id=2 and ghl_id in ('k0','k1')")
    limpiar();adm.call('workspace/tableros?modulo=ventas','DELETE',{'modulo':'ventas'})
print(n,'comprobaciones')
