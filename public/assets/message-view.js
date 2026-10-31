// Cómo se ve un mensaje: sus adjuntos, su estado y, si no salió, por qué.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const KINDS=[
  ['image',/\.(png|jpe?g|gif|webp|avif|bmp|svg|heic)(\?|$)/i],
  ['audio',/\.(mp3|m4a|aac|ogg|oga|opus|wav|amr)(\?|$)/i],
  ['video',/\.(mp4|mov|webm|3gp|m4v)(\?|$)/i],
  ['pdf',/\.pdf(\?|$)/i],
];

/**
 * Sólo se siguen enlaces https de la propia cuenta, y el nombre que se enseña es
 * el del archivo, nunca un texto que el remitente pudiera usar para disfrazarlo.
 */
export function attachmentsOf(message){
  const raw=Array.isArray(message?.attachments)?message.attachments:[];
  const files=[];
  for(const item of raw.slice(0,10)){
    const url=typeof item==='string'?item:(item&&typeof item.url==='string'?item.url:null);
    if(!url)continue;
    let parsed;try{parsed=new URL(url)}catch{continue}
    if(parsed.protocol!=='https:')continue;
    let name;try{name=decodeURIComponent(parsed.pathname.split('/').filter(Boolean).pop()||'archivo')}catch{name='archivo'}
    const kind=(KINDS.find(([,test])=>test.test(parsed.pathname))||['file'])[0];
    files.push({url:parsed.href,name:name.slice(0,80),kind});
  }
  return files;
}

const ICONS={
  pdf:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3v4h4M9 13h6M9 16.5h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  file:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12.5 13.6 7a2.6 2.6 0 0 1 3.7 3.7l-7 7a4.2 4.2 0 0 1-6-6l6.6-6.6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};

/** Una imagen se ve en miniatura; audio y vídeo se reproducen ahí mismo. */
export function attachmentMarkup(file){
  if(file.kind==='image')return `<button type="button" class="att-image loading" data-view="${esc(file.url)}" data-name="${esc(file.name)}" aria-label="Ver la imagen ${esc(file.name)} a pantalla completa"><img src="${esc(file.url)}" alt="${esc(file.name)}" loading="lazy" decoding="async"></button>`;
  if(file.kind==='audio')return `<div class="att-media"><audio controls preload="none" src="${esc(file.url)}"></audio><a href="${esc(file.url)}" target="_blank" rel="noopener noreferrer">${esc(file.name)}</a></div>`;
  if(file.kind==='video')return `<div class="att-media"><video controls preload="metadata" playsinline src="${esc(file.url)}"></video><a href="${esc(file.url)}" target="_blank" rel="noopener noreferrer">${esc(file.name)}</a></div>`;
  return `<a class="att-file" href="${esc(file.url)}" target="_blank" rel="noopener noreferrer">${ICONS[file.kind]||ICONS.file}<span>${esc(file.name)}</span><b>Abrir</b></a>`;
}

/**
 * Tras pintar los mensajes: la miniatura deja de parpadear cuando carga, se
 * sustituye por un aviso claro si no carga, y abre el visor al tocarla.
 */
export function bindAttachments(root){
  root.querySelectorAll('.att-image').forEach(button=>{
    const img=button.querySelector('img');
    const done=()=>button.classList.remove('loading');
    if(img.complete&&img.naturalWidth)done();
    img.addEventListener('load',done,{once:true});
    img.addEventListener('error',()=>{
      button.outerHTML=`<a class="att-file broken" href="${esc(button.dataset.view)}" target="_blank" rel="noopener noreferrer">${ICONS.file}<span>No se pudo cargar la imagen</span><b>Abrir original</b></a>`;
    },{once:true});
    button.onclick=()=>openViewer(button.dataset.view,button.dataset.name);
  });
}

/** El visor: la imagen entera, ajustada a la pantalla, y la forma de salir. */
export function openViewer(url,name){
  document.querySelector('.viewer')?.remove();
  const viewer=document.createElement('div');
  viewer.className='viewer';
  viewer.setAttribute('role','dialog');viewer.setAttribute('aria-modal','true');viewer.setAttribute('aria-label',`Imagen ${name||''}`);
  viewer.innerHTML=`<div class="viewer-bar"><span>${esc(name||'Imagen')}</span><a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Abrir original</a><button type="button" class="viewer-close" aria-label="Cerrar la imagen">×</button></div><img src="${esc(url)}" alt="${esc(name||'Imagen')}">`;
  const close=()=>{viewer.remove();document.removeEventListener('keydown',onKey);opener?.focus?.()};
  const onKey=event=>{if(event.key==='Escape')close()};
  const opener=document.activeElement;
  viewer.addEventListener('click',event=>{if(event.target===viewer||event.target.tagName==='IMG')close()});
  viewer.querySelector('.viewer-close').onclick=close;
  document.addEventListener('keydown',onKey);
  document.body.appendChild(viewer);
  viewer.querySelector('.viewer-close').focus();
}

