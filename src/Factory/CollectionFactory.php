<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use Psr\Container\ContainerInterface;
use Yiisoft\Yii\AuthClient\Collection;

class CollectionFactory
{
    public function __construct(private array $clients = [])
    {
    }

    public function __invoke(ContainerInterface $container): Collection
    {
        $clients = [];
        foreach ($this->clients as $name => $client) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Client name must be set.');
            }
            $clients[$name] = $container->get($client);
        }
        return new Collection($clients);
    }
}
