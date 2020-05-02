<?php

namespace Yiisoft\Yii\AuthClient\StateStorage;

use yii\base\Component;
use yii\web\Session;

/**
 * SessionStateStorage provides Auth client state storage based on web session.
 *
 * @see StateStorageInterface
 * @see Session
 */
class SessionStateStorage extends Component implements StateStorageInterface
{
    /**
     * @var Session session object to be used.
     *
     * After the SessionStateStorage object is created, if you want to change this property,
     * you should only assign it with a session object.
     *
     * If not set - application 'session' component will be used, but only, if it is available (e.g. in web application),
     * otherwise - no session will be used and no data saving will be performed.
     */
    private $session;

    public function __construct(Session $session)
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

    public function remove($key)
    {
        if ($this->session !== null) {
            $this->session->remove($key);
        }
        return true;
    }
}
