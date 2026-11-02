#!/usr/bin/env python3
"""Genera public/assets/text.css: el modo «Texto grande».

Cada tamaño de letra en px de app.css y layout.css se repite bajo html[data-text="large"]
multiplicado por FACTOR, y todo el texto pasa a negrita. El modo normal no cambia.
Uso: python3 tools/make-large.py   (vuelve a escribir text.css; se ejecuta después de cambiar el CSS)
"""
import importlib.util, re
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('make_dark', ROOT/'tools/make-dark.py')
dark = importlib.util.module_from_spec(spec); spec.loader.exec_module(dark)
FACTOR = 1.15
SCOPE = 'html[data-text="large"]'
SIZE = re.compile(r'(\d+(?:\.\d+)?)px')

def scale(value):
    return SIZE.sub(lambda m: '%spx' % (('%.2f' % (float(m.group(1)) * FACTOR)).rstrip('0').rstrip('.')), value)

def scoped(prelude):
    return ','.join(SCOPE + ' ' + s.strip() for s in prelude.split(',') if s.strip())

def rules(css):
    out = []
    for prelude, body in dark.split_rules(re.sub(r'/\*.*?\*/', '', css, flags=re.S)):
        if prelude.startswith('@media'):
            inner = rules(body)
            if inner: out.append(prelude + '{' + ''.join(inner) + '}')
            continue
        if prelude.startswith('@'): continue
        decls = []
        for decl in body.split(';'):
            if ':' not in decl: continue
            prop, value = decl.split(':', 1)
            if prop.strip().lower() != 'font-size' or not SIZE.search(value): continue
            decls.append('font-size:' + scale(value.strip().replace('!important', '').strip()) + '!important')
        if decls: out.append(scoped(prelude) + '{' + ';'.join(decls) + '}')
    return out

BASE = '''/* Generado por tools/make-large.py: no editar a mano. */
html[data-text="large"] body *{font-weight:700!important}
'''

def main():
    parts = [BASE]
    for src in dark.SOURCES:
        parts.extend(r + '\n' for r in rules(src.read_text()))
    (ROOT/'public/assets/text.css').write_text(''.join(parts))
    print('text.css:', sum(len(p) for p in parts), 'bytes')

if __name__ == '__main__':
    main()
