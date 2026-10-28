"""Exercise the shipped static entry while a stale worker controls root assets."""
import functools
import http.server
import json
import os
from pathlib import Path
import tempfile
import threading
import zipfile
from playwright.sync_api import sync_playwright

archive = Path(os.getenv('NATIVE_ARCHIVE', 'releases/agora-update-0.4.1.zip'))
with tempfile.TemporaryDirectory() as directory:
    with zipfile.ZipFile(archive) as bundle:
        bundle.extractall(directory)
    public = Path(directory) / 'public_html'
    version = json.loads((public / 'version.json').read_text())
    (public / 'old-worker.js').write_text('''
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',e=>e.waitUntil(clients.claim()));
self.addEventListener('fetch',e=>{
 const path=new URL(e.request.url).pathname;
 if(path.startsWith('/assets/')&&!path.startsWith('/assets/release-'))
   e.respondWith(Promise.resolve(new Response('throw new Error("stale asset loaded")',{headers:{'Content-Type':'application/javascript'}})));
});
''')
    class QuietHandler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *args): pass
    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(QuietHandler, directory=str(public)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])
            page = browser.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            signed_in = [False]
            def api(route):
                path = route.request.url.split('/api/')[1]
                status = 200
                if path == 'login':
                    signed_in[0] = True
                    response = {'csrf': 'test'}
                elif path == 'me':
                    status = 200 if signed_in[0] else 401
                    response = {'user': {'role': 'administrator'}, 'csrf': 'test'} if signed_in[0] else {'error': 'Inicia sesión'}
                elif path == 'admin/diagnostics':
                    response = {'ready': True, 'version': version['version'], 'checks': []}
                else:
                    response = {'items': []}
                route.fulfill(status=status, content_type='application/json', body=json.dumps(response))
            page.route('**/api/**', api)
            base = f'http://127.0.0.1:{server.server_port}'
            page.goto(base + '/version.json')
            page.evaluate("navigator.serviceWorker.register('/old-worker.js')")
            page.wait_for_function('navigator.serviceWorker.controller !== null')
            page.goto(base + '/conexiones.html')
            page.locator('#settings-login').click()
            page.locator('[name="email"]').fill('admin@example.test')
            page.locator('[name="password"]').fill('test-password')
            page.locator('#login-form button').click()
            page.wait_for_selector('#add-integration')
            assert page.locator('#action-dialog').evaluate('(d)=>!d.open')
            assert page.url.endswith('/conexiones.html')
            page.reload()
            page.wait_for_selector('#add-integration')
            assert not errors, errors
            assert 'Acceder al backend' not in page.locator('body').inner_text()
            scripts = page.locator('script[src]').evaluate_all('(nodes)=>nodes.map(n=>n.src)')
            assert scripts and all('/assets/release-' + version['build'] + '/' in url for url in scripts)
            browser.close()
            print('PASS packaged static entry, stale worker/assets, login return, retained session and versioned scripts; mock API')
    finally:
        server.shutdown()
        server.server_close()
