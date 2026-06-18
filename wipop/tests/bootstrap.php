<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.0.0');
}

$moduleLoader = require dirname(__DIR__) . '/vendor/autoload.php';
$moduleLoader->unregister();
$moduleLoader->register(true);
