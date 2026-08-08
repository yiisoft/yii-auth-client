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
    // "web-token/jwt-library" is a virtual package: web-token/jwt-framework declares it via
    // Composer "replace", so it provides no code of its own (always "unused") while the code it
    // stands for is shipped by web-token/jwt-framework (always a "shadow" dependency).
    ->ignoreErrorsOnPackages(['web-token/jwt-library'], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackages(['web-token/jwt-framework'], [ErrorType::SHADOW_DEPENDENCY])
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
