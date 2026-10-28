#!/usr/bin/env python3
"""Build a secret-free CyberPanel package with an immutable frontend snapshot."""
from pathlib import Path
import hashlib
import json
import re
import stat
import zipfile
import sys

root=Path(__file__).resolve().parents[2]
output=Path(sys.argv[1]) if len(sys.argv)>1 else Path('/tmp/agora-cyberpanel-php83.zip')
output.parent.mkdir(parents=True,exist_ok=True)
update='--update' in sys.argv
version=re.search(r"VERSION='([^']+)'",(root/'src/Installation.php').read_text()).group(1)
assets=sorted(p for p in (root/'public/assets').rglob('*') if p.is_file())
index=(root/'public/index.html').read_text()
worker=(root/'public/sw.js').read_text()
digest=hashlib.sha256(index.encode()+worker.encode())
for asset in assets:
    digest.update(asset.relative_to(root/'public/assets').as_posix().encode()+b'\0'+asset.read_bytes())
build=digest.hexdigest()[:16]
prefix='assets/release-'+build
files=[]
for folder in ['src','bin','database']:
    for file in sorted((root/folder).rglob('*')):
        if file.is_file() and not(update and folder=='database' and file.name=='schema.sql'):
            files.append((file,'agora-private/'+file.relative_to(root).as_posix(),0o644))
for file in sorted((root/'public').rglob('*')):
    if file.is_file() and file.name not in ['index.html','sw.js']:
        files.append((file,'public_html/'+file.relative_to(root/'public').as_posix(),0o644))
# Relative JS imports keep the same directory, so every module uses this build.
for file in assets:
    files.append((file,'public_html/'+prefix+'/'+file.relative_to(root/'public/assets').as_posix(),0o644))
html=index.replace('/assets/','/'+prefix+'/')
files.append((html.encode(),'public_html/index.html',0o644))
files.append((html.replace('<title>Ágora Digital · Conversaciones</title>','<title>Ágora Digital · Conexiones</title>').encode(),'public_html/conexiones.html',0o644))
shell=['/','/conexiones.html','/manifest.webmanifest']+['/'+prefix+'/'+file.relative_to(root/'public/assets').as_posix() for file in assets]
worker=re.sub(r"const CACHE=.*?;",'const CACHE='+json.dumps('agora-shell-'+version+'-'+build)+';',worker,count=1)
worker=re.sub(r"const SHELL=\[.*?\];",'const SHELL='+json.dumps(shell)+';',worker,count=1,flags=re.S)
files.append((worker.encode(),'public_html/sw.js',0o644))
files.append((json.dumps({'version':version,'build':build}).encode(),'public_html/version.json',0o644))
if update:
    files.append((root/f'deploy/native/UPDATE-{version}.md','ACTUALIZAR.md',0o644))
else:
    files.extend([(root/'config/local.example.php','agora-private/config/local.example.php',0o600),
                  (root/'deploy/native/README.md','LEEME.md',0o644)])
dirs={'agora-private':0o755,'agora-private/var':0o700,'agora-private/config':0o700,'public_html':0o755}
for _,name,mode in files:
    for parent in Path(name).parents:
        if str(parent)!='.':dirs[parent.as_posix()]=0o700 if parent.as_posix()=='agora-private/config' else 0o755
with zipfile.ZipFile(output,'w',compression=zipfile.ZIP_DEFLATED) as archive:
    for name,mode in ([] if update else sorted(dirs.items())):
        item=zipfile.ZipInfo(name+'/');item.create_system=3;item.external_attr=(stat.S_IFDIR|mode)<<16
        archive.writestr(item,b'')
    for source,name,mode in files:
        item=zipfile.ZipInfo(name);item.create_system=3;item.external_attr=(stat.S_IFREG|mode)<<16
        archive.writestr(item,source if isinstance(source,bytes) else source.read_bytes(),compress_type=zipfile.ZIP_DEFLATED)
with zipfile.ZipFile(output) as archive:
    names=archive.namelist()
    assert 'public_html/.htaccess' in names and 'public_html/conexiones.html' in names
    if update:assert not any(n.startswith(('agora-private/var/','agora-private/config/')) for n in names)
    assert not any(Path(n).name in ['local.php','.env','integration.key'] for n in names)
    assert not any(n.startswith('public_html/') and ('/src/' in n or '/config/' in n or '/database/' in n) for n in names)
    assert ('/'+prefix+'/app.js').encode() in archive.read('public_html/conexiones.html')
print(f'Built and checked {output.name}: version {version}, build {build}; no runtime secrets')
