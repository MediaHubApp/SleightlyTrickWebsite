"""Check card semantics and compatibility with existing site content."""
import json
import os
from pathlib import Path
import subprocess
import unittest
import xml.etree.ElementTree as ET

ROOT=Path(os.environ.get('ST_TEST_SITE_ROOT',Path(__file__).resolve().parents[1]))
PHP=os.environ.get('PHP_BIN','php')
NS={'s':'http://www.w3.org/2000/svg'}

def php(code,*args):
    return subprocess.check_output([PHP,'-r','require $argv[1]; '+code,str(ROOT/'app/bootstrap.php'),*args],text=True)

class CardArtwork(unittest.TestCase):
    def test_standard_card_pips_colours_and_proportions(self):
        from html.parser import HTMLParser
        class Image(HTMLParser):
            def handle_starttag(self, tag, attrs):
                if tag=='img': self.attrs=dict(attrs)
        for suit in ['spades','clubs','hearts','diamonds']:
            for rank in [1,2,7]:
                with self.subTest(suit=suit,rank=rank):
                    image=Image()
                    image.feed(php('playing_card($argv[2],(int)$argv[3]);',suit,str(rank)))
                    svg=ET.parse(ROOT/image.attrs['src'].lstrip('/')).getroot()
                    _,_,width,height=map(float,svg.attrib['viewBox'].split())
                    self.assertAlmostEqual(width/height,5/7,delta=.005)
                    colour='#C0242A' if suit in ['hearts','diamonds'] else '#0A0A08'
                    marks=[p for p in svg.findall('.//s:path',NS) if p.get('fill')==colour]
                    # The source has one path per centre pip, plus the two
                    # corner ranks and their two matching suit marks.
                    self.assertEqual(len(marks)-4,rank)
                    self.assertFalse(svg.findall('.//s:script',NS))
                    self.assertFalse(svg.findall('.//s:image',NS))

    def test_back_is_the_unmodified_licensed_rider_design(self):
        from hashlib import sha256
        source=ROOT/'assets/cards/rider-back.svg'
        self.assertEqual(sha256(source.read_bytes()).hexdigest(),'2ea6d8f7b57ee7b2fd78beae0402a13a98ac173dd5b5fdef1dfb3d12f3a8332b')
        self.assertIn('id="Bicycle"',source.read_text())
        self.assertTrue((ROOT/'assets/cards/LICENSE-LGPL-3.0.txt').is_file())
        self.assertTrue((ROOT/'assets/cards/LICENSE-GPL-3.0.txt').is_file())
        for blue in ['true','false']:
            rendered=php('card_back('+blue+');')
            self.assertIn('/assets/cards/rider-back.svg',rendered)

    def test_stock_copy_updates_without_replacing_custom_content(self):
        result=json.loads(php('''$old=require APP_ROOT.'/app/previous-copy.php';
            $old['notice']='Our own trading information.';
            $old['bannerImage']='/media.php?image=custom.jpg';
            $old['products']=[['id'=>'custom','name'=>'Our own trick']];
            echo json_encode(design_content($old));'''))
        self.assertEqual(result['heroTitle'],'Find your')
        self.assertEqual(result['heroImage'],'/images/cards-at-table.jpg')
        self.assertEqual(result['storyImage'],'')
        for stock_image in ['/images/hero.png','/images/still-life.png']:
            migrated=json.loads(php('$old=defaults(); $old["heroImage"]=$argv[2]; echo json_encode(design_content($old));',stock_image))
            self.assertEqual(migrated['heroImage'],'/images/cards-at-table.jpg')
        custom=json.loads(php('$old=defaults(); $old["heroImage"]="/media.php?image=custom.jpg"; $old["storyImage"]="/media.php?image=story.jpg"; echo json_encode(design_content($old));'))
        self.assertEqual(custom['heroImage'],'/media.php?image=custom.jpg')
        self.assertEqual(custom['storyImage'],'/media.php?image=story.jpg')
        self.assertEqual(result['notice'],'Our own trading information.')
        self.assertEqual(result['bannerImage'],'/media.php?image=custom.jpg')
        self.assertEqual(result['products'],[{'id':'custom','name':'Our own trick'}])
        products=json.loads(php("$old=require APP_ROOT.'/app/previous-copy.php'; $old['products'][0]['price']=199.99; $old['products'][1]['description']='Our own description.'; echo json_encode(design_content($old)['products']);"))
        self.assertEqual(products[0]['price'],199.99)
        self.assertEqual(products[0]['description'],'Reveal a freely named card as the only reversed card in the deck.')
        self.assertEqual(products[1]['description'],'Our own description.')

if __name__=='__main__':unittest.main()
