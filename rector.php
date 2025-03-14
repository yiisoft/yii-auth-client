<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * Rector applied to code 21/02/2025
 * by running c:\wamp64\www\yii-auth-client>php ./vendor/bin/rector process src --dry-run
 * To apply ran: Above command without --dry-run switch
 */

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpSets(php83: true);
