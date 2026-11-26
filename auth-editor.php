<?php
declare(strict_types=1);
define('STATE',__DIR__.'/privato');
if (!is_dir(STATE)) mkdir(STATE,0700,true);
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
header('Cache-Control: no-store');
$_SESSION['csrf']??=bin2hex(random_bytes(32));
function require_editor():void {
 if (empty($_SESSION['auth'])) {http_response_code(401);exit;}
 if ($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_EDITOR_CSRF']??'')){http_response_code(403);exit;}
}
