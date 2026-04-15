<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'var']);

$baseRules = class_exists(\PrestaShop\CodingStandards\CsFixer\Config::class)
    ? (new \PrestaShop\CodingStandards\CsFixer\Config())->getRules()
    : ['@PSR12' => true];

$config = (new PhpCsFixer\Config('PrestaShop coding standard'))
    ->setRiskyAllowed(true);

$config->setRules(array_replace($baseRules, [
    'fully_qualified_strict_types' => false,
    'global_namespace_import' => false,
    'types_spaces' => [
        'space' => 'none',
        'space_multiple_catch' => 'single',
    ],
]));

return $config->setFinder($finder);
