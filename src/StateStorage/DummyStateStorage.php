<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\StateStorage;

class DummyStateStorage implements StateStorageInterface
{
    public function set(string $key, $value): void
    {
        // do nothing
    }

    public function get($key)
    {
        return null;
    }

    public function remove($key): void
    {
        // do nothing
    }
}
