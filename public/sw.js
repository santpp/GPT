const CACHE='agora-shell-v21';
const SHELL=['/','/bandeja','/contactos','/oportunidades','/assets/app.css','/assets/layout.css','/assets/app.js','/assets/workspace.js','/assets/contacts.js','/assets/board.js','/assets/icon.svg','/assets/agora-logo.svg','/assets/integrations.js','/assets/live-inbox.js','/manifest.webmanifest'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL.map(url=>new Request(url,{cache:'reload'}))))));
self.addEventListener('message',e=>{if(e.data?.type==='ACTIVATE')self.skipWaiting()});
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('agora-shell-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||u.origin!==self.location.origin||u.pathname.startsWith('/api/'))return;
 if(e.request.mode==='navigate'){e.respondWith(fetch(e.request).catch(()=>caches.match(u.pathname).then(hit=>hit||caches.match('/'))));return}
 if(SHELL.includes(u.pathname))e.respondWith(caches.match(e.request).then(hit=>hit||fetch(e.request)));
});
