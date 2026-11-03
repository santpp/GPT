#!/usr/bin/env python3
"""Genera public/assets/text.css: el modo «Texto grande».

Cada tamaño de letra en px de app.css y layout.css se repite multiplicado por FACTOR_BASE (tamaño
normal) y, bajo html[data-text="large"], por FACTOR_LARGE (con negrita en todo el texto).
Uso: python3 tools/make-large.py   (vuelve a escribir text.css; se ejecuta después de cambiar el CSS)
"""
import colorsys, importlib.util, re
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('make_dark', ROOT/'tools/make-dark.py')
dark = importlib.util.module_from_spec(spec); spec.loader.exec_module(dark)
FACTOR_BASE = 1.2      # tamaño normal: todo el texto de Ágora, más grande que en 1.10.0
FACTOR_LARGE = 1.4     # «Texto grande» (sobre los tamaños originales)
FLOOR_BASE = 11.5      # ninguna letra por debajo de esto
FLOOR_LARGE = 13
SCOPE = 'html[data-text="large"]'
SIZE = re.compile(r'(\d+(?:\.\d+)?)px')

def scale(value, factor, floor):
    return SIZE.sub(lambda m: '%spx' % (('%.2f' % max(float(m.group(1)) * factor, floor)).rstrip('0').rstrip('.')), value)

def scoped(prelude, scope):
    return ','.join((scope + ' ' + s.strip()) if scope else s.strip() for s in prelude.split(',') if s.strip())

def rules(css, scope, factor, floor):
    out = []
    for prelude, body in dark.split_rules(re.sub(r'/\*.*?\*/', '', css, flags=re.S)):
        if prelude.startswith('@media'):
            inner = rules(body, scope, factor, floor)
            if inner: out.append(prelude + '{' + ''.join(inner) + '}')
            continue
        if prelude.startswith('@'): continue
        decls = []
        for decl in body.split(';'):
            if ':' not in decl: continue
            prop, value = decl.split(':', 1)
            if prop.strip().lower() != 'font-size' or not SIZE.search(value): continue
            decls.append('font-size:' + scale(value.strip().replace('!important', '').strip(), factor, floor) + '!important')
        if decls: out.append(scoped(prelude, scope) + '{' + ';'.join(decls) + '}')
    return out


HEX6 = re.compile(r'^#([0-9a-fA-F]{6})$')
def washed(value):
    """¿Es un gris apagado (o verdoso) de los que cuestan leer? Se vuelve el gris oscuro de la marca."""
    m = HEX6.match(value.strip())
    if not m: return False
    r, g, b = (int(m.group(1)[i:i+2], 16) / 255 for i in (0, 2, 4))
    h, l, sat = colorsys.rgb_to_hls(r, g, b)
    return 0.40 <= l <= 0.80 and sat < 0.30

def contrast(css):
    """Reglas cuyo color de texto es un gris apagado: pasan a --muted (más oscuro) para leerse bien."""
    out = []
    for prelude, body in dark.split_rules(re.sub(r'/\*.*?\*/', '', css, flags=re.S)):
        if prelude.startswith('@media'):
            inner = contrast(body)
            if inner: out.append(prelude + '{' + ''.join(inner) + '}')
            continue
        if prelude.startswith('@'): continue
        for decl in body.split(';'):
            if ':' not in decl: continue
            prop, value = decl.split(':', 1)
            if prop.strip().lower() == 'color' and washed(value):
                out.append(scoped(prelude, '') + '{color:var(--muted)!important}')
                break
    return out

BASE = '''/* Generado por tools/make-large.py: no editar a mano. */
html[data-text="large"] body *{font-weight:700!important}
'''
FINAL = '''/* Lo que más se lee: los mensajes y el cuadro de respuesta. */
.breadcrumbs,.breadcrumbs strong{color:var(--ink)!important}
::placeholder{color:var(--muted)!important;opacity:.85!important}
.bubble,.live-message-log .bubble,.bubble p{font-size:17px!important;line-height:1.55!important}
.live-composer textarea,#live-reply{font-size:17px!important;line-height:1.5!important;min-height:72px}
html[data-text="large"] .bubble,html[data-text="large"] .live-message-log .bubble,html[data-text="large"] .bubble p{font-size:20px!important}
html[data-text="large"] .live-composer textarea,html[data-text="large"] #live-reply{font-size:20px!important;min-height:88px}
'''

def main():
    parts = [BASE]
    for src in dark.SOURCES:
        parts.extend(r + '\n' for r in rules(src.read_text(), '', FACTOR_BASE, FLOOR_BASE))
    for src in dark.SOURCES:
        parts.extend(r + '\n' for r in rules(src.read_text(), SCOPE, FACTOR_LARGE, FLOOR_LARGE))
    for src in dark.SOURCES:
        parts.extend(r + '\n' for r in contrast(src.read_text()))
    parts.append(FINAL)
    (ROOT/'public/assets/text.css').write_text(''.join(parts))
    print('text.css:', sum(len(p) for p in parts), 'bytes')

if __name__ == '__main__':
    main()
