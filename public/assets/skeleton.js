/** Esqueletos de carga: la forma de lo que va a aparecer, en vez de un texto «Cargando…». */
const rows=n=>Array.from({length:n},(_,i)=>`<div class="sk-row" style="--i:${i}"><span class="sk sk-avatar"></span><div><span class="sk sk-line w60"></span><span class="sk sk-line w90"></span></div></div>`).join('');
const bubbles=()=>['in w55','out w40','in w70','out w50'].map((b,i)=>`<span class="sk sk-bubble ${b}" style="--i:${i}"></span>`).join('');
const tiles=n=>Array.from({length:n},(_,i)=>`<div class="sk-tile" style="--i:${i}"><span class="sk sk-line w40"></span><span class="sk sk-number"></span></div>`).join('');
const cards=n=>Array.from({length:n},(_,i)=>`<div class="sk-card" style="--i:${i}"><span class="sk sk-line w60"></span><span class="sk sk-line w30"></span></div>`).join('');
const lines=n=>Array.from({length:n},(_,i)=>`<span class="sk sk-line ${['w90','w70','w80','w50'][i%4]}" style="--i:${i}"></span>`).join('');
const KINDS={list:rows,chat:bubbles,tiles,cards,lines};
export const skeleton=(kind,count=5)=>`<div class="sk-wrap sk-${kind}" role="status" aria-busy="true" aria-label="Cargando">${(KINDS[kind]||lines)(count)}</div>`;
