<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Yiisoft\Yii\AuthClient\Collection;

final readonly class CollectionFactory
{
    public function __construct(private array $clients = [])
    {
    }

    /**
     * @param ContainerInterface $container
     * @throws InvalidArgumentException
     * @return Collection
     * @psalm-suppress MixedAssignment
     */
    public function __invoke(ContainerInterface $container): Collection
    {
        $clients = [];
        /**
         * @var string $client
         */
        foreach ($this->clients as $name => $client) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Client name must be set.');
            }
            $clients[$name] = $container->get($client);
        }
        return new Collection($clients);
    }
}
