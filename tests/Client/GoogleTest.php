<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use ReflectionMethod;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\Google;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GoogleTest extends ProviderClientTestCase
{
    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('google', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Google', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-primary bi bi-google', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://oauth2.googleapis.com/token', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame(
            'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
            $client->getScope(),
        );
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

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testGetCurrentUserJsonArrayReturnsDecodedResponseBody(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], (string) json_encode(['email' => 'user@example.com']));
            }
        };
        $client = $this->createGoogleClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');

        $result = $client->getCurrentUserJsonArray($token);

        $this->assertSame(['email' => 'user@example.com'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('www.googleapis.com', $capturedRequest->getHeaderLine('Host'));
        $this->assertStringStartsWith('https://www.googleapis.com/oauth2/v2/userinfo', (string) $capturedRequest->getUri());
    }

    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createGoogleClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesReturnsCurrentUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['email' => 'user@example.com'])));
        $client = $this->createGoogleClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['email' => 'user@example.com'], $method->invoke($client));
    }

    protected function createClient(): OAuth2
    {
        return $this->instantiate(Google::class);
    }

    private function createGoogleClient(?ClientInterface $httpClient = null): Google
    {
        return new Google(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }
}
