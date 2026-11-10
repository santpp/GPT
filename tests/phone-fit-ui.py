"""1.10.9: en el teléfono nada se sale del ancho; el menú de abajo no se desliza (cuatro pestañas y «Más» para la propietaria)."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def api(route):
    path=urlparse(route.request.url).path
    if path=='/api/me':d={'user':{'role':'administrator','email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':d={'items':[{'id':1,'name':'UBA Line Pro','currency':'USD'},{'id':2,'name':'ASOCIM','currency':'USD'}],'reason':None}
    elif path=='/api/workspace/inbox':d={'limit':20,'items':[{'id':'c%d'%i,'contactName':n,'lastMessageType':t,'lastMessageBody':'¡Excelente! Ya registré tus datos de interés. En este momento','lastMessageDirection':'inbound','lastMessageDate':1793900000000-i*600000,'unreadCount':4} for i,(n,t) in enumerate([('eilynascanio','TYPE_INSTAGRAM'),('Martin Hernández Rangel','TYPE_INSTAGRAM'),('.','TYPE_WHATSAPP')])],'total':3775,'partial':True,'channels':{'whatsapp':'WhatsApp','instagram':'Instagram','facebook':'Messenger'}}
    elif path=='/api/workspace/board':d={'pipelines':[{'id':'p1','name':'Embudo de Ventas Principal 2026'}],'restricted':False,'pipelineId':'p1','total':100,'partial':True,'canEdit':True,'stages':[{'id':'s1','name':'Lead Nuevo','total':30,'value':130.0,'items':[{'id':'o1','name':'Martin HernáNdez Rangel','contactName':'Martin Hernández Rangel','monetaryValue':0,'pipelineStageId':'s1'},{'id':'o2','name':'Monica Catari Catari','contactName':'Otra Persona','monetaryValue':0,'pipelineStageId':'s1'}]}]}
    else:d={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for w in (360,372,390,412):
        ctx=b.new_context(viewport={'width':w,'height':800},service_workers='block');ctx.add_init_script("localStorage.setItem('agora-tour-done','1')")
        pg=ctx.new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)));pg.route('**/api/**',api)
        pg.goto(BASE+'/bandeja');pg.wait_for_selector('.live-conversation');pg.wait_for_timeout(400)
        over=lambda:pg.evaluate("""()=>{const w=document.documentElement.clientWidth;return [document.documentElement.scrollWidth,[...document.querySelectorAll('#view *, .topbar *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.right>w+1&&!e.closest('.sidebar')}).slice(0,4).map(e=>e.tagName+'.'+String(e.className).slice(0,24)+':'+Math.round(e.getBoundingClientRect().right))]}""")
        sw,bad=over();assert sw<=w and not bad,(w,'bandeja',sw,bad)
        sel=pg.locator('.live-channel-filters select').bounding_box();card=pg.locator('.live-sidebar').bounding_box()
        assert sel['x']>=card['x'] and sel['x']+sel['width']<=card['x']+card['width']+1,(w,sel,card)
        for t in pg.locator('.live-sidebar .tabs button:visible').all():
            assert t.evaluate('e=>e.getBoundingClientRect().height')<60,(w,'una pestaña se parte en varias líneas')
        top=pg.evaluate("[...document.querySelectorAll('.topbar > *')].map(e=>Math.round(e.getBoundingClientRect().top))");assert max(top)-min(top)<40,(w,'la barra de arriba se parte en dos filas',top)
        # el menú de abajo: ninguna pestaña con la etiqueta pisada por otra
        boxes=pg.evaluate("[...document.querySelectorAll('.sidebar nav .nav')].map(b=>{const r=b.getBoundingClientRect();return [r.left,r.right,r.width,b.id]}).filter(x=>x[2]>0)")
        for a,c in zip(boxes,boxes[1:]):assert c[0]>=a[1]-1,(w,'el menú de abajo se pisa',boxes)
        assert len(boxes)==5 and boxes[-1][3]=='nav-more',(w,'cuatro pestañas y «Más» a la derecha',boxes)
        assert boxes[-1][1]<=w+1,(w,'«Más» se sale de la pantalla',boxes)
        assert pg.evaluate("document.querySelector('.sidebar nav').scrollWidth<=document.querySelector('.sidebar nav').clientWidth+1"),(w,'el menú de abajo se desliza')
        pg.locator('#nav-more').click();pg.wait_for_selector('#more-sheet:not([hidden])')
        assert pg.locator('#more-sheet [data-more]').all_inner_texts()==['Consumos','Conexiones','Ayuda'],pg.locator('#more-sheet [data-more]').all_inner_texts()
        pg.locator('#more-sheet [data-more="consumption"]').click();pg.wait_for_selector('#more-sheet',state='hidden')
        assert 'active' in pg.locator('#nav-more').get_attribute('class'),'«Más» se marca cuando la pantalla está dentro'
        pg.locator('#nav-more').click();pg.locator('#more-sheet').click(position={'x':10,'y':10});pg.wait_for_selector('#more-sheet',state='hidden')
        pg.locator('button[data-view="board"]').first.click();pg.wait_for_selector('.board-card2');pg.wait_for_timeout(300)
        sw,bad=over();assert sw<=w and not bad,(w,'oportunidades',sw,bad)
        for sid in ('#board-pipeline','#board-stage'):
            e=pg.locator(sid).bounding_box();assert e['width']>w*0.6,(w,sid,'el desplegable es muy angosto',e)
        assert pg.locator('.board-card2-sub').count()==1,'el nombre del contacto sólo se repite si es distinto al de la oportunidad'
        assert not errs,errs
        print(f'PASS {w}px: filters, tabs, top bar, bottom menu and the funnel fit inside the screen')
    b.close()
