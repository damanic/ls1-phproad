<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__ . '/src')
    ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules(
    array(
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'long'],
    )
)->setLineEnding(PHP_EOL)->setFinder($finder);
