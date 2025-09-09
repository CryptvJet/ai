<?php
class PcoreBridgeController{
    function enqueue(){
        require_admin();
        $input=json_decode(file_get_contents('php://input'),true);
        $db=ai_db();
        $stmt=$db->prepare('INSERT INTO pcore_write_queue(action,payload,status,created_at) VALUES(?,? ,"queued",NOW())');
        $stmt->execute([$input['action']??'',json_encode($input['payload']??[]) ]);
        json_response(['ok'=>true]);
    }
    function queue(){
        require_admin();
        $db=ai_db();
        $stmt=$db->query('SELECT * FROM pcore_write_queue ORDER BY id DESC LIMIT 100');
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
}
