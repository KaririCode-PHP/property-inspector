<?php

declare(strict_types=1);

// KaririCode Devkit (kcode) installs the toolchain into .kcode/ and runs it
// with the configs committed at the root (ADR-013). Exact versions keep a
// gate result reproducible from the commit that produced it (§32).
return [
    'root_configs' => true,
    'tools' => [
        'phpunit' => '12.5.37',
        'phpstan' => '2.3.7',
        'psalm' => '6.19.1',
        'php-cs-fixer' => '3.95.27',
        'infection/infection' => '0.29.14',
    ],
];
