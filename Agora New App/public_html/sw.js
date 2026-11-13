/* =====================================================================================
   Agora Digital App · trabajador de servicio
   -------------------------------------------------------------------------------------
   Es el de Ágora 1.10.0, con dos cambios: el nombre de la caché y la lista de archivos
   del armazón, que ahora son los de esta versión. Los avisos (push) y la apertura
   de la conversación al tocarlos siguen exactamente igual.
   ===================================================================================== */
const CACHE="agora-digital-app-2.1.19-20261113";
const SHELL=["/", "/manifest.webmanifest", "/assets/backend-client.js", "/assets/marca.css", "/assets/diseno.css", "/assets/ecosistema.css", "/assets/athena/athena-saludo.webp", "/assets/fonts/Author-Variable.woff2", "/assets/fonts/Author-VariableItalic.woff2", "/assets/fonts/Inter-Variable.ttf", "/assets/sistema.css", "/assets/modulos.css", "/assets/config.js", "/assets/agora-modulos.js", "/assets/agora-app.js", "/assets/agora-logo.png", "/assets/agora-mark.png", "/assets/favicon.ico", "/assets/icons/icon-192.png", "/assets/icons/icon-512.png", "/assets/icons/badge-96.png", "/assets/icons/apple-touch-icon.png"];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL.map(url=>new Request(url,{cache:'reload'}))))));
self.addEventListener('message',e=>{
 if(e.data?.type==='ACTIVATE')self.skipWaiting();
 // La app pregunta qué versión atiende los avisos, para notar si se quedó atrás.
 if(e.data?.type==='VERSION')e.ports?.[0]?.postMessage({cache:CACHE});
});
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>(k.startsWith('agora-shell-')||k.startsWith('agora-digital-app-'))&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
// A notice arrives for a conversation: it is shown, and opening it goes to that
// conversation. Nothing of the message is stored by the worker.
self.addEventListener('push',event=>{
 let data={};
 try{data=event.data?event.data.json():{}}catch{data={}}
 // El aviso trae el título y el texto arriba; los datos de navegación van aparte.
 // Antes se leían los datos como si fueran el aviso y se perdía quién escribía.
 const source=data.notification&&typeof data.notification==='object'?data.notification:data;
 const title=typeof source.title==='string'&&source.title?source.title:'Ágora';
 const body=typeof source.body==='string'&&source.body?source.body:'Tienes un mensaje nuevo.';
 const path=typeof data.data?.path==='string'?data.data.path:'/bandeja';
 const safe=/^\/[A-Za-z0-9/_-]{0,120}$/.test(path)?path:'/bandeja';
 const unread=Number.isFinite(Number(data.unread))?Math.max(0,Number(data.unread)):null;
 const connection=/^\d{1,12}$/.test(String(data.data?.connectionId??''))?String(data.data.connectionId):'';
 event.waitUntil(Promise.all([
  self.registration.showNotification(title,{body,data:{path:safe,connection},tag:safe,renotify:true,icon:'/assets/icons/icon-192.png',badge:'/assets/icons/badge-96.png'}),
  // El número del icono donde el sistema lo permite (iPhone instalada, escritorio).
  unread!==null&&self.navigator?.setAppBadge?(unread>0?self.navigator.setAppBadge(unread):self.navigator.clearAppBadge()).catch(()=>{}):Promise.resolve(),
 ]));
});
self.addEventListener('notificationclick',event=>{
 event.notification.close();
 const path=event.notification.data?.path||'/bandeja';
 const connection=/^\d{1,12}$/.test(String(event.notification.data?.connection??''))?event.notification.data.connection:'';
 // La dirección completa de Ágora, no sólo la ruta: así el sistema sabe a qué
 // app instalada pertenece y no la abre en una pestaña de Chrome.
 // Con la subcuenta del aviso, para abrir esa conversación y no otra.
 const target=new URL(path,self.location.origin).href+(connection&&path.startsWith('/conversations/')?'?c='+connection:'');
 event.waitUntil((async()=>{
  const open=await self.clients.matchAll({type:'window',includeUncontrolled:true});
  const mine=open.filter(client=>new URL(client.url).origin===self.location.origin);
  // Si la app ya está abierta, se reutiliza esa ventana, en primer plano.
  for(const client of mine){
   try{const focused=await client.focus();await (focused||client).navigate(target);return}catch(error){}
  }
  await self.clients.openWindow(target);
 })());
});
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||u.origin!==self.location.origin||u.pathname.startsWith('/api/'))return;
 if(e.request.mode==='navigate'){e.respondWith(fetch(e.request).catch(()=>caches.match(u.pathname).then(hit=>hit||caches.match('/'))));return}
 if(SHELL.includes(u.pathname))e.respondWith(caches.match(e.request).then(hit=>hit||fetch(e.request)));
});
