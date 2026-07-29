<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use RuntimeException;

use function array_key_exists;

/**
 * Collection is a storage for all auth clients in the application.
 *
 * Example application configuration:
 *
 * ```php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'google' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Google::class,
 *             'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
 *             'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
 *         ],
 *         'facebook' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
 *             'clientId' => $_ENV['FACEBOOK_CLIENT_ID'],
 *             'clientSecret' => $_ENV['FACEBOOK_CLIENT_SECRET'],
 *         ],
 *     ],
 * ]
 * ```
 *
 * The array key (`'google'`, `'facebook'`) is used as the client's `name`.
 * Remaining keys map to setters on the client class, e.g. `clientId` → `setClientId()`.
 *
 * @see CollectionFactory
 */
final class Collection
{
    public function __construct(
        /**
         * @var array|OAuth2Interface[] list of OAuth2 clients with their configuration in format: 'clientName' => [...]
         */
        private array $clients,
    ) {}

    public function getClient(string $name): OAuth2
    {
        if (!$this->hasClient($name)) {
            throw new InvalidArgumentException("Unknown auth client '{$name}'.");
        }

        $client = $this->clients[$name];
        if (!($client instanceof OAuth2)) {
            throw new RuntimeException(
                'The Client should be an OAuth2 Interface.',
            );
        }
        return $client;
    }

    /**
     * @psalm-return array<string, OAuth2>
     */
    public function getClients(): array
    {
        $clients = [];

        /**
         * @var OAuth2 $client
         * @var string $name
         * @var array $this->clients
         */
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
