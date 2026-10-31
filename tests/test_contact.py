"""Contact form HTTP tests with a fake local sendmail; no real messages leave the test."""
from email import policy
from email.parser import BytesParser
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import unittest
import urllib.error
import test_instructions as workflow


class ContactWorkflow(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='sleightly-contact-')
        cls.directory = Path(cls.temp.name)
        cls.storage = cls.directory / 'private'
        cls.storage.mkdir()
        cls.capture = cls.directory / 'mail.json'
        cls.failure = cls.directory / 'fail-mail'
        cls.mailer = cls.directory / 'capture.py'
        cls.mailer.write_text('''import json, os, sys
from pathlib import Path
folder=Path(os.environ['ST_CONTACT_CAPTURE'])
payload=sys.stdin.buffer.read().decode('utf-8')
if (folder/'fail-mail').exists(): sys.exit(1)
path=folder/'mail.json'
messages=json.loads(path.read_text()) if path.exists() else []
messages.append({'args':sys.argv[1:],'message':payload})
path.write_text(json.dumps(messages))
''')
        cls.write_config()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{cls.port}'
        cls.log = open(cls.directory / 'server.log', 'wb')
        cls.server = subprocess.Popen([
            os.environ.get('PHP_BIN', 'php'), '-d', f'sendmail_path=python3 {cls.mailer}',
            '-S', f'127.0.0.1:{cls.port}', '-t', str(workflow.ROOT), str(workflow.SOURCE_ROOT / 'tools/dev-router.php'),
        ], cwd=workflow.ROOT, env={**os.environ, 'ST_LOCAL_HTTP':'1','ST_STORAGE_DIR':str(cls.storage),'ST_CONTACT_CAPTURE':str(cls.directory)}, stdout=cls.log, stderr=cls.log)
        for _ in range(100):
            try:
                workflow.Client(cls.base).request('/contact.php')
                break
            except urllib.error.URLError:
                if cls.server.poll() is not None:
                    raise AssertionError('PHP contact server exited')
                time.sleep(.05)

    @classmethod
    def write_config(cls, sender='contact@example.test', recipient='enquiries@example.test'):
        (cls.storage / 'contact-mail.php').write_text("<?php return ['to'=>" + json.dumps(recipient) + ",'from'=>" + json.dumps(sender) + '];')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)
        cls.log.close()
        cls.temp.cleanup()

    def clear_attempts(self):
        if (self.storage / 'site.sqlite').exists():
            with sqlite3.connect(self.storage / 'site.sqlite') as db:
                if db.execute("SELECT name FROM sqlite_master WHERE name='attempts'").fetchone():
                    db.execute('DELETE FROM attempts')

    def setUp(self):
        self.write_config()
        self.capture.write_text('[]')
        self.failure.unlink(missing_ok=True)
        self.clear_attempts()
        self.customer = workflow.Client(self.base)

    def payload(self, **changes):
        code, body, _ = self.customer.request('/contact.php')
        self.assertEqual(code, 200)
        tokens = {key:re.search(('name="' + key + '" value="([a-f0-9]+)"').encode(), body)[1].decode() for key in ['_csrf','_form_id']}
        return {**tokens, 'name':'Alex Example','email':'visitor@example.test','topic':'Product question','message':'Do you have this deck in stock?','website':'', **changes}

    def messages(self):
        return json.loads(self.capture.read_text())

    def test_public_links_private_settings_and_allowed_methods(self):
        code, body, headers = self.customer.request('/contact.php')
        self.assertEqual(code, 200)
        self.assertIn(b'Send us a message.', body)
        self.assertIn('no-store', headers['Cache-Control'])
        self.assertNotIn(b'enquiries@example.test', body)
        self.assertNotIn(b'contact-mail.php', body)
        self.assertGreaterEqual(self.customer.request('/')[1].count(b'href="/contact.php"'), 3)
        self.assertEqual(self.customer.request('/app/contact.php')[0], 404)
        self.assertEqual(self.customer.request('/contact.php', method='PUT')[0], 405)

    def test_send_utf8_reply_to_fixed_sender_and_refresh_without_duplicates(self):
        payload = self.payload(name='Alex <Example> & Co', email='visitor+question@example.test', message='Hello — café!\nA second line with <cards>.')
        code, body, _ = self.customer.form('/contact.php', payload)
        self.assertEqual(code, 200)
        self.assertIn(b'Message sent.', body)
        messages = self.messages()
        self.assertEqual(len(messages), 1)
        message = BytesParser(policy=policy.default).parsebytes(messages[0]['message'].encode())
        self.assertEqual(message['To'], 'enquiries@example.test')
        self.assertEqual(message['From'], 'Sleightly Tricky <contact@example.test>')
        self.assertEqual(message['Reply-To'], payload['email'])
        self.assertEqual(message['Subject'], '[Sleightly Tricky] Product question')
        self.assertEqual(messages[0]['args'], ['-fcontact@example.test'])
        self.assertIn(payload['message'], message.get_content())
        self.assertIn(payload['name'], message.get_content())
        self.customer.request('/contact.php')
        code, body, _ = self.customer.form('/contact.php', payload)
        self.assertEqual(code, 403)
        self.assertNotIn(b'type="submit" disabled', body)
        self.assertEqual(len(self.messages()), 1)
        with sqlite3.connect(self.storage / 'site.sqlite') as db:
            dump = '\n'.join(db.iterdump())
            self.assertNotIn(payload['email'], dump)
            self.assertNotIn(payload['message'], dump)

    def test_validation_escaping_and_header_injection(self):
        for changes in [
            {'email':'visitor@example.test\r\nBcc: victim@example.test'},
            {'name':'Alex\nBcc: victim@example.test'},
            {'topic':'Product question\r\nBcc: victim@example.test'},
            {'email':'not-an-email'}, {'name':''}, {'message':' '},
            {'name':'A'*101}, {'message':'x'*5001}, {'message':'x'*20001},
            {'name[]':'array','name':None}, {'message':'message\x00hidden'},
        ]:
            with self.subTest(changes=changes):
                self.clear_attempts()
                payload = self.payload(**changes)
                if 'name[]' in changes:
                    payload.pop('name')
                code, body, _ = self.customer.form('/contact.php', payload)
                self.assertEqual(code, 400)
                self.assertIn(b'Please check the highlighted fields.', body)
                self.assertEqual(self.messages(), [])
        self.clear_attempts()
        code, body, _ = self.customer.form('/contact.php', self.payload(name='<script>alert(1)</script>', email='bad', message='A <private> question'))
        self.assertEqual(code, 400)
        self.assertIn(b'&lt;script&gt;alert(1)&lt;/script&gt;', body)
        self.assertNotIn(b'<script>alert(1)</script>', body)
        self.assertIn(b'A &lt;private&gt; question', body)

    def test_csrf_spam_trap_and_duplicate_form(self):
        code, body, _ = self.customer.form('/contact.php', self.payload(_csrf='wrong'))
        self.assertEqual(code, 403)
        self.assertNotIn(b'type="submit" disabled', body)
        self.assertEqual(self.messages(), [])
        code, body, _ = self.customer.form('/contact.php', self.payload(website='https://spam.example.test'))
        self.assertEqual(code, 200)
        self.assertIn(b'Message sent.', body)
        self.assertEqual(self.messages(), [])

    def test_rate_limit_counts_successes(self):
        for _ in range(5):
            self.assertEqual(self.customer.form('/contact.php', self.payload())[0], 200)
        code, body, _ = self.customer.form('/contact.php', self.payload())
        self.assertEqual(code, 429)
        self.assertIn(b'15 minutes', body)
        self.assertEqual(len(self.messages()), 5)

    def test_mail_failure_preserves_message_and_allows_retry(self):
        self.failure.touch()
        payload = self.payload(message='Please keep my question if delivery fails.')
        code, body, _ = self.customer.form('/contact.php', payload)
        self.assertEqual(code, 503)
        self.assertIn(payload['message'].encode(), body)
        self.assertNotIn(b'Message sent.', body)
        self.assertNotIn(b'type="submit" disabled', body)
        self.assertEqual(self.messages(), [])
        self.failure.unlink()
        self.assertEqual(self.customer.form('/contact.php', payload)[0], 200)
        self.assertEqual(len(self.messages()), 1)

    def test_missing_or_invalid_configuration_never_sends(self):
        (self.storage / 'contact-mail.php').unlink()
        code, body, _ = self.customer.request('/contact.php')
        self.assertEqual(code, 200)
        self.assertIn(b'temporarily unavailable', body)
        self.assertIn(b'type="submit" disabled', body)
        self.assertEqual(self.customer.form('/contact.php', self.payload())[0], 503)
        for sender in ['contact@example.test -X/tmp/mail.log','contact@example.test\r\nBcc: other@example.test','-options@example.test']:
            self.write_config(sender=sender)
            code, body, _ = self.customer.request('/contact.php')
            self.assertEqual(code, 503)
            self.assertNotIn(sender.encode(), body)
            self.assertEqual(self.messages(), [])


if __name__ == '__main__':
    unittest.main()
