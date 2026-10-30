"""The supplied price list and safe upgrades for existing catalogues."""
import json
import os
from pathlib import Path
import subprocess
import unittest

SOURCE=Path(__file__).resolve().parents[1]
ROOT=Path(os.environ.get('ST_TEST_SITE_ROOT',SOURCE))
PHP=os.environ.get('PHP_BIN','php')
EXPECTED=json.loads((SOURCE/'tests/fixtures/price-list.json').read_text())

def php(code):
    return json.loads(subprocess.check_output([PHP,'-r','require $argv[1]; '+code,str(ROOT/'app/bootstrap.php')],text=True))

class Catalogue(unittest.TestCase):
    def test_all_items_and_shared_prices_match_the_document(self):
        data=php('echo json_encode(defaults());')
        actual=[{key:p[key] for key in ['name','price','category']} for p in data['products']]
        self.assertEqual(actual,EXPECTED)
        self.assertEqual(len({p['id'] for p in data['products']}),28)
        self.assertEqual({key:sum(p['category']==key for p in data['products']) for key in ['decks','magic','sets','cards']},{'decks':7,'magic':13,'sets':5,'cards':3})
        self.assertEqual([p['price'] for p in data['products'][:5]],[12]*5)
        self.assertEqual([p['price'] for p in data['products'][7:16]],[8]*9)

    def test_upgrade_updates_prices_and_preserves_existing_assets(self):
        data=php('''$c=defaults(); unset($c['catalogueRevision']);
            $c['products']=require APP_ROOT.'/app/catalogue-before-price-list.php';
            $c['products'][0]['image']='/media.php?image=owner.jpg';
            $c['products'][0]['description']='Our own performance notes.';
            $c['products'][0]['price']=99;
            $c['products'][4]['visible']=false;
            $c['products'][]=['id'=>'our-special','name'=>'Our special trick','price'=>23,'description'=>'Custom description.','category'=>'magic','image'=>'','label'=>'','visible'=>true];
            echo json_encode(design_content($c));''')
        by_id={p['id']:p for p in data['products']}
        self.assertEqual(by_id['invisible']['price'],12)
        self.assertEqual(by_id['invisible']['image'],'/media.php?image=owner.jpg')
        self.assertEqual(by_id['invisible']['description'],'Our own performance notes.')
        self.assertFalse(by_id['ghost']['visible'])
        self.assertEqual(by_id['cups']['price'],8)
        self.assertEqual(by_id['prisma']['name'],'Lights from Anywhere')
        self.assertFalse(by_id['bwave']['visible'])
        self.assertFalse(by_id['spiderman']['visible'])
        self.assertEqual(by_id['our-special']['price'],23)
        self.assertTrue(by_id['our-special']['visible'])
        self.assertEqual(sum(p['category']=='cards' and p['visible'] for p in data['products']),3)
        self.assertEqual(len(by_id),len(data['products']))

    def test_aliases_keep_existing_product_ids_without_duplicates(self):
        data=php('''$c=defaults(); unset($c['catalogueRevision']);
            $c['products']=[['id'=>'owner-disney','name'=>'Disney Playing Cards','price'=>7,'description'=>'Our Disney designs.','category'=>'cards','image'=>'/images/banner.jpeg','label'=>'','visible'=>true],
              ['id'=>'owner-invisible','name'=>'Invisible','price'=>10,'description'=>'Our invisible routine.','category'=>'magic','image'=>'/images/hero.png','label'=>'','visible'=>true],
              ['id'=>'owner-book','name'=>'Magic Coloring Book','price'=>9,'description'=>'Our book.','category'=>'magic','image'=>'/images/banner.jpeg','label'=>'','visible'=>true]];
            echo json_encode(upgrade_catalogue($c));''')
        self.assertEqual(len(data['products']),28)
        by_name={p['name']:p for p in data['products']}
        self.assertEqual(by_name['Disney Cards']['id'],'owner-disney')
        self.assertEqual(by_name['Disney Cards']['price'],8)
        self.assertEqual(by_name['Disney Cards']['image'],'/images/banner.jpeg')
        self.assertEqual(by_name['Invisible Deck']['id'],'owner-invisible')
        self.assertEqual(by_name['Invisible Deck']['price'],12)
        self.assertEqual(by_name['Magic Colouring Book']['id'],'owner-book')
        self.assertEqual(by_name['Magic Colouring Book']['price'],10)

    def test_later_admin_edits_and_removals_are_not_reimported(self):
        data=php('''$c=defaults(); $c['products'][0]['price']=14;
            $c['products'][0]['visible']=false;
            $c['products']=array_values(array_filter($c['products'],fn($p)=>$p['id']!=='atom-rings'));
            $c=validate_content($c); echo json_encode(design_content($c));''')
        self.assertEqual(data['products'][0]['price'],14)
        self.assertFalse(data['products'][0]['visible'])
        self.assertNotIn('atom-rings',[p['id'] for p in data['products']])
        self.assertEqual(len(data['products']),27)

if __name__=='__main__':unittest.main()
