<?php
require __DIR__.'/api/bootstrap.php';

$db=ai_db();
$pc=pcore_db();

$stmt=$db->query("SELECT * FROM pcore_write_queue WHERE status='queued' ORDER BY id LIMIT 20");
foreach($stmt->fetchAll() as $row){
    $db->prepare("UPDATE pcore_write_queue SET status='processing', processed_at=NOW() WHERE id=?")
       ->execute([$row['id']]);
    $payload=json_decode($row['payload'],true);
    try{
        switch($row['action']){
            case 'attach_note':
                $call=$pc->prepare('CALL pcore_bridge.sp_attach_note_to_nova(?,?)');
                $call->execute([$payload['nova_id']??0,$payload['note']??'']);
                break;
            case 'flag_nova':
                $call=$pc->prepare('CALL pcore_bridge.sp_flag_nova(?,?)');
                $call->execute([$payload['nova_id']??0,$payload['flag']??'']);
                break;
            case 'upsert_analysis':
                $call=$pc->prepare('CALL pcore_bridge.sp_upsert_analysis(?,?)');
                $call->execute([$payload['climax_id']??0,$payload['analysis']??'']);
                break;
            default:
                throw new Exception('unknown action');
        }
        $db->prepare("UPDATE pcore_write_queue SET status='done', processed_at=NOW() WHERE id=?")
           ->execute([$row['id']]);
    }catch(Exception $e){
        $db->prepare("UPDATE pcore_write_queue SET status='error', processed_at=NOW(), error=? WHERE id=?")
           ->execute([$e->getMessage(),$row['id']]);
    }
}
