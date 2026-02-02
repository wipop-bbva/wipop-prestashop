<?php

declare(strict_types=1);

$prestashopRoot = getenv('_PS_ROOT_DIR_');

if ($prestashopRoot === false || $prestashopRoot === '') {
    fwrite(STDERR, "Missing _PS_ROOT_DIR_ env var. usuarlly /var/www/html under prestashop-core\n");
    exit(1);
}

$prestashopRoot = rtrim($prestashopRoot, DIRECTORY_SEPARATOR);

if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', $prestashopRoot);
}

if (!is_file($prestashopRoot . '/config/settings.inc.php')) {
    fwrite(STDERR, "Invalid _PS_ROOT_DIR_: cannot find");
    exit(1);
}

$files = [
    $prestashopRoot . '/config/defines.inc.php',
    $prestashopRoot . '/config/autoload.php',
    $prestashopRoot . '/vendor/autoload.php',
];

foreach ($files as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}
