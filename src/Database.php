<?php
declare(strict_types=1);
namespace App;
use PDO;
final class Database {
    public PDO $pdo;
    public function __construct(Config $config) {
        $name = $config->get('DB_NAME');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new \RuntimeException('INVALID_DB_NAME');
        $this->pdo = new PDO('mysql:host='.$config->get('DB_HOST','127.0.0.1').';port='.$config->get('DB_PORT','3306').';dbname='.$name.';charset=utf8mb4', $config->get('DB_USER'), $config->get('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $this->pdo->exec("SET time_zone = '+00:00'");
    }
    public function run(string $sql, array $params = []): \PDOStatement { $s=$this->pdo->prepare($sql); $s->execute($params); return $s; }
    public function one(string $sql, array $params = []): ?array { return $this->run($sql,$params)->fetch() ?: null; }
    public function transaction(callable $fn): mixed {
        $this->pdo->beginTransaction();
        try { $result=$fn(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
