<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

final class OAuthTest extends TestCase
{
    private function createClient(?ClientInterface $httpClient = null, ?SessionStateStorage $stateStorage = null): TestClient
    {
        return new TestClient(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            $stateStorage ?? new SessionStateStorage(new Session()),
            new YiisoftFactory(),
            new Session(),
        );
    }

    private function httpClientReturning(ResponseInterface $response): ClientInterface
    {
        return new class ($response) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    public function testApiReturnsDecodedJsonOnSuccess(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], '{"login":"octocat"}'));
        $client = $this->createClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $result = $client->api('/user', 'GET');

        $this->assertSame(['login' => 'octocat'], $result);
    }

    public function testApiThrowsOnNonSuccessStatus(): void
    {
        $httpClient = $this->httpClientReturning(new Response(404, [], 'Not Found'));
        $client = $this->createClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $this->expectException(InvalidResponseException::class);

        $client->api('/user', 'GET');
    }

    public function testBeforeApiRequestSendThrowsWithoutAccessToken(): void
    {
        $client = $this->createClient();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid access token.');

        $client->api('/user', 'GET');
    }

    public function testSetAccessTokenFromArrayConfig(): void
    {
        $client = $this->createClient();

        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $this->assertSame('abc123', $client->getAccessToken()?->getToken());
    }

    public function testSetAccessTokenFromTokenInstance(): void
    {
        $client = $this->createClient();
        $token = new OAuthToken();
        $token->setToken('abc123');

        $client->setAccessToken($token);

        $this->assertSame($token, $client->getAccessToken());
    }

    public function testGetAccessTokenReturnsNullWhenNoneStored(): void
    {
        $client = $this->createClient();

        $this->assertNull($client->getAccessToken());
    }

    public function testGetAccessTokenRestoresPersistedTokenOnFreshInstance(): void
    {
        $session = new Session();
        $stateStorage = new SessionStateStorage($session);
        $writer = $this->createClient(stateStorage: $stateStorage);
        $writer->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $reader = $this->createClient(stateStorage: $stateStorage);

        $this->assertSame('abc123', $reader->getAccessToken()?->getToken());
    }

    public function testGetAccessTokenAutoRefreshesExpiredToken(): void
    {
        $session = new Session();
        $stateStorage = new SessionStateStorage($session);
        $writer = $this->createClient(stateStorage: $stateStorage);
        $writer->setAccessToken(['params' => ['access_token' => 'expired-token', 'expires_in' => -3600]]);

        $refreshHttpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=refreshed-token&expires_in=3600')
        );
        $reader = $this->createClient($refreshHttpClient, $stateStorage);
        $reader->setTokenUrl('http://token.local');
        $reader->setClientSecret('secret');

        $token = $reader->getAccessToken();

        $this->assertNotNull($token);
        $this->assertSame('refreshed-token', $token->getToken());
        $this->assertFalse($token->getIsExpired());
    }

    public function testGetReturnUrlUsesDefaultRequestUriWhenNotSet(): void
    {
        $client = $this->createClient();
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $url = $client->getReturnUrl($request);

        $this->assertSame('http://example.com/callback', $url);
    }

    public function testSetReturnUrlOverridesDefault(): void
    {
        $client = $this->createClient();
        $client->setReturnUrl('http://custom.local/callback');
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $url = $client->getReturnUrl($request);

        $this->assertSame('http://custom.local/callback', $url);
    }

    public function testGetScopeReturnsEmptyStringByDefault(): void
    {
        $client = $this->createClient();

        $this->assertSame('', $client->getScope());
    }

    public function testApiWithNonEmptyArrayDataAddsRequestParams(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest)
            {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], '{}');
            }
        };
        $client = $this->createClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $client->api('/user', 'GET', ['foo' => 'bar']);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('bar', \Yiisoft\Yii\AuthClient\RequestUtil::getParams($capturedRequest)['foo']);
    }

    public function testApiThrowsWithExactFailureMessage(): void
    {
        $httpClient = $this->httpClientReturning(new Response(404, [], 'Not Found Body'));
        $client = $this->createClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Request failed with code: 404, message: Not Found Body');

        $client->api('/user', 'GET');
    }

    public function testCreateApiRequestIsPubliclyCallable(): void
    {
        $client = $this->createClient();

        $request = $client->createApiRequest('GET', '/user');

        $this->assertSame('http://api.test.local/user', (string) $request->getUri());
    }

    public function testBeforeApiRequestSendIsPubliclyCallable(): void
    {
        $client = $this->createClient();
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);
        $request = (new Psr17Factory())->createRequest('GET', 'http://api.test.local/user');

        $newRequest = $client->beforeApiRequestSend($request);

        $this->assertSame('abc123', \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest)['access_token']);
    }

    public function testSetAccessTokenWithTokenInstancePersistsToState(): void
    {
        $session = new Session();
        $stateStorage = new SessionStateStorage($session);
        $writer = $this->createClient(stateStorage: $stateStorage);
        $token = new OAuthToken();
        $token->setToken('abc123');
        $token->setExpireDuration(3600);

        $writer->setAccessToken($token);

        $reader = $this->createClient(stateStorage: $stateStorage);
        $this->assertSame('abc123', $reader->getAccessToken()?->getToken());
    }

    public function testSetAccessTokenIgnoresNonArrayParamsInConfig(): void
    {
        $client = $this->createClient();

        $client->setAccessToken(['params' => 'not-an-array']);

        $this->assertSame([], $client->getAccessToken()?->getParams());
    }

    public function testStateIsPersistedUnderClassAndNamePrefixedKey(): void
    {
        $session = new Session();
        $client = $this->createClient(stateStorage: new SessionStateStorage($session));
        $token = new OAuthToken();
        $token->setToken('abc123');

        $client->setAccessToken($token);

        $this->assertArrayHasKey(TestClient::class . '_test_token', $session->all());
    }
}
