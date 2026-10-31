// Notas de voz. Se graba en el navegador y se deja en un formato que WhatsApp acepta:
//  - Chrome (Android, ordenador) graba Opus dentro de WebM, que ningún canal admite: se
//    reempaqueta a OGG sin tocar el audio (no se recodifica, así no pierde calidad).
//  - Firefox graba OGG/Opus: sirve tal cual.
//  - Safari (iPhone) graba AAC en MP4: sirve tal cual, y WhatsApp lo enseña como audio.
// Sólo el OGG con Opus sale en WhatsApp como nota de voz.

export const MAX_SECONDS=180;

export const supported=()=>typeof MediaRecorder==='function'&&!!navigator.mediaDevices?.getUserMedia;

// Opus primero (sale como nota de voz); AAC sólo si el navegador no sabe grabar Opus.
const PREFERRED=['audio/ogg;codecs=opus','audio/webm;codecs=opus','audio/webm','audio/mp4'];
const pickType=()=>PREFERRED.find(type=>{try{return MediaRecorder.isTypeSupported(type)}catch{return false}})||'';

/** Empieza a grabar. Devuelve {stop(), cancel(), seconds()}; stop() da {blob,mime,seconds}. */
export async function record(){
  let stream;
  try{stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}})}
  catch(error){
    const denied=error?.name==='NotAllowedError'||error?.name==='SecurityError';
    throw Object.assign(new Error(denied?'El micrófono está bloqueado. Actívalo para Ágora en los ajustes del navegador y vuelve a intentarlo.':error?.name==='NotFoundError'?'No se encontró ningún micrófono.':'No se pudo usar el micrófono.'),{code:denied?'mic_denied':'mic_failed'});
  }
  const type=pickType();
  const recorder=new MediaRecorder(stream,type?{mimeType:type,audioBitsPerSecond:32000}:{audioBitsPerSecond:32000});
  const chunks=[];const started=Date.now();let timer=null,finish;
  recorder.ondataavailable=event=>{if(event.data&&event.data.size)chunks.push(event.data)};
  const closed=new Promise(done=>{finish=done});
  recorder.onstop=()=>{clearTimeout(timer);stream.getTracks().forEach(track=>track.stop());finish()};
  recorder.start(250);
  timer=setTimeout(()=>{if(recorder.state!=='inactive')recorder.stop()},MAX_SECONDS*1000);
  const seconds=()=>Math.round((Date.now()-started)/1000);
  return {
    seconds,
    cancel(){chunks.length=0;if(recorder.state!=='inactive')recorder.stop();return closed},
    async stop(){
      const length=seconds();
      if(recorder.state!=='inactive')recorder.stop();
      await closed;
      const raw=new Blob(chunks,{type:recorder.mimeType||type||'audio/webm'});
      return {...(await toSendable(raw)),seconds:length};
    },
  };
}

/** El audio grabado, en un formato que WhatsApp acepta. */
export async function toSendable(blob){
  const kind=String(blob.type||'').toLowerCase();
  if(kind.startsWith('audio/ogg'))return {blob:new Blob([blob],{type:'audio/ogg'}),mime:'audio/ogg'};
  if(kind.startsWith('audio/mp4'))return {blob:new Blob([blob],{type:'audio/mp4'}),mime:'audio/mp4'};
  const bytes=new Uint8Array(await blob.arrayBuffer());
  const ogg=webmToOgg(bytes);
  return {blob:new Blob([ogg],{type:'audio/ogg'}),mime:'audio/ogg'};
}

// ---- WebM (Matroska) -> OGG/Opus, sin recodificar --------------------------------------------

const ID={SEGMENT:0x18538067,CLUSTER:0x1F43B675,TRACKS:0x1654AE6B,TRACK_ENTRY:0xAE,BLOCK_GROUP:0xA0,
  SIMPLE_BLOCK:0xA3,BLOCK:0xA1,CODEC_PRIVATE:0x63A2,CODEC_ID:0x86,TRACK_NUMBER:0xD7};
const MASTERS=new Set([ID.SEGMENT,ID.CLUSTER,ID.TRACKS,ID.TRACK_ENTRY,ID.BLOCK_GROUP]);

function vint(bytes,at,keepMarker){
  const first=bytes[at];if(first===undefined||first===0)return null;
  let length=1,mask=0x80;while(!(first&mask)){length++;mask>>=1}
  if(at+length>bytes.length)return null;
  let value=keepMarker?first:first&(mask-1);let unknown=(first&(mask-1))===mask-1;
  for(let i=1;i<length;i++){value=value*256+bytes[at+i];if(bytes[at+i]!==0xFF)unknown=false}
  return {value,length,unknown};
}

