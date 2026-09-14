<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in(['app', 'tests']);

$rules = [
    '@PHP7x1Migration' => true,
    '@PSR12' => true,
    '@PhpCsFixer' => true,
    'operator_linebreak' => [
        'position' => 'end',
    ]
];

$config = new PhpCsFixer\Config();

return $config->setRiskyAllowed(true)->setRules($rules)->setFinder($finder);
