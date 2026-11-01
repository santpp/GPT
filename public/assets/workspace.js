// The subaccount in use is chosen once, in the header, and governs every screen.
const KEY='agora-connection';
const listeners=new Set();
let connections=[],current='',profile=null,reason=null;

const remembered=()=>{try{return localStorage.getItem(KEY)||''}catch{return ''}};
const remember=id=>{try{id?localStorage.setItem(KEY,id):localStorage.removeItem(KEY)}catch{}};

export const workspace={
  get me(){return profile},
  /** Filtro de la bandeja que otra pantalla pide al abrirla (por ejemplo, «sin respuesta» desde el tablero). */
  pendingFilter:null,
  get connections(){return connections},
  /** The account every screen must read. Empty while none is available. */
  current(){return current},
  setProfile(value){profile=value},
  setConnections(items,why=null){
    connections=Array.isArray(items)?items:[];reason=why;
    const previous=current;
    const wanted=current||remembered();
    current=connections.some(c=>String(c.id)===String(wanted))?String(wanted):(connections[0]?String(connections[0].id):'');
    remember(current);
    if(current!==previous)notify();
    return current;
  },
  select(id){
    const value=String(id??'');
    if(!connections.some(c=>String(c.id)===value)||value===current)return false;
    current=value;remember(value);notify();return true;
  },
  /** Lo que se dice cuando no hay subcuenta que mostrar: distinto para quien administra Ágora y para quien usa una subcuenta. */
  noAccountText(what){
    if(profile?.user?.role==='administrator')return `Añade una cuenta desde Conexiones para ver ${what}.`;
    if(reason==='no_permission')return 'Tu usuario no tiene activadas las conversaciones en su subcuenta. Pídele al administrador de la subcuenta que las active.';
    if(reason==='no_membership')return 'Tu correo todavía no está asociado a ninguna subcuenta en Ágora. Pide al administrador que sincronice el equipo.';
    return 'Tu usuario todavía no tiene acceso a ninguna subcuenta.';
  },
  /** Moneda de la subcuenta en uso: euros o dólares (dólares si no se sabe). */
  currency(){const found=connections.find(c=>String(c.id)===current);return found?.currency==='EUR'?'EUR':'USD'},
  name(){const found=connections.find(c=>String(c.id)===current);return found?found.name:''},
  onChange(listener){listeners.add(listener);return()=>listeners.delete(listener)},
  reset(){connections=[];current='';profile=null;reason=null;remember('')},
};

function notify(){for(const listener of [...listeners])listener(current)}

/**
 * Lo que se le dice a la persona cuando algo falla. Los mensajes del servidor ya
 * vienen en castellano y se muestran tal cual; un fallo del propio navegador
 * (sin red, o un dato que no se pudo pintar) no se enseña en inglés técnico.
 */
export function friendly(error){
  if(!navigator.onLine)return 'Sin conexión. Se reintentará al volver la red.';
  if(error instanceof TypeError||error instanceof RangeError||error instanceof ReferenceError||error instanceof SyntaxError)
    return 'No se pudo mostrar esta información. Vuelve a entrar en un momento.';
  return error?.message||'No se pudo completar. Vuelve a intentarlo.';
}
