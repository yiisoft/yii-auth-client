<?php

/** @var array $params */

use Yiisoft\Yii\AuthClient\AuthClientServiceProvider;

if (!(bool)$params['authClient.enabled']) {
    return [];
}

return [
    'AuthClient' => AuthClientServiceProvider::class
];
