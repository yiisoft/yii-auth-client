<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class OpenIdConnectLogoutTest extends TestCase
{
    public function testBuildLogoutUrlFromDiscovery(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())->method('sendRequest')
            ->willReturnCallback(static function (RequestInterface $request): ResponseInterface {
                self::assertSame('GET', $request->getMethod());
                self::assertSame(
                    'https://provider.example/.well-known/openid-configuration',
                    (string) $request->getUri(),
                );
                return new Response(
                    200,
                    [],
                    '{"end_session_endpoint":"https://provider.example/logout?tenant=example"}',
                );
            });
        $client = $this->createClient($httpClient);
        $client->setReturnUrl('https://app.example/login-callback');
        $token = new OAuthToken();
        $token->setParams(['id_token' => 'header.payload.signature', 'access_token' => 'access-token']);
        $client->setAccessToken($token);

        $url = $client->buildLogoutUrl([
            'post_logout_redirect_uri' => 'https://app.example/logged-out?lang=en&from=logout',
            'state' => 'a+b/c=?',
            'ui_locales' => 'en fr',
            'logout_hint' => 'user@example.com',
        ]);

        $this->assertSame(
            'https://provider.example/logout?tenant=example&client_id=test-client'
            . '&id_token_hint=header.payload.signature'
            . '&post_logout_redirect_uri=https%3A%2F%2Fapp.example%2Flogged-out%3Flang%3Den%26from%3Dlogout'
            . '&state=a%2Bb%2Fc%3D%3F&ui_locales=en%20fr&logout_hint=user%40example.com',
            $url,
        );
        $this->assertSame($token, $client->getAccessToken());
    }

    public function testExplicitParametersBypassDiscoveryAndStorage(): void
    {
        $storage = $this->createMock(StateStorageInterface::class);
        $storage->expects($this->never())->method('get');
        $client = $this->createClient(stateStorage: $storage);
        $client->setLogoutUrl('https://provider.example/logout');

        $this->assertSame(
            'https://provider.example/logout?client_id=other-client&id_token_hint=explicit-token',
            $client->buildLogoutUrl(['client_id' => 'other-client', 'id_token_hint' => 'explicit-token']),
        );
    }

    public function testNullParametersOmitDefaultsWithoutReadingStorage(): void
    {
        $storage = $this->createMock(StateStorageInterface::class);
        $storage->expects($this->never())->method('get');
        $client = $this->createClient(stateStorage: $storage);
        $client->setLogoutUrl('https://provider.example/logout');

        $this->assertSame(
            'https://provider.example/logout',
            $client->buildLogoutUrl(['client_id' => null, 'id_token_hint' => null]),
        );
    }

    public function testBuildLogoutUrlWithoutToken(): void
    {
        $client = $this->createClient();
        $client->setLogoutUrl('https://provider.example/logout');

        $this->assertSame('https://provider.example/logout?client_id=test-client', $client->buildLogoutUrl());
    }

    public function testBuildLogoutUrlWithoutIdToken(): void
    {
        $client = $this->createClient();
        $client->setLogoutUrl('https://provider.example/logout');
        $token = new OAuthToken();
        $token->setParam('access_token', 'access-token');
        $client->setAccessToken($token);

        $this->assertSame('https://provider.example/logout?client_id=test-client', $client->buildLogoutUrl());
    }

    public function testLogoutDoesNotRefreshOrCacheExpiredStoredToken(): void
    {
        $storage = new SessionStateStorage(new Session());
        $client = $this->createClient(stateStorage: $storage);
        $token = new OAuthToken();
        $token->setParams(['expires_in' => -1, 'id_token' => 'old-id-token', 'refresh_token' => 'refresh-token']);
        $this->assertTrue($token->getIsExpired());
        $client->setAccessToken($token);

        $refreshed = false;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())->method('sendRequest')
            ->willReturnCallback(static function (RequestInterface $request) use (&$refreshed): ResponseInterface {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('https://provider.example/token', (string) $request->getUri());
                $refreshed = true;
                return new Response(
                    200,
                    [],
                    '{"access_token":"fresh-token","id_token":"fresh-id-token","expires_in":3600}',
                );
            });
        $cache = new ArrayCache();
        $cache->set('config-params-openid-connect', [
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
        ]);
        $restoredClient = new OpenIdConnect(
            $httpClient,
            new Psr17Factory(),
            $storage,
            new Factory(),
            new Session(),
            $cache,
        );
        $restoredClient = $restoredClient->withoutValidateJws();
        $restoredClient->setClientId('test-client');
        $restoredClient->setTokenUrl('https://provider.example/token');
        $restoredClient->setLogoutUrl('https://provider.example/logout');

        $this->assertSame(
            'https://provider.example/logout?client_id=test-client&id_token_hint=old-id-token',
            $restoredClient->buildLogoutUrl(),
        );
        $this->assertFalse($refreshed);
        $freshToken = $restoredClient->getAccessToken();
        $this->assertTrue($refreshed);
        $this->assertSame('fresh-id-token', $freshToken?->getParam('id_token'));
        $this->assertSame(
            'https://provider.example/logout?client_id=test-client&id_token_hint=fresh-id-token',
            $restoredClient->buildLogoutUrl(),
        );
    }

    #[DataProvider('endpointFallbackProvider')]
    public function testEndpointFallsBackToDiscovery(?string $logoutUrl): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())->method('sendRequest')->willReturn(
            new Response(200, [], '{"end_session_endpoint":"https://provider.example/logout"}'),
        );
        $client = $this->createClient($httpClient);
        $client->setLogoutUrl('https://custom.example/logout');
        $client->setLogoutUrl($logoutUrl);

        $this->assertSame('https://provider.example/logout?client_id=test-client', $client->buildLogoutUrl());
    }

    public static function endpointFallbackProvider(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    public function testEmptyDefaultsAreOmitted(): void
    {
        $client = $this->createClient();
        $client->setLogoutUrl('https://provider.example/logout');
        $client->setClientId('');
        $token = new OAuthToken();
        $token->setParam('id_token', '');
        $client->setAccessToken($token);

        $this->assertSame('https://provider.example/logout', $client->buildLogoutUrl());
    }

    public function testExplicitEmptyParametersArePreserved(): void
    {
        $client = $this->createClient();
        $client->setLogoutUrl('https://provider.example/logout');

        $this->assertSame(
            'https://provider.example/logout?client_id=&id_token_hint=',
            $client->buildLogoutUrl(['client_id' => '', 'id_token_hint' => '']),
        );
    }

    #[DataProvider('unexpectedTokenProvider')]
    public function testUnexpectedStoredTokenIsIgnored(mixed $token): void
    {
        $storage = $this->createStub(StateStorageInterface::class);
        $storage->method('get')->willReturn($token);
        $client = $this->createClient(stateStorage: $storage);
        $client->setLogoutUrl('https://provider.example/logout');

        $this->assertSame('https://provider.example/logout?client_id=test-client', $client->buildLogoutUrl());
    }

    public static function unexpectedTokenProvider(): array
    {
        return ['false' => [false], 'array' => [['id_token' => 'unexpected']]];
    }

    #[DataProvider('missingEndpointProvider')]
    public function testMissingEndpoint(string $configuration): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], $configuration));
        $client = $this->createClient($httpClient);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('end_session_endpoint');
        $client->buildLogoutUrl();
    }

    public static function missingEndpointProvider(): array
    {
        return [
            'missing' => ['{}'],
            'empty' => ['{"end_session_endpoint":""}'],
            'invalid type' => ['{"end_session_endpoint":false}'],
        ];
    }

    public function testDiscoveryFailurePreservesToken(): void
    {
        $failure = new RuntimeException('Discovery failed.');
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException($failure);
        $client = $this->createClient($httpClient);
        $token = new OAuthToken();
        $client->setAccessToken($token);

        try {
            $client->buildLogoutUrl();
            $this->fail('Expected discovery to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertSame($token, $client->getAccessToken());
    }

    private function createClient(
        ?ClientInterface $httpClient = null,
        ?StateStorageInterface $stateStorage = null,
    ): OpenIdConnect {
        if ($httpClient === null) {
            $httpClient = $this->createMock(ClientInterface::class);
            $httpClient->expects($this->never())->method('sendRequest');
        }
        $client = new OpenIdConnect(
            $httpClient,
            new Psr17Factory(),
            $stateStorage ?? new DummyStateStorage(),
            new Factory(),
            new Session(),
            new ArrayCache(),
        );
        $client->setIssuerUrl('https://provider.example');
        $client->setClientId('test-client');
        return $client;
    }
}
