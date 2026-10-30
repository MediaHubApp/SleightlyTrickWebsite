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
    def test_rank_has_exactly_the_right_number_of_pips(self):
        for suit in ['spades','clubs','hearts','diamonds']:
            for rank in [1,2,7]:
                with self.subTest(suit=suit,rank=rank):
                    svg=ET.fromstring(php('playing_card($argv[2],(int)$argv[3]);',suit,str(rank)).strip())
                    pips=svg.findall('.//s:path[@data-pip]',NS)
                    self.assertEqual(len(pips),rank)
                    self.assertEqual(svg.attrib['viewBox'],'0 0 250 350')
                    indices=svg.findall('.//s:path[@data-index-suit]',NS)
                    self.assertEqual(len(indices),2)
                    self.assertTrue(all(p.attrib['d']==pips[0].attrib['d'] for p in indices))
                    self.assertEqual([t.text for t in svg.findall('.//s:text',NS)],['A' if rank==1 else str(rank)]*2)

    def test_suit_colour_and_opposing_indices(self):
        for suit,colour in [('spades','#1c1c1a'),('clubs','#1c1c1a'),('hearts','#af2433'),('diamonds','#af2433')]:
            with self.subTest(suit=suit):
                svg=ET.fromstring(php('playing_card($argv[2],7);',suit).strip())
                group=svg.find('s:g',NS)
                self.assertEqual(group.attrib['fill'],colour)
                indices=group.findall('s:g',NS)
                self.assertEqual(indices[1].attrib['transform'],'rotate(180 125 175)')
                self.assertEqual(len(group.findall('s:path',NS)),7)

    def test_stock_copy_updates_without_replacing_custom_content(self):
        result=json.loads(php('''$old=require APP_ROOT.'/app/previous-copy.php';
            $old['notice']='Our own trading information.';
            $old['bannerImage']='/media.php?image=custom.jpg';
            $old['products']=[['id'=>'custom','name'=>'Our own trick']];
            echo json_encode(design_content($old));'''))
        self.assertEqual(result['heroTitle'],'Find your')
        self.assertEqual(result['heroImage'],'/images/still-life.png')
        self.assertEqual(result['notice'],'Our own trading information.')
        self.assertEqual(result['bannerImage'],'/media.php?image=custom.jpg')
        self.assertEqual(result['products'],[{'id':'custom','name':'Our own trick'}])
        products=json.loads(php("$old=require APP_ROOT.'/app/previous-copy.php'; $old['products'][0]['price']=199.99; $old['products'][1]['description']='Our own description.'; echo json_encode(design_content($old)['products']);"))
        self.assertEqual(products[0]['price'],199.99)
        self.assertEqual(products[0]['description'],'Reveal a freely named card as the only reversed card in the deck.')
        self.assertEqual(products[1]['description'],'Our own description.')

if __name__=='__main__':unittest.main()
