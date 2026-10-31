"""Responsive and JavaScript-free contact form checks, using only a fake mailer."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import test_contact as contact

server = contact.ContactWorkflow
server.setUpClass()
artifacts = Path('/workspace/artifacts/contact')
artifacts.mkdir(parents=True, exist_ok=True)
try:
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
        errors = []
        for width in [320,390,768,1024,1440,1920]:
            context = browser.new_context(viewport={'width':width,'height':1000}, java_script_enabled=width!=390)
            page = context.new_page()
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(server.base+'/contact.php')
            expect(page.get_by_role('heading',name='Send us a message.',exact=True)).to_be_visible()
            expect(page.get_by_role('button',name='Send message',exact=True)).to_be_enabled()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'Contact overflow at {width}px'
            if width in [390,1440]:
                page.get_by_label('Your name',exact=True).fill('A sample visitor')
                page.get_by_label('Email address',exact=True).fill('visitor@example.test')
                page.get_by_label('What’s it about?',exact=True).select_option(label='Instruction help')
                page.get_by_label('Your message',exact=True).fill('Could you help me find the instructions for my trick?')
                page.get_by_role('button',name='Send message',exact=True).scroll_into_view_if_needed()
                page.get_by_role('button',name='Send message',exact=True).click()
                expect(page.get_by_role('heading',name='Message sent.',exact=True)).to_be_visible()
                page.reload()
                expect(page.get_by_role('heading',name='Send us a message.',exact=True)).to_be_visible()
            if width in [390,1440]:
                page.evaluate('document.fonts.ready')
                page.screenshot(path=str(artifacts/f'contact-{width}.png'),full_page=True,animations='allow')
            context.close()
        assert not errors, errors
        browser.close()
    assert len(__import__('json').loads(server.capture.read_text())) == 2
    print('Contact browser checks passed at six widths; desktop and JavaScript-free mobile submission, confirmation, refresh, no overflow or script errors; only two locally captured messages.')
finally:
    server.tearDownClass()
