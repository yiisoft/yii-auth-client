<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Yiisoft\Di\Container;
use Yiisoft\Di\Support\ServiceProvider;
use Yiisoft\Factory\Factory;
use Yiisoft\Factory\FactoryInterface;

final class AuthClientServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        if (!$container->has(FactoryInterface::class)) {
            $container->set(FactoryInterface::class, new Factory($container));
        }
    }
}
