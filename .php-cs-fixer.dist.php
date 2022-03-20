<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude(['vendor', 'thirdpart'])
    ->in([__DIR__ . '/src', __DIR__ . '/src/phproad'])
    ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules(
    array(
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'long'],
    )
)->setLineEnding(PHP_EOL)->setFinder($finder);
