"""Nota de voz en el chat: sólo WhatsApp y con la conversación abierta; grabar, escuchar, enviar, reintentar con la misma clave."""
import json,os,datetime
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
NOW=datetime.datetime.now(datetime.timezone.utc).isoformat().replace('+00:00','Z')
OLD=(datetime.datetime.now(datetime.timezone.utc)-datetime.timedelta(days=3)).isoformat().replace('+00:00','Z')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox','--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream'])
    for width in [390,1440]:
        context=b.new_context(viewport={'width':width,'height':900},service_workers='block',permissions=['microphone'])
        page=context.new_page();errors=[];posts=[];mode=['fail'];page.on('pageerror',lambda e:errors.append(str(e)))
        def api(route):
            req=route.request;u=urlparse(req.url);path=u.path;q=parse_qs(u.query)
            if path=='/api/me':data={'user':{'role':'administrator','email':'a@b.c','capabilities':{}},'csrf':'t','passkeys':1}
            elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
            elif path=='/api/workspace/inbox':data={'limit':20,'items':[{'id':'wa','contactId':'k1','contactName':'María WhatsApp','lastMessageType':'TYPE_WHATSAPP'},{'id':'sms','contactId':'k2','contactName':'Luis SMS','lastMessageType':'TYPE_SMS'},{'id':'old','contactId':'k3','contactName':'Ana Antigua','lastMessageType':'TYPE_WHATSAPP'}],'total':3}
            elif path=='/api/workspace/messages':
                data={'items':[{'id':'m1','direction':'inbound','body':'Hola','dateAdded':OLD if q['conversationId'][0]=='old' else NOW}],'hasMore':False}
            elif path=='/api/workspace/contact':data={'contact':{'name':'X','tags':[],'customFields':[]},'canEdit':False,'channels':[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False,'reason':None},{'type':'SMS','label':'SMS','available':True,'needsSubject':False,'reason':None}]}
            elif path=='/api/workspace/voice':
                assert req.headers['x-csrf-token']=='t'
                posts.append({'type':req.headers.get('content-type'),'id':q['requestId'][0],'conv':q['conversationId'][0],'size':len(req.post_data_buffer or b''),'head':(req.post_data_buffer or b'')[:4]})
                if mode[0]=='fail':route.abort();return
                if mode[0]=='reject':route.fulfill(status=415,content_type='application/json',body=json.dumps({'error':'La cuenta no acepta este tipo de audio.','code':'voice_upload_rejected'}));return
                data={'state':'accepted','messageId':'voz-1','deliveryStatus':'pending','url':'https://storage.googleapis.com/agora/nota-de-voz.ogg'}
            else:data={'items':[]}
            route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id="wa"]')
        page.locator('[data-live-id="sms"]').evaluate('(n)=>n.click()');page.wait_for_selector('#live-composer');page.wait_for_timeout(800)
        assert page.locator('#live-mic').is_hidden()
        page.locator('[data-live-id="old"]').evaluate('(n)=>n.click()');page.wait_for_timeout(1200)
        assert page.locator('#live-mic').is_hidden()
        print(f'PASS at {width}px there is no microphone on SMS, nor on WhatsApp once the 24-hour window is closed')
        page.locator('[data-live-id="wa"]').evaluate('(n)=>n.click()');page.wait_for_selector('#live-mic:not([hidden])')
        assert page.locator('#live-mic').bounding_box()['width']>=44
        page.locator('#live-mic').click();page.wait_for_selector('#voice-stop')
        page.wait_for_timeout(1500)
        assert page.locator('#live-reply').is_hidden() and page.locator('#voice-time').inner_text()!='0:00'
        page.locator('#voice-stop').click();page.wait_for_selector('#voice-send')
        assert page.locator('.voice-bar audio').count()==1
        print(f'PASS at {width}px it records with a visible timer, and offers to listen before sending')
        page.locator('#voice-send').click();page.wait_for_selector('.voice-bar .voice-hint:has-text("sin conexión"), .voice-bar .voice-hint:has-text("Error"), #voice-send:has-text("Reintentar")',timeout=15000)
        first=posts[0];assert first['head']==b'OggS' and first['type']=='audio/ogg' and first['size']>500 and first['conv']=='wa',first
        print(f'PASS at {width}px the audio travels as OGG/Opus to Ágora, never to the account directly')
        mode[0]='ok';page.locator('#voice-send').click()
        page.wait_for_selector('.bubble[data-mid="voz-1"] audio',timeout=15000)
        assert posts[-1]['id']==first['id'],'a retry reuses the same request key, so it can never send twice'
        assert page.locator('.voice-bar').count()==0 and page.locator('#live-reply').is_visible()
        print(f'PASS at {width}px after a failure, retrying sends it once with the same key, and it plays in the chat')
        page.locator('#live-mic').click();page.wait_for_selector('#voice-cancel');page.locator('#voice-cancel').click()
        assert page.locator('.voice-bar').count()==0 and page.locator('#live-mic').is_visible()
        mode[0]='reject';page.locator('#live-mic').click();page.wait_for_selector('#voice-stop');page.wait_for_timeout(700);page.locator('#voice-stop').click();page.wait_for_selector('#voice-send');page.locator('#voice-send').click()
        page.wait_for_function("document.querySelector('.voice-hint')?.textContent.includes('no acepta')")
        assert page.locator('#voice-send').is_hidden()
        print(f'PASS at {width}px cancelling leaves nothing behind, and a format the account refuses is said so, with no pointless retry')
        assert not errors,errors
        context.close()
    print('PASS no JS errors')
    b.close()