const STATES={
  pending:['Enviando…','wait'],queued:['Enviando…','wait'],scheduled:['Programado','wait'],
  sent:['Enviado','ok'],delivered:['Entregado','ok'],read:['Leído','ok'],opened:['Abierto','ok'],clicked:['Abierto','ok'],
  connected:['Conectada','ok'],received:['Recibido','ok'],
  failed:['No enviado','bad'],undelivered:['No entregado','bad'],opt_out:['Se dio de baja','bad'],
};
// Lo que suele estar detrás de un envío fallido, por canal. La cuenta no
// publica el motivo, así que se dice como habitual, nunca como el motivo cierto.
const CAUSES={
  TYPE_WHATSAPP:['Han pasado más de 24 horas desde el último mensaje del contacto: WhatsApp sólo deja escribir con una plantilla aprobada.','El número no tiene WhatsApp o no está bien escrito.','El contacto bloqueó el número de la empresa.','La cuenta de WhatsApp Business tiene un aviso de calidad o de pago pendiente.'],
  TYPE_SMS:['El número no es válido o no admite SMS.','El contacto respondió STOP y se dio de baja.','Falta saldo o el registro del número que envía.'],
  TYPE_EMAIL:['La dirección no existe o el correo rebotó.','El servidor del destinatario lo rechazó.'],
  TYPE_INSTAGRAM:['Han pasado más de 24 horas desde el último mensaje del contacto.','La cuenta de Instagram se desconectó o el contacto la bloqueó.'],
  TYPE_FACEBOOK:['Han pasado más de 24 horas desde el último mensaje del contacto.','La página se desconectó o el contacto la bloqueó.'],
};
const OPT_OUT='El contacto pidió no recibir mensajes por este canal. No se le puede escribir por aquí hasta que vuelva a escribir él o se reactive.';

/** Si el mensaje trae un motivo propio, aunque no esté documentado, se usa tal cual. */
function reasonOf(message){
  const candidates=[message?.error,message?.errorMessage,message?.meta?.error,message?.meta?.errorMessage,message?.meta?.reason,message?.meta?.error?.message,message?.error?.message];
  for(const value of candidates)if(typeof value==='string'&&value.trim())return value.trim().slice(0,200);
  return null;
}

/** @return {{label:string,tone:string,reason:?string,causes:string[]}} */
export function statusInfo(message){
  const [label,tone]=STATES[message?.status]||['',''];
  if(tone!=='bad')return {label,tone,reason:null,causes:[]};
  return {label,tone,reason:reasonOf(message),causes:message.status==='opt_out'?[OPT_OUT]:(CAUSES[message.messageType]||['Revisa el número o la dirección del contacto y el estado del canal.'])};
}

/** El pie de cada burbuja: hora, estado y, si no salió, el porqué a un toque. */
export function statusMarkup(message,time){
  const info=statusInfo(message);
  const line=`<time>${esc(time)}${info.label?` · <span class="msg-state ${esc(info.tone)}">${esc(info.label)}</span>`:''}</time>`;
  if(info.tone!=='bad')return line;
  const retry=message.direction==='outbound'&&typeof message.body==='string'&&message.body.trim()&&message.status!=='opt_out'
    ? `<button type="button" class="msg-retry" data-retry="${esc(message.id)}">Volver a escribirlo</button>`:'';
  return `${line}<details class="msg-why"><summary>Por qué</summary>
    ${info.reason?`<p><b>Motivo indicado por el canal:</b> ${esc(info.reason)}</p>`:''}
    <p>${info.reason?'Otras causas habituales:':'La cuenta no indica el motivo. Causas habituales:'}</p>
    <ul>${info.causes.map(c=>`<li>${esc(c)}</li>`).join('')}</ul>${retry}</details>`;
}

// Los iconos de la cabecera del chat, dibujados con el mismo trazo que las
// marcas de canal, en vez de caracteres tipográficos que cada teléfono pinta
// a su manera.
const svg=path=>`<svg viewBox="0 0 24 24" aria-hidden="true" class="ui-icon">${path}</svg>`;
export const UI_ICONS={
  back:svg('<path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
  expand:svg('<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'),
  shrink:svg('<path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'),
  contact:svg('<circle cx="12" cy="8.5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'),
};
