<?php

/*
 * Additional rules or rules to override.
 * These rules will be added to default rules or will override them if the same key already exists.
 */
$additionalRules = [];

$config = PhpCsFixer\Config::create();
$config->setRules(Facile\CodingStandards\Rules::getRules($additionalRules));

$config->setUsingCache(false);
$config->setRiskyAllowed(false);

$finder = PhpCsFixer\Finder::create();
$finder->in(array (
  0 => 'src/',
));

$config->setFinder($finder);

return $config;
