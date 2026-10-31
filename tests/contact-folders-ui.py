"""Ficha del contacto: los campos personalizados vienen por carpetas y «Añadir» ofrece las etiquetas que ya existen."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
FIELDS=[{'id':'f2','name':'Ciudad natal','dataType':'TEXT','options':[],'folder':'Datos personales','position':1},
        {'id':'f1','name':'Presupuesto','dataType':'MONETORY','options':[],'folder':'Ventas','position':2},
        {'id':'f3','name':'Interés','dataType':'TEXT','options':[],'folder':None,'position':1}]
saved=[]
def api(route):
    req=route.request;path=urlparse(req.url).path
    if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'viewContacts':True,'editContacts':True}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
    elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Ana Ruiz','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Hola'}],'total':1}
    elif path=='/api/workspace/contact':data={'contact':{'id':'k0','name':'Ana Ruiz','tags':['cliente'],'customFields':[{'id':'f1','name':'Presupuesto','value':'500'},{'id':'f2','name':'Ciudad natal','value':'Quito'}]},'channels':[],'canEdit':True,'fields':FIELDS,'assignees':[]}
    elif path=='/api/workspace/tags':data={'items':['cliente','lead frío','vip']}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':900},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
    page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()');page.wait_for_selector('.bubble, #live-contact-toggle')
    page.evaluate("document.querySelector('#live-contact-toggle').click()")
    page.wait_for_selector('.folder-title')
    titles=page.locator('.folder-title').all_inner_texts()
    assert titles==['Datos personales','Ventas'],titles
    print('PASS the contact card shows its custom values grouped by the account\'s folders')
    page.locator('#live-edit-contact').click();page.wait_for_selector('#live-contact-form')
    names=page.locator('#live-contact-form details[data-fold] > summary > span').all_inner_texts()
    assert names==['Datos personales','Ventas','Otros campos'],names
    assert page.locator('details[data-fold]').nth(0).get_attribute('open') is not None and page.locator('details[data-fold]').nth(1).get_attribute('open') is None,'only the first folder starts open'
    assert '1 de 1' in page.locator('details[data-fold] summary small').first.inner_text()
    print('PASS the editor splits the fields into the same folders, with the rest at the end')
    page.locator('#live-add-tag').click();page.wait_for_selector('.tag-option')
    options=page.locator('.tag-option').all_inner_texts()
    assert options==['lead frío','vip'],options
    print('PASS pressing Añadir offers the account\'s existing tags, without the ones the contact already has')
    page.fill('#live-new-tag','vi');page.wait_for_function("document.querySelectorAll('.tag-option').length===1")
    page.locator('.tag-option').click()
    assert 'vip' in page.locator('#live-tag-list').inner_text() and page.locator('.tag-option').count()==1
    print('PASS typing filters them, and tapping one adds it to the contact')
    # cambios marcados, contador y aviso al descartar
    assert page.locator('#live-save-contact').inner_text()=='Guardar ficha (1)',page.locator('#live-save-contact').inner_text()
    page.locator('details[data-fold]').nth(0).locator('input').fill('Cuenca')
    assert page.locator('.contact-editor label.changed').count()>=1 and page.locator('#live-save-contact').inner_text()=='Guardar ficha (2)'
    page.screenshot(path='/tmp/contact-edit2.png')
    print('PASS changed fields are marked and the save button counts the changes')
    page.locator('[data-cancel-edit]').click()
    assert page.locator('#live-contact-form').count()==1 and 'sin guardar' in page.locator('#live-contact-status').inner_text()
    page.locator('[data-cancel-edit]').click();page.wait_for_selector('#live-contact-form',state='detached')
    print('PASS discarding changes asks for a second tap, then leaves without saving')
    page.screenshot(path='/tmp/contact-edit.png',full_page=True)
    # con muchos campos aparece el buscador
    many=[{'id':'g%d'%i,'name':'Campo %d'%i,'dataType':'TEXT','options':[],'folder':'Datos personales' if i<6 else 'Ventas','position':i} for i in range(12)]
    FIELDS[:]=many
    page.evaluate("document.querySelector('#live-edit-contact')?.click()")
    page.reload();page.wait_for_selector('[data-live-id]');page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
    page.wait_for_selector('#live-contact-toggle',state='attached');page.evaluate("document.querySelector('#live-contact-toggle').click()")
    page.locator('#live-edit-contact').click();page.wait_for_selector('#live-field-find')
    page.fill('#live-field-find','campo 9')
    visible=page.locator('details[data-fold]:not([hidden]) .contact-grid > *:not([hidden])').count()
    assert visible==1,visible
    print('PASS with many fields a finder narrows them down, opening the folder that matches')
    assert not errors,errors
    b.close()
