#!/usr/bin/env python3
"""Agora New App · paquete de PRODUCCIÓN para CyberPanel (sin Docker, MySQL).
Uso: python3 tools/package.py [versión]   → agora-new-app-<versión>.zip junto a este archivo.
Deja fuera: modo pruebas y datos sintéticos, demos, logos de clientes, pruebas, herramientas,
config.php real, storage y cualquier archivo con aspecto de clave. El ZIP de demostración es aparte
(agora-cyberpanel-2.1.19-demo.zip)."""
import hashlib,pathlib,re,sys,zipfile
ROOT=pathlib.Path(__file__).resolve().parent.parent
version=sys.argv[1] if len(sys.argv)>1 else '2.3.1'
out=ROOT/f'agora-new-app-{version}.zip'
FUERA=[r'^servidor/datos-sinteticos/',r'^servidor/bin/sembrar-sinteticos\.php$',r'^servidor/storage/(?!\.gitkeep$|\.gitignore$)',
       r'^servidor/config/config\.php$',r'^public_html/assets/demo(-capas)?\.js$',r'^public_html/assets/asocim-',
       r'(^|/)\.DS_Store$',r'\.sqlite',r'^public_html/assets/fonts/Inter']
files=[]
for top in ('public_html','servidor'):
    for f in sorted((ROOT/top).rglob('*')):
        if not f.is_file():continue
        rel=f.relative_to(ROOT).as_posix()
        if any(re.search(p,rel) for p in FUERA):continue
        files.append((f,rel))
files.append((ROOT/'INSTALAR-CYBERPANEL.md','INSTALAR-CYBERPANEL.md'))
SECRETO=re.compile(rb'(pit-[0-9a-f]{8}-[0-9a-f]{4}|-----BEGIN [A-Z ]*PRIVATE KEY|eyJhbGciOi[A-Za-z0-9_-]{20,}\.)')
with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as z:
    for f,rel in files:
        data=f.read_bytes()
        if SECRETO.search(data):sys.exit(f'Posible secreto en {rel}: no se empaqueta.')
        i=zipfile.ZipInfo(rel,date_time=(2026,1,1,0,0,0));i.external_attr=0o644<<16;i.compress_type=zipfile.ZIP_DEFLATED
        z.writestr(i,data)
names=zipfile.ZipFile(out).namelist()
for must in ('public_html/api/index.php','servidor/bootstrap.php','servidor/app/database/migrations/004_ghl_conexion.sql','public_html/assets/agora-new.css','servidor/motor/src/live-inbox.php','servidor/motor/database/schema.sql','servidor/bin/crear-dueno.php','public_html/assets/motor/consumption.js','servidor/app/src/Core/MotorMobile.php'):
    assert must in names,must
for never in ('servidor/datos-sinteticos/LEEME.md','public_html/assets/demo.js','servidor/config/config.php','servidor/storage/motor-vault.key'):
    assert never not in names,never
sha=hashlib.sha256(out.read_bytes()).hexdigest()
(out.parent/(out.name+'.sha256')).write_text(f'{sha}  {out.name}\n')
print(f'{out.name}: {len(names)} archivos, {out.stat().st_size//1024} KB, sha256 {sha[:16]}…')
