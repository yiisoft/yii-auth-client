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

    public function set($key, $value)
    {
        if ($this->session !== null) {
            $this->session->set($key, $value);
        }
    }

    public function get($key)
    {
        if ($this->session !== null) {
            return $this->session->get($key);
        }
        return null;
    }

    public function remove($key): bool
    {
        if ($this->session !== null) {
            $this->session->remove($key);
        }
        return true;
    }
}
