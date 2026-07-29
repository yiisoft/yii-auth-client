<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Factory;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;
use Yiisoft\Yii\AuthClient\Factory\CollectionFactory;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Container;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

final class CollectionFactoryTest extends TestCase
{
    public function testInvokeBuildsCollectionWithArrayConfig(): void
    {
        $container = $this->createContainer();

        $factory = new CollectionFactory([
            'test' => [
                'class' => TestClient::class,
                'title' => 'Custom Test',
            ],
        ]);

        $collection = $factory($container);

        $this->assertTrue($collection->hasClient('test'));
        $client = $collection->getClient('test');
        $this->assertSame('test', $client->getName());
        $this->assertSame('Custom Test', $client->getTitle());
    }

    public function testInvokeSetsNameFromArrayKey(): void
    {
        $container = $this->createContainer();

        $factory = new CollectionFactory([
            'my-custom-name' => [
                'class' => TestClient::class,
            ],
        ]);

        $collection = $factory($container);

        $client = $collection->getClient('my-custom-name');
        $this->assertSame('my-custom-name', $client->getName());
    }

    public function testInvokeBuildsMultipleInstancesOfSameClass(): void
    {
        $container = $this->createContainer();

        $factory = new CollectionFactory([
            'alpha' => [
                'class' => TestClient::class,
                'title' => 'Alpha',
            ],
            'beta' => [
                'class' => TestClient::class,
                'title' => 'Beta',
            ],
        ]);

        $collection = $factory($container);

        $this->assertTrue($collection->hasClient('alpha'));
        $this->assertTrue($collection->hasClient('beta'));
        $this->assertNotSame($collection->getClient('alpha'), $collection->getClient('beta'));
        $this->assertSame('Alpha', $collection->getClient('alpha')->getTitle());
        $this->assertSame('Beta', $collection->getClient('beta')->getTitle());
    }

    public function testInvokeWithEmptyClientsReturnsEmptyCollection(): void
    {
        $factory = new CollectionFactory([]);
        $container = new Container();

        $collection = $factory($container);

        $this->assertSame([], $collection->getClients());
    }

    public function testInvokeThrowsExceptionForNonStringClientName(): void
    {
        $factory = new CollectionFactory([TestClient::class]);
        $container = $this->createContainer();

        $this->expectException(InvalidArgumentException::class);

        $factory($container);
    }

    public function testInvokeThrowsExceptionForMissingClassKey(): void
    {
        $factory = new CollectionFactory([
            'test' => ['title' => 'No class here'],
        ]);
        $container = $this->createContainer();

        $this->expectException(InvalidArgumentException::class);

        $factory($container);
    }

    public function testInvokeThrowsExceptionForUnknownOption(): void
    {
        $factory = new CollectionFactory([
            'test' => [
                'class' => TestClient::class,
                'nonexistent' => 'value',
            ],
        ]);
        $container = $this->createContainer();

        $this->expectException(InvalidArgumentException::class);

        $factory($container);
    }

    public function testInvokeAppliesClientIdSetter(): void
    {
        $container = $this->createContainer();

        $factory = new CollectionFactory([
            'test' => [
                'class' => TestClient::class,
                'clientId' => 'my-client-id',
            ],
        ]);

        $collection = $factory($container);
        $client = $collection->getClient('test');
        $this->assertSame('my-client-id', $client->getClientId());
    }

    public function testInvokeThrowsExceptionForNonOAuth2Class(): void
    {
        $factory = new CollectionFactory([
            'test' => ['class' => self::class],
        ]);
        $container = $this->createContainer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a valid OAuth2 class-string');

        $factory($container);
    }

    public function testInvokeBuildsOpenIdConnectWithCache(): void
    {
        $cache = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                return ['authorization_endpoint' => 'https://issuer.example.com/authorize'];
            }

            public function set(string $key, mixed $value, mixed $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, mixed $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $container = new Container([
            ClientInterface::class => $this->createStub(ClientInterface::class),
            RequestFactoryInterface::class => $this->createStub(RequestFactoryInterface::class),
            StateStorageInterface::class => new DummyStateStorage(),
            YiisoftFactory::class => new YiisoftFactory(),
            SessionInterface::class => new Session(),
            CacheInterface::class => $cache,
        ]);

        $factory = new CollectionFactory([
            'my-oidc' => [
                'class' => OpenIdConnect::class,
                'issuerUrl' => 'https://issuer.example.com',
                'clientId' => 'oidc-client',
            ],
        ]);

        $collection = $factory($container);

        $this->assertTrue($collection->hasClient('my-oidc'));
        $client = $collection->getClient('my-oidc');
        $this->assertInstanceOf(OpenIdConnect::class, $client);
        $this->assertSame('oidc-client', $client->getClientId());
    }

    private function createContainer(): Container
    {
        return new Container([
            ClientInterface::class => $this->createStub(ClientInterface::class),
            RequestFactoryInterface::class => $this->createStub(RequestFactoryInterface::class),
            StateStorageInterface::class => new DummyStateStorage(),
            YiisoftFactory::class => new YiisoftFactory(),
            SessionInterface::class => new Session(),
        ]);
    }
}
