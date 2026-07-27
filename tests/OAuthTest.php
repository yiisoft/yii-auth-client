<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use Yiisoft\Yii\AuthClient\OAuth;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Exception;

#[AllowMockObjectsWithoutExpectations]
final class OAuthTest extends TestCase
{
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

        $this->expectException(Exception::class);
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
            new Response(200, [], 'access_token=refreshed-token&expires_in=3600'),
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

    public function testSetScopeOverridesDefault(): void
    {
        $client = $this->createClient();
        $client->setScope('profile email');

        $this->assertSame('profile email', $client->getScope());
    }

    public function testSetYiisoftFactoryReplacesFactoryUsedByGetYiisoftFactory(): void
    {
        $client = $this->createClient();
        $newFactory = new YiisoftFactory();

        $client->setYiisoftFactory($newFactory);

        $this->assertSame($newFactory, $client->getYiisoftFactory());
    }

    public function testApiWithStringDataWritesItToRequestBody(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], '{}');
            }
        };
        $client = $this->createClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);

        $client->api('/user', 'POST', 'raw body content');

        $this->assertNotNull($capturedRequest);
        $this->assertSame('raw body content', (string) $capturedRequest->getBody());
    }

    /**
     * defaultReturnUrl() must stay protected so subclasses (e.g. OAuth2) can override it;
     * OAuth2 does override it, so a bare OAuth mock is needed to exercise the base implementation.
     */
    public function testDefaultReturnUrlIsProtectedAndCastsUriToString(): void
    {
        $client = $this->createBareOAuthClient();
        $method = new ReflectionMethod($client, 'defaultReturnUrl');
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/bare-callback');

        $this->assertTrue($method->isProtected());
        $this->assertSame('http://example.com/bare-callback', $method->invoke($client, $request));
    }

    public function testApiWithNonEmptyArrayDataAddsRequestParams(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

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
        $this->assertSame('bar', RequestUtil::getParams($capturedRequest)['foo']);
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

        $this->assertSame('abc123', RequestUtil::getParams($newRequest)['access_token']);
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

    /**
     * tokenParamKey must be both set AND a string before being applied; OAuthToken::setTokenParamKey()
     * is strictly typed `string`, so a non-string value reaching it (e.g. if `&&` became `||`) would
     * throw a TypeError instead of being silently ignored.
     */
    public function testCreateTokenIgnoresNonStringTokenParamKeyInConfig(): void
    {
        $client = $this->createBareOAuthClient();
        $method = new ReflectionMethod($client, 'createToken');

        $token = $method->invoke($client, ['tokenParamKey' => 123, 'params' => ['oauth_token' => 'abc123']]);

        $this->assertInstanceOf(OAuthToken::class, $token);
        $this->assertSame('abc123', $token->getToken());
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

    /**
     * restoreAccessToken() must stay protected so subclasses can call it (e.g. via getAccessToken());
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testRestoreAccessTokenIsProtectedAndReturnsPersistedToken(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $writer = $this->createClient(stateStorage: $stateStorage);
        $writer->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);
        $reader = $this->createClient(stateStorage: $stateStorage);
        $method = new ReflectionMethod($reader, 'restoreAccessToken');

        $this->assertTrue($method->isProtected());
        $this->assertSame('abc123', $method->invoke($reader)?->getToken());
    }

    /**
     * saveAccessToken() must stay protected so subclasses can call it (e.g. via setAccessToken());
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testSaveAccessTokenIsProtectedAndPersistsToken(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $client = $this->createClient(stateStorage: $stateStorage);
        $token = new OAuthToken();
        $token->setToken('xyz789');
        $token->setExpireDuration(3600);
        $method = new ReflectionMethod($client, 'saveAccessToken');

        $this->assertTrue($method->isProtected());
        $method->invoke($client, $token);

        $this->assertSame('xyz789', $client->getAccessToken()?->getToken());
    }

    /**
     * getDefaultScope() must stay protected so subclasses (e.g. GitHub, Google) can override it;
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testGetDefaultScopeIsProtectedAndReturnsEmptyString(): void
    {
        $client = $this->createClient();
        $method = new ReflectionMethod($client, 'getDefaultScope');

        $this->assertTrue($method->isProtected());
        $this->assertSame('', $method->invoke($client));
    }

    /**
     * OAuth2::createToken() unconditionally overwrites tokenParamKey with a string before delegating
     * to parent::createToken(), so any OAuth2-based test double (like TestClient) never exercises
     * OAuth::createToken()'s own isset/is_string check with attacker-controlled input. This bare mock
     * of the abstract OAuth class isolates that logic directly.
     */
    private function createBareOAuthClient(): OAuth
    {
        return $this->getMockBuilder(OAuth::class)
            ->setConstructorArgs([
                $this->createStub(ClientInterface::class),
                new Psr17Factory(),
                new SessionStateStorage(new Session()),
                new YiisoftFactory(),
            ])
            ->onlyMethods(['getName', 'getTitle', 'buildAuthUrl', 'getButtonClass', 'getClientId', 'refreshAccessToken', 'applyAccessTokenToRequest'])
            ->getMock();
    }

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
            public function __construct(private readonly ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
