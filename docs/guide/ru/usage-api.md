Получение дополнительных данных с помощью дополнительных обращений к API
========================================================================

[[\Yiisoft\Yii\AuthClient\OAuth2]] предоставляет метод `api()`, который можно использовать для доступа к REST API
внешнего сервиса аутентификации после получения токена доступа.

Запросы через `api()`/`createApiRequest()` выполняются относительно свойства `$endpoint` клиента, которое каждый
встроенный клиент жёстко задаёт под базовый URL API своего провайдера (например, `Google::$endpoint` уже
указывает на endpoint получения информации о пользователе). Это наиболее полезно в собственном клиенте, см.
[Создание собственных клиентов аутентификации](creating-your-own-auth-clients.md). Например:

```php
use Yiisoft\Yii\AuthClient\OAuth2;

final class MyClient extends OAuth2
{
    protected string $endpoint = 'https://www.my.com/apis/oauth2/v1/';

    // ...
}

/** @var MyClient $client */
$userInfo = $client->api('userinfo', 'GET');
```

Метод [[\Yiisoft\Yii\AuthClient\OAuth::api()]] очень простой: он возвращает декодированное тело JSON-ответа в
виде массива, либо выбрасывает [[\Yiisoft\Yii\AuthClient\Exception\InvalidResponseException]] (предоставляющий
неудачный `ResponseInterface` через `getResponse()`), если статус ответа отличен от `200`.

Если нужен больший контроль, используйте вместо него [[\Yiisoft\Yii\AuthClient\OAuth::createApiRequest()]] - он
возвращает PSR-7 `RequestInterface`, который можно донастроить обычными методами `with*()`, а также при помощи
[[\Yiisoft\Yii\AuthClient\RequestUtil::addParams()]]/[[\Yiisoft\Yii\AuthClient\RequestUtil::addHeaders()]] для
добавления нескольких GET-параметров/заголовков сразу. `createApiRequest()`/`createRequest()` лишь строят запрос -
отправка остаётся за вами, через ваш собственный экземпляр PSR-18 `ClientInterface` (внутренний HTTP клиент
объекта клиента не доступен извне):

```php
use Psr\Http\Client\ClientInterface;

/** @var MyClient $client */
/** @var ClientInterface $httpClient ваш собственный PSR-18 клиент */
$request = $client->createApiRequest('GET', 'users');
$request = \Yiisoft\Yii\AuthClient\RequestUtil::addParams($request, ['id' => $userId]);
$request = \Yiisoft\Yii\AuthClient\RequestUtil::addHeaders($request, ['MyHeader' => 'my-value']);

// createApiRequest() не подписывает запрос; примените токен доступа перед отправкой, см. ниже
$request = $client->beforeApiRequestSend($request);

$response = $httpClient->sendRequest($request);
```

Запрос, созданный через [[\Yiisoft\Yii\AuthClient\OAuth::createApiRequest()]], всё ещё требует применения токена
доступа перед отправкой - `api()` делает это автоматически через `beforeApiRequestSend()`. Если вы строите запрос
напрямую через [[\Yiisoft\Yii\AuthClient\AuthClient::createRequest()]], вы должны самостоятельно вызвать
[[\Yiisoft\Yii\AuthClient\OAuth2::applyAccessTokenToRequest()]]:

```php
/** @var MyClient $client */
/** @var ClientInterface $httpClient ваш собственный PSR-18 клиент */
$request = $client->createRequest('GET', 'https://www.my.com/apis/oauth2/v1/users');

$token = $client->getAccessToken();
if ($token !== null) {
    $request = $client->applyAccessTokenToRequest($request, $token); // использовать особый токен доступа для API
}

$response = $httpClient->sendRequest($request);
```
