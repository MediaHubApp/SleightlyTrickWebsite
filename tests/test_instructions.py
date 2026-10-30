"""Real HTTP tests against an isolated PHP server; no live accounts or videos.
Run: PHP_BIN=/path/to/php python3 -m unittest discover -s tests -v
"""
import http.cookiejar
import http.client
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import struct
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import zlib

SOURCE_ROOT = Path(__file__).resolve().parents[1]
ROOT = Path(os.environ.get('ST_TEST_SITE_ROOT', SOURCE_ROOT))
FIXTURES = SOURCE_ROOT / 'tests/fixtures'

def png_fixture(width=2, height=2, padding=0):
    """A valid lossless PNG, with optional text padding for upload-size tests."""
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    compressor = zlib.compressobj()
    row = bytes((width + 7) // 8 + 1)  # Filter byte and one-bit grayscale pixels.
    pixels = b''.join(compressor.compress(row) for _ in range(height)) + compressor.flush()
    header = struct.pack('>IIBBBBB', width, height, 1, 0, 0, 0, 0)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', header)
            + (chunk(b'tEXt', b'Padding\0' + b'x' * padding) if padding else b'')
            + chunk(b'IDAT', pixels) + chunk(b'IEND', b''))

class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, headers=None, method=None):
        request = urllib.request.Request(self.base + path, data=data, headers=headers or {}, method=method)
        try:
            response = self.opener.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read(), response.headers

    def form(self, path, data):
        return self.request(path, urllib.parse.urlencode(data).encode(), {'Content-Type': 'application/x-www-form-urlencoded'})

    def api(self, action, data=None, csrf=None):
        headers = {'Content-Type': 'application/json'}
        if csrf is not None:
            headers['X-CSRF-Token'] = csrf
        code, body, _ = self.request('/admin/api.php?action=' + action, None if data is None else json.dumps(data).encode(), headers)
        return code, json.loads(body)

    def token(self, path):
        code, body, _ = self.request(path)
        match = re.search(rb'name="_csrf" value="([a-f0-9]+)"', body)
        if not match:
            raise AssertionError(f'No CSRF token at {path}: status {code}')
        return match[1].decode()


