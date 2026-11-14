#!/usr/bin/env python3
"""Cuenta simulada (solo desarrollo) para probar Agora New App sin la cuenta real.
Uso: python3 tests/fake-ghl.py 8099  y arrancar la API con APP_ENV=development AGORA_GHL_FAKE=http://127.0.0.1:8099
Responde con la misma forma que la API v2 en las rutas que usa el motor. Anota cada petición en fake-ghl.log."""
import json,sys,time,re
from http.server import BaseHTTPRequestHandler,HTTPServer
from urllib.parse import urlparse,parse_qs
LOC='LOCasocim000000001'
NOW=int(time.time()*1000)
USERS=[{'id':'u-admin','name':'Valentina Castillo','firstName':'Valentina','lastName':'Castillo','email':'admin.asocim@agora.test','roles':{'type':'account','role':'admin'},'permissions':{}},
       {'id':'u-paola','name':'Paola Rojas','firstName':'Paola','lastName':'Rojas','email':'ejecutivo1.asocim@agora.test','roles':{'type':'account','role':'user'},'permissions':{'conversationsEnabled':True,'contactsEnabled':True,'opportunitiesEnabled':True}}]
CONTACTS={f'k{i}':{'id':f'k{i}','locationId':LOC,'contactName':n,'firstName':n.split()[0],'lastName':n.split()[1],'email':f'{n.split()[0].lower()}@ejemplo.test','phone':f'+58414000000{i}','assignedTo':a,'tags':['cliente'],'dateAdded':'2026-11-10T10:00:00Z','customFields':[]}
          for i,(n,a) in enumerate([('Lucía Fernández','u-paola'),('Marcos Ortega','u-admin'),('Camila Reyes','u-paola'),('Pedro Navarro',None)])}
CONVS=[{'id':f'c{i}','locationId':LOC,'contactId':k,'contactName':c['contactName'],'fullName':c['contactName'],'phone':c['phone'],'email':c['email'],'lastMessageType':'TYPE_WHATSAPP' if i%2==0 else 'TYPE_SMS',
        'lastMessageBody':['Hola, ¿tienen cupo el sábado?','Gracias, quedo atento','¿Cuánto cuesta el plan anual?','Buenas tardes'][i],'lastMessageDate':NOW-i*600000,'lastMessageDirection':'inbound','unreadCount':[2,0,1,0][i],
        'assignedTo':c['assignedTo'],'type':'TYPE_PHONE'} for i,(k,c) in enumerate(CONTACTS.items())]
MSGS={cv['id']:[{'id':f"{cv['id']}-m{j}",'conversationId':cv['id'],'contactId':cv['contactId'],'locationId':LOC,'body':t,'direction':d,'dateAdded':f'2026-11-13T1{j}:00:00.000Z','messageType':cv['lastMessageType'],'status':'delivered','attachments':[]}
      for j,(t,d) in enumerate([('Hola, buenos días','inbound'),('¡Hola! ¿En qué te ayudo?','outbound'),(cv['lastMessageBody'],'inbound')])] for cv in CONVS}
PIPES=[{'id':'p1','name':'Embudo de ventas','stages':[{'id':'s1','name':'Lead nuevo'},{'id':'s2','name':'Propuesta enviada'},{'id':'s3','name':'Negociación'}]}]
OPPS=[{'id':f'o{i}','name':n,'contactId':k,'contact':{'id':k,'name':CONTACTS[k]['contactName'],'email':CONTACTS[k]['email'],'phone':CONTACTS[k]['phone']},'monetaryValue':v,'pipelineId':'p1','pipelineStageId':st,'status':s,'assignedTo':CONTACTS[k]['assignedTo'],'locationId':LOC,'updatedAt':'2026-11-12T10:00:00Z','lastStatusChangeAt':'2026-11-12T10:00:00Z','createdAt':'2026-11-01T10:00:00Z'}
      for i,(n,k,v,st,s) in enumerate([('Reserva sábado','k0',250,'s1','open'),('Plan anual','k2',900,'s2','open'),('Curso intensivo','k1',300,'s3','won'),('Asesoría','k3',150,'s1','open')])]