/** Los paquetes Opus de un WebM, con el OpusHead de su pista. */
export function readWebm(bytes){
  let at=0,head=null,trackNumber=null,currentTrack=null,codec=null;
  const packets=[];
  while(at<bytes.length){
    const id=vint(bytes,at,true);if(!id)break;
    const size=vint(bytes,at+id.length,false);if(!size)break;
    const start=at+id.length+size.length;
    if(MASTERS.has(id.value)){at=start;if(id.value===ID.TRACK_ENTRY){currentTrack=null;codec=null}continue}   // se desciende: sus hijos van a continuación
    if(size.unknown)break;
    const end=start+size.value;if(end>bytes.length)break;
    if(id.value===ID.TRACK_NUMBER){currentTrack=0;for(let i=start;i<end;i++)currentTrack=currentTrack*256+bytes[i]}
    else if(id.value===ID.CODEC_ID){codec=String.fromCharCode(...bytes.subarray(start,end))}
    else if(id.value===ID.CODEC_PRIVATE&&(codec==='A_OPUS'||codec===null)){head=bytes.slice(start,end)}
    if(codec==='A_OPUS'&&currentTrack!==null&&trackNumber===null)trackNumber=currentTrack;
    if(id.value===ID.SIMPLE_BLOCK||id.value===ID.BLOCK){
      const track=vint(bytes,start,false);if(track){
        const flags=bytes[start+track.length+2];
        if(flags&0x06)throw new Error('Audio con lacing: no soportado');
        if(trackNumber===null||track.value===trackNumber)packets.push(bytes.slice(start+track.length+3,end));
      }
    }
    at=end;
  }
  if(!packets.length)throw new Error('La grabación no tiene audio.');
  return {head,packets};
}

/** Cuántas muestras a 48 kHz dura un paquete Opus, según su primer byte. */
export function opusSamples(packet){
  if(!packet.length)return 0;
  const toc=packet[0],config=toc>>3,code=toc&3;
  let ms;
  if(config<12)ms=[10,20,40,60][config%4];
  else if(config<16)ms=[10,20][config%2];
  else ms=[2.5,5,10,20][config%4];
  const frames=code===0?1:code===3?(packet[1]&0x3F):2;
  return Math.round(frames*ms*48);
}

const CRC=(()=>{const table=new Uint32Array(256);for(let i=0;i<256;i++){let c=i<<24;for(let k=0;k<8;k++)c=(c&0x80000000)?((c<<1)^0x04C11DB7):(c<<1);table[i]=c>>>0}return table})();
const crc32=bytes=>{let c=0;for(let i=0;i<bytes.length;i++)c=((c<<8)^CRC[((c>>>24)^bytes[i])&0xFF])>>>0;return c>>>0};

function page(headerType,granule,serial,sequence,packets){
  // Cada paquete se parte en segmentos de 255; el último, más corto, lo cierra.
  const lacing=[];for(const packet of packets){let left=packet.length;while(left>=255){lacing.push(255);left-=255}lacing.push(left)}
  if(lacing.length>255)throw new Error('Página demasiado grande');
  const bodyLength=packets.reduce((sum,p)=>sum+p.length,0);
  const out=new Uint8Array(27+lacing.length+bodyLength);const view=new DataView(out.buffer);
  out.set([0x4F,0x67,0x67,0x53,0,headerType]);
  view.setUint32(6,granule%0x100000000,true);view.setUint32(10,Math.floor(granule/0x100000000),true);
  view.setUint32(14,serial,true);view.setUint32(18,sequence,true);view.setUint32(22,0,true);
  out[26]=lacing.length;out.set(lacing,27);
  let offset=27+lacing.length;for(const packet of packets){out.set(packet,offset);offset+=packet.length}
  view.setUint32(22,crc32(out),true);
  return out;
}

export function webmToOgg(bytes){
  const {head,packets}=readWebm(bytes);
  const opusHead=head&&head.length>=19&&String.fromCharCode(...head.subarray(0,8))==='OpusHead'?head:Uint8Array.from([0x4F,0x70,0x75,0x73,0x48,0x65,0x61,0x64,1,1,0x38,0x01,0x80,0xBB,0,0,0,0,0]);
  const vendor=new TextEncoder().encode('Agora');
  const tags=new Uint8Array(8+4+vendor.length+4);tags.set(new TextEncoder().encode('OpusTags'));
  new DataView(tags.buffer).setUint32(8,vendor.length,true);tags.set(vendor,12);   // sin comentarios
  const serial=Math.floor(Math.random()*0xFFFFFFFF)>>>0;
  const pages=[page(0x02,0,serial,0,[opusHead]),page(0x00,0,serial,1,[tags])];
  let sequence=2,granule=0,group=[],groupSegments=0;
  const flush=last=>{pages.push(page(last?0x04:0x00,granule,serial,sequence++,group));group=[];groupSegments=0};
  packets.forEach((packet,index)=>{
    const segments=Math.floor(packet.length/255)+1;
    if(group.length&&groupSegments+segments>250)flush(false);
    group.push(packet);groupSegments+=segments;granule+=opusSamples(packet);
    if(index===packets.length-1)flush(true);
  });
  const total=pages.reduce((sum,p)=>sum+p.length,0);const out=new Uint8Array(total);
  let offset=0;for(const p of pages){out.set(p,offset);offset+=p.length}
  return out;
}
