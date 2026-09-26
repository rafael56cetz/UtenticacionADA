<?php
declare(strict_types=1);
namespace App;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;
final class WebAuthnService {
    private WebAuthn $lib;
    public function __construct(private Config $config, private Database $db) { $this->lib=new WebAuthn('Acceso',$config->get('WEBAUTHN_RP_ID'),['none'],true); }
    public static function encode(string $s): string { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
    public static function decode(mixed $s, int $max=65536): string {
        if(!is_string($s) || strlen($s)>$max || !preg_match('/^[A-Za-z0-9_-]+$/D',$s)) throw new \InvalidArgumentException('INVALID_BINARY');
        $b=base64_decode(strtr($s,'-_','+/'),true);
        if($b===false || self::encode($b)!==$s) throw new \InvalidArgumentException('INVALID_BINARY');
        return $b;
    }
    public function options(?array $user, bool $enroll): array {
        if($enroll) {
            $ids=$this->db->run('SELECT credential_id FROM webauthn_credentials WHERE user_id=?',[$user['id']])->fetchAll(\PDO::FETCH_COLUMN);
            $options=$this->lib->getCreateArgs($user['webauthn_handle'],$user['identifier'],$user['name'],120,true,true,false,$ids)->publicKey;
        } else {
            // Discoverable credentials: identical options for existing/nonexistent accounts.
            $options=$this->lib->getGetArgs([],120,false,false,false,true,true,true)->publicKey;
        }
        return ['options'=>$options,'challenge'=>$this->lib->getChallenge()->getBinaryString()];
    }
    private function client(array $payload, string $type): string {
        if(($payload['type'] ?? '')!=='public-key' || ($payload['id'] ?? null)!==($payload['rawId'] ?? null)) throw new \InvalidArgumentException('INVALID_CREDENTIAL');
        $raw=self::decode($payload['response']['clientDataJSON'] ?? null);
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(($data['origin'] ?? '')!==$this->config->get('APP_ORIGIN') || ($data['type'] ?? '')!==$type || ($data['crossOrigin'] ?? false)!==false) throw new \InvalidArgumentException('INVALID_ORIGIN');
        return $raw;
    }
    public function enroll(array $user, array $challenge, array $payload): void {
        $client=$this->client($payload,'webauthn.create');
        $data=$this->lib->processCreate($client,self::decode($payload['response']['attestationObject'] ?? null),$challenge['challenge'],true,true);
        if(!hash_equals($data->credentialId,self::decode($payload['rawId'],2048))) throw new \InvalidArgumentException('ID_MISMATCH');
        $transports=array_values(array_intersect((array)($payload['response']['transports'] ?? []),['internal','hybrid','usb','nfc','ble']));
        $this->db->run('INSERT INTO webauthn_credentials(user_id,credential_id,credential_digest,public_key,sign_count,transports,backup_eligible,backup_state) VALUES(?,?,?,?,?,?,?,?)',[$user['id'],$data->credentialId,hash('sha256',$data->credentialId,true),$data->credentialPublicKey,$data->signatureCounter ?? 0,json_encode($transports),(int)$data->isBackupEligible,(int)$data->isBackedUp]);
    }
    public function verify(array $user, array $challenge, array $payload): bool {
        $client=$this->client($payload,'webauthn.get');
        $id=self::decode($payload['rawId'] ?? null,2048);
        $cred=$this->db->one('SELECT * FROM webauthn_credentials WHERE credential_digest=? AND user_id=? FOR UPDATE',[hash('sha256',$id,true),$user['id']]);
        if(!$cred || !hash_equals($cred['credential_id'],$id) || !hash_equals($user['webauthn_handle'],self::decode($payload['response']['userHandle'] ?? null,256))) return false;
        $authData=self::decode($payload['response']['authenticatorData'] ?? null);
        $this->lib->processGet($client,$authData,self::decode($payload['response']['signature'] ?? null),$cred['public_key'],$challenge['challenge'],$cred['backup_eligible'] ? null : (int)$cred['sign_count'],true,true);
        $meta=new AuthenticatorData($authData);
        if((bool)$cred['backup_eligible']!==$meta->getIsBackupEligible() || ($meta->getIsBackup() && !$meta->getIsBackupEligible())) return false;
        $this->db->run('UPDATE webauthn_credentials SET sign_count=?,backup_state=? WHERE id=?',[$meta->getSignCount(),(int)$meta->getIsBackup(),$cred['id']]);
        return true;
    }
}
