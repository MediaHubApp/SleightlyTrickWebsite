<?php
declare(strict_types=1);

/** Instruction details and passwords live separately from public website content. */
function instruction_by(string $column, string $value): ?array {
 if(!in_array($column,['id','slug'],true))throw new LogicException('Invalid lookup.');
 $q=db()->prepare("SELECT * FROM instructions WHERE $column=?");$q->execute([$value]);return $q->fetch()?:null;
}
function instruction_admin(array $row): array {
 $row['has_password']=$row['password_hash']!=='';unset($row['password_hash']);
 $row['published']=(bool)$row['published'];$row['version']=(int)$row['version'];return $row;
}
/** Use the published collection's images and the same artwork position as its grid. */
function instruction_products(): array {
 $products=[];$visibleIndex=0;
 foreach(content()['content']['products'] as $product){
  $products[$product['id']]=['product'=>$product,'index'=>$product['visible']?$visibleIndex++:0];
 }
 return $products;
}
function instruction_text(array $data,string $key,int $max,bool $required=false): string {
 $v=$data[$key]??'';
 if(!is_string($v)||strlen($v)>$max*4||($required&&!trim($v)))throw new DomainException('Please check the instruction '.$key.' field.');
 return trim($v);
}
function video_source(mixed $source): string {
 if(!is_string($source)||strlen($source)>2000)throw new DomainException('Please enter a supported video link or upload an MP4.');
 $source=trim($source);
 if($source==='')return '';
 if(preg_match('/^upload:([a-f0-9]{32}\.mp4)$/D',$source,$m)){
  if(!is_file(storage().'/videos/'.$m[1]))throw new DomainException('The uploaded video is missing. Please upload it again.');
  return $source;
 }
 $url=parse_url($source);
 if(!$url||($url['scheme']??'')!=='https'||isset($url['user'])||isset($url['pass'])||isset($url['port']))throw new DomainException('Video links must use HTTPS.');
 $host=strtolower($url['host']??'');$path=$url['path']??'';
 if(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtube-nocookie.com'],true)){
  parse_str($url['query']??'',$query);
  $id=$host==='youtu.be'?ltrim($path,'/'):($path==='/watch'?($query['v']??''):preg_replace('~^/(embed|shorts)/~','',$path));
  if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{11}$/D',$id))throw new DomainException('Use a YouTube watch, share or embed link.');
  return 'https://www.youtube-nocookie.com/embed/'.$id;
 }
 if(in_array($host,['vimeo.com','www.vimeo.com','player.vimeo.com'],true)){
  if(!preg_match('~^/(?:video/)?([0-9]+)(?:/([a-f0-9]+))?/?$~D',$path,$m))throw new DomainException('Use a Vimeo video link.');
  parse_str($url['query']??'',$query);$hash=$m[2]??$query['h']??'';
  if(!is_string($hash)||($hash!==''&&!preg_match('/^[a-f0-9]+$/D',$hash)))throw new DomainException('Invalid Vimeo privacy link.');
  return 'https://player.vimeo.com/video/'.$m[1].($hash!==''?'?h='.$hash:'');
 }
 if(!$host||!preg_match('/\.mp4$/iD',$path)||!filter_var($source,FILTER_VALIDATE_URL))throw new DomainException('Use a YouTube or Vimeo link, a direct HTTPS .mp4 link, or upload an MP4.');
 return $source;
}
function save_instruction(array $data): array {
 $id=$data['id']??'';
 if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{32}$/D',$id)))throw new DomainException('Invalid instruction page.');
 $current=$id!==''?instruction_by('id',$id):null;
 if($id!==''&&!$current)throw new DomainException('This instruction page no longer exists.',404);
 $title=instruction_text($data,'title',160,true);$slug=instruction_text($data,'slug',100,true);
 if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||strlen($slug)>100)throw new DomainException('Use a page address with lowercase letters, numbers and single hyphens (for example, svengali-deck).');
 $summary=instruction_text($data,'summary',600);$notes=instruction_text($data,'notes',10000);
 $productId=instruction_text($data+['product_id'=>$current['product_id']??''],'product_id',80);
 if($productId!==''&&!isset(instruction_products()[$productId])&&$productId!==($current['product_id']??''))throw new DomainException('That product is no longer in the collection. Publish your collection changes, then select a product.');
 $source=video_source($data['video_source']??'');$published=$data['published']??false;
 if(!is_bool($published))throw new DomainException('Invalid publication status.');
 if($published&&$source==='')throw new DomainException('Add a video before publishing this page.');
 $password=$data['password']??'';
 if(!is_string($password)||strlen($password)>72||($password!==''&&trim($password)===''))throw new DomainException('Use a shared password between 1 and 72 bytes.');
 if(!$current&&$password==='')throw new DomainException('Set the shared password supplied with this trick.');
 $hash=$password!==''?password_hash($password,PASSWORD_DEFAULT):$current['password_hash'];
 $version=$data['version']??0;
 if(!is_int($version)||($current&&$version!==(int)$current['version']))throw new DomainException('Someone else changed this page. Reload it before saving.',409);
 try{
  if($current){
   $q=db()->prepare('UPDATE instructions SET slug=?,title=?,summary=?,notes=?,video_source=?,password_hash=?,published=?,product_id=?,version=version+1,updated_at=? WHERE id=? AND version=?');
   $q->execute([$slug,$title,$summary,$notes,$source,$hash,(int)$published,$productId,gmdate('c'),$id,$version]);
   if($q->rowCount()!==1)throw new DomainException('Someone else changed this page. Reload it before saving.',409);
  }else{
   $id=bin2hex(random_bytes(16));
   db()->prepare('INSERT INTO instructions(id,slug,title,summary,notes,video_source,password_hash,published,product_id,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$id,$slug,$title,$summary,$notes,$source,$hash,(int)$published,$productId,gmdate('c')]);
  }
 }catch(PDOException $e){if((int)$e->getCode()===23000)throw new DomainException('That page address is already in use. Choose another.');throw $e;}
 return instruction_admin(instruction_by('id',$id));
}
function instruction_unlocked(array $page): bool {
 $grant=$_SESSION['instruction_access'][$page['id']]??null;
 return (bool)$page['published']&&is_array($grant)&&($grant['expires']??0)>time()&&hash_equals($page['password_hash'],$grant['signature']??'');
}
function upload_instruction_video(): string {
 $f=$_FILES['file']??null;
 if($f&&in_array($f['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new DomainException('Your hosting rejected the video size. Increase upload_max_filesize and post_max_size in Plesk, and check the web-server upload limit.',413);
 if($f&&$f['error']===UPLOAD_ERR_PARTIAL)throw new DomainException('The video upload was interrupted. Please try again.');
 if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']<12||!is_uploaded_file($f['tmp_name']))throw new DomainException('Choose a valid MP4 video. If the file did not arrive, check your hosting upload limits and available disk space.');
 $head=file_get_contents($f['tmp_name'],false,null,0,32);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
 if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='mp4'||substr($head,4,4)!=='ftyp'||!in_array($mime,['video/mp4','application/mp4'],true))throw new DomainException('Please choose a valid MP4 video.');
 $dir=storage().'/videos';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Could not create video directory.');
 $key=bin2hex(random_bytes(16)).'.mp4';
 if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$key))throw new RuntimeException('Could not save video.');
 chmod($dir.'/'.$key,0600);return 'upload:'.$key;
}
