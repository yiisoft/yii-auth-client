<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

final class CollectionTest extends TestCase
{
    public function testSetGet(): void
    {
        $collection = new Collection([]);

        $clients = [
            'testClient1' => $this->getTestClient(),
            'testClient2' => $this->getTestClient(),
        ];
        $collection->setClients($clients);
        $this->assertEquals($clients, $collection->getClients(), 'Unable to setup clients!');
    }

    public function testGetProviderByName(): void
    {
        $clientId = 'testClientId';
        $client = $this->getTestClient();
        $clients = [
            $clientId => $client,
        ];
        $collection = new Collection($clients);

        $this->assertEquals($client, $collection->getClient($clientId), 'Unable to get client by id!');
    }

    public function testHasProvider(): void
    {
        $clientName = 'testClientName';
        $collection = new Collection(
            [
                $clientName => $this->getTestClient(),
            ],
        );

        $this->assertTrue($collection->hasClient($clientName), 'Existing client check fails!');
        $this->assertFalse($collection->hasClient('nonExistingClientName'), 'Not existing client check fails!');
    }

    public function testGetClientThrowsForUnknownClient(): void
    {
        $collection = new Collection([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown auth client 'unknown'.");

        $collection->getClient('unknown');
    }

    public function testGetClientThrowsWhenClientIsNotOAuth2(): void
    {
        $client = $this->createStub(AuthClientInterface::class);
        $collection = new Collection(['nonOAuth2' => $client]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Client should be an OAuth2 Interface.');

        $collection->getClient('nonOAuth2');
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    private function getYiisoftFactory(): YiisoftFactory
    {
        return new YiisoftFactory();
    }

    private function getStateStorage(): StateStorageInterface
    {
        return new SessionStateStorage(new Session());
    }

    private function getTestClient(): TestClient
    {
        return new TestClient(
            $this->createStub(ClientInterface::class),
            $this->getRequestFactory(),
            $this->getStateStorage(),
            $this->getYiisoftFactory(),
            new Session(),
        );
    }
}
