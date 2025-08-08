<?php
class AuthController{
    function login(){
        $input=json_decode(file_get_contents('php://input'),true);
        $email=$input['email']??''; $pass=$input['password']??'';
        $db=ai_db();
        $stmt=$db->prepare('SELECT * FROM users WHERE email=?');
        $stmt->execute([$email]);
        $u=$stmt->fetch();
        if(!$u || !password_verify($pass,$u['password_hash'])) json_response(['ok'=>false,'error'=>'invalid']);
        $token=bin2hex(random_bytes(16));
        $stmt=$db->prepare('INSERT INTO sessions(session_token,user_id,ip,user_agent,created_at,expires_at) VALUES(?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))');
        $stmt->execute([$token,$u['id'],$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
        setcookie('AISESSION',$token, time()+86400,'/ai/', '', false, true);
        json_response(['ok'=>true]);
    }
    function logout(){
        if(!empty($_COOKIE['AISESSION'])){
            $db=ai_db();
            $stmt=$db->prepare('DELETE FROM sessions WHERE session_token=?');
            $stmt->execute([$_COOKIE['AISESSION']]);
            setcookie('AISESSION','',time()-3600,'/ai/');
        }
        json_response(['ok'=>true]);
    }
    function me(){
        $u=get_user();
        json_response(['ok'=> (bool)$u, 'data'=>$u?['email'=>$u['email']]:null]);
    }
}
