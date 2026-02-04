<?php

declare(strict_types=1);

// rector.php
use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/database',
        __DIR__.'/config',
        __DIR__.'/routes',
    ])
    ->withRules([
        JsonThrowOnErrorRector::class,
        DeclareStrictTypesRector::class,
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
    ->withImportNames()
    ->withPhpSets(
        php84: true,
    );
