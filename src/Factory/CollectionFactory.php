<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

use function array_key_exists;
use function is_array;
use function is_string;

final readonly class CollectionFactory
{
    public function __construct(private array $clients = []) {}

    public function __invoke(ContainerInterface $container): Collection
    {
        if ($this->clients === []) {
            return new Collection([]);
        }

        /** @var YiisoftFactory $yiisoftFactory */
        $yiisoftFactory = $container->get(YiisoftFactory::class);

        $clients = [];
        foreach ($this->clients as $name => $config) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('Client name must be a non-empty string.');
            }

            if (!is_array($config) || !isset($config['class'])) {
                throw new InvalidArgumentException(
                    "Client '$name' must be an array with a 'class' key.",
                );
            }

            /** @var mixed $class */
            $class = $config['class'];
            if (!is_string($class) || !is_a($class, OAuth2::class, true)) {
                throw new InvalidArgumentException(
                    "Client '$name' class must be a valid OAuth2 class-string.",
                );
            }
            unset($config['class']);

            if (array_key_exists('name', $config)) {
                throw new InvalidArgumentException(
                    "Client '$name' cannot set 'name' via config; it is derived from the client's array key.",
                );
            }

            /**
             * @var OAuth2 $client Built via the DI-aware factory (not $container->get()) so every
             * configured entry gets its own instance, even several entries sharing a class - and so
             * a third-party OAuth2 subclass with extra constructor dependencies is still autowired
             * correctly instead of requiring a fixed positional argument list here.
             */
            $client = $yiisoftFactory->create($class);
            $client->setName($name);

            foreach ($config as $key => $value) {
                if (!is_string($key)) {
                    throw new InvalidArgumentException(
                        "Configuration key for client '$name' must be a string.",
                    );
                }
                $setter = 'set' . ucfirst($key);
                if (!method_exists($client, $setter)) {
                    throw new InvalidArgumentException(
                        "Unknown configuration option '$key' for client '$name'. No setter method '$setter()' found on $class.",
                    );
                }
                $client->$setter($value);
            }

            $clients[$name] = $client;
        }

        return new Collection($clients);
    }
}
