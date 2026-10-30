<?php
// PHP's built-in server does not read Apache .htaccess rules.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(in_array($path,['/assets/cards/NOTICE.txt','/assets/cards/LICENSE-LGPL-3.0.txt','/assets/cards/LICENSE-GPL-3.0.txt','/images/PHOTO-CREDITS.txt'],true))return false;
if(str_contains($path,'..')||preg_match('~(?:^|/)(?:\.[^/]*|app|tools|tests|releases)(?:/|$)|\.(?:txt|md|zip|sqlite|db|log|bak|env)$~i',$path)){
 http_response_code(404);echo 'Not found';return true;
}
return false;
