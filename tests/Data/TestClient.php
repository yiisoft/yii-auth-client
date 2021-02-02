<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\AuthClient;

/**
 * Mock for the Auth client.
 */
final class TestClient extends AuthClient
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
