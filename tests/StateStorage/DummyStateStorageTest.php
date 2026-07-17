<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\StateStorage;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;

final class DummyStateStorageTest extends TestCase
{
    public function testGetAlwaysReturnsNull(): void
    {
        $storage = new DummyStateStorage();

        $value = $storage->get('key');

        $this->assertNull($value);
    }

    public function testSetDoesNotMakeValueRetrievable(): void
    {
        $storage = new DummyStateStorage();

        $storage->set('key', 'value');

        $this->assertNull($storage->get('key'));
    }

    public function testRemoveDoesNotThrow(): void
    {
        $storage = new DummyStateStorage();

        $storage->remove('key');

        $this->assertNull($storage->get('key'));
    }
}
