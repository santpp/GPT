"""The contacts register: list, full record and editing it, on desktop and on a 6.7" phone."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')

CONTACT={'id':'c1','name':'Andreina Del Nogal','firstName':'Andreina','lastName':'Del Nogal','email':'a@x.test','phone':'+584142736522',
 'companyName':'Agora','website':'https://agora.test','address1':'Calle 1','city':'Caracas','state':'DC','postalCode':'1010','country':'VE',
 'address':'Calle 1, Caracas, DC, 1010, VE','timezone':'America/Caracas','source':'Instagram','dateOfBirth':'1990-04-12','dnd':False,
 'assignedTo':'u1','assignedName':'Maria Carvallo','tags':['VIP'],
 'customFields':[{'id':'f1','name':'Presupuesto','value':'1200','values':[]}]}
FIELDS=[{'id':'f1','name':'Presupuesto','dataType':'NUMERICAL','options':[],'placeholder':None,'position':0},
        {'id':'f2','name':'Interés','dataType':'SINGLE_OPTIONS','options':['Alto','Bajo'],'placeholder':None,'position':1}]

with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    def session(viewport):
        context=b.new_context(viewport=viewport,service_workers='block')
        page=context.new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        saved=[];state={'logged':False}
        def api(route,request):
            path=request.url.split('/api/')[1]
            status=200
            if path=='me':
                if not state['logged']: status=401;data={'error':'Inicia sesión'}
                else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
            elif path=='login': state['logged']=True;data={'csrf':'t'}
            elif path.startswith('workspace/connections'):
                data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'}]}
            elif path.startswith('workspace/contacts'):
                data={'items':[{'id':'c1','name':'Andreina Del Nogal','email':'a@x.test','phone':'+584142736522','companyName':None,'assignedName':'Maria Carvallo','tags':['VIP'],'dateAdded':None}],
                      'total':1,'query':None,'restricted':False,'partial':False}
            elif path.startswith('workspace/record'):
                if request.method=='POST':
                    assert request.headers.get('x-csrf-token')=='t','a change carries the session token'
                    saved.append(json.loads(request.post_data or '{}'));data={'updated':True}
                else:
                    data={'contact':dict(CONTACT),'canEdit':True,'fields':FIELDS,'assignees':[{'id':'u1','name':'Maria Carvallo'}]}
            elif path.startswith('workspace/contact-conversation'):
                assert request.method=='POST' and request.headers.get('x-csrf-token')=='t' and json.loads(request.post_data)=={'contactId':'c1'}
                state['opened']=True
                data={'conversation':{'id':'conv-new','contactId':'c1','locationId':'a','contactName':'Andreina Del Nogal','unreadCount':0}}
            elif path.startswith('workspace/messages'):
                data={'items':[],'hasMore':False}
            elif path.startswith('workspace/contact?'):
                data={'contact':dict(CONTACT),'canEdit':False,'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None}]}
            elif path.startswith('workspace/inbox'):
                data={'syncIntervalSeconds':120,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None}
            else: data={'items':[]}
            route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/')
        page.wait_for_selector('#signin:not([hidden])')
        page.locator('.admin-login summary').click()
        page.locator('#login-form [name="email"]').fill('admin@example.test')
        page.locator('#login-form [name="password"]').fill('secret')
        page.locator('#login-form button').click()
        page.wait_for_selector('#app:not([hidden])')
        page.locator('[data-view="contacts"]').click()
        page.wait_for_selector('.record-row',state='attached')
        return page,errors,saved,context

    page,errors,saved,context=session({'width':1440,'height':900})
    page.locator('.record-row').first.click()
    page.wait_for_selector('.record-header',state='attached')
    record=page.locator('#contact-record').inner_text()
    for expected in ['Andreina Del Nogal','a@x.test','+584142736522','Calle 1, Caracas','America/Caracas','Instagram','Maria Carvallo','Presupuesto','VIP']:
        assert expected in record, f'{expected} missing from the record: {record}'
    print('PASS the record shows every field the account holds')
    body=page.locator('body').inner_text()
    for word in ['GoHighLevel','GHL','LeadConnector','locationId','contactId','customFields']:
        assert word not in body, f'technical wording reached the screen: {word}'
    print('PASS nothing technical is shown on the contacts screen')

    page.locator('#record-edit').click()
    page.wait_for_selector('#live-contact-form',state='attached')
    form=page.locator('#live-contact-form').inner_text()
    assert 'Presupuesto' in form and 'Interés' in form, form
    assert page.locator('[data-field="f2"]').count()==1, 'a field with no value yet must still be offered'
    print('PASS editing offers every field the account defines, filled in or not')

    page.locator('#live-contact-form [name="companyName"]').fill('Ágora Digital')
    page.select_option('[data-field="f2"]','Alto')
    page.locator('#live-new-tag').fill('Cliente')
    page.locator('#live-add-tag').click()
    page.locator('[data-drop-tag="VIP"]').click()
    page.locator('#live-save-contact').click()
    page.wait_for_function('()=>document.querySelector(".record-header")!==null')
    assert len(saved)==1, saved
    sent=saved[0]
    assert sent['fields']['companyName']=='Ágora Digital', sent
    assert sent['fields']['customFields']==[{'id':'f2','value':'Alto'}], sent
    assert 'name' not in sent['fields'] and 'email' not in sent['fields'], 'untouched fields must not be sent'
    assert sent['addTags']==['Cliente'] and sent['removeTags']==['VIP'], sent
    assert sent['requestId'], 'an edit must carry its own key so a repeat cannot duplicate it'
    print('PASS only what changed is sent, tags apart, under a repeat-proof key')
    page.wait_for_selector('#record-message')
    page.locator('#record-message').click()
    page.wait_for_selector('.live-chat-header h2:has-text("Andreina Del Nogal")')
    page.wait_for_selector('#live-reply')
    assert page.url.endswith('/conversations/conv-new'),page.url
    print('PASS «Enviar mensaje» in the record opens that contact\'s conversation, ready to write')
    assert not errors, errors
    context.close()

    page,errors,saved,context=session({'width':390,'height':844})
    assert page.locator('#contact-record').is_hidden(), 'the record must not take the phone screen before one is chosen'
    page.locator('.record-row').first.click()
    page.wait_for_selector('.record-header',state='attached')
    assert page.locator('.records-list').is_hidden(), 'the list steps aside while a record is open on a phone'
    box=page.locator('#record-edit').bounding_box()
    assert box['height']>=44, f'the edit button must be comfortable to tap: {box}'
    page.locator('#record-back').click()
    page.wait_for_selector('.record-row',state='visible')
    assert page.locator('#contact-record').is_hidden()
    print('PASS on a 6.7" phone the list and the record take turns, and the actions are tappable')
    assert not errors, errors
    context.close()
    b.close()
