<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\StateStorage;

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

    public function remove($key): bool
    {
        return true;
    }
}
