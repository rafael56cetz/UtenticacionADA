<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
use App\{Config,Database,Security};
date_default_timezone_set('UTC');
$root=dirname(__DIR__); $command=$argv[1] ?? 'help';
function answer(string $prompt, bool $secret=false): string {
    global $argv;
    if(!in_array('--quiet-prompts',$argv,true)) fwrite(STDOUT,$prompt);
    $s=fgets(STDIN);
    if($s===false) throw new RuntimeException('Entrada requerida.');
    return $secret?rtrim($s,"\r\n"):trim($s);
}
try {
    if($command==='setup') {
        if(is_file($root.'/.env')) throw new RuntimeException('La configuración ya existe; no se reemplaza.');
        $host=answer('Host MySQL [127.0.0.1]: ') ?: '127.0.0.1'; $port=answer('Puerto [3306]: ') ?: '3306';
        $admin=answer('Usuario para crear la base [root]: ') ?: 'root'; $password=answer('Contraseña MySQL (no se guardará): ',true);
        $name=answer('Base nueva [acceso_ada]: ') ?: 'acceso_ada';
        if(!preg_match('/^acceso_[a-z0-9_]{1,30}$/D',$name)) throw new RuntimeException('El nombre debe comenzar con acceso_ y contener letras minúsculas, números o _.');
        $pdo=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$admin,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $appPassword=bin2hex(random_bytes(24));
        $pdo->exec("CREATE USER ".$pdo->quote($name)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($appPassword));
        $pdo->exec("USE `$name`"); $pdo->exec(file_get_contents($root.'/database/schema.sql'));
        $pdo->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$name`.* TO ".$pdo->quote($name)."@'127.0.0.1'");
        $env=file_get_contents($root.'/.env.example');
        $values=['DB_HOST'=>$host,'DB_PORT'=>$port,'DB_NAME'=>$name,'DB_USER'=>$name,'DB_PASSWORD'=>$appPassword,'APP_KEY'=>base64_encode(random_bytes(32)),'TEMPLATE_KEY'=>base64_encode(random_bytes(32)),'BIOMETRIC_TOKEN'=>bin2hex(random_bytes(32))];
        foreach($values as $key=>$value) $env=preg_replace('/^'.preg_quote($key,'/').'=.*$/m',$key.'='.$value,$env);
        file_put_contents($root.'/.env',$env,LOCK_EX);
        echo "Base y configuración creadas. La cuenta de la app solo tiene SELECT/INSERT/UPDATE/DELETE.\n"; exit;
    }
    $config=new Config($root); $db=new Database($config);
    if($command==='admin:create') {
        $identifier=Security::identifier(answer('Identificador del primer administrador: ')); $name=answer('Nombre visible: ');
        $password=answer('Contraseña de recuperación (12–128 caracteres; no se registrará): ',true); $confirm=answer('Confirmar contraseña: ',true);
        if($password!==$confirm) throw new RuntimeException('Las contraseñas no coinciden. Escribe exactamente la misma en ambos campos.');
        if(strlen($password)<12) throw new RuntimeException('La contraseña es demasiado corta. Usa al menos 12 caracteres.');
        if(strlen($password)>128) throw new RuntimeException('La contraseña es demasiado larga (máximo 128 bytes en UTF-8).');
        if(mb_strlen($name)<2 || mb_strlen($name)>150) throw new RuntimeException('El nombre visible debe tener entre 2 y 150 caracteres.');
        $db->transaction(function() use($db,$identifier,$name,$password) {
            $db->run('SELECT id FROM app_locks WHERE id=1 FOR UPDATE');
            if((int)$db->run('SELECT COUNT(*) FROM users')->fetchColumn()>0) throw new RuntimeException('Ya hay usuarios. Utiliza la administración autenticada.');
            $db->run("INSERT INTO users(identifier,name,role,password_hash,webauthn_handle) VALUES(?,?,'Administrator',?,?)",[$identifier,$name,Security::hash($password),random_bytes(32)]);
        }); echo "Administrador creado. Entra mediante Recuperación y registra sus modalidades.\n";
    } elseif($command==='doctor') {
        echo 'PHP CLI '.PHP_VERSION.'; ini '.php_ini_loaded_file().PHP_EOL;
        foreach(['pdo_mysql','openssl','mbstring','sodium','fileinfo','curl'] as $ext) echo $ext.': '.(extension_loaded($ext)?'OK':'FALTA').PHP_EOL;
        echo 'DB '.$db->run('SELECT VERSION()')->fetchColumn().PHP_EOL;
        echo 'Origen '.$config->get('APP_ORIGIN').'; RP '.$config->get('WEBAUTHN_RP_ID').PHP_EOL;
    } elseif($command==='cleanup') {
        $db->run('DELETE FROM auth_challenges WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
        $db->run('DELETE FROM access_attempts WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)');
        echo "Retos antiguos e intentos de más de 30 días eliminados.\n";
    } else echo "Comandos: setup, admin:create, doctor, cleanup\n";
} catch(Throwable $e) { fwrite(STDERR,'Error: '.$e->getMessage().PHP_EOL); exit(1); }
