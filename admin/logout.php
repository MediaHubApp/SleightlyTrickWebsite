<?php
require dirname(__DIR__).'/app/bootstrap.php';session_boot();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
try{csrf_check();}catch(Throwable $e){http_response_code(403);exit('Please return to the editor and try again.');}
$_SESSION=[];session_destroy();setcookie('ST_STALLHOLDER','',['expires'=>time()-3600,'path'=>'/','secure'=>https(),'httponly'=>true,'samesite'=>'Lax']);header('Location: /admin/login.php',true,303);
