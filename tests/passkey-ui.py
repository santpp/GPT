"""Huella o cara de punta a punta: un autenticador virtual de Chromium hace de
teléfono contra el servidor real (APP_ORIGIN http://localhost:8080). Sólo las
lecturas de la cuenta se simulan; entrar, la llave, el bloqueo y la sesión del
dispositivo pasan por el servidor de verdad."""
import json,os,secrets,subprocess
from playwright.sync_api import sync_playwright
BASE='http://localhost:8080'
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
suffix=secrets.token_hex(5);email=f'passkey-{suffix}@example.test';password=secrets.token_hex(10)
php=lambda code,*args:subprocess.run(['php','-r','require $argv[1]."/src/bootstrap.php";'+code,ROOT,*args],check=True,capture_output=True,text=True).stdout
php('foreach(["127.0.0.1","::1"] as $ip)query("DELETE FROM login_attempts WHERE key_hash=?",[hash("sha256","passkey|".hash("sha256",$ip))]);')
php('query("INSERT INTO tenants(name,location_id) VALUES(?,?)",["Passkey UI",$argv[2]]);$t=db()->lastInsertId();query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,\'administrator\')",[$t,$argv[3],password_hash($argv[4],PASSWORD_DEFAULT)]);','pk-'+suffix,email,password)

def account(route):
    path=route.request.url.split('/api/')[1]
    if path.startswith('workspace/connections'):data={'items':[{'id':1,'name':'Cuenta','location_id':'loc','status':'connected'}]}
    elif path.startswith('workspace/inbox'):data={'syncIntervalSeconds':10,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None,'nextCursor':None,'hasMore':False}
    elif path.startswith('workspace/pulse'):data={'token':'t','unread':0,'syncedAt':None,'intervalSeconds':10,'refreshed':False,'reason':None,'retryAfter':0}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))

try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
        context=browser.new_context(viewport={'width':390,'height':844},service_workers='block')
        page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        cdp=context.new_cdp_session(page);cdp.send('WebAuthn.enable')
        authenticator=cdp.send('WebAuthn.addVirtualAuthenticator',{'options':{'protocol':'ctap2','transport':'internal','hasResidentKey':True,'hasUserVerification':True,'isUserVerified':True,'automaticPresenceSimulation':True}})['authenticatorId']
        page.route('**/api/workspace/**',account)
        page.goto(BASE+'/');page.wait_for_selector('#signin:not([hidden])')
        assert page.locator('#passkey-login').is_hidden(),'a device that never had a key is not offered the button'

        page.locator('summary').click()
        page.fill('#login-form [name=email]',email);page.fill('#login-form [name=password]',password);page.locator('#login-form button').click()
        page.wait_for_selector('#app:not([hidden])')
        # Primero la bienvenida (instalar o avisos); al cerrarla llega la oferta de la huella.
        page.wait_for_timeout(600)
        if page.locator('#welcome').is_visible():
            assert page.locator('#welcome-go').inner_text() in ('Activar avisos','Instalar','Entendido')
            page.locator('#welcome-later').click()
            print('PASS right after entering, Ágora offers to install the app or turn on notices, one tap away')
        page.wait_for_selector('#offer-yes',timeout=5000)
        print('PASS after the password, Ágora offers fingerprint or face once')
        page.locator('#offer-yes').click()
        page.wait_for_function("!document.querySelector('#action-dialog').open")
        assert len(cdp.send('WebAuthn.getCredentials',{'authenticatorId':authenticator})['credentials'])==1
        print('PASS the phone made a key and Ágora saved it')

        page.locator('#profile-top').click();page.wait_for_selector('#lock-minutes:not([disabled])')
        assert 'Tus llaves de acceso' in page.locator('#dialog-content').inner_text()
        assert 'este dispositivo' in page.locator('#dialog-content').inner_text()
        page.select_option('#lock-minutes','5');page.wait_for_function("document.querySelector('#security-result').textContent.includes('Hecho')")
        page.screenshot(path='/tmp/passkey-security.png')
        print('PASS the security panel lists the key and this device, and turns the lock on')
        page.keyboard.press('Escape')

        # La sesión del servidor caduca; la del dispositivo la rehace, bloqueada.
        context.clear_cookies(name='workspace_session')
        page.reload();page.wait_for_selector('#lock:not([hidden])')
        assert page.locator('#app').is_hidden()
        page.screenshot(path='/tmp/passkey-lock.png')
        print('PASS back after the server session expired, the device opens locked, without asking for the password')
        page.locator('#unlock').click();page.wait_for_selector('#app:not([hidden])')
        print('PASS the fingerprint or face opens it')

        page.locator('#profile-top').click();page.wait_for_selector('#signout');page.locator('#signout').click()
        page.wait_for_selector('#signin:not([hidden])')
        assert not [c for c in context.cookies() if c['name']=='agora_device'],'signing out leaves no device cookie behind'
        assert page.locator('#passkey-login').is_visible(),'a device with a key is offered the button'
        page.screenshot(path='/tmp/passkey-signin.png')
        page.locator('#passkey-login').click();page.wait_for_selector('#app:not([hidden])')
        print('PASS signing out ends the device session, and the fingerprint button enters again without typing anything')
        assert [c for c in context.cookies() if c['name']=='agora_device' and c['httpOnly'] and c['sameSite']=='Strict']
        print('PASS and the device is remembered again, with a cookie the page cannot read')
        assert not errors,errors
        print('PASS no JS errors')
        browser.close()
finally:
    php('$t=query("SELECT id FROM tenants WHERE location_id=?",[$argv[2]])->fetchColumn();if($t){query("DELETE FROM passkeys WHERE tenant_id=?",[$t]);query("DELETE FROM device_sessions WHERE tenant_id=?",[$t]);query("DELETE FROM users WHERE tenant_id=?",[$t]);query("DELETE FROM tenants WHERE id=?",[$t]);}','pk-'+suffix)
