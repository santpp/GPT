"""Modo oscuro: sigue al dispositivo, se puede fijar a mano y se recuerda, sin parpadeo."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def api(route):
    path=route.request.url.split('/api/')[1].split('?')[0]
    if path=='me':data={'user':{'role':'administrator','email':'admin@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
    elif path=='security':data={'passkeys':[],'sessions':[],'team':None,'lockMinutes':None,'lockChoices':[5,15,30,60],'deviceRemembered':True}
    elif path=='workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}]}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    context=b.new_context(viewport={'width':390,'height':844},service_workers='block',color_scheme='dark')
    page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    first=[]
    page.on('domcontentloaded',lambda: first.append(page.evaluate("document.documentElement.dataset.theme")))
    page.route('**/api/**',api);page.goto(BASE+'/');page.wait_for_selector('#app:not([hidden])')
    assert first and first[0]=='dark','the theme is set before anything is painted, so there is no white flash'
    bg=page.evaluate("getComputedStyle(document.body).backgroundColor")
    assert bg=='rgb(18, 21, 31)',bg
    assert page.evaluate("document.querySelector('meta[name=theme-color]').content")=='#12151f'
    print('PASS a phone set to dark opens Ágora in dark, status bar included')
    page.locator('#profile-top').click();page.wait_for_selector('#theme-choice')
    assert page.locator('#theme-choice').input_value()=='auto'
    page.select_option('#theme-choice','light')
    assert page.evaluate("document.documentElement.dataset.theme")=='light'
    assert page.evaluate("getComputedStyle(document.body).backgroundColor")!='rgb(18, 21, 31)'
    print('PASS the person can choose light even when the phone is dark')
    page.reload();page.wait_for_selector('#app:not([hidden])')
    assert page.evaluate("document.documentElement.dataset.theme")=='light'
    print('PASS and the choice is remembered on this device')
    page.locator('#profile-top').click();page.wait_for_selector('#theme-choice');page.select_option('#theme-choice','auto')
    assert page.evaluate("document.documentElement.dataset.theme")=='dark'
    context.close()
    light=b.new_context(viewport={'width':1440,'height':900},service_workers='block',color_scheme='light').new_page()
    light.route('**/api/**',api);light.goto(BASE+'/');light.wait_for_selector('#app:not([hidden])')
    assert light.evaluate("document.documentElement.dataset.theme")=='light'
    assert light.evaluate("getComputedStyle(document.body).backgroundColor")=='rgb(247, 247, 250)','light mode is exactly as before'
    print('PASS on automatic it follows the device again, and light mode is unchanged')
    manifest=light.request.get(BASE+'/manifest.webmanifest').json()
    icons={(i['sizes'],i['purpose']):i['src'] for i in manifest['icons']}
    assert ('512x512','maskable') in icons and ('192x192','any') in icons
    for src in list(icons.values())+['/assets/icons/apple-touch-icon.png','/assets/icons/favicon-32.png','/assets/icons/badge-96.png','/favicon.ico']:
        r=light.request.get(BASE+src);assert r.ok and r.body()[:4] in (b'\x89PNG',b'\x00\x00\x01\x00'),src
    assert light.locator('link[rel=apple-touch-icon]').count()==1 and light.locator('link[rel=icon][sizes="32x32"]').count()==1
    print('PASS the installed app, the browser tab and the notifications use the Ágora icon, with a safe area for rounded icons')
    assert not errors,errors
    print('PASS no JS errors')
    b.close()
