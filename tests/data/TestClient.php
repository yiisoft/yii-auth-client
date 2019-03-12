<?php

namespace yii\authclient\tests\data;

use yii\authclient\BaseClient;

/**
 * Mock for the Auth client.
 */
class TestClient extends BaseClient
{
    /**
     * {@inheritdoc}
     */
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
