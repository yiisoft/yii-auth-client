<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use ReflectionMethod;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\LinkedIn;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class LinkedInTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(LinkedIn::class);
    }

    private function createLinkedInClient(?ClientInterface $httpClient = null): LinkedIn
    {
        return new LinkedIn(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('linkedin', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('LinkedIn', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-info bi bi-linkedin', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://www.linkedin.com/oauth/v2/accessToken', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('openid profile email w_member_social', $client->getScope());
    }

    public function testGetViewOptions(): void
    {
        $client = $this->createClient();

        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $client->getViewOptions());
    }

    public function testBuildAuthUrlUsesAuthorizeEndpoint(): void
    {
        $client = $this->createClient();
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub());

        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testGetCurrentUserJsonArrayReturnsDecodedResponseBody(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['sub' => 'abc'])));
        $client = $this->createLinkedInClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');

        $result = $client->getCurrentUserJsonArray($token);

        $this->assertSame(['sub' => 'abc'], $result);
    }

    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createLinkedInClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesReturnsCurrentUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['sub' => 'abc'])));
        $client = $this->createLinkedInClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['sub' => 'abc'], $method->invoke($client));
    }
}
