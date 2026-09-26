<?php
declare(strict_types=1);
namespace App;
final class Config {
    private array $values = [];
    public function __construct(public readonly string $root, ?string $file = null) {
        $file ??= $root . '/.env';
        if (!is_file($file)) throw new \RuntimeException('CONFIG_MISSING');
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m)) $this->values[$m[1]] = trim($m[2], " \t\"'");
        }
    }
    public function get(string $key, string $default = ''): string { return $this->values[$key] ?? $default; }
    public function key(string $name): string {
        $value = base64_decode($this->get($name), true);
        if ($value === false || strlen($value) !== 32) throw new \RuntimeException('INVALID_KEY');
        return $value;
    }
    public function base(): string { return rtrim($this->get('APP_BASE_PATH'), '/'); }
    public function url(string $path): string { return $this->base() . $path; }
}
