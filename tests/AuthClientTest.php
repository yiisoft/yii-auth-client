<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use Yiisoft\Yii\AuthClient\AuthClient;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

#[AllowMockObjectsWithoutExpectations]
final class AuthClientTest extends TestCase
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
    private function createClient()
    {
        $httpClient = $this->createStub(ClientInterface::class);

        return $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getButtonClass', 'getClientId'])
            ->getMock();
    }

    public function testCreateRequestIsPubliclyCallable(): void
    {
        $request = $this->createClient()->createRequest('GET', 'http://example.com/');

        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function testSetRequestFactoryReplacesFactoryUsedByGetRequestFactory(): void
    {
        $client = $this->createClient();
        $newFactory = new Psr17Factory();

        $client->setRequestFactory($newFactory);

        $this->assertSame($newFactory, $client->getRequestFactory());
    }

    public function testGetNormalizeUserAttributeMapFallsBackToDefaultWhenEmpty(): void
    {
        $client = $this->createClient();

        $this->assertSame([], $client->getNormalizeUserAttributeMap());
    }

    /**
     * defaultNormalizeUserAttributeMap() must stay protected so subclasses can override it;
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testDefaultNormalizeUserAttributeMapIsProtectedAndReturnsEmptyArray(): void
    {
        $client = $this->createClient();
        $method = new ReflectionMethod($client, 'defaultNormalizeUserAttributeMap');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    /**
     * defaultViewOptions() must stay protected so subclasses (e.g. Google, GitHub) can override it;
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testDefaultViewOptionsIsProtectedAndReturnsPopupSize(): void
    {
        $client = $this->createClient();
        $method = new ReflectionMethod($client, 'defaultViewOptions');

        $this->assertTrue($method->isProtected());
        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $method->invoke($client));
    }

    /**
     * getStateKeyPrefix() must stay protected for subclass access; the return value alone can't
     * distinguish protected from private, so this also asserts visibility.
     */
    public function testGetStateKeyPrefixIsProtectedAndBuildsExpectedPrefix(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $client = $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getButtonClass', 'getClientId'])
            ->getMock();
        $client->method('getName')->willReturn('test');
        $method = new ReflectionMethod($client, 'getStateKeyPrefix');

        $this->assertTrue($method->isProtected());
        $this->assertSame($client::class . '_test_', $method->invoke($client));
    }
}
