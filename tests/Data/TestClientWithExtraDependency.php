<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

/**
 * Mock for a third-party auth client whose constructor requires a dependency beyond the fixed set every
 * built-in client happens to share, used to prove client instantiation still autowires such clients
 * instead of assuming a fixed positional constructor signature.
 */
final class TestClientWithExtraDependency extends OAuth2
{
    protected string $endpoint = 'http://api.test.local';

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage,
        YiisoftFactory $factory,
        SessionInterface $session,
        public readonly ExtraDependencyInterface $extraDependency,
    ) {
        parent::__construct($httpClient, $requestFactory, $stateStorage, $factory, $session);
    }

    public function getName(): string
    {
        return $this->name ?: 'test-with-extra-dependency';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Test With Extra Dependency';
    }

    public function getButtonClass(): string
    {
        return '';
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
