"""1.10.1: texto negro y más grande en la bandeja, mensajes y cuadro de respuesta legibles, y el canal de origen a la vista."""
import json,os,re
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
CH=[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False}]
CONV=[('c0','Lucía','TYPE_WHATSAPP'),('c1','Marcos','TYPE_INSTAGRAM'),('c2','Camila','TYPE_FACEBOOK'),('c3','Andrés','TYPE_SMS')]
def api(route):
    path=urlparse(route.request.url).path
    if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':'USD'}],'reason':None}
    elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':i,'contactId':'k'+i,'contactName':n,'lastMessageType':t,'lastMessageBody':'Hola, quiero información','lastMessageDirection':'inbound'} for i,n,t in CONV],'total':4,'restricted':True}
    elif path=='/api/workspace/messages':data={'items':[{'id':'m1','body':'Hola','direction':'inbound','dateAdded':'2026-11-02T10:00:00Z'}],'hasMore':False,'channels':CH}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
def rgb(v):return [int(x) for x in re.findall(r'\d+',v)[:3]]
def luma(v):r,g,b=rgb(v);return (0.2126*r+0.7152*g+0.0722*b)
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
    style=lambda sel:page.locator(sel).first.evaluate('e=>{const c=getComputedStyle(e);return [c.color,parseFloat(c.fontSize)]}')
    color,size=style('.conversation-info strong');assert luma(color)<40 and size>=17,(color,size)
    color,size=style('.conversation-info p');assert luma(color)<90 and size>=15,(color,size)
    print('PASS the list is black and big: names 17 px or more, previews 15 px or more and dark')
    chips={k:page.locator(f'.channel-chip.ch-{k}').count() for k in ('WhatsApp','IG','FB')}
    assert all(v==1 for v in chips.values()),chips
    assert page.locator('[data-live-id="c3"] .channel-chip').inner_text().strip()=='SMS'
    colours={k:page.locator(f'.channel-chip.ch-{k}').evaluate('e=>getComputedStyle(e).color') for k in chips};assert len(set(colours.values()))==3,colours
    print('PASS each conversation shows its channel in the colour of the network (WhatsApp, Instagram, Facebook)')
    page.locator('[data-live-id="c1"]').evaluate('n=>n.click()');page.wait_for_selector('.bubble')
    assert 'Instagram' in page.locator('.live-chat-header .channel-chip').inner_text()
    color,size=style('.bubble');assert luma(color)<40 and size>=16.5,(color,size)
    color,size=style('#live-reply');assert luma(color)<40 and size>=16.5,(color,size)
    print('PASS the chat header names the channel, and messages and the reply box are black and 17 px')
    page.evaluate("agoraText.set('large')");page.wait_for_timeout(200)
    _,big=style('.bubble');assert big>=19.5,big
    print('PASS «Texto grande» goes further still')
    assert not errors,errors
    b.close()
