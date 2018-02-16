<?php

namespace yiiunit\authclient\data;

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
}