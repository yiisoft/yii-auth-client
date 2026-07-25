<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Override;

/**
 * Mock for the Auth client.
 */
final class TestClient extends OAuth2
{
    protected array $viewOptions;

    protected string $endpoint = 'http://api.test.local';

    #[Override]
    public function getName(): string
    {
        return 'test';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Test';
    }

    #[Override]
    public function getButtonClass(): string
    {
        return 'btn btn-primary bi';
    }

    #[Override]
    public function getClientId(): string
    {
        return 'adfadfasdfasdfasdfasdfasdfasdfa';
    }

    #[Override]
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        return 'http://test.local';
    }

    #[Override]
    protected function initUserAttributes(): array
    {
        return [];
    }
}
