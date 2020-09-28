<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use Psr\Container\ContainerInterface;
use Yiisoft\Yii\AuthClient\Collection;

class CollectionFactory
{
    private array $clients;

    public function __construct(array $clients = [])
    {
        $this->clients = $clients;
    }

    public function __invoke(ContainerInterface $container)
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
