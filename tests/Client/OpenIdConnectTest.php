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
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;
use Yiisoft\Yii\AuthClient\Exception\ClientException;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class OpenIdConnectTest extends TestCase
{
    private const SECRET = 'my-256-bit-secret-my-256-bit-secret';
    private const ISSUER_URL = 'https://issuer.example.com';
    private const CLIENT_ID = 'client-id';

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
    private function createClient(array $configParams = [], ?ClientInterface $httpClient = null): OpenIdConnect
    {
        $cache = new ArrayCache();
        if ($configParams !== []) {
            $cache->set('config-params-oidc', $configParams);
        }

        $client = new OpenIdConnect(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
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
        (new \ReflectionProperty($client, 'jwkSet'))->setValue($client, $jwkSet);
    }

    private function invokeCreateToken(OpenIdConnect $client, array $tokenConfig): \Yiisoft\Yii\AuthClient\OAuthToken
    {
        /** @var \Yiisoft\Yii\AuthClient\OAuthToken */
        return (new \ReflectionMethod($client, 'createToken'))->invoke($client, $tokenConfig);
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('oidc', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Open Id Connect', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('', $client->getButtonClass());
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
        $httpClient->method('sendRequest')->willThrowException(new \RuntimeException('HTTP should not be called'));
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
                    (string) $request->getUri()
                );

                return new \Nyholm\Psr7\Response(200, [], (string) json_encode([
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

        $authUrl = $client->buildAuthUrl($this->createStub(\Psr\Http\Message\ServerRequestInterface::class));

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

    public function testBuildAuthUrlDoesNotThrowWhenAuthorizationEndpointConfigIsMissing(): void
    {
        $client = $this->createClient(['claims_supported' => []]);

        $authUrl = @$client->buildAuthUrl($this->createStub(\Psr\Http\Message\ServerRequestInterface::class));

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
                return new \Nyholm\Psr7\Response(200, [], (string) json_encode(['authorization_endpoint' => 'https://issuer.example.com/authorize']));
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
                return new \Nyholm\Psr7\Response(200, [], '{}');
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
}
