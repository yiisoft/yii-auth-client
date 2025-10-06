<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\AuthClient;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class ClientTestCase extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    /**
     * Creates test OAuth client instance.
     *
     * @return AuthClient oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        return $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl'])
            ->getMock();
    }

    /**
     * @depends testSetGet
     */
    public function testCreateRequest(): void
    {
        $request = $this->createClient()->createRequest('GET', 'http://example.com/');
        $this->assertInstanceOf(RequestInterface::class, $request);
    }
}
