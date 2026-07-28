<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\Yandex;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class YandexTest extends ProviderClientTestCase
{
    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('yandex', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Yandex', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-dark bi', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://oauth.yandex.com/token', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('login:info', $client->getScope());
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

        $this->assertStringStartsWith('https://oauth.yandex.com/authorize?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testApplyAccessTokenToRequestAddsOauthTokenAndFormat(): void
    {
        $client = $this->createClient();
        $token = new OAuthToken();
        $token->setToken('the-token');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $this->assertSame(
            ['format' => 'json', 'oauth_token' => 'the-token'],
            RequestUtil::getParams($newRequest),
        );
    }

    public function testApplyAccessTokenToRequestKeepsExistingFormat(): void
    {
        $client = $this->createClient();
        $token = new OAuthToken();
        $token->setToken('the-token');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/?format=xml');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $this->assertSame(
            ['format' => 'xml', 'oauth_token' => 'the-token'],
            RequestUtil::getParams($newRequest),
        );
    }

    public function testGetCurrentUserJsonArrayReturnsDecodedResponseBody(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], (string) json_encode(['login' => 'yandex-user']));
            }
        };
        $client = $this->createYandexClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');

        $result = $client->getCurrentUserJsonArray($token);

        $this->assertSame(['login' => 'yandex-user'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('OAuth abc123', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createYandexClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesReturnsCurrentUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['login' => 'yandex-user'])));
        $client = $this->createYandexClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['login' => 'yandex-user'], $method->invoke($client));
    }

    protected function createClient(): OAuth2
    {
        return $this->instantiate(Yandex::class);
    }

    private function createYandexClient(?ClientInterface $httpClient = null): Yandex
    {
        return new Yandex(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }
}
