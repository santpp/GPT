"""Contactos de un vendedor: el total real de los suyos, los más recientes primero y «Ver más»."""
import json,os
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
asked=[]
def api(route):
    url=route.request.url;path=url.split('/api/')[1].split('?')[0]
    if path=='me':data={'user':{'role':'team','email':'maria@example.test','identity_type':'external','capabilities':{'viewConversations':True,'viewContacts':True,'assignedDataOnly':True}},'csrf':'t','passkeys':0}
    elif path=='workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
    elif path=='workspace/contacts':
        q=parse_qs(urlparse(url).query);page=int(q.get('page',['1'])[0]);asked.append(page)
        items=[{'id':f'c{page}-{i}','name':f'Persona {page}-{i}','phone':'+58414','tags':[]} for i in range(50)]
        data={'items':items,'total':120,'restricted':True,'exact':True,'partial':True,'page':page,'hasMore':page<3}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.route('**/api/**',api);page.goto(BASE+'/contactos');page.wait_for_selector('.record-row',state='attached')
    fb=page.locator('#contact-feedback').inner_text()
    assert fb=='120 contactos asignados a ti · se muestran 50, los más recientes primero',fb
    print('PASS the seller sees their real total, not the ones that happened to be among the latest of the account')
    page.locator('#contact-more').click()
    page.wait_for_function("document.querySelectorAll('.record-row').length===100")
    assert asked[-1]==2 and 'se muestran 100' in page.locator('#contact-feedback').inner_text()
    page.locator('#contact-more').click()
    page.wait_for_function("document.querySelectorAll('.record-row').length===150")
    assert page.locator('#contact-more').count()==0,'no «Ver más» when everything is shown'
    print('PASS «Ver más» adds the next page and disappears at the end')
    assert not errors,errors
    b.close()
