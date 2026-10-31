<?php
declare(strict_types=1);

const CONTACT_TOPICS=['General enquiry','Product question','Instruction help'];

function contact_default_fields(): array {
 return [
  ['id'=>'name','label'=>'Your name','type'=>'text','required'=>true,'options'=>[]],
  ['id'=>'email','label'=>'Email address','type'=>'email','required'=>true,'options'=>[]],
  ['id'=>'topic','label'=>'What’s it about?','type'=>'select','required'=>true,'options'=>CONTACT_TOPICS],
  ['id'=>'message','label'=>'Your message','type'=>'textarea','required'=>true,'options'=>[]],
 ];
}
function contact_form_definition(): array {
 $pdo=db();
 $pdo->exec('CREATE TABLE IF NOT EXISTS contact_form (id INTEGER PRIMARY KEY CHECK(id=1), body TEXT NOT NULL, version INTEGER NOT NULL, updated_at TEXT NOT NULL)');
 $row=$pdo->query('SELECT body,version FROM contact_form WHERE id=1')->fetch();
 return $row?['fields'=>json_decode($row['body'],true,512,JSON_THROW_ON_ERROR),'version'=>(int)$row['version']]:['fields'=>contact_default_fields(),'version'=>0];
}
function contact_single_line(mixed $value,int $max): bool {
 return is_string($value)&&strlen($value)<=$max*4&&preg_match('/^.{1,'.$max.'}$/usD',$value)&&!preg_match('/[\x00-\x1f\x7f]/',$value);
}
function save_contact_form(array $data): array {
 $fields=$data['fields']??null;$version=$data['version']??null;
 if(!is_array($fields)||!array_is_list($fields)||count($fields)<1||count($fields)>15||!is_int($version)||$version<0)throw new DomainException('Use between 1 and 15 fields and a valid form version.');
 $clean=[];$ids=[];
 foreach($fields as $field){
  if(!is_array($field))throw new DomainException('Invalid contact field.');
  $id=$field['id']??null;$label=$field['label']??null;$type=$field['type']??null;$required=$field['required']??null;
  if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$id)||$id==='website'||isset($ids[$id]))throw new DomainException('Contact fields need unique valid identifiers.');
  if(!contact_single_line($label,80)||!trim($label))throw new DomainException('Enter a field label of up to 80 characters on one line.');
  if(!in_array($type,['text','email','tel','textarea','select','checkbox'],true)||!is_bool($required))throw new DomainException('Choose a supported field type and required setting.');
  $options=[];
  if($type==='select'){
   $options=$field['options']??null;
   if(!is_array($options)||!array_is_list($options)||count($options)<1||count($options)>30)throw new DomainException('Each dropdown needs between 1 and 30 options.');
   foreach($options as &$option){if(!contact_single_line($option,80)||!trim($option))throw new DomainException('Dropdown options must be single lines of up to 80 characters.');$option=trim($option);}unset($option);
   if(count(array_unique($options))!==count($options))throw new DomainException('Remove duplicate dropdown options.');
  }
  $clean[]=['id'=>$id,'label'=>trim($label),'type'=>$type,'required'=>$required,'options'=>$options];$ids[$id]=true;
 }
 $reply=array_values(array_filter($clean,fn($field)=>$field['id']==='email'));
 if(!$reply||$reply[0]['type']!=='email'||!$reply[0]['required'])throw new DomainException('Keep the Email address field as a required email field so you can reply.');
 contact_form_definition();$pdo=db();$pdo->exec('BEGIN IMMEDIATE');
 try{
  $current=$pdo->query('SELECT version FROM contact_form WHERE id=1')->fetchColumn();
  if(($current===false?0:(int)$current)!==$version)throw new DomainException('Someone else changed the contact form. Reload before saving.',409);
  $pdo->prepare('INSERT INTO contact_form(id,body,version,updated_at) VALUES(1,?,?,?) ON CONFLICT(id) DO UPDATE SET body=excluded.body,version=excluded.version,updated_at=excluded.updated_at')->execute([safe_json($clean),$version+1,gmdate('c')]);
  $pdo->exec('COMMIT');
 }catch(Throwable $e){$pdo->exec('ROLLBACK');throw $e;}
 return ['fields'=>$clean,'version'=>$version+1];
}

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

function contact_values(array $input,?array $fields=null): array {
 $values=[];
 foreach($fields??contact_default_fields() as $field){
  $max=match($field['type']){'textarea'=>20000,'email'=>254,'select'=>320,'tel'=>160,default=>400};
  $value=$input[$field['id']]??'';
  $values[$field['id']]=is_string($value)&&strlen($value)<=$max?trim(str_replace(["\r\n","\r"],"\n",$value)):null;
 }
 return $values;
}

function contact_errors(array $values,?array $fields=null): array {
 $errors=[];
 foreach($fields??contact_default_fields() as $field){
  $value=$values[$field['id']]??null;
  if($value===''&&!$field['required'])continue;
  $valid=is_string($value)&&match($field['type']){
   'email'=>$value!==''&&!preg_match('/[\x00-\x20\x7f]/',$value)&&filter_var($value,FILTER_VALIDATE_EMAIL)!==false,
   'textarea'=>preg_match('/^.{1,5000}$/usD',$value)&&!str_contains($value,"\0"),
   'select'=>in_array($value,$field['options'],true),
   'checkbox'=>$value==='yes',
   'tel'=>contact_single_line($value,40),
   default=>contact_single_line($value,100),
  };
  if(!$valid)$errors[$field['id']]=match($field['type']){
   'email'=>'Enter a valid email address for '.$field['label'].'.',
   'select'=>'Choose an option for '.$field['label'].'.',
   'checkbox'=>'Please tick '.$field['label'].'.',
   'textarea'=>'Enter up to 5,000 characters for '.$field['label'].'.',
   default=>'Enter '.$field['label'].' on one line (up to '.($field['type']==='tel'?40:100).' characters).',
  };
 }
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

function contact_send(array $settings,array $values,?array $fields=null): bool {
 $fields??=contact_default_fields();$body="Website contact enquiry\n\n";$topic='Website enquiry';
 foreach($fields as $field){
  $value=$values[$field['id']]??'';
  if($field['id']==='topic'&&$field['type']==='select'&&in_array($value,$field['options'],true))$topic=$value;
  $body.=$field['label'].":\n".($field['type']==='checkbox'?($value==='yes'?'Yes':'No'):($value===''?'(Not supplied)':$value))."\n\n";
 }
 $subject='[Sleightly Tricky] '.$topic;
 if(preg_match('/[^\x20-\x7e]/',$subject))$subject='[Sleightly Tricky] Website enquiry';
 $headers=[
  'From'=>'Sleightly Tricky <'.$settings['from'].'>',
  'Reply-To'=>$values['email'],
  'MIME-Version'=>'1.0',
  'Content-Type'=>'text/plain; charset=UTF-8',
  'Content-Transfer-Encoding'=>'base64',
 ];
 // Plesk's local mail server handles delivery. Never use visitor input as sender,
 // recipient, or sendmail arguments, and never send automatic replies to visitors.
 return @mail($settings['to'],$subject,chunk_split(base64_encode($body),76,"\r\n"),$headers,'-f'.escapeshellarg($settings['from']));
}
