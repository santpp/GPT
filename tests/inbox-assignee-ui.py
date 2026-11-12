"""1.10.14 · Bandeja: quien ve toda la subcuenta ve una línea con el responsable de cada conversación; un vendedor no."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
ITEMS=[{'id':'c1','contactName':'Joel Moto Taxi','lastMessageType':'TYPE_WHATSAPP','lastMessageDate':1793900000000,'unreadCount':0,'assignedTo':'u-ana'},
       {'id':'c2','contactName':'Nancy Peña','lastMessageType':'TYPE_WHATSAPP','lastMessageDate':1793899000000,'unreadCount':1},
       {'id':'c3','contactName':'Freddy Henríquez','lastMessageType':'TYPE_SMS','lastMessageDate':1793898000000,'unreadCount':0,'assignedTo':'u-gone'}]
def session(b,admin):
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/me':d={'user':{'role':'administrator' if admin else 'team','email':'x@example.test','identity_type':'native' if admin else 'external','capabilities':{'viewConversations':True,'assignedDataOnly':not admin}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':d={'items':[{'id':1,'name':'UBA Line Pro'}]}
        elif path=='/api/workspace/inbox':d={'limit':20,'items':ITEMS if admin else ITEMS[:1],'total':3,'team':{'u-ana':'Ana Ventas <b>'} if admin else None}
        else:d={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
    ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block');ctx.add_init_script("localStorage.setItem('agora-tour-done','1')")
    pg=ctx.new_page();errors=[];pg.on('pageerror',lambda e:errors.append(str(e)));pg.route('**/api/**',api)
    pg.goto(BASE+'/bandeja');pg.wait_for_selector('.live-conversation');return pg,errors
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    pg,errors=session(b,True)
    lines=pg.locator('.live-conversation .live-assignee').all_inner_texts()
    assert lines==['Responsable: Ana Ventas <b>','Sin asignar','Responsable: otra persona del equipo'],lines
    assert 'none' in pg.locator('.live-conversation').nth(1).locator('.live-assignee').get_attribute('class')
    over=pg.evaluate('document.documentElement.scrollWidth-document.documentElement.clientWidth');assert over<=0,over
    assert not errors,errors
    print('PASS who sees the whole sub-account sees each conversation\'s owner, «Sin asignar» highlighted, names as text')
    pg,errors=session(b,False)
    assert pg.locator('.live-assignee').count()==0,'a seller only sees their own: no owner line'
    assert not errors,errors
    print('PASS a seller sees no owner line')
    b.close()
