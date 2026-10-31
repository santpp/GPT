"""Connected inbox regression checks with isolated, explicit mock data."""
import json
import os
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for width in [390,1440]:
        context=browser.new_context(viewport={'width':width,'height':844},service_workers='block')
        page=context.new_page();errors=[];calls=[];fail=[True];held=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        reads=[]
        inbox_queries=[]
        saved=[]
        contact={'name':'Ángeles García','phone':'+34123456789','email':'angeles@example.test','source':'Formulario web','assignedName':'Ana Responsable','lastActivity':'2026-10-28T11:00:00Z','tags':['Interesada'],'customFields':[{'id':'f1','label':'Campo personalizado','value':'Precio'}],'address1':'Calle Uno','city':'Caracas','website':'https://example.test','dnd':False,'assignedTo':'u1','dateOfBirth':'1990-04-12'}
        actions=[]
        tasks=[{'id':'t1','title':'Confirmar disponibilidad','body':'Llamar por la tarde','dueDate':'2026-11-05T10:00:00Z','completed':False,'assignedName':'Ana Responsable'}]
        notes=[{'id':'n1','body':'Pidió presupuesto','dateAdded':'2026-10-28T10:00:00Z','authorName':'Ana Responsable'}]
        def api(route):
            path=route.request.url.split('/api/')[1];calls.append(path);status=200
            if path=='me':data={'user':{'role':'administrator'},'csrf':'test'}
            elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta uno'},{'id':2,'name':'Cuenta dos'}]}
            elif path.startswith('workspace/inbox?'):
                import urllib.parse as up
                q=dict(up.parse_qsl(path.split('?',1)[1]))
                inbox_queries.append(q)
                rows=[{'id':'c1','contactName':'Ángeles García','lastMessageBody':'Texto reciente','lastMessageType':'TYPE_INSTAGRAM','unreadCount':3},
                      {'id':'c2','contactName':'Ana','lastMessageBody':'Otro mensaje','lastMessageType':'TYPE_WHATSAPP','unreadCount':0}] if q.get('connectionId')=='1' else []
                # The account resolves the filters, exactly as the server does.
                if q.get('status')=='unread':rows=[r for r in rows if r['unreadCount']>0]
                if q.get('channel'):rows=[r for r in rows if r['lastMessageType']==q['channel']]
                if q.get('query'):
                    import unicodedata
                    plain=lambda t:''.join(c for c in unicodedata.normalize('NFD',t.lower()) if unicodedata.category(c)!='Mn')
                    rows=[r for r in rows if plain(q['query']) in plain(r['contactName'])]
                data={'items':rows,'total':40 if rows else 0,'partial':True,'syncedAt':'2026-10-28T19:00:00Z','stale':False,'reason':None,
                      'unread':sum(r['unreadCount'] for r in rows),'channels':{'TYPE_INSTAGRAM':'Instagram','TYPE_WHATSAPP':'WhatsApp'},'limit':20}
            elif path.startswith('workspace/read'):
                reads.append(path);data={'read':True}
            elif path.startswith('workspace/messages?'):
                if 'conversationId=c2' in path:
                    held.append(route);return
                if 'lastMessageId=m2' in path and fail[0]:
                    fail[0]=False;status=429;data={'error':'Consulta pausada','retryAfter':1}
                elif 'lastMessageId=m2' in path:
                    data={'items':[{'id':'m1','body':'Mensaje anterior','dateAdded':'2026-10-27T10:00:00Z','direction':'inbound'},{'id':'m2','body':'Texto reciente','dateAdded':'2026-10-28T10:00:00Z'}],'hasMore':False}
                else:data={'items':[{'id':'m2','body':'Texto reciente','dateAdded':'2026-10-28T10:00:00Z'},{'id':'m3','body':'<script>window.injected=true</script>','direction':'outbound','dateAdded':'2026-10-28T11:00:00Z','attachments':['https://cdn.example.test/fotos/propuesta.png','javascript:alert(1)','http://inseguro.example.test/x.pdf','https://cdn.example.test/docs/contrato.pdf']}],'hasMore':True,'lastMessageId':'m2'}
            elif path.startswith('workspace/activity/contact'):
                saved.append(route.request.post_data_json)
                contact['name']='Ángeles G. Nueva';contact['tags']=['Interesada','Prioritaria']
                data={'state':'accepted','id':'c1'}
            elif path.startswith('workspace/contact?'):
                data={'contact':dict(contact),'canEdit':True,'channels':[],
                      'fields':[{'id':'f1','name':'Tipo de consulta','dataType':'SINGLE_OPTIONS','options':['Precio','Soporte'],'placeholder':None,'position':1},
                                {'id':'f2','name':'Presupuesto','dataType':'NUMERICAL','options':[],'placeholder':'En euros','position':2},
                                {'id':'f3','name':'Canales','dataType':'MULTIPLE_OPTIONS','options':['Email','Teléfono'],'placeholder':None,'position':3},
                                {'id':'f4','name':'Acepta promociones','dataType':'CHECKBOX','options':[],'placeholder':None,'position':4}],
                      'assignees':[{'id':'u1','name':'Ana Responsable'},{'id':'u2','name':'David López'}]}
            elif path.startswith('workspace/contact-fields?'):
                data={'items':[]}
            elif path.startswith('workspace/activity/task-state'):
                assert route.request.method=='POST' and route.request.headers['x-csrf-token']=='test'
                payload=route.request.post_data_json
                assert len(payload['requestId'])>=16, 'a quick action always carries a request key'
                actions.append(('task-state',payload['taskId'],payload['completed']))
                tasks[0]['completed']=payload['completed'];data={'state':'accepted','id':payload['taskId']}
            elif path.startswith('workspace/activity/note'):
                payload=route.request.post_data_json
                assert route.request.headers['x-csrf-token']=='test' and len(payload['requestId'])>=16
                actions.append(('note',payload['body'],payload['requestId']))
                notes.insert(0,{'id':'n9','body':payload['body'],'dateAdded':'2026-10-29T10:00:00Z','authorName':'Ana Responsable'})
                data={'state':'accepted','id':'n9'}
            elif path.startswith('workspace/activity'):
                data={'tasks':tasks,'notes':notes,'appointments':[{'id':'a1','title':'Demo de proyecto','startTime':'2026-11-02T10:00:00Z','statusLabel':'Confirmada','assignedName':'Ana Responsable','address':'Videollamada'}],
                      'appointmentsVisible':True,'canEdit':True,'partial':{'tasks':False,'notes':False,'appointments':False}}
            elif path.startswith('workspace/opportunities?'):
                active={'id':'o1','name':'Proyecto de Ángeles','pipelineName':'Pipeline comercial','stageName':'Propuesta enviada','statusLabel':'Abierta','monetaryValue':2400,'assignedName':'Ana Responsable','source':'Formulario web'}
                other={'id':'o2','name':'<script>window.injected=true</script>','pipelineName':'Pipeline comercial','stageName':'Nuevo lead','statusLabel':'Ganada','monetaryValue':900.5,'assignedName':None,'source':None}
                data={'active':active,'items':[active,other],'total':2,'restricted':False,'partial':False,'pipelineNamesAvailable':True}
            else:data={'items':[]}
            route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(os.getenv('TEST_BASE_URL','http://127.0.0.1:8080/'))
        page.wait_for_selector('[data-live-id]')
        assert page.locator('button[data-view="inbox"]').get_attribute('class').endswith('active')
        assert page.locator('#inbox-view').is_hidden()
        assert 'Instagram' in page.locator('#live-list').inner_text()
        assert 'TYPE_' not in page.locator('#live-list').inner_text()
        # Search and filters are answered by the account, not by the loaded window.
        page.locator('#live-search').fill('angeles')
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===1")
        assert inbox_queries[-1].get('query')=='angeles', inbox_queries[-1]
        page.locator('#live-search').fill('')
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===2")
        page.locator('[data-live-filter="unread"]').click()
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===1")
        assert inbox_queries[-1].get('status')=='unread', inbox_queries[-1]
        page.wait_for_selector('#live-channel-select option[value="TYPE_WHATSAPP"]',state='attached')
        page.select_option('#live-channel-select','TYPE_WHATSAPP')
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===0")
        assert inbox_queries[-1].get('channel')=='TYPE_WHATSAPP' and inbox_queries[-1].get('status')=='unread', inbox_queries[-1]
        page.select_option('#live-channel-select','')
        page.locator('[data-live-filter="all"]').click()
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===2")
        assert page.locator('#nav-unread').inner_text()=='3', 'the menu shows what the account reported unread'
        page.locator('[data-live-id="c1"]').click()
        page.wait_for_selector('#live-log .bubble')
        page.wait_for_selector('#live-contact-panel h2',state='attached')
        assert 'Ana Responsable' in page.locator('#live-contact-panel').inner_text()
        page.wait_for_selector('#live-contact-panel .live-opportunity',state='attached')
        opportunity=page.locator('#live-contact-panel .live-profile-opportunities').evaluate('(n)=>n.textContent')
        assert 'Proyecto de Ángeles' in opportunity and 'Pipeline comercial' in opportunity and 'Propuesta enviada' in opportunity
        assert 'Abierta' in opportunity and '2.400' in opportunity and 'Ana Responsable' in opportunity
        assert '900,5' in page.locator('#live-contact-panel .live-opportunity-all').evaluate('(n)=>n.textContent'), 'Spanish decimal separator'
        assert page.locator('#live-contact-panel .live-opportunity-all').evaluate('(n)=>!n.open'), 'the other opportunities stay collapsed'
        assert 'Ganada' in page.locator('#live-contact-panel .live-opportunity-all').evaluate('(n)=>n.textContent')
        assert page.locator('#live-contact-panel script').count()==0 and page.evaluate('window.injected === undefined')
        page.wait_for_selector('#live-contact-panel .live-task',state='attached')
        activity=page.locator('#live-contact-panel .live-profile-activity').evaluate('(n)=>n.textContent')
        assert 'Confirmar disponibilidad' in activity and 'Pidió presupuesto' in activity and 'Demo de proyecto' in activity
        assert 'Confirmada' in activity and 'Videollamada' in activity
        page.locator('#live-contact-panel [data-task="t1"]').evaluate('(n)=>n.click()')
        page.wait_for_function("document.querySelector('#live-activity-status')?.textContent.includes('Tarea actualizada')")
        assert actions[-1]==('task-state','t1',True), 'completing a task sends the documented state'
        page.evaluate("()=>{document.querySelectorAll('#live-contact-panel .live-quick-actions details').forEach(d=>d.open=true);document.querySelector('#live-note-body').value='Nota escrita desde Ágora'}")
        page.locator('#live-note-form button').evaluate('(n)=>n.click()')
        page.wait_for_function("document.querySelector('#live-activity-status')?.textContent.includes('Nota guardada')")
        assert actions[-1][0]=='note' and actions[-1][1]=='Nota escrita desde Ágora'
        assert 'Nota escrita desde Ágora' in page.locator('#live-contact-panel .live-notes').evaluate('(n)=>n.textContent')

        # Every field of the record, and its tags, can be edited.
        page.locator('#live-edit-contact').evaluate('(n)=>n.click()')
        page.wait_for_selector('#live-contact-form',state='attached')
        page.locator('#live-contact-form [name="name"]').evaluate("(n)=>n.value='Ángeles G. Nueva'")
        # Every field the account defines is offered with its own control.
        assert page.locator('#live-contact-form [name="address1"]').input_value()=='Calle Uno', 'address parts are shown, not only the joined line'
        assert page.locator('#live-contact-form [name="dateOfBirth"]').input_value()=='1990-04-12'
        assert page.locator('#live-contact-form [name="assignedTo"]').input_value()=='u1'
        assert page.locator('#live-contact-form [data-field="f1"]').evaluate('(n)=>n.tagName')=='SELECT', 'a choice field is a select'
        assert page.locator('#live-contact-form [data-field="f1"]').input_value()=='Precio'
        assert page.locator('#live-contact-form [data-field="f2"]').evaluate('(n)=>n.type')=='number', 'a numeric field asks for a number'
        assert page.locator('#live-contact-form [data-field="f2"]').input_value()=='', 'a defined field with no value is offered empty'
        assert page.locator('#live-contact-form [data-field="f3"] [data-option]').count()==2, 'a multiple-choice field lists its options'
        assert page.locator('#live-contact-form [data-field="f4"]').evaluate('(n)=>n.type')=='checkbox'
        page.locator('#live-contact-form [data-field="f1"]').evaluate("(n)=>n.value='Soporte'")
        page.locator('#live-contact-form [data-field="f2"]').evaluate("(n)=>n.value='2400'")
        page.locator('#live-contact-form [data-field="f3"] [data-option="Email"]').evaluate('(n)=>n.click()')
        page.locator('#live-contact-form [data-field="f4"]').evaluate('(n)=>n.click()')
        page.locator('#live-contact-form [name="dnd"]').evaluate('(n)=>n.click()')
        page.locator('#live-contact-form [name="assignedTo"]').evaluate("(n)=>n.value='u2'")
        page.locator('#live-new-tag').evaluate("(n)=>n.value='Prioritaria'")
        page.locator('#live-add-tag').evaluate('(n)=>n.click()')
        page.locator('#live-contact-form [data-drop-tag="Interesada"]').evaluate('(n)=>n.click()')
        page.locator('#live-save-contact').evaluate('(n)=>n.click()')
        page.wait_for_function("!document.querySelector('#live-contact-form')")
        assert len(saved)==1, saved
        sent=saved[0]
        assert sent['fields']['name']=='Ángeles G. Nueva'
        assert sent['fields']['dnd'] is True and sent['fields']['assignedTo']=='u2'
        assert sent['fields']['customFields']==[{'id':'f1','value':'Soporte'},{'id':'f2','value':'2400'},{'id':'f3','value':['Email']},{'id':'f4','value':True}], sent['fields']['customFields']
        assert 'address1' not in sent['fields'], 'an untouched field is never rewritten'
        assert sent['addTags']==['Prioritaria'] and sent['removeTags']==['Interesada']
        assert 'tags' not in sent['fields'], 'tags never ride in the update field'
        assert len(sent['requestId'])>=16
        assert 'Ángeles G. Nueva' in page.locator('#live-contact-panel').evaluate('(n)=>n.textContent')
        if width==390:
            page.locator('#live-contact-toggle').click();assert page.locator('#live-contact-panel').is_visible();page.locator('#live-profile-close').click()
        # Attachments are shown; only the account's https links are followed.
        page.wait_for_selector('#live-log .att-image, #live-log .att-file',state='attached')
        # A picture that cannot load becomes a link to the original, so read both forms.
        links=page.locator('#live-log .att-image, #live-log .att-file').evaluate_all('ns=>ns.map(n=>n.dataset.view||n.getAttribute("href"))')
        assert links==['https://cdn.example.test/fotos/propuesta.png','https://cdn.example.test/docs/contrato.pdf'], links
        assert page.locator('#live-log .att-image img, #live-log .att-file.broken').count()==1, 'an image is shown inline, or says it could not load'
        assert 'contrato.pdf' in page.locator('#live-log').evaluate('(n)=>n.textContent'), 'a file shows its own name'
        assert page.locator('#live-log a[href^="javascript:"]').count()==0 and page.locator('#live-log a[href^="http:"]').count()==0
        # Opening an unread conversation clears it in the account.
        assert reads and 'conversationId=c1' in reads[0], reads
        assert page.locator('#live-log script').count()==0
        assert page.evaluate('window.injected === undefined')
        page.locator('#live-older').click()
        page.wait_for_selector('#live-retry')
        assert page.locator('#live-log .bubble').count()==2
        page.locator('#live-retry').click()
        page.wait_for_function("document.querySelectorAll('#live-log .bubble').length===3")
        assert 'Mensaje anterior' in page.locator('#live-log .bubble').first.inner_text()
        assert page.locator('#live-older').is_hidden()
        assert page.evaluate('document.documentElement.scrollWidth')==width
        if width==390:
            assert page.locator('.live-sidebar').is_hidden()
            page.locator('#live-back').click()
            assert page.locator('.live-sidebar').is_visible()
        page.screenshot(path=f'/tmp/agora-030-{width}.png',full_page=True)
        page.locator('[data-live-filter="all"]').click()
        page.locator('[data-live-id="c2"]').click()
        page.wait_for_timeout(100)
        if width==390:page.locator('#live-back').click()
        page.locator('[data-live-id="c1"]').click()
        page.wait_for_selector('#live-log .bubble')
        assert held
        held.pop().fulfill(status=200,content_type='application/json',body=json.dumps({'items':[{'id':'stale','body':'WRONG CONVERSATION'}],'hasMore':False}))
        page.wait_for_timeout(100)
        assert 'WRONG CONVERSATION' not in page.locator('#live-messages').inner_text()
        if width==390:page.locator('#live-back').click()
        page.locator('#account').select_option('2')
        page.wait_for_function("document.querySelectorAll('[data-live-id]').length===0")
        assert page.locator('#live-log').count()==0
        assert not errors,errors
        context.close()
        print(f'PASS {width}px: default real inbox, local search/filter, channel names, pagination/retry/dedup, XSS, mobile back and account change')
    browser.close()
