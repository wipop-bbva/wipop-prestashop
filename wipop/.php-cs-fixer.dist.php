<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'var']);

$config = class_exists(\PrestaShop\CodingStandards\CsFixer\Config::class)
    ? new \PrestaShop\CodingStandards\CsFixer\Config()
    : new PhpCsFixer\Config();

$rules = method_exists($config, 'getRules')
    ? $config->getRules()
    : ['@PSR12' => true];

$config->setRules(array_replace($rules, [
    'fully_qualified_strict_types' => false,
    'global_namespace_import' => [
        'import_classes' => true,
        'import_constants' => false,
        'import_functions' => false,
    ],
]));

return $config->setFinder($finder);
