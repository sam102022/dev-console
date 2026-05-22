<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use App\Kernel;

$kernel = new Kernel();
$kernel->handleIndex();
