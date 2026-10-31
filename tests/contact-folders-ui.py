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
    legends=page.locator('#live-contact-form legend').all_inner_texts()
    assert legends[-5:-1]==['Datos personales','Ventas','Otros campos','Etiquetas'][0:4] or legends[-4:]==['Datos personales','Ventas','Otros campos','Etiquetas'],legends
    print('PASS the editor splits the fields into the same folders, with the rest at the end')
    page.locator('#live-add-tag').click();page.wait_for_selector('.tag-option')
    options=page.locator('.tag-option').all_inner_texts()
    assert options==['lead frío','vip'],options
    print('PASS pressing Añadir offers the account\'s existing tags, without the ones the contact already has')
    page.fill('#live-new-tag','vi');page.wait_for_function("document.querySelectorAll('.tag-option').length===1")
    page.locator('.tag-option').click()
    assert 'vip' in page.locator('#live-tag-list').inner_text() and page.locator('.tag-option').count()==1
    print('PASS typing filters them, and tapping one adds it to the contact')
    page.screenshot(path='/tmp/contact-edit.png',full_page=True)
    assert not errors,errors
    b.close()
