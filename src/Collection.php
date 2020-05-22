<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;

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
 *         'setClientSecret' => ['google_client_secret'],
 *      ],
 *     'facebook' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
 *         'setClientId' => ['facebook_client_id'],
 *         'setClientSecret' => ['facebook_client_secret'],
 *     ]
 *     ...
 * ]
 * ```
 */
class Collection
{
    /**
     * @var ClientInterface|array list of Auth clients with their configuration in format: 'clientName' => [...]
     */
    private iterable $clients;

    public function __construct(iterable $clients = [])
    {
        $this->clients = $clients;
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
     * @return ClientInterface auth client instance.
     * @throws InvalidArgumentException on non existing client request.
     */
    public function getClient(string $name): ClientInterface
    {
        if (!$this->hasClient($name)) {
            throw new InvalidArgumentException("Unknown auth client '{$name}'.");
        }

        // TODO: support declarative syntax and callables?

        return $this->clients[$name];
    }

    /**
     * Checks if client exists in the hub.
     * @param string $name client id.
     * @return bool whether client exist.
     */
    public function hasClient(string $name): bool
    {
        return array_key_exists($name, $this->clients);
    }
}
