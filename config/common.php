<?php

declare(strict_types=1);

/** @var array $params */

use Psr\Container\ContainerInterface;
use Yiisoft\Yii\AuthClient\Collection;

return [
    Collection::class => static function (ContainerInterface $container) use ($params) {
        return new Collection($params['authClients'], $container);
    },
];
