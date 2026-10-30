<?php
// PHP's built-in server does not read Apache .htaccess rules.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(str_contains($path,'..')||preg_match('~(?:^|/)(?:\.[^/]*|app|tools|tests|releases)(?:/|$)|\.(?:txt|md|zip|sqlite|db|log|bak|env)$~i',$path)){
 http_response_code(404);echo 'Not found';return true;
}
return false;
