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

There is no per-client override for this - every entry in the `clients` params array (see
[Installation](installation.md)) is built with the same system-wide `ClientInterface` binding, so a custom
timeout or proxy needed by only one provider has to be configured on that shared client (or handled by the
client implementation itself, e.g. a middleware-based PSR-18 client that branches on the request URI).

Likewise, a PSR-17 `Psr\Http\Message\RequestFactoryInterface` is required to build outgoing requests
(`AuthClient::createRequest()`); bind an implementation for that interface the same way.
