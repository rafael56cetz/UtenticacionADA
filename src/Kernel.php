<?php
declare(strict_types=1);
namespace App;
final class Kernel {
    public readonly Database $db;
    public readonly Security $security;
    public readonly AuthService $auth;
    public readonly AdminService $admin;
    public readonly BiometricClient $bio;
    public function __construct(public Config $config, public string $requestId) {
        $this->db=new Database($config); $this->security=new Security($config,$this->db,$requestId);
        $web=new WebAuthnService($config,$this->db); $this->bio=new BiometricClient($config);
        $this->auth=new AuthService($this->db,$this->security,$web,$this->bio);
        $this->admin=new AdminService($this->db,$this->security,$this->auth,$web,$this->bio);
    }
    public function json(mixed $data=null, int $status=200, string $code='OK', string $message=''): never {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        if($status===429) header('Retry-After: 600');
        echo json_encode(['ok'=>$status<400,'code'=>$code,'message'=>$message,'data'=>$data,'requestId'=>$this->requestId],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    }
    private function body(): array {
        if((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>28*1024*1024) throw new HttpError(413,'TOO_LARGE','La carga supera el tamaño permitido.');
        if(str_starts_with($_SERVER['CONTENT_TYPE'] ?? '','multipart/form-data')) return $_POST;
        $raw=file_get_contents('php://input',false,null,0,65537);
        if(strlen($raw)>65536) throw new HttpError(413,'TOO_LARGE','Datos demasiado grandes.');
        if($raw==='') return [];
        try { $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR); } catch(\JsonException) { throw new HttpError(400,'INVALID_JSON','JSON inválido.'); }
        if(!is_array($data) || (array_is_list($data) && $data!==[])) throw new HttpError(422,'INVALID_INPUT','Objeto de datos inválido.'); return $data;
    }
    private function payload(array $input): array {
        $raw=$input['payload'] ?? '{}';
        if(!is_string($raw) || strlen($raw)>65536) throw new HttpError(422,'INVALID_INPUT','Datos de captura inválidos.');
        try { $payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR); } catch(\JsonException) { throw new HttpError(422,'INVALID_INPUT','Datos de captura inválidos.'); }
        if(!is_array($payload)) throw new HttpError(422,'INVALID_INPUT','Datos inválidos.'); return $payload;
    }
    public function run(): never {
        $this->security->start();
        $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/'; $base=$this->config->base();
        if($base!=='' && !str_starts_with($path,$base.'/') && $path!==$base) throw new HttpError(404,'NOT_FOUND','Página no encontrada.');
        $path=substr($path,strlen($base)) ?: '/'; $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        if(str_starts_with($path,'/api/')) {
            $input=[];
            if(!in_array($method,['GET','HEAD'],true)) { $this->security->csrf(); $input=$this->body(); }
            if($path==='/api/session' && $method==='GET') {
                $user=$this->security->user();
                $this->json(['authenticated'=>$user!==null,'user'=>$user?['id'=>(int)$user['id'],'name'=>$user['name'],'role'=>$user['role'],'method_used'=>$_SESSION['method'] ?? null]:null,'csrfToken'=>$_SESSION['csrf']]);
            }
            if($path==='/api/capabilities' && $method==='GET') $this->json([...$this->bio->capabilities(),'webauthn'=>true,'pattern'=>true]);
            if($path==='/api/auth/begin' && $method==='POST') $this->json($this->auth->begin($input));
            if($path==='/api/auth/verify' && $method==='POST') $this->json($this->auth->verify($input,$this->payload($input)));
            if($path==='/api/auth/recover' && $method==='POST') $this->json($this->auth->recover($input));
            if($path==='/api/logout' && $method==='POST') { $this->security->logout(); $this->json(); }
            if($path==='/api/users' || str_starts_with($path,'/api/users/') || $path==='/api/access-attempts') $this->security->user(true,true);
            if($path==='/api/users' && $method==='GET') $this->json($this->admin->listing($_GET));
            if($path==='/api/users' && $method==='POST') $this->json($this->admin->create($input),201);
            if(preg_match('~^/api/users/([1-9][0-9]*)$~D',$path,$m)) {
                if($method==='GET') $this->json($this->admin->detail((int)$m[1]));
                if($method==='PATCH') $this->json($this->admin->update((int)$m[1],$input));
            }
            if(preg_match('~^/api/users/([1-9][0-9]*)/status$~D',$path,$m) && $method==='PATCH') $this->json($this->admin->update((int)$m[1],$input,true));
            if(preg_match('~^/api/users/([1-9][0-9]*)/recovery$~D',$path,$m) && $method==='PUT') $this->json($this->admin->password((int)$m[1],$input));
            if(preg_match('~^/api/users/([1-9][0-9]*)/enroll/(face|voice|webauthn|pattern)/(begin|finish)$~D',$path,$m) && $method==='POST') {
                $this->json($m[3]==='begin'?$this->admin->begin((int)$m[1],$m[2]):$this->admin->finish((int)$m[1],$m[2],$input,$this->payload($input)),$m[3]==='finish'?201:200);
            }
            if(preg_match('~^/api/users/([1-9][0-9]*)/credentials/([a-z]+-[1-9][0-9]*)$~D',$path,$m) && $method==='DELETE') $this->json($this->admin->revoke((int)$m[1],$m[2]));
            if($path==='/api/access-attempts' && $method==='GET') $this->json($this->admin->attempts($_GET));
            throw new HttpError(404,'NOT_FOUND','Ruta no encontrada.');
        }
        if($method!=='GET') throw new HttpError(405,'METHOD_NOT_ALLOWED','Método no permitido.');
        $user=$this->security->user(); $view=''; $title='Acceso'; $userId=null; $enrollMethod=null;
        if($user) $user=['id'=>(int)$user['id'],'name'=>$user['name'],'role'=>$user['role'],'method_used'=>$_SESSION['method'] ?? null];
        if($path==='/' || $path==='/login') { $view='auth-select'; $title='Iniciar sesión'; }
        elseif($path==='/verify') { $view='auth-verify'; $title='Verificación'; }
        elseif($path==='/recovery') { $view='recovery'; $title='Recuperación'; }
        elseif($path==='/dashboard') { $this->security->user(true); $view='dashboard'; $title='Panel'; }
        elseif(str_starts_with($path,'/admin/')) {
            $this->security->user(true,true);
            if($path==='/admin/users') { $view='admin-users'; $title='Usuarios'; }
            elseif($path==='/admin/users/new') { $view='admin-user-form'; $title='Alta de usuario'; }
            elseif($path==='/admin/attempts') { $view='admin-attempts'; $title='Intentos'; }
            elseif(preg_match('~^/admin/users/([1-9][0-9]*)(/enroll)?$~D',$path,$m)) {
                $userId=(int)$m[1]; $this->admin->find($userId);
                if(isset($m[2])) { $view='admin-enroll'; $enrollMethod=AuthService::method($_GET['method'] ?? null); $title='Registrar modalidad'; }
                else { $view='admin-user-form'; $title='Editar usuario'; }
            }
        }
        if($view==='') throw new HttpError(404,'NOT_FOUND','Página no encontrada.');
        $config=$this->config; require $config->root.'/views/'.$view.'.php'; exit;
    }
}
