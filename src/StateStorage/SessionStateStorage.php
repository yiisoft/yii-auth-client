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
    /**
     * @var SessionInterface session object to be used.
     *
     * After the SessionStateStorage object is created, if you want to change this property,
     * you should only assign it with a session object.
     *
     * If not set - application 'session' component will be used, but only, if it is available (e.g. in web application),
     * otherwise - no session will be used and no data saving will be performed.
     */
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function set(string $key, $value): void
    {
        $this->session->set($key, $value);
    }

    public function get(string $key)
    {
        return $this->session->get($key);
    }

    public function remove(string $key): void
    {
        $this->session->remove($key);
    }
}
