Setup HTTP Client
=================

This extension sends its HTTP requests (auth flow, token exchange, `api()` calls) through a [PSR-18](https://www.php-fig.org/psr/psr-18/)
`Psr\Http\Client\ClientInterface`, injected into every [[\Yiisoft\Yii\AuthClient\AuthClient]] via its constructor.
The package itself only depends on the PSR-18 interface (`psr/http-client`) - your application must provide a
concrete implementation (e.g. [Guzzle](https://github.com/guzzle/guzzle) via `php-http/guzzle7-adapter`, or
[Buzz](https://github.com/kriswallsmith/buzz)) and bind it in your DI container.

Since all auth clients share the same `Psr\Http\Client\ClientInterface` constructor dependency, binding it once
in `config/common/di.php` configures the HTTP client used by every auth client at once:

```php
use Psr\Http\Client\ClientInterface;
use Buzz\Client\Curl;

return [
    ClientInterface::class => Curl::class,
];
```

If a specific client needs a different HTTP client (a custom timeout, proxy, etc.), override just that
constructor argument in its own definition:

```php
use Yiisoft\Yii\AuthClient\Client\Google;

return [
    Google::class => [
        '__construct()' => [
            'httpClient' => MyCustomPsr18Client::class,
        ],
        'setClientId()' => [$_ENV['GOOGLE_CLIENT_ID']],
        'setClientSecret()' => [$_ENV['GOOGLE_CLIENT_SECRET']],
    ],
];
```

Likewise, a PSR-17 `Psr\Http\Message\RequestFactoryInterface` is required to build outgoing requests
(`AuthClient::createRequest()`); bind an implementation for that interface the same way.
