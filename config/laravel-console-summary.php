<?php

declare(strict_types=1);

return [
    // commands hidden in the summary that is shown when Papdy is called without a command
    'hide' => [
        'list',
    ],

    // the package falls back to the script name only when this key is missing, which is empty in the binaries
    'binary' => 'papdy',
];
