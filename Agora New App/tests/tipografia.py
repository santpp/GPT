#!/usr/bin/env python3
"""Agora New App 2.11.5 · Una sola fuente y una sola escala en todos los módulos.
Fuente: Author en todo el texto (solo los códigos usan letra de ancho fijo). Escala (escritorio):
título del módulo 28 px · títulos de sección 20 px · títulos de panel 16,5 px · tarjetas: rótulo 14 px (13 en el teléfono; 2.12.6) y cifra 26 px
(como «Resumen de hoy») · botones de cabecera 13,5 px. En el teléfono: título 24 px y cifra 22 px.
Requiere la API local en :8090. Uso: python3 tests/tipografia.py"""
import sys,subprocess
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8090';n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);sys.exit(1)
    n+=1;print('PASS',m)
RUTAS=['/centro','/inicio','/comercial','/ventas','/cobros','/facturas','/libro-ventas','/datos-fiscales','/tasas','/productos','/catalogo','/oportunidades','/tareas','/equipo','/publicidad','/costos','/tendencias','/calidad','/redes','/contratos','/configuracion','/usuarios','/ajustes','/personalizar','/migracion','/ayuda']
JS="""()=>{const m=document.querySelector('main');const out={fam:{},h1:[],sec:[],pan:[],t:[],v:[],b:[]};
 const px=e=>parseFloat(getComputedStyle(e).fontSize);const vis=e=>!!e.offsetParent;
 for(const e of m.querySelectorAll('*')){if(!vis(e))continue;if(![...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim()))continue;
   const f=getComputedStyle(e).fontFamily.split(',')[0].replace(/["']/g,'').trim();out.fam[f]=(out.fam[f]||0)+1;}
 const take=(k,s)=>m.querySelectorAll(s).forEach(e=>{if(vis(e))out[k].push(px(e))});
 take('h1','h1');take('sec','h2.t-sec,h2.hx-sec,#manual h2');take('pan','.pf-sec-h h2,h2.t-panel,#inicio-resumen-t');
 take('t','.ag-resumen-t,.micro > h3,.inicio-resumen-l');take('v','.ag-resumen-v,.micro .t-metric,.inicio-resumen-v');
 take('b','.mod-h .btn,.inicio-resumen-h .btn,#period-btn');return out}"""
def recorrer(pg,ancho):
    malos=[]
    for r in RUTAS:
        pg.goto(B+r);pg.wait_for_timeout(3500)
        d=pg.evaluate(JS)
        otras=[f for f in d['fam'] if f not in ('Author','ui-monospace')]
        if otras:malos.append(f'{r}: fuente {otras}')
        esp={'h1':28 if ancho>600 else 24,'sec':20 if ancho>600 else 18,'pan':16.5,'t':14 if ancho>600 else 13,'b':13.5}
        for k,v in esp.items():
            if any(abs(x-v)>.3 for x in d[k]):malos.append(f'{r}: {k} {sorted(set(d[k]))} (esperado {v})')
        vv=[x for x in d['v'] if not (ancho<=600 and abs(x-20)<.3)]  # la caja de Gestión Comercial usa 20 px en el teléfono
        if any(abs(x-(26 if ancho>600 else 22))>.3 for x in vv):malos.append(f'{r}: cifra {sorted(set(vv))}')
    return malos
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    for ancho in (1280,390):
        subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-e','delete from agora_rate_limits'])
        pg=br.new_context(viewport={'width':ancho,'height':900},service_workers='block').new_page()
        pg.goto(B+'/entrar');pg.wait_for_timeout(1200)
        pg.locator('input').first.fill('admin.asocim@agora.test');pg.locator('input[type=password]').fill('Agora-Pruebas-2026!');pg.keyboard.press('Enter');pg.wait_for_timeout(3000)
        malos=recorrer(pg,ancho)
        ok(not malos,f'{"escritorio" if ancho>600 else "teléfono"}: Author en todo y la misma escala en los {len(RUTAS)} módulos {malos[:6]}')
        pg.context.close()
    br.close()
print(n,'comprobaciones')
