<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('dev/instances')
    ->exclude('dev/phpunit/unit/resources/')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
$config->setUsingCache(false);
return $config->setFinder($finder);