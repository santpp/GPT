// The subaccount in use is chosen once, in the header, and governs every screen.
const KEY='agora-connection';
const listeners=new Set();
let connections=[],current='',profile=null;

const remembered=()=>{try{return localStorage.getItem(KEY)||''}catch{return ''}};
const remember=id=>{try{id?localStorage.setItem(KEY,id):localStorage.removeItem(KEY)}catch{}};

export const workspace={
  get me(){return profile},
  get connections(){return connections},
  /** The account every screen must read. Empty while none is available. */
  current(){return current},
  setProfile(value){profile=value},
  setConnections(items){
    connections=Array.isArray(items)?items:[];
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
  name(){const found=connections.find(c=>String(c.id)===current);return found?found.name:''},
  onChange(listener){listeners.add(listener);return()=>listeners.delete(listener)},
  reset(){connections=[];current='';profile=null;remember('')},
};

function notify(){for(const listener of [...listeners])listener(current)}
