#!/usr/bin/env python3
# © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.11.7 · Correcciones del usuario (20 nov):
1 «Leads de hoy» igual que en Mobile: el ERP pide el tablero de la cuenta con la zona horaria y el día de quien mira (antes, «hoy» en hora universal).
2 El menú de la agencia (Subcuentas y Centro de mando) con el lenguaje del menú de las subcuentas: fondo claro, ícono en recuadro de color,
  rótulos en tipo oración, sin franja dorada ni «Próximamente»; en el teléfono, una fila de pestañas que no tapa la pantalla.
3 «Seguridad de la agencia» dice «Entró», no el código «entro».
4 Inicio de sesión sin emoji ni punto verde junto a Athena, sin píldoras (Convenios, Ventas, Auditoría) y sin los textos de arriba y de abajo.
Requiere la API local en :8090. Uso: python3 tests/fixes-2117.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    pg=br.new_page();pg.goto(B+'/entrar');pg.wait_for_timeout(2000);t=pg.evaluate("document.body.innerText")
    ok(not any(x in t for x in ['👋','Operación comercial y convenios','Entra a la operación comercial','lo crea el administrador','Convenios\nVentas']) and pg.locator('.lg-chips,.lg-bubble-h i,.lg-hi').count()==0,'el inicio de sesión no tiene emoji, punto verde, píldoras ni textos de relleno')
    pg.close()
    for vp in ({'width':1280,'height':900},{'width':390,'height':844}):
        quien='escritorio' if vp['width']>600 else 'teléfono'
        subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
        ctx=br.new_context(viewport=vp,service_workers='block',timezone_id='America/Caracas');pg=ctx.new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
        pedidos=[];pg.on('request',lambda r:pedidos.append(r.url) if '/api/workspace/dashboard' in r.url else None)
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
        pg.locator('input').first.fill('duena@example.test');pg.locator('input[type=password]').fill('Clave-Local-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
        pg.goto(B+'/agencia/control');pg.wait_for_timeout(3500)
        d=pg.evaluate("""()=>{const r=document.querySelector('.launch-rail');const bg=getComputedStyle(r).backgroundColor.match(/\\d+/g).map(Number);
          return {claro:bg.slice(0,3).reduce((a,b)=>a+b,0)>600,soon:!!r.querySelector('.launch-soon'),may:[...r.querySelectorAll('.launch-rail-label')].some(e=>getComputedStyle(e).textTransform==='uppercase'),
          tiles:r.querySelectorAll('.launch-system > a .mdm-ic').length,oro:[...r.querySelectorAll('a')].some(a=>getComputedStyle(a).boxShadow.includes('inset')),
          codigos:[...document.querySelectorAll('.security-tx b')].map(b=>b.textContent).filter(t=>/^[a-z_.]+$/.test(t))}}""")
        ok((vp['width']<=600 or (d['claro'] and not d['oro'])) and not d['soon'] and not d['may'] and d['tiles']==4,f'{quien}: el menú de la agencia usa el lenguaje del menú de las subcuentas {d}')
        if vp['width']<=600:ok(pg.evaluate("document.querySelector('.launch-rail').getBoundingClientRect().height")<90,'teléfono: el menú de la agencia es una fila que no tapa la pantalla')
        ok(not d['codigos'],f'{quien}: Seguridad de la agencia sin códigos internos {d["codigos"]}')
        ok(not pg.evaluate('document.documentElement.scrollWidth>innerWidth+2'),f'{quien}: sin desborde')
        if vp['width']>600:
            pg.evaluate("async()=>{await fetch('/api/workspace/active',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({connectionId:2})})}")
            pg.goto(B+'/centro');pg.wait_for_timeout(5000)
            ok(pedidos and all('tz=America%2FCaracas' in u and 'from=' in u for u in pedidos),f'el tablero de la cuenta se pide con la zona y el día de quien mira {pedidos[-1:] }')
        ok(not errs,f'{quien}: sin errores {errs}');ctx.close()
    br.close()
print(n,'comprobaciones')
