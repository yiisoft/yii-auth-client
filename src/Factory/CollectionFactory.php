<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

use function is_string;

final readonly class CollectionFactory
{
    public function __construct(private array $clients = []) {}

    /**
     * @param ContainerInterface $container
     * @throws InvalidArgumentException
     * @return Collection
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
            /** @var OAuth2Interface $resolvedClient */
            $resolvedClient = $container->get($client);
            $clients[$name] = $resolvedClient;
        }
        return new Collection($clients);
    }
}
