<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\StateStorage;

class DummyStateStorage implements StateStorageInterface
{
    #[\Override]
    public function set(string $key, $value): void
    {
        // do nothing
    }

    #[\Override]
    public function get(string $key): mixed
    {
        return null;
    }

    #[\Override]
    public function remove(string $key): void
    {
        // do nothing
    }
}