class InstructionWorkflow(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='sleightly-tests-')
        cls.storage = Path(cls.temp.name) / 'private'
        cls.storage.mkdir()
        # An original installation has no instructions table. Exercise its additive migration.
        with sqlite3.connect(cls.storage / 'site.sqlite') as db:
            db.execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, session_version INTEGER NOT NULL DEFAULT 1)')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{cls.port}'
        cls.log = open(Path(cls.temp.name) / 'server.log', 'wb')
        cls.server = subprocess.Popen([os.environ.get('PHP_BIN', 'php'), '-d', 'upload_max_filesize=210M', '-d', 'post_max_size=220M', '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT), str(SOURCE_ROOT / 'tools/dev-router.php')], cwd=ROOT, env={**os.environ, 'ST_LOCAL_HTTP': '1', 'ST_STORAGE_DIR': str(cls.storage)}, stdout=cls.log, stderr=cls.log)
        cls.admin = Client(cls.base)
        for _ in range(100):
            try:
                cls.admin.request('/')
                break
            except urllib.error.URLError:
                if cls.server.poll() is not None:
                    raise AssertionError('PHP server exited during startup')
                time.sleep(.05)
        token = cls.admin.token('/setup.php')
        code, body, _ = cls.admin.form('/setup.php', {'_csrf': token, 'setup_key': (cls.storage / 'setup-key.txt').read_text().strip(), 'email': 'owner@example.test', 'password': 'Test owner password 123', 'confirm': 'Test owner password 123'})
        assert code == 200 and b'editor-data' in body, 'Owner setup failed'
        cls.csrf = json.loads(re.search(rb'id="editor-data">(.*?)</script>', body, re.S)[1])['csrf']

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)
        cls.log.close()
        cls.temp.cleanup()

    def setUp(self):
        self.customer = Client(self.base)
        self.slug = self._testMethodName.replace('_', '-')
        self.page = self.save(title='Test trick <special>', slug=self.slug, summary='Public summary', notes='PRIVATE SECRET NOTES\nSecond line <safe>', video_source='https://youtu.be/dQw4w9WgXcQ', password='Printed-Secret', published=True)
        self.path = '/instructions.php?trick=' + self.slug

    def save(self, **data):
        code, result = self.admin.api('save-instruction', data, self.csrf)
        self.assertEqual(code, 200, result)
        return result['instruction']

    def update(self, **values):
        self.page = self.save(**{**self.page, 'password': '', **values})

    def unlock(self, password='Printed-Secret', path=None, customer=None):
        customer = customer or self.customer
        path = path or self.path
        return customer.form(path, {'_csrf': customer.token(path), 'password': password})

    def upload(self, data=None, filename='sample.mp4', csrf=None):
        boundary = 'SleightlyTestUploadBoundary'
        data = data if data is not None else (FIXTURES / 'sample.mp4').read_bytes()
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: video/mp4\r\n\r\n').encode() + data + f'\r\n--{boundary}--\r\n'.encode()
        code, raw, _ = self.admin.request('/admin/api.php?action=upload-video', body, {'Content-Type': f'multipart/form-data; boundary={boundary}', 'X-CSRF-Token': csrf or self.csrf})
        return code, json.loads(raw)

    def upload_photo(self, data, csrf=None, client=None):
        boundary = 'PhotoUploadBoundary'
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="photo.png"\r\nContent-Type: image/png\r\n\r\n').encode() + data + f'\r\n--{boundary}--\r\n'.encode()
        code, raw, _ = (client or self.admin).request('/admin/api.php?action=upload', body, {'Content-Type': f'multipart/form-data; boundary={boundary}', 'X-CSRF-Token': csrf or self.csrf})
        return code, json.loads(raw)

    def test_photo_uploads_above_former_limits_and_publication(self):
        uploaded = []
        for width, height, padding in [(2, 2, 6_000_000), (17001, 1, 0), (1, 17001, 0), (8000, 8001, 0)]:
            with self.subTest(width=width, height=height, padding=padding):
                image = png_fixture(width, height, padding)
                code, result = self.upload_photo(image)
                self.assertEqual(code, 201, result)
                url = result['url']
                uploaded.append(url)
                code, body, headers = self.customer.request(url)
                self.assertEqual(code, 200)
                self.assertEqual(headers['Content-Type'], 'image/png')
                self.assertEqual(int(headers['Content-Length']), len(image))
                self.assertEqual(body, image)
        code, original = self.admin.api('load')
        self.assertEqual(code, 200)
        draft = json.loads(json.dumps(original))
        url = uploaded[0]
        draft['content']['heroImage'] = url
        draft['content']['bannerImage'] = url
        draft['content']['products'][0]['image'] = url
        self.assertNotIn(url.encode(), self.customer.request('/')[1])
        code, result = self.admin.api('save', draft, self.csrf)
        self.assertEqual(code, 200, result)
        try:
            self.assertEqual(self.customer.request('/')[1].count(url.encode()), 3)
            _, reloaded = self.admin.api('load')
            self.assertEqual(reloaded['content']['heroImage'], url)
            self.assertEqual(reloaded['content']['bannerImage'], url)
            self.assertEqual(reloaded['content']['products'][0]['image'], url)
        finally:
            code, restored = self.admin.api('save', {'content': original['content'], 'version': result['version']}, self.csrf)
            self.assertEqual(code, 200, restored)

    def test_photo_upload_validation_and_permissions(self):
        self.assertEqual(self.upload_photo(b'<?php echo "bad"; ?>')[0], 400)
        self.assertEqual(self.upload_photo(b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')[0], 400)
        self.assertEqual(self.upload_photo(b'')[0], 400)
        self.assertEqual(self.upload_photo(png_fixture(), csrf='wrong')[0], 403)
        self.assertEqual(self.upload_photo(png_fixture(), client=self.customer)[0], 401)

    def test_video_larger_than_previous_limit(self):
        # Stream a valid MP4 plus a standards-compliant padding box, without
        # constructing a 200 MB request in memory. Hosting permits 210M here.
        payload_size = 200_000_032
        fixture = (FIXTURES / 'sample.mp4').read_bytes()
        padding_size = payload_size - len(fixture)
        boundary = 'LargeVideoBoundary'
        prefix = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="large.mp4"\r\nContent-Type: video/mp4\r\n\r\n').encode()
        suffix = f'\r\n--{boundary}--\r\n'.encode()
        def chunks():
            yield prefix
            yield fixture
            yield struct.pack('>I4s', padding_size, b'free')
            remaining = padding_size - 8
            block = bytes(65536)
            while remaining:
                count = min(remaining, len(block))
                yield block[:count]
                remaining -= count
            yield suffix
        jar = next(h.cookiejar for h in self.admin.opener.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
        cookie = '; '.join(f'{item.name}={item.value}' for item in jar)
        connection = http.client.HTTPConnection('127.0.0.1', self.port, timeout=60)
        try:
            connection.request('POST', '/admin/api.php?action=upload-video', body=chunks(), headers={
                'Content-Type': f'multipart/form-data; boundary={boundary}',
                'Content-Length': str(len(prefix) + payload_size + len(suffix)),
                'X-CSRF-Token': self.csrf, 'Cookie': cookie,
            })
            response = connection.getresponse()
            result = json.loads(response.read())
            self.assertEqual(response.status, 201, result)
        finally:
            connection.close()
        source = result['source']
        self.assertEqual((self.storage / 'videos' / source.removeprefix('upload:')).stat().st_size, payload_size)
        self.update(video_source=source)
        self.unlock()
        code, _, headers = self.customer.request('/instruction-video.php?id=' + self.page['id'], method='HEAD')
        self.assertEqual(code, 200)
        self.assertEqual(int(headers['Content-Length']), payload_size)
        code, body, _ = self.customer.request('/instruction-video.php?id=' + self.page['id'], headers={'Range':'bytes=200000000-200000031'})
        self.assertEqual(code, 206)
        self.assertEqual(body, bytes(32))

    def tearDown(self):
        code, result = self.admin.api('delete-instruction', {'id': self.page['id'], 'version': self.page['version']}, self.csrf)
        self.assertEqual(code, 200, result)

    def test_public_site_and_locked_page(self):
        code, home, _ = self.customer.request('/')
        self.assertEqual(code, 200)
        self.assertIn(b'Watch your instructions', home)
        _, listing, _ = self.customer.request('/instructions.php')
        self.assertIn(self.slug.encode(), listing)
        code, body, headers = self.customer.request(self.path)
        self.assertEqual(code, 200)
        self.assertIn(b'Trick password', body)
        self.assertNotIn(b'PRIVATE SECRET NOTES', body)
        self.assertNotIn(b'dQw4w9WgXcQ', body)
        self.assertNotIn(b'Printed-Secret', body)
        self.assertNotIn(b'password_hash', body)
        self.assertIn('no-store', headers['Cache-Control'])
        self.assertIn('noindex', headers['X-Robots-Tag'])
        self.assertIn(b'&lt;special&gt;', body)

    def test_correct_wrong_password_lock_and_csrf(self):
        code, body, _ = self.unlock('wrong')
        self.assertEqual(code, 403)
        self.assertNotIn(b'dQw4w9WgXcQ', body)
        code, _, _ = self.customer.form(self.path, {'_csrf': 'wrong', 'password': 'Printed-Secret'})
        self.assertEqual(code, 403)
        code, body, headers = self.unlock()
        self.assertEqual(code, 200)
        self.assertIn(b'PRIVATE SECRET NOTES', body)
        self.assertIn(b'&lt;safe&gt;', body)
        self.assertIn(b'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', body)
        self.assertIn('https://www.youtube-nocookie.com', headers['Content-Security-Policy'])
        _, body, _ = self.customer.form(self.path, {'_csrf': self.customer.token(self.path), 'action': 'lock'})
        self.assertNotIn(b'PRIVATE SECRET NOTES', body)

    def test_password_replacement_and_expiry(self):
        self.unlock()
        self.update(password='New-Printed-Secret')
        self.assertNotIn(b'PRIVATE SECRET NOTES', self.customer.request(self.path)[1])
        self.assertEqual(self.unlock()[0], 403)
        self.assertIn(b'PRIVATE SECRET NOTES', self.unlock('New-Printed-Secret')[1])
        # Expire this test session on disk, as PHP would after its two-hour grant.
        for path in (self.storage / 'sessions').glob('sess_*'):
            text = path.read_text()
            if self.page['id'] in text:
                text = re.sub(r'("expires";i:)\d+', r'\g<1>1', text)
                path.write_text(text)
        self.assertNotIn(b'PRIVATE SECRET NOTES', self.customer.request(self.path)[1])

    def test_hidden_and_deleted_pages(self):
        self.unlock()
        self.update(published=False)
        self.assertEqual(self.customer.request(self.path)[0], 404)
        self.assertNotIn(self.slug.encode(), self.customer.request('/instructions.php')[1])
        code, _ = self.admin.api('delete-instruction', {'id': self.page['id'], 'version': self.page['version']}, self.csrf)
        self.assertEqual(code, 200)
        self.assertEqual(self.customer.request(self.path)[0], 404)
        self.page = self.save(title='Replacement', slug=self.slug, password='replacement')

    def test_private_upload_stream_ranges_and_isolation(self):
        code, uploaded = self.upload()
        self.assertEqual(code, 201, uploaded)
        self.update(video_source=uploaded['source'])
        path = '/instruction-video.php?id=' + self.page['id']
        self.assertEqual(self.customer.request(path)[0], 404)
        self.unlock()
        code, data, headers = self.customer.request(path)
        fixture = (FIXTURES / 'sample.mp4').read_bytes()
        self.assertEqual(code, 200)
        self.assertEqual(data, fixture)
        self.assertEqual(headers['Content-Type'], 'video/mp4')
        self.assertEqual(Client(self.base).request(path)[0], 404)
        code, data, headers = self.customer.request(path, headers={'Range': 'bytes=10-39'})
        self.assertEqual(code, 206)
        self.assertEqual(data, fixture[10:40])
        self.assertEqual(headers['Content-Range'], f'bytes 10-39/{len(fixture)}')
        self.assertEqual(self.customer.request(path, headers={'Range': 'bytes=-16'})[1], fixture[-16:])
        self.assertEqual(self.customer.request(path, headers={'Range': 'bytes=99999999-'})[0], 416)
        self.assertEqual(self.customer.request(path, headers={'Range': 'bytes=0-1,4-8'})[0], 416)
        self.assertEqual(self.customer.request(path, method='HEAD')[1], b'')
        code, _, headers = self.customer.request(path, data=b'', method='POST')
        self.assertEqual(code, 405)
        self.update(password='Changed')
        self.assertEqual(self.customer.request(path)[0], 404)
        self.unlock('Changed')
        self.update(published=False)
        self.assertEqual(self.customer.request(path)[0], 404)

    def test_upload_and_api_security(self):
        self.assertEqual(self.upload(b'<?php echo "bad"; ?>')[0], 400)
        self.assertEqual(self.upload(filename='video.php')[0], 400)
        self.assertEqual(self.upload(csrf='wrong')[0], 403)
        self.assertEqual(self.customer.api('instructions')[0], 401)
        self.assertEqual(self.customer.api('save-instruction', self.page)[0], 401)
        self.assertEqual(self.admin.api('save-instruction', self.page, 'wrong')[0], 403)
        code, data = self.admin.api('instructions')
        self.assertEqual(code, 200)
        self.assertNotIn('password_hash', json.dumps(data))
        self.assertNotIn('Printed-Secret', json.dumps(data))
        self.assertTrue(next(p for p in data['instructions'] if p['id'] == self.page['id'])['has_password'])
        for path in ['/app/config.php', '/.git/config', '/INSTALL-PLESK.txt', '/tests/fixtures/sample.mp4']:
            self.assertEqual(self.customer.request(path)[0], 404, path)

    def test_validation_and_concurrent_changes(self):
        old = dict(self.page)
        self.update(title='Changed title', password='')
        self.assertEqual(self.admin.api('save-instruction', {**old, 'password': ''}, self.csrf)[0], 409)
        self.assertEqual(self.admin.api('delete-instruction', {'id': old['id'], 'version': old['version']}, self.csrf)[0], 409)
        self.assertEqual(self.admin.api('save-instruction', {'title': 'Duplicate', 'slug': self.slug, 'password': 'x'}, self.csrf)[0], 400)
        for changes in [{'slug': '../bad'}, {'video_source': 'javascript:alert(1)'}, {'video_source': 'https://youtube.com.evil.test/x.mp4.php'}, {'video_source': 'https://youtu.be/invalid'}, {'video_source': ''}, {'video_source': 'upload:../secret.mp4'}]:
            self.assertEqual(self.admin.api('save-instruction', {**self.page, **changes}, self.csrf)[0], 400, changes)
        self.assertIn(b'PRIVATE SECRET NOTES', self.unlock()[1], 'Blank replacement must retain the password')

    def test_vimeo_and_external_mp4(self):
        self.update(video_source='https://vimeo.com/123456789/abcdef1234')
        body = self.unlock()[1]
        self.assertIn(b'https://player.vimeo.com/video/123456789?h=abcdef1234', body)
        self.update(video_source='https://example.test/lesson.mp4?token=something')
        self.assertIn(b'https://example.test/lesson.mp4?token=something', self.customer.request(self.path)[1])
        self.assertNotIn(b'lesson.mp4', Client(self.base).request(self.path)[1])

    def test_existing_site_editor_and_editor_role(self):
        code, content = self.admin.api('load')
        self.assertEqual(code, 200)
        content['content']['notice'] = 'Existing website editing still works.'
        code, result = self.admin.api('save', content, self.csrf)
        self.assertEqual(code, 200, result)
        self.assertIn(b'Existing website editing still works.', self.customer.request('/')[1])
        code, _ = self.admin.api('add-user', {'email': 'editor@example.test', 'password': 'Editor password 1234'}, self.csrf)
        self.assertEqual(code, 201)
        editor = Client(self.base)
        code, body, _ = editor.form('/admin/login.php', {'_csrf': editor.token('/admin/login.php'), 'email': 'editor@example.test', 'password': 'Editor password 1234'})
        self.assertEqual(code, 200)
        token = json.loads(re.search(rb'id="editor-data">(.*?)</script>', body, re.S)[1])['csrf']
        self.assertEqual(editor.api('instructions')[0], 200)
        code, result = editor.api('save-instruction', {**self.page, 'password': '', 'title': 'Editor can manage instructions'}, token)
        self.assertEqual(code, 200, result)
        self.page = result['instruction']
        self.assertEqual(editor.api('users')[0], 403)
        _, users = self.admin.api('users')
        uid = next(u['id'] for u in users['users'] if u['email'] == 'editor@example.test')
        self.admin.api('remove-user', {'id': uid}, self.csrf)
        self.assertEqual(editor.api('instructions')[0], 401)
        with sqlite3.connect(self.storage / 'site.sqlite') as db:
            self.assertEqual(db.execute('SELECT COUNT(*) FROM users').fetchone()[0], 1)
            self.assertNotIn('Printed-Secret', db.execute('SELECT password_hash FROM instructions WHERE id=?', (self.page['id'],)).fetchone()[0])

    def test_z_rate_limiting(self):
        for _ in range(8):
            self.assertEqual(self.unlock('incorrect')[0], 403)
        self.assertEqual(self.unlock('Printed-Secret')[0], 429)

if __name__ == '__main__':
    unittest.main()
