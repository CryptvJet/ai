<?php
class ChatController{
    function send(){
        $input=json_decode(file_get_contents('php://input'),true);
        $cid=$input['conversation_id']??null;
        $content=trim($input['content']??'');
        if(!$content) json_response(['ok'=>false,'error'=>'empty']);
        $db=ai_db();
        if(!$cid){
            $db->exec('INSERT INTO conversations(created_at,updated_at) VALUES(NOW(),NOW())');
            $cid=$db->lastInsertId();
        }
        $stmt=$db->prepare('INSERT INTO messages(conversation_id,role,content,tokens,created_at) VALUES(?,?,?,?,NOW())');
        $stmt->execute([$cid,'user',$content,strlen($content)]);
        // basic canned response
        $reply='Thanks for your message.';
        $res=$db->query('SELECT body FROM canned_responses ORDER BY id LIMIT 1');
        if($row=$res->fetch()) $reply=$row['body'];
        $stmt->execute([$cid,'assistant',$reply,strlen($reply)]);
        json_response(['ok'=>true,'data'=>['conversation_id'=>$cid,'reply'=>$reply]]);
    }
    function conversation(){
        $id=$_GET['id']??0; $db=ai_db();
        $stmt=$db->prepare('SELECT role,content,created_at FROM messages WHERE conversation_id=? ORDER BY id');
        $stmt->execute([$id]);
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    function list(){
        $limit=intval($_GET['limit']??50); $offset=intval($_GET['offset']??0);
        $db=ai_db();
        $stmt=$db->prepare('SELECT id,meta FROM conversations ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$limit,PDO::PARAM_INT);
        $stmt->bindValue(2,$offset,PDO::PARAM_INT);
        $stmt->execute();
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
}
