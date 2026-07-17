<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Factory;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Factory\CollectionFactory;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Container;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

final class CollectionFactoryTest extends TestCase
{
    private function createTestClient(): TestClient
    {
        return new TestClient(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }

    public function testInvokeBuildsCollectionFromContainer(): void
    {
        $client = $this->createTestClient();
        $factory = new CollectionFactory(['testClient' => TestClient::class]);
        $container = new Container([TestClient::class => $client]);

        $collection = $factory($container);

        $this->assertTrue($collection->hasClient('testClient'));
        $this->assertSame($client, $collection->getClient('testClient'));
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
        $container = new Container([TestClient::class => $this->createTestClient()]);

        $this->expectException(InvalidArgumentException::class);

        $factory($container);
    }
}
