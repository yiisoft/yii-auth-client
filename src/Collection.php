<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient;

use yii\exceptions\InvalidArgumentException;

/**
 * Collection is a storage for all auth clients in the application.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'setClients()' => [
 *             'google' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Google::class,
 *                 'clientId' => 'google_client_id',
 *                 'clientSecret' => 'google_client_secret',
 *              ],
 *             'facebook' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
 *                 'clientId' => 'facebook_client_id',
 *                 'clientSecret' => 'facebook_client_secret',
 *             ],
 *         ],
 *     ]
 *     ...
 * ]
 * ```
 *
 * @property ClientInterface[] $clients List of auth clients indexed by their names. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Collection
{
    /**
     * @var array list of Auth clients with their configuration in format: 'clientName' => [...]
     */
    private $clients = [];

    /**
     * @param array $clients list of auth clients indexed by their names
     */
    public function setClients(array $clients): void
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
     * @param string $name client name
     * @return ClientInterface auth client instance.
     * @throws InvalidArgumentException on non existing client request.
     */
    public function getClient(string $name): ClientInterface
    {
        if (!array_key_exists($name, $this->clients)) {
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
