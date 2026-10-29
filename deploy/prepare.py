#!/usr/bin/env python3
"""Prepare fresh deployment secrets without printing or replacing existing values."""
import base64
import os
from pathlib import Path
import secrets

root = Path(__file__).resolve().parent
os.umask(0o077)
env = root / '.env.production'
keydir = root / 'secrets'
keydir.mkdir(mode=0o700, exist_ok=True)
key = keydir / 'integration.key'

def create(path, value):
    try:
        with path.open('x') as stream:
            stream.write(value)
        path.chmod(0o600)
        print(f'Created {path.name}; value hidden')
    except FileExistsError:
        print(f'Preserved existing {path.name}')

# Existing deployments must restore their original env and encryption key first.
create(env, 'APP_HOST=nueva.agoradigital.app\n'
       f'DB_PASSWORD={secrets.token_hex(32)}\n'
       f'DB_ROOT_PASSWORD={secrets.token_hex(32)}\n'
       'GHL_LOCATION_ID=agora-workspace\n'
       'LOGIN_MAIL_FROM=acceso@nueva.agoradigital.app\n')
create(key, base64.b64encode(secrets.token_bytes(32)).decode())
