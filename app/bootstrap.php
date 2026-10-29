<?php
declare(strict_types=1);
const APP_ROOT = __DIR__ . '/..';
const APP_VERSION = '1.1.0';
function config(): array { static $c; return $c ??= require __DIR__.'/config.php'; }
function defaults(): array { static $c; return $c ??= require __DIR__.'/defaults.php'; }
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(float|int $v): string { return '£'.number_format($v, floor($v)==$v ? 0 : 2); }
function safe_json(mixed $data): string { return json_encode($data, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); }
function security_headers(bool $private=false): void {
 header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: strict-origin-when-cross-origin');
 header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; media-src 'self' https:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
 if($private){header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');}
}
function https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (int)($_SERVER['SERVER_PORT']??0)===443; }
function storage(): string {
 $dir=config()['storage_dir'];
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('Cannot create the private data directory. Check the folder permissions in Plesk.');
 $real=realpath($dir);$web=realpath(APP_ROOT);
 if(!$real||!$web||$real===$web||str_starts_with($real,$web.DIRECTORY_SEPARATOR))throw new RuntimeException('The data directory must be outside httpdocs.');
 return $real;
}
function db(): PDO {
 static $pdo;if($pdo instanceof PDO)return $pdo;
 if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('Enable the pdo_sqlite PHP extension in Plesk.');
 $path=storage().'/site.sqlite';$pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec('PRAGMA busy_timeout=5000');$pdo->exec('PRAGMA foreign_keys=ON');
 $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, session_version INTEGER NOT NULL DEFAULT 1)');
 $pdo->exec('CREATE TABLE IF NOT EXISTS content (id INTEGER PRIMARY KEY CHECK(id=1), body TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL)');
 $pdo->exec('CREATE TABLE IF NOT EXISTS attempts (scope TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
 $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attempt_scope_time ON attempts(scope, attempted_at)');
 $pdo->exec('CREATE TABLE IF NOT EXISTS instructions (id TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, summary TEXT NOT NULL, notes TEXT NOT NULL, video_source TEXT NOT NULL, password_hash TEXT NOT NULL, published INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL)');
 @chmod($path,0600);return $pdo;
}
function installed(): bool { return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0; }
function content(bool $fallback=false): array {
 try { if($fallback&&!is_file(config()['storage_dir'].'/site.sqlite'))return ['content'=>defaults(),'version'=>0];$row=db()->query('SELECT body, version FROM content WHERE id=1')->fetch();
 return $row?['content'=>json_decode($row['body'],true,512,JSON_THROW_ON_ERROR),'version'=>(int)$row['version']]:['content'=>defaults(),'version'=>0];
 }catch(Throwable $e){error_log('Sleightly Tricky: '.$e->getMessage());if($fallback)return ['content'=>defaults(),'version'=>0];throw $e;}
}
function session_boot(): void {
 security_headers(true);
 if(config()['require_https']&&!https()){http_response_code(400);echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>HTTPS required</title><body><h1>Please open this page using HTTPS.</h1><p>Enable your SSL certificate and HTTPS redirect in Plesk, then return to this page using https://.</p></body></html>';exit;}
 if(session_status()===PHP_SESSION_ACTIVE)return;
 $dir=storage().'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.gc_maxlifetime','43200');session_save_path($dir);session_name('ST_STALLHOLDER');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>https(),'httponly'=>true,'samesite'=>'Lax']);session_start();
 if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function csrf(): string { return $_SESSION['csrf']; }
