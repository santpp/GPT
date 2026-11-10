"""Animaciones al abrir: la ficha de contacto entra deslizándose y «Más» sube desde abajo (y baja al cerrar)."""
import json,os
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
def api(route):
    path=route.request.url.split('/api/')[1].split('?')[0]
    if path=='me':data={'user':{'role':'administrator','email':'owner@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
    elif path=='workspace/connections':data={'items':[{'id':1,'name':'ASOCIM'}]}
    elif path=='workspace/contacts':data={'items':[{'id':'c1','name':'Ana Pérez','phone':'+58414','tags':[]}],'total':1,'restricted':False,'partial':False}
    elif path=='workspace/record':data={'contact':{'id':'c1','name':'Ana Pérez','tags':[],'customFields':[]},'canEdit':False,'fields':[],'assignees':[]}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
anim=lambda pg,sel:pg.evaluate(f"getComputedStyle(document.querySelector('{sel}')).animationName")
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for motion in ('no-preference','reduce'):
        ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block',reduced_motion=motion);ctx.add_init_script("localStorage.setItem('agora-tour-done','1')")
        pg=ctx.new_page();errors=[];pg.on('pageerror',lambda e:errors.append(str(e)));pg.route('**/api/**',api)
        pg.goto(BASE+'/contactos');pg.wait_for_selector('.record-row')
        pg.locator('.record-row').first.click();pg.wait_for_selector('#contact-record.opening')
        name=anim(pg,'#contact-record')
        assert name==('slide-in-right' if motion=='no-preference' else 'none'),(motion,name)
        pg.locator('#nav-more').click();pg.wait_for_selector('#more-sheet:not([hidden])')
        panel=anim(pg,'#more-sheet .more-panel')
        assert panel==('sheet-up' if motion=='no-preference' else 'none'),(motion,panel)
        pg.locator('#more-sheet').click(position={'x':10,'y':10})
        if motion=='no-preference':
            assert 'closing' in pg.locator('#more-sheet').get_attribute('class'),'it slides down before hiding'
        pg.wait_for_selector('#more-sheet',state='hidden')
        # Abrir otra vez justo después: se queda abierto.
        pg.locator('#nav-more').click();pg.wait_for_timeout(700)
        assert pg.locator('#more-sheet').is_visible(),'reopening right after closing keeps it open'
        pg.keyboard.press('Escape');pg.wait_for_selector('#more-sheet',state='hidden')
        assert not errors,errors
        print('PASS',motion,': the contact record and «Más» '+('animate as they open and close' if motion=='no-preference' else 'open without movement when the phone asks for less motion'))
        ctx.close()
    b.close()
