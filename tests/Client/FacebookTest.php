<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use ReflectionProperty;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\Facebook;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class FacebookTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(Facebook::class);
    }

    private function createFacebookClient(?ClientInterface $httpClient = null): Facebook
    {
        return new Facebook(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
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

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('facebook', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Facebook', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-primary bi bi-facebook', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://graph.facebook.com/oauth/access_token', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('public_profile', $client->getScope());
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

        $this->assertStringStartsWith('https://www.facebook.com/dialog/oauth?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testApplyAccessTokenToRequestAddsAppSecretProof(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('the-access-token', $params['access_token']);
        $this->assertSame(hash_hmac('sha256', 'the-access-token', 'the-client-secret'), $params['appsecret_proof']);
    }

    public function testApplyAccessTokenToRequestAddsMachineIdWhenPresent(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $token->setParam('machine_id', 'the-machine-id');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('the-machine-id', $params['machine_id']);
    }

    /**
     * machine_id is cast to string before the `empty()` check. A negative-zero float is "empty" as
     * a raw value but not once cast to the string "-0", so this distinguishes the cast being applied.
     */
    public function testApplyAccessTokenToRequestCastsMachineIdToStringBeforeEmptyCheck(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $token->setParam('machine_id', -0.0);
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('-0', $params['machine_id']);
    }

    public function testGetCurrentUserJsonArrayReturnsDecodedResponseBody(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['id' => '123', 'name' => 'Jane'])),
            $capturedRequest
        );
        $client = $this->createFacebookClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');

        $result = $client->getCurrentUserJsonArray($token);

        $this->assertSame(['id' => '123', 'name' => 'Jane'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertStringStartsWith('https://graph.facebook.com/v23.0/me?fields=', (string) $capturedRequest->getUri());
    }

    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createFacebookClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesReturnsCurrentUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['id' => '123'])));
        $client = $this->createFacebookClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['id' => '123'], $method->invoke($client));
    }

    public function testFetchAccessTokenReturnsTokenUnchangedWhenAutoExchangeDisabled(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createFacebookClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('https://graph.facebook.com/oauth/access_token');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
    }

    public function testFetchAccessTokenPersistsAccessTokenWhenAutoExchangeDisabled(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], 'access_token=abc123&expires_in=3600'),
            $capturedRequest
        );
        $client = $this->createFacebookClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('https://graph.facebook.com/oauth/access_token');
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame($token, $client->getAccessToken());
    }

    public function testFetchAccessTokenExchangesTokenWhenAutoExchangeEnabled(): void
    {
        $capturedRequests = [];
        $httpClient = new class ($capturedRequests) implements ClientInterface {
            public function __construct(private array &$capturedRequests)
            {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequests[] = $request;
                if (count($this->capturedRequests) === 1) {
                    return new Response(200, [], 'access_token=short-token&expires_in=3600');
                }
                return new Response(200, [], (string) json_encode(['access_token' => 'long-lived-token', 'expires_in' => 5184000]));
            }
        };
        $client = $this->createFacebookClient($httpClient)->withoutValidateAuthState();
        $client->setTokenUrl('https://graph.facebook.com/oauth/access_token');
        $client->setClientSecret('secret');
        (new ReflectionProperty($client, 'autoExchangeAccessToken'))->setValue($client, true);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertCount(2, $capturedRequests);
        $this->assertSame('long-lived-token', $token->getToken());
    }

    public function testExchangeAccessTokenSendsExpectedParamsAndStoresDecodedToken(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'long-lived-token', 'expires_in' => 5184000])),
            $capturedRequest
        );
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->exchangeAccessToken($oldToken);

        $this->assertSame('long-lived-token', $newToken->getToken());
        $this->assertSame($newToken, $client->getAccessToken());
        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest->getMethod());
        $this->assertStringStartsWith('https://graph.facebook.com/oauth/access_token', (string) $capturedRequest->getUri());
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('fb_exchange_token', $params['grant_type']);
        $this->assertSame('old-token', $params['fb_exchange_token']);
        $this->assertSame('cid', $params['client_id']);
        $this->assertSame('csecret', $params['client_secret']);
    }

    public function testFetchClientAuthCodeReturnsStatusCodeAsStringAndSendsProvidedToken(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(201), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $result = $client->fetchClientAuthCode($incomingRequest, $token);

        $this->assertSame('201', $result);
        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('the-access-token', $params['access_token']);
        $this->assertSame('http://example.com/callback', $params['redirect_uri']);
    }

    public function testFetchClientAuthCodeUsesStoredAccessTokenWhenNoneProvided(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $client->setAccessToken(['params' => ['access_token' => 'stored-token']]);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $client->fetchClientAuthCode($incomingRequest);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('stored-token', $params['access_token']);
    }

    public function testFetchClientAuthCodeSkipsAccessTokenParamWithoutStoredToken(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $client->fetchClientAuthCode($incomingRequest);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertArrayNotHasKey('access_token', $params);
    }

    public function testFetchClientAccessTokenSendsExpectedParamsAndStoresDecodedToken(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(
            new Response(200, [], (string) json_encode(['access_token' => 'client-access-token', 'expires_in' => 3600])),
            $capturedRequest
        );
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $token = $client->fetchClientAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('client-access-token', $token->getToken());
        $this->assertSame($token, $client->getAccessToken());
        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('auth-code', $params['code']);
        $this->assertSame('cid', $params['client_id']);
        $this->assertSame('http://example.com/callback', $params['redirect_uri']);
    }

    public function testExchangeAccessTokenCastsScalarJsonResponseToArray(): void
    {
        // A JSON-scalar body (not an object) exercises the `(array)` cast around json_decode().
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '5'), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientSecret('csecret');
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->exchangeAccessToken($oldToken);

        $this->assertSame([5], $newToken->getParams());
    }

    public function testFetchClientAccessTokenCastsScalarJsonResponseToArray(): void
    {
        // A JSON-scalar body (not an object) exercises the `(array)` cast around json_decode().
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], '5'), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $token = $client->fetchClientAccessToken($incomingRequest, 'auth-code');

        $this->assertSame([5], $token->getParams());
    }

    public function testFetchClientAuthCodeMergesCallerSuppliedParamsOverDefaults(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $client->fetchClientAuthCode($incomingRequest, $token, ['extra' => 'custom-value']);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('custom-value', $params['extra']);
        $this->assertSame('the-access-token', $params['access_token']);
    }

    public function testFetchClientAccessTokenMergesCallerSuppliedParamsOverDefaults(): void
    {
        $capturedRequest = null;
        $httpClient = $this->httpClientCapturing(new Response(200, [], ''), $capturedRequest);
        $client = $this->createFacebookClient($httpClient);
        $client->setClientId('cid');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/callback');

        $client->fetchClientAccessToken($incomingRequest, 'auth-code', ['extra' => 'custom-value']);

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $params);
        $this->assertSame('custom-value', $params['extra']);
        $this->assertSame('auth-code', $params['code']);
    }
}
