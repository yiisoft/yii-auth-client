<?php


namespace yii\authclient\stateStorage;

class DummyStateStorage implements StateStorageInterface
{

    public function set($key, $value)
    {
        // do nothing
    }

    public function get($key)
    {
        return null;
    }

    public function remove($key)
    {
        return true;
    }
}