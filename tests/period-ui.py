"""Tablero: selector de periodo (hoy, ayer, 7 y 30 días, mes, otro mes, rango) que cambia los leads y el mes del resumen."""
import json,os,datetime
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
dash=[];months=[]
today=datetime.date.today()
def api(route):
    u=urlparse(route.request.url);q=parse_qs(u.query);path=u.path
    if path=='/api/me':data={'user':{'role':'administrator','email':'a@example.test','identity_type':'native','capabilities':{}},'csrf':'t','passkeys':0}
    elif path=='/api/workspace/connections':data={'items':[{'id':1,'name':'Cuenta'}],'reason':None}
    elif path=='/api/workspace/dashboard':
        dash.append((q['from'][0],q['to'][0]))
        data={'restricted':False,'conversations':{'total':4,'unread':1},'contacts':None,'leads':{'today':5,'mine':27,'linked':True,'range':{'from':q['from'][0],'to':q['to'][0]}},'opportunities':None,'readAt':'2026-10-31T20:00:00+00:00'}
    elif path=='/api/workspace/board-month':
        months.append(q['period'][0]);data={'error':'x'}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(data))
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block');page=ctx.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
    page.goto(BASE+'/tablero');page.wait_for_selector('.period-chips');page.wait_for_selector('.board-tile')
    assert dash[-1]==(today.isoformat(),today.isoformat()) and 'Leads de hoy' in page.locator('#dashboard-body').inner_text()
    print('PASS the board opens on today, as before')
    page.locator('[data-period="yesterday"]').click();page.wait_for_function("document.querySelector('.board-tile span')?.textContent.includes('ayer')")
    y=(today-datetime.timedelta(days=1)).isoformat();assert dash[-1]==(y,y)
    page.locator('[data-period="week"]').click();page.wait_for_function("document.querySelector('.board-tile small')?.textContent.includes('7 días')")
    assert dash[-1]==((today-datetime.timedelta(days=6)).isoformat(),today.isoformat())
    print('PASS yesterday and the last 7 days change the leads period and say which one it is')
    page.locator('[data-period="prev"]').click();page.wait_for_timeout(500)
    first_this=today.replace(day=1);last_prev=first_this-datetime.timedelta(days=1)
    assert dash[-1]==(last_prev.replace(day=1).isoformat(),last_prev.isoformat()) and months[-1]==last_prev.strftime('%Y-%m')
    print('PASS the previous month sets both the leads period and the month of the monthly summary')
    page.locator('[data-period="custom"]').click();page.wait_for_selector('#period-from')
    page.fill('#period-from','2026-10-03');page.fill('#period-to','2026-10-12');page.wait_for_timeout(500)
    assert dash[-1]==('2026-10-03','2026-10-12') and months[-1]=='2026-10'
    print('PASS a custom range is sent as chosen, and the summary follows the month it ends in')
    page.reload();page.wait_for_selector('.period-chips');page.wait_for_timeout(500)
    assert page.locator('[data-period="custom"].on').count()==1 and dash[-1]==('2026-10-03','2026-10-12')
    print('PASS the choice is remembered on this device')
    page.screenshot(path='/tmp/period.png')
    assert not errors,errors
    b.close()
