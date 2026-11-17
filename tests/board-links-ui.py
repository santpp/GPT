"""1.10.19 · Tablero: cada cifra lleva a su módulo ya filtrado (según permisos) y Contactos tiene «Creados hoy»."""
import json,os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def run(p,caps,restricted,label):
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':1280,'height':900},service_workers='block').new_page()
    errors=[];contacts=[];page.on('pageerror',lambda e:errors.append(str(e)))
    def api(route):
        u=urlparse(route.request.url);q=parse_qs(u.query);path=u.path
        if path=='/api/me':data={'user':{'role':'team' if restricted else 'administrator','email':'a@example.test','identity_type':'external' if restricted else 'native','capabilities':caps},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}],'reason':None}
        elif path=='/api/workspace/dashboard':
            data={'restricted':restricted,'conversations':{'total':40,'unread':3,'waiting':7},'contacts':None if restricted else 900,'leads':{'today':5,'mine':2,'linked':True},
                  'opportunities':{'count':4,'value':800,'stages':[{'name':'Ventas · Nuevo','count':4,'value':800}]} if caps.get('viewOpportunities',True) else None,'readAt':'2026-10-31T20:00:00+00:00'}
        elif path=='/api/workspace/board-month':data={'error':'x'}
        elif path=='/api/workspace/contacts':contacts.append(q);data={'items':[{'id':'k1','name':'Nuevo hoy','email':'n@example.test'}],'total':1,'restricted':restricted,'exact':True,'partial':False,'hasMore':False}
        elif path=='/api/workspace/inbox':data={'limit':20,'items':[],'total':0}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    def board():
        page.goto(BASE+'/tablero');page.wait_for_selector('.board-tile')
    board()
    links={page.locator('.board-tile.is-link span').nth(i).inner_text().replace('›','').strip() for i in range(page.locator('.board-tile.is-link').count())}
    return page,b,errors,contacts,links,board
with sync_playwright() as p:
    page,b,errors,contacts,links,board=run(p,{},False,'admin')
    assert {'Conversaciones','Sin leer','Esperando respuesta','Contactos','Oportunidades abiertas','Leads de hoy','Mis leads de hoy'}<=links,links
    page.locator('.board-tile.is-link',has_text='Leads de hoy').first.click();page.wait_for_selector('[data-contact-since="hoy"].active')
    assert contacts[-1].get('since')==['hoy'] and contacts[-1].get('tz'),contacts[-1]
    assert 'creado' in page.locator('#contact-feedback').inner_text()
    print('PASS «Leads de hoy» abre Contactos con «Creados hoy» (fecha de la cuenta, hora del dispositivo)')
    page.locator('[data-contact-since=""]').click();page.wait_for_timeout(500)
    assert 'since' not in contacts[-1]
    page.locator('[data-contact-since="hoy"]').click();page.wait_for_timeout(500)
    assert contacts[-1].get('since')==['hoy']
    print('PASS en Contactos se cambia entre «Todos» y «Creados hoy»')
    board();page.locator('.board-tile.is-link',has_text='Sin leer').click();page.wait_for_selector('[data-live-filter="unread"].active')
    board();page.locator('.board-tile.is-link',has_text='Esperando respuesta').click();page.wait_for_selector('[data-live-filter="waiting"].active')
    print('PASS «Sin leer» y «Esperando respuesta» abren la bandeja con su filtro')
    board();page.locator('.board-stage.is-link').first.click();page.wait_for_timeout(1200)
    assert page.evaluate("document.querySelector('main, #view, body').dataset.view||location.pathname").endswith(('board','oportunidades')) or 'oportunidades' in page.url,page.url
    print('PASS una etapa del embudo lleva a Oportunidades')
    assert not errors,errors;b.close()
    # Vendedor sin permiso de oportunidades: ve sus cifras, y lo que no puede abrir no es enlace.
    page,b,errors,contacts,links,board=run(p,{'viewConversations':True,'viewContacts':True,'viewOpportunities':False,'assignedDataOnly':True},True,'vendedor')
    t=page.locator('#dashboard-body').inner_text()
    assert 'Mis leads de hoy' in t and 'Leads de hoy\n' not in t.replace('Mis leads de hoy',''),t
    assert 'Oportunidades abiertas' not in links and page.locator('.board-stage.is-link').count()==0,links
    assert 'Mis leads de hoy' in links and 'Sin leer' in links
    print('PASS un vendedor ve solo lo suyo y no recibe enlaces a módulos que no puede ver')
    assert not errors,errors;b.close()
print('PASS board links')
