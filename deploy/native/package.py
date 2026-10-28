#!/usr/bin/env python3
"""Build a secret-free CyberPanel package with private code outside public_html."""
from pathlib import Path
import stat
import zipfile
import sys

root=Path(__file__).resolve().parents[2]
output=Path(sys.argv[1]) if len(sys.argv)>1 else Path('/tmp/agora-cyberpanel-php83.zip')
output.parent.mkdir(parents=True,exist_ok=True)
update='--update' in sys.argv
files=[]
for folder in ['src','bin','database']:
    for file in sorted((root/folder).rglob('*')):
        if file.is_file() and not(update and folder=='database' and file.name=='schema.sql'):files.append((file,'agora-private/'+file.relative_to(root).as_posix(),0o644))
for file in sorted((root/'public').rglob('*')):
    if file.is_file():files.append((file,'public_html/'+file.relative_to(root/'public').as_posix(),0o644))
if update:
    files.append((root/'deploy/native/UPDATE-0.2.1.md','ACTUALIZAR.md',0o644))
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
    for file,name,mode in files:
        item=zipfile.ZipInfo(name);item.create_system=3;item.external_attr=(stat.S_IFREG|mode)<<16
        archive.writestr(item,file.read_bytes(),compress_type=zipfile.ZIP_DEFLATED)
with zipfile.ZipFile(output) as archive:
    names=archive.namelist()
    assert 'public_html/.htaccess' in names and 'public_html/router.php' in names
    if update:assert not any(n.startswith(('agora-private/var/','agora-private/config/')) for n in names)
    assert not any(Path(n).name in ['local.php','.env','integration.key'] for n in names)
    assert not any(n.startswith('public_html/') and ('/src/' in n or '/config/' in n or '/database/' in n) for n in names)
print(f'Built and checked {output.name}: {len(files)} files; no runtime secrets')
