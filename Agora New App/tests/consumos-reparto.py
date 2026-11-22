#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.11.8 · Consumos: saber de qué subcuenta es cada gasto.
1 Un cargo que trae el identificador de la subcuenta en cualquier dato (no solo en el campo de siempre) se suma a esa subcuenta.
2 Los cargos sin subcuenta muestran «pistas»: los datos que se repiten (como el número de WhatsApp) y cuánto suma cada valor;
  la referencia distinta de cada mensaje no sirve para agrupar y no se ofrece.
3 Una regla «este número → esta subcuenta» pasa esos cargos a la subcuenta (en el total y al elegir solo esa subcuenta); quitarla los devuelve.
4 Los tipos se leen en palabras («WhatsApp · marketing») y sin la referencia técnica. Solo la dueña puede crear reglas.
5 Pantalla: el bloque «¿De qué subcuenta es…?» asigna con un toque, en el teléfono sin desborde.
Requiere la API local en :8090, la cuenta simulada y haber corrido tests/motor-puente.py. Uso: python3 tests/consumos-reparto.py"""
import sys,json,subprocess
sys.argv=[sys.argv[0],'duena@example.test','Clave-Local-2026!']
from playwright.sync_api import sync_playwright
exec(open(__file__.replace('consumos-reparto.py','motor-puente.py')).read().split('o=C(sys.argv[1],sys.argv[2])')[0])
o=C('duena@example.test','Clave-Local-2026!')
LOC='LOCasocim000000001'
for r in o.call('admin/consumption/reglas')[1].get('reglas',[]):o.call('admin/consumption/reglas','DELETE',{'campo':r['campo'],'valor':r['valor']})
st,d=o.call('admin/consumption/report?month=2026-11');ok(st==200,'Consumos responde')
aso=[l for l in d['locations'] if l['locationId']==LOC][0];ag=[l for l in d['locations'] if l['locationId']=='agencia'][0]
ok(any(c['tipo']=='Conversation ai' for c in aso['categorias']),'el cargo con el identificador de la subcuenta en otro dato se suma a la subcuenta')
ok(ag['locationName']=='Agencia · sin subcuenta identificada' and abs(ag['gasto']-30*0.074)<1e-6,'los 30 cargos de WhatsApp sin subcuenta quedan en la agencia (US$ 2,22)')
ok(all('wamid' not in (m['descripcion'] or '') and 'ref:' not in (m['descripcion'] or '') for m in ag['ultimos']) and any(c['tipo']=='WhatsApp · marketing' for c in ag['categorias']),'el tipo se lee «WhatsApp · marketing», sin la referencia técnica')
TOT=d['totales']['gasto'];p=d['pistas'];campos={c['campo']:c for c in p['campos']}
ok(p['movimientos']==30 and 'meta.phoneNumber' in campos and 'ref' not in campos and 'ref' in p['unicos'],'las pistas ofrecen el número de WhatsApp (2 valores) y no la referencia única')
ok(len(campos['meta.phoneNumber']['valores'])==2,'el número reparte los cargos en dos grupos')
st,_=o.call('admin/consumption/reglas','POST',{'campo':'meta.phoneNumber','valor':'+584140000001','locationId':'no-existe'});ok(st==422,'no se puede asignar a una subcuenta que no está conectada')
st,_=o.call('admin/consumption/reglas','POST',{'campo':'','valor':'x','locationId':LOC});ok(st==422,'el servidor valida la regla')
st,d2=o.call('admin/consumption/reglas','POST',{'campo':'meta.phoneNumber','valor':'+584140000001','locationId':LOC});ok(st==200 and len(d2['reglas'])==1,'la dueña guarda una regla')
st,d=o.call('admin/consumption/report?month=2026-11')
aso2=[l for l in d['locations'] if l['locationId']==LOC][0];ag2=[l for l in d['locations'] if l['locationId']=='agencia'][0]
ok(abs((aso2['gasto']-aso['gasto'])-20*0.074)<1e-6 and abs(ag2['gasto']-10*0.074)<1e-6 and d['porRegla']==20,'por la regla, 20 cargos (US$ 1,48) pasan a ASOCIM y 10 quedan en la agencia')
ok(abs(d['totales']['gasto']-sum(l['gasto'] for l in d['locations']))<1e-6 and abs(d['totales']['gasto']-TOT)<1e-6,'el total del mes no cambia: solo se reparte')
st,d=o.call(f'admin/consumption/report?month=2026-11&locationId={LOC}')
ok(abs(d['locations'][0]['gasto']-aso2['gasto'])<1e-6,'al elegir solo ASOCIM, también suma lo de la regla')
a=C('admin.asocim@agora.test','Agora-Pruebas-2026!')
ok(a.call('admin/consumption/reglas','POST',{'campo':'meta.phoneNumber','valor':'+584240000002','locationId':LOC})[0]==403,'una administradora de subcuenta no puede crear reglas')
o.call('admin/consumption/reglas','DELETE',{'campo':'meta.phoneNumber','valor':'+584140000001'})
st,d=o.call('admin/consumption/report?month=2026-11');ag3=[l for l in d['locations'] if l['locationId']=='agencia'][0]
ok(abs(ag3['gasto']-30*0.074)<1e-6,'quitar la regla devuelve los cargos a la agencia')
# ---- pantalla
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for vp in ({'width':1280,'height':900},{'width':390,'height':844}):
        subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
        pg=br.new_context(viewport=vp,service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
        pg.goto('http://127.0.0.1:8090/entrar');pg.wait_for_timeout(1200)
        pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
        pg.goto('http://127.0.0.1:8090/agencia/consumos');pg.wait_for_timeout(3000)
        pg.select_option('#cons-month','2026-11')
        pg.click('#cons-run');pg.wait_for_selector('.cons-atrib',timeout=15000)
        t=pg.inner_text('.cons-atrib');quien='escritorio' if vp['width']>600 else 'teléfono'
        ok('¿De qué subcuenta es el gasto sin identificar?' in t and '+584140000001' in t and 'wamid' not in pg.inner_text('#cons-out'),f'{quien}: el bloque muestra los números de WhatsApp para asignar, sin referencias técnicas')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{quien}: sin desborde')
        if vp['width']>600:
            fila=pg.locator('.cons-atrib tr',has_text='+584240000002');fila.locator('select').select_option(LOC);fila.locator('[data-reg-add]').click()
            pg.wait_for_selector('.cons-reglas',timeout=15000)
            ok('→ ASOCIM' in pg.inner_text('.cons-reglas') or 'ASOCIM' in pg.inner_text('.cons-reglas'),'«Asignar» guarda la regla y el informe se recalcula')
            pg.locator('.cons-reglas [data-reg-del]').first.click();pg.wait_for_timeout(4000)
            ok(pg.locator('.cons-reglas').count()==0,'«Quitar» borra la regla')
        ok(not errs,f'{quien}: sin errores {errs}');pg.context.close()
    br.close()
print(n,'comprobaciones')
