<?php

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\AbstractAuthClient;

/**
 * Mock for the Auth client.
 */
class TestClient extends AbstractAuthClient
{
    protected function initUserAttributes(): array
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

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params): string
    {
        return 'http://test.local';
    }
}
