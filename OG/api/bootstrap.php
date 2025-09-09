<?php
require __DIR__.'/db_config.php';

function ai_db(){
    static $pdo; if($pdo) return $pdo;
    $pdo=new PDO('mysql:host='.AI_DB_HOST.';dbname='.AI_DB_NAME.';charset=utf8mb4',AI_DB_USER,AI_DB_PASS,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

function pcore_db(){
    static $pdo; if($pdo) return $pdo;
    $pdo=new PDO('mysql:host='.PCORE_DB_HOST.';dbname='.PCORE_DB_NAME.';charset=utf8mb4',PCORE_DB_USER,PCORE_DB_PASS,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

function json_response($arr){
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

function get_user(){
    if(empty($_COOKIE['AISESSION'])) return null;
    $token=$_COOKIE['AISESSION'];
    $db=ai_db();
    $stmt=$db->prepare('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.session_token=? AND s.expires_at>NOW()');
    $stmt->execute([$token]);
    return $stmt->fetch();
}

function require_admin(){
    $u=get_user();
    if(!$u) json_response(['ok'=>false,'error'=>'auth']);
    return $u;
}

function audit($endpoint,$params){
    try{
        $db=ai_db();
        $stmt=$db->prepare('INSERT INTO api_audit(endpoint,params,ip,ua,created_at) VALUES(?,?,?,?,NOW())');
        $stmt->execute([$endpoint,json_encode($params),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
    }catch(Exception $e){/* ignore */}
}
