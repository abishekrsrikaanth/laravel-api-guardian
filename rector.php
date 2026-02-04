<?php

declare(strict_types=1);

// rector.php
use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/database',
        __DIR__ . '/config',
        __DIR__ . '/routes',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withRules([
        JsonThrowOnErrorRector::class
    ])
    ->withPhpSets(
        php84: true,
    );
