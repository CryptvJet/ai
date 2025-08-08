<?php
class LiveFeedController{
    function feed(){
        $since=$_GET['since']??date('c',time()-3600);
        $pc=pcore_db();
        $stmt=$pc->prepare("SELECT id,created_at,finished_at FROM novas WHERE created_at>=? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$since]);
        $novas=$stmt->fetchAll();
        $stmt=$pc->prepare("SELECT id,started_at,finished_at FROM climax_groups WHERE started_at>=? ORDER BY started_at DESC LIMIT 100");
        $stmt->execute([$since]);
        $climax=$stmt->fetchAll();
        json_response(['ok'=>true,'data'=>['novas'=>$novas,'climax'=>$climax]]);
    }
}
