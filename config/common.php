<?php

declare(strict_types=1);

/** @var array $params */

use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Factory\CollectionFactory;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

return [
    Collection::class => new CollectionFactory($params['yiisoft/yii-auth-client']['clients']),
    StateStorageInterface::class => SessionStateStorage::class
];
