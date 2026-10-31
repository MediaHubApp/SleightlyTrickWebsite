<?php
declare(strict_types=1);

const CONTACT_TOPICS=['General enquiry','Product question','Instruction help'];

/** Keep delivery settings outside httpdocs, alongside the existing private data. */
function contact_mail_config(): ?array {
 $path=storage().'/contact-mail.php';
 if(!is_file($path))return null;
 $settings=require $path;
 if(!is_array($settings))throw new RuntimeException('Invalid private contact mail configuration.');
 foreach(['to','from'] as $key){
  $address=$settings[$key]??null;
  // A single fixed mailbox, with no header or sendmail-option characters.
  if(!is_string($address)||strlen($address)>254||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._+\-]*@[a-zA-Z0-9.\-]+$/D',$address)||!filter_var($address,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid contact mail '.$key.' address.');
 }
 return ['to'=>$settings['to'],'from'=>$settings['from']];
}

function contact_values(array $input): array {
 $values=[];
 foreach(['name'=>400,'email'=>254,'topic'=>80,'message'=>20000] as $key=>$max){
  $value=$input[$key]??'';
  $values[$key]=is_string($value)&&strlen($value)<=$max?trim(str_replace(["\r\n","\r"],"\n",$value)):'';
 }
 return $values;
}

function contact_errors(array $values): array {
 $errors=[];
 if(!preg_match('/^.{1,100}$/usD',$values['name'])||preg_match('/[\x00-\x1f\x7f]/',$values['name']))$errors['name']='Enter your name on a single line, using up to 100 characters.';
 if($values['email']===''||preg_match('/[\x00-\x20\x7f]/',$values['email'])||!filter_var($values['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address so we can reply.';
 if(!in_array($values['topic'],CONTACT_TOPICS,true))$errors['topic']='Choose what your message is about.';
 if(!preg_match('/^.{1,5000}$/usD',$values['message'])||str_contains($values['message'],"\0"))$errors['message']='Enter a message of up to 5,000 characters.';
 return $errors;
}

/** Reserve a submission before calling mail, including concurrent requests. */
function contact_rate_limit(): void {
 $pdo=db();$scope=rate_scope('contact:'.($_SERVER['REMOTE_ADDR']??'unknown'));
 $pdo->exec('BEGIN IMMEDIATE');
 try{
  $q=$pdo->prepare('SELECT COUNT(*) FROM attempts WHERE scope=? AND attempted_at>?');$q->execute([$scope,time()-900]);
  if((int)$q->fetchColumn()>=5)throw new DomainException('You’ve sent several messages recently. Please wait 15 minutes before trying again.',429);
  $pdo->prepare('INSERT INTO attempts(scope,attempted_at) VALUES(?,?)')->execute([$scope,time()]);
  $pdo->prepare('DELETE FROM attempts WHERE attempted_at<?')->execute([time()-86400]);
  $pdo->exec('COMMIT');
 }catch(Throwable $e){$pdo->exec('ROLLBACK');throw $e;}
}

function contact_send(array $settings,array $values): bool {
 $body="Website contact enquiry\n\nName: ".$values['name']."\nEmail: ".$values['email']."\nTopic: ".$values['topic']."\n\nMessage:\n".$values['message']."\n";
 $headers=[
  'From'=>'Sleightly Tricky <'.$settings['from'].'>',
  'Reply-To'=>$values['email'],
  'MIME-Version'=>'1.0',
  'Content-Type'=>'text/plain; charset=UTF-8',
  'Content-Transfer-Encoding'=>'base64',
 ];
 // Plesk's local mail server handles delivery. Never use visitor input as sender,
 // recipient, or sendmail arguments, and never send automatic replies to visitors.
 return @mail($settings['to'],'[Sleightly Tricky] '.$values['topic'],chunk_split(base64_encode($body),76,"\r\n"),$headers,'-f'.escapeshellarg($settings['from']));
}
