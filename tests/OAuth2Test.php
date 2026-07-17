<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

#[AllowMockObjectsWithoutExpectations]
final class OAuth2Test extends TestCase
{
    /**
     * Creates test OAuth2 client instance. Only the abstract methods are mocked; everything else
     * (buildAuthUrl, fetchAccessToken, state handling, etc.) runs the real OAuth2/OAuth implementation.
     *
     * @return OAuth2 oauth client.
     */
    protected function createClient(?ClientInterface $httpClient = null)
    {
        $httpClient ??= $this->createStub(ClientInterface::class);

        $requestFactory = new Psr17Factory();

        $yiisoftFactory = new YiisoftFactory(
            new Container(ContainerConfig::create())
        );

        $session = new Session();

        $sessionStateStorage = new SessionStateStorage($session);

        return $this->getMockBuilder(OAuth2::class)
            ->setConstructorArgs(
                [$httpClient, $requestFactory, $sessionStateStorage, $yiisoftFactory, $session]
            )
            ->onlyMethods(['getName', 'getTitle', 'getViewOptions', 'getButtonClass', 'getClientId'])
            ->getMock();
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

    private function httpClientCapturing(ResponseInterface $response, ?RequestInterface &$capturedRequest): ClientInterface
    {
        return new class ($response, $capturedRequest) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response, private ?RequestInterface &$capturedRequest)
            {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return $this->response;
            }
        };
    }

    private function createTestClient(?ClientInterface $httpClient = null, ?SessionStateStorage $stateStorage = null): TestClient
    {
        return new TestClient(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            $stateStorage ?? new SessionStateStorage(new Session()),
            new YiisoftFactory(),
            new Session(),
        );
    }

    // Tests :

    public function testBuildAuthUrl(): void
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $clientId = 'test_client_id';
        $oauthClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $oauthClient->setOauth2ReturnUrl($returnUrl);
        $serverRequest = $this->createStub(ServerRequestInterface::class);

        $builtAuthUrl = $oauthClient->buildAuthUrl($serverRequest, []);

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertStringContainsString(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }

    public function testFetchAccessTokenPopulatesUsableToken(): void
    {
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'access_token=abc123&token_type=bearer&expires_in=3600');
            }
        };
        $client = new TestClient(
            $httpClient,
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
        $client = $client->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
        $this->assertTrue($token->getIsValid());
    }

    public function testFetchAccessTokenParsesDottedResponseKeys(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&custom.key=val&expires_in=3600')
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('val', $token->getParam('custom.key'));
    }

    public function testFetchAccessTokenAppliesClientCredentialsToTokenRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        $params = RequestUtil::getParams($capturedRequest);
        $this->assertSame('client-id', $params['client_id']);
        $this->assertSame('client-secret', $params['client_secret']);
        $this->assertSame('auth-code', $params['code']);
    }

    public function testFetchAccessTokenThrowsWhenIncomingStateIsMissing(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenThrowsWhenIncomingStateDoesNotMatch(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => 'wrong-state']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenSucceedsWhenIncomingStateMatches(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createClient($httpClient);
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->setClientSecret('secret');
        $client->setTokenUrl('http://token.local');
        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authUrlParams);
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => $authUrlParams['state']]);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
    }

    public function testFetchAccessTokenWithCodeVerifierPopulatesToken(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token', 'expires_in' => 3600]))
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', [
            'redirect_uri' => 'http://return.local',
            'code_verifier' => 'verifier-value',
        ]);

        $this->assertSame('pkce-token', $token->getToken());
    }

    public function testRefreshAccessTokenReturnsNewToken(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=refreshed&expires_in=3600')
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->refreshAccessToken($oldToken);

        $this->assertSame('refreshed', $newToken->getToken());
    }

    public function testApplyAccessTokenToRequestAddsAccessTokenParam(): void
    {
        $client = $this->createTestClient();
        $token = new OAuthToken();
        $token->setToken('abc123');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $this->assertSame('abc123', RequestUtil::getParams($newRequest)['access_token']);
    }

    public function testWithValidateAuthStateReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $client = $this->createTestClient()->withoutValidateAuthState();

        $withState = $client->withValidateAuthState();

        $this->assertNotSame($client, $withState);
    }

    public function testWithValidateAuthStateReenablesStateRequirement(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState()->withValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $this->expectException(InvalidArgumentException::class);

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testWithoutValidateAuthStateAllowsFetchWithoutStateParam(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
    }

    public function testGetSessionAuthStateReturnsStoredState(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');

        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authUrlParams);

        $this->assertSame($authUrlParams['state'], $client->getSessionAuthState());
    }

    public function testBuildAuthUrlIncludesXoauthDisplaynameFromRequestAttribute(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn('my-app');

        $authUrl = $client->buildAuthUrl($request, []);

        $this->assertStringContainsString('xoauth_displayname=my-app', $authUrl);
    }

    public function testBuildAuthUrlMergesCustomParamsOverDefaults(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');

        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), ['extra' => 'custom-value']);

        $this->assertStringContainsString('extra=custom-value', $authUrl);
    }

    public function testFetchAccessTokenQueryStateTakesPriorityOverBodyState(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createClient($httpClient);
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->setClientSecret('secret');
        $client->setTokenUrl('http://token.local');
        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authUrlParams);
        // Correct state is in the query; body carries a different, wrong value.
        // If body took priority (as a mutant would), this would throw instead of succeeding.
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => $authUrlParams['state']])
            ->withParsedBody(['state' => 'wrong-body-state']);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
    }

    public function testFetchAccessTokenRemovesAuthStateAfterSuccess(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createClient($httpClient);
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->setClientSecret('secret');
        $client->setTokenUrl('http://token.local');
        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authUrlParams);
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => $authUrlParams['state']]);

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNull($client->getSessionAuthState());
    }

    public function testFetchAccessTokenIncludesRedirectUriInTokenRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $client->setOauth2ReturnUrl('http://return.local/callback');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        $this->assertSame('http://return.local/callback', RequestUtil::getParams($capturedRequest)['redirect_uri']);
    }

    public function testFetchAccessTokenMergesCustomParamsIntoTokenRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code', ['custom_param' => 'custom-value']);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('custom-value', RequestUtil::getParams($capturedRequest)['custom_param']);
    }

    public function testFetchAccessTokenWithCodeVerifierSendsExpectedRequestBody(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', [
            'redirect_uri' => 'http://return.local/callback',
            'code_verifier' => 'verifier-value',
        ]);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame([
            'code' => 'auth-code',
            'grant_type' => 'authorization_code',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'http://return.local/callback',
            'code_verifier' => 'verifier-value',
        ], $body);
    }

    public function testFetchAccessTokenWithCodeVerifierDefaultsMissingParamsToEmptyString(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', []);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame('', $body['redirect_uri']);
        $this->assertSame('', $body['code_verifier']);
    }

    public function testFetchAccessTokenWithCodeVerifierReturnsEmptyTokenOnEmptyResponseBody(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], ''));
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', []);

        $this->assertSame([], $token->getParams());
    }

    public function testFetchAccessTokenWithCodeVerifierCastsScalarJsonResponseToArray(): void
    {
        // A JSON-scalar body (not an object) exercises the `(array)` cast around json_decode().
        $httpClient = $this->httpClientReturning(new Response(200, [], '5'));
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', []);

        $this->assertSame([5], $token->getParams());
    }

    public function testGetOauth2ReturnUrlIsPubliclyCallable(): void
    {
        $client = $this->createTestClient();
        $client->setOauth2ReturnUrl('http://return.local');

        $this->assertSame('http://return.local', $client->getOauth2ReturnUrl());
    }

    public function testRefreshAccessTokenIncludesGrantTypeRefreshToken(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=refreshed&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $client->refreshAccessToken($oldToken);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('refresh_token', RequestUtil::getParams($capturedRequest)['grant_type']);
    }

    public function testRefreshAccessTokenMergesOldTokenParamsIntoRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=refreshed&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');
        $oldToken->setParam('refresh_token', 'the-refresh-token');

        $client->refreshAccessToken($oldToken);

        $this->assertNotNull($capturedRequest);
        $params = RequestUtil::getParams($capturedRequest);
        $this->assertSame('the-refresh-token', $params['refresh_token']);
        $this->assertSame('refresh_token', $params['grant_type']);
    }

    public function testWithoutValidateAuthStateDoesNotMutateOriginalInstance(): void
    {
        $client = $this->createTestClient();

        $withoutState = $client->withoutValidateAuthState();

        $this->assertNotSame($client, $withoutState);
        // The original client must still require state validation: fetching without a state param throws.
        $httpClient = $this->httpClientReturning(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $originalClient = $this->createTestClient($httpClient);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $this->expectException(InvalidArgumentException::class);

        $originalClient->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenRestoresUrlEncodedDottedKey(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&custom%2Ekey=val&expires_in=3600')
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('val', $token->getParam('custom.key'));
    }
}
