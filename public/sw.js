const CACHE='agora-shell-v22';
const SHELL=['/','/bandeja','/contactos','/oportunidades','/tablero','/assets/app.css','/assets/layout.css','/assets/app.js','/assets/workspace.js','/assets/contacts.js','/assets/board.js','/assets/icon.svg','/assets/agora-logo.svg','/assets/integrations.js','/assets/live-inbox.js','/assets/contact-editor.js','/assets/dashboard.js','/assets/opportunity-editor.js','/assets/native.js','/assets/push.js','/manifest.webmanifest'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL.map(url=>new Request(url,{cache:'reload'}))))));
self.addEventListener('message',e=>{if(e.data?.type==='ACTIVATE')self.skipWaiting()});
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('agora-shell-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
// A notice arrives for a conversation: it is shown, and opening it goes to that
// conversation. Nothing of the message is stored by the worker.
self.addEventListener('push',event=>{
 let data={};
 try{data=event.data?event.data.json():{}}catch{data={}}
 const payload=data.notification||data.data||data;
 const title=typeof payload.title==='string'&&payload.title?payload.title:'Ágora';
 const body=typeof payload.body==='string'?payload.body:'Tienes un mensaje nuevo.';
 const path=typeof (data.data?.path)==='string'?data.data.path:(typeof payload.path==='string'?payload.path:'/bandeja');
 const safe=/^\/[A-Za-z0-9/_-]{0,120}$/.test(path)?path:'/bandeja';
 event.waitUntil(self.registration.showNotification(title,{body,data:{path:safe},tag:safe,icon:'/assets/icon.svg',badge:'/assets/icon.svg'}));
});
self.addEventListener('notificationclick',event=>{
 event.notification.close();
 const path=event.notification.data?.path||'/bandeja';
 event.waitUntil((async()=>{
  const open=await self.clients.matchAll({type:'window',includeUncontrolled:true});
  for(const client of open){if(new URL(client.url).origin===self.location.origin){await client.focus();await client.navigate(path).catch(()=>{});return}}
  await self.clients.openWindow(path);
 })());
});
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||u.origin!==self.location.origin||u.pathname.startsWith('/api/'))return;
 if(e.request.mode==='navigate'){e.respondWith(fetch(e.request).catch(()=>caches.match(u.pathname).then(hit=>hit||caches.match('/'))));return}
 if(SHELL.includes(u.pathname))e.respondWith(caches.match(e.request).then(hit=>hit||fetch(e.request)));
});
