<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in('app');

$rules = [
    '@PHP7x1Migration' => true,
    '@PSR12'           => true,
    '@PhpCsFixer'      => true
];

$config = new PhpCsFixer\Config();

return $config->setRiskyAllowed(true)->setRules($rules)->setFinder($finder);
