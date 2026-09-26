<?php
declare(strict_types=1);
// Synthetic test authenticator. Exercises real server cryptography, never represents a fingerprint sensor.
final class TestBytes { public function __construct(public string $value) {} }
final class VirtualAuthenticator {
    private \OpenSSLAsymmetricKey $key;
    public string $id;
    public function __construct() {
        $options=['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1'];
        $config=getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if(is_file($config)) $options['config']=$config;
        $key=openssl_pkey_new($options);
        if($key===false) throw new RuntimeException('Cannot create synthetic authenticator key: '.openssl_error_string());
        $this->key=$key; $this->id=random_bytes(32);
    }
    private static function head(int $type,int $length): string { if($length<24)return chr(($type<<5)|$length);if($length<256)return chr(($type<<5)|24).chr($length);if($length<65536)return chr(($type<<5)|25).pack('n',$length);return chr(($type<<5)|26).pack('N',$length); }
    private static function cbor(mixed $v): string {
        if($v instanceof TestBytes)return self::head(2,strlen($v->value)).$v->value;
        if(is_string($v))return self::head(3,strlen($v)).$v;
        if(is_int($v))return self::head($v>=0?0:1,$v>=0?$v:-1-$v);
        $values=(array)$v;$out=self::head(5,count($values));foreach($values as $key=>$value)$out.=self::cbor($key).self::cbor($value);return $out;
    }
    private function client(string $challenge,string $type,string $origin): string {return json_encode(['type'=>$type,'challenge'=>$challenge,'origin'=>$origin,'crossOrigin'=>false],JSON_UNESCAPED_SLASHES);}
    public function registration(object $options): array {
        $options=json_decode(json_encode($options),true);$details=openssl_pkey_get_details($this->key);
        $cose=self::cbor([1=>2,3=>-7,-1=>1,-2=>new TestBytes($details['ec']['x']),-3=>new TestBytes($details['ec']['y'])]);
        $auth=hash('sha256',$options['rp']['id'],true).chr(0x45).pack('N',0).str_repeat("\0",16).pack('n',strlen($this->id)).$this->id.$cose;
        $object=self::cbor(['fmt'=>'none','attStmt'=>new stdClass(),'authData'=>new TestBytes($auth)]);
        return ['id'=>App\WebAuthnService::encode($this->id),'rawId'=>App\WebAuthnService::encode($this->id),'type'=>'public-key','response'=>['clientDataJSON'=>App\WebAuthnService::encode($this->client($options['challenge'],'webauthn.create','http://localhost:8088')),'attestationObject'=>App\WebAuthnService::encode($object),'transports'=>['internal']]];
    }
    public function assertion(object $options,string $handle,int $count=1,string $origin='http://localhost:8088',int $flags=0x05): array {
        $o=json_decode(json_encode($options),true);$client=$this->client($o['challenge'],'webauthn.get',$origin);
        $auth=hash('sha256',$o['rpId'],true).chr($flags).pack('N',$count);openssl_sign($auth.hash('sha256',$client,true),$signature,$this->key,OPENSSL_ALGO_SHA256);
        return ['id'=>App\WebAuthnService::encode($this->id),'rawId'=>App\WebAuthnService::encode($this->id),'type'=>'public-key','response'=>['clientDataJSON'=>App\WebAuthnService::encode($client),'authenticatorData'=>App\WebAuthnService::encode($auth),'signature'=>App\WebAuthnService::encode($signature),'userHandle'=>App\WebAuthnService::encode($handle)]];
    }
}
