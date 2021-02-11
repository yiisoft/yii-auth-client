<?php

declare(strict_types=1);

/** @var array $params */

use Yiisoft\Yii\AuthClient\AuthClientServiceProvider;

if (!(bool)$params['yiisoft/yii-auth-client']['enabled']) {
    return [];
}

return [
    'yiisoft/yii-auth-client' => AuthClientServiceProvider::class,
];
