"""Exercise real form editing, explicit save, public delivery and responsive controls."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import test_contact as contact

server=contact.ContactWorkflow
server.setUpClass()
artifacts=Path('/workspace/artifacts/contact-admin')
artifacts.mkdir(parents=True,exist_ok=True)
try:
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
        context=browser.new_context(viewport={'width':1440,'height':1000})
        context.add_cookies([{'name':cookie.name,'value':cookie.value,'url':server.base} for cookie in server.admin.cookies])
        page=context.new_page();errors=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.goto(server.base+'/admin/')
        page.get_by_role('tab',name='Contact form',exact=True).click()
        expect(page.locator('#contact-field-picker button')).to_have_count(4)
        expect(page.locator('#save-dock')).to_be_hidden()
        expect(page.locator('#save-contact-form')).to_be_disabled()
        page.locator('#contact-field-picker').get_by_role('button',name='What’s it about?').click()
        page.get_by_label('Dropdown options',exact=False).fill('General enquiry\nProduct question\nInstruction help\nWholesale orders')
        expect(page.locator('#contact-form-preview select option')).to_have_count(5)
        public=browser.new_context().new_page()
        public.goto(server.base+'/contact.php')
        expect(public.get_by_role('option',name='Wholesale orders',exact=True)).to_have_count(0)
        page.get_by_label('Add a field',exact=True).select_option('tel')
        page.get_by_role('button',name='+ Add field',exact=True).click()
        page.get_by_label('Field label',exact=True).fill('Phone number')
        page.locator('#contact-field-required').check()
        page.get_by_role('button',name='Move up',exact=True).click()
        page.locator('#contact-field-picker').get_by_role('button',name='Your name').click()
        page.get_by_role('button',name='Remove field',exact=True).click()
        page.locator('#contact-field-picker').get_by_role('button',name='Email address').click()
        expect(page.get_by_role('button',name='Remove field',exact=True)).to_be_disabled()
        expect(page.locator('#contact-field-required')).to_be_disabled()
        page.keyboard.press('Control+s')
        expect(page.locator('#contact-builder-status')).to_contain_text('The changes are live.')
        public.reload()
        expect(public.get_by_label('Your name',exact=True)).to_have_count(0)
        expect(public.get_by_role('option',name='Wholesale orders',exact=True)).to_have_count(1)
        expect(public.get_by_label('Phone number',exact=True)).to_have_attribute('required','')
        page.reload()
        page.get_by_role('tab',name='Contact form',exact=True).click()
        page.locator('#contact-field-picker').get_by_role('button',name='Phone number').click()
        expect(page.locator('#contact-field-required')).to_be_checked()
        expect(page.get_by_label('Field type',exact=True)).to_have_value('tel')
        public.get_by_label('Email address',exact=True).fill('visitor@example.test')
        public.get_by_label('What’s it about?',exact=True).select_option(label='Wholesale orders')
        public.get_by_label('Phone number',exact=True).fill('+44 1234 567890')
        public.get_by_label('Your message',exact=True).fill('A custom form enquiry.')
        public.get_by_role('button',name='Send message',exact=True).scroll_into_view_if_needed()
        public.get_by_role('button',name='Send message',exact=True).click()
        expect(public.get_by_role('heading',name='Message sent.',exact=True)).to_be_visible()
        for width in [320,390,768,1024,1440,1920]:
            page.set_viewport_size({'width':width,'height':900})
            page.evaluate('scrollTo({top:0,behavior:"instant"})')
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Builder overflow at {width}px'
            expect(page.locator('#save-contact-form')).to_be_in_viewport()
            if width<=1000: expect(page.get_by_role('tab',name='Contact form',exact=True)).to_be_in_viewport()
            page.locator('.contact-builder-preview').scroll_into_view_if_needed()
            expect(page.locator('#save-contact-form')).to_be_in_viewport()
            public.goto(server.base+'/contact.php')
            public.set_viewport_size({'width':width,'height':900})
            assert public.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Custom form overflow at {width}px'
            if width in [390,1440]:
                page.evaluate('scrollTo({top:0,behavior:"instant"})')
                page.screenshot(path=str(artifacts/f'builder-{width}.png'),animations='disabled')
        assert not errors,errors
        browser.close()
    assert len(json.loads(server.capture.read_text()))==1
    print('Contact builder checks passed: add/remove/reorder, required email protection, dropdown editing, preview, Ctrl+S publication/reload, public delivery and six responsive widths.')
finally:
    server.tearDownClass()
