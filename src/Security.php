<?php
declare(strict_types=1);
namespace App;
final class Security {
    public function __construct(public Config $config, public Database $db, public string $requestId) {}
    public function start(): void {
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        $dir=$this->config->root.'/storage/private/sessions';
        if(!is_dir($dir)) mkdir($dir,0700,true);
        session_save_path($dir); session_name('ADA_SESSION');
        session_set_cookie_params(['lifetime'=>0,'path'=>$this->config->base().'/', 'secure'=>str_starts_with($this->config->get('APP_ORIGIN'),'https://'),'httponly'=>true,'samesite'=>'Lax']);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $_SESSION['binding'] ??= bin2hex(random_bytes(32));
    }
    public function csrf(): void {
        $token=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
        if(!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '',$token) || ($origin!=='' && $origin!==$this->config->get('APP_ORIGIN'))) throw new HttpError(403,'CSRF_INVALID','La sesión del formulario venció. Recarga la página.');
    }
    public function user(bool $required=false, bool $admin=false): ?array {
        $user=null;
        if(isset($_SESSION['uid'])) {
            $user=$this->db->one('SELECT * FROM users WHERE id=?',[$_SESSION['uid']]);
            if(!$user || !$user['active'] || $user['session_version']!==$_SESSION['version'] || time()-($_SESSION['last'] ?? 0)>(int)$this->config->get('SESSION_IDLE_SECONDS','1800') || time()-($_SESSION['started'] ?? 0)>(int)$this->config->get('SESSION_MAX_SECONDS','28800')) {
                $this->clearIdentity(); $user=null;
            } else $_SESSION['last']=time();
        }
        if($required && !$user) throw new HttpError(401,'SESSION_REQUIRED','Inicia sesión para continuar.');
        if($admin && ($user['role'] ?? '')!=='Administrator') throw new HttpError(403,'FORBIDDEN','No tienes permiso para esta operación.');
        return $user;
    }
    public function login(array $user, string $method): void {
        session_regenerate_id(true); $_SESSION=[];
        $_SESSION['uid']=(int)$user['id']; $_SESSION['version']=$user['session_version'];
        $_SESSION['started']=$_SESSION['last']=time(); $_SESSION['method']=$method;
        $_SESSION['csrf']=bin2hex(random_bytes(32)); $_SESSION['binding']=bin2hex(random_bytes(32));
    }
    private function clearIdentity(): void { $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); $_SESSION['binding']=bin2hex(random_bytes(32)); }
    public function logout(): void {
        $_SESSION=[]; $params=session_get_cookie_params();
        setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$params['path'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Lax']); session_destroy();
    }
    public function digest(string $value): string { return hash_hmac('sha256',$value,$this->config->key('APP_KEY')); }
    public function binding(): string { return $this->digest('session:'.($_SESSION['binding'] ?? '')); }
    public function ip(): string { return $this->digest('ip:'.($_SERVER['REMOTE_ADDR'] ?? 'cli')); }
    public static function identifier(mixed $value): string {
        if(!is_string($value) || !preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/',trim($value))) throw new HttpError(422,'INVALID_INPUT','El identificador debe tener entre 3 y 100 letras, números o caracteres . _ @ -');
        return strtolower(trim($value));
    }
    public static function pattern(mixed $seq): string {
        if(!is_array($seq) || !array_is_list($seq) || count($seq)<6 || count($seq)>9 || count(array_unique($seq,SORT_REGULAR))!==count($seq)) throw new HttpError(422,'INVALID_PATTERN','Selecciona de 6 a 9 puntos diferentes.');
        foreach($seq as $n) if(!is_int($n) || $n<1 || $n>9) throw new HttpError(422,'INVALID_PATTERN','Patrón inválido.');
        return implode(',',$seq);
    }
    public static function hash(string $secret): string { return password_hash($secret, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT); }
    public static function denied(): never { throw new HttpError(401,'ACCESS_DENIED','Acceso no autorizado'); }
    public function attempt(?int $uid, string $digest, string $method, string $outcome, string $reason): void {
        $this->db->run('INSERT INTO access_attempts(user_id,identifier_digest,ip_digest,method,outcome,reason_code,request_id) VALUES(?,?,?,?,?,?,?)',[$uid,$digest,$this->ip(),$method,$outcome,$reason,$this->requestId]);
    }
    public function audit(?int $actor, ?int $target, string $action): void { $this->db->run('INSERT INTO audit_events(actor_id,target_user_id,action,request_id) VALUES(?,?,?,?)',[$actor,$target,$action,$this->requestId]); }
    public function limited(string $digest, string $method, callable $fn): mixed {
        // Locks serialize verification across sessions/modalities, including nonexistent accounts.
        $locks=['ada:'.substr($digest,0,55),'ada:'.substr($this->ip(),0,55)]; sort($locks); $held=[];
        try {
            foreach(array_unique($locks) as $lock) {
                if((int)$this->db->run('SELECT GET_LOCK(?,2)',[$lock])->fetchColumn()!==1) throw new HttpError(429,'RATE_LIMITED','Demasiados intentos. Espera unos minutos.');
                $held[]=$lock;
            }
            $count=(int)$this->db->run("SELECT COUNT(*) FROM access_attempts WHERE (identifier_digest=? OR ip_digest=?) AND outcome='failure' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)",[$digest,$this->ip()])->fetchColumn();
            $requests=(int)$this->db->run('SELECT COUNT(*) FROM access_attempts WHERE ip_digest=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE)',[$this->ip()])->fetchColumn();
            if($count>=5 || $requests>=40) { $this->attempt(null,$digest,$method,'limited','RATE_LIMIT'); throw new HttpError(429,'RATE_LIMITED','Demasiados intentos. Espera 10 minutos.'); }
            return $fn();
        } finally { foreach(array_reverse($held) as $lock) $this->db->run('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
}
