<?php
require __DIR__.'/bootstrap.php';

$path=trim($_SERVER['PATH_INFO'] ?? '', '/');
$parts=explode('/', $path);
$controller=ucfirst($parts[0] ?? '');
$action=str_replace('-','_',$parts[1] ?? '');
$endpoint=$controller.'/'.$action;
audit($endpoint, $_REQUEST);

if(!$controller || !$action) json_response(['ok'=>false,'error'=>'not_found']);
$file=__DIR__.'/'.$controller.'Controller.php';
if(!file_exists($file)) json_response(['ok'=>false,'error'=>'not_found']);
require $file;
$class=$controller.'Controller';
$inst=new $class();
$method=$action;
if($_SERVER['REQUEST_METHOD']==='POST' && method_exists($inst,$action.'_post')) $method=$action.'_post';
if(!method_exists($inst,$method)) json_response(['ok'=>false,'error'=>'not_found']);
$inst->$method();
