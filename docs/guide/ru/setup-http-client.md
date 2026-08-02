Настройка HTTP клиента
======================

Расширение отправляет HTTP-запросы (процесс аутентификации, обмен токена, вызовы `api()`) через
[PSR-18](https://www.php-fig.org/psr/psr-18/) `Psr\Http\Client\ClientInterface`, внедряемый в каждый
[[\Yiisoft\Yii\AuthClient\AuthClient]] через конструктор. Сам пакет зависит только от интерфейса PSR-18
(`psr/http-client`) - ваше приложение должно предоставить конкретную реализацию (например,
[Guzzle](https://github.com/guzzle/guzzle) через `php-http/guzzle7-adapter`, или
[Buzz](https://github.com/kriswallsmith/buzz)) и привязать её в DI-контейнере.

Все клиенты аутентификации используют один и тот же экземпляр `Psr\Http\Client\ClientInterface`, полученный
из контейнера при построении `Collection` - привязка его один раз в `config/common/di.php` настраивает HTTP
клиент сразу для всех настроенных клиентов аутентификации:

```php
use Psr\Http\Client\ClientInterface;
use Buzz\Client\Curl;

return [
    ClientInterface::class => Curl::class,
];
```

Каждая запись в массиве `clients` (см. [Установка](installation.md)) строится с этой же системной привязкой
`ClientInterface`, поэтому особый таймаут или прокси, нужный только одному провайдеру, нужно настраивать на
самом общем клиенте (либо реализовать в самом PSR-18 клиенте, например через middleware, различающий запросы
по URI).

Аналогично, для построения исходящих запросов (`AuthClient::createRequest()`) требуется PSR-17
`Psr\Http\Message\RequestFactoryInterface`; привяжите реализацию этого интерфейса таким же образом.
