"""Opening and editing an opportunity, from the board and from the conversation panel."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')

PIPELINES={'pipelines':[
  {'id':'pipe-1','name':'Pipeline comercial','stages':[{'id':'stage-1','name':'Nuevo lead'},{'id':'stage-2','name':'Propuesta enviada'}]},
  {'id':'pipe-2','name':'Postventa','stages':[{'id':'stage-9','name':'Seguimiento'}]}],
 'assignees':[{'id':'seller-1','name':'Ana Responsable'},{'id':'seller-2','name':'Luis Comercial'}],
 'canEdit':True}
CARD={'id':'opp-1','name':'Proyecto Andreina','contactId':'c1','contactName':'Andreina','monetaryValue':2400.0,
      'pipelineId':'pipe-1','pipelineStageId':'stage-1','assignedTo':'seller-1','assignedName':'Ana Responsable','updatedAt':None}

with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    def session(viewport,can_edit=True):
        context=b.new_context(viewport=viewport,service_workers='block')
        page=context.new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        sent=[];state={'logged':False,'stage':'stage-1'}
        def api(route,request):
            path=request.url.split('/api/')[1];status=200
            if path=='me':
                if not state['logged']: status=401;data={'error':'Inicia sesión'}
                else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
            elif path=='login': state['logged']=True;data={'csrf':'t'}
            elif path.startswith('workspace/connections'):
                data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'}]}
            elif path.startswith('workspace/pipelines'): data=dict(PIPELINES,canEdit=can_edit)
            elif path.startswith('workspace/opportunity/'):
                sent.append({'path':path,'body':json.loads(request.post_data or '{}')})
                state['stage']=sent[-1]['body'].get('pipelineStageId',state['stage'])
                data={'state':'accepted','id':'opp-1'}
            elif path.startswith('workspace/board'):
                card=dict(CARD,pipelineStageId=state['stage'])
                stages=[{'id':'stage-1','name':'Nuevo lead','items':[card] if state['stage']=='stage-1' else [],'total':1 if state['stage']=='stage-1' else 0,'value':2400.0},
                        {'id':'stage-2','name':'Propuesta enviada','items':[card] if state['stage']=='stage-2' else [],'total':1 if state['stage']=='stage-2' else 0,'value':2400.0}]
                data={'pipelines':[{'id':'pipe-1','name':'Pipeline comercial'}],'pipelineId':'pipe-1','stages':stages,
                      'total':1,'restricted':False,'canEdit':can_edit,'partial':False}
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
        page.locator('[data-view="board"]').click()
        page.wait_for_selector('.board-card',state='attached')
        return page,errors,sent,context

    page,errors,sent,context=session({'width':1440,'height':900})
    page.locator('[data-edit-opportunity="opp-1"]').click()
    page.wait_for_selector('.opportunity-form',state='attached')
    form=page.locator('.opportunity-form')
    assert form.locator('[name="name"]').input_value()=='Proyecto Andreina'
    assert form.locator('[name="pipelineStageId"]').input_value()=='stage-1'
    assert form.locator('[name="assignedTo"]').input_value()=='seller-1'
    assert form.locator('[name="monetaryValue"]').input_value()=='2400'
    options=form.locator('[name="pipelineStageId"] option').all_inner_texts()
    assert options==['Nuevo lead','Propuesta enviada'], options
    print('PASS the form opens on the real state of the opportunity, with the account\'s own stages')

    page.select_option('.opportunity-form [name="pipelineId"]','pipe-2')
    page.wait_for_function("()=>document.querySelector('.opportunity-form [name=\"pipelineStageId\"]').options[0]?.text==='Seguimiento'")
    print('PASS choosing another pipeline offers only its stages')
    page.select_option('.opportunity-form [name="pipelineId"]','pipe-1')
    page.select_option('.opportunity-form [name="pipelineStageId"]','stage-2')
    page.select_option('.opportunity-form [name="assignedTo"]','seller-2')
    page.select_option('.opportunity-form [name="status"]','won')
    page.locator('.opportunity-form [data-save]').click()
    page.wait_for_function('()=>!document.querySelector(".opportunity-form")')
    assert len(sent)==1, sent
    assert sent[0]['path'].startswith('workspace/opportunity/edit'), sent[0]
    assert 'opportunityId=opp-1' in sent[0]['path'], sent[0]
    body=sent[0]['body']
    assert body['pipelineStageId']=='stage-2' and body['assignedTo']=='seller-2' and body['status']=='won', body
    assert 'name' not in body and 'monetaryValue' not in body, 'what was not touched must not be sent'
    assert body['requestId'], 'an edit must carry its own key so a repeat cannot apply twice'
    print('PASS only the changed stage, responsible and status are sent, under a repeat-proof key')
    page.wait_for_function("()=>document.body.innerText.includes('Oportunidad guardada')")
    assert 'Propuesta enviada' in page.locator('.kanban').inner_text()
    print('PASS the board reloads and the card shows in its new stage')

    body_text=page.locator('body').inner_text()
    for word in ['GoHighLevel','GHL','LeadConnector','pipelineId','opportunityId','assignedTo']:
        assert word not in body_text, f'technical wording reached the screen: {word}'
    print('PASS nothing technical is shown on the board')
    assert not errors, errors
    context.close()

    page,errors,sent,context=session({'width':390,'height':844})
    page.locator('[data-edit-opportunity="opp-1"]').click()
    page.wait_for_selector('.opportunity-form',state='attached')
    box=page.locator('.opportunity-form [data-save]').bounding_box()
    assert box['height']>=44, f'saving must be comfortable to tap: {box}'
    page.locator('.opportunity-form [data-cancel]').click()
    page.wait_for_function('()=>!document.querySelector(".opportunity-form")')
    assert sent==[], 'cancelling must send nothing'
    print('PASS on a 6.7" phone the form is tappable and cancelling writes nothing')
    assert not errors, errors
    context.close()

    page,errors,sent,context=session({'width':1440,'height':900},can_edit=False)
    assert page.locator('[data-edit-opportunity]').count()==0, 'a user without write permission is offered no editing'
    print('PASS a read-only user sees the board without any editing offered')
    assert not errors, errors
    context.close()
    b.close()

# --- the same form, reached from the conversation panel ------------------------
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    context=b.new_context(viewport={'width':1440,'height':900},service_workers='block')
    page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    sent=[];state={'logged':False,'opportunities':[]}
    def api(route,request):
        path=request.url.split('/api/')[1];status=200
        if path=='me':
            if not state['logged']: status=401;data={'error':'Inicia sesión'}
            else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
        elif path=='login': state['logged']=True;data={'csrf':'t'}
        elif path.startswith('workspace/connections'):
            data={'items':[{'id':1,'name':'Cuenta A','location_id':'a','status':'connected'}]}
        elif path.startswith('workspace/inbox'):
            data={'syncIntervalSeconds':120,'limit':20,'items':[{'id':'c1','contactName':'Raissa Moreno','lastMessageBody':'Hola','lastMessageType':'TYPE_WHATSAPP','contactId':'ct1','locationId':'a','unreadCount':0,'lastMessageDate':'2026-10-29T18:05:00Z'}],'total':1,'syncedAt':'2026-10-29 18:05:00','partial':False,'state':None}
        elif path.startswith('workspace/messages'): data={'items':[],'hasMore':False,'lastMessageId':None}
        elif path.startswith('workspace/contact-fields'): data={'items':[]}
        elif path.startswith('workspace/pipelines'): data=PIPELINES
        elif path.startswith('workspace/opportunity/'):
            sent.append({'path':path,'body':json.loads(request.post_data or '{}')})
            state['opportunities']=[{'id':'opp-new','name':sent[-1]['body'].get('name'),'status':'open','statusLabel':'Abierta','monetaryValue':1500.0,
                                     'pipelineId':'pipe-1','pipelineStageId':'stage-1','pipelineName':'Pipeline comercial','stageName':'Nuevo lead',
                                     'assignedTo':'seller-1','assignedName':'Ana Responsable','source':None,'updatedAt':None}]
            data={'state':'accepted','id':'opp-new'}
        elif path.startswith('workspace/opportunities'):
            items=state['opportunities']
            data={'active':items[0] if items else None,'items':items,'total':len(items),'restricted':False,'canEdit':True,'partial':False,'pipelineNamesAvailable':True}
        elif path.startswith('workspace/contact'):
            data={'contact':{'id':'ct1','name':'Raissa Moreno','phone':'+584142736522','email':'r@x.test','assignedName':None,'tags':[],'customFields':[]},'canEdit':True,'fields':[],'assignees':[],'channels':[]}
        elif path.startswith('workspace/activity'):
            data={'tasks':[],'notes':[],'appointments':[],'appointmentsVisible':True,'canEdit':True,'partial':{}}
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
    page.wait_for_selector('[data-live-id]',state='attached')
    page.locator('[data-live-id]').first.click()
    page.locator('#live-contact-toggle').click()
    page.wait_for_selector('[data-new-opportunity]',state='attached')
    print('PASS the conversation panel offers opening an opportunity for the contact')
    page.locator('[data-new-opportunity]').click()
    page.wait_for_selector('.opportunity-form',state='attached')
    page.locator('.opportunity-form [name="name"]').fill('Proyecto Raissa')
    page.locator('.opportunity-form [name="monetaryValue"]').fill('1500')
    page.locator('.opportunity-form [data-save]').click()
    page.wait_for_function('()=>!document.querySelector(".opportunity-form")')
    assert len(sent)==1, sent
    assert sent[0]['path'].startswith('workspace/opportunity/new') and 'contactId=ct1' in sent[0]['path'], sent[0]
    body=sent[0]['body']
    assert body['name']=='Proyecto Raissa' and body['monetaryValue']==1500 and body['pipelineId']=='pipe-1' and body['pipelineStageId']=='stage-1', body
    assert body['status']=='open' and body['requestId'], body
    print('PASS it is opened for that contact with the chosen pipeline and stage, under its own key')
    page.wait_for_function("()=>document.body.innerText.includes('Proyecto Raissa')")
    assert 'Pipeline comercial' in page.locator('#live-contact-panel').inner_text()
    print('PASS the panel reloads and shows the new opportunity with its real stage')
    assert page.locator('[data-edit-opportunity="opp-new"]').count()==1
    print('PASS and offers editing the one it shows')
    assert not errors, errors
    context.close();b.close()
