<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Factory;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

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

        $httpClient = $container->get(ClientInterface::class);
        $requestFactory = $container->get(RequestFactoryInterface::class);
        $stateStorage = $container->get(StateStorageInterface::class);
        $yiisoftFactory = $container->get(YiisoftFactory::class);
        $session = $container->get(SessionInterface::class);

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

            if (is_subclass_of($class, OpenIdConnect::class) || $class === OpenIdConnect::class) {
                $cache = $container->get(CacheInterface::class);
                /** @psalm-suppress UnsafeInstantiation All OAuth2 subtypes use the same 6-param signature */
                $client = new $class($httpClient, $requestFactory, $stateStorage, $yiisoftFactory, $session, $cache);
            } else {
                /** @psalm-suppress UnsafeInstantiation All OAuth2 subtypes use the same 5-param signature */
                $client = new $class($httpClient, $requestFactory, $stateStorage, $yiisoftFactory, $session);
            }

            /** @var OAuth2 $client */
            $client->setName($name);

            foreach ($config as $key => $value) {
                if (!is_string($key)) {
                    throw new InvalidArgumentException(
                        "Configuration key for client '$name' must be a string.",
                    );
                }
                /**
                 * @infection-ignore-all
                 * PHP method names are case-insensitive, so ucfirst has no observable effect here.
                 */
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
