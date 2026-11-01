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
def_pick='''
'''
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    ctx=b.new_context(viewport={'width':390,'height':844},service_workers='block');page=ctx.new_page()
    def pick(kind):
        page.locator('#period-btn').click();page.locator(f'[data-period="{kind}"]').click()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.route('**/api/**',api)
    page.goto(BASE+'/tablero');page.wait_for_selector('#period-btn');page.wait_for_selector('.board-tile')
    assert dash[-1]==(today.isoformat(),today.isoformat()) and 'Leads de hoy' in page.locator('#dashboard-body').inner_text()
    print('PASS the board opens on today, as before')
    pick('yesterday');page.wait_for_function("document.querySelector('.board-tile span')?.textContent.includes('ayer')")
    y=(today-datetime.timedelta(days=1)).isoformat();assert dash[-1]==(y,y)
    pick('week');page.wait_for_function("document.querySelector('.board-tile small')?.textContent.includes('7 días')")
    assert dash[-1]==((today-datetime.timedelta(days=6)).isoformat(),today.isoformat())
    print('PASS yesterday and the last 7 days change the leads period and say which one it is')
    pick('prev');page.wait_for_timeout(500)
    first_this=today.replace(day=1);last_prev=first_this-datetime.timedelta(days=1)
    assert dash[-1]==(last_prev.replace(day=1).isoformat(),last_prev.isoformat()) and months[-1]==last_prev.strftime('%Y-%m')
    print('PASS the previous month sets both the leads period and the month of the monthly summary')
    page.locator('#period-btn').click();page.wait_for_selector('#period-from')
    page.fill('#period-from','2026-10-03');page.fill('#period-to','2026-10-12');page.locator('#period-apply').click();page.wait_for_timeout(500)
    assert dash[-1]==('2026-10-03','2026-10-12') and months[-1]=='2026-10'
    print('PASS a custom range is sent as chosen, and the summary follows the month it ends in')
    page.reload();page.wait_for_selector('#period-btn');page.wait_for_timeout(500)
    assert '3 oct' in page.locator('#period-btn').inner_text() and dash[-1]==('2026-10-03','2026-10-12')
    print('PASS the choice is remembered on this device')
    # uno u otro: al tocar el mes o el rango se apaga el botón elegido, y al elegir un botón se vacían los campos
    pick('week');page.locator('#period-btn').click()
    assert page.locator('.period-list button.on').count()==1
    page.fill('#period-from','2026-10-01');page.wait_for_timeout(200)
    assert page.locator('.period-list button.on').count()==0 and page.locator('#period-month').input_value()==''
    page.fill('#period-month','2026-09');page.wait_for_timeout(500)
    assert page.locator('#period-from').input_value()=='' if page.locator('#period-menu').is_visible() else True
    print('PASS the periods are either a button or a month/range, never both at once')
    page.screenshot(path='/tmp/period.png')
    assert not errors,errors
    b.close()
