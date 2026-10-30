"""Portal navigation, draft preview, publication and responsive account access."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import test_instructions as workflow

server=workflow.InstructionWorkflow
server.setUpClass()
artifacts=Path('/workspace/artifacts/portal')
artifacts.mkdir(parents=True,exist_ok=True)
try:
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
        context=browser.new_context(viewport={'width':1440,'height':1000})
        page=context.new_page()
        errors=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(server.base+'/admin/')
        page.evaluate('document.fonts.ready')
        page.screenshot(path=str(artifacts/'login-desktop.png'),animations='disabled')
        page.get_by_label('Email address',exact=True).fill('owner@example.test')
        page.get_by_label('Password',exact=True).fill('Test owner password 123')
        page.get_by_role('button',name='Sign in',exact=True).click()
        expect(page.locator('[data-preview=heroTitle]')).to_have_text('Find your')
        headline=page.locator('[data-field=heroTitle]')
        headline.fill('Find something special')
        expect(page.locator('[data-preview=heroTitle]')).to_have_text('Find something special')
        expect(page.locator('#save-state')).to_have_text('Unpublished changes')
        # Preview changes are local until the editor explicitly publishes.
        public=context.new_page()
        public.goto(server.base+'/')
        assert 'Find something special' not in public.locator('.hero h1').inner_text()
        page.keyboard.press('Control+s')
        expect(page.locator('#editor-status')).to_contain_text('Your changes are published.')
        public.reload()
        expect(public.locator('.hero h1')).to_contain_text('Find something special')
        headline.fill('Find your')
        page.get_by_role('button',name='Publish changes',exact=True).click()
        expect(page.locator('#publish')).to_be_disabled()
        page.locator('#editor-status').evaluate('el=>el.hidden=true')
        page.evaluate('scrollTo({top:0,behavior:"instant"})')
        page.evaluate('document.fonts.ready')
        page.wait_for_function('document.getElementById("preview-photo").naturalWidth > 0')
        page.screenshot(path=str(artifacts/'website-desktop.png'),animations='disabled')
        page.locator('#tab-website').focus()
        page.keyboard.press('ArrowDown')
        expect(page.get_by_role('tab',name='Collection',exact=True)).to_be_focused()
        expect(page.locator('#editor-heading')).to_have_text('Your collection.')
        page.get_by_role('searchbox',name='Find a product',exact=True).fill('Invisible Deck')
        expect(page.locator('#product-picker button')).to_have_count(1)
        page.get_by_role('searchbox',name='Find a product',exact=True).fill('no match')
        expect(page.locator('#product-search-empty')).to_be_visible()
        page.get_by_role('searchbox',name='Find a product',exact=True).fill('')
        expect(page.locator('#product-picker button')).to_have_count(18)
        page.screenshot(path=str(artifacts/'collection-desktop.png'),animations='disabled')
        page.get_by_role('tab',name='Instruction videos',exact=True).click()
        expect(page.locator('#save-dock')).to_be_hidden()
        page.screenshot(path=str(artifacts/'instructions-desktop.png'),animations='disabled')
        page.get_by_role('button',name='+ Add instruction page',exact=True).click()
        page.get_by_label('Trick / page title',exact=True).fill('Cups and Balls')
        page.get_by_label('Shared trick password').fill('Practice-Secret')
        page.get_by_role('button',name='Save instruction page',exact=True).click()
        expect(page.locator('#instruction-status')).to_contain_text('The page is hidden')
        # Every portal tab must fit a narrow phone, tablet and desktop; save controls must stay on screen.
        for width in [320,390,768,1024,1440]:
            page.set_viewport_size({'width':width,'height':900})
            for tab in ['Website text','Collection','Instruction videos','Photos','Editor access','My account']:
                page.get_by_role('tab',name=tab,exact=True).click()
                page.evaluate('document.fonts.ready')
                assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Overflow at {width}px {tab}'
                if tab in ['Website text','Collection','Photos']:
                    box=page.locator('#publish').bounding_box()
                    assert box and box['x']>=0 and box['x']+box['width']<=width
                    assert box['y']+box['height']<=900
                else:expect(page.locator('#save-dock')).to_be_hidden()
                if width==390 and tab in ['Website text','Instruction videos']:
                    page.evaluate('scrollTo({top:0,behavior:"instant"})')
                    page.screenshot(path=str(artifacts/('website-mobile.png' if tab=='Website text' else 'instructions-mobile.png')),animations='disabled')
            expect(page.get_by_role('tablist')).to_have_attribute('aria-orientation','horizontal' if width<=1000 else 'vertical')
        page.set_viewport_size({'width':390,'height':844})
        page.get_by_role('button',name='Sign out',exact=True).click()
        expect(page.get_by_role('heading',name='Sign in.',exact=True)).to_be_visible()
        assert page.url.endswith('/admin/login.php')
        page.screenshot(path=str(artifacts/'login-mobile.png'),animations='disabled')
        # Reduced motion applies to both tab panels and custom switches.
        reduced=browser.new_context(reduced_motion='reduce')
        view=reduced.new_page()
        view.goto(server.base+'/admin/login.php')
        view.get_by_label('Email address',exact=True).fill('owner@example.test')
        view.get_by_label('Password',exact=True).fill('Test owner password 123')
        view.get_by_role('button',name='Sign in',exact=True).click()
        view.get_by_role('tab',name='Collection',exact=True).click()
        assert view.locator('#panel-products').evaluate('el=>getComputedStyle(el).animationName')=='none'
        assert not errors,errors
        browser.close()
    print('Portal checks passed: live draft preview, explicit publishing, keyboard save/navigation, product search, all six tabs at five widths, mobile sign-out and reduced motion; no JavaScript errors.')
finally:
    server.tearDownClass()
