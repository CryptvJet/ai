<?php
class AdminController{
    function canned_responses(){
        require_admin();
        $db=ai_db();
        $rows=$db->query('SELECT * FROM canned_responses ORDER BY id')->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
    }
    function canned_responses_post(){
        require_admin();
        $input=json_decode(file_get_contents('php://input'),true);
        $db=ai_db();
        $mode=$input['mode']??'create';
        if($mode==='delete'){
            $stmt=$db->prepare('DELETE FROM canned_responses WHERE id=?');
            $stmt->execute([$input['id']]);
        }elseif($mode==='update'){
            $stmt=$db->prepare('UPDATE canned_responses SET title=?,body=?,tags=? WHERE id=?');
            $stmt->execute([$input['title'],$input['body'],$input['tags'],$input['id']]);
        }else{
            $stmt=$db->prepare('INSERT INTO canned_responses(title,body,tags) VALUES(?,?,?)');
            $stmt->execute([$input['title'],$input['body'],$input['tags']]);
        }
        json_response(['ok'=>true]);
    }
    function conversations(){
        require_admin();
        $db=ai_db();
        $q='%'.($_GET['query']??'').'%';
        $stmt=$db->prepare('SELECT * FROM conversations WHERE meta LIKE ? ORDER BY id DESC LIMIT 100');
        $stmt->execute([$q]);
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    function messages(){
        require_admin();
        $cid=$_GET['conversation_id']??0;
        $db=ai_db();
        $stmt=$db->prepare('SELECT * FROM messages WHERE conversation_id=? ORDER BY id');
        $stmt->execute([$cid]);
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    function analytics_events(){
        require_admin();
        $db=ai_db();
        $range=$_GET['range']??'24h';
        $since="NOW() - INTERVAL 24 HOUR";
        if($range==='7d') $since="NOW() - INTERVAL 7 DAY"; elseif($range==='30d') $since="NOW() - INTERVAL 30 DAY"; elseif($range==='all') $since="'1970-01-01'";
        $rows=$db->query("SELECT * FROM analytics_events WHERE created_at >= $since ORDER BY id DESC LIMIT 100")->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
    }
}
