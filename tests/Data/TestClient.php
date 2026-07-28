<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;

/**
 * Mock for the Auth client.
 */
final class TestClient extends OAuth2
{
    protected array $viewOptions;

    protected string $endpoint = 'http://api.test.local';

    public function getName(): string
    {
        return 'test';
    }

    public function getTitle(): string
    {
        return 'Test';
    }

    public function getButtonClass(): string
    {
        return 'btn btn-primary bi';
    }

    public function getClientId(): string
    {
        return 'adfadfasdfasdfasdfasdfasdfasdfa';
    }

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        return 'http://test.local';
    }

    protected function initUserAttributes(): array
    {
        return [];
    }
}
