"""Conversaciones que vienen de un comentario en una publicación: se dice y se responde por mensaje directo."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=b.new_context(viewport={'width':390,'height':844},service_workers='block').new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    def api(route):
        path=urlparse(route.request.url).path
        if path=='/api/me':data={'user':{'role':'team','email':'a@example.test','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
        elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'c0','contactId':'k0','contactName':'Lucía Gómez','lastMessageType':'TYPE_INSTAGRAM_COMMENT','lastMessageBody':'Precio?'}],'total':1}
        elif path=='/api/workspace/messages':data={'items':[{'id':'m1','body':'Precio?','direction':'inbound','messageType':'TYPE_INSTAGRAM_COMMENT','dateAdded':'2026-10-31T10:00:00Z'}],'hasMore':False}
        elif path=='/api/workspace/channels':data={'items':[{'type':'IG','label':'Mensaje directo en Instagram','available':True,'needsSubject':False}]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    page.route('**/api/**',api)
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]')
    page.locator('[data-live-id="c0"]').evaluate('(n)=>n.click()')
    page.wait_for_selector('.origin-note')
    note=page.locator('.origin-note').inner_text()
    assert 'primera interacción fue un comentario' in note and 'Instagram' in note and 'mensaje directo' in note,note
    assert 'Comentario en una publicación de Instagram' in page.locator('.comment-tag').inner_text()
    assert 'Mensaje directo en Instagram' in page.locator('#live-messages').inner_text()
    print('PASS a conversation that began as a comment says so and offers a direct message reply')
    assert page.locator('.reply-dm').count()==1 and 'Responder como DM' in page.locator('.reply-dm').inner_text()
    page.wait_for_timeout(500);page.locator('.reply-dm').click()
    assert page.evaluate("document.activeElement&&document.activeElement.id")=='live-reply' and 'mensaje directo' in page.locator('#live-send-status').inner_text()
    print('PASS the «Responder como DM» button on a comment picks the direct message and moves to the reply box')
    assert not errors,errors
    b.close()
