<?php
declare(strict_types=1);
namespace App;
final class AdminService {
    public function __construct(private Database $db, private Security $security, private AuthService $auth, private WebAuthnService $web, private BiometricClient $bio) {}
    private function actor(): array { return $this->security->user(true,true); }
    public function find(int $id): array {
        $user=$this->db->one('SELECT * FROM users WHERE id=?',[$id]);
        if(!$user) throw new HttpError(404,'NOT_FOUND','Usuario no encontrado.'); return $user;
    }
    public function detail(int $id): array {
        $this->actor(); $u=$this->find($id);
        $out=['id'=>(int)$u['id'],'identifier'=>$u['identifier'],'name'=>$u['name'],'role'=>$u['role'],'active'=>(bool)$u['active'],'hasRecovery'=>$u['password_hash']!==null,'modalities'=>[],'credentials'=>[]];
        foreach(AuthService::METHODS as $m) $out['modalities'][$m]=false;
        foreach($this->db->run('SELECT id FROM pattern_credentials WHERE user_id=?',[$id])->fetchAll() as $c) $out['credentials'][]=['id'=>'pattern-'.$c['id'],'method'=>'pattern'];
        foreach($this->db->run('SELECT id,modality FROM biometric_templates WHERE user_id=?',[$id])->fetchAll() as $c) $out['credentials'][]=['id'=>$c['modality'].'-'.$c['id'],'method'=>$c['modality']];
        foreach($this->db->run('SELECT id FROM webauthn_credentials WHERE user_id=?',[$id])->fetchAll() as $c) $out['credentials'][]=['id'=>'webauthn-'.$c['id'],'method'=>'webauthn'];
        foreach($out['credentials'] as $c) $out['modalities'][$c['method']]=true;
        return $out;
    }
    public function listing(array $query): array {
        $this->actor(); $page=max(1,min(10000,(int)($query['page'] ?? 1))); $where=['1=1']; $params=[];
        if(is_string($query['search'] ?? null) && $query['search']!=='') { $where[]='(name LIKE ? OR identifier LIKE ?)'; $s='%'.mb_substr($query['search'],0,100).'%'; array_push($params,$s,$s); }
        if(in_array($query['role'] ?? '',['Administrator','Gestor'],true)) { $where[]='role=?'; $params[]=$query['role']; }
        if(in_array($query['active'] ?? '',['0','1'],true)) { $where[]='active=?'; $params[]=(int)$query['active']; }
        $filter=implode(' AND ',$where); $total=(int)$this->db->run('SELECT COUNT(*) FROM users WHERE '.$filter,$params)->fetchColumn();
        $ids=$this->db->run('SELECT id FROM users WHERE '.$filter.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20),$params)->fetchAll(\PDO::FETCH_COLUMN);
        return ['items'=>array_map(fn($id)=>$this->detail((int)$id),$ids),'page'=>$page,'total'=>$total,'pages'=>max(1,(int)ceil($total/20))];
    }
    private function data(array $data, bool $create): array {
        $allowed=$create?['identifier','name','role','active']:['name','role'];
        if(array_diff(array_keys($data),$allowed)) throw new HttpError(422,'INVALID_INPUT','Campos no permitidos.');
        $name=$data['name'] ?? null; $role=$data['role'] ?? null;
        if(!is_string($name) || mb_strlen(trim($name))<2 || mb_strlen($name)>150 || preg_match('/[\x00-\x1f]/',$name) || !in_array($role,['Administrator','Gestor'],true)) throw new HttpError(422,'INVALID_INPUT','Revisa el nombre y el rol.');
        return [trim($name),$role];
    }
    public function create(array $data): array {
        $actor=$this->actor(); [$name,$role]=$this->data($data,true); $identifier=Security::identifier($data['identifier'] ?? null); $active=$data['active'] ?? true;
        if(!is_bool($active)) throw new HttpError(422,'INVALID_INPUT','Estado inválido.');
        return $this->db->transaction(function() use($actor,$name,$role,$identifier,$active) {
            $this->db->run('SELECT id FROM app_locks WHERE id=1 FOR UPDATE');
            $this->db->run('INSERT INTO users(identifier,name,role,active,webauthn_handle) VALUES(?,?,?,?,?)',[$identifier,$name,$role,(int)$active,random_bytes(32)]);
            $id=(int)$this->db->pdo->lastInsertId(); $this->security->audit($actor['id'],$id,'USER_CREATED'); return ['id'=>$id];
        });
    }
    public function update(int $id, array $data, bool $status=false): array {
        $actor=$this->actor();
        if($status) { if(array_keys($data)!==['active'] || !is_bool($data['active'])) throw new HttpError(422,'INVALID_INPUT','Estado inválido.'); }
        else [$name,$role]=$this->data($data,false);
        $this->db->transaction(function() use($id,$data,$status,$actor) {
            $this->db->run('SELECT id FROM app_locks WHERE id=1 FOR UPDATE'); $user=$this->find($id);
            $active=$status?$data['active']:(bool)$user['active']; $role=$status?$user['role']:$data['role'];
            if($user['role']==='Administrator' && $user['active'] && (!$active || $role!=='Administrator')) {
                $count=(int)$this->db->run("SELECT COUNT(*) FROM users WHERE active=1 AND role='Administrator'")->fetchColumn();
                if($count<=1) throw new HttpError(409,'LAST_ADMIN','Debe permanecer al menos un Administrator activo.');
            }
            if($status) $this->db->run('UPDATE users SET active=?,session_version=session_version+1 WHERE id=?',[(int)$active,$id]);
            else $this->db->run('UPDATE users SET name=?,role=?,session_version=session_version+? WHERE id=?',[trim($data['name']),$role,(int)($role!==$user['role']),$id]);
            $this->security->audit($actor['id'],$id,$status?'STATUS_CHANGED':'USER_UPDATED');
        });
        return ['id'=>$id];
    }
    public function password(int $id, array $data): array {
        $actor=$this->actor(); $password=$data['password'] ?? null;
        if(!is_string($password) || strlen($password)<12 || strlen($password)>128 || $password!==($data['confirmation'] ?? null)) throw new HttpError(422,'INVALID_INPUT','Usa entre 12 y 128 caracteres y confirma la contraseña.');
        $this->find($id); $hash=Security::hash($password);
        $this->db->transaction(function() use($id,$actor,$hash) { $this->db->run('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[$hash,$id]); $this->security->audit($actor['id'],$id,'RECOVERY_CHANGED'); }); return ['id'=>$id];
    }
    public function begin(int $id,string $method): array {
        $actor=$this->actor(); AuthService::method($method); $user=$this->find($id);
        if(!$user['active']) throw new HttpError(409,'INACTIVE_USER','Activa la cuenta antes de registrar credenciales.');
        $digest=$this->security->digest('account:'.$user['identifier']);
        return $this->security->limited($digest,$method,function() use($user,$method,$digest,$actor) {
            if(in_array($method,['face','voice'],true) && !($this->bio->capabilities()[$method])) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Servicio biométrico no disponible.');
            $result=$this->auth->challenge($user,$user['identifier'],$method,'enroll'); $this->security->attempt($user['id'],$digest,$method,'request','ENROLL_BEGIN'); $this->security->audit($actor['id'],$user['id'],'ENROLL_BEGIN_'.$method); return $result;
        });
    }
    public function finish(int $id,string $method,array $input,array $payload): array {
        $actor=$this->actor(); AuthService::method($method); $user=$this->find($id); $cid=$input['challengeId'] ?? '';
        if(!is_string($cid) || strlen($cid)!==64) throw new HttpError(422,'INVALID_CHALLENGE','Vuelve a iniciar el registro.');
        $challenge=$this->auth->consume($cid,$method,'enroll',$id);
        if(!$challenge || !$user['active'] || $user['session_version']!==$challenge['session_version']) throw new HttpError(409,'INVALID_CHALLENGE','El registro venció o ya se utilizó. Inícialo otra vez.');
        $template=null; $hash=null;
        if($method==='pattern') { $pattern=Security::pattern($payload['sequence'] ?? null); if($pattern!==Security::pattern($payload['confirmation'] ?? null)) throw new HttpError(422,'PATTERN_MISMATCH','Los patrones no coinciden.'); $hash=Security::hash($pattern); }
        if(in_array($method,['face','voice'],true)) {
            if(($payload['consent'] ?? null)!==true || ($payload['noticeVersion'] ?? '')!=='academic-v1') throw new HttpError(422,'CONSENT_REQUIRED','Se requiere el consentimiento del titular.');
            if(!$this->bio->enabled($method)) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Modalidad no disponible.');
            $result=$this->bio->request('/enroll/'.$method,$this->bio->uploads($method,true)); $template=$result['template'] ?? null;
            if(!is_array($template) || !is_string($template['modelVersion'] ?? null) || !is_string($template['thresholdVersion'] ?? null) || strlen($template['modelVersion'])>150 || strlen($template['thresholdVersion'])>150) throw new HttpError(503,'BIOMETRIC_UNAVAILABLE','Respuesta biométrica inválida.');
        }
        $this->db->transaction(function() use($id,$method,$user,$challenge,$payload,$hash,$template,$actor) {
            $current=$this->db->one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]);
            if(!$current['active'] || $current['session_version']!==$challenge['session_version']) throw new HttpError(409,'ACCOUNT_CHANGED','La cuenta cambió. Reinicia el registro.');
            if($method==='pattern') $this->db->run('INSERT INTO pattern_credentials(user_id,pattern_hash) VALUES(?,?) ON DUPLICATE KEY UPDATE pattern_hash=VALUES(pattern_hash)',[$id,$hash]);
            elseif($method==='webauthn') {
                if((int)$this->db->run('SELECT COUNT(*) FROM webauthn_credentials WHERE user_id=?',[$id])->fetchColumn()>=5) throw new HttpError(409,'CREDENTIAL_LIMIT','Revoca una credencial antes de registrar otra.');
                try { $this->web->enroll($user,$challenge,$payload); } catch(\InvalidArgumentException|\JsonException|\lbuchs\WebAuthn\WebAuthnException) { throw new HttpError(422,'INVALID_CREDENTIAL','No se pudo validar la credencial del dispositivo.'); }
            } else {
                [$cipher,$nonce,$keyVersion]=$this->bio->encrypt($template,$id,$method);
                $this->db->run('DELETE FROM biometric_templates WHERE user_id=? AND modality=?',[$id,$method]);
                $this->db->run('INSERT INTO biometric_templates(user_id,modality,ciphertext,nonce,key_version,model_version,threshold_version) VALUES(?,?,?,?,?,?,?)',[$id,$method,$cipher,$nonce,$keyVersion,$template['modelVersion'],$template['thresholdVersion']]);
                $this->db->run('UPDATE consents SET withdrawn_at=UTC_TIMESTAMP() WHERE user_id=? AND modality=? AND withdrawn_at IS NULL',[$id,$method]);
                $this->db->run('INSERT INTO consents(user_id,modality,notice_version) VALUES(?,?,?)',[$id,$method,'academic-v1']);
            }
            $this->security->audit($actor['id'],$id,'CREDENTIAL_ENROLLED_'.$method);
        }); return ['id'=>$id,'method'=>$method];
    }
    public function revoke(int $id,string $credentialId): array {
        $actor=$this->actor(); $this->find($id);
        if(!preg_match('/^(pattern|face|voice|webauthn)-([1-9][0-9]*)$/D',$credentialId,$m)) throw new HttpError(422,'INVALID_CREDENTIAL','Credencial inválida.');
        $table=match($m[1]) {'pattern'=>'pattern_credentials','webauthn'=>'webauthn_credentials',default=>'biometric_templates'};
        $this->db->transaction(function() use($id,$m,$table,$actor) {
            $this->db->run('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]);
            $params=[$m[2],$id]; $sql='DELETE FROM '.$table.' WHERE id=? AND user_id=?';
            if($table==='biometric_templates') { $sql.=' AND modality=?'; $params[]=$m[1]; }
            if($this->db->run($sql,$params)->rowCount()!==1) throw new HttpError(404,'NOT_FOUND','Credencial no encontrada.');
            if($table==='biometric_templates') $this->db->run('UPDATE consents SET withdrawn_at=UTC_TIMESTAMP() WHERE user_id=? AND modality=? AND withdrawn_at IS NULL',[$id,$m[1]]);
            $this->db->run('UPDATE users SET session_version=session_version+1 WHERE id=?',[$id]); $this->security->audit($actor['id'],$id,'CREDENTIAL_REVOKED_'.$m[1]);
        }); return ['id'=>$id];
    }
    public function attempts(array $query): array {
        $this->actor(); $page=max(1,min(10000,(int)($query['page'] ?? 1))); $where=["a.outcome<>'request'"]; $params=[];
        if(in_array($query['method'] ?? '',[...AuthService::METHODS,'recovery'],true)) { $where[]='a.method=?'; $params[]=$query['method']; }
        if(in_array($query['outcome'] ?? '',['success','failure','error','limited'],true)) { $where[]='a.outcome=?'; $params[]=$query['outcome']; }
        $filter=implode(' AND ',$where); $total=(int)$this->db->run('SELECT COUNT(*) FROM access_attempts a WHERE '.$filter,$params)->fetchColumn();
        $rows=$this->db->run('SELECT a.id,a.created_at,a.method,a.outcome,a.request_id,u.identifier FROM access_attempts a LEFT JOIN users u ON u.id=a.user_id WHERE '.$filter.' ORDER BY a.id DESC LIMIT 20 OFFSET '.(($page-1)*20),$params)->fetchAll();
        return ['items'=>$rows,'page'=>$page,'total'=>$total,'pages'=>max(1,(int)ceil($total/20))];
    }
}
