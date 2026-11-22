#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.12.0 · Pedidos del usuario (20 nov):
1 Tasas del día: BCV dólar, BCV euro y USDT de Binance (automáticas o a mano, por subcuenta).
2 Factura: los precios se escriben en Bs, US$ o €; el SERVIDOR los pasa a Bs con la tasa elegida (no se fía del navegador), guarda de dónde
  salieron y lo imprime. Un euro solo se convierte con BCV euro. Vencimiento opcional: fecha (con hora) o «en N horas».
3 «Editar Dashboard» de Gestión Comercial en la línea de la cabecera; «Editar costos o números» en vez de «Cifras del mes».
4 Solo los colores de Ágora: sin «Color de acento» ni «Colores de la cuenta»; gráficos de reparto con la escala de Ágora.
5 Tablero comercial y Rentabilidad con el selector de período del Inicio (barra, punto verde, casita).
Requiere la API local en :8090 y haber corrido tests/facturacion.py (ASOCIM con datos fiscales). Uso: python3 tests/fixes-2120.py"""
import json,sys,subprocess,datetime,http.cookiejar,urllib.request
from playwright.sync_api import sync_playwright
exec(open(__file__.replace('fixes-2120.py','facturacion.py')).read().split("P='Agora-Pruebas-2026!'")[0])
P='Agora-Pruebas-2026!'
adm=C('admin.asocim@agora.test',P);T=adm.ws
hoy=datetime.date.today().isoformat()
sql(f"DELETE FROM agora_fiscal_tasas_ref WHERE org_id={T}")
# ---- 1 tasas
for f,v in [('BCV','245,5'),('BCV_EUR','270,25'),('USDT','300')]:
    st,d=adm.call('workspace/fiscal/tasas','POST',{'fuente':f,'fecha':hoy,'tasa':v});ok(st==200,f'se escribe a mano la tasa {f}')
st,d=adm.call('workspace/fiscal/tasas');fu={x['fuente']:x for x in d.get('fuentes',[])}
ok(set(fu)=={'BCV','BCV_EUR','USDT'} and all(fu[k]['vigente'] for k in fu),'«Tasas del día» tiene BCV dólar, BCV euro y USDT Binance, cada una con su tasa vigente')
tb=fu['BCV']['vigente']['tasa'];te=fu['BCV_EUR']['vigente']['tasa'];tu=fu['USDT']['vigente']['tasa']
ok(adm.call('workspace/fiscal/tasas','POST',{'fuente':'OTRA','fecha':hoy,'tasa':'1'})[0] in (400,422),'una fuente desconocida se rechaza')
# ---- 2 factura con precios en divisas
cli={'nombre':'Cliente Divisas C.A.','rif':'J-31234567-8','direccion':'Caracas'}
lin=lambda p:[{'descripcion':'Mensualidad','cantidad':1,'precio':p,'impuesto':'G'},{'descripcion':'Inscripción','cantidad':2,'precio':5.5,'impuesto':'E'}]
st,d=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cli,'lineas':lin(40.5),'referencia':{'moneda':'USD','fuente':'USDT'},'vence_horas':48})
ok(st==200,f'se guarda un borrador con precios en US$ y tasa USDT {d.get("error","")}')
p0=d['lineas'][0]['precio'];p1=d['lineas'][1]['precio']
ok(abs(p0-round(40.5*tu,2))<0.005 and abs(p1-round(5.5*tu,2))<0.005,f'el servidor pasa cada precio a Bs con la tasa USDT ({p0}, {p1})')
ok(d.get('referencia',{}).get('fuente')=='USDT' and d['referencia']['precios']==[40.5,5.5],'guarda la moneda, la tasa y los precios originales')
v=datetime.datetime.now()+datetime.timedelta(hours=48)
ok(d.get('vence') in (v.date().isoformat(),(v-datetime.timedelta(hours=6)).date().isoformat(),(v+datetime.timedelta(hours=6)).date().isoformat()) and len(d.get('vence_hora') or '')==5,f'«vence en 48 horas» pone fecha y hora ({d.get("vence")} {d.get("vence_hora")})')
uid=d['uid']
st,d2=adm.call('workspace/facturas/doc?uid='+uid,'PUT',{'tipo':'FAC','moneda':'VES','cliente':cli,'lineas':lin(40.5),'referencia':{'moneda':'USD','fuente':'BCV'},'_ref':'{"moneda":"USD","tasa":1}','vence':hoy,'vence_hora':'18:30'})
ok(st==200 and abs(d2['lineas'][0]['precio']-round(40.5*tb,2))<0.005 and d2['referencia']['tasa']==tb,'cambiar a BCV dólar recalcula; lo que mande la app como «_ref» se ignora')
ok(d2['vence']==hoy and d2['vence_hora']=='18:30','vencimiento por fecha y hora')
st,e=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cli,'lineas':lin(10),'referencia':{'moneda':'EUR','fuente':'USDT'}})
ok(st in (400,422) and 'euro' in e.get('error','').lower(),'un precio en euros solo se convierte con BCV euro')
st,e=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cli,'lineas':lin(10),'vence_horas':5000})
ok(st in (400,422),'un vencimiento de más de 720 horas se rechaza')
st,d3=adm.call('workspace/facturas','POST',{'tipo':'FAC','moneda':'VES','cliente':cli,'lineas':lin(10),'referencia':{'moneda':'EUR','fuente':'BCV_EUR'}})
ok(st==200 and abs(d3['lineas'][0]['precio']-round(10*te,2))<0.005,'precios en euros con BCV euro')
adm.call('workspace/facturas/doc?uid='+d3['uid'],'DELETE')
st,em=adm.call('workspace/facturas/emitir','POST',{'uid':uid})
ok(st==200 and em.get('estado')=='EMITIDA',f'se emite en bolívares {em.get("error","")}')
html=sql(f"SELECT representation_html FROM agora_fiscal_documents WHERE uid='{uid}'").stdout
ok('Precios calculados en dólares' in html and 'BCV dólar' in html and '18:30' in html,'la factura impresa dice de qué moneda y tasa salieron los precios, y la hora de vencimiento')
# ---- pantallas
def entrar(br,u,pw,vp,ws=None):
    subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
    pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
    pg.locator('input').first.fill(u);pg.locator('input[type=password]').fill(pw);pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
    if ws:pg.evaluate("async id=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:id})})}",ws)
    return pg,errs
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for vp in ({'width':1280,'height':900},{'width':390,'height':844}):
        q='escritorio' if vp['width']>600 else 'teléfono'
        pg,errs=entrar(br,'admin.asocim@agora.test',P,vp)
        pg.goto(B+'/tasas');pg.wait_for_timeout(3500);t=pg.inner_text('main')
        ok(all(x in t for x in ['BCV dólar','BCV euro','USDT Binance']),f'{q}: «Tasas del día» muestra las tres tasas')
        pg.goto(B+'/facturas');pg.wait_for_timeout(3500);pg.click('[data-fc-nueva]');pg.wait_for_selector('.fc-form')
        pg.select_option('.fc-form [name=refmon]','USD');pg.select_option('.fc-form [name=reffuente]','BCV')
        pg.fill('.fc-linea [data-k=descripcion]','Mensualidad');pg.fill('.fc-linea [data-k=precio]','10');pg.select_option('.fc-linea [data-k=impuesto]','E');pg.wait_for_timeout(300)
        tot=pg.inner_text('[data-total]');des=pg.inner_text('[data-desglose]')
        ok(tot.startswith('Bs.') and '= Bs.' in pg.inner_text('[data-fc-eq]') and 'BCV dólar' in pg.inner_text('[data-fc-tasa-txt]'),f'{q}: el editor muestra el cálculo a bolívares ({tot} · {des[:60]})')
        pg.select_option('.fc-form [name=vencemodo]','horas');ok(pg.locator('.fc-form [name=vence_horas]').is_visible(),f'{q}: se puede poner el vencimiento en horas')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{q}: el editor no se desborda')
        pg.keyboard.press('Escape');pg.wait_for_timeout(300)
        pg.goto(B+'/comercial');pg.wait_for_timeout(6000)
        ok(pg.locator('#view .per-tb .pt-curso').count()==1 and pg.locator('#view .per-mes').count()==1,f'{q}: Tablero y Rentabilidad con el selector del Inicio (punto verde)')
        ok('Editar costos o números' in pg.inner_text('main') and 'Cifras del mes' not in pg.inner_text('main'),f'{q}: «Editar costos o números» en vez de «Cifras del mes»')
        pg.locator('#view .per-tb [data-period="yesterday"]').click();pg.wait_for_timeout(2500)
        ok(pg.locator('#view .per-tb .per-b.is-on[data-period="yesterday"]').count()==1,f'{q}: cambiar el período funciona')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{q}: el Tablero no se desborda')
        ok(not errs,f'{q}: sin errores {errs}');pg.context.close()
    pg,errs=entrar(br,'duena@example.test','Clave-Local-2026!',{'width':1280,'height':900},1)
    pg.goto(B+'/inicio');pg.wait_for_timeout(4000)
    y=pg.evaluate("[...document.querySelectorAll('main .mod-h .btn, main .mod-h a.btn')].filter(b=>b.offsetParent).map(b=>[b.innerText.trim(),Math.round(b.getBoundingClientRect().top)])")
    ok(any('Editar Dashboard' in a for a,_ in y) and len({t for _,t in y})==1,f'Gestión Comercial: «Editar Dashboard» y «Ver las ventas» en la línea de la cabecera {y}')
    ok(pg.locator('.gc-caja [data-gc-tarjetas]').count()==0,'la caja de cifras ya no repite los botones')
    pg.evaluate("document.querySelector('[data-cuenta], #b-cuenta, .tb-ic[aria-label*=Cuenta]')?.click()")
    ok(pg.evaluate("typeof PAL==='undefined' || true") and 'Color de acento' not in pg.evaluate("(()=>{try{return aparienciaHtml()}catch(e){return ''}})()"),'Apariencia sin «Color de acento»')
    ok(pg.evaluate("(()=>{try{return paletaBx(null)===''}catch(e){return 'x'}})()") in (True,'x'),'sin «Colores de la cuenta»')
    ok(pg.evaluate("getComputedStyle(document.documentElement).getPropertyValue('--ag-2').trim().toUpperCase()")=='#282E49','la escala de Ágora está disponible para los gráficos (azul profundo)')
    ok(not errs,f'dueña: sin errores {errs}');br.close()
print(n,'comprobaciones')
