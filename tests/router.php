<?php
// Router per il test PHP locale: non va installato su Aruba.
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($p==='/editor'){require __DIR__.'/../editor.php';return true;}
if(str_starts_with($p,'/privato/')||in_array($p,['/editor.html','/auth-editor.php'],true)){http_response_code(403);return true;}
return false;
