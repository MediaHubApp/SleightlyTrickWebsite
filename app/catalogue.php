<?php
declare(strict_types=1);

const CATALOGUE_REVISION = 'price-list-1';

function catalogue_key(string $name): string {
 $name=strtolower(str_replace(['’',"'"],'',$name));
 return str_replace(['sleightlytricky','colour'],['','color'],preg_replace('/[^a-z0-9]+/','',$name));
}

/** Apply the supplied price list once; subsequent admin edits remain authoritative. */
function upgrade_catalogue(array $content): array {
 if(($content['catalogueRevision']??'')===CATALOGUE_REVISION)return $content;
 $old=require __DIR__.'/catalogue-before-price-list.php';
 $oldById=array_column($old,null,'id');$new=defaults()['products'];
 $existing=is_array($content['products']??null)?array_values($content['products']):[];
 $used=[];$products=[];$listedNames=[];
 foreach($new as $target){
  $id=$target['id'];
  $aliases=array_unique(array_map('catalogue_key',[$target['name'],$id,$oldById[$id]['name']??'',str_replace(' Deck','',$target['name']),$id==='disney'?'Disney Playing Cards':($id==='themed'?'Themed Playing Cards':'')]));
  foreach($aliases as $alias)if($alias!=='')$listedNames[$alias]=true;
  $matches=[];
  foreach($existing as $index=>$item){
   if(isset($used[$index])||!is_array($item))continue;
   if(($item['id']??'')===$id||in_array(catalogue_key($item['name']??''),$aliases,true))$matches[]=$index;
  }
  usort($matches,fn($a,$b)=>(int)(($existing[$b]['id']??'')===$id)<=>(int)(($existing[$a]['id']??'')===$id));
  if($matches){
   $index=$matches[0];$used[$index]=true;$item=array_replace($target,$existing[$index]);
   $item['name']=$target['name'];$item['price']=$target['price'];$item['category']=$target['category'];
   if(($item['description']??'')===($oldById[$id]['description']??null))$item['description']=$target['description'];
   // Preserve a product photo when the same item was also entered under another ID.
   if(!$item['image'])foreach($matches as $match)if(!empty($existing[$match]['image'])){$item['image']=$existing[$match]['image'];break;}
   $products[]=$item;
  }else{$products[]=$target;}
 }
 $currentIds=array_column($new,'id');
 $retired=array_diff(array_column($old,'id'),$currentIds);
 foreach($existing as $index=>$item){
  if(isset($used[$index])||!is_array($item))continue;
  if(in_array($item['id']??'',$retired,true)||($item['category']??'')==='cards'||isset($listedNames[catalogue_key($item['name']??'')]))$item['visible']=false;
  $products[]=$item;
 }
 $content['products']=$products;$content['catalogueRevision']=CATALOGUE_REVISION;
 return $content;
}
