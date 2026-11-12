"""1.10.12 · El sistema de diseño se mantiene: en app.css y layout.css sólo colores de la paleta,
tamaños de letra de la escala y sombras de los tres niveles. Ver docs/DESIGN.md §3–§5.
Si de verdad hace falta algo nuevo, se añade aquí y en DESIGN.md en el mismo cambio."""
import re,pathlib
A=pathlib.Path(__file__).resolve().parent.parent/'public/assets'
css=''.join((A/n).read_text() for n in ('app.css','layout.css'))
css=re.sub(r'/\*.*?\*/','',css,flags=re.S)
PALETTE={'#0b0b10','#30343c','#5d6370','#ad103c','#f7e9ee','#282e49','#fabd4b','#fff4d5','#7a5c18','#2f7a52','#eaf7ef','#0a57d0','#e9f1ff',
         '#e5e4e9','#d8dde6','#f1f1f5','#ffffff','#fff','#f7f7fa','#000','#000000'}
BRANDS={'#1faf54','#c13584','#0866ff'}   # WhatsApp, Instagram y Facebook: colores de esas marcas
colors={c.lower() for c in re.findall(r'#[0-9a-fA-F]{3,8}\b',css)}
solid={c for c in colors if len(c) in (4,7)}
odd=sorted(solid-PALETTE-BRANDS)
assert not odd,f'colores fuera de la paleta: {odd}'
print(f'PASS {len(solid)} solid colours, all from the palette or the three channel brands ({len(colors)-len(solid)} transparencies)')
SCALE={10,11,12,13,14,16,18,22,26,32}
sizes={float(v) for v in re.findall(r'font-size:\s*(\d+(?:\.\d+)?)px',css)}
off=sorted(sizes-SCALE)
assert not off,f'tamaños de letra fuera de la escala: {off}'
print(f'PASS every font size is on the scale {sorted(SCALE)}')
SHADOWS={'0 1px 3px #12151f1f','0 8px 24px #12151f1f','0 16px 40px #12151f26'}
found=set()
for v in re.findall(r'box-shadow:\s*([^;}]+)',css):
    v=v.replace('!important','').strip()
    if v=='none' or v.startswith('var(') or 'inset' in v or v.startswith('0 0 0'):continue
    found.add(v)
extra=sorted(found-SHADOWS)
assert not extra,f'sombras fuera de los tres niveles: {extra}'
print('PASS shadows use the three levels (plus focus rings and insets)')
