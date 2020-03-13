<?php

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Yiisoft\Yii\AuthClient\BaseClient;

/**
 * Mock for the Auth client.
 */
class TestClient extends BaseClient
{
    protected function initUserAttributes()
    {
        return [];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getTitle(): string
    {
        return 'Test';
    }
}
