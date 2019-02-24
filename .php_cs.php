<?php

$config = \PhpCsFixer\Config::create();
$config->setRules([
    '@PSR2' => true,
]);

$finder = \PhpCsFixer\Finder::create();
$finder->files();
$finder->in('src');

$config->setFinder($finder);
$config->setUsingCache(true);
$config->setRiskyAllowed(true);

return $config;
