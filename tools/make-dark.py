#!/usr/bin/env python3
"""Genera public/assets/dark.css a partir de app.css y layout.css.

Se queda sólo con las declaraciones de color de cada regla y las convierte a
modo oscuro según para qué sirven: un fondo claro pasa a superficie oscura con
el mismo matiz, un texto oscuro pasa a claro, los colores de marca se aclaran lo
justo para leerse sobre oscuro. Todo queda bajo html[data-theme="dark"], así que
el modo claro no cambia. Los retoques a mano van en el bloque MANUAL del final.

Uso: python3 tools/make-dark.py   (vuelve a escribir dark.css)
"""
import colorsys, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCES = [ROOT/'public/assets/app.css', ROOT/'public/assets/layout.css']
OUT = ROOT/'public/assets/dark.css'
SCOPE = 'html[data-theme="dark"]'
COLOR_PROPS = ('color','background','background-color','background-image','border','border-color','border-top','border-bottom','border-left','border-right',
               'border-top-color','border-bottom-color','border-left-color','border-right-color','outline','outline-color','box-shadow','fill','stroke','caret-color','accent-color')
HEX = re.compile(r'#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b')
NAMED = {'white':'#ffffff','black':'#000000'}

def parse_hex(h):
    h = h.lstrip('#')
    if len(h) in (3,4): h = ''.join(c*2 for c in h)
    r,g,b = (int(h[i:i+2],16)/255 for i in (0,2,4))
    a = h[6:8] if len(h)==8 else ''
    return r,g,b,a

def to_hex(r,g,b,a=''):
    return '#%02x%02x%02x%s' % (round(r*255),round(g*255),round(b*255),a)

def convert(hexcolor, role):
    r,g,b,a = parse_hex(hexcolor)
    h,l,s = colorsys.rgb_to_hls(r,g,b)
    if role == 'text':
        if l >= .8: return hexcolor                      # texto blanco sobre color: igual
        if s > .45 and l < .6:                            # color de marca: más luz
            nl = .66; ns = min(s, .75)
        elif l < .45: nl = .9 - l*.25; ns = s*.4          # tinta: casi blanco
        else: nl = .55 + (l-.45)*.35; ns = s*.4           # grises de apoyo
        return to_hex(*colorsys.hls_to_rgb(h,nl,ns),a)
    if role == 'border':
        if l >= .75 and max(r,g,b)-min(r,g,b) < .07: return to_hex(*colorsys.hls_to_rgb(228/360,.25,.15),a)
        if l >= .75: return to_hex(*colorsys.hls_to_rgb(h,.27,s*.35),a)
        return hexcolor
    # fondo
    chroma = max(r,g,b) - min(r,g,b)
    if l >= .82 and chroma < .07:
        # Blancos y casi blancos: todos al mismo gris azulado de la paleta, más
        # claro cuanto menos blanco era, para conservar los escalones.
        return to_hex(*colorsys.hls_to_rgb(228/360, min(.14 + (1-l)*.5, .22), .2), a)
    if l >= .82:
        # Superficies claras: oscuras, con un poco del matiz para que los
        # avisos amarillos, las burbujas rosadas o verdes sigan distinguiéndose.
        tint = s*(1-l)*4
        nl = .13 + (1-l)*.55 + (.04 if tint > .15 else 0)
        return to_hex(*colorsys.hls_to_rgb(h,min(nl,.24),min(s*.55,.45)),a)
    return hexcolor

def role_of(prop):
    if prop in ('color','fill','caret-color'): return 'text'
    if prop.startswith('border') or prop.startswith('outline'): return 'border'
    if prop == 'box-shadow': return 'shadow'
    return 'background'

def transform_value(prop, value):
    role = role_of(prop)
    if role == 'shadow': return re.sub(HEX, lambda m: '#00000066', value)
    for name,code in NAMED.items():
        value = re.sub(r'(?<![-\w])%s(?![-\w])' % name, code, value)
    return HEX.sub(lambda m: convert(m.group(0), role), value)