SENT=[]
TASKS=[]
SOCIAL_ACCOUNTS=[{'id':'acc-fb','profileId':'fb1','name':'ASOCIM Admisiones','platform':'facebook','type':'page','isExpired':False},
 {'id':'acc-ig','profileId':'ig1','name':'@asocim','platform':'instagram','type':'business','isExpired':False},
 {'id':'acc-li','profileId':'li1','name':'ASOCIM','platform':'linkedin','type':'page','isExpired':True}]
POSTS=[{'_id':'post-ig','summary':'Así fue la graduación de la cohorte de octubre.','status':'published','platform':'instagram','accountIds':['acc-ig'],'displayDate':'2026-11-08T18:00:00.000Z','publishedAt':'2026-11-08T18:00:00.000Z','media':[{'url':'https://ejemplo.test/grad.jpg','type':'image/jpeg'}],'type':'post','source':'composer'},
 {'_id':'post-pub','summary':'Abrimos inscripciones para el diplomado de enero.','status':'published','platform':'facebook','accountIds':['acc-fb'],'displayDate':'2026-11-10T14:00:00.000Z','publishedAt':'2026-11-10T14:00:00.000Z','media':[],'type':'post','source':'composer'},
 {'_id':'post-err','summary':'Clase abierta este sábado.','status':'failed','error':'Token de la página vencido','platform':'linkedin','accountIds':['acc-li'],'displayDate':'2026-11-12T15:00:00.000Z','media':[],'type':'post','source':'composer'}]
def log(*a):
    with open('fake-ghl.log','a') as f:f.write(' '.join(map(str,a))+'\n')
