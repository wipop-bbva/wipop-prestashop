<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'var']);

$config = class_exists(\PrestaShop\CodingStandards\CsFixer\Config::class)
    ? new \PrestaShop\CodingStandards\CsFixer\Config()
    : (new PhpCsFixer\Config())->setRules(['@PSR12' => true]);

return $config->setFinder($finder);
