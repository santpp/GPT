// Novedades: cómo se dibuja un texto escrito por el administrador. Sólo se
// reconocen párrafos y líneas con «- » (viñetas); todo lo demás es texto plano.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

export function renderBody(text){
  const bullet=/^\s*[-•]\s+/;
  return String(text||'').split(/\n{2,}/).map(block=>{
    // Dentro de un bloque, las viñetas seguidas forman una lista y el resto es texto.
    const out=[];let list=[],lines=[];
    const flushList=()=>{if(list.length){out.push(`<ul>${list.map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`);list=[]}};
    const flushText=()=>{if(lines.length){out.push(`<p>${lines.map(esc).join('<br>')}</p>`);lines=[]}};
    for(const line of block.split('\n')){
      if(bullet.test(line)){flushText();list.push(line.replace(bullet,''))}
      else{flushList();lines.push(line)}
    }
    flushList();flushText();
    return out.join('');
  }).join('');
}

/** El contenido de la ventana de una novedad. */
export function windowMarkup(item,{preview=false}={}){
  const link=item.linkLabel&&item.linkUrl?`<a class="announce-link primary" href="${esc(item.linkUrl)}"${item.linkUrl.startsWith('https://')?' target="_blank" rel="noopener noreferrer"':''} data-announce-link>${esc(item.linkLabel)}</a>`:'';
  return `<span class="eyebrow">NOVEDADES${preview?' · VISTA PREVIA':''}</span><h2 class="announce-title">${esc(item.title)}</h2>
   <div class="announce-body">${renderBody(item.body)}</div>
   <div class="dialog-actions announce-actions">${link}<button id="announce-ok" class="${link?'link-button':'primary'}" type="button">${preview?'Cerrar':'Entendido'}</button></div>`;
}
