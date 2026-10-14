Setup HTTP Client
=================

This extension sends its HTTP requests (auth flow, token exchange, `api()` calls) through a [PSR-18](https://www.php-fig.org/psr/psr-18/)
`Psr\Http\Client\ClientInterface`, injected into every [[\Yiisoft\Yii\AuthClient\AuthClient]] via its constructor.
The package itself only depends on the PSR-18 interface (`psr/http-client`) - your application must provide a
concrete implementation (e.g. [Guzzle](https://github.com/guzzle/guzzle) via `php-http/guzzle7-adapter`, or
[Buzz](https://github.com/kriswallsmith/buzz)) and bind it in your DI container.

All auth clients share the same `Psr\Http\Client\ClientInterface` instance, resolved from the container when
`Collection` is built - binding it once in `config/common/di.php` configures the HTTP client used by every
configured auth client at once:

```php
use Psr\Http\Client\ClientInterface;
use Buzz\Client\Curl;

return [
    ClientInterface::class => Curl::class,
];
```

Every entry in the `clients` params array (see [Installation](installation.md)) is built with this same
system-wide `ClientInterface` binding, so a custom timeout or proxy needed by only one provider has to be
configured on that shared client (or handled by the client implementation itself, e.g. a middleware-based
PSR-18 client that branches on the request URI).

Likewise, a PSR-17 `Psr\Http\Message\RequestFactoryInterface` is required to build outgoing requests
(`AuthClient::createRequest()`); bind an implementation for that interface the same way.

Custom response formats
-----------------------

For a provider that returns JSONP, wrap the injected PSR-18 client with a decorator that converts
the known response format to JSON. Auth clients will then read the converted response body through
their normal JSON decoder. For example, this decorator handles `callback({"openid":"123"});` only
at the provider's UserInfo endpoint:

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class JsonpClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->httpClient->sendRequest($request);
        $endpoint = (string) $request->getUri()->withQuery('')->withFragment('');
        if (
            $endpoint !== 'https://provider.example/userinfo'
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
        ) {
            return $response;
        }

        $content = (string) $response->getBody();
        if (preg_match('/\A\s*callback\s*\((.*)\)\s*;?\s*\z/s', $content, $matches) === 1) {
            $content = $matches[1];
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Length', (string) strlen($content));
        }

        return $response->withBody($this->streamFactory->createStream($content));
    }
}
```

The replacement stream starts at the beginning, so the auth client's subsequent `getContents()` call
receives the full body even after the decorator has read it. Ordinary JSON is preserved. Non-successful
responses and other endpoints, including the token endpoint, are returned untouched. Transport
exceptions propagate from the wrapped client.

Adjust the endpoint and wrapper to the provider's documented response format. The decorator removes
only the known wrapper; it does not execute JavaScript. Malformed JSON inside the wrapper still fails
when the UserInfo response is decoded. The underlying HTTP client should handle response decompression.
When replacing a body, keep its headers consistent, as required by
[PSR-18](https://www.php-fig.org/psr/psr-18/).

For example, with Buzz and a PSR-17 stream factory already registered in your container, bind the
decorator in `config/common/di.php`:

```php
use App\Auth\JsonpClient;
use Buzz\Client\Curl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;

return [
    ClientInterface::class => static fn(Curl $transport, StreamFactoryInterface $streamFactory): ClientInterface
        => new JsonpClient($transport, $streamFactory),
];
```

Inject the concrete transport into this factory; resolving `ClientInterface` there would depend on
the very service being created. The shared decorator can serve all configured providers because it
only transforms responses from the specified endpoint.
