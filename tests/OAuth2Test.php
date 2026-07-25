<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use RuntimeException;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

use function strlen;

use const JSON_ERROR_NONE;
use const PHP_URL_QUERY;

#[AllowMockObjectsWithoutExpectations]
final class OAuth2Test extends TestCase
{
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
            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
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

    /**
     * RFC 6749 §4.1.3 requires token-endpoint parameters as an application/x-www-form-urlencoded
     * request body, not a query string on the POST URI - a strict provider like Google rejects a
     * query-string-only request outright, unlike GitHub's lenient legacy endpoint.
     */
    public function testFetchAccessTokenSendsFormUrlencodedBodyWithGrantType(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'abc123'])),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        $this->assertSame('', $capturedRequest->getUri()->getQuery());
        $this->assertSame('application/x-www-form-urlencoded', $capturedRequest->getHeaderLine('Content-Type'));
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('authorization_code', $params['grant_type']);
        $this->assertSame('auth-code', $params['code']);
        $this->assertSame('client-id', $params['client_id']);
        $this->assertSame('client-secret', $params['client_secret']);
    }

    public function testFetchAccessTokenPersistsTokenAsAccessToken(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame($token, $client->getAccessToken());
    }

    public function testFetchAccessTokenParsesDottedResponseKeys(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&custom.key=val&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('val', $token->getParam('custom.key'));
    }

    /**
     * RFC 6749 §5.1 mandates a JSON token response, and every modern provider (Google, Microsoft,
     * LinkedIn, etc.) sends one - only GitHub's legacy endpoint still defaults to the query-string
     * form covered by the other fetchAccessToken tests here.
     */
    public function testFetchAccessTokenParsesJsonResponseBody(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode([
                'access_token' => 'json-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ])),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('json-token', $token->getToken());
        $this->assertTrue($token->getIsValid());
    }

    public function testFetchAccessTokenAppliesClientCredentialsToTokenRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
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

    public function testFetchAccessTokenThrowsWhenAuthStateWasNeverGenerated(): void
    {
        $client = $this->createTestClient();
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenHandlesArrayValuedParameterRecursively(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&scope[]=read&scope[]=write&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame(['read', 'write'], $token->getParam('scope'));
    }

    /**
     * A dotted/underscored key collision inside a nested array element must be resolved the same way
     * it is at the top level: sanitizeKeys() recurses into array values to restore and de-collide their
     * keys, not just the outer array's own keys.
     */
    public function testFetchAccessTokenResolvesNestedArrayKeyCollisionViaRecursion(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&scope[a.b]=1&scope[a_b]=2&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $scope = $token->getParam('scope');
        $this->assertIsArray($scope);
        $this->assertSame('1', $scope['a.b']);
        $this->assertSame('2', $scope['a_b']);
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
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token', 'expires_in' => 3600])),
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

    public function testFetchAccessTokenWithCodeVerifierPersistsTokenAsAccessToken(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token', 'expires_in' => 3600])),
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

        $this->assertSame($token, $client->getAccessToken());
    }

    public function testFetchAccessTokenWithCodeVerifierThrowsWhenIncomingStateIsMissing(): void
    {
        $client = $this->createClient();
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenWithCodeVerifierThrowsWhenIncomingStateDoesNotMatch(): void
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

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenWithCodeVerifierThrowsWhenAuthStateWasNeverGenerated(): void
    {
        $client = $this->createTestClient();
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenWithCodeVerifierSucceedsWhenIncomingStateMatches(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
        );
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

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');

        $this->assertSame('pkce-token', $token->getToken());
    }

    public function testFetchAccessTokenWithCodeVerifierQueryStateTakesPriorityOverBodyState(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
        );
        $client = $this->createClient($httpClient);
        $client->setAuthUrl('http://auth.local');
        $client->setClientId('client-id');
        $client->setClientSecret('secret');
        $client->setTokenUrl('http://token.local');
        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class), []);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authUrlParams);
        // Correct state is in the query; body carries a different, wrong value.
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => $authUrlParams['state']])
            ->withParsedBody(['state' => 'wrong-body-state']);

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');

        $this->assertSame('pkce-token', $token->getToken());
    }

    public function testFetchAccessTokenWithCodeVerifierRemovesAuthStateAfterSuccess(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
        );
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

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code');

        $this->assertNull($client->getSessionAuthState());
    }

    public function testFetchAccessTokenWithCodeVerifierReturnsEmptyTokenWhenHttpClientThrows(): void
    {
        $httpClient = new class implements ClientInterface {
            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('network failure');
            }
        };
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', []);

        $this->assertSame([], $token->getParams());
    }

    public function testGetClientIdAndGetClientSecretReturnConfiguredValues(): void
    {
        $client = $this->getMockBuilder(OAuth2::class)
            ->setConstructorArgs([
                $this->createStub(ClientInterface::class),
                new Psr17Factory(),
                new SessionStateStorage(new Session()),
                new YiisoftFactory(),
                new Session(),
            ])
            ->onlyMethods(['getName', 'getTitle', 'getViewOptions', 'getButtonClass'])
            ->getMock();
        $client->setClientId('client-id-value');
        $client->setClientSecret('client-secret-value');

        $this->assertSame('client-id-value', $client->getClientId());
        $this->assertSame('client-secret-value', $client->getClientSecret());
    }

    /**
     * fetchCurrentUserJsonArray() must stay protected so subclasses (e.g. GitHub, Google) can call it;
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testFetchCurrentUserJsonArrayIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createTestClient();
        $token = new OAuthToken();
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client, $token, 'http://api.test.local/user'));
    }

    /**
     * getParam('access_token') is cast to string before the `=== ''` check. Without the cast, a
     * missing access_token param (null) would fail `null === ''` and fall through to actually send
     * a request, instead of short-circuiting to the empty array below.
     */
    public function testFetchCurrentUserJsonArrayDoesNotSendRequestWithoutAccessToken(): void
    {
        $callCount = 0;
        $httpClient = new class ($callCount) implements ClientInterface {
            public function __construct(private int &$callCount) {}

            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                return new Response(200, [], '{}');
            }
        };
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $result = $method->invoke($client, $token, 'http://api.test.local/user');

        $this->assertSame([], $result);
        $this->assertSame(0, $callCount);
    }

    public function testFetchCurrentUserJsonArrayCastsScalarJsonResponseToArray(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], '5'));
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $result = $method->invoke($client, $token, 'http://api.test.local/user');

        $this->assertSame([5], $result);
    }

    public function testFetchCurrentUserJsonArrayReturnsDecodedBodyWithAuthorizationHeader(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['login' => 'octocat'])),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $result = $method->invoke($client, $token, 'http://api.test.local/user');

        $this->assertSame(['login' => 'octocat'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('Bearer abc123', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testFetchCurrentUserJsonArrayUsesCustomAuthSchemeAndMergesHeaders(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['id' => 1])),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $method->invoke($client, $token, 'http://api.test.local/user', ['X-Extra' => 'yes'], 'OAuth');

        $this->assertNotNull($capturedRequest);
        $this->assertSame('OAuth abc123', $capturedRequest->getHeaderLine('Authorization'));
        $this->assertSame('yes', $capturedRequest->getHeaderLine('X-Extra'));
    }

    public function testFetchCurrentUserJsonArrayReturnsEmptyArrayOnEmptyResponseBody(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], ''));
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $this->assertSame([], $method->invoke($client, $token, 'http://api.test.local/user'));
    }

    public function testFetchCurrentUserJsonArrayReturnsEmptyArrayWhenHttpClientThrows(): void
    {
        $httpClient = new class implements ClientInterface {
            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('network failure');
            }
        };
        $client = $this->createTestClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');
        $method = new ReflectionMethod($client, 'fetchCurrentUserJsonArray');

        $this->assertSame([], $method->invoke($client, $token, 'http://api.test.local/user'));
    }

    public function testRefreshAccessTokenReturnsNewToken(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=refreshed&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->refreshAccessToken($oldToken);

        $this->assertSame('refreshed', $newToken->getToken());
    }

    public function testRefreshAccessTokenParsesJsonResponseBody(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], (string) json_encode(['access_token' => 'refreshed-json', 'expires_in' => 3600])),
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->refreshAccessToken($oldToken);

        $this->assertSame('refreshed-json', $newToken->getToken());
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
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $client->setOauth2ReturnUrl('http://return.local/callback');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('http://return.local/callback', $params['redirect_uri']);
    }

    public function testFetchAccessTokenMergesCustomParamsIntoTokenRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code', ['custom_param' => 'custom-value']);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('custom-value', $params['custom_param']);
    }

    public function testFetchAccessTokenWithCodeVerifierSendsExpectedRequestBody(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'pkce-token'])),
            $capturedRequest,
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
            $capturedRequest,
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

    /**
     * An empty response body must return [] without ever calling json_decode(): decoding an empty
     * string is invalid JSON and would leave json_last_error() set to JSON_ERROR_SYNTAX, which the
     * `> 0` vs `>= 0` boundary on strlen($body) can't otherwise be distinguished by return value alone
     * (both branches ultimately produce []).
     */
    public function testFetchAccessTokenWithCodeVerifierDoesNotDecodeEmptyResponseBody(): void
    {
        $httpClient = $this->httpClientReturning(new Response(200, [], ''));
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');
        json_decode('null'); // reset json_last_error() to JSON_ERROR_NONE

        $client->fetchAccessTokenWithCodeVerifier($incomingRequest, 'auth-code', []);

        $this->assertSame(JSON_ERROR_NONE, json_last_error());
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
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $client->refreshAccessToken($oldToken);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('refresh_token', $params['grant_type']);
    }

    public function testRefreshAccessTokenMergesOldTokenParamsIntoRequest(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=refreshed&expires_in=3600'),
            $capturedRequest,
        );
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');
        $oldToken->setParam('refresh_token', 'the-refresh-token');

        $client->refreshAccessToken($oldToken);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
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
            new Response(200, [], 'access_token=abc&custom%2Ekey=val&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('val', $token->getParam('custom.key'));
    }

    /**
     * generateAuthState() must stay protected so subclasses can call it (e.g. via buildAuthUrl());
     * the return value alone can't distinguish protected from private, so this also asserts visibility.
     */
    public function testGenerateAuthStateIsProtectedAndReturnsNonEmptyHash(): void
    {
        $client = $this->createTestClient();
        $method = new ReflectionMethod($client, 'generateAuthState');

        $this->assertTrue($method->isProtected());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $method->invoke($client));
    }

    /**
     * generateAuthState()'s return value is an opaque hash and can't reveal how its seed string was
     * assembled, so {@see OAuth2::generateAuthStateBaseString()} is tested directly, pinning the exact
     * class-name / dash / timestamp ordering.
     */
    public function testGenerateAuthStateBaseStringFormatWithoutSessionId(): void
    {
        $client = $this->createTestClient();
        $method = new ReflectionMethod($client, 'generateAuthStateBaseString');

        $this->assertTrue($method->isProtected());
        $before = time();
        $baseString = $method->invoke($client);
        $after = time();

        $this->assertMatchesRegularExpression('/^' . preg_quote($client::class, '/') . '-\d+$/', $baseString);
        $timestamp = (int) substr($baseString, strlen($client::class . '-'));
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testGenerateAuthStateBaseStringAppendsActiveSessionId(): void
    {
        $session = new class implements SessionInterface {
            #[Override]
            public function open(): void {}

            #[Override]
            public function get(string $key, $default = null)
            {
                return $default;
            }

            #[Override]
            public function set(string $key, $value): void {}

            #[Override]
            public function close(): void {}

            #[Override]
            public function isActive(): bool
            {
                return true;
            }

            #[Override]
            public function getId(): ?string
            {
                return 'the-session-id';
            }

            #[Override]
            public function regenerateId(): void {}

            #[Override]
            public function discard(): void {}

            #[Override]
            public function getName(): string
            {
                return 'sess';
            }

            #[Override]
            public function all(): array
            {
                return [];
            }

            #[Override]
            public function remove(string $key): void {}

            #[Override]
            public function has(string $key): bool
            {
                return false;
            }

            #[Override]
            public function pull(string $key, $default = '')
            {
                return $default;
            }

            #[Override]
            public function clear(): void {}

            #[Override]
            public function destroy(): void {}

            #[Override]
            public function getCookieParameters(): array
            {
                return [];
            }

            #[Override]
            public function setId(string $sessionId): void {}
        };
        $client = new TestClient(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new SessionStateStorage($session),
            new YiisoftFactory(),
            $session,
        );
        $method = new ReflectionMethod($client, 'generateAuthStateBaseString');

        $baseString = $method->invoke($client);

        $this->assertMatchesRegularExpression('/^' . preg_quote($client::class, '/') . '-\d+-the-session-id$/', $baseString);
    }

    /**
     * applyClientCredentialsToRequest() must stay protected so subclasses can call it (e.g. via
     * fetchAccessToken()); the return value alone can't distinguish protected from private, so this
     * also asserts visibility.
     */
    public function testApplyClientCredentialsToRequestIsProtectedAndAddsCredentials(): void
    {
        $client = $this->createTestClient();
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');

        $this->assertTrue($method->isProtected());
        $newRequest = $method->invoke($client, $request);

        parse_str((string) $newRequest->getBody(), $params);
        $this->assertSame('client-id', $params['client_id']);
        $this->assertSame('client-secret', $params['client_secret']);
    }

    /**
     * parse_str_clean() swaps '.', '%2E', '+', ' ' and '%20' for placeholder tokens pairwise by array
     * index before restoring them after parse_str(). Dropping '.' from the search list (but not from
     * the replace list) shifts every later pair out of alignment, so '+' ends up swapped for the "dot"
     * placeholder instead of the "space" one — restoring a literal '+' as '.' instead of ' '.
     */
    public function testFetchAccessTokenRestoresLiteralPlusAsSpaceInValue(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&name=John+Doe&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('John Doe', $token->getParam('name'));
    }

    /**
     * "a.b" and "a_b" both collide on the underscore-normalized key "a_b" once parse_str() mangles the
     * dot. sanitizeKeys()'s regex fallback resolves this collision in a specific (if imperfect) way;
     * pinning the exact resulting param set catches the several mutants in the fallback's regex and
     * match-count check that would otherwise resolve the collision differently or not at all.
     */
    public function testFetchAccessTokenResolvesDottedAndUnderscoredKeyCollision(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&a.b=1&a_b=2&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('2', $token->getParam('a.b'));
        $this->assertNull($token->getParam('a_b'));
    }

    /**
     * A mix of a dotted key, a literal-space key, an already-underscored key and a %20-encoded key
     * exercises the haystack-construction mutants in sanitizeKeys()'s regex fallback (missing leading
     * '&', trailing '&' instead, or dropping the querystring from the haystack entirely), each of which
     * would resolve this particular combination differently from the real implementation.
     */
    public function testFetchAccessTokenHandlesMultipleUnderscoreKeyVariants(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], "access_token=abc&a.b=1&a b=2&a_b=3&a%20b=4&expires_in=3600"),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('3', $token->getParam('a_b'));
        $this->assertSame('1', $token->getParam('a.b'));
        $this->assertSame('4', $token->getParam('a b'));
    }

    /**
     * $newkey is quoted with preg_quote() before being embedded in the fallback regex. A key
     * containing an unbalanced parenthesis (reachable via a %28/%29-encoded key, since native
     * parse_str() percent-decodes key content independently of this file's own placeholder swap)
     * breaks regex compilation if left unescaped, throwing a TypeError when count(array_unique(null))
     * is reached — versus a clean, well-formed pattern that simply finds no match.
     */
    public function testFetchAccessTokenPreservesKeyWithUnbalancedParenthesis(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&a%28_b=val&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('val', $token->getParam('a(_b'));
    }

    /**
     * The fallback regex haystack is built as '&' . urldecode($querystr), so a candidate key can only
     * be found if it's the *first* parameter in the raw querystring — any other parameter already has
     * a natural leading '&' from the delimiter before it, which masks a haystack built without (or
     * with a misplaced) leading '&'.
     */
    public function testFetchAccessTokenResolvesCollisionWhenDottedKeyIsFirstParam(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'a.b=1&a_b=2&access_token=abc&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('2', $token->getParam('a.b'));
        $this->assertNull($token->getParam('a_b'));
    }

    /**
     * When the same dotted key appears twice with different values, the regex fallback finds two
     * matches with identical captured text. array_unique() collapses them to a single candidate before
     * the count(...) === 1 check; without it, the (inflated) raw count would never equal 1 and the
     * "a_b" key would wrongly survive unrenamed instead of colliding into "a.b".
     */
    public function testFetchAccessTokenDeduplicatesRepeatedMatchesOfSameKey(): void
    {
        $httpClient = $this->httpClientReturning(
            new Response(200, [], 'access_token=abc&a.b=1&a.b=9&a_b=3&expires_in=3600'),
        );
        $client = $this->createTestClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('3', $token->getParam('a.b'));
        $this->assertNull($token->getParam('a_b'));
    }

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
            new Container(ContainerConfig::create()),
        );

        $session = new Session();

        $sessionStateStorage = new SessionStateStorage($session);

        return $this->getMockBuilder(OAuth2::class)
            ->setConstructorArgs(
                [$httpClient, $requestFactory, $sessionStateStorage, $yiisoftFactory, $session],
            )
            ->onlyMethods(['getName', 'getTitle', 'getViewOptions', 'getButtonClass', 'getClientId'])
            ->getMock();
    }

    private function httpClientReturning(ResponseInterface $response): ClientInterface
    {
        return new class ($response) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            #[Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function httpClientCapturing(ResponseInterface $response, ?RequestInterface &$capturedRequest): ClientInterface
    {
        return new class ($response, $capturedRequest) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response, private ?RequestInterface &$capturedRequest) {}

            #[Override]
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
}
