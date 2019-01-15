<?php

namespace yii\authclient\tests;

use yii\authclient\SessionStateStorage;
use yii\authclient\tests\data\Session;

class SessionStateStorageTest extends TestCase
{
    public function testSetState()
    {
        $storage = new SessionStateStorage([
            'session' => Session::class
        ]);

        $key = 'test-key';
        $value = 'test-value';

        $storage->set($key, $value);

        $this->assertEquals($value, $storage->get($key));

        $storage->remove($key);
        $this->assertNull($storage->get($key));
    }
}
