<?php
require __DIR__.'/app/bootstrap.php';security_headers();
$key=$_GET['image']??'';if(!is_string($key)||!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D',$key)){http_response_code(404);exit;}
$path=config()['storage_dir'].'/uploads/'.$key;if(!is_file($path)){http_response_code(404);exit;}
$mime=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][pathinfo($path,PATHINFO_EXTENSION)];header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: public, max-age=31536000, immutable');readfile($path);
