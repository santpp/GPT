#!/usr/bin/env python3
"""Read-only checks after deployment. TLS verification stays enabled for HTTPS."""
import json
import os
import urllib.error
import urllib.request

base = os.getenv('TEST_BASE_URL', 'https://nueva.agoradigital.app').rstrip('/')
for path, expected in [('/',200),('/api/health',200),('/api/me',401),('/api/admin/integrations',401),('/router.php',404),('/router%2ephp',404),('/.env',404),('/conversations/demo-lucia',200),('/assets/agora-logo.svg',200),('/sw.js',200)]:
    try:
        with urllib.request.urlopen(base + path, timeout=20) as response:
            code, body, headers = response.status, response.read(), response.headers
    except urllib.error.HTTPError as response:
        code, body, headers = response.code, response.read(), response.headers
    assert code == expected, (path, code)
    if path == '/api/health':
        assert json.loads(body)['status'] == 'ok'
        assert headers.get('Cache-Control') == 'no-store'
    if path == '/api/me':
        cookie = headers.get('Set-Cookie', '').lower()
        assert 'secure' in cookie and 'httponly' in cookie and 'samesite=strict' in cookie
    if path == '/sw.js':
        assert headers.get('Cache-Control') == 'no-cache'
    print('PASS', path, code)
