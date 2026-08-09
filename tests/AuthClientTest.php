<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use Yiisoft\Yii\AuthClient\AuthClient;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class AuthClientTest extends TestCase
{
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
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getClientId'])
            ->getMock();
        $client->method('getName')->willReturn('test');
        $method = new ReflectionMethod($client, 'getStateKeyPrefix');

        $this->assertTrue($method->isProtected());
        $this->assertSame($client::class . '_test_', $method->invoke($client));
    }

    /**
     * initUserAttributes() must stay protected so subclasses (e.g. Google, GitHub) can override it;
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayByDefault(): void
    {
        $client = $this->createClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testGetUserAttributesReturnsRawAttributesWhenNormalizeMapIsEmpty(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $client = $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getClientId', 'initUserAttributes'])
            ->getMock();
        $client->method('initUserAttributes')->willReturn(['id' => '42', 'email' => 'user@example.com']);

        $this->assertSame(['id' => '42', 'email' => 'user@example.com'], $client->getUserAttributes());
    }

    public function testGetUserAttributesAppliesNormalizeUserAttributeMap(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $client = $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods([
                'getName',
                'getTitle',
                'buildAuthUrl',
                'getClientId',
                'initUserAttributes',
                'defaultNormalizeUserAttributeMap',
            ])
            ->getMock();
        $client->method('initUserAttributes')->willReturn([
            'bio' => 'Loves PHP',
            'languages' => [['name' => 'English'], ['name' => 'Spanish']],
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);
        $client->method('defaultNormalizeUserAttributeMap')->willReturn([
            'about' => 'bio',
            'language' => ['languages', 0, 'name'],
            'fullName' => static fn(array $attributes) => $attributes['firstName'] . ' ' . $attributes['lastName'],
            'missing' => 'doesNotExist',
            'missingPath' => ['languages', 99, 'name'],
        ]);

        $attributes = $client->getUserAttributes();

        $this->assertSame('Loves PHP', $attributes['about']);
        $this->assertSame('English', $attributes['language']);
        $this->assertSame('John Doe', $attributes['fullName']);
        $this->assertNull($attributes['missing']);
        $this->assertNull($attributes['missingPath']);
        // raw attributes remain available alongside the normalized ones
        $this->assertSame('Loves PHP', $attributes['bio']);
    }

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
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getClientId'])
            ->getMock();
    }
}
