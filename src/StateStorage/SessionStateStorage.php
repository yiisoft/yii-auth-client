<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\StateStorage;

use Yiisoft\Session\SessionInterface;

/**
 * SessionStateStorage provides Auth client state storage based on web session.
 *
 * @see StateStorageInterface
 * @see SessionInterface
 */
class SessionStateStorage implements StateStorageInterface
{
    public function __construct(
        /**
         * @var SessionInterface session object to be used.
         *
         * After the SessionStateStorage object is created, if you want to change this property,
         * you should only assign it with a session object.
         *
         * If not set - application 'session' component will be used, but only, if it is available (e.g. in web application),
         * otherwise - no session will be used and no data saving will be performed.
         */
        private readonly SessionInterface $session
    ) {
    }

    #[\Override]
    public function set(string $key, $value): void
    {
        $this->session->set($key, $value);
    }

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->session->get($key);
    }

    #[\Override]
    public function remove(string $key): void
    {
        $this->session->remove($key);
    }
}
