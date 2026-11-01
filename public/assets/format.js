// Cifras y dinero con el formato de la subcuenta en uso: miles con punto, decimales con coma y su moneda (euros o dólares).
import {workspace} from './workspace.js';
const grouped=(options={})=>new Intl.NumberFormat('es-ES',{useGrouping:'always',...options});
export const number=value=>grouped().format(Number(value)||0);
export const currencyCode=()=>workspace.currency();
/** Importe con su moneda («1.234,50 $» o «1.234,50 €»); null si no es un número. */
export function money(value,{decimals=2}={}){
  if(value===null||value===undefined||value===''||!Number.isFinite(Number(value)))return null;
  return grouped({style:'currency',currency:workspace.currency(),currencyDisplay:'narrowSymbol',minimumFractionDigits:decimals,maximumFractionDigits:decimals}).format(Number(value));
}
export const symbol=()=>workspace.currency()==='EUR'?'€':'$';
