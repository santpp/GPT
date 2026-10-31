"""El tablero del mes: de las ventas a la ganancia, el equipo y las cifras que escribe el propietario."""
import json,os,re
from playwright.sync_api import sync_playwright

# El navegador de pruebas no agrupa los miles; lo que importa es la cifra.
def plain(text):
    return re.sub(r'(?<=\d)[.\u202f ](?=\d{3}\b)','',text)
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')

SETTINGS={'otherAds':85.0,'fixedCosts':2056.0,'commissionRate':10.0,'teamGoal':20100.0,
          'goals':{'seller-1':4800.0,'seller-2':4700.0,'seller-3':5500.0},'closingWord':'estudiantes','green':100.0,'amber':70.0}
TEAM=[{'id':'seller-2','name':'María Carvallo','sold':4794.0,'closings':52,'goal':4700.0,'difference':94.0,'progress':102.0,'state':'lograda','commission':479.4},
      {'id':'seller-1','name':'Rosangélica Pérez','sold':3858.25,'closings':40,'goal':4800.0,'difference':-941.75,'progress':80.4,'state':'cerca','commission':385.83},
      {'id':'seller-3','name':'Lisbella Moreno','sold':2640.0,'closings':38,'goal':5500.0,'difference':-2860.0,'progress':48.0,'state':'apoyo','commission':264.0}]

def month(period='2026-10',missing=None,can_edit=True,ads_reason=None):
    data={'period':period,'restricted':False,'canEdit':can_edit,'settings':SETTINGS,
      'sales':{'total':11292.25,'closings':130,'counted':130,'partial':False},
      'profitability':{'sales':11292.25,'ads':1385.0,'adsFromAccount':1300.0,'adsOther':85.0,'adsSource':'INTEGRATION','adsReason':None,'commissions':1129.23,'profit':8778.02,'fixedCosts':2056.0,'net':6722.02,
        'marginPerUnit':0.78,'adsPerUnit':0.12,'commissionPerUnit':0.1,'costPerClosing':10.65,'returnPerHundred':815.33,'marketingCost':3441.0},
      'team':TEAM,'goal':{'target':20100.0,'sold':11292.25,'progress':56.2,'missing':8807.75},
      'missing':missing or [],'readAt':'2026-10-31T10:00:00+00:00'}
    if ads_reason:
        data['profitability']=dict(data['profitability'],adsFromAccount=None,adsSource=None,adsReason=ads_reason,adsOther=None,ads=None)
    return data

