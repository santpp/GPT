"""Genera las capturas del manual (datos inventados) y el PDF descargable.
Uso: python3 tools/make-manual.py   (con la app local en TEST_BASE_URL, por defecto http://127.0.0.1:8080)"""
import json,os,time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
ROOT=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..','public','assets')
IMG=os.path.join(ROOT,'manual','img');os.makedirs(IMG,exist_ok=True)
now=int(time.time()*1000)
CH=[{'type':'WhatsApp','label':'WhatsApp','available':True,'needsSubject':False},{'type':'SMS','label':'SMS','available':True,'needsSubject':False},{'type':'Email','label':'Correo','available':True,'needsSubject':True},{'type':'IG','label':'Instagram','available':False,'reason':'needs_thread'}]
INBOX=[('Lucía Fernández','Hola, ¿tienen disponibilidad para el sábado?','TYPE_WHATSAPP',4,'inbound',2),('Marcos Ortega','Perfecto, quedo atento al presupuesto','TYPE_WHATSAPP',35,'inbound',0),('Camila Reyes','Gracias por la información','TYPE_INSTAGRAM',180,'inbound',1),('Andrés Molina','Te envío los documentos hoy','TYPE_SMS',600,'outbound',0),('Valeria Soto','¿Cuánto cuesta el plan anual?','TYPE_WHATSAPP',1500,'inbound',3)]
def conv(i,r):
    n,b,t,m,d,u=r
    return {'id':f'c{i}','contactId':f'k{i}','contactName':n,'lastMessageType':t,'lastMessageBody':b,'lastMessageDirection':d,'lastMessageDate':now-m*60000,'unreadCount':u}
ITEMS=[conv(i,r) for i,r in enumerate(INBOX)]
WON=[{'id':'o1','name':'Plan anual','contactName':'Pedro Navarro','assignedName':'Ana','days':3,'value':0,'missing':True,'wonAt':'2026-11-01T10:00:00Z'},{'id':'o2','name':'Curso intensivo','contactName':'María Vidal','assignedName':'Ana','days':1,'value':0,'missing':True,'wonAt':'2026-11-02T10:00:00Z'},{'id':'o3','name':'Asesoría mensual','contactName':'Raúl Prieto','assignedName':'Ana','days':5,'value':1250.5,'missing':False,'wonAt':'2026-11-01T09:00:00Z'},{'id':'o4','name':'Pack inicial','contactName':'Elena Cruz','assignedName':'Ana','days':6,'value':480,'missing':False,'wonAt':'2026-10-30T09:00:00Z'}]
ME={'user':{'role':'team','email':'ana@example.test','name':'Ana','identity_type':'external','capabilities':{'viewConversations':True,'sendMessages':True,'viewContacts':True,'editContacts':True,'viewOpportunities':True,'editOpportunities':True,'assignedDataOnly':True}},'csrf':'t','passkeys':0}
FLAGS={'alert':True}
def api(route):
    path=urlparse(route.request.url).path
    if path=='/api/me':d=ME
    elif path=='/api/workspace/connections':d={'items':[{'id':1,'name':'Mi cuenta','currency':'USD'}],'reason':None}
    elif path=='/api/workspace/inbox':d={'limit':20,'items':ITEMS,'total':len(ITEMS),'restricted':True}
    elif path=='/api/workspace/messages':d={'items':[{'id':'m1','body':'Hola, ¿tienen disponibilidad para el sábado?','direction':'inbound','dateAdded':'2026-11-02T10:00:00Z','messageType':'TYPE_WHATSAPP'},{'id':'m2','body':'¡Hola Lucía! Sí, tenemos lugar a las 11:00. ¿Te funciona?','direction':'outbound','dateAdded':'2026-11-02T10:05:00Z','messageType':'TYPE_WHATSAPP'},{'id':'m3','body':'Perfecto, resérvamelo por favor','direction':'inbound','dateAdded':'2026-11-02T10:08:00Z','messageType':'TYPE_WHATSAPP'}],'hasMore':False,'channels':CH}
    elif path=='/api/workspace/contact':d={'contact':{'id':'k0','name':'Lucía Fernández','email':'lucia@example.test','phone':'+34 600 000 000','tags':['cliente','sábado'],'customFields':[]},'channels':CH,'canEdit':True,'fields':[],'assignees':[]}
    elif path=='/api/workspace/record':d={'contact':{'id':'k0','name':'Lucía Fernández','firstName':'Lucía','lastName':'Fernández','email':'lucia@example.test','phone':'+34 600 000 000','companyName':'Estudio Sol','source':'Instagram','tags':['cliente','sábado'],'customFields':[]},'fields':[],'assignees':[],'canEdit':True,'notes':[{'id':'n1','body':'Prefiere que la llamen por la tarde.','dateAdded':'2026-11-01T10:00:00Z'}],'tasks':[],'activity':[]}
    elif path=='/api/workspace/board-month':d={'sales':{'closings':9,'total':5400,'counted':9,'partial':False},'mine':{'closings':4,'sold':1730.5},'profit':None}
    elif path=='/api/workspace/tags':d={'items':[{'name':'cliente'},{'name':'sábado'},{'name':'seguimiento'}]}
    elif path=='/api/workspace/contacts':d={'items':[{'id':f'k{i}','name':r[0],'email':'','phone':'','tags':[]} for i,r in enumerate(INBOX)],'total':len(INBOX),'limit':20}
    elif path=='/api/security':d={'passkeys':[],'sessions':[{'id':1,'label':'Este teléfono','current':True,'mine':True,'lastUsedAt':'2026-11-02T10:00:00+00:00'}],'lockChoices':[5,15,30,60],'deviceRemembered':True,'lockMinutes':None,'team':None}
    elif path=='/api/workspace/won':d={'period':'2026-11','items':WON,'totals':{'count':4,'sum':1730.5,'missing':2},'canEdit':True,'sellers':[]}
    elif path=='/api/workspace/won-alert' and FLAGS['alert']:d={'month':'2026-11','count':2,'items':WON[:2],'oldestDays':3,'days':7,'escalate':False,'canEdit':True,'team':None}
    elif path=='/api/workspace/dashboard':d={'restricted':True,'conversations':{'total':9,'unread':6,'waiting':4},'contacts':None,'leads':{'today':3,'mine':None,'linked':True,'range':{}},'opportunities':{'count':7,'value':8200,'stages':[]},'readAt':'2026-11-02T10:00:00+00:00'}
    else:d={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps(d))
