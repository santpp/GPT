"""1.10.31 · Guardar la ficha: «Sin asignar» se manda a nuestro servidor y, al guardar, el aviso breve de la app «Ficha guardada.»
(o el motivo si la cuenta mantuvo al responsable). Teléfono y escritorio, sin errores ni desborde."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080');SHOTS=os.getenv('SHOTS')
def run(p,vp,warn,name):
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport=vp,service_workers='block').new_page();errors=[];sent=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    contact={'id':'k1','name':'Eduardo Rojas','phone':'+584243603978','source':'Ágora','assignedTo':'u-edu','assignedName':'Eduardo','customFields':[],'tags':[]}
    def api(route):
        u=urlparse(route.request.url);path=u.path[5:];method=route.request.method
        if path=='me':data={'user':{'role':'administrator','email':'a@e.test','identity_type':'native','capabilities':{'editContacts':True}},'csrf':'t'}
        elif path.startswith('workspace/connections'):data={'items':[{'id':1,'name':'Cuenta'}]}
        elif path=='workspace/record' and method=='POST':
            body=route.request.post_data_json;sent.append(body)
            if 'assignedTo' in body['fields'] and not warn:contact.pop('assignedTo',None);contact.pop('assignedName',None)
            data={'state':'accepted','id':'k1','requestId':body['requestId']}
            if warn:data['warning']='La ficha se guardó, pero la cuenta mantuvo al responsable. Puede que una regla automática de la cuenta lo vuelva a asignar.'
        elif path.startswith('workspace/contacts'):data={'items':[{'id':'k1','name':contact['name']}],'total':1,'restricted':False,'exact':True,'self':None}
        elif path.startswith('workspace/record'):data={'contact':dict(contact),'canEdit':True,'fields':[],'assignees':[{'id':'u-edu','name':'Eduardo'},{'id':'u-ana','name':'Ana'}]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/');page.wait_for_selector('#app:not([hidden])',timeout=15000)
    page.evaluate("document.querySelector('[data-view=\"contacts\"]').click()");page.wait_for_selector('#contact-results .record-row')
    page.click('#contact-results [data-contact="k1"]');page.wait_for_selector('#record-edit');page.click('#record-edit')
    page.select_option('#live-contact-form [name=assignedTo]','');page.click('#live-save-contact')
    page.wait_for_selector('#toast:not([hidden])')
    note=page.inner_text('#toast')
    if SHOTS:page.screenshot(path=f'{SHOTS}/{name}.png')
    overflow=page.evaluate('document.documentElement.scrollWidth>innerWidth+2')
    b.close();return sent,note,errors,overflow
with sync_playwright() as p:
    sent,note,errors,overflow=run(p,{'width':390,'height':844},False,'guardada-telefono')
    assert sent and sent[0]['fields'].get('assignedTo')=='',sent;print('PASS «Sin asignar» se manda a nuestro servidor como quitar el responsable')
    assert note=='Ficha guardada.',note;print('PASS al guardar aparece el aviso breve «Ficha guardada.»')
    assert not overflow and not errors,errors;print('PASS en el teléfono, sin desborde ni errores')
    sent,note,errors,overflow=run(p,{'width':1280,'height':800},True,'mantuvo-escritorio')
    assert 'mantuvo al responsable' in note,note;print('PASS si la cuenta mantuvo al responsable, el aviso lo dice')
    assert not errors,errors;print('PASS sin errores de pantalla')
