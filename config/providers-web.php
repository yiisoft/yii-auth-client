<?php

declare(strict_types=1);

/** @var array $params */

use Yiisoft\Yii\AuthClient\AuthClientServiceProvider;

if (!(bool)$params['authClient.enabled']) {
    return [];
}

return [
    'AuthClient' => AuthClientServiceProvider::class,
];