def fresh(b,tour_done=True,w=390,h=844):
    ctx=b.new_context(viewport={'width':w,'height':h},device_scale_factor=2,service_workers='block',locale='es-ES')
    ctx.add_init_script("try{localStorage.setItem('agora-welcome-later','1');"+("localStorage.setItem('agora-tour-done','1');" if tour_done else "")+"localStorage.setItem('agora-passkey-asked','1')}catch(e){}")
    page=ctx.new_page();page.route('**/api/**',api);errors=[];page.on('pageerror',lambda e:errors.append(str(e)));return page,errors
def shot(page,name):
    page.wait_for_timeout(700);path=os.path.join(IMG,name);page.screenshot(path=path,animations='disabled')
    # Paleta reducida y 780 px de ancho: se ven nítidas en el manual y el paquete pesa poco.
    from PIL import Image
    im=Image.open(path).convert('RGB');im=im.resize((780,round(im.height*780/im.width)),Image.LANCZOS)
    im.quantize(colors=96,method=Image.MEDIANCUT,dither=Image.NONE).save(path,optimize=True);print('captura',name,os.path.getsize(path)//1024,'KB')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page,errs=fresh(b)
    page.goto(BASE+'/bandeja');page.wait_for_selector('[data-live-id]');page.wait_for_timeout(5200)
    page.wait_for_selector('#won-dialog[open]',timeout=9000)
    shot(page,'06-alerta.png');FLAGS['alert']=False
    page.reload();page.wait_for_selector('[data-live-id]')
    page.evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close())")
    shot(page,'01-conversaciones.png')
    page.locator('[data-live-filter="waiting"]').click();page.wait_for_timeout(500);shot(page,'03-sin-respuesta.png')
    page.locator('[data-live-filter="all"]').click();page.wait_for_timeout(300)
    page.locator('[data-live-id="c0"]').evaluate('n=>n.click()');page.wait_for_selector('.channel-current');page.locator('.channel-current').click();shot(page,'02-canal.png')
    page.keyboard.press('Escape')
    page.goto(BASE+'/contactos');page.wait_for_selector('[data-contact-id],.contact-row,.record-row',timeout=8000)
    page.locator('[data-contact-id],.contact-row,.record-row').first.evaluate('n=>n.click()');page.wait_for_timeout(900);shot(page,'04-ficha.png')
    page.goto(BASE+'/oportunidades');page.wait_for_timeout(500)
    page.evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close())")
    tab=page.locator('[data-board-tab="won"],#board-tab-won,button:has-text("Ganadas")').first;tab.click();page.wait_for_selector('.won-item,.won-row',timeout=8000);shot(page,'05-ganadas.png')
    page.goto(BASE+'/tablero');page.wait_for_timeout(900);shot(page,'07-tablero.png')
    page.locator('#profile-top').click();page.wait_for_selector('#text-large');shot(page,'09-perfil.png')
    page.close()
    page,e2=fresh(b,tour_done=False)
    page.goto(BASE+'/bandeja');page.wait_for_selector('.tour-card h2',timeout=10000)
    for _ in range(8):
        if 'ayuda' in page.locator('.tour-card h2').inner_text().lower():break
        page.locator('.tour-card button.primary').first.click();page.wait_for_timeout(450)
    shot(page,'08-ayuda.png');page.close()
    # PDF: la misma página /ayuda con los estilos de impresión
    page,e3=fresh(b,w=800,h=1100)
    page.goto(BASE+'/ayuda');page.wait_for_selector('.manual',timeout=8000);page.wait_for_load_state('networkidle')
    page.evaluate("Promise.all([...document.images].map(i=>i.complete?1:new Promise(r=>i.onload=i.onerror=r)))")
    page.pdf(path=os.path.join(ROOT,'manual-vendedores.pdf'),format='A4',print_background=True,prefer_css_page_size=True)
    print('PDF listo')
    assert not (errs+e2+e3),(errs,e2,e3)
    b.close()
