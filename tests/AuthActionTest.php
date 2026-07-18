<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\View\WebView;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

final class AuthActionTest extends TestCase
{
    private function createAction(Collection $collection): AuthAction
    {
        return new AuthAction(
            $collection,
            new Aliases(),
            new WebView(),
            new Psr17Factory(),
        );
    }

    private function setCallback(AuthAction $action, string $property, callable $callback): void
    {
        (new ReflectionProperty($action, $property))->setValue($action, $callback);
    }

    private function createTestClient(?ClientInterface $httpClient = null): OAuth2
    {
        $client = new TestClient(
            $httpClient ?? $this->createStub(ClientInterface::class),
            new Psr17Factory(),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );

        return $client->withoutValidateAuthState();
    }

    private function createRequestHandlerStub(): RequestHandlerInterface
    {
        return $this->createStub(RequestHandlerInterface::class);
    }

    public function testProcessReturnsNotFoundWithoutClientIdAttribute(): void
    {
        $action = $this->createAction(new Collection([]));
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.com/auth');

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame(404, $response->getStatusCode());
        // Distinguishes the "no client id at all" branch from the "unknown client" branch,
        // which produces a reason phrase containing "Unknown auth client".
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testProcessReturnsNotFoundForUnknownClient(): void
    {
        $action = $this->createAction(new Collection([]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'unknown');

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('unknown', $response->getReasonPhrase());
    }

    public function testProcessRedirectsToAuthUrlWhenNoCodeOrError(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test');

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://test.local', $response->getHeaderLine('Location'));
    }

    public function testProcessThrowsGenericExceptionForNonAccessDeniedError(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'invalid_request', 'error_description' => 'bad request']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Auth error: bad request');

        $action->process($request, $this->createRequestHandlerStub());
    }

    public function testProcessInvokesCancelCallbackOnAccessDenied(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]))->withCancelUrl('http://cancel.local');
        $receivedClient = null;
        $this->setCallback($action, 'cancelCallback', function (AuthClientInterface $c) use (&$receivedClient) {
            $receivedClient = $c;
            return null;
        });
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'access_denied']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame($client, $receivedClient);
        $this->assertStringContainsString('http://cancel.local', (string) $response->getBody());
    }

    public function testProcessReturnsCancelCallbackResponseDirectly(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $customResponse = (new Psr17Factory())->createResponse(418);
        $this->setCallback($action, 'cancelCallback', fn () => $customResponse);
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'access_denied']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame($customResponse, $response);
    }

    public function testProcessThrowsWhenCancelCallbackNotConfigured(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'access_denied']);

        $this->expectException(InvalidConfigException::class);

        $action->process($request, $this->createRequestHandlerStub());
    }

    public function testProcessInvokesSuccessCallbackOnValidCode(): void
    {
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'access_token=abc123&token_type=bearer&expires_in=3600');
            }
        };
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $action = $this->createAction(new Collection(['test' => $client]))->withSuccessUrl('http://success.local');
        $receivedClient = null;
        $this->setCallback($action, 'successCallback', function (AuthClientInterface $c) use (&$receivedClient) {
            $receivedClient = $c;
            return null;
        });
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['code' => 'auth-code']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame($client, $receivedClient);
        $this->assertStringContainsString('http://success.local', (string) $response->getBody());
    }

    public function testProcessCancelsWhenTokenExchangeYieldsNoToken(): void
    {
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'token_type=bearer');
            }
        };
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $action = $this->createAction(new Collection(['test' => $client]))->withCancelUrl('http://cancel.local');
        $cancelCallbackInvoked = false;
        $this->setCallback($action, 'cancelCallback', function () use (&$cancelCallbackInvoked) {
            $cancelCallbackInvoked = true;
            return null;
        });
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['code' => 'auth-code']);

        $action->process($request, $this->createRequestHandlerStub());

        $this->assertTrue($cancelCallbackInvoked);
    }

    public function testWithSuccessUrlReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $action = $this->createAction(new Collection([]));

        $withUrl = $action->withSuccessUrl('http://success.local');

        $this->assertNotSame($action, $withUrl);
    }

    public function testWithCancelUrlReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $action = $this->createAction(new Collection([]));

        $withUrl = $action->withCancelUrl('http://cancel.local');

        $this->assertNotSame($action, $withUrl);
    }

    public function testProcessHandlesNonStringErrorQueryParamWithoutCrashing(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 12345, 'error_message' => '']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Auth error:');

        $action->process($request, $this->createRequestHandlerStub());
    }

    public function testProcessUsesErrorMessageFallbackWhenErrorDescriptionMissing(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'invalid_request', 'error_message' => 'fallback message']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Auth error: fallback message');

        $action->process($request, $this->createRequestHandlerStub());
    }

    public function testProcessHandlesNonStringCodeQueryParam(): void
    {
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'access_token=abc123&expires_in=3600');
            }
        };
        $client = $this->createTestClient($httpClient);
        $client->setTokenUrl('http://token.local');
        $client->setClientSecret('secret');
        $action = $this->createAction(new Collection(['test' => $client]))->withSuccessUrl('http://success.local');
        $this->setCallback($action, 'successCallback', fn () => null);
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['code' => 12345]);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertStringContainsString('http://success.local', (string) $response->getBody());
    }

    public function testProcessThrowsWithExactMessageWhenCancelCallbackNotConfigured(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'access_denied']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"' . AuthAction::class . '::$successCallback" should be a valid callback.');

        $action->process($request, $this->createRequestHandlerStub());
    }

    public function testCancelRedirectDisablesEnforceRedirectUnlikeSuccessRedirect(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]))->withCancelUrl('http://cancel.local');
        $this->setCallback($action, 'cancelCallback', fn () => null);
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'access_denied']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertStringContainsString('false', (string) $response->getBody());
    }

    public function testSuccessRedirectEnforcesRedirectByDefault(): void
    {
        $action = $this->createAction(new Collection([]))->withSuccessUrl('http://success.local');

        $response = (new \ReflectionMethod($action, 'redirectSuccess'))->invoke($action);

        $this->assertStringContainsString('true);', (string) $response->getBody());
    }

    public function testProcessRedirectsToAuthUrlWhenErrorQueryParamIsEmptyString(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => '']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://test.local', $response->getHeaderLine('Location'));
    }

    public function testProcessRedirectsToAuthUrlWhenCodeQueryParamIsEmptyString(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['code' => '']);

        $response = $action->process($request, $this->createRequestHandlerStub());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://test.local', $response->getHeaderLine('Location'));
    }

    public function testProcessCastsErrorMessageToStringBeforeElvisCheck(): void
    {
        $client = $this->createTestClient();
        $action = $this->createAction(new Collection(['test' => $client]));
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://example.com/auth')
            ->withAttribute('authclient', 'test')
            ->withQueryParams(['error' => 'invalid_request', 'error_message' => -0.0]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Auth error: -0');

        $action->process($request, $this->createRequestHandlerStub());
    }
}
