"""Cifras con miles y la moneda de la subcuenta; mes en español; el propietario elige quién es."""
import json,os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
put=[]
def make(currency,role='administrator'):
    def api(route):
        req=route.request;path=urlparse(req.url).path
        if path=='/api/me':data={'user':{'role':role,'email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
        elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta','currency':currency}],'reason':None}
        elif path=='/api/workspace/dashboard':data={'restricted':False,'conversations':{'total':1520,'unread':42},'contacts':1950,'leads':{'today':16,'mine':None,'linked':False,'range':{}},'opportunities':{'count':3,'value':12345.5,'stages':[],'sampled':False},'readAt':'2026-11-01T20:00:00+00:00'}
        elif path=='/api/workspace/board-month':data={'period':'2026-11','restricted':False,'canEdit':True,'settings':{},'sales':{'total':2500,'closings':3,'counted':3,'partial':False},'profitability':{'sales':2500,'ads':100,'fixedCosts':50,'commissions':0,'marketingCost':150,'net':2350,'profit':2350,'costPerClosing':33,'returnPerHundred':2500,'marginPerUnit':0.9},'team':[],'goal':{'target':None,'progress':None,'missing':0},'missing':[],'mine':None,'commission':{'kind':'percent','amount':0},'origin':None}
        elif path=='/api/workspace/self' and req.method=='GET':data={'linked':None,'detected':None,'options':[{'id':'u1','name':'Ana','email':'ana@example.test'},{'id':'u2','name':'Luis','email':None}]}
        elif path=='/api/workspace/self':put.append(req.post_data_json);data={'linked':'u1','detected':'u1','options':[]}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
    return api
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    for currency,sym in [('USD','$'),('EUR','€')]:
        page=b.new_context(viewport={'width':390,'height':900},service_workers='block',locale='es-ES').new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',make(currency))
        page.goto(BASE+'/tablero');page.wait_for_selector('.board-tile');page.wait_for_timeout(600)
        text=page.locator('#dashboard-body').inner_text()
        assert '1.520' in text and '1.950' in text,text[:300]
        assert sym in text and ('12.345,50' in text),text[:400]
        assert (('€' not in text) if sym=='$' else ('$' not in text)),'only the sub-account\'s currency appears'
        if currency=='USD':
            opts=page.locator('#board-period option').all_inner_texts()
            assert opts[0]=='Noviembre 2026' and all(o.split(' ')[0].isalpha() for o in opts),opts[:3]
            page.locator('#period-btn').click();assert page.locator('#period-month option').nth(1).inner_text()=='Noviembre 2026'
            page.keyboard.press('Escape')
            page.locator('[data-self]').first.click();page.wait_for_selector('#self-form')
            page.select_option('#self-form select','u1');page.locator('#self-form button.primary').click();page.wait_for_timeout(500)
            assert put and put[-1]=={'externalUserId':'u1'},put
            print('PASS thousands, the sub-account\'s currency, months in Spanish and the owner choosing who they are')
        assert not errors,errors
    print('PASS euros and dollars follow the sub-account')
    b.close()
