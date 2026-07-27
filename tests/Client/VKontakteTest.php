<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\Client\VKontakte;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Override;
use ReflectionMethod;
use RuntimeException;

use const JSON_ERROR_NONE;

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
     * An empty response body must return [] without ever calling json_decode(): decoding an empty
     * string is invalid JSON and would leave json_last_error() set to JSON_ERROR_SYNTAX, which the
     * `> 0` vs `>= 0` boundary on strlen($body) can't otherwise be distinguished by return value alone
     * (both branches ultimately return []).
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

    private function createVKontakteClient(?ClientInterface $httpClient = null): VKontakte
    {
        if ($httpClient === null) {
            return $this->instantiate(VKontakte::class);
        }

        return new VKontakte(
            $httpClient,
            new Psr17Factory(),
            new DummyStateStorage(),
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