with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    def session(viewport={'width':1440,'height':900},**kw):
        context=b.new_context(viewport=viewport,service_workers='block')
        page=context.new_page()
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        saved=[];state={'logged':False,'month':month(**kw)}
        def api(route,request):
            path=request.url.split('/api/')[1];status=200
            if path=='me':
                if not state['logged']: status=401;data={'error':'Inicia sesión'}
                else: data={'user':{'role':'administrator','email':'admin@example.test'},'csrf':'t'}
            elif path=='login': state['logged']=True;data={'csrf':'t'}
            elif path.startswith('workspace/connections'):
                data={'items':[{'id':1,'name':'ASOCIM','location_id':'a','status':'connected'}]}
            elif path.startswith('workspace/dashboard'):
                data={'restricted':False,'conversations':{'total':1464,'unread':9},'contacts':3120,
                      'opportunities':{'count':12,'value':24000.0,'stages':[{'name':'Pipeline comercial · Nuevo lead','count':7,'value':14000.0}],'sampled':False},
                      'readAt':'2026-10-31T10:00:00+00:00'}
            elif path.startswith('workspace/board-month'):
                if request.method=='POST':
                    saved.append(json.loads(request.post_data or '{}'));data={'settings':SETTINGS,'period':'2026-10'}
                else:
                    period=path.split('period=')[1] if 'period=' in path else '2026-10'
                    data=dict(state['month'],period=period)
            elif path.startswith('workspace/inbox'):
                data={'syncIntervalSeconds':120,'limit':20,'items':[],'total':0,'syncedAt':None,'partial':False,'state':None,'nextCursor':None,'hasMore':False}
            elif path.startswith('workspace/pulse'):
                data={'token':'t','unread':0,'syncedAt':None,'intervalSeconds':10,'refreshed':False,'reason':None,'retryAfter':0}
            else: data={'items':[]}
            route.fulfill(status=status,content_type='application/json',body=json.dumps(data))
        page.route('**/api/**',api)
        page.goto(BASE+'/')
        page.wait_for_selector('#signin:not([hidden])')
        page.locator('.admin-login summary').click()
        page.locator('#login-form [name="email"]').fill('admin@example.test')
        page.locator('#login-form [name="password"]').fill('secret')
        page.locator('#login-form button').click()
        page.wait_for_selector('#app:not([hidden])')
        page.locator('[data-view="dashboard"]').click()
        page.wait_for_selector('.board-tile',state='attached')
        return page,errors,saved,context

    page,errors,saved,context=session()
    page.wait_for_selector('.waterfall',state='attached')
    page.wait_for_selector('.waterfall',state='attached')
    body=plain(page.locator('#view').inner_text())
    assert '1464' in body and '3120' in body, body[:300]
    print('PASS el tablero muestra lo de siempre, con el total de contactos de la cuenta')
    steps=[plain(t) for t in page.locator('.waterfall-step').all_inner_texts()]
    assert len(steps)==4 and 'Ventas' in steps[0] and 'Ganancia' in steps[3], steps
    assert '8778,02' in steps[3], steps[3]
    assert '−1385,00' in steps[1], steps[1]
    print('PASS la cascada va de las ventas a la ganancia, restando lo que se va por el camino')
    assert 'De cada 1,00 vendido quedan 0,78' in body, body[:300]
    print('PASS y dice cuánto queda de cada unidad vendida')
    source=plain(page.locator('.board-source').inner_text())
    assert 'cuenta publicitaria de Meta' in source and '1300,00' in source and '85,00' in source, source
    print('PASS dice cuánto viene de la cuenta de Meta conectada y cuánto se indicó aparte')

    rows=page.locator('.board-table tbody tr')
    assert rows.count()==3
    first=plain(rows.nth(0).inner_text())
    assert 'María Carvallo' in first and '102 %' in first and 'Meta lograda' in first, first
    assert 'Necesita apoyo' in rows.nth(2).inner_text()
    assert 'estudiantes' in page.locator('.board-table thead').inner_text().lower(), 'la palabra del negocio manda en la tabla'
    print('PASS el equipo sale ordenado por lo vendido, con su meta, su diferencia y su estado')
    for word in ['GoHighLevel','GHL','LeadConnector','monetaryValue','assignedTo','pipelineId','adPublishing','readonly','INTEGRATION','AD_MANAGER']:
        assert word not in body, word
    print('PASS sin una palabra técnica en pantalla')
    assert not errors, errors
    context.close()

    # Las cifras del mes las escribe el propietario.
    page,errors,saved,context=session()
    page.locator('#board-settings').click()
    page.wait_for_selector('#board-form',state='visible')
    page.locator('#board-form [name="otherAds"]').fill('150')
    page.locator('#board-form [name="teamGoal"]').fill('21000')
    page.locator('#board-form [name="closingWord"]').fill('estudiantes')
    page.locator('#board-form [data-goal="seller-3"]').fill('6000')
    page.locator('#board-form button.primary').click()
    page.wait_for_function("()=>!document.querySelector('#action-dialog').open")
    assert len(saved)==1, saved
    assert saved[0]['otherAds']=='150' and saved[0]['teamGoal']=='21000', saved[0]
    assert 'adSpend' not in saved[0], 'lo de Meta ya no se escribe a mano'
    assert saved[0]['goals']['seller-3']=='6000', saved[0]
    print('PASS el propietario fija lo invertido, la meta y las metas de cada asesora')
    assert not errors, errors
    context.close()

    # Sin esas cifras, el tablero dice qué falta en lugar de enseñar ceros.
    page,errors,saved,context=session(missing=['ads','teamGoal'])
    warning=page.locator('.board-warning').inner_text()
    assert 'lo invertido en publicidad' in warning and 'la meta del equipo' in warning, warning
    print('PASS si faltan cifras lo dice, en lugar de enseñar un cero que parezca un resultado')
    assert not errors, errors
    context.close()

    # Sin permiso para leer los anuncios lo dice en palabras, sin nombres técnicos.
    page,errors,saved,context=session(ads_reason='permission')
    note=page.locator('.board-source').inner_text()
    assert 'permiso para leer los anuncios' in note, note
    for word in ['adPublishing','readonly','scope','INTEGRATION']:
        assert word not in note, word
    print('PASS sin permiso de anuncios lo explica en palabras, sin nombres técnicos')
    assert not errors, errors
    context.close()

    # Quien no administra no puede tocarlas.
    page,errors,saved,context=session(can_edit=False)
    assert page.locator('#board-settings').count()==0
    print('PASS quien no administra ve el tablero pero no puede cambiar las cifras')
    assert not errors, errors
    context.close()

    # En un móvil de 6,7 pulgadas todo cabe y se toca.
    page,errors,saved,context=session(viewport={'width':390,'height':844})
    width=page.evaluate('document.documentElement.scrollWidth')
    assert width<=390, f'se sale de la pantalla: {width}px'
    box=page.locator('#board-settings').bounding_box()
    assert box['height']>=44, box
    print('PASS en 390 px cabe sin desbordarse y el botón se puede tocar')
    # Cada asesora es una tarjeta: el nombre en una o dos líneas, nunca letra a letra.
    names=page.locator('.board-table th[scope="row"]')
    for i in range(names.count()):
        name=names.nth(i).bounding_box()
        assert name['width']>=200, f'el nombre se estrecha hasta partirse: {name}'
        assert name['height']<=48, f'el nombre ocupa demasiadas líneas: {name}'
    cells=page.evaluate("[...document.querySelectorAll('.board-table td')].map(td=>getComputedStyle(td,'::before').content)")
    assert all(c not in ('none','""') for c in cells), 'cada cifra lleva su rótulo en el teléfono'
    bars=page.locator('.board-table .board-bar').first.bounding_box()
    card=page.locator('.board-table tbody tr').first.bounding_box()
    assert card['x']<=bars['x'] and bars['x']+bars['width']<=card['x']+card['width'], 'la barra de avance queda dentro de su tarjeta'
    print('PASS en el teléfono el equipo se lee como tarjetas, sin nombres partidos ni barras fuera de sitio')
    assert not errors, errors
    context.close()
    b.close()
