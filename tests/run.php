<?php
declare(strict_types=1);
// Real MySQL integration tests. Only the randomly named database created here is removed.
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/VirtualAuthenticator.php';
use App\{Config,Database,Kernel,Security,HttpError,WebAuthnService};
date_default_timezone_set('UTC'); ob_start();
$root=dirname(__DIR__); $name='acceso_test_'.bin2hex(random_bytes(6)); $created=false; $checks=0;
$admin=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$dir=$root.'/.tmp/'.$name;mkdir($dir,0700,true);
function check(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS $label\n";}
function rejects(callable $fn,int $status,string $label): void {try{$fn();}catch(HttpError $e){check($e->status===$status,$label.' ('.$e->status.')');return;}throw new RuntimeException('FAIL no rejection: '.$label);}
try {
 $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");$created=true;$admin->exec("USE `$name`");$admin->exec(file_get_contents($root.'/database/schema.sql'));
 $env="DB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=$name\nDB_USER=".(getenv('TEST_DB_USER')?:'root')."\nDB_PASSWORD=".(getenv('TEST_DB_PASSWORD')?:'')."\nAPP_ORIGIN=http://localhost:8088\nWEBAUTHN_RP_ID=localhost\nAPP_KEY=".base64_encode(random_bytes(32))."\nTEMPLATE_KEY=".base64_encode(random_bytes(32))."\nBIOMETRIC_URL=http://127.0.0.1:19999\nBIOMETRIC_TOKEN=".bin2hex(random_bytes(32))."\nFACE_ENABLED=1\nVOICE_ENABLED=1\n";
 file_put_contents($dir.'/.env',$env);$kernel=new Kernel(new Config($dir),bin2hex(random_bytes(16)));$db=$kernel->db;$security=$kernel->security;$security->start();$_SERVER['REMOTE_ADDR']='127.0.0.91';
 $clearRates=fn()=> $db->run('DELETE FROM access_attempts');
 $db->run("INSERT INTO users(identifier,name,role,password_hash,webauthn_handle) VALUES('test.admin','Admin','Administrator',?,?)",[Security::hash('Test-recovery-'.str_repeat('x',16)),random_bytes(32)]);$aid=(int)$db->pdo->lastInsertId();
 $adminLogin=function()use($security,$db,$aid){$security->login($db->one('SELECT * FROM users WHERE id=?',[$aid]),'recovery');};
 rejects(fn()=> $security->csrf(),403,'CSRF absent denied');$_SERVER['HTTP_X_CSRF_TOKEN']=$_SESSION['csrf'];$security->csrf();check(true,'CSRF valid accepted');
 $_SERVER['HTTP_ORIGIN']='https://evil.invalid';rejects(fn()=> $security->csrf(),403,'Foreign origin denied');unset($_SERVER['HTTP_ORIGIN']);
 rejects(fn()=> $kernel->auth->verify(['challengeId'=>str_repeat('a',64),'method'=>'pattern'],['sequence'=>[1,2,3,4,5,6]]),401,'Unknown challenge cannot log in');check($security->user()===null,'No fabricated session');$clearRates();
 $before=session_id();$kernel->auth->recover(['identifier'=>'test.admin','password'=>'Test-recovery-'.str_repeat('x',16)]);check(session_id()!==$before && $security->user()['role']==='Administrator','Recovery hashes and session rotation');
 $u=$kernel->admin->create(['identifier'=>'test.gestor','name'=>'<img src=x onerror=alert(1)>','role'=>'Gestor','active'=>true]);$uid=$u['id'];
 check($kernel->admin->detail($uid)['modalities']['pattern']===false,'New account has no credential');
 rejects(fn()=> $kernel->admin->update($aid,['active'=>false],true),409,'Last administrator cannot be disabled');
 rejects(fn()=> $kernel->admin->update($aid,['name'=>'Admin','role'=>'Gestor']),409,'Last administrator cannot be demoted');
 rejects(fn()=> $kernel->admin->create(['identifier'=>'bad.role','name'=>'Bad Role','role'=>'root','active'=>true]),422,'Unknown roles rejected');
 $c=$kernel->admin->begin($uid,'pattern');rejects(fn()=> $kernel->admin->finish($uid,'pattern',$c,['sequence'=>[1,2,3,4,5,6],'confirmation'=>[1,2,3,4,5,7]]),422,'Pattern confirmation checked on server');
 $c=$kernel->admin->begin($uid,'pattern');$kernel->admin->finish($uid,'pattern',$c,['sequence'=>[1,2,3,4,5,6],'confirmation'=>[1,2,3,4,5,6]]);
 $hash=$db->run('SELECT pattern_hash FROM pattern_credentials WHERE user_id=?',[$uid])->fetchColumn();check($hash!=='1,2,3,4,5,6' && password_verify('1,2,3,4,5,6',$hash),'Pattern stored as hash');
 rejects(fn()=> $kernel->admin->finish($uid,'pattern',$c,['sequence'=>[1,2,3,4,5,6],'confirmation'=>[1,2,3,4,5,6]]),409,'Enrollment replay rejected');
 $security->logout();$security->start();$clearRates();
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'pattern']);$kernel->auth->verify([...$c,'method'=>'pattern'],['sequence'=>[1,2,3,4,5,6]]);check($security->user()['role']==='Gestor','Pattern authenticates correct account');
 rejects(fn()=> $kernel->admin->listing([]),403,'Gestor cannot call administration');
 $gestorSession=$_SESSION;$adminLogin();$kernel->admin->update($uid,['active'=>false],true);$_SESSION=$gestorSession;check($security->user()===null,'Deactivation revokes existing session');
 $adminLogin();$kernel->admin->update($uid,['active'=>true],true);$security->logout();$security->start();$clearRates();
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'pattern']);$db->run('UPDATE auth_challenges SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$c['challengeId']]);rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'pattern'],['sequence'=>[1,2,3,4,5,6]]),401,'Expired challenge rejected');
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'pattern']);$_SESSION['binding']=bin2hex(random_bytes(32));rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'pattern'],['sequence'=>[1,2,3,4,5,6]]),401,'Challenge bound to initiating session');
 $clearRates();$c=$kernel->auth->begin(['identifier'=>'unknown.account','method'=>'pattern']);rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'pattern'],['sequence'=>[1,2,3,4,5,6]]),401,'Unknown account generic rejection');
 $clearRates();for($i=0;$i<5;$i++){$c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'pattern']);rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'pattern'],['sequence'=>[9,8,7,6,5,4]]),401,'Wrong pattern '.($i+1));}
 rejects(fn()=> $kernel->auth->begin(['identifier'=>'test.gestor','method'=>'voice']),429,'Rate limit shared across modalities');
 rejects(fn()=> $kernel->auth->recover(['identifier'=>'test.gestor','password'=>'wrong']),429,'Recovery shares rate limit');$clearRates();
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'webauthn']);$fake=$kernel->auth->begin(['identifier'=>'no.such.user','method'=>'webauthn']);
 $a=json_decode(json_encode($c['publicKeyOptions']),true);$b=json_decode(json_encode($fake['publicKeyOptions']),true);unset($a['challenge'],$b['challenge']);check($a===$b,'WebAuthn options do not disclose account credentials');
 rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'webauthn'],['authenticated'=>true]),401,'Client boolean cannot authenticate WebAuthn');$clearRates();
 $adminLogin();rejects(fn()=> $kernel->admin->begin($uid,'face'),503,'Biometric service down closes enrollment');
 $template=['modelVersion'=>'synthetic','thresholdVersion'=>'test','vectors'=>[[0.1,0.2]]];[$cipher,$nonce,$version]=$kernel->bio->encrypt($template,$uid,'face');$row=['user_id'=>$uid,'modality'=>'face','key_version'=>$version,'ciphertext'=>$cipher,'nonce'=>$nonce];check($kernel->bio->decrypt($row)===$template,'Authenticated template encryption roundtrip');
 $row['user_id']=$aid;try{$kernel->bio->decrypt($row);throw new RuntimeException('FAIL swapped template');}catch(RuntimeException $e){check($e->getMessage()==='TEMPLATE_INTEGRITY','Template bound to account and modality');}
 $authenticator=new VirtualAuthenticator();$c=$kernel->admin->begin($uid,'webauthn');$kernel->admin->finish($uid,'webauthn',$c,$authenticator->registration($c['publicKeyOptions']));
 check($kernel->admin->detail($uid)['modalities']['webauthn'],'WebAuthn signed registration accepted');
 $handle=$db->run('SELECT webauthn_handle FROM users WHERE id=?',[$uid])->fetchColumn();
 $security->logout();$security->start();$clearRates();
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'webauthn']);$assertion=$authenticator->assertion($c['publicKeyOptions'],$handle);$kernel->auth->verify([...$c,'method'=>'webauthn'],$assertion);check($security->user()['id']===$uid,'Valid WebAuthn signature authenticates owner');
 $security->logout();$security->start();$clearRates();
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'webauthn']);$assertion=$authenticator->assertion($c['publicKeyOptions'],$handle,2,'http://localhost:9999');rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'webauthn'],$assertion),401,'Exact origin includes port');
 $c=$kernel->auth->begin(['identifier'=>'test.admin','method'=>'webauthn']);$assertion=$authenticator->assertion($c['publicKeyOptions'],$handle,2);rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'webauthn'],$assertion),401,'Another account cannot use credential');
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'webauthn']);$assertion=$authenticator->assertion($c['publicKeyOptions'],$handle,2,'http://localhost:8088',1);rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'webauthn'],$assertion),401,'User verification required');
 $c=$kernel->auth->begin(['identifier'=>'test.gestor','method'=>'webauthn']);$assertion=$authenticator->assertion($c['publicKeyOptions'],$handle,2);$assertion['response']['signature']=WebAuthnService::encode(random_bytes(64));rejects(fn()=> $kernel->auth->verify([...$c,'method'=>'webauthn'],$assertion),401,'Invalid WebAuthn signature rejected');
 $clearRates();$adminLogin();$detail=$kernel->admin->detail($uid);$kernel->admin->revoke($uid,$detail['credentials'][0]['id']);check(!$kernel->admin->detail($uid)['modalities']['pattern'],'Credential revocation removes hash');
 $security->logout();$security->start();check($security->user()===null,'Logout clears identity');
 check((int)$db->run("SELECT COUNT(*) FROM audit_events WHERE action='CREDENTIAL_REVOKED_pattern'")->fetchColumn()===1,'Administrative audit recorded');
 echo "\n$checks integration checks passed.\n";
} catch(Throwable $e){echo "FAILED ".get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n"; $failed=true;}
finally{
 if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
 if($created && preg_match('/^acceso_test_[0-9a-f]{12}$/D',$name))$admin->exec("DROP DATABASE `$name`");
 if(is_file($dir.'/.env'))unlink($dir.'/.env');
 ob_end_flush();
}
exit(isset($failed)?1:0);
