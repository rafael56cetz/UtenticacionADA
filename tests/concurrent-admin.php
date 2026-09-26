<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\{Config,Kernel,Security,HttpError};
date_default_timezone_set('UTC');
if(($argv[1]??'')==='child') {
    $dir=$argv[2]; $id=(int)$argv[3];
    $kernel=new Kernel(new Config($dir),bin2hex(random_bytes(16)));
    $kernel->security->start();
    $kernel->security->login($kernel->db->one('SELECT * FROM users WHERE id=?',[$id]),'recovery');
    touch($dir.'/ready-'.$id);
    $deadline=microtime(true)+10;
    while(!is_file($dir.'/go') && microtime(true)<$deadline) {clearstatcache();usleep(10000);}
    if(!is_file($dir.'/go')) exit(2);
    try {$kernel->admin->update($id,['name'=>'Synthetic concurrent account','role'=>'Gestor']);echo '200';}
    catch(HttpError $e){echo (string)$e->status;}
    session_destroy();exit;
}
$root=dirname(__DIR__); $name='acceso_test_'.bin2hex(random_bytes(6));$dir=$root.'/.tmp/'.$name;
$admin=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created=false;$children=[];$failed=false;
mkdir($dir,0700,true);
try {
    $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");$created=true;
    $admin->exec("USE `$name`");$admin->exec(file_get_contents($root.'/database/schema.sql'));
    $env="DB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=$name\nDB_USER=".(getenv('TEST_DB_USER')?:'root')."\nDB_PASSWORD=".(getenv('TEST_DB_PASSWORD')?:'')."\nAPP_ORIGIN=http://localhost:8088\nWEBAUTHN_RP_ID=localhost\nAPP_KEY=".base64_encode(random_bytes(32))."\nTEMPLATE_KEY=".base64_encode(random_bytes(32))."\n";
    file_put_contents($dir.'/.env',$env);
    $insert=$admin->prepare("INSERT INTO users(identifier,name,role,webauthn_handle) VALUES(?,?,'Administrator',?)");
    foreach([1,2] as $id) {
        $insert->execute(['concurrent.'.$id,'Synthetic concurrent account',random_bytes(32)]);
        $process=proc_open([PHP_BINARY,__FILE__,'child',$dir,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Cannot start worker');
        fclose($pipes[0]);$children[]=[$process,$pipes];
    }
    $deadline=microtime(true)+10;
    while((!is_file($dir.'/ready-1') || !is_file($dir.'/ready-2')) && microtime(true)<$deadline){clearstatcache();usleep(10000);}
    if(!is_file($dir.'/ready-1') || !is_file($dir.'/ready-2')) throw new RuntimeException('Workers did not reach barrier');
    touch($dir.'/go');$results=[];
    foreach($children as [$process,$pipes]) {
        $results[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        if($exit!==0 || $error!=='')throw new RuntimeException('Worker failed: '.$error);
    }
    $children=[];sort($results);
    if($results!==['200','409'])throw new RuntimeException('Unexpected concurrent outcomes: '.json_encode($results));
    echo "PASS Concurrent administrator changes: one accepted, one rejected (409)\n";
    if((int)$admin->query("SELECT COUNT(*) FROM users WHERE active=1 AND role='Administrator'")->fetchColumn()!==1)throw new RuntimeException('Last administrator lost');
    echo "PASS One active Administrator preserved after concurrent requests\n";
}catch(Throwable $e){$failed=true;echo 'FAIL '.$e->getMessage().PHP_EOL;}
finally {
    foreach($children as [$process,$pipes])if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
    if($created && preg_match('/^acceso_test_[0-9a-f]{12}$/D',$name))$admin->exec("DROP DATABASE `$name`");
    if(is_file($dir.'/.env'))unlink($dir.'/.env');
}
exit($failed?1:0);
