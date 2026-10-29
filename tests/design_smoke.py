"""Responsive design, progressive enhancement, keyboard and motion checks."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import test_instructions as workflow

server=workflow.InstructionWorkflow
server.setUpClass()
artifacts=Path('/workspace/artifacts/redesign')
artifacts.mkdir(parents=True,exist_ok=True)
try:
    boundary='DesignVideoBoundary'
    fixture=(workflow.FIXTURES/'sample.mp4').read_bytes()
    body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="sample.mp4"\r\nContent-Type: video/mp4\r\n\r\n').encode()+fixture+f'\r\n--{boundary}--\r\n'.encode()
    code,raw,_=server.admin.request('/admin/api.php?action=upload-video',body,{'Content-Type':f'multipart/form-data; boundary={boundary}','X-CSRF-Token':server.csrf})
    assert code==201
    source=__import__('json').loads(raw)['source']
    for title,slug,summary in [('Invisible Deck','invisible-deck','One thought-of card. One impossible reveal. Let’s make it happen.'),('Cups and Balls','cups-and-balls','A timeless classic, ready for your own little twist.'),('Cardtoon 2','cardtoon-2','Bring a little pocket-sized performance to life.')]:
        code,data=server.admin.api('save-instruction',{'title':title,'slug':slug,'summary':summary,'notes':'Take your time and practise the handling before your first performance.\n\nRewatch any details you want to work on. Keep the secret, and enjoy that first reaction.','password':'Practice-Secret','video_source':source,'published':True},server.csrf)
        assert code==200,data
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
        errors=[]
        def capture(page,path):
            page.evaluate('document.fonts.ready')
            page.locator('img[loading=lazy]').evaluate_all("images=>images.forEach(img=>img.loading='eager')")
            page.wait_for_function('() => [...document.images].every(img=>img.complete && img.naturalWidth>0)')
            page.evaluate('document.querySelectorAll(".reveal-pending").forEach(el=>el.classList.remove("reveal-pending"))')
            page.screenshot(path=str(artifacts/path),full_page=True,animations='disabled')
        desktop=browser.new_context(viewport={'width':1440,'height':1000})
        page=desktop.new_page()
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(server.base+'/')
        capture(page,'home-desktop.png')
        page.screenshot(path=str(artifacts/'home-desktop-top.png'),animations='disabled')
        search=page.get_by_role('searchbox',name='Find a magic trick…')
        search.fill('Invisible Deck')
        expect(page.locator('#magic-grid .product:visible')).to_have_count(1)
        expect(page.locator('[data-filter-count="magic-grid"]')).to_have_text('1 result found')
        search.fill('no such trick exists')
        expect(page.locator('[data-filter-empty="magic-grid"]')).to_be_visible()
        page.get_by_role('button',name='Clear search').click()
        expect(page.locator('#magic-grid .product:visible')).to_have_count(12)
        page.goto(server.base+'/instructions.php')
        capture(page,'library-desktop.png')
        page.screenshot(path=str(artifacts/'library-desktop-top.png'),animations='disabled')
        page.get_by_role('searchbox',name='Find your trick…').fill('Cups and Balls')
        expect(page.locator('.lesson-card:visible')).to_have_count(1)
        page.get_by_role('button',name='Clear search').click()
        expect(page.locator('.lesson-card:visible')).to_have_count(3)
        faq=page.locator('.faq-list details').first
        faq.locator('summary').click()
        expect(faq).to_have_attribute('open','')
        page.goto(server.base+'/instructions.php?trick=invisible-deck')
        capture(page,'gate-desktop.png')
        page.get_by_role('button',name='Show trick password').click()
        expect(page.get_by_label('Trick password',exact=True)).to_have_attribute('type','text')
        page.get_by_role('button',name='Hide trick password').click()
        expect(page.get_by_label('Trick password',exact=True)).to_have_attribute('type','password')
        page.get_by_label('Trick password',exact=True).fill('Practice-Secret')
        page.get_by_role('button',name='Unlock instruction video').click()
        expect(page.locator('video')).to_be_visible()
        capture(page,'watch-desktop.png')
        # Verify all main surfaces at small phone, phone, tablet and narrow desktop widths.
        for width in [320,390,768,1024,1440]:
            context=browser.new_context(viewport={'width':width,'height':900})
            view=context.new_page()
            view.on('pageerror',lambda e:errors.append(str(e)))
            for path,label in [('/','home'),('/instructions.php','library'),('/instructions.php?trick=invisible-deck','gate'),('/admin/login.php','login')]:
                view.goto(server.base+path)
                view.evaluate('document.fonts.ready')
                assert view.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Horizontal overflow: {width}px {path}'
                if width in [390,768]:capture(view,f'{label}-{width}.png')
            if width<961:
                view.goto(server.base+'/')
                menu=view.get_by_role('button',name='Open menu')
                menu.click()
                expect(view.get_by_role('navigation',name='Main navigation')).to_be_visible()
                view.keyboard.press('Escape')
                expect(menu).to_be_focused()
                expect(menu).to_have_attribute('aria-expanded','false')
                menu.click()
                view.get_by_role('navigation',name='Main navigation').get_by_role('link',name='Instruction videos').click()
                expect(view.get_by_role('heading',name='Learn the secret.')).to_be_visible()
                expect(view.get_by_role('button',name='Open menu')).to_have_attribute('aria-expanded','false')
            context.close()
        nojs=browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844})
        view=nojs.new_page()
        view.goto(server.base+'/')
        expect(view.get_by_role('heading',name='Invisible Deck',exact=True)).to_be_visible()
        expect(view.locator('.hero h1')).to_be_visible()
        # Navigation must remain available without JavaScript, too.
        expect(view.get_by_role('navigation',name='Main navigation')).to_be_visible()
        view.goto(server.base+'/instructions.php?trick=invisible-deck')
        view.get_by_label('Trick password',exact=True).fill('Practice-Secret')
        # Exercise native keyboard submission with scripting disabled.
        view.get_by_label('Trick password',exact=True).press('Enter')
        expect(view.locator('video')).to_be_visible()
        reduced=browser.new_context(reduced_motion='reduce')
        view=reduced.new_page()
        view.goto(server.base+'/')
        assert view.locator('.hero-note').evaluate('el=>getComputedStyle(el).animationName')=='none'
        assert view.locator('.reveal-pending').count()==0
        assert not errors,errors
        browser.close()
    print('Design smoke passed: five viewport widths, collection/library search, show/hide password, FAQ, mobile menu keyboard/links, no-JS navigation/unlock, reduced motion, no horizontal overflow or JavaScript errors.')
finally:
    server.tearDownClass()
