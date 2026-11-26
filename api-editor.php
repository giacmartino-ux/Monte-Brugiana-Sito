<?php
declare(strict_types=1);
require __DIR__.'/auth-editor.php';
require_editor();
header('Content-Type: application/json; charset=utf-8');
function fail(string $message,int $code=400):void {http_response_code($code);echo json_encode(['error'=>$message]);exit;}
function pages():array {
 $out=[];
 foreach(glob(__DIR__.'/*.html') as $p){$n=basename($p);if($n!=='editor.html'&&preg_match('/^[a-z0-9-]+\.html$/D',$n))$out[$n]=file_get_contents($p);}
 ksort($out);return $out;
}
function version(array $p):string {ksort($p);return hash('sha256',serialize($p));}
function valid_page(string $p):bool{return preg_match('/^[a-z0-9-]{1,100}\.html$/D',$p)===1&&$p!=='editor.html';}
function valid_asset(string $p):bool{return preg_match('#^/assets/editor-[a-z0-9-]{1,90}\.(jpg|png|webp)$#D',$p)===1;}
function write_atomic(string $p,string $s):void {
 $tmp=tempnam(dirname($p),'.edit-');if($tmp===false)throw new RuntimeException('File temporaneo non disponibile');
 if(file_put_contents($tmp,$s,LOCK_EX)!==strlen($s)||!chmod($tmp,str_starts_with($p,STATE.'/')?0600:0644)||!rename($tmp,$p)){@unlink($tmp);throw new RuntimeException('Scrittura fallita');}
}
function validate_html(string $html):void {
 if(!class_exists('DOMDocument'))throw new RuntimeException('Estensione PHP DOM non disponibile');
 if(preg_match('/<\?(?:php|=)/i',$html)||strlen($html)>500000||!str_contains(strtolower($html),'<html')||!str_contains(strtolower($html),'</html>'))throw new RuntimeException('Pagina non valida');
 $d=new DOMDocument();$prev=libxml_use_internal_errors(true);$d->loadHTML($html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($prev);
 foreach($d->getElementsByTagName('*') as $el){
  $tag=strtolower($el->tagName);
  if(in_array($tag,['iframe','object','embed','form','base','svg','math'],true))throw new RuntimeException('Elemento non consentito: '.$tag);
  if($tag==='script'&&($el->getAttribute('src')!=='/guida.js'||trim($el->textContent)!==''))throw new RuntimeException('Script non consentito');
  if($tag==='meta'&&strtolower($el->getAttribute('http-equiv'))==='refresh')throw new RuntimeException('Reindirizzamento non consentito');
  foreach($el->attributes as $a){$n=strtolower($a->name);$v=trim($a->value);
   if(str_starts_with($n,'on')||$n==='srcdoc'||preg_match('/^(javascript|vbscript):/i',$v))throw new RuntimeException('Attributo non consentito');
   if($n==='src'&&$tag==='img'&&!preg_match('#^/assets/[a-z0-9-]+\.(jpg|png|webp)$#D',$v))throw new RuntimeException('Percorso foto non valido');
  }
 }
}
$lock=fopen(STATE.'/publish.lock','c');if(!$lock||!flock($lock,LOCK_EX))fail('Impossibile bloccare la pubblicazione',503);
$old=pages();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $backups=array_map('basename',glob(STATE.'/backup-*.json'));rsort($backups);
 $archive=array_map(fn($p)=>'/assets/'.basename($p),array_merge(glob(__DIR__.'/assets/*.jpg'),glob(__DIR__.'/assets/*.png'),glob(__DIR__.'/assets/*.webp')));
 echo json_encode(['pages'=>$old,'head'=>version($old),'archive'=>$archive,'backups'=>$backups]);exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST')fail('Metodo non consentito',405);
if((int)($_SERVER['CONTENT_LENGTH']??0)>25000000)fail('Caricamento troppo grande',413);
try{
 $input=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
 if(($input['head']??'')!==version($old))fail('Il sito è cambiato: esporta la bozza prima di ricaricare.',409);
 $action=$input['action']??'publish';
 if($action==='restore'){
  $name=$input['backup']??'';
  if(!preg_match('/^backup-[0-9a-z-]+\.json$/D',$name)||!is_file(STATE.'/'.$name))fail('Backup non valido');
  $next=json_decode(file_get_contents(STATE.'/'.$name),true,512,JSON_THROW_ON_ERROR)['pages'];$assets=[];
 }else{
  if($action!=='publish')fail('Azione non valida');
  $next=$input['pages']??null;$assets=$input['assets']??[];
 }
 if(!is_array($next)||count($next)>100||!is_array($assets))fail('Dati non validi');
 foreach(['index.html','la-fatica.html','la-comunita.html','don-marco.html','il-percorso.html'] as $p)if(!isset($next[$p]))fail('Pagina essenziale mancante: '.$p);
 foreach($next as $p=>$html){if(!valid_page((string)$p)||!is_string($html))fail('Nome pagina non valido');validate_html($html);}
 $decoded=[];
 foreach($assets as $p=>$data){
  if(!valid_asset((string)$p)||!is_string($data)||!preg_match('#^data:image/(jpeg|png|webp);base64,#',$data))fail('Foto non valida');
  $b=base64_decode(substr($data,strpos($data,',')+1),true);if($b===false||strlen($b)>5000000)fail('Foto troppo grande');
  $info=@getimagesizefromstring($b);$expected=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][pathinfo($p,PATHINFO_EXTENSION)];
  if(!$info||$info['mime']!==$expected||$info[0]*$info[1]>30000000)fail('Formato o dimensioni foto non validi');
  $dest=__DIR__.$p;if(is_file($dest)&&hash_file('sha256',$dest)!==hash('sha256',$b))fail('Nome fotografia già in uso');
  $decoded[$p]=$b;
 }
 $docs=[];
 foreach($next as $p=>$html){
  $d=new DOMDocument();$prev=libxml_use_internal_errors(true);$d->loadHTML($html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($prev);$docs[$p]=$d;
 }
 foreach($docs as $p=>$d){
  $photos=[];
  foreach($d->getElementsByTagName('img') as $img)$photos[]=$img->getAttribute('src');
  foreach($d->getElementsByTagName('*') as $el)if($el->hasAttribute('style')){
   preg_match_all("~url\\(\\s*['\"]?([^'\"\\s)]+)~i",$el->getAttribute('style'),$matches);
   foreach($matches[1] as $src)$photos[]=$src;
  }
  foreach($photos as $v){
   if(!preg_match('#^/assets/[a-z0-9-]+\.(jpg|png|webp)$#D',$v)||(!isset($decoded[$v])&&!is_file(__DIR__.$v)))fail('Foto non valida o mancante in '.$p);
  }
  foreach($d->getElementsByTagName('a') as $a){
   $v=$a->getAttribute('href');
   if($v===''||preg_match('/[\x00-\x20]/',$v)||!preg_match('#^(?:https?://[^/]+|mailto:|tel:|/(?!/)|\#)#i',$v))fail('Collegamento non valido in '.$p);
   $parts=parse_url($v);if($parts===false)fail('Indirizzo non valido in '.$p);
   if(isset($parts['scheme'])&&!in_array(strtolower($parts['host']??''),['indisparte.it','www.indisparte.it'],true))continue;
   $t=$parts['path']??'';$t=$t===''?$p:($t==='/'?'index.html':ltrim($t,'/'));
   if(!isset($docs[$t]))fail('Collegamento interno mancante in '.$p.': '.$v);
   if(!empty($parts['fragment'])&&!$docs[$t]->getElementById(rawurldecode($parts['fragment'])))fail('Ancora mancante in '.$p.': '.$v);
  }
 }
 $backup='backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
 write_atomic(STATE.'/'.$backup,json_encode(['pages'=>$old],JSON_THROW_ON_ERROR));
 $sitemap='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
 foreach($next as $p=>$html)$sitemap.='<url><loc>https://indisparte.it/'.($p==='index.html'?'':$p).'</loc></url>';
 $sitemap.='</urlset>';
 $oldmap=file_get_contents(__DIR__.'/sitemap.xml');$created=[];
 try{
  foreach($decoded as $p=>$b){if(!is_file(__DIR__.$p)){write_atomic(__DIR__.$p,$b);$created[]=__DIR__.$p;}}
  foreach($next as $p=>$html){write_atomic(__DIR__.'/'.$p,$html);if(!isset($old[$p]))$created[]=__DIR__.'/'.$p;}
  foreach(array_diff_key($old,$next) as $p=>$html)if(!unlink(__DIR__.'/'.$p))throw new RuntimeException('Rimozione pagina fallita');
  write_atomic(__DIR__.'/sitemap.xml',$sitemap);
 }catch(Throwable $e){
  foreach($created as $p)@unlink($p);
  foreach($old as $p=>$html)write_atomic(__DIR__.'/'.$p,$html);
  write_atomic(__DIR__.'/sitemap.xml',$oldmap);throw $e;
 }
 echo json_encode(['ok'=>true,'head'=>version(pages()),'backup'=>$backup]);
}catch(Throwable $e){error_log('Editor: '.$e->getMessage());fail('Operazione non completata. Controllare i dati o il registro errori PHP.');}
