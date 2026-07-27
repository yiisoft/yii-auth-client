<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Yiisoft\CodeStyle\Rector\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/config',
        __DIR__ . '/resources/views',
    ])
    ->withPhpSets(php82: true)
    ->withSets([
        SetList::YII_CORE,
    ]);
