<?php
declare(strict_types=1);
namespace App;
final class BiometricClient {
    public function __construct(private Config $config) {}
    public function enabled(string $method): bool { return $this->config->get(strtoupper($method).'_ENABLED')==='1'; }
    public function request(string $path, array $fields=[], bool $health=false): array {
        $url=$this->config->get('BIOMETRIC_URL');
        if(!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D',$url) || strlen($this->config->get('BIOMETRIC_TOKEN'))<32) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Servicio biométrico no disponible.');
        $ch=curl_init($url.$path); $response='';
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>$health?3:35,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config->get('BIOMETRIC_TOKEN')],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use (&$response) { if(strlen($response)+strlen($chunk)>262144) return 0; $response.=$chunk; return strlen($chunk); }]);
        if(!$health) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields]);
        $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        $data=json_decode($response,true);
        if($status===422) throw new HttpError(422,'INVALID_SAMPLE','La muestra no cumple los requisitos. Repite la captura.');
        if($ok===false || $status!==200 || !is_array($data) || ($data['ok'] ?? false)!==true) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Servicio biométrico no disponible. Intenta más tarde.');
        return $data;
    }
    public function capabilities(): array {
        try { $health=$this->request('/health',[],true); }
        catch(HttpError) { return ['face'=>false,'voice'=>false]; }
        return ['face'=>$this->enabled('face') && ($health['face'] ?? false)===true,'voice'=>$this->enabled('voice') && ($health['voice'] ?? false)===true];
    }
    public function uploads(string $method, bool $enroll): array {
        $field=$enroll?'samples':'sample'; $f=$_FILES[$field] ?? null;
        if(!$f || !isset($f['tmp_name'])) throw new HttpError(422,'INVALID_SAMPLE','Falta la muestra.');
        $paths=$enroll?$f['tmp_name']:[$f['tmp_name']]; $errors=$enroll?$f['error']:[$f['error']];
        if(!is_array($paths) || count($paths)!==($enroll?3:1)) throw new HttpError(422,'INVALID_SAMPLE','Se requieren '.($enroll?'tres muestras.':'una muestra.'));
        $fields=[];
        foreach($paths as $i=>$tmp) {
            if(!is_string($tmp) || ($errors[$i] ?? 1)!==UPLOAD_ERR_OK || !is_uploaded_file($tmp) || filesize($tmp)>8*1024*1024 || filesize($tmp)<20) throw new HttpError(422,'INVALID_SAMPLE','Archivo inválido o demasiado grande (máximo 8 MB).');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            $allowed=$method==='face'?['image/jpeg','image/png','image/webp']:['audio/webm','video/webm','audio/ogg','video/ogg','application/ogg','audio/mp4','video/mp4','audio/x-m4a','audio/wav','audio/x-wav'];
            if(!in_array($mime,$allowed,true)) throw new HttpError(422,'INVALID_SAMPLE','Formato no admitido.');
            $fields[$enroll?'samples['.$i.']':'sample']=new \CURLFile($tmp,$mime,'capture');
        }
        return $fields;
    }
    public function encrypt(array $template, int $uid, string $method): array {
        $nonce=random_bytes(24); $version=$this->config->get('TEMPLATE_KEY_VERSION','1');
        $cipher=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(json_encode($template,JSON_THROW_ON_ERROR),"$uid:$method:$version",$nonce,$this->config->key('TEMPLATE_KEY'));
        return [$cipher,$nonce,$version];
    }
    public function decrypt(array $row): array {
        if($row['key_version']!==$this->config->get('TEMPLATE_KEY_VERSION','1')) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Es necesario volver a registrar esta modalidad.');
        $plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($row['ciphertext'],$row['user_id'].':'.$row['modality'].':'.$row['key_version'],$row['nonce'],$this->config->key('TEMPLATE_KEY'));
        if($plain===false) throw new \RuntimeException('TEMPLATE_INTEGRITY');
        return json_decode($plain,true,32,JSON_THROW_ON_ERROR);
    }
}
