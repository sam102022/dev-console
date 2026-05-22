<?php

use App\Kernel;

require dirname(__DIR__) . '/autoload.php';

$kernel = new Kernel();
$kernel->handleConsole($argv);
