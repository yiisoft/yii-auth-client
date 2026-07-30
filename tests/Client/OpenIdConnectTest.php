<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\AuthClient;
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;
use Yiisoft\Yii\AuthClient\Exception\ClientException;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class OpenIdConnectTest extends TestCase
{
    private const SECRET = 'my-256-bit-secret-my-256-bit-secret';
    private const ISSUER_URL = 'https://issuer.example.com';
    private const CLIENT_ID = 'client-id';

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('oidc', $client->getName());
    }

    /**
     * getName() must return the name configured per-instance via the constructor: it feeds both the
     * session-state key prefix ({@see AuthClient::getStateKeyPrefix()}) and the
     * config-discovery cache key ({@see getConfigParams()}), so two different providers configured with
     * distinct names in the same app must not collide on either.
     */
    public function testGetNameReflectsConstructorArgumentPerInstance(): void
    {
        $client = new OpenIdConnect(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            new ArrayCache(),
            'auth0',
            'Auth0',
        );

        $this->assertSame('auth0', $client->getName());
    }

    public function testGetConfigParamsDoesNotLeakAcrossDifferentlyNamedProviders(): void
    {
        $cache = new ArrayCache();
        $auth0Client = new OpenIdConnect(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'auth0',
            'Auth0',
        );
        $auth0Client->setIssuerUrl(self::ISSUER_URL);
        $cache->set('config-params-auth0', ['authorization_endpoint' => 'https://auth0.example.com/authorize']);

        $oktaClient = new OpenIdConnect(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'okta',
            'Okta',
        );
        $oktaClient->setIssuerUrl(self::ISSUER_URL);
        $cache->set('config-params-okta', ['authorization_endpoint' => 'https://okta.example.com/authorize']);

        $this->assertSame('https://auth0.example.com/authorize', $auth0Client->getConfigParam('authorization_endpoint'));
        $this->assertSame('https://okta.example.com/authorize', $oktaClient->getConfigParam('authorization_endpoint'));
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('OIDC', $client->getTitle());
    }

    /**
     * getTitle() must return the title configured per-instance via the constructor, so that an app
     * registering multiple OpenIdConnect clients (Auth0, Okta, ...) can tell them apart in the UI.
     */
    public function testGetTitleReflectsConstructorArgumentPerInstance(): void
    {
        $client = new OpenIdConnect(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            new ArrayCache(),
            'auth0',
            'Auth0',
        );

        $this->assertSame('Auth0', $client->getTitle());
    }

    public function testGetViewOptions(): void
    {
        $client = $this->createClient();

        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $client->getViewOptions());
    }

    public function testGetDefaultScopeIsOpenid(): void
    {
        $client = $this->createClient();

        $this->assertSame('openid', $client->getScope());
    }

    public function testGetConfigParamReturnsCachedValueWithoutHttpCall(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException(new RuntimeException('HTTP should not be called'));
        $client = $this->createClient(['authorization_endpoint' => 'https://issuer.example.com/authorize'], $httpClient);

        $value = $client->getConfigParam('authorization_endpoint');

        $this->assertSame('https://issuer.example.com/authorize', $value);
    }

    public function testGetConfigParamsDiscoversConfigWhenNotCached(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request): ResponseInterface {
                $this->assertSame(
                    'https://issuer.example.com/.well-known/openid-configuration',
                    (string) $request->getUri(),
                );

                return new Response(200, [], (string) json_encode([
                    'authorization_endpoint' => 'https://issuer.example.com/authorize',
                ]));
            });
        $client = $this->createClient([], $httpClient);

        $params = $client->getConfigParams();

        $this->assertSame(['authorization_endpoint' => 'https://issuer.example.com/authorize'], $params);
    }

    public function testBuildAuthUrlDiscoversAuthorizationEndpointFromConfig(): void
    {
        $client = $this->createClient(['authorization_endpoint' => 'https://issuer.example.com/authorize']);

        $authUrl = $client->buildAuthUrl($this->createStub(ServerRequestInterface::class));

        $this->assertStringStartsWith('https://issuer.example.com/authorize?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testCreateTokenPopulatesParamsFromValidJws(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
        $this->assertSame(self::ISSUER_URL, $token->getParam('iss'));
    }

    public function testCreateTokenThrowsOnInvalidIssuer(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => 'https://not-the-configured-issuer.example.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid "iss"');

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    public function testCreateTokenThrowsOnInvalidAudience(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => 'someone-elses-client-id',
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid "aud"');

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    public function testCreateTokenThrowsOnTamperedSignature(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        // Corrupt the signature part of the compact JWS.
        $parts = explode('.', $idToken);
        $parts[2] = strrev($parts[2]);
        $tamperedIdToken = implode('.', $parts);

        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $tamperedIdToken]]);
    }

    public function testGetValidateAuthNonceIsTrueWhenNonceIsSupportedClaim(): void
    {
        $client = $this->createClient(['claims_supported' => ['sub', 'nonce']]);

        $this->assertTrue($client->getValidateAuthNonce());
    }

    public function testGetValidateAuthNonceIsFalseWhenNonceIsNotSupportedClaim(): void
    {
        $client = $this->createClient(['claims_supported' => ['sub']]);

        $this->assertFalse($client->getValidateAuthNonce());
    }

    public function testSetValidateAuthNonceOverridesAutoDetection(): void
    {
        $client = $this->createClient(['claims_supported' => ['sub', 'nonce']]);

        $client->setValidateAuthNonce(false);

        $this->assertFalse($client->getValidateAuthNonce());
    }

    /**
     * getValidateAuthNonce() short-circuits on {@see OpenIdConnect::$validateJws}, so disabling JWS
     * validation via withoutValidateJws() must be observable through it even when 'nonce' is a supported claim.
     */
    public function testWithoutValidateJwsDisablesAutoDetectedAuthNonceValidation(): void
    {
        $client = $this->createClient(['claims_supported' => ['sub', 'nonce']]);

        $withoutJws = $client->withoutValidateJws();

        $this->assertFalse($withoutJws->getValidateAuthNonce());
        // withoutValidateJws() must not mutate the original instance.
        $this->assertTrue($client->getValidateAuthNonce());
    }

    public function testWithValidateJwsReenablesAutoDetectedAuthNonceValidation(): void
    {
        $client = $this->createClient(['claims_supported' => ['sub', 'nonce']])->withoutValidateJws();

        $withJws = $client->withValidateJws();

        $this->assertTrue($withJws->getValidateAuthNonce());
        // withValidateJws() must not mutate the original instance.
        $this->assertFalse($client->getValidateAuthNonce());
    }

    public function testBuildAuthUrlDoesNotThrowWhenAuthorizationEndpointConfigIsMissing(): void
    {
        $client = $this->createClient(['claims_supported' => []]);

        $authUrl = @$client->buildAuthUrl($this->createStub(ServerRequestInterface::class));

        // With no configured authorization_endpoint, authUrl falls back to '', so the
        // composed URL is just the query string.
        $this->assertStringStartsWith('?client_id=', $authUrl);
    }

    public function testGetConfigParamsCachesDiscoveredConfigAcrossCalls(): void
    {
        $callCount = 0;
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(function () use (&$callCount): ResponseInterface {
                $callCount++;
                return new Response(200, [], (string) json_encode(['authorization_endpoint' => 'https://issuer.example.com/authorize']));
            });
        $client = $this->createClient([], $httpClient);

        $client->getConfigParams();
        $client->getConfigParams();

        $this->assertSame(1, $callCount);
    }

    public function testSetIssuerUrlTrimsTrailingSlashBeforeDiscovery(): void
    {
        $requestedUrl = null;
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use (&$requestedUrl): ResponseInterface {
                $requestedUrl = (string) $request->getUri();
                return new Response(200, [], '{}');
            });
        $client = $this->createClient([], $httpClient);
        $client->setIssuerUrl(self::ISSUER_URL . '/');

        $client->getConfigParams();

        $this->assertSame(self::ISSUER_URL . '/.well-known/openid-configuration', $requestedUrl);
    }

    public function testCreateTokenPreservesOuterParamsAlongsideJwsClaims(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $token = $this->invokeCreateToken($client, [
            'params' => ['id_token' => $idToken, 'access_token' => 'outer-access-token'],
        ]);

        $this->assertSame('outer-access-token', $token->getParam('access_token'));
        $this->assertSame('user-1', $token->getParam('sub'));
    }

    public function testCreateTokenPreservesNestedArrayClaimValues(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nested' => ['inner' => 'value'],
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame(['inner' => 'value'], $token->getParam('nested'));
    }

    public function testCreateTokenThrowsWithLoadingJwsExceptionMessagePrefix(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $parts = explode('.', $idToken);
        $parts[2] = strrev($parts[2]);
        $tamperedIdToken = implode('.', $parts);

        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Loading JWS: Exception:');

        $this->invokeCreateToken($client, ['params' => ['id_token' => $tamperedIdToken]]);
    }

    public function testCreateTokenThrowsOnInvalidIssuerWithCode400(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => 'https://not-the-configured-issuer.example.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    public function testCreateTokenThrowsOnInvalidAudienceWithCode400(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => 'someone-elses-client-id',
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    public function testCreateTokenValidatesIssuerIgnoringTrailingSlashDifferences(): void
    {
        $jwk = $this->createHmacJwk();
        // JWT claims the issuer with a trailing slash; configured issuer URL has none.
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL . '/',
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
    }

    /**
     * The cached value is cast to array before the empty() check. A non-empty scalar is truthy either
     * way, but (array) wraps a scalar into a single-element array, while an uncast scalar would flow
     * straight into {@see OpenIdConnect::$configParams}, which is typed `array`.
     */
    public function testGetConfigParamsCastsCachedValueToArray(): void
    {
        $cache = new ArrayCache();
        $cache->set('config-params-oidc', 'not-an-array');
        $client = new OpenIdConnect(
            $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'oidc',
            'OIDC',
        );

        $params = $client->getConfigParams();

        $this->assertSame(['not-an-array'], $params);
    }

    public function testGetConfigParamsPersistsDiscoveredConfigToCacheForOtherInstances(): void
    {
        $cache = new ArrayCache();
        $httpClient = $this->createStub(ClientInterface::class);
        $callCount = 0;
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(function () use (&$callCount): ResponseInterface {
                $callCount++;
                return new Response(200, [], (string) json_encode(['authorization_endpoint' => 'https://issuer.example.com/authorize']));
            });
        $firstClient = new OpenIdConnect(
            $httpClient,
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'oidc',
            'OIDC',
        );
        $firstClient->setIssuerUrl(self::ISSUER_URL);
        $secondClient = new OpenIdConnect(
            $httpClient,
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'oidc',
            'OIDC',
        );
        $secondClient->setIssuerUrl(self::ISSUER_URL);

        $firstClient->getConfigParams();
        $secondClient->getConfigParams();

        $this->assertSame(1, $callCount);
    }

    /**
     * tokenConfig['params'] is cast to array before being read from and merged into. An object with
     * a public `id_token` property casts cleanly to an array; without the cast, plain array access on
     * an object that doesn't implement ArrayAccess is a fatal TypeError.
     */
    public function testCreateTokenCastsParamsToArrayBeforeAccessingIdToken(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $token = $this->invokeCreateToken($client, ['params' => (object) ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
    }

    /**
     * id_token is cast to string before being passed to loadJws(), which is typed `string`. An int
     * value would trigger a TypeError from loadJws() itself if the cast were removed, instead of the
     * ClientException that loadJws() throws internally for a malformed (but string) JWS.
     */
    public function testCreateTokenCastsIdTokenToStringBeforeLoadingJws(): void
    {
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Loading JWS: Exception:');

        $this->invokeCreateToken($client, ['params' => ['id_token' => 12345]]);
    }

    public function testCreateTokenThrowsLoadingJwsExceptionMessageWithOriginalMessageAppended(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $parts = explode('.', $idToken);
        $parts[2] = strrev($parts[2]);
        $tamperedIdToken = implode('.', $parts);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        // Must start with the static prefix AND have the underlying exception message appended after
        // it, ruling out both a reversed concatenation and a dropped operand.
        $this->expectExceptionMessageMatches('/^Loading JWS: Exception: .+/');

        $this->invokeCreateToken($client, ['params' => ['id_token' => $tamperedIdToken]]);
    }

    /**
     * "iss" is cast to string before being passed to rtrim(), which is typed `string`. A non-string
     * claim value would trigger a TypeError from rtrim() itself if the cast were removed, instead of
     * the ClientException the code is meant to throw for a genuinely mismatched issuer.
     */
    public function testCreateTokenCastsIssuerClaimToStringBeforeComparison(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => 12345,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid "iss"');

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    /**
     * {@see OpenIdConnect::setIssuerUrl()} always strips a trailing slash, so under normal usage
     * $issuerUrl never has one. The rtrim() in validateClaims() defends against that invariant being
     * bypassed (e.g. a differently-configured subclass), so the property is forced via reflection here.
     */
    public function testCreateTokenTrimsTrailingSlashFromIssuerUrlBeforeComparison(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);
        $client = $this->createClient(['claims_supported' => []]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));
        (new ReflectionProperty($client, 'issuerUrl'))->setValue($client, self::ISSUER_URL . '/');

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
    }

    public function testGetConfigParamsThrowsWhenIssuerUrlIsEmpty(): void
    {
        $client = $this->createClient();
        $client->setIssuerUrl('');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Cannot discover config because issuer URL is not set.');

        $client->getConfigParams();
    }

    /**
     * The discovered token_endpoint config value is cast to string before being stored. A non-string
     * config value (int here) would fail to assign to the strictly `string`-typed $tokenUrl property
     * if the cast were removed, throwing a TypeError instead of succeeding.
     */
    public function testFetchAccessTokenCastsDiscoveredTokenEndpointToString(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], 'access_token=abc123&expires_in=3600'));
        $client = $this->createClient([
            'token_endpoint' => 12345,
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'claims_supported' => [],
        ], $httpClient)->withoutValidateAuthState();
        $client->setClientSecret('secret');
        $this->disableJwsValidation($client);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $token = $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertSame('abc123', $token->getToken());
        $this->assertSame('12345', $client->getTokenUrl());
    }

    /**
     * When no 'nonce' param is supplied and nonce validation is enabled, fetchAccessToken() must
     * generate a fresh nonce, persist it as state, and send it along in the token request. Captures
     * the outgoing request and inspects state directly rather than relying on the deeper createToken()
     * nonce check, in order to isolate and precisely kill the branch/negation mutants on this
     * `!isset($params['nonce']) && $this->getValidateAuthNonce()` condition and on the setState() call.
     */
    public function testFetchAccessTokenGeneratesAndPersistsNonceWhenMissingAndValidationEnabled(): void
    {
        $capturedRequest = null;
        // createToken()'s own downstream JWS validation (irrelevant to this test) triggers a *second*
        // HTTP call (JWKS discovery); only the first call, the actual token request, is captured.
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest ??= $request;
                return new Response(200, [], 'access_token=abc123&expires_in=3600');
            }
        };
        $client = $this->createClient(
            [
                'token_endpoint' => 'https://issuer.example.com/token',
                'token_endpoint_auth_methods_supported' => ['client_secret_post'],
                'claims_supported' => ['nonce'],
            ],
            $httpClient,
            new SessionStateStorage(new Session()),
        )->withoutValidateAuthState();
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        try {
            $client->fetchAccessToken($incomingRequest, 'auth-code');
        } catch (Throwable) {
        }

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $bodyParams);
        $sentNonce = $bodyParams['nonce'] ?? null;
        $this->assertIsString($sentNonce);
        $this->assertNotSame('', $sentNonce);
        $storedNonce = (new ReflectionMethod($client, 'getState'))->invoke($client, 'authNonce');
        $this->assertSame($sentNonce, $storedNonce);
    }

    /**
     * When the caller already supplies a 'nonce' param, fetchAccessToken() must not overwrite it or
     * generate/persist a new one, regardless of whether nonce validation is enabled.
     */
    public function testFetchAccessTokenLeavesCallerSuppliedNonceUntouched(): void
    {
        $capturedRequest = null;
        // createToken()'s own downstream JWS validation (irrelevant to this test) triggers a *second*
        // HTTP call (JWKS discovery); only the first call, the actual token request, is captured.
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest ??= $request;
                return new Response(200, [], 'access_token=abc123&expires_in=3600');
            }
        };
        $client = $this->createClient(
            [
                'token_endpoint' => 'https://issuer.example.com/token',
                'token_endpoint_auth_methods_supported' => ['client_secret_post'],
                'claims_supported' => ['nonce'],
            ],
            $httpClient,
            new SessionStateStorage(new Session()),
        )->withoutValidateAuthState();
        $client->setClientSecret('secret');
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        try {
            $client->fetchAccessToken($incomingRequest, 'auth-code', ['nonce' => 'caller-supplied-nonce']);
        } catch (Throwable) {
        }

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $bodyParams);
        $this->assertSame('caller-supplied-nonce', $bodyParams['nonce']);
        $storedNonce = (new ReflectionMethod($client, 'getState'))->invoke($client, 'authNonce');
        $this->assertNull($storedNonce);
    }

    /**
     * When nonce validation is disabled, fetchAccessToken() must not add a nonce param at all.
     */
    public function testFetchAccessTokenOmitsNonceParamWhenValidationDisabled(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], 'access_token=abc123&expires_in=3600');
            }
        };
        $client = $this->createClient([
            'token_endpoint' => 'https://issuer.example.com/token',
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'claims_supported' => [],
        ], $httpClient)->withoutValidateAuthState();
        $client->setClientSecret('secret');
        $this->disableJwsValidation($client);
        $incomingRequest = (new Psr17Factory())->createServerRequest('GET', 'http://return.local');

        $client->fetchAccessToken($incomingRequest, 'auth-code');

        $this->assertNotNull($capturedRequest);
        parse_str((string) $capturedRequest->getBody(), $bodyParams);
        $this->assertArrayNotHasKey('nonce', $bodyParams);
    }

    /**
     * The discovered token_endpoint config value is cast to string before being stored. A non-string
     * config value (int here) would fail to assign to the strictly `string`-typed $tokenUrl property
     * if the cast were removed, throwing a TypeError instead of succeeding.
     */
    public function testRefreshAccessTokenCastsDiscoveredTokenEndpointToString(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], 'access_token=refreshed&expires_in=3600'));
        $client = $this->createClient([
            'token_endpoint' => 12345,
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'claims_supported' => [],
        ], $httpClient);
        $client->setClientSecret('secret');
        $this->disableJwsValidation($client);
        $oldToken = new OAuthToken();
        $oldToken->setToken('old-token');

        $newToken = $client->refreshAccessToken($oldToken);

        $this->assertSame('refreshed', $newToken->getToken());
        $this->assertSame('12345', $client->getTokenUrl());
    }

    public function testInitUserAttributesIsProtectedAndFetchesUserInfoEndpoint(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], (string) json_encode(['sub' => 'user-1']));
            }
        };
        $client = $this->createClient(['userinfo_endpoint' => 'https://issuer.example.com/userinfo'], $httpClient);
        $this->disableJwsValidation($client);
        $client->setAccessToken(['params' => ['access_token' => 'abc123', 'expires_in' => 3600]]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $result = $method->invoke($client);

        $this->assertSame(['sub' => 'user-1'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertStringStartsWith('https://issuer.example.com/userinfo', (string) $capturedRequest->getUri());
    }

    public function testApplyClientCredentialsToRequestIsProtectedAndUsesBasicAuthWhenSupported(): void
    {
        $client = $this->createClient(['token_endpoint_auth_methods_supported' => ['client_secret_basic']]);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');

        $this->assertTrue($method->isProtected());
        $newRequest = $method->invoke($client, $request);

        $this->assertSame('Basic ' . base64_encode('cid:csecret'), $newRequest->getHeaderLine('Authorization'));
    }

    public function testApplyClientCredentialsToRequestUsesPostParamsWhenSupported(): void
    {
        $client = $this->createClient(['token_endpoint_auth_methods_supported' => ['client_secret_post']]);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');

        $newRequest = $method->invoke($client, $request);

        parse_str((string) $newRequest->getBody(), $params);
        $this->assertSame('cid', $params['client_id']);
        $this->assertSame('csecret', $params['client_secret']);
    }

    /**
     * token_endpoint_auth_methods_supported is cast to array before the in_array() check. A bare
     * string config value (not wrapped in an array) would fail in_array()'s strictly-typed `array`
     * parameter if the cast were removed, throwing a TypeError instead of matching successfully.
     */
    public function testApplyClientCredentialsToRequestCastsAuthMethodsConfigToArray(): void
    {
        $client = $this->createClient(['token_endpoint_auth_methods_supported' => 'client_secret_basic']);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');

        $newRequest = $method->invoke($client, $request);

        $this->assertSame('Basic ' . base64_encode('cid:csecret'), $newRequest->getHeaderLine('Authorization'));
    }

    /**
     * The request passed in already carries a form-urlencoded body (as it would when called from
     * fetchAccessToken()/refreshAccessToken()), so the '&' separator prefixed onto the assertion
     * param matters - without it, the two writes would merge into one malformed, unparseable key
     * instead of two independent params.
     */
    public function testApplyClientCredentialsToRequestUsesJwtAssertionWhenSupported(): void
    {
        $client = $this->createClient(['token_endpoint_auth_methods_supported' => ['client_secret_jwt']]);
        $client->setClientId('cid');
        $client->setClientSecret('csecret');
        $client->setTokenUrl('https://issuer.example.com/token');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $request->getBody()->write('code=auth-code');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');
        $before = time();

        $newRequest = $method->invoke($client, $request);

        $after = time();
        parse_str((string) $newRequest->getBody(), $params);
        $this->assertSame('auth-code', $params['code']);
        $this->assertArrayHasKey('assertion', $params);
        [$headerSegment, $payloadSegment, $signatureSegment] = explode('.', $params['assertion']);
        $header = (array) json_decode((string) base64_decode($headerSegment), true);
        $payload = (array) json_decode((string) base64_decode($payloadSegment), true);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'HS256'], $header);
        $this->assertSame('cid', $payload['iss']);
        $this->assertSame('cid', $payload['sub']);
        $this->assertSame('https://issuer.example.com/token', $payload['aud']);
        $this->assertNotSame('', $payload['jti']);
        $this->assertGreaterThanOrEqual($before, $payload['iat']);
        $this->assertLessThanOrEqual($after, $payload['iat']);
        $this->assertSame($payload['iat'] + 3600, $payload['exp']);
        $this->assertNotSame('', $signatureSegment);
    }

    public function testApplyClientCredentialsToRequestThrowsWhenNoSupportedAuthMethod(): void
    {
        $client = $this->createClient(['token_endpoint_auth_methods_supported' => ['unsupported_method']]);
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');
        $method = new ReflectionMethod($client, 'applyClientCredentialsToRequest');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to authenticate request: No auth method supported');

        $method->invoke($client, $request);
    }

    public function testDefaultReturnUrlIsProtectedAndStripsOidcSpecificQueryParams(): void
    {
        $client = $this->createClient();
        $method = new ReflectionMethod($client, 'defaultReturnUrl');
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/callback')
            ->withQueryParams([
                'code' => 'abc',
                'state' => 'xyz',
                'nonce' => 'n',
                'authuser' => '0',
                'session_state' => 's',
                'prompt' => 'none',
                'keep' => 'me',
            ]);

        $this->assertTrue($method->isProtected());
        $url = $method->invoke($client, $request);

        $this->assertSame('http://example.com/callback?keep=me', $url);
    }

    public function testCreateTokenSucceedsWhenNonceMatchesStoredAuthNonce(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nonce' => 'known-nonce',
        ]);
        $client = $this->createClient(
            ['claims_supported' => ['nonce']],
            stateStorage: new SessionStateStorage(new Session()),
        );
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));
        (new ReflectionMethod($client, 'setState'))->invoke($client, 'authNonce', 'known-nonce');

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
        $this->assertNull((new ReflectionMethod($client, 'getState'))->invoke($client, 'authNonce'));
    }

    public function testCreateTokenThrowsOnNonceMismatch(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nonce' => 'wrong-nonce',
        ]);
        $client = $this->createClient(
            ['claims_supported' => ['nonce']],
            stateStorage: new SessionStateStorage(new Session()),
        );
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));
        (new ReflectionMethod($client, 'setState'))->invoke($client, 'authNonce', 'expected-nonce');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid auth nonce');
        $this->expectExceptionCode(400);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    /**
     * The 'nonce' JWS claim is cast to string before comparison. A non-string claim value (int here)
     * would fail strcmp()'s strictly-typed `string` parameter if the cast were removed, throwing a
     * TypeError instead of comparing successfully against the (matching) stored auth nonce.
     */
    public function testCreateTokenCastsJwsNonceClaimToStringBeforeComparison(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nonce' => 12345,
        ]);
        $client = $this->createClient(
            ['claims_supported' => ['nonce']],
            stateStorage: new SessionStateStorage(new Session()),
        );
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));
        (new ReflectionMethod($client, 'setState'))->invoke($client, 'authNonce', '12345');

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
    }

    /**
     * The stored auth-nonce state value is cast to string before comparison. A non-string stored
     * value (int here) would fail strcmp()'s strictly-typed `string` parameter if the cast were
     * removed, throwing a TypeError instead of comparing successfully against the matching claim.
     */
    public function testCreateTokenCastsStoredAuthNonceToStringBeforeComparison(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nonce' => '67890',
        ]);
        $client = $this->createClient(
            ['claims_supported' => ['nonce']],
            stateStorage: new SessionStateStorage(new Session()),
        );
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));
        (new ReflectionMethod($client, 'setState'))->invoke($client, 'authNonce', 67890);

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
    }

    /**
     * The three nonce-invalidity conditions are joined with ||, not &&: a present-but-empty nonce
     * claim (isset() true) combined with a never-set auth-nonce state (empty() true) must still throw,
     * even though strcmp() of two empty strings is itself 0. An && mutant on the first two operands
     * would wrongly let this combination through since the third disjunct alone is false here.
     */
    public function testCreateTokenThrowsWhenAuthNonceMissingEvenIfClaimNonceIsEmptyString(): void
    {
        $jwk = $this->createHmacJwk();
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
            'nonce' => '',
        ]);
        $client = $this->createClient(['claims_supported' => ['nonce']]);
        $this->primeJwkSetCache($client, new JWKSet([$jwk]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid auth nonce');
        $this->expectExceptionCode(400);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
    }

    public function testCreateTokenThrowsWhenJwkSetCannotBeResolved(): void
    {
        // A response shaped as a single JWK (has "kty", no "keys" wrapper) makes
        // JWKFactory::createFromValues() return a plain JWK instead of a JWKSet,
        // so getJwkSet() legitimately resolves to null instead of a usable set.
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], (string) json_encode(['kty' => 'oct', 'k' => 'c2VjcmV0']));
            }
        };
        $client = $this->createClient([
            'claims_supported' => [],
            'jwks_uri' => 'https://issuer.example.com/jwks',
        ], $httpClient);

        try {
            $this->invokeCreateToken($client, ['params' => ['id_token' => 'irrelevant.jws.value']]);
            $this->fail('Expected ClientException was not thrown.');
        } catch (ClientException $e) {
            $this->assertSame('Loading JWS: Exception: JWK Set is not available.', $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }

        $this->assertNotNull($capturedRequest);
        $this->assertSame('https://issuer.example.com/jwks', (string) $capturedRequest->getUri());
    }

    /**
     * getJwkSet() reads its cache entry under `$this->configParamsCacheKeyPrefix . 'jwkSet'`
     * ('config-params-jwkSet'). Pre-seeding the cache under exactly that key must produce a cache hit
     * (no HTTP call); any mutation of the concatenation (order, dropped operand) would miss the cache
     * and fall through to HTTP-based discovery instead.
     */
    public function testGetJwkSetReadsCacheUnderConfigParamsPrefixConcatenatedWithJwkSetSuffix(): void
    {
        $calledHttp = false;
        $httpClient = new class ($calledHttp) implements ClientInterface {
            public function __construct(private bool &$calledHttp) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->calledHttp = true;
                return new Response(200, [], '{}');
            }
        };
        $jwk = $this->createHmacJwk();
        $client = $this->createClient(['claims_supported' => []], $httpClient);
        $cache = (new ReflectionProperty($client, 'cache'))->getValue($client);
        $cache->set('config-params-jwkSet', new JWKSet([$jwk]));
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);

        $token = $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame('user-1', $token->getParam('sub'));
        $this->assertFalse($calledHttp);
    }

    /**
     * A freshly-discovered JWK set must be cached, so a second createToken() call (e.g. for a
     * subsequent login) reuses it instead of re-fetching the JWKS endpoint over HTTP.
     */
    public function testGetJwkSetCachesFreshlyDiscoveredSetForSubsequentCalls(): void
    {
        $jwk = $this->createHmacJwk();
        $callCount = 0;
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(function () use (&$callCount, $jwk): ResponseInterface {
            $callCount++;
            return new Response(200, [], (string) json_encode(['keys' => [$jwk->jsonSerialize()]]));
        });
        $client = $this->createClient([
            'claims_supported' => [],
            'jwks_uri' => 'https://issuer.example.com/jwks',
        ], $httpClient);
        $idToken = $this->signJws($jwk, [
            'iss' => self::ISSUER_URL,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-1',
        ]);

        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);
        $this->invokeCreateToken($client, ['params' => ['id_token' => $idToken]]);

        $this->assertSame(1, $callCount);
    }

    public function testGetJwsLoaderThrowsForUnknownAlgorithmClass(): void
    {
        $client = $this->createClient();
        (new ReflectionProperty($client, 'allowedJwsAlgorithms'))->setValue($client, ['NOT_A_REAL_ALG']);
        $method = new ReflectionMethod($client, 'getJwsLoader');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage("Algorithm class \\Jose\\Component\\Signature\\Algorithm\\NOT_A_REAL_ALG doesn't exist");

        $method->invoke($client);
    }

    private function createHmacJwk(): JWK
    {
        return JWKFactory::createFromSecret(self::SECRET, ['alg' => 'HS256', 'use' => 'sig']);
    }

    private function signJws(JWK $jwk, array $claims): string
    {
        $algorithmManager = new AlgorithmManager([new HS256()]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
            ->create()
            ->withPayload((string) json_encode($claims))
            ->addSignature($jwk, ['alg' => 'HS256'])
            ->build();

        return (new CompactSerializer())->serialize($jws, 0);
    }

    /**
     * @param array<string, mixed> $configParams
     */
    private function createClient(
        array $configParams = [],
        ?ClientInterface $httpClient = null,
        ?StateStorageInterface $stateStorage = null,
    ): OpenIdConnect {
        $cache = new ArrayCache();
        if ($configParams !== []) {
            $cache->set('config-params-oidc', $configParams);
        }

        $client = new OpenIdConnect(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            $stateStorage ?? new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
            $cache,
            'oidc',
            'OIDC',
        );
        $client->setIssuerUrl(self::ISSUER_URL);
        $client->setClientId(self::CLIENT_ID);

        return $client;
    }

    private function primeJwkSetCache(OpenIdConnect $client, JWKSet $jwkSet): void
    {
        (new ReflectionProperty($client, 'jwkSet'))->setValue($client, $jwkSet);
    }

    private function invokeCreateToken(OpenIdConnect $client, array $tokenConfig): OAuthToken
    {
        /** @var OAuthToken */
        return (new ReflectionMethod($client, 'createToken'))->invoke($client, $tokenConfig);
    }

    private function disableJwsValidation(OpenIdConnect $client): void
    {
        (new ReflectionProperty($client, 'validateJws'))->setValue($client, false);
    }
}
