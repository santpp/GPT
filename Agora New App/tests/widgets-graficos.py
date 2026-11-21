#!/usr/bin/env python3
"""Agora New App 2.16.0 · Widgets fases 4, 5 y 6 en pantalla:
gráficos (línea, barras, área, dona, tabla) al final del módulo con tooltip y las mismas cifras del widget;
detalle de una cifra con su descarga; tablero solo para la administración; copiar de otro módulo; cifras del reporte.
Requiere la API local en :8090 y la cuenta simulada (ASOCIM conectada). Uso: python3 tests/widgets-graficos.py"""
import sys,re,os
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'widgets-ui.py'),encoding='utf-8').read().split('adm=limpiar_datos()')[0])
def sql(q):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','agora_erp','-N','-e',q],capture_output=True,text=True).stdout.strip()
def borrar():
    limpiar();a=C('admin.asocim@agora.test')
    for m in ('ventas','costos','centro'):a.call(f'workspace/tableros?modulo={m}','DELETE',{'modulo':m})
    return a
a=borrar()
W=lambda i,r,**k:{'id':i,'tipo':k.get('tipo','cifra'),'fuente':'metrica','ref':r,'titulo':k.get('t',''),'nota':'','tamano':k.get('tam','chico'),'periodo':'modulo','comparar':'','formato':'auto','decimales':2,'semaforo':{'activo':False},'mas':[{'fuente':'metrica','ref':x} for x in k.get('mas',[])],'meses':k.get('meses',6),'top':5}
st,_=a.call('workspace/tableros','PUT',{'modulo':'ventas','version':0,'widgets':[W('cifra01','rep.vendido'),W('linea01','rep.vendido',tipo='linea',t='Vendido por mes',mas=['rep.pendiente'],tam='ancho'),W('barra01','rep.inscritos',tipo='barras',t='Inscritos'),W('dona001','rep.costos_fijos',tipo='dona',t='Costos del mes',mas=['rep.costos_variables','rep.anuncios']),W('tabla01','rep.vendido',tipo='tabla',t='Lo más grande',mas=['rep.pendiente','rep.cuota1','rep.entradas'],tam='ancho')]})
ok(st==200,'tablero de prueba con una cifra y cuatro gráficos')
serie=a.call('workspace/metricas/serie?ids=rep.vendido,rep.pendiente&hasta=2026-11&meses=6')[1]
with sync_playwright() as p:
    br=p.chromium.launch(executable_path='/opt/pw-browsers/chromium',args=['--no-sandbox'])
    try:
        pg=entrar(br,'admin.asocim@agora.test');pg.goto(B+'/ventas');pg.wait_for_timeout(6500)
        r=pg.evaluate("""(()=>{const g=document.querySelector('section.wg-g');return [g&&g.parentElement.lastElementChild===g, document.querySelectorAll('section.wg .wg-cel').length, g?g.querySelectorAll('.wg-graf').length:0, document.querySelectorAll('.wg-graf .wg-svg').length, !!g.querySelector('.wg-tabla')]})()""")
        ok(r[0],'los gráficos van al final del módulo (decisión del usuario)')
        ok(r[1]==1 and r[2]==4 and r[3]==3 and r[4],f'la cifra queda arriba y los 4 gráficos abajo: línea, barras y dona dibujados y la tabla {r}')
        ok(pg.locator('.wg-graf .xt-bar, .wg-graf .xt-q').count()==0,'la tabla del widget no recibe el buscador ni las columnas de las tablas del módulo')
        f=pg.locator('.wg-graf[data-wg-id=linea01]');f.scroll_into_view_if_needed()
        ok(f.locator('.wg-ley span').count()==2,'con dos series hay leyenda (no se distingue solo por color)')
        ok(f.locator('polyline').count()>=2 and f.locator('svg[role=img][aria-label]').count()==1,'la línea tiene sus dos trazos y una descripción para lectores de pantalla')
        b=f.locator('.wg-hov').nth(5);bb=b.bounding_box();pg.mouse.move(bb['x']+bb['width']/2,bb['y']+bb['height']/2);pg.wait_for_timeout(300)
        tip=pg.evaluate("document.querySelector('.wg-graf[data-wg-id=linea01] .wg-tip').innerText")
        esp=f"{serie['series']['rep.vendido'][5]['valor']:,.2f}".replace(',','X').replace('.',',').replace('X','.')
        ok('noviembre de 2026' in tip and esp in tip,f'el tooltip del gráfico da la misma cifra que el widget ({esp} en «{tip[:60]}…»)')
        cel=pg.locator('section.wg .wg-cel').first.locator('.ag-resumen-v').inner_text()
        ok(esp in cel,'y la misma que la cifra de arriba')
        d=pg.locator('.wg-graf[data-wg-id=dona001]')
        ok(d.locator('.wg-arco').count()==3 and '%' in d.locator('.wg-ley-v').inner_text(),'la dona reparte en partes con su porcentaje')
        t=pg.locator('.wg-graf[data-wg-id=tabla01] tbody tr')
        vals=[float(re.sub(r'[^\d,]','',x).replace(',','.')) for x in t.locator('td:last-child').all_inner_texts()]
        ok(len(vals)==4 and vals==sorted(vals,reverse=True),f'la tabla ordena de mayor a menor {vals}')
        # detalle y descarga
        pg.locator('section.wg .wg-cel').first.click();pg.wait_for_selector('[data-wg-csv]')
        ok(pg.locator('.wg-detalle .wg-tabla tbody tr').count()==6 and 'Reporte mensual' in pg.inner_text('.wg-detalle'),'el detalle trae qué mide, de dónde sale y los últimos 6 meses')
        with pg.expect_download() as dl:pg.click('[data-wg-csv]')
        ruta=dl.value.path();txt=open(ruta,encoding='utf-8-sig').read()
        ok(dl.value.suggested_filename.endswith('.csv') and 'noviembre de 2026' in txt and 'Qué mide' in txt,f'«Descargar» baja un archivo para Excel ({dl.value.suggested_filename})')
        pg.wait_for_timeout(800)
        aud=sql("select count(*) from agora_audit where action like 'cliente:descargo.%' and created_at>now()-interval 1 minute")
        ok(int(aud or 0)>=1,'la descarga queda en Auditoría')
        pg.click('.modal [data-close] >> nth=-1');pg.wait_for_timeout(300)
        pg.click('.wg-graf[data-wg-id=dona001] [data-wg-det]');pg.wait_for_selector('.wg-det-v');pg.click('.modal [data-close] >> nth=-1')
        # editor: tipos, series, «solo la administración» y copiar
        abrir_ed(pg);pg.wait_for_selector('[data-wg-visible]')
        pg.select_option('[data-wg-nuevo]','mes.vendido');pg.wait_for_timeout(200)
        pg.select_option('.wg-item.is-open [data-k=tipo]','area');pg.wait_for_timeout(200)
        ok(pg.locator('.wg-item.is-open [data-k=meses]').count()==1 and pg.locator('.wg-item.is-open [data-k=comparar]').count()==0 and pg.locator('.wg-item.is-open [data-k=sem]').count()==0,'al elegir un gráfico aparecen «Meses» y las series; la comparación y el semáforo son de las cifras')
        pg.select_option('.wg-item.is-open [data-mas-nueva]','mes.mis_ventas');pg.wait_for_timeout(200)
        ok(pg.locator('.wg-item.is-open [data-mas]').count()==1,'se agrega una segunda serie')
        pg.select_option('[data-wg-visible]','administracion')
        pg.click('[data-wg-guardar]');pg.wait_for_timeout(6000)
        ok(pg.locator('section.wg-g .wg-graf').count()==5 and pg.locator('.wg-priv').count()>=1,'se guarda: el gráfico de área queda al final y se ve la marca «Solo la administración»')
        pg.close()
        pg=entrar(br,'ejecutivo1.asocim@agora.test');pg.goto(B+'/ventas');pg.wait_for_timeout(5500)
        ok(pg.locator('section.wg .wg-cel, section.wg-g .wg-graf').count()==0,'el ejecutivo no ve un tablero que es solo de la administración')
        pg.close()
        pg=entrar(br,'admin.asocim@agora.test');pg.goto(B+'/costos');pg.wait_for_timeout(5000)
        abrir_ed(pg);pg.wait_for_selector('[data-wg-copiar]')
        pg.select_option('[data-wg-copiar]','ventas');pg.wait_for_timeout(1200)
        ok(pg.locator('.wg-item').count()==6,'«Copiar los widgets de otro módulo» trae los 6 de Ventas')
        pg.click('[data-wg-guardar]');pg.wait_for_timeout(6000)
        ok(pg.locator('section.wg-g .wg-graf').count()==5 and pg.evaluate("(()=>{const g=document.querySelector('section.wg-g');return g.parentElement.lastElementChild===g})()"),'en Costos también quedan al final')
        ok(not pg.errs,f'sin errores de la página {pg.errs[:2]}')
        pg.close()
        pg=entrar(br,'admin.asocim@agora.test',390,844);pg.goto(B+'/ventas');pg.wait_for_timeout(6500)
        r=pg.evaluate("(()=>{const g=document.querySelector('section.wg-g').getBoundingClientRect();return [Math.round(g.right),document.documentElement.scrollWidth,[...document.querySelectorAll('.wg-graf')].every(f=>f.getBoundingClientRect().right<=391)]})()")
        ok(r[0]<=390 and r[1]<=390 and r[2],f'teléfono 390: los gráficos caben sin desborde {r}')
        pg.close()
    finally:
        borrar();br.close()
print(n,'comprobaciones')
