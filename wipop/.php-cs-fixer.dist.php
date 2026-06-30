<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'vendor-dev', 'var']);

$baseRules = class_exists(\PrestaShop\CodingStandards\CsFixer\Config::class)
    ? (new \PrestaShop\CodingStandards\CsFixer\Config())->getRules()
    : ['@PSR12' => true];

$config = (new PhpCsFixer\Config('PrestaShop coding standard'))
    ->setRiskyAllowed(true);

$config->setRules(array_replace($baseRules, [
    'class_definition' => [
        'multi_line_extends_each_single_line' => true,
        'single_line' => false,
        'single_item_single_line' => false,
    ],
    'blank_line_after_opening_tag' => false,
    'fully_qualified_strict_types' => false,
    'global_namespace_import' => false,
    'types_spaces' => [
        'space' => 'none',
        'space_multiple_catch' => 'single',
    ],
]));

return $config->setFinder($finder);
