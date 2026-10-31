"""«¿Venta ganada?» en el chat y las cifras personales del tablero."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for width in [390,1440]:
        page=b.new_context(viewport={'width':width,'height':900},service_workers='block').new_page()
        errors=[];sent=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def api(route):
            req=route.request;path=urlparse(req.url).path
            if path=='/api/me':data={'user':{'role':'team','email':'ana@example.test','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True,'viewOpportunities':True,'editOpportunities':True,'viewContacts':True}},'csrf':'t','passkeys':0}
            elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
            elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Raissa Moreno','lastMessageType':'TYPE_WHATSAPP','lastMessageBody':'Lo quiero'}],'total':1}
            elif path=='/api/workspace/messages':data={'items':[],'hasMore':False}
            elif path=='/api/workspace/opportunities':data={'items':[{'id':'opp-1','name':'Curso de inglés','status':'open','monetaryValue':800.0,'stageName':'Propuesta'},{'id':'opp-old','name':'Antigua','status':'won','monetaryValue':100.0}],'active':{'id':'opp-1','name':'Curso de inglés'},'canEdit':True}
            elif path=='/api/workspace/pipelines':data={'pipelines':[{'id':'pipe-1','name':'Ventas','stages':[{'id':'s1','name':'Nuevo'}]}],'assignees':[],'canEdit':True}
            elif path.startswith('/api/workspace/opportunity/'):
                assert req.headers['x-csrf-token']=='t';sent.append((path,req.post_data_json));data={'state':'accepted','id':'opp-1'}
            elif path=='/api/workspace/dashboard':
                assert 'tz=' in req.url,'the dashboard is told the device time zone'
                data={'restricted':True,'conversations':{'total':4,'unread':1},'contacts':None,'leads':{'today':3,'mine':27,'linked':True},'opportunities':None,'readAt':'2026-10-31T10:00:00+00:00'}
            elif path=='/api/workspace/board-month':data={'error':'x'} if False else {'period':'2026-10','restricted':True,'canEdit':False,'settings':{},'sales':{'total':800,'closings':1,'counted':1,'partial':False},'profitability':{},'team':[],'goal':{},'missing':[],'mine':{'closings':4,'sold':2150.5}}
            else:data={'items':[]}
            route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
        page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
        page.wait_for_selector('#live-won')
        assert page.locator('#live-won').is_visible()
        page.locator('#live-won').click();page.wait_for_selector('#win-form')
        options=page.locator('#win-form [name=opportunity] option').all_inner_texts()
        assert options[0].startswith('Curso de inglés') and not any('Antigua' in o for o in options) and options[-1]=='+ Nueva venta',options
        assert page.locator('#win-form [name=value]').input_value()=='800'
        print('PASS «¿Venta ganada?» offers the contact\'s open sales, the active one first, with its value')
        page.fill('#win-form [name=value]','1250');page.locator('#win-form button.primary').click()
        page.wait_for_function("!document.querySelector('#action-dialog').open")
        path,body=sent[-1]
        assert path.endswith('/edit') and body['status']=='won' and body['monetaryValue']==1250 and body['claimIfUnassigned'] is True and len(body['requestId'])>=16,(path,body)
        assert 'Venta ganada' in page.locator('#toast').inner_text()
        print('PASS it is marked as won with the value written, and the person is told it counts this month')
        page.locator('#live-won').click();page.wait_for_selector('#win-form')
        page.select_option('#win-form [name=opportunity]','new')
        assert page.locator('#win-new').is_visible() and page.locator('#win-form [name=name]').input_value()=='Venta · Raissa Moreno'
        page.fill('#win-form [name=value]','300');page.locator('#win-form button.primary').click()
        page.wait_for_function("!document.querySelector('#action-dialog').open")
        path,body=sent[-1]
        assert path.endswith('/new') and body['status']=='won' and body['pipelineId']=='pipe-1' and body['monetaryValue']==300,(path,body)
        print('PASS a sale with no opportunity yet is created already won')
        page.locator('[data-view="dashboard"]').click()
        page.wait_for_selector('.personal-tiles')
        tiles=page.locator('.personal-tiles').inner_text()
        assert 'Leads de hoy' in tiles and '3' in tiles and 'Leads asignados a mí' in tiles and '27' in tiles and 'Mis ventas del mes' in tiles and '4' in tiles,tiles
        print('PASS the board shows today\'s leads, my leads and my sales won this month')
        assert page.locator('#menu-version').inner_text().startswith('Ágora v') or width==390
        assert not errors,errors
    print('PASS no JS errors')
    b.close()
