/* API de producción: CSRF y contexto por pestaña; nunca guarda credenciales en localStorage. */
(() => {
 const original=window.fetch.bind(window);let csrf='',csrfPending=null,workspace=null;
 const csrfToken=()=>csrf?Promise.resolve(csrf):(csrfPending||=(async()=>{const r=await original('/api/session/csrf',{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw new Error('No se pudo iniciar la sesión segura.');const d=await r.json();return csrf=d.csrf;})().finally(()=>csrfPending=null));
 window.fetch=async(input,init={})=>{
  const url=new URL(typeof input==='string'?input:input.url,location.href);
  if(url.origin!==location.origin||!url.pathname.startsWith('/api/'))return original(input,init);
  const method=(init.method||'GET').toUpperCase();const headers=new Headers(init.headers||(input instanceof Request?input.headers:undefined));
  if(workspace)headers.set('X-Workspace-ID',String(workspace));
  if(!['GET','HEAD'].includes(method))headers.set('X-CSRF-Token',await csrfToken());
  const response=await original(input,{...init,headers,credentials:'same-origin'});
  if(response.ok&&['/api/me','/api/login','/api/workspace/active','/api/logout'].includes(url.pathname)){
   const d=await response.clone().json();if(d.csrf)csrf=d.csrf;
   if(url.pathname==='/api/workspace/active')workspace=d.accountId;
   else if(url.pathname==='/api/logout')workspace=null;
   else if(url.pathname==='/api/login'||d.activeTenantId!==null&&'activeTenantId' in d)workspace=d.activeTenantId;
  }
  if(response.status===419)csrf='';
  return response;
 };
 window.agoraWorkspace=()=>workspace;
})();
