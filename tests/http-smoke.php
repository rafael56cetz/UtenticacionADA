<?php
declare(strict_types=1);
// Explicit opt-in: creates and removes only uniquely named synthetic accounts in local app DB.
require dirname(__DIR__).'/vendor/autoload.php';
use App\{Config,Database,Security};
if(!in_array('--local-fixtures',$argv,true)) exit("Use --local-fixtures to test the local WAMP site with temporary synthetic accounts.\n");
$config=new Config(dirname(__DIR__)); $db=new Database($config); $origin=$config->get('APP_ORIGIN');
if($origin!=='http://localhost:8088') throw new RuntimeException('This fixture runner only targets local WAMP.');
$prefix='http_test_'.bin2hex(random_bytes(8)); $ids=[]; $count=0; $csrf='';
$curl=curl_init(); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>40]);
function check(bool $condition,string $label): void { global $count; if(!$condition) throw new RuntimeException($label); $count++; echo "PASS $label\n"; }
function request(string $path,string $method='GET',?array $data=null,bool $withCsrf=true): array {
    global $curl,$origin,$csrf;
    $headers=['Origin: '.$origin]; if($withCsrf) $headers[]='X-CSRF-Token: '.$csrf;
    curl_setopt($curl,CURLOPT_POSTFIELDS,null);
    if($data!==null) { $headers[]='Content-Type: application/json'; curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data,JSON_THROW_ON_ERROR)); }
    curl_setopt_array($curl,[CURLOPT_URL=>$origin.$path,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);
    $body=curl_exec($curl); if($body===false) throw new RuntimeException(curl_error($curl));
    return [curl_getinfo($curl,CURLINFO_RESPONSE_CODE),json_decode($body,true),$body];
}
function sessionToken(): void { global $csrf; $response=request('/api/session'); $csrf=$response[1]['data']['csrfToken']; }
try {
    $secret=bin2hex(random_bytes(24));
    $db->run("INSERT INTO users(identifier,name,role,password_hash,webauthn_handle) VALUES(?,?,'Administrator',?,?)",[$prefix,'Synthetic HTTP administrator',Security::hash($secret),random_bytes(32)]);
    $ids[]=(int)$db->pdo->lastInsertId();
    check(request('/')[0]===200,'Login HTML served by Apache');
    check(request('/assets/js/app.js')[0]===200,'Frontend module served');
    check(request('/.env')[0]===404,'Private environment not served');
    check(request('/api/users')[0]===401,'Anonymous administration denied');
    sessionToken();
    check(request('/api/auth/recover','POST',['identifier'=>$prefix,'password'=>$secret],false)[0]===403,'HTTP CSRF enforced');
    check(request('/api/auth/recover','POST',['identifier'=>$prefix,'password'=>$secret])[0]===200,'Recovery authenticates through Apache');
    sessionToken();
    $response=request('/api/users','POST',['identifier'=>$prefix.'_user','name'=>'Synthetic <img src=x onerror=alert(1)>','role'=>'Gestor']);
    check($response[0]===201,'Administrator creates user through API'); $uid=$response[1]['data']['id']; $ids[]=$uid;
    check(request('/api/users?search='.rawurlencode("' OR 1=1 --"))[1]['data']['total']===0,'SQL input remains a search value');
    check(request('/api/users/'.$uid)[1]['data']['name']==='Synthetic <img src=x onerror=alert(1)>','API preserves text for safe DOM rendering');
    $challenge=request('/api/users/'.$uid.'/enroll/pattern/begin','POST',[])[1]['data']['challengeId'];
    $payload=json_encode(['sequence'=>[1,2,3,6,5,4],'confirmation'=>[1,2,3,6,5,4]]);
    check(request('/api/users/'.$uid.'/enroll/pattern/finish','POST',['challengeId'=>$challenge,'payload'=>$payload])[0]===201,'Pattern enrollment through API');
    check(request('/api/logout','POST',[])[0]===200,'Administrator logs out'); sessionToken();
    $challenge=request('/api/auth/begin','POST',['identifier'=>$prefix.'_user','method'=>'pattern'])[1]['data']['challengeId'];
    check(request('/api/auth/verify','POST',['challengeId'=>$challenge,'method'=>'pattern','payload'=>$payload])[0]===200,'Pattern login through API'); sessionToken();
    check(request('/dashboard')[0]===200,'Authenticated dashboard served');
    check(request('/api/users')[0]===403,'Gestor direct API access denied');
    check(request('/admin/users')[0]===403,'Gestor direct administration page denied');
    $db->run('UPDATE users SET active=0,session_version=session_version+1 WHERE id=?',[$uid]);
    check(request('/dashboard')[0]===401,'Deactivation invalidates Apache session');
    $caps=request('/api/capabilities'); check($caps[0]===200 && $caps[1]['data']['face'] && $caps[1]['data']['voice'],'PHP reaches both real biometric models');
    echo "$count HTTP checks passed. Browser rendering and physical sensors not covered.\n";
} finally {
    foreach($ids as $id) {
        // Exact IDs captured from this run only; never delete existing users.
        $db->run('DELETE FROM audit_events WHERE actor_id=? OR target_user_id=?',[$id,$id]);
        $db->run('DELETE FROM access_attempts WHERE user_id=?',[$id]);
        $db->run('DELETE FROM users WHERE id=? AND identifier LIKE ?',[$id,$prefix.'%']);
    }
    curl_close($curl);
}
