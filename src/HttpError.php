<?php
declare(strict_types=1);
namespace App;
final class HttpError extends \RuntimeException {
    public function __construct(public readonly int $status, public readonly string $publicCode, string $message) { parent::__construct($message); }
}
