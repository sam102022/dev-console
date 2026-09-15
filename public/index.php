<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => 'dev-console',
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

require dirname(__DIR__) . '/autoload.php';

use App\Kernel;

$kernel = new Kernel();
$kernel->handleIndex();
