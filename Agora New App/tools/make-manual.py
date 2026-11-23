#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""2.21.0 · Manual «Agora Digital App - ERP» (pedido del usuario: desde cero, con el formato del manual de Mobile).
Toma las capturas en el ERP local (datos de prueba inventados, subcuenta ASOCIM con la cuenta simulada) y arma el PDF
a partir de tools/manual/manual-erp.html. Uso, con la API local en :8090:
    python3 tools/make-manual.py
Deja las capturas en tools/manual/img/ y el PDF en manual-erp.pdf (se entrega aparte, no va en el ZIP)."""
import os,re,sys,time,datetime,json,urllib.request,http.cookiejar
from playwright.sync_api import sync_playwright
from PIL import Image
AQUI=os.path.dirname(os.path.abspath(__file__));RAIZ=os.path.dirname(AQUI)
IMG=os.path.join(AQUI,'manual','img');os.makedirs(IMG,exist_ok=True)
B=os.environ.get('ERP','http://127.0.0.1:8090');U='admin.asocim@agora.test';P='Agora-Pruebas-2026!'
VER=re.search(r"'version'=>'([\d.]+)'",open(os.path.join(RAIZ,'servidor/app/src/Controllers/Api.php'),encoding='utf-8').read()).group(1)
MESES=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre']
hoy=datetime.date.today();FECHA=f'{MESES[hoy.month-1]} de {hoy.year}'
PROHIBIDO=re.compile(r'GoHighLevel|HighLevel|LeadConnector|\bGHL\b')
def reducir(ruta,ancho):
    im=Image.open(ruta).convert('RGB');im=im.resize((ancho,round(im.height*ancho/im.width)),Image.LANCZOS)
    im.quantize(colors=160,method=Image.MEDIANCUT,dither=Image.NONE).save(ruta,optimize=True)
def foto(pg,nombre,ancho=1400,el=None):
    pg.wait_for_timeout(900);ruta=os.path.join(IMG,nombre)
    txt=pg.inner_text('body')
    if PROHIBIDO.search(txt):sys.exit(f'La captura {nombre} muestra el nombre del proveedor')
    (el or pg).screenshot(path=ruta,animations='disabled');reducir(ruta,ancho);print('captura',nombre,os.path.getsize(ruta)//1024,'KB')
def contexto(br,w,h,tutorial=False):
    ctx=br.new_context(viewport={'width':w,'height':h},device_scale_factor=2,service_workers='block',locale='es-ES')
    # En una sesión automática el tutorial no se abre solo (tutorial.js); para la captura del tutorial se pide con agora.tutorial.auto.
    return ctx
def entrar(ctx):
    pg=ctx.new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)));pg.errs=errs
    pg.goto(B+'/entrar');pg.wait_for_timeout(1200);pg.locator('input').first.fill(U);pg.locator('input[type=password]').fill(P);pg.keyboard.press('Enter')
    pg.wait_for_url(lambda u:'/entrar' not in u,timeout=20000);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
    return pg
def ir(pg,ruta,esperar=None):
    pg.goto(B+ruta);pg.wait_for_selector('#ag-entrada',state='detached',timeout=25000)
    if esperar:pg.wait_for_selector(esperar,timeout=20000)
    pg.wait_for_timeout(1500)
with sync_playwright() as pw:
    br=pw.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    # Entrada (sin sesión)
    ctx=contexto(br,1280,800);pg=ctx.new_page();pg.goto(B+'/entrar');pg.wait_for_selector('input[type=password]');pg.wait_for_timeout(1800);foto(pg,'01-entrar.png');pg.close()
    pg=entrar(ctx)
    ir(pg,'/centro');foto(pg,'02-inicio.png')
    ir(pg,'/comercial','#dashboard-body');foto(pg,'04-dashboard.png')
    ir(pg,'/bandeja','[data-live-id="c0"]');pg.locator('[data-live-id="c0"]').first.click();pg.wait_for_selector('#live-lost',state='visible');foto(pg,'05-bandeja.png')
    ir(pg,'/contactos','.record-row');pg.locator('.record-row').first.click();pg.wait_for_timeout(1200);foto(pg,'07-contactos.png')
    ir(pg,'/oportunidades','.kanban');foto(pg,'08-oportunidades.png')
    ir(pg,'/tareas','.tw');foto(pg,'09-tareas.png')
    ir(pg,'/inicio');foto(pg,'10-gestion.png')
    ir(pg,'/ventas','.tw');foto(pg,'11-ventas.png')
    ir(pg,'/facturas');foto(pg,'12-facturas.png')
    ir(pg,'/contratos');foto(pg,'13-contratos.png')
    ir(pg,'/comercial','#dashboard-body');pg.locator('#b-notif').click();pg.wait_for_timeout(1200);foto(pg,'14-notificaciones.png')
    errs=list(pg.errs);ctx.close()
    # Tutorial de la Bandeja (primera visita)
    ctx=contexto(br,1280,800,tutorial=True);pg=entrar(ctx);pg.evaluate("localStorage.setItem('agora.tutorial.auto','1')")
    ir(pg,'/bandeja');pg.wait_for_selector('#ag-tour .tour-card',timeout=20000);pg.locator('#ag-tour .tour-sig').click();pg.wait_for_timeout(700);foto(pg,'03-tutorial.png')
    errs+=pg.errs;ctx.close()
    # «Lead perdido» en el teléfono
    ctx=br.new_context(viewport={'width':390,'height':844},device_scale_factor=2,service_workers='block',locale='es-ES');pg=entrar(ctx)
    ir(pg,'/bandeja','[data-live-id="c3"]');pg.locator('[data-live-id="c3"]').first.click();pg.wait_for_selector('#live-lost',state='visible')
    pg.locator('#live-lost').click();pg.wait_for_selector('.lost-form');foto(pg,'06-perdido.png',ancho=600)
    errs+=pg.errs;ctx.close()
    # PDF
    html=open(os.path.join(AQUI,'manual','manual-erp.html'),encoding='utf-8').read().replace('{{VERSION}}',VER).replace('{{FECHA}}',FECHA)
    tmp=os.path.join(AQUI,'manual','.manual-listo.html');open(tmp,'w',encoding='utf-8').write(html)
    pg=br.new_page();pg.goto('file://'+tmp);pg.wait_for_load_state('networkidle')
    pg.evaluate("Promise.all([...document.images].map(i=>i.complete?1:new Promise(r=>i.onload=i.onerror=r)))");pg.wait_for_timeout(800)
    rotas=pg.evaluate("[...document.images].filter(i=>!i.naturalWidth).map(i=>i.getAttribute('src'))")
    if rotas:sys.exit(f'Imágenes que no cargan: {rotas}')
    if PROHIBIDO.search(pg.inner_text('body')):sys.exit('El manual nombra al proveedor')
    salida=os.path.join(RAIZ,'manual-erp.pdf')
    pg.pdf(path=salida,format='A4',print_background=True,prefer_css_page_size=True,display_header_footer=True,header_template='<span></span>',
           footer_template='<div style="font:8px system-ui,sans-serif;color:#8a8f9c;width:100%;text-align:center">Agora Digital App - ERP · <span class="pageNumber"></span> de <span class="totalPages"></span></div>')
    os.remove(tmp);br.close()
    print('PDF listo:',salida,os.path.getsize(salida)//1024,'KB · versión',VER)
    if errs:print('Errores de pantalla durante las capturas:',errs[:3])