class H(BaseHTTPRequestHandler):
    def log_message(self,*a):pass
    def out(self,code,data):
        b=json.dumps(data).encode();self.send_response(code);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(b)));self.end_headers();self.wfile.write(b)
    def body(self):
        n=int(self.headers.get('Content-Length') or 0);
        try:return json.loads(self.rfile.read(n) or b'{}')
        except Exception:return {}
    def route(self,m):
        u=urlparse(self.path);p=u.path;q={k:v[0] for k,v in parse_qs(u.query).items()};b=self.body() if m in('POST','PUT','DELETE') else {}
        log(m,self.path,self.headers.get('Version'),json.dumps(b)[:200] if b else '')
        if not (self.headers.get('Authorization') or '').startswith('Bearer pit-'):return self.out(401,{'message':'Invalid JWT'})
        if p==f'/locations/{LOC}':return self.out(200,{'location':{'id':LOC,'name':'ASOCIM Admisiones'}})
        if re.match(r'^/saas/companies/[\w-]+/wallet-transactions$',p) and m=='POST':
            tx=[{'locationId':LOC,'locationName':'ASOCIM Admisiones','amount':-1.25,'type':'SMS','description':'SMS salientes','settlementTime':'2026-11-10T10:00:00Z'},
                {'locationId':LOC,'locationName':'ASOCIM Admisiones','amount':-3.40,'type':'WhatsApp','description':'Conversaciones de WhatsApp','settlementTime':'2026-11-11T10:00:00Z'},
                {'locationId':None,'locationName':None,'amount':50,'type':'Recharge','description':'Recarga de saldo','settlementTime':'2026-11-02T10:00:00Z'}]
            return self.out(201,{'transactions':tx if b.get('skip',0)==0 else [],'total':len(tx)})
        if p.startswith('/locations/'+LOC+'/tags'):return self.out(200,{'tags':[{'name':'cliente'}]})
        if p.startswith('/locations/'+LOC+'/customFields') or p.startswith('/custom-fields'):return self.out(200,{'customFields':[],'fields':[]})
        if p.startswith('/locations/'):return self.out(404,{'message':'Location not found'})
        if p=='/users/':return self.out(200,{'users':USERS})
        if p=='/conversations/search':
            items=[c for c in CONVS if 'assignedTo' not in q or c['assignedTo']==q['assignedTo']]
            if q.get('status')=='unread':items=[c for c in items if c['unreadCount']>0]
            if q.get('query'):items=[c for c in items if q['query'].lower() in (c['contactName']+c['phone']+c['email']).lower()]
            if q.get('contactId'):items=[c for c in items if c['contactId']==q['contactId']]
            return self.out(200,{'conversations':items,'total':len(items)})
        m2=re.match(r'^/conversations/([\w-]+)/messages$',p)
        if m2:return self.out(200,{'messages':{'lastMessageId':None,'nextPage':False,'messages':list(reversed(MSGS.get(m2.group(1),[])))}})
        if p=='/conversations/messages' and m=='POST':
            SENT.append(b);mid=f'sent{len(SENT)}'
            for c in CONVS:
                if c['contactId']==b.get('contactId'):MSGS[c['id']].append({'id':mid,'conversationId':c['id'],'contactId':c['contactId'],'locationId':LOC,'body':b.get('message'),'direction':'outbound','dateAdded':'2026-11-13T23:59:00.000Z','messageType':'TYPE_'+str(b.get('type','')).upper(),'status':'sent','attachments':[]})
            return self.out(201,{'messageId':mid,'conversationId':'c0','msg':'Message queued'})
        m2=re.match(r'^/conversations/([\w-]+)$',p)
        if m2 and m=='PUT':return self.out(200,{'success':True,'conversation':{'id':m2.group(1)}})
        if m2 and m=='GET':return self.out(200,next((c for c in CONVS if c['id']==m2.group(1)),{}))
        if p=='/contacts/' and m=='POST':
            if any((b.get('phone') and c.get('phone')==b.get('phone')) or (b.get('email') and c.get('email')==b.get('email')) for c in CONTACTS.values()):return self.out(400,{'message':'This location does not allow duplicated contacts.','meta':{'matchingField':'phone'}})
            k='n'+__import__('uuid').uuid4().hex[:18];CONTACTS[k]={'id':k,'locationId':LOC,'contactName':b.get('name'),'firstName':b.get('firstName'),'lastName':b.get('lastName'),'email':b.get('email'),'phone':b.get('phone'),'assignedTo':b.get('assignedTo'),'tags':[],'dateAdded':'2026-11-14T10:00:00Z','customFields':[],'source':b.get('source')}
            return self.out(201,{'contact':CONTACTS[k]})
        if p=='/contacts/' and m=='GET':return self.out(200,{'contacts':list(CONTACTS.values()),'meta':{'total':len(CONTACTS)}})
        if p=='/contacts/search' and m=='POST':
            who=None
            for f in b.get('filters',[]):
                if f.get('field')=='assignedTo':who=f['value'][0] if isinstance(f['value'],list) else f['value']
            items=[c for c in CONTACTS.values() if who is None or c['assignedTo']==who]
            return self.out(200,{'contacts':items,'total':len(items)})
        m2=re.match(r'^/contacts/([\w-]+)$',p)
        if m2 and m2.group(1) in CONTACTS:
            if m=='PUT':CONTACTS[m2.group(1)].update({k:v for k,v in b.items() if k in('firstName','lastName','email','phone','assignedTo')});return self.out(200,{'contact':CONTACTS[m2.group(1)]})
            return self.out(200,{'contact':CONTACTS[m2.group(1)]})
        m3=re.match(r'^/contacts/([\w-]+)/tasks$',p)
        if m3 and m=='POST':
            t={'id':'t'+__import__('uuid').uuid4().hex[:20],'contactId':m3.group(1),'title':b.get('title'),'body':b.get('body'),'dueDate':b.get('dueDate'),'completed':False};TASKS.append(t);return self.out(201,{'task':t})
        if m3 and m=='GET':return self.out(200,{'tasks':[t for t in TASKS if t['contactId']==m3.group(1)]})
        m3=re.match(r'^/contacts/([\w-]+)/tasks/([\w-]+)(/completed)?$',p)
        if m3 and m=='PUT':
            t=next((x for x in TASKS if x['id']==m3.group(2) and x['contactId']==m3.group(1)),None)
            if not t:return self.out(404,{'message':'task not found'})
            t.update({k:v for k,v in b.items() if k in('title','body','dueDate','completed')});return self.out(200,{'task':t})
        m4=re.match(r'^/social-media-posting/([\w-]+)/(accounts|posts/list|posts|posts/([\w-]+))$',p)
        if m4 and m4.group(1)!=LOC:return self.out(403,{'message':'not authorized for this location'})
        if m4 and m4.group(2)=='accounts' and m=='GET':return self.out(200,{'success':True,'statusCode':200,'message':'Fetched Accounts','results':{'accounts':SOCIAL_ACCOUNTS,'groups':[]}})
        if m4 and m4.group(2)=='posts/list' and m=='POST':
            t=b.get('type','all');items=[x for x in POSTS if t in('all','recent') or x['status']==t]
            if b.get('accounts'):acc=set(a.strip() for a in b['accounts'].split(','));items=[x for x in items if acc & set(x['accountIds'])]
            if not b.get('fromDate') or not b.get('includeUsers'):return self.out(422,{'message':'fromDate and includeUsers are required'})
            return self.out(201,{'success':True,'statusCode':201,'message':'Fetched Posts','results':{'posts':items,'count':len(items)}})
        if m4 and m4.group(2)=='posts' and m=='POST':
            if not b.get('userId') or not b.get('accountIds') or not b.get('type'):return self.out(422,{'message':'userId, accountIds and type are required'})
            pid='post-'+__import__('uuid').uuid4().hex[:10]
            POSTS.append({'_id':pid,'summary':b.get('summary'),'status':b.get('status') or 'scheduled','platform':'facebook','accountIds':b['accountIds'],'displayDate':b.get('scheduleDate') or '2026-11-20T15:00:00.000Z','media':b.get('media') or [],'type':'post','source':'composer','createdBy':b.get('userId')})
            return self.out(201,{'success':True,'statusCode':201,'message':'Created Post','results':{'post':POSTS[-1]}})
        if m4 and m4.group(3) and m=='DELETE':
            before=len(POSTS);POSTS[:]=[x for x in POSTS if x['_id']!=m4.group(3)]
            return self.out(200,{'success':True}) if len(POSTS)<before else self.out(404,{'message':'Post not found'})
        if p=='/social-media-posting/statistics' and m=='POST':
            perfiles={a['profileId'] for a in SOCIAL_ACCOUNTS}
            if __import__('os').environ.get('FAKE_STATS_FAIL') or not b.get('profileIds') or any(x not in perfiles for x in b['profileIds']):return self.out(422,{'message':'profileIds must be profile ids from /accounts'})
            return self.out(201,{'results':{'dayRange':['2026-11-08','2026-11-09','2026-11-10','2026-11-11','2026-11-12','2026-11-13','2026-11-14'],'totals':{'posts':3,'likes':41,'followers':1280,'impressions':2350,'comments':7},'breakdowns':{'posts':{'total':3,'totalChange':50},'impressions':{'total':2350,'totalChange':-12},'reach':{'total':1900,'totalChange':8}},'platformTotals':{'impressions':{'facebook':1500,'instagram':850},'followers':{'facebook':900,'instagram':380},'likes':{'facebook':25,'instagram':16}}},'message':'ok'})
        if p=='/__tasks':return self.out(200,{'tasks':TASKS})
        if re.match(r'^/contacts/[\w-]+/(notes|tasks|appointments)$',p):
            key=p.rsplit('/',1)[1];return self.out(200,{key:[] ,'events':[]})
        if p=='/opportunities/pipelines':return self.out(200,{'pipelines':PIPES})
        if p=='/opportunities/search':
            items=[o for o in OPPS if (q.get('status') in(None,'all') or o['status']==q.get('status')) and ('assigned_to' not in q or o['assignedTo']==q['assigned_to']) and ('contact_id' not in q or o['contactId']==q['contact_id'])]
            return self.out(200,{'opportunities':items,'meta':{'total':len(items)}})
        m2=re.match(r'^/opportunities/([\w-]+)$',p)
        if m2:
            o=next((x for x in OPPS if x['id']==m2.group(1)),None)
            if not o:return self.out(404,{'message':'not found'})
            if m=='PUT':o.update({k:v for k,v in b.items() if k in('pipelineStageId','monetaryValue','name','status','assignedTo')})
            return self.out(200,{'opportunity':o})
        if p.startswith('/locations/') or p.startswith('/custom-fields') or p.endswith('/customFields'):return self.out(200,{'customFields':[],'tags':[]})
        if p.startswith('/locations/'+LOC+'/tags'):return self.out(200,{'tags':[{'name':'cliente'}]})
        log('SIN RUTA',m,self.path);return self.out(404,{'message':'Not found (simulada)'})
    def do_GET(self):self.route('GET')
    def do_POST(self):self.route('POST')
    def do_PUT(self):self.route('PUT')
    def do_DELETE(self):self.route('DELETE')
HTTPServer(('127.0.0.1',int(sys.argv[1]) if len(sys.argv)>1 else 8099),H).serve_forever()
