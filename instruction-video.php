<?php
require __DIR__.'/app/bootstrap.php';
try {
 session_boot();
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
 $id=$_GET['id']??'';
 $page=is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)?instruction_by('id',$id):null;
 if(!$page||!instruction_unlocked($page)||!preg_match('/^upload:([a-f0-9]{32}\.mp4)$/D',$page['video_source'],$m)){http_response_code(404);exit;}
 $path=storage().'/videos/'.$m[1];
 if(!is_file($path)){http_response_code(404);exit;}
 $file=fopen($path,'rb');if(!$file)throw new RuntimeException('Cannot read video.');
 $size=filesize($path);$start=0;$end=$size-1;
 // A single byte range lets browsers seek without exposing private files.
 $range=$_SERVER['HTTP_RANGE']??'';
 if($range!==''&&$_SERVER['REQUEST_METHOD']==='GET'){
  if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$parts)||($parts[1]===''&&$parts[2]==='')){
   header('Content-Range: bytes */'.$size);http_response_code(416);exit;
  }
  if($parts[1]===''){$start=max(0,$size-(int)$parts[2]);}else{$start=(int)$parts[1];if($parts[2]!=='')$end=min($end,(int)$parts[2]);}
  if($start>$end||$start>=$size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
  http_response_code(206);header("Content-Range: bytes $start-$end/$size");
 }
 header('Content-Type: video/mp4');header('Content-Disposition: inline; filename="instruction.mp4"');header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));
 session_write_close();
 if($_SERVER['REQUEST_METHOD']==='HEAD'){fclose($file);exit;}
 set_time_limit(0);fseek($file,$start);$remaining=$end-$start+1;
 while($remaining>0&&!connection_aborted()){$chunk=fread($file,min(1048576,$remaining));if($chunk===false||$chunk==='')break;echo $chunk;$remaining-=strlen($chunk);flush();}
 fclose($file);
}catch(Throwable $e){error_log('Instruction video: '.$e->getMessage());http_response_code(503);}
