<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use RuntimeException;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\VKontakte;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

use function strlen;

use const JSON_ERROR_NONE;
use const PHP_URL_QUERY;

final class VKontakteTest extends ProviderClientTestCase
{
    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('vkontakte', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('VKontakte', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-dark', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://id.vk.ru/oauth2/auth', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('email phone', $client->getScope());
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

        $this->assertStringStartsWith('https://id.vk.ru/authorize?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testBuildAuthUrlIncludesPkceCodeChallengeMatchingStoredVerifier(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $client = $this->createVKontakteClient(stateStorage: $stateStorage);
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub());

        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method']);
        $codeVerifier = (string) (new ReflectionMethod($client, 'getState'))->invoke($client, 'codeVerifier');
        // RFC 7636 code_verifier: base64url(random_bytes(64)) without padding is always exactly 86 chars.
        $this->assertSame(86, strlen($codeVerifier));
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $query['code_challenge']);
    }

    public function testBuildAuthUrlMergesCallerSuppliedParamsWithPkceDefaults(): void
    {
        $client = $this->createVKontakteClient();
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub(), ['prompt' => 'select_account']);

        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertSame('S256', $query['code_challenge_method']);
    }

    public function testFetchAccessTokenThrowsWhenIncomingStateDoesNotMatch(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $buildClient->buildAuthUrl($this->createServerRequestStub());
        $client = $this->createVKontakteClient(stateStorage: $stateStorage);
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => 'wrong-state']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenThrowsWhenIncomingStateIsMissing(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $buildClient->buildAuthUrl($this->createServerRequestStub());
        $client = $this->createVKontakteClient(stateStorage: $stateStorage);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenThrowsWhenAuthStateWasNeverGenerated(): void
    {
        $client = $this->createVKontakteClient();
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['state' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenSendsCodeVerifierAndDeviceIdWithoutClientSecret(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setClientId('client-id');
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $codeVerifier = (string) (new ReflectionMethod($buildClient, 'getState'))->invoke($buildClient, 'codeVerifier');

        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'abc123'])),
            $capturedRequest,
        );
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setClientId('client-id');
        $client->setClientSecret('should-not-be-sent');
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state'], 'device_id' => 'the-device-id']);

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame('authorization_code', $body['grant_type']);
        $this->assertSame($codeVerifier, $body['code_verifier']);
        $this->assertSame('the-device-id', $body['device_id']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertSame('http://return.local', $body['redirect_uri']);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function testFetchAccessTokenMergesCallerSuppliedParams(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'abc123'])),
            $capturedRequest,
        );
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state']]);

        $client->fetchAccessToken($incomingRequest, 'auth-code', ['scope' => 'email']);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame('email', $body['scope']);
    }

    public function testFetchAccessTokenSendsCodeVerifierAsEmptyStringWhenStateWasNeverStored(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'abc123'])),
            $capturedRequest,
        );
        $client = $this->createVKontakteClient($httpClient)->withoutValidateAuthState();
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertArrayHasKey('code_verifier', $body);
        $this->assertSame('', $body['code_verifier']);
    }

    public function testFetchAccessTokenRemovesAuthStateAndCodeVerifierStateAfterSuccess(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => 'abc123'])));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state']]);

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNull((new ReflectionMethod($client, 'getState'))->invoke($client, 'authState'));
        $this->assertNull((new ReflectionMethod($client, 'getState'))->invoke($client, 'codeVerifier'));
    }

    public function testFetchAccessTokenThrowsWhenIncomingStateIsNotAString(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $buildClient->buildAuthUrl($this->createServerRequestStub());

        $client = $this->createVKontakteClient(stateStorage: $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        // A malformed `state[]=...` query is not a string and must be rejected outright rather than
        // silently skipping the comparison against the stored auth state.
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => ['unexpected']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth state parameter.');

        $client->fetchAccessToken($incomingRequest, 'auth-code');
    }

    public function testFetchAccessTokenQueryStateTakesPriorityOverBodyState(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => 'abc123'])));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        // Correct state is in the query; body carries a different, wrong value.
        // If body took priority (as a mutant would), this would throw instead of succeeding.
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state']])
            ->withParsedBody(['state' => 'wrong-body-state']);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
    }

    public function testFetchAccessTokenCastsScalarJsonResponseToArray(): void
    {
        // A JSON-scalar body (not an object) exercises the `(array)` cast around json_decode(): without
        // it, the later `$output['device_id'] ??= $deviceId` write would throw on a non-array scalar.
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], '5'));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state'], 'device_id' => 'the-device-id']);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame([5, 'device_id' => 'the-device-id'], $token->getParams());
    }

    public function testFetchAccessTokenKeepsResponseSuppliedDeviceIdOverCallbackDeviceId(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(
            200,
            [],
            (string) json_encode(['access_token' => 'abc123', 'device_id' => 'response-device-id']),
        ));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state'], 'device_id' => 'callback-device-id']);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('response-device-id', $token->getParam('device_id'));
    }

    public function testFetchAccessTokenPersistsAccessTokenOnClient(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => 'abc123'])));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state']]);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame($token, $client->getAccessToken());
    }

    public function testFetchAccessTokenPersistsDeviceIdOnToken(): void
    {
        $stateStorage = new SessionStateStorage(new Session());
        $buildClient = $this->createVKontakteClient(stateStorage: $stateStorage);
        $buildClient->setClientId('client-id');
        $buildClient->setOauth2ReturnUrl('http://return.local');
        $authUrl = $buildClient->buildAuthUrl($this->createServerRequestStub());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => 'abc123'])));
        $client = $this->createVKontakteClient($httpClient, $stateStorage);
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');
        $incomingRequest = (new Psr17Factory())
            ->createServerRequest('GET', 'http://return.local')
            ->withQueryParams(['code' => 'auth-code', 'state' => $query['state'], 'device_id' => 'the-device-id']);

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('the-device-id', $token->getParam('device_id'));
    }

    public function testRefreshAccessTokenSendsDeviceIdFromTokenWithoutClientSecret(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'new-token'])),
            $capturedRequest,
        );
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $client->setClientSecret('should-not-be-sent');
        $expiredToken = new OAuthToken();
        $expiredToken->setParams([
            'access_token' => 'old-token',
            'refresh_token' => 'the-refresh-token',
            'device_id' => 'the-device-id',
        ]);

        $client->refreshAccessToken($expiredToken);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('the-refresh-token', $body['refresh_token']);
        $this->assertSame('the-device-id', $body['device_id']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertNotSame('', $body['state']);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function testRefreshAccessTokenPreservesDeviceIdWhenResponseOmitsIt(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => 'new-token'])));
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $expiredToken = new OAuthToken();
        $expiredToken->setParams([
            'access_token' => 'old-token',
            'refresh_token' => 'the-refresh-token',
            'device_id' => 'the-device-id',
        ]);

        $newToken = $client->refreshAccessToken($expiredToken);

        $this->assertSame('new-token', $newToken->getToken());
        $this->assertSame('the-device-id', $newToken->getParam('device_id'));
    }

    public function testRefreshAccessTokenKeepsResponseSuppliedDeviceIdOverTokenDeviceId(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(
            200,
            [],
            (string) json_encode(['access_token' => 'new-token', 'device_id' => 'response-device-id']),
        ));
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $expiredToken = new OAuthToken();
        $expiredToken->setParams([
            'access_token' => 'old-token',
            'refresh_token' => 'the-refresh-token',
            'device_id' => 'old-device-id',
        ]);

        $newToken = $client->refreshAccessToken($expiredToken);

        $this->assertSame('response-device-id', $newToken->getParam('device_id'));
    }

    public function testRefreshAccessTokenSendsEmptyDeviceIdAndRefreshTokenWhenTokenLacksThem(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'new-token'])),
            $capturedRequest,
        );
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $expiredToken = new OAuthToken();
        $expiredToken->setParams(['access_token' => 'old-token']);

        $client->refreshAccessToken($expiredToken);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertArrayHasKey('device_id', $body);
        $this->assertSame('', $body['device_id']);
        $this->assertArrayHasKey('refresh_token', $body);
        $this->assertSame('', $body['refresh_token']);
    }

    public function testRefreshAccessTokenCastsScalarJsonResponseToArray(): void
    {
        // A JSON-scalar body (not an object) exercises the `(array)` cast around json_decode(): without
        // it, the later `$output['device_id'] ??= $deviceId` write would throw on a non-array scalar.
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], '5'));
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $expiredToken = new OAuthToken();
        $expiredToken->setParams([
            'access_token' => 'old-token',
            'refresh_token' => 'the-refresh-token',
            'device_id' => 'the-device-id',
        ]);

        $newToken = $client->refreshAccessToken($expiredToken);

        $this->assertSame([5, 'device_id' => 'the-device-id'], $newToken->getParams());
    }

    public function testStep7TokenInvalidationReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $httpClient = $this->createStub(ClientInterface::class);
        $requestFactory = new Psr17Factory();

        $result = $client->step7TokenInvalidationWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame([], $result);
    }

    public function testStep8ObtainingUserDataReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $httpClient = $this->createStub(ClientInterface::class);
        $requestFactory = new Psr17Factory();

        $result = $client->step8ObtainingUserDataArrayWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame([], $result);
    }

    public function testStep8ObtainingUserDataDecodesResponseBody(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturn(new Response(200, [], json_encode(['user' => ['user_id' => '123']])));
        $requestFactory = new Psr17Factory();

        $result = $client->step8ObtainingUserDataArrayWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame(['user' => ['user_id' => '123']], $result);
    }

    /**
     * initUserAttributes() must stay protected so subclasses can override it; the return value alone
     * can't distinguish protected from private, so this also asserts visibility.
     */
    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesUnwrapsUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturn(new Response(200, [], json_encode(['user' => ['user_id' => '123', 'first_name' => 'Ivan']])));
        $client = $this->createVKontakteClient($httpClient);
        $client->setClientId('client-id');
        $client->setAccessToken(['params' => ['access_token' => 'the-token']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['user_id' => '123', 'first_name' => 'Ivan'], $method->invoke($client));
    }

    public function testStep9GetPublicUserDataDecodesResponseBody(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturn(new Response(200, [], json_encode(['user' => ['user_id' => '123']])));
        $requestFactory = new Psr17Factory();

        $result = $client->step9GetPublicUserDataArrayWithClientId('client-id', '123', $httpClient, $requestFactory);

        $this->assertSame(['user' => ['user_id' => '123']], $result);
    }

    /**
     * An empty response body must return [] without a decoding error: {@see Json::decode()} special-cases
     * the empty string and returns null without ever calling the underlying json_decode(), so
     * json_last_error() stays untouched.
     */
    public function testStep8ObtainingUserDataDoesNotDecodeEmptyResponseBody(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], ''));
        $requestFactory = new Psr17Factory();
        json_decode('null'); // reset json_last_error() to JSON_ERROR_NONE

        $result = $client->step8ObtainingUserDataArrayWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame([], $result);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testStep9GetPublicUserDataDoesNotDecodeEmptyResponseBody(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], ''));
        $requestFactory = new Psr17Factory();
        json_decode('null'); // reset json_last_error() to JSON_ERROR_NONE

        $result = $client->step9GetPublicUserDataArrayWithClientId('client-id', '123', $httpClient, $requestFactory);

        $this->assertSame([], $result);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testStep6ReturnsErrorArrayOnFailureStatus(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(400, [], '', '1.1', 'Bad Request'));
        $requestFactory = new Psr17Factory();

        $result = $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'refresh-token',
            'client-id',
            'device-id',
            'state',
            $httpClient,
            $requestFactory,
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testStep6DecodesSuccessResponseBody(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturn(new Response(200, [], json_encode(['access_token' => 'new-token'])));
        $requestFactory = new Psr17Factory();

        $result = $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'refresh-token',
            'client-id',
            'device-id',
            'state',
            $httpClient,
            $requestFactory,
        );

        $this->assertSame(['access_token' => 'new-token'], $result);
    }

    public function testStep6SendsGrantTypeAndOtherFieldsInRequestBody(): void
    {
        $client = $this->createVKontakteClient();
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '{}'), $capturedRequest);
        $requestFactory = new Psr17Factory();

        $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'the-refresh-token',
            'the-client-id',
            'the-device-id',
            'the-state',
            $httpClient,
            $requestFactory,
        );

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $body);
        $this->assertSame([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'the-refresh-token',
            'client_id' => 'the-client-id',
            'device_id' => 'the-device-id',
            'state' => 'the-state',
        ], $body);
    }

    public function testStep6ReturnsErrorArrayWhenHttpClientThrows(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('network failure');
            }
        };
        $requestFactory = new Psr17Factory();

        $result = $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'refresh-token',
            'client-id',
            'device-id',
            'state',
            $httpClient,
            $requestFactory,
        );

        $this->assertSame(['error' => 'Exception: network failure'], $result);
    }

    public function testStep7ReturnsEmptyArrayWhenHttpClientThrows(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('network failure');
            }
        };
        $requestFactory = new Psr17Factory();

        $result = $client->step7TokenInvalidationWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame([], $result);
    }

    public function testStep9ReturnsEmptyArrayWhenHttpClientThrows(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('network failure');
            }
        };
        $requestFactory = new Psr17Factory();

        $result = $client->step9GetPublicUserDataArrayWithClientId('client-id', '123', $httpClient, $requestFactory);

        $this->assertSame([], $result);
    }

    public function testStep6ReturnsExactErrorMessageOnFailureStatus(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(400, [], '', '1.1', 'Bad Request'));
        $requestFactory = new Psr17Factory();

        $result = $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'refresh-token',
            'client-id',
            'device-id',
            'state',
            $httpClient,
            $requestFactory,
        );

        $this->assertSame(['error' => 'Error:Bad Request'], $result);
    }

    public function testStep6ReturnsEmptyArrayOnEmptySuccessBody(): void
    {
        $client = $this->createVKontakteClient();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], ''));
        $requestFactory = new Psr17Factory();

        $result = $client->step6GettingNewAccessTokenAfterPreviousExpires(
            'refresh-token',
            'client-id',
            'device-id',
            'state',
            $httpClient,
            $requestFactory,
        );

        $this->assertSame([], $result);
    }

    public function testStep7DecodesResponseBodyWithAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturn(new Response(200, [], json_encode(['response' => 1])));
        $requestFactory = new Psr17Factory();

        $result = $client->step7TokenInvalidationWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame(['response' => 1], $result);
    }

    public function testStep7DoesNotSendRequestWithoutAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $callCount = 0;
        $httpClient = $this->httpClientCountingCalls(new Response(200, [], '{}'), $callCount);
        $requestFactory = new Psr17Factory();

        $client->step7TokenInvalidationWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame(0, $callCount);
    }

    public function testStep8DoesNotSendRequestWithoutAccessToken(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $callCount = 0;
        $httpClient = $this->httpClientCountingCalls(new Response(200, [], '{}'), $callCount);
        $requestFactory = new Psr17Factory();

        $client->step8ObtainingUserDataArrayWithClientId($token, 'client-id', $httpClient, $requestFactory);

        $this->assertSame(0, $callCount);
    }

    public function testStep7RequestsExpectedUrl(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '{}'), $capturedRequest);
        $requestFactory = new Psr17Factory();

        $client->step7TokenInvalidationWithClientId($token, 'the-client-id', $httpClient, $requestFactory);

        $this->assertNotNull($capturedRequest);
        $uri = (string) $capturedRequest->getUri();
        $this->assertStringStartsWith('https://id.vk.ru/oauth2/logout?', $uri);
        $this->assertSame('the-client-id', RequestUtil::getParams($capturedRequest)['client_id']);
        $this->assertSame('the-token', RequestUtil::getParams($capturedRequest)['access_token']);
    }

    public function testStep8RequestsExpectedUrl(): void
    {
        $client = $this->createVKontakteClient();
        $token = new OAuthToken();
        $token->setParam('access_token', 'the-token');
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '{}'), $capturedRequest);
        $requestFactory = new Psr17Factory();

        $client->step8ObtainingUserDataArrayWithClientId($token, 'the-client-id', $httpClient, $requestFactory);

        $this->assertNotNull($capturedRequest);
        $uri = (string) $capturedRequest->getUri();
        $this->assertStringStartsWith('https://id.vk.ru/oauth2/user_info?', $uri);
        $this->assertSame('the-client-id', RequestUtil::getParams($capturedRequest)['client_id']);
        $this->assertSame('the-token', RequestUtil::getParams($capturedRequest)['access_token']);
    }

    public function testStep9RequestsExpectedUrl(): void
    {
        $client = $this->createVKontakteClient();
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '{}'), $capturedRequest);
        $requestFactory = new Psr17Factory();

        $client->step9GetPublicUserDataArrayWithClientId('the-client-id', 'the-user-id', $httpClient, $requestFactory);

        $this->assertNotNull($capturedRequest);
        $uri = (string) $capturedRequest->getUri();
        $this->assertStringStartsWith('https://id.vk.ru/oauth2/user_info?', $uri);
        $this->assertSame('the-client-id', RequestUtil::getParams($capturedRequest)['client_id']);
        $this->assertSame('the-user-id', RequestUtil::getParams($capturedRequest)['user_id']);
    }

    protected function createClient(): OAuth2
    {
        return $this->instantiate(VKontakte::class);
    }

    private function createVKontakteClient(?ClientInterface $httpClient = null, ?StateStorageInterface $stateStorage = null): VKontakte
    {
        if ($httpClient === null && $stateStorage === null) {
            return $this->instantiate(VKontakte::class);
        }

        return new VKontakte(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            $stateStorage ?? new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }

    private function httpClientCapturing(ResponseInterface $response, ?RequestInterface &$capturedRequest): ClientInterface
    {
        return new class ($response, $capturedRequest) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response, private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return $this->response;
            }
        };
    }

    private function httpClientCountingCalls(ResponseInterface $response, int &$callCount): ClientInterface
    {
        return new class ($response, $callCount) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response, private int &$callCount) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                return $this->response;
            }
        };
    }
}
