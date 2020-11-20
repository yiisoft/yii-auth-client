<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Collection is a storage for all auth clients in the application.
 *
 * Example application configuration:
 *
 * ```php
 * 'authClients' => [
 *     'google' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Clients\Google::class,
 *         'setClientId()' => ['google_client_id'],
 *         'setClientSecret()' => ['google_client_secret'],
 *      ],
 *     'facebook' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
 *         'setClientId()' => ['facebook_client_id'],
 *         'setClientSecret()' => ['facebook_client_secret'],
 *     ]
 *     ...
 * ]
 * ```
 */
class Collection
{
    /**
     * @var array|ClientInterface[] list of Auth clients with their configuration in format: 'clientName' => [...]
     */
    private array $clients;
    private ContainerInterface $container;

    public function __construct(array $clients, ContainerInterface $container)
    {
        $this->clients = $clients;
        $this->container = $container;
    }

    /**
     * @return ClientInterface[] list of auth clients.
     */
    public function getClients(): array
    {
        $clients = [];
        foreach ($this->clients as $name => $client) {
            $clients[$name] = $this->getClient($name);
        }

        return $clients;
    }

    /**
     * @param array $clients list of auth clients indexed by their names
     */
    public function setClients(array $clients): void
    {
        $this->clients = $clients;
    }

    /**
     * @param string $name client name
     *
     * @throws InvalidArgumentException on non existing client request.
     *
     * @return ClientInterface auth client instance.
     */
    public function getClient(string $name): ClientInterface
    {
        if (!$this->hasClient($name)) {
            throw new InvalidArgumentException("Unknown auth client '{$name}'.");
        }

        $client = $this->clients[$name];
        if (is_string($client)) {
            $client = $this->container->get($client);
        } elseif ($client instanceof ClientInterface) {
            return $client;
        } elseif (is_object($client) && method_exists($client, '__invoke')) {
            $client = $client($this->container);
        }
        if (!($client instanceof ClientInterface)) {
            throw new RuntimeException(
                'Client should be ClientInterface instance. "' . get_class($client) . '" given.'
            );
        }
        return $client;
    }

    /**
     * Checks if client exists in the hub.
     *
     * @param string $name client id.
     *
     * @return bool whether client exist.
     */
    public function hasClient(string $name): bool
    {
        return array_key_exists($name, $this->clients);
    }
}
