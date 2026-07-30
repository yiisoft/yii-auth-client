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
use Yiisoft\Yii\AuthClient\Client\MicrosoftOnline;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class MicrosoftOnlineTest extends ProviderClientTestCase
{
    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('microsoftonline', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('MicrosoftOnline', $client->getTitle());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('offline_access User.Read', $client->getScope());
    }

    public function testGetViewOptions(): void
    {
        $client = $this->createClient();

        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $client->getViewOptions());
    }

    public function testDefaultTenantIsCommon(): void
    {
        $client = $this->createMicrosoftClient();

        $this->assertSame('common', $client->getTenant());
    }

    public function testSetTenantChangesTenant(): void
    {
        $client = $this->createMicrosoftClient();

        $client->setTenant('contoso');

        $this->assertSame('contoso', $client->getTenant());
    }

    public function testGetAuthUrlWithTenantInserted(): void
    {
        $client = $this->createMicrosoftClient();

        $authUrl = $client->getAuthUrlWithTenantInserted('contoso');

        $this->assertSame('https://login.microsoftonline.com/contoso/oauth2/v2.0/authorize', $authUrl);
    }

    public function testGetTokenUrlWithTenantInserted(): void
    {
        $client = $this->createMicrosoftClient();

        $tokenUrl = $client->getTokenUrlWithTenantInserted('contoso');

        $this->assertSame('https://login.microsoftonline.com/contoso/oauth2/v2.0/token', $tokenUrl);
    }

    public function testBuildAuthUrlRequiresExplicitTenantSubstitution(): void
    {
        $client = $this->createMicrosoftClient();
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');
        $client->setAuthUrl($client->getAuthUrlWithTenantInserted($client->getTenant()));

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub());

        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $authUrl);
    }

    public function testSetTokenUrlOverridesTokenUrl(): void
    {
        $client = $this->createMicrosoftClient();

        $client->setTokenUrl('https://login.microsoftonline.com/contoso/oauth2/v2.0/token');

        $this->assertSame('https://login.microsoftonline.com/contoso/oauth2/v2.0/token', $client->getTokenUrl());
    }

    public function testGetCurrentUserJsonArrayReturnsDecodedResponseBody(): void
    {
        $capturedRequest = null;
        $httpClient = new class ($capturedRequest) implements ClientInterface {
            public function __construct(private ?RequestInterface &$capturedRequest) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new Response(200, [], (string) json_encode(['displayName' => 'John Doe']));
            }
        };
        $client = $this->createMicrosoftClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'abc123');

        $result = $client->getCurrentUserJsonArray($token);

        $this->assertSame(['displayName' => 'John Doe'], $result);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('https://graph.microsoft.com/v1.0/me', (string) $capturedRequest->getUri());
    }

    public function testInitUserAttributesIsProtectedAndReturnsEmptyArrayWithoutAccessToken(): void
    {
        $client = $this->createMicrosoftClient();
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertTrue($method->isProtected());
        $this->assertSame([], $method->invoke($client));
    }

    public function testInitUserAttributesReturnsCurrentUserDataWhenAccessTokenPresent(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(200, [], (string) json_encode(['displayName' => 'John Doe'])));
        $client = $this->createMicrosoftClient($httpClient);
        $client->setAccessToken(['params' => ['access_token' => 'abc123']]);
        $method = new ReflectionMethod($client, 'initUserAttributes');

        $this->assertSame(['displayName' => 'John Doe'], $method->invoke($client));
    }

    protected function createClient(): OAuth2
    {
        return $this->instantiate(MicrosoftOnline::class);
    }

    private function createMicrosoftClient(?ClientInterface $httpClient = null): MicrosoftOnline
    {
        if ($httpClient === null) {
            return $this->instantiate(MicrosoftOnline::class);
        }

        return new MicrosoftOnline(
            $httpClient,
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }
}
