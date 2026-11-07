"""Embudos: etapas plegables, tarjetas compactas, filtros, búsqueda y «Mover a…»."""
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
                cards=[dict(CARD,pipelineStageId=state['stage']),
                       {'id':'opp-2','name':'Web Tienda','contactId':'c2','contactName':'Carlos Pérez','contactEmail':'carlos@ejemplo.com','contactPhone':'+1 809 555 1234','monetaryValue':0,'pipelineId':'pipe-1','pipelineStageId':'stage-1','assignedName':'Luis Comercial'},
                       {'id':'opp-3','name':'Soporte anual','contactId':'c3','contactName':'María','monetaryValue':500,'pipelineId':'pipe-1','pipelineStageId':'stage-2','assignedName':None}]
                stages=[{'id':'stage-1','name':'Nuevo lead','items':[c for c in cards if c['pipelineStageId']=='stage-1'],'total':2,'value':2400.0},
                        {'id':'stage-2','name':'Propuesta enviada','items':[c for c in cards if c['pipelineStageId']=='stage-2'],'total':1,'value':500.0}]
                data={'pipelines':[{'id':'pipe-1','name':'Pipeline comercial'}],'pipelineId':'pipe-1','stages':stages,
                      'total':3,'restricted':False,'canEdit':can_edit,'partial':False}
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
        page.wait_for_selector('.board-card2',state='attached')
        return page,errors,sent,context


    page,errors,sent,context=session({'width':1440,'height':900})
    cnt=lambda:page.locator('.board-card2').count()
    assert cnt()==3,'tres tarjetas'
    assert page.locator('.board-chips button').count()==2
    assert page.locator('[data-edit-opportunity]').first.locator('text=Editar').count()==0,'sin botón Editar'
    print('PASS tarjetas compactas y chips de etapa')
    s=page.locator('#board-search')
    s.fill('809 555-1234');page.wait_for_timeout(400)
    assert cnt()==1 and 'Web Tienda' in page.locator('.board-card2').inner_text(),'teléfono con otro formato'
    s.fill('CARLOS@ejemplo');page.wait_for_timeout(400);assert cnt()==1,'correo'
    s.fill('andreína');page.wait_for_timeout(400);assert cnt()==1,'nombre sin importar acentos'
    s.fill('');page.wait_for_timeout(400);assert cnt()==3
    print('PASS búsqueda por nombre, teléfono y correo')
    page.locator('#board-noamount').click();assert cnt()==1
    page.locator('#board-clear').click();assert cnt()==3
    page.locator('#board-who').select_option('Luis Comercial');assert cnt()==1
    page.locator('#board-clear').click()
    print('PASS filtros «sin monto» y responsable')
    page.locator('[data-toggle="stage-1"]').click()
    assert page.locator('#stage-stage-1 .board-stage-body').is_hidden(),'etapa plegada'
    page.locator('[data-toggle="stage-1"]').click()
    page.locator('[data-open-chat="c1"]').first.click()
    page.wait_for_timeout(300)
    print('PASS plegar etapas y atajo a la conversación')
    page.locator('[data-move="opp-1"]').click()
    page.locator('[data-move-to="stage-2"][data-opp="opp-1"]').click()
    page.wait_for_timeout(500)
    assert sent and sent[-1]['body'].get('pipelineStageId')=='stage-2' and sent[-1]['body'].get('requestId'),'Mover a… escribe etapa y requestId'
    assert page.locator('#stage-stage-2 [data-edit-opportunity="opp-1"]').count()==1,'la tarjeta pasó de etapa'
    print('PASS «Mover a…» cambia sólo la etapa')
    page.set_viewport_size({'width':390,'height':800})
    over=page.evaluate('document.documentElement.scrollWidth-document.documentElement.clientWidth')
    assert over<=0,'sin desborde en 390 px: %s'%over
    print('PASS 390 px sin desborde')
    assert not errors,errors
    b.close()