def split_rules(css):
    """[(prelude, body)] respetando @media."""
    out, i, n = [], 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0: break
        prelude = css[i:j].strip()
        depth, k = 1, j+1
        while k < n and depth:
            if css[k] == '{': depth += 1
            elif css[k] == '}': depth -= 1
            k += 1
        out.append((prelude, css[j+1:k-1]))
        i = k
    return out

def scope(selectors):
    parts = []
    for sel in selectors.split(','):
        sel = sel.strip()
        if not sel: continue
        if sel.startswith(':root'): parts.append(SCOPE + sel[5:])
        elif sel.startswith('html'): parts.append(SCOPE + sel[4:])
        else: parts.append(SCOPE + ' ' + sel)
    return ','.join(parts)

def rules(css):
    out = []
    for prelude, body in split_rules(re.sub(r'/\*.*?\*/', '', css, flags=re.S)):
        if prelude.startswith('@media'):
            inner = rules(body)
            if inner: out.append(prelude + '{' + ''.join(inner) + '}')
            continue
        if prelude.startswith('@'): continue
        decls = []
        for decl in body.split(';'):
            if ':' not in decl: continue
            prop, value = decl.split(':', 1)
            prop = prop.strip().lower()
            if prop.startswith('--'): continue   # la paleta base va a mano, abajo
            if prop not in COLOR_PROPS: continue
            if not (HEX.search(value) or re.search(r'(?<![-\w])(white|black)(?![-\w])', value)): continue
            decls.append(prop + ':' + transform_value(prop, value.strip()))
        if decls: out.append(scope(prelude) + '{' + ';'.join(decls) + '}')
    return out

MANUAL = '''
/* ---- a mano: paleta base y piezas que el generador no puede adivinar ---- */
html[data-theme="dark"]{color-scheme:dark;--ink:#e6e8f0;--muted:#9aa0b4;--line:#2f3445;--paper:#1b1f2c;--bg:#12151f;--green:#d13c63;--accent:#f5b84a}
html[data-theme="dark"] body{background:var(--bg);color:var(--ink)}
html[data-theme="dark"] .sidebar,html[data-theme="dark"] .other-view,html[data-theme="dark"] .inbox-layout,html[data-theme="dark"] .signin-card,html[data-theme="dark"] dialog{background:var(--paper);color:var(--ink)}
html[data-theme="dark"] input,html[data-theme="dark"] select,html[data-theme="dark"] textarea{color:var(--ink);background-color:#151925;border-color:var(--line)}
html[data-theme="dark"] .search{background:#151925;border-color:var(--line)}
html[data-theme="dark"] .search input{background:transparent;border:0}
html[data-theme="dark"] input::placeholder,html[data-theme="dark"] textarea::placeholder{color:#727891}
html[data-theme="dark"] .primary,html[data-theme="dark"] .send,html[data-theme="dark"] .passkey-button.primary-passkey{background:#c0204b;border-color:#c0204b;color:#fff}
html[data-theme="dark"] .toast{background:#e6e8f0;color:#12151f}
html[data-theme="dark"] .avatar{background:#3a3428;color:#e8d9bf}
html[data-theme="dark"] .avatar.olive{background:#43202c;color:#f0a3b8}
html[data-theme="dark"] dialog::backdrop{background:#05070dcc}
html[data-theme="dark"] .breadcrumbs strong{color:var(--ink)}
html[data-theme="dark"] .waterfall-step i,html[data-theme="dark"] .waterfall-step.up i{background:#8c3352}
html[data-theme="dark"] .waterfall-step.down i{background:#46506e}
html[data-theme="dark"] .waterfall-step.total i{background:var(--green)}
html[data-theme="dark"] img.brand-logo,html[data-theme="dark"] img.brand-dot{filter:brightness(1.25) saturate(1.1)}
'''

def main():
    out = ['/* Modo oscuro. Generado por tools/make-dark.py: no editar a mano. */']
    for source in SOURCES: out += rules(source.read_text())
    OUT.write_text('\n'.join(out) + '\n' + MANUAL.strip() + '\n')
    print(f'wrote {OUT.relative_to(ROOT)}: {len(out)-1} rules')

if __name__ == '__main__': main()
