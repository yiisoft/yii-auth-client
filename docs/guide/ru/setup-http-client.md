Настройка HTTP клиента
======================

Расширение отправляет HTTP-запросы (процесс аутентификации, обмен токена, вызовы `api()`) через
[PSR-18](https://www.php-fig.org/psr/psr-18/) `Psr\Http\Client\ClientInterface`, внедряемый в каждый
[[\Yiisoft\Yii\AuthClient\AuthClient]] через конструктор. Сам пакет зависит только от интерфейса PSR-18
(`psr/http-client`) - ваше приложение должно предоставить конкретную реализацию (например,
[Guzzle](https://github.com/guzzle/guzzle) через `php-http/guzzle7-adapter`, или
[Buzz](https://github.com/kriswallsmith/buzz)) и привязать её в DI-контейнере.

Так как все клиенты аутентификации используют одну и ту же зависимость конструктора `Psr\Http\Client\ClientInterface`,
привязка её один раз в `config/common/di.php` настраивает HTTP клиент сразу для всех клиентов аутентификации:

```php
use Psr\Http\Client\ClientInterface;
use Buzz\Client\Curl;

return [
    ClientInterface::class => Curl::class,
];
```

Если конкретному клиенту нужен другой HTTP клиент (особый таймаут, прокси и т.д.), переопределите только этот
аргумент конструктора в его собственном определении:

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

Аналогично, для построения исходящих запросов (`AuthClient::createRequest()`) требуется PSR-17
`Psr\Http\Message\RequestFactoryInterface`; привяжите реализацию этого интерфейса таким же образом.
