<?php
class StatsController{
    private $tables=[
        'novas'=>'novas',
        'climax_groups'=>'climax_groups'
    ];
    private function rangeCond($range){
        switch($range){
            case '24h': return 'NOW() - INTERVAL 24 HOUR';
            case '7d': return 'NOW() - INTERVAL 7 DAY';
            case '30d': return 'NOW() - INTERVAL 30 DAY';
            default: return 'NOW() - INTERVAL 10 YEAR';
        }
    }
    function overview(){
        $range=$_GET['range']??'24h';
        $since=$this->rangeCond($range);
        $pc=pcore_db();
        $novas=$pc->query("SELECT COUNT(*) c FROM {$this->tables['novas']} WHERE created_at >= $since")->fetch()['c'];
        $finished=$pc->query("SELECT COUNT(*) c FROM {$this->tables['novas']} WHERE finished_at IS NOT NULL AND created_at >= $since")->fetch()['c'];
        $climax=$pc->query("SELECT COUNT(*) c FROM {$this->tables['climax_groups']} WHERE started_at >= $since")->fetch()['c'];
        $avg=$pc->query("SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) a FROM {$this->tables['novas']} WHERE finished_at IS NOT NULL AND created_at >= $since")->fetch()['a'];
        json_response(['ok'=>true,'data'=>[
            'novas'=>(int)$novas,
            'finished_novas'=>(int)$finished,
            'climax_groups'=>(int)$climax,
            'avg_time_to_climax_sec'=>(int)$avg
        ]]);
    }
    function timeseries(){
        $range=$_GET['range']??'7d';
        $metric=$_GET['metric']??'novas_per_hour';
        $since=$this->rangeCond($range);
        $pc=pcore_db();
        if($metric==='novas_per_day'){
            $q="SELECT DATE(created_at) d, COUNT(*) c FROM {$this->tables['novas']} WHERE created_at >= $since GROUP BY d ORDER BY d";
        }else{
            $q="SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') d, COUNT(*) c FROM {$this->tables['novas']} WHERE created_at >= $since GROUP BY d ORDER BY d";
        }
        $rows=$pc->query($q)->fetchAll();
        $data=[]; foreach($rows as $r){ $data[]=['time'=>$r['d'],'value'=>(int)$r['c']]; }
        json_response(['ok'=>true,'data'=>$data]);
    }
    function climax_summary(){
        $range=$_GET['range']??'24h';
        $since=$this->rangeCond($range);
        $pc=pcore_db();
        $rows=$pc->query("SELECT id,started_at,finished_at,event_count FROM {$this->tables['climax_groups']} WHERE started_at >= $since ORDER BY started_at DESC LIMIT 50")->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
    }
    function fingerprint(){
        $input=json_decode(file_get_contents('php://input'),true);
        $db=ai_db();
        $stmt=$db->prepare('INSERT INTO browser_fingerprints(conversation_id,ip,user_agent,tz,lang,viewport_w,viewport_h,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            $input['conversation_id']??null,
            $_SERVER['REMOTE_ADDR']??'',
            $_SERVER['HTTP_USER_AGENT']??'',
            $input['tz']??'',
            $input['lang']??'',
            $input['viewport_w']??0,
            $input['viewport_h']??0
        ]);
        json_response(['ok'=>true]);
    }
    function event(){
        $input=json_decode(file_get_contents('php://input'),true);
        $db=ai_db();
        $stmt=$db->prepare('INSERT INTO analytics_events(type,created_at,meta) VALUES(?,NOW(),?)');
        $stmt->execute([$input['type']??'',json_encode($input['meta']??[]) ]);
        json_response(['ok'=>true]);
    }
}
