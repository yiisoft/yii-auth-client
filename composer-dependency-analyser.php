<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/resources', isDev: false)
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // Virtual "-implementation" meta-packages exist only to declare a contract, fulfilled via
    // another package's "provide"; they never ship real code, so they always show as "unused".
    ->ignoreErrorsOnPackages(
        [
            'psr/http-factory-implementation',
            'psr/http-message-implementation',
            'psr/simple-cache-implementation',
        ],
        [ErrorType::UNUSED_DEPENDENCY],
    );
