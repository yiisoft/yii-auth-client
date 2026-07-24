<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

/**
 * Shared instantiation helpers for the OAuth2 provider client tests.
 */
abstract class ProviderClientTestCase extends TestCase
{
    abstract protected function createClient(): OAuth2;

    protected function createServerRequestStub(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    /**
     * @template T of OAuth2
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function instantiate(string $class): OAuth2
    {
        return new $class(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }
}
