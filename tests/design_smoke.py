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
    _, catalogue=server.admin.api('load')
    next(product for product in catalogue['content']['products'] if product['id']=='cups')['image']='/images/still-life.png'
    assert server.admin.api('save',catalogue,server.csrf)[0]==200
    for title,slug,summary in [('Invisible Deck','invisible-deck','Instructions for the Invisible Deck.'),('Cups and Balls','cups-and-balls','Learn the Cups and Balls routine.'),('Cardtoon 2','cardtoon-2','How to use your Cardtoon 2 deck.')]:
        code,data=server.admin.api('save-instruction',{'title':title,'slug':slug,'summary':summary,'product_id':{'invisible-deck':'invisible','cups-and-balls':'cups'}.get(slug,''),'notes':'Take your time and practise the handling before your first performance.\n\nRewatch any details you want to work on. Keep the secret, and enjoy that first reaction.','password':'Practice-Secret','video_source':source,'published':True},server.csrf)
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
        page.locator('.route-collection .route-art').screenshot(path=str(artifacts/'card-art.png'),animations='disabled')
        page.evaluate('scrollTo({top:0,behavior:"instant"})')
        search=page.get_by_role('searchbox',name='Search the collection…')
        search.fill('Invisible Deck')
        expect(page.locator('#collection-grid .product:visible')).to_have_count(1)
        expect(page.locator('[data-filter-count="collection-grid"]')).to_have_text('1 result found')
        trigger=page.locator('#collection-grid [data-product-open]').first
        trigger.click()
        modal=page.get_by_role('dialog',name='Invisible Deck')
        expect(modal).to_be_visible()
        expect(page.locator('#dialog-price')).to_have_text('£12')
        expect(page.locator('#dialog-description')).to_have_text('Reveal a freely named card as the only reversed card in the deck.')
        expect(page.get_by_role('button',name='Close product details')).to_be_focused()
        page.screenshot(path=str(artifacts/'product-details-desktop.png'),animations='disabled')
        page.keyboard.press('Shift+Tab')
        expect(page.locator('#dialog-visit')).to_be_focused()
        page.keyboard.press('Tab')
        expect(page.get_by_role('button',name='Close product details')).to_be_focused()
        page.keyboard.press('Escape')
        expect(modal).not_to_be_visible()
        expect(trigger).to_be_focused()
        page.get_by_role('button',name='Clear search').click()
        page.get_by_role('button',name='Playing cards',exact=True).click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(3)
        expect(page.locator('[data-filter-count="collection-grid"]')).to_have_text('3 results found')
        search.fill('Disney')
        expect(page.locator('#collection-grid .product:visible')).to_have_count(1)
        page.locator('#collection-grid [data-product-open]:visible').click()
        expect(page.locator('#dialog-name')).to_have_text('Disney Cards')
        expect(page.locator('#dialog-price')).to_have_text('£8')
        expect(page.locator('.dialog-browse')).to_be_hidden()
        page.keyboard.press('Escape')
        page.get_by_role('button',name='Clear search').click()
        page.get_by_role('button',name='Magic sets',exact=True).click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(5)
        page.get_by_role('button',name='Visual magic',exact=True).click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(13)
        page.get_by_role('button',name='Trick decks',exact=True).click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(7)
        page.get_by_role('button',name='All products',exact=True).click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(28)
        assert page.locator('#deck-rail').count()==0
        search.fill('no such trick exists')
        expect(page.locator('[data-filter-empty="collection-grid"]')).to_be_visible()
        page.get_by_role('button',name='Clear search').click()
        expect(page.locator('#collection-grid .product:visible')).to_have_count(28)
        page.locator('#collection-grid [data-product-open]').first.click()
        expect(page.locator('#dialog-position')).to_have_text('1 of 28')
        page.get_by_role('button',name='Next product',exact=True).click()
        expect(page.locator('#dialog-name')).to_have_text('Brainwave Deck')
        expect(page.locator('#dialog-position')).to_have_text('2 of 28')
        page.keyboard.press('ArrowLeft')
        expect(page.locator('#dialog-name')).to_have_text('Invisible Deck')
        page.keyboard.press('ArrowLeft')
        expect(page.locator('#dialog-position')).to_have_text('28 of 28')
        page.get_by_role('button',name='Next product',exact=True).click()
        expect(page.locator('#dialog-position')).to_have_text('1 of 28')
        page.screenshot(path=str(artifacts/'product-details-desktop.png'),animations='disabled')
        page.keyboard.press('Escape')
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
            for path,label in [('/','home'),('/instructions.php','library'),('/instructions.php?trick=invisible-deck','gate'),('/instructions.php?trick=cups-and-balls','photo-gate'),('/admin/login.php','login')]:
                view.goto(server.base+path)
                view.evaluate('document.fonts.ready')
                assert view.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Horizontal overflow: {width}px {path}'
                if width in [390,768]:capture(view,f'{label}-{width}.png')
                if width==390 and label=='home':
                    view.screenshot(path=str(artifacts/'home-mobile-top.png'),animations='disabled')
                    view.locator('#collection-grid [data-product-open]').first.click()
                    expect(view.get_by_role('dialog',name='Invisible Deck')).to_be_visible()
                    assert view.evaluate('document.documentElement.scrollWidth <= innerWidth'),'Mobile product dialog overflow'
                    view.screenshot(path=str(artifacts/'product-details-mobile.png'),animations='disabled')
                    view.get_by_role('button',name='Close product details').click()

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
                expect(view.get_by_role('heading',name='Instruction videos.')).to_be_visible()
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
        assert view.locator('.hero-image img').evaluate('el=>getComputedStyle(el).animationName')=='none'
        assert view.locator('.reveal-pending').count()==0
        # Pointer depth activates only with motion enabled and cancels on preference change.
        motion=browser.new_context(viewport={'width':1440,'height':1000})
        moving=motion.new_page()
        moving.on('pageerror',lambda e:errors.append(str(e)))
        moving.goto(server.base+'/')
        box=moving.locator('[data-depth]').bounding_box()
        moving.mouse.move(box['x']+box['width']*.8,box['y']+box['height']*.3)
        moving.wait_for_function('() => getComputedStyle(document.querySelector(".hero-image")).transform !== "none"')
        moving.emulate_media(reduced_motion='reduce')
        moving.wait_for_function('() => getComputedStyle(document.querySelector(".hero-image")).transform === "none"')
        moving.locator('#collection-grid [data-product-open]').first.click()
        moving.get_by_role('button',name='Next product',exact=True).click()
        assert moving.locator('#dialog-art').evaluate('el=>el.getAnimations().length')==0
        moving.emulate_media(reduced_motion='no-preference')
        moving.get_by_role('button',name='Next product',exact=True).click()
        moving.emulate_media(reduced_motion='reduce')
        moving.wait_for_function('() => document.getElementById("dialog-art").getAnimations().length === 0')

        assert not errors,errors
        browser.close()
    print('Design smoke passed: five viewport widths, collection/library search, show/hide password, FAQ, mobile menu keyboard/links, no-JS navigation/unlock, reduced motion, product dialogs/focus, combined category/search filters, pointer depth, no horizontal overflow or JavaScript errors.')
finally:
    server.tearDownClass()
