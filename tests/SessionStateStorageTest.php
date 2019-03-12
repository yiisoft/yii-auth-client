<?php

namespace yii\authclient\tests;

use PHPUnit\Framework\TestCase;
use yii\authclient\stateStorage\SessionStateStorage;
use yii\authclient\tests\data\Session;

class SessionStateStorageTest extends TestCase
{
    public function testSetState()
    {
        $storage = new SessionStateStorage(
            new Session()
        );

        $key = 'test-key';
        $value = 'test-value';

        $storage->set($key, $value);

        $this->assertEquals($value, $storage->get($key));

        $storage->remove($key);
        $this->assertNull($storage->get($key));
    }
}
