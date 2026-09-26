<?php
declare(strict_types=1);
use App\{Config,Kernel,HttpError};
date_default_timezone_set('UTC');
ini_set('display_errors','0');
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin'); header('X-Frame-Options: DENY');
header("Permissions-Policy: camera=(self), microphone=(self), publickey-credentials-get=(self)");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; media-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
$requestId=bin2hex(random_bytes(16)); $kernel=null;
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $config=new Config(dirname(__DIR__));
    // Once HTTPS is activated, send visits to the old local URL to the secure entry.
    // Never accept an authentication body through the old HTTP origin.
    if(str_starts_with($config->get('APP_ORIGIN'),'https://') && ($_SERVER['HTTPS'] ?? '')!=='on') {
        header('Location: '.$config->get('APP_ORIGIN').$config->base().'/',true,303); exit;
    }
    $kernel=new Kernel($config,$requestId); $kernel->run();
} catch(Throwable $e) {
    $status=$e instanceof HttpError?$e->status:503;
    $code=$e instanceof HttpError?$e->publicCode:'SERVICE_UNAVAILABLE';
    $message=$e instanceof HttpError?$e->getMessage():'El servicio no está disponible. Comprueba la instalación.';
    if($e instanceof PDOException && $e->getCode()==='23000') { $status=409; $code='CONFLICT'; $message='Identificador o credencial ya registrado.'; }
    if(!$e instanceof HttpError) error_log('ADA '.$requestId.' '.get_class($e).' code='.$e->getCode());
    $uri=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
    if(str_contains($uri,'/api/')) {
        if($kernel) $kernel->json(null,$status,$code,$message);
        http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'code'=>$code,'message'=>$message,'data'=>null,'requestId'=>$requestId]);
    } else {
        http_response_code($status); $config=$kernel?->config; $title='Error'; $user=null;
        if($config) require dirname(__DIR__).'/views/error.php';
        else echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso</title><h1>Instalación pendiente</h1><p>Completa la configuración local siguiendo el README.</p></html>';
    }
}
