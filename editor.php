<?php
declare(strict_types=1);
require __DIR__.'/auth-editor.php';
if (!is_file(STATE.'/password.hash')) {
 http_response_code(503);
 exit('Editor non configurato. Installare la password secondo README-aruba.md.');
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf']??''))) {http_response_code(403);exit;}
 if (isset($_POST['logout'])) {session_destroy();header('Location: /editor');exit;}
 $gate=fopen(STATE.'/login.lock','c+');
 if(!$gate||!flock($gate,LOCK_EX)){http_response_code(503);exit;}
 $retry=(int)stream_get_contents($gate);
 if (time() < max($retry,(int)($_SESSION['retry']??0))) {http_response_code(429);exit('Riprova tra qualche secondo.');}
 if (password_verify((string)($_POST['password']??''),trim(file_get_contents(STATE.'/password.hash')))) {
  session_regenerate_id(true);$_SESSION['auth']=true;$_SESSION['last']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /editor');exit;
 }
 $_SESSION['retry']=time()+5;ftruncate($gate,0);rewind($gate);fwrite($gate,(string)$_SESSION['retry']);fflush($gate);flock($gate,LOCK_UN);fclose($gate);
}
if (empty($_SESSION['auth'])) {
 header('X-Robots-Tag: noindex, nofollow');
 echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>In disparte · Accesso riservato</title><link rel="stylesheet" href="/editor.css"><main style="display:block;height:auto;max-width:420px;margin:10vh auto;padding:24px"><h1>Editor In disparte</h1><form method="post"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES).'"><label>Password <input type="password" name="password" required autocomplete="current-password"></label><button>Accedi</button></form></main></html>';exit;
}
header('X-Robots-Tag: noindex, nofollow');
$h=file_get_contents(__DIR__.'/editor.html');
$h=str_replace('<form action="/editor" method="post" id="logout-form" hidden></form>','<form action="/editor" method="post"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES).'"><button name="logout" value="1">Esci</button></form>',$h);
echo str_replace('</head>','<meta name="editor-csrf" content="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES).'"></head>',$h);
