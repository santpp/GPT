#!/usr/bin/env python3
"""Agora New App · cada estado que el programa de la bandeja activa (classList) debe tener estilo en alguna hoja.
Evita botones que «no hacen nada» porque el programa y la hoja usan nombres distintos (pasó con la ficha del contacto).
Uso: python3 tests/ui-estados.py   (no necesita servidor)"""
import re,pathlib,sys
A=pathlib.Path(__file__).resolve().parent.parent/'public_html'/'assets'
js=(A/'agora-modulos.js').read_text()
css=''.join((A/f).read_text() for f in ['sistema.css','modulos.css','marca.css','diseno.css','ecosistema.css','agora-new.css'])
# Estados que no pintan nada por diseño: la barra «nav» de Ágora Mobile no existe en esta app.
SIN_ESTILO_A_PROPOSITO={'has-alert'}
estados=set(re.findall(r'classList\.(?:toggle|add|remove)\(\s*"([\w-]+)"',js))
faltan=sorted(e for e in estados-SIN_ESTILO_A_PROPOSITO if not re.search(r'\.'+re.escape(e)+r'(?![\w-])',css))
if faltan:sys.exit('FALLA: estados sin estilo: '+', '.join(faltan))
html=(A.parent/'index.html').read_text()
v=set(re.findall(r'\?v=([\d.]+)',html))
ver=re.search(r'"version":"([\d.]+)"',(A.parent/'version.json').read_text()).group(1)
if v!={ver}:sys.exit(f'FALLA: index.html pide {sorted(v)} y version.json dice {ver}')
if f'agora-digital-app-{ver}-' not in (A.parent/'sw.js').read_text():sys.exit('FALLA: sw.js no lleva la versión '+ver)
print(f'PASS {len(estados)} estados con estilo · versión {ver} igual en index.html, version.json y sw.js')
