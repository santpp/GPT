"""1.10.30 · «Asignármelo · <nombre>» dice a quién se asigna, se manda quién es «tú» y al crear se avisa a quién quedó asignado; sin saber quién eres, no hay casilla.
1.10.27 · «Nuevo contacto» en Contactos: validación, envío a nuestro servidor (nunca a la cuenta), duplicado con «Abrir ficha»,
y al crearlo se abre su ficha. En el teléfono, sin desborde. Quien no puede editar fichas no ve el botón."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def run(p,vp,caps,mode,self_={'id':'u-duena','name':'Dueña'}):
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport=vp,service_workers='block').new_page();errors=[];sent=[];foreign=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.on('request',lambda r:foreign.append(r.url) if 'leadconnector' in r.url or 'gohighlevel' in r.url else None)
    contacts=[{'id':'k1','name':'Ana Pérez','email':'ana@e.test'}]
    def api(route):
        u=urlparse(route.request.url);path=u.path[5:];method=route.request.method
        if path=='me':data={'user':{'role':'administrator','email':'a@e.test','identity_type':'native','capabilities':caps},'csrf':'t'}
        elif path.startswith('workspace/connections'):data={'items':[{'id':1,'name':'Cuenta'}]}
        elif path=='workspace/contacts' and method=='POST':
            body=route.request.post_data_json;sent.append((body,route.request.headers.get('x-csrf-token')))
            if mode=='duplicado':return route.fulfill(status=409,content_type='application/json',body=json.dumps({'error':'Ya existe un contacto con ese correo. Ábrelo en vez de crear otro.','code':'contact_exists','contactId':'k1'}))
            contacts.insert(0,{'id':'new-1','name':body['firstName']+' '+body['lastName'],'phone':body['phone']})
            return route.fulfill(status=201,content_type='application/json',body=json.dumps({'state':'accepted','id':'new-1','name':'x','assignedTo':'u-duena' if body.get('assignToMe') else None,'assignedName':'Dueña' if body.get('assignToMe') else None,'assignedAsAsked':True}))
        elif path.startswith('workspace/contacts'):data={'items':contacts,'total':len(contacts),'restricted':False,'exact':True,'partial':False,'self':self_}
        elif path.startswith('workspace/record'):
            cid=dict(x.split('=') for x in u.query.split('&'))['contactId'];c=next(x for x in contacts if x['id']==cid)
            data={'contact':{**c,'customFields':[],'tags':[]},'canEdit':True,'fields':[]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/');page.wait_for_selector('#app:not([hidden])',timeout=15000)
    page.evaluate("document.querySelector('[data-view=\"contacts\"]').click()");page.wait_for_selector('#contact-results .record-row')
    return b,page,errors,sent,foreign
with sync_playwright() as p:
    b,page,errors,sent,foreign=run(p,{'width':1280,'height':800},{'editContacts':True},'ok')
    page.click('#contact-new');page.wait_for_selector('#contact-new-form')
    assert 'Asignármelo · Dueña' in page.inner_text('#contact-new-form');print('PASS «Asignármelo» dice a quién se asigna')
    page.click('#contact-new-save');assert 'nombre' in page.inner_text('#contact-new-status') and not sent
    page.fill('#contact-new-form [name=firstName]','Lucía');page.click('#contact-new-save');assert 'teléfono o el correo' in page.inner_text('#contact-new-status') and not sent
    page.fill('#contact-new-form [name=phone]','0414 123 4567');page.click('#contact-new-save');assert 'código del país' in page.inner_text('#contact-new-status') and not sent
    print('PASS el formulario explica qué falta y no envía nada hasta que está bien')
    page.fill('#contact-new-form [name=lastName]','Gómez');page.fill('#contact-new-form [name=phone]','+58 414 123-4567');page.check('#contact-new-form [name=assignToMe]');page.click('#contact-new-save')
    page.wait_for_selector('.record-header h2:has-text("Lucía Gómez")')
    page.wait_for_selector('text=Contacto creado y asignado a Dueña');print('PASS al crear, avisa a quién quedó asignado')
    body,csrf=sent[0]
    assert body['firstName']=='Lucía' and body['lastName']=='Gómez' and body['phone']=='+584141234567' and body['assignToMe'] is True and body.get('selfId')=='u-duena' and len(body['requestId'])>=16 and csrf=='t',sent
    print('PASS se envía a nuestro servidor con el teléfono limpio, «Asignármelo» y su identificador, y se abre la ficha nueva')
    assert page.locator('#contact-results [data-contact="new-1"]').count()==1;print('PASS el contacto nuevo aparece en la lista')
    assert not foreign,foreign;print('PASS el navegador nunca habla con la cuenta directamente')
    assert not errors,errors;b.close()
    b,page,errors,sent,foreign=run(p,{'width':390,'height':844},{'editContacts':True,'assignedDataOnly':True},'duplicado')
    page.click('#contact-new');page.wait_for_selector('#contact-new-form')
    assert page.locator('#contact-new-form [name=assignToMe]').count()==0 and 'asignado a ti' in page.inner_text('.record-header');print('PASS el vendedor ve que se le asigna a él (sin casilla)')
    assert not page.evaluate('document.documentElement.scrollWidth>innerWidth+2');print('PASS en el teléfono el formulario no se desborda')
    page.fill('#contact-new-form [name=firstName]','Ana');page.fill('#contact-new-form [name=email]','ana@e.test');page.click('#contact-new-save')
    page.wait_for_selector('#contact-new-open');assert 'Ya existe' in page.inner_text('#contact-new-status')
    page.click('#contact-new-open');page.wait_for_selector('.record-header h2:has-text("Ana Pérez")');print('PASS si ya existe, lo dice y «Abrir ficha» lleva al contacto existente')
    assert not errors,errors;b.close()
    b,page,errors,sent,foreign=run(p,{'width':1280,'height':800},{'editContacts':True},'ok',None)
    page.click('#contact-new');page.wait_for_selector('#contact-new-form')
    assert page.locator('#contact-new-form [name=assignToMe]').count()==0 and '¿Quién eres en esta subcuenta?' in page.inner_text('#contact-new-form');print('PASS si Ágora no sabe quién eres, no hay casilla y explica dónde elegirlo')
    assert not errors,errors;b.close()
    b,page,errors,sent,foreign=run(p,{'width':1280,'height':800},{'editContacts':False},'ok')
    assert page.locator('#contact-new').count()==0;print('PASS quien no puede editar fichas no ve «Nuevo contacto»')
    b.close()
print('PASS sin errores de pantalla')