function csrf_check(): void {
 $token=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';
 if(!is_string($token)||!hash_equals(csrf(),$token))throw new DomainException('Your session changed. Reload the editor, then try again.',403);
}
function user(): ?array {
 if(empty($_SESSION['uid']))return null;
 if(time()-(int)($_SESSION['active']??0)>1800||time()-(int)($_SESSION['started']??0)>43200){unset($_SESSION['uid']);return null;}
 $q=db()->prepare('SELECT id, email, role, session_version FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);$u=$q->fetch();
 if(!$u||(int)$u['session_version']!==(int)($_SESSION['sv']??0)){unset($_SESSION['uid']);return null;}
 $_SESSION['active']=time();return $u;
}
function login(array $u): void { session_regenerate_id(true);$_SESSION=['uid'=>(int)$u['id'],'sv'=>(int)$u['session_version'],'active'=>time(),'started'=>time(),'csrf'=>bin2hex(random_bytes(32))]; }
function require_user(bool $api=false): array {
 $u=user();if($u)return $u;
 if($api)throw new DomainException('Please sign in again. Your unsaved changes are still in this page.',401);
 header('Location: /admin/login.php',true,303);exit;
}
function require_owner(array $u): void { if($u['role']!=='owner')throw new DomainException('Only the owner can manage editor accounts.',403); }
function setup_key(): string {
 if(isset(config()['setup_key']))return config()['setup_key']; // Preserve existing installations.
 $path=storage().'/setup-key.txt';
 if(!is_file($path)){
  $key=bin2hex(random_bytes(24));$file=@fopen($path,'x');
  if($file){chmod($path,0600);fwrite($file,$key."\n");fclose($file);}
 }
 $key=trim((string)@file_get_contents($path));
 if(strlen($key)<32)throw new RuntimeException('Cannot read the private setup code. Check private directory permissions.');
 return $key;
}
function rate_scope(string $scope): string { return hash_hmac('sha256',$scope,setup_key()); }
function rate_check(string $scope,int $limit=10): void {
 $q=db()->prepare('SELECT COUNT(*) FROM attempts WHERE scope=? AND attempted_at>?');$q->execute([rate_scope($scope),time()-900]);
 if((int)$q->fetchColumn()>=$limit)throw new DomainException('Too many unsuccessful attempts. Please wait 15 minutes and try again.',429);
}
function failed_attempt(string $scope): void { $q=db()->prepare('INSERT INTO attempts(scope,attempted_at) VALUES(?,?)');$q->execute([rate_scope($scope),time()]);db()->prepare('DELETE FROM attempts WHERE attempted_at<?')->execute([time()-86400]); }
function validate_password(string $password): void {if(strlen($password)<12||strlen($password)>72)throw new DomainException('Use a password between 12 and 72 characters.');}
function valid_email(mixed $email): string {if(!is_string($email)||strlen($email)>200||!filter_var(trim($email),FILTER_VALIDATE_EMAIL))throw new DomainException('Please enter a valid email address.');return strtolower(trim($email));}
function image_path(mixed $path,bool $empty=true): string {
 if(!is_string($path)||strlen($path)>250||(!$empty&&!$path)||($path&&!preg_match('~^(/images/[a-zA-Z0-9_.-]+|/media\.php\?image=[a-f0-9]{32}\.(jpg|png|webp))$~D',$path)))throw new DomainException('Please select a valid uploaded image.');return $path;
}
function validate_content(mixed $data): array {
 if(!is_array($data))throw new DomainException('Invalid website content.');$clean=[];
 $limits=['eyebrow'=>160,'heroTitle'=>160,'heroAccent'=>160,'heroDescription'=>700,'aboutTitle'=>160,'aboutText'=>1200,'aboutSecond'=>800,'magicTitle'=>160,'magicIntro'=>500,'cardsTitle'=>160,'cardsIntro'=>500,'visitTitle'=>160,'location'=>160,'visitText'=>700,'tradingDays'=>300,'email'=>200,'notice'=>400];
 foreach($limits as $key=>$max){$value=$data[$key]??null;if(!is_string($value)||strlen($value)>$max*4||(!in_array($key,['tradingDays','email','notice'],true)&&!trim($value)))throw new DomainException('Please check the '.$key.' field.');$clean[$key]=trim($value);}
 if($clean['email'])$clean['email']=valid_email($clean['email']);$clean['heroImage']=image_path($data['heroImage']??null,false);$clean['bannerImage']=image_path($data['bannerImage']??null,false);
 if(!isset($data['products'])||!is_array($data['products'])||count($data['products'])>100)throw new DomainException('Use no more than 100 products.');$ids=[];$clean['products']=[];
 foreach($data['products'] as $p){if(!is_array($p))throw new DomainException('Invalid product.');$item=[];foreach(['id'=>80,'name'=>100,'description'=>700,'label'=>60] as $k=>$max){$v=$p[$k]??null;if(!is_string($v)||strlen($v)>$max*4||($k!=='label'&&!trim($v)))throw new DomainException('Please check each product name and description.');$item[$k]=trim($v);}
 if(isset($ids[$item['id']]))throw new DomainException('Duplicate product.');$ids[$item['id']]=true;
 if(!in_array($p['category']??null,['magic','cards'],true)||!is_bool($p['visible']??null)||!is_numeric($p['price']??null)||!is_finite((float)$p['price'])||$p['price']<0||$p['price']>10000)throw new DomainException('Please check the product price and collection.');
 $item['category']=$p['category'];$item['visible']=$p['visible'];$item['price']=round((float)$p['price'],2);$item['image']=image_path($p['image']??'');$clean['products'][]=$item;}
 return $clean;
}
function json_response(array $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo safe_json($data);exit;}
function upload_image(): string {
 $f=$_FILES['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']<1||$f['size']>5000000||!is_uploaded_file($f['tmp_name']))throw new DomainException('Please choose a JPG, PNG or WebP image under 5 MB.');
 $info=@getimagesize($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 if(!$info||!isset($types[$info['mime']])||$info[0]>16000||$info[1]>16000||$info[0]*$info[1]>60000000)throw new DomainException('Please choose a valid JPG, PNG or WebP image with smaller dimensions.');
 $dir=storage().'/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$key=bin2hex(random_bytes(16)).'.'.$types[$info['mime']];
 if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$key))throw new RuntimeException('Could not save the image.');chmod($dir.'/'.$key,0600);return '/media.php?image='.$key;
}
function auth_page(string $title,string $intro,string $body,?string $error=null): void {
 echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.h($title).' | Sleightly Tricky</title><link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/site.css"></head><body><main class="login-shell"><a class="wordmark" href="/">SLEIGHTLY<br>TRICKY<span class="logo-diamond">♦</span></a><div class="login-panel"><p class="eyebrow">BEHIND THE SCENES</p><h1>'.h($title).'</h1><p>'.h($intro).'</p>';
 if($error)echo '<div class="editor-error" role="alert">'.h($error).'</div>';echo $body.'<a class="text-link" href="/">Back to the website</a></div></main></body></html>';
}
require_once __DIR__.'/instructions.php';
