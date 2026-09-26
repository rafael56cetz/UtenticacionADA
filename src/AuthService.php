<?php
declare(strict_types=1);
namespace App;
final class AuthService {
    public const METHODS=['face','voice','webauthn','pattern'];
    public function __construct(private Database $db, private Security $security, private WebAuthnService $web, private BiometricClient $bio) {}
    public static function method(mixed $method): string {
        if(!is_string($method) || !in_array($method,self::METHODS,true)) throw new HttpError(422,'INVALID_METHOD','Método inválido.'); return $method;
    }
    public function challenge(?array $user, string $identifier, string $method, string $purpose): array {
        $id=bin2hex(random_bytes(32)); $bytes=random_bytes(32); $options=null;
        if($method==='webauthn') { $result=$this->web->options($user,$purpose==='enroll'); $bytes=$result['challenge']; $options=$result['options']; }
        $digest=$this->security->digest('account:'.$identifier);
        $this->db->run('INSERT INTO auth_challenges(id,user_id,identifier_digest,session_binding,purpose,method,challenge,session_version,expires_at) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 120 SECOND))',[$id,$user['id'] ?? null,$digest,$this->security->binding(),$purpose,$method,$bytes,$user['session_version'] ?? null]);
        $out=['challengeId'=>$id,'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+120)];
        if($options) $out['publicKeyOptions']=$options;
        return $out;
    }
    public function begin(array $input): array {
        $identifier=Security::identifier($input['identifier'] ?? null); $method=self::method($input['method'] ?? null); $digest=$this->security->digest('account:'.$identifier);
        return $this->security->limited($digest,$method,function() use($identifier,$method,$digest) {
            $user=$this->db->one('SELECT * FROM users WHERE identifier=?',[$identifier]);
            $out=$this->challenge($user,$identifier,$method,'auth');
            $this->security->attempt($user['id'] ?? null,$digest,$method,'request','BEGIN'); return $out;
        });
    }
    public function consume(string $id, string $method, string $purpose, ?int $uid=null): ?array {
        return $this->db->transaction(function() use($id,$method,$purpose,$uid) {
            $row=$this->db->one('SELECT * FROM auth_challenges WHERE id=? FOR UPDATE',[$id]);
            if(!$row || $row['method']!==$method || $row['purpose']!==$purpose || !hash_equals($row['session_binding'],$this->security->binding()) || $row['consumed_at']!==null || strtotime($row['expires_at'].' UTC')<=time() || ($uid!==null && (int)$row['user_id']!==$uid)) return null;
            $this->db->run('UPDATE auth_challenges SET consumed_at=UTC_TIMESTAMP() WHERE id=?',[$id]); return $row;
        });
    }
    public function verify(array $input, array $payload): array {
        $method=self::method($input['method'] ?? null); $id=is_string($input['challengeId'] ?? null)?$input['challengeId']:'';
        $row=strlen($id)===64?$this->db->one('SELECT * FROM auth_challenges WHERE id=?',[$id]):null;
        $digest=$row['identifier_digest'] ?? $this->security->digest('invalid');
        return $this->security->limited($digest,$method,function() use($id,$method,$payload,$digest) {
            $challenge=$this->consume($id,$method,'auth');
            $user=$challenge?$this->db->one('SELECT * FROM users WHERE id=?',[$challenge['user_id']]):null;
            $match=false; $reason='INVALID_CHALLENGE';
            try {
                if($challenge) {
                    $eligible=$user && $user['active'] && $user['session_version']===$challenge['session_version'];
                    $reason='BAD_CREDENTIAL';
                    if($method==='pattern') {
                        $pattern=Security::pattern($payload['sequence'] ?? null);
                        $credential=$user?$this->db->one('SELECT pattern_hash FROM pattern_credentials WHERE user_id=?',[$user['id']]):null;
                        // Fixed-cost fallback hash prevents fast nonexistent-account rejection.
                        $hash=$credential['pattern_hash'] ?? $this->dummyHash();
                        $match=password_verify($pattern,$hash) && $eligible && $credential!==null;
                    } elseif($method==='webauthn') {
                        $match=$eligible && $this->db->transaction(fn()=> $this->web->verify($user,$challenge,$payload));
                    } else {
                        if(!$this->bio->enabled($method)) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Modalidad biométrica no disponible.');
                        $fields=$this->bio->uploads($method,false);
                        $template=$user?$this->db->one('SELECT * FROM biometric_templates WHERE user_id=? AND modality=?',[$user['id'],$method]):null;
                        $fields['reference']=$eligible && $template ? json_encode($this->bio->decrypt($template),JSON_THROW_ON_ERROR) : '{}';
                        $res=$this->bio->request('/verify/'.$method,$fields);
                        if(!isset($res['match']) || !is_bool($res['match'])) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Servicio biométrico no disponible.');
                        $match=$res['match'] && $eligible && $template!==null;
                    }
                }
            } catch(HttpError $e) {
                $this->security->attempt($user['id'] ?? null,$digest,$method,$e->status===503?'error':'failure',$e->publicCode);
                if($e->status===503) throw $e;
                Security::denied();
            } catch(\InvalidArgumentException|\JsonException|\lbuchs\WebAuthn\WebAuthnException $e) { $match=false; $reason='INVALID_EVIDENCE'; }
            if(!$match) { $this->security->attempt($user['id'] ?? null,$digest,$method,'failure',$reason); Security::denied(); }
            return $this->complete($user,$method,$digest);
        });
    }
    private function dummyHash(): string {
        $path=$this->security->config->root.'/storage/private/dummy.hash';
        if(!is_file($path)) file_put_contents($path,Security::hash(bin2hex(random_bytes(32))),LOCK_EX);
        return trim(file_get_contents($path));
    }
    private function complete(array $user,string $method,string $digest): array {
        $current=$this->db->one('SELECT * FROM users WHERE id=?',[$user['id']]);
        if(!$current || !$current['active'] || $current['session_version']!==$user['session_version']) { $this->security->attempt($user['id'],$digest,$method,'failure','ACCOUNT_CHANGED'); Security::denied(); }
        $this->security->login($current,$method); $this->security->attempt($current['id'],$digest,$method,'success','VERIFIED');
        return ['redirectTo'=>$this->security->config->url('/dashboard'),'csrfToken'=>$_SESSION['csrf']];
    }
    public function recover(array $input): array {
        $identifier=Security::identifier($input['identifier'] ?? null); $password=$input['password'] ?? null;
        if(!is_string($password) || strlen($password)>256) throw new HttpError(422,'INVALID_INPUT','Datos inválidos.');
        $digest=$this->security->digest('account:'.$identifier);
        return $this->security->limited($digest,'recovery',function() use($identifier,$password,$digest) {
            $user=$this->db->one('SELECT * FROM users WHERE identifier=?',[$identifier]);
            $valid=password_verify($password,$user['password_hash'] ?? $this->dummyHash());
            if(!$valid || !$user || !$user['active'] || !$user['password_hash']) { $this->security->attempt($user['id'] ?? null,$digest,'recovery','failure','BAD_CREDENTIAL'); Security::denied(); }
            return $this->complete($user,'recovery',$digest);
        });
    }
}
