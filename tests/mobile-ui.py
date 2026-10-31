"""Phone layout at 412 x 915 (a 6.7-inch screen): touch targets, no sideways scroll, chat fills the screen."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
PHONE={'width':412,'height':915}   # a 6.7-inch phone
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport=PHONE,device_scale_factor=2.6,is_mobile=True,has_touch=True,service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    msgs=[{'id':f'm{i}','body':f'Mensaje {i}','direction':'inbound' if i%2 else 'outbound','dateAdded':'2026-10-29T18:0%d:00Z'%(i%6),'status':'delivered'} for i in range(6)]
    def api(route):
        path=route.request.url.split('/api/')[1]
        if path=='me':d={'user':{'role':'administrator','email':'admin@agoradigital.app'},'csrf':'t'}
        elif path.startswith('workspace/connections'):d={'items':[{'id':1,'name':'ASOCIM','location_id':'a','status':'connected'}]}
        elif path.startswith('workspace/inbox'):d={'syncIntervalSeconds':120,'limit':20,'items':[{'id':'c1','contactName':'Raissa Moreno','lastMessageBody':'Hola, quiero información sobre el servicio','lastMessageType':'TYPE_WHATSAPP','contactId':'ct1','locationId':'a','unreadCount':2,'lastMessageDate':'2026-10-29T18:05:00Z'}],'total':1,'syncedAt':'2026-10-29 18:05:00','partial':False,'state':None}
        elif path.startswith('workspace/messages'):d={'items':msgs,'hasMore':False,'lastMessageId':None}
        elif path.startswith('workspace/contact-fields'):d={'items':[]}
        elif path.startswith('workspace/contact'):d={'contact':{'name':'Raissa Moreno','phone':'+584142736522','email':'r@x.test','assignedName':'Maria Carvallo','tags':[],'customFields':[]}}
        elif path.startswith('workspace/opportunities'):d={'active':None,'items':[],'total':0,'restricted':False,'partial':False,'pipelineNamesAvailable':True}
        elif path.startswith('workspace/activity'):d={'tasks':[],'notes':[],'appointments':[],'appointmentsVisible':True,'canEdit':True,'partial':{}}
        elif path.startswith('workspace/sync'):d={'synced':True}
        else:d={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
    page.route('**/api/**',api)
    page.goto(BASE+'/')
    page.wait_for_selector('#app:not([hidden])')

    def no_overflow(where):
        w=page.evaluate('document.documentElement.scrollWidth')
        assert w<=PHONE['width'], f'{where}: horizontal overflow, {w}px wide'
    def targets(where,selector,minimum=44):
        small=page.evaluate("""(sel)=>[...document.querySelectorAll(sel)]
            .filter(n=>n.offsetParent!==null)
            .map(n=>({t:(n.innerText||n.getAttribute('aria-label')||n.tagName).trim().slice(0,24),h:Math.round(n.getBoundingClientRect().height),w:Math.round(n.getBoundingClientRect().width)}))
            .filter(b=>b.h<%d||b.w<%d)"""%(minimum,minimum),selector)
        assert not small, f'{where}: targets below {minimum}px -> {small}'

    # El modo foco recordado no puede dejar la pantalla sin lista ni menú: sin
    # conversación abierta no hay forma de salir de él.
    page.evaluate("try{localStorage.setItem('agora-focus','1')}catch{}")
    page.reload()
    page.wait_for_selector('[data-live-id]')
    assert page.locator('.sidebar').is_visible(), 'el menú tiene que seguir ahí al volver a entrar'
    assert not page.evaluate("document.body.classList.contains('agora-focus')"), 'el modo foco no puede estar puesto sin conversación abierta'
    print('PASS focus mode remembered cannot leave the phone without list or menu')
    page.evaluate("try{localStorage.setItem('agora-focus','0')}catch{}")
    page.reload()
    page.wait_for_selector('[data-live-id]')
    no_overflow('inbox list');targets('inbox list','.sidebar .nav');targets('inbox list','.tabs button')
    print('PASS inbox list fits 412px with finger-sized navigation and tabs')

    page.locator('[data-live-id]').click()
    page.wait_for_selector('#live-log .bubble')
    no_overflow('chat')
    targets('chat','.live-chat-header button');targets('chat','.live-composer .primary');targets('chat','.sidebar .nav')
    box=page.locator('#live-log').bounding_box()
    assert box['height']>=200, f'the message log is only {box["height"]}px tall'
    composer=page.locator('.live-composer').bounding_box()
    assert composer['y']+composer['height']<=PHONE['height']+2, 'the composer falls below the fold'
    assert page.evaluate("getComputedStyle(document.querySelector('.live-composer textarea')).fontSize")=='16px'
    print(f'PASS chat uses the screen: log {int(box["height"])}px, composer reachable, no zoom on focus')

    # Focus hides the menu and the list so the conversation owns the screen.
    before=page.locator('#live-log').bounding_box()['height']
    page.locator('#live-focus').click()
    page.wait_for_timeout(250)
    assert page.locator('.sidebar').is_hidden(), 'the menu bar is hidden in focus'
    assert page.locator('.live-sidebar').is_hidden(), 'the conversation list is hidden in focus'
    after=page.locator('#live-log').bounding_box()['height']
    assert after>before, f'the log should grow in focus: {before} -> {after}'
    no_overflow('focus')
    targets('focus','.live-chat-header button')
    page.locator('#live-focus').click()
    page.wait_for_timeout(250)
    assert page.locator('.sidebar').is_visible(), 'leaving focus brings the menu back'
    print(f'PASS focus mode hides menu and list, log grows {int(before)} to {int(after)}px')

    page.locator('#live-contact-toggle').click()
    page.wait_for_selector('#live-contact-panel .live-profile-details')
    no_overflow('contact sheet');targets('contact sheet','.live-profile-actions a')
    assert page.locator('#live-contact-panel a[href^="tel:"]').count()==0, 'the call action is gone'
    print('PASS contact sheet fits and its actions are tappable')
    page.locator('#live-profile-close').click()

    for view,ready in [('contacts','#contact-feedback'),('board','#view'),('settings','#view')]:
        page.locator(f'[data-view="{view}"]').click()
        page.wait_for_selector(ready)
        page.wait_for_timeout(350)
        no_overflow(view)
        targets(view,'.sidebar .nav')
    print('PASS contacts, opportunities and connections fit without sideways scrolling')

    page.locator('[data-view="inbox"]').click()
    page.wait_for_selector('[data-live-id]')
    account=page.locator('#account').bounding_box()
    assert account['height']>=40, f'the account switch is only {account["height"]}px tall'
    assert account['x']+account['width']<=PHONE['width'], 'the account switch is cut off'
    print('PASS the account switch stays reachable and whole in the header')
    assert not errors,errors
    print('PASS no JS errors on a phone')
    b.close()
