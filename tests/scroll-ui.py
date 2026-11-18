"""1.10.25 · Sin «scroll infinito» en el ordenador: con 120 conversaciones, contactos y oportunidades,
la página no crece (mide lo mismo que la pantalla) y cada lista se desplaza por dentro. En el teléfono, la página se desplaza como siempre."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080');N=120
conv=[{'id':f'c{i}','contactId':f'k{i}','contactName':f'Cliente {i}','lastMessageBody':'Hola','lastMessageType':'TYPE_WHATSAPP','lastMessageDate':'2026-11-18T10:00:00Z','unreadCount':2,'assignedTo':None} for i in range(N)]
cont=[{'id':f'k{i}','name':f'Contacto {i}','email':f'c{i}@e.test','phone':'+58414000000','tags':['cliente','vip']} for i in range(N)]
cards=[{'id':f'o{i}','name':f'Oportunidad {i}','contactId':f'k{i}','contactName':f'Cliente {i}','monetaryValue':100,'pipelineId':'p1','pipelineStageId':'s1' if i%2 else 's2','assignedName':'Ana'} for i in range(N)]
def api(route):
    path=urlparse(route.request.url).path[5:]
    if path=='me':data={'user':{'role':'administrator','email':'a@e.test','identity_type':'native','capabilities':{}},'csrf':'t'}
    elif path.startswith('workspace/connections'):data={'items':[{'id':1,'name':'Cuenta'}]}
    elif path.startswith('workspace/inbox'):data={'syncIntervalSeconds':120,'limit':100,'items':conv,'total':N,'partial':False}
    elif path.startswith('workspace/contacts'):data={'items':cont,'total':N,'restricted':False,'exact':True,'partial':False}
    elif path.startswith('workspace/board'):
        st=[{'id':s,'name':n,'items':[c for c in cards if c['pipelineStageId']==s],'total':N//2,'value':6000.0} for s,n in [('s1','Nuevo'),('s2','Propuesta')]]
        data={'pipelines':[{'id':'p1','name':'Ventas'}],'pipelineId':'p1','stages':st,'total':N,'restricted':False,'canEdit':True,'partial':False}
    elif path.startswith('workspace/pipelines'):data={'pipelines':[{'id':'p1','name':'Ventas','stages':[{'id':'s1','name':'Nuevo'},{'id':'s2','name':'Propuesta'}]}]}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    pg=b.new_context(viewport={'width':1280,'height':800},service_workers='block').new_page();errs=[];pg.on('pageerror',lambda e:errs.append(str(e)));pg.route('**/api/**',api)
    for v,lst in [('inbox','#live-list'),('contacts','#contact-results'),('board','.board-stage-body')]:
        pg.goto(BASE+'/');pg.wait_for_selector('#app:not([hidden])',timeout=15000);pg.locator(f'.sidebar [data-view="{v}"]').first.click();pg.wait_for_timeout(3000)
        alto=pg.evaluate('document.documentElement.scrollHeight')
        assert alto<=820,(v,alto)
        box=pg.locator(lst).first.bounding_box();pg.mouse.move(box['x']+40,box['y']+60);pg.mouse.wheel(0,2000);pg.wait_for_timeout(600)
        assert pg.evaluate(f"document.querySelector('{lst}').scrollTop")>0 and pg.evaluate('scrollY')==0,v
        print(f'PASS {v}: la página mide {alto} px y la lista se desplaza por dentro')
    assert not errs,errs
    b.close()
print('PASS sin errores de pantalla')
