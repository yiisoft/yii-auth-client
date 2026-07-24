Getting additional data via extra API calls
===========================================

[[\Yiisoft\Yii\AuthClient\OAuth2]] provides a method `api()`, which can be used to access the external auth
provider's REST API once an access token has been obtained.

Requests made through `api()`/`createApiRequest()` are relative to the client's `$endpoint` property, which each
built-in client hardcodes to its provider's API base (e.g. `Google::$endpoint` already points at the userinfo
endpoint). This is most useful in a client you write yourself, see [Creating your own auth clients](creating-your-own-auth-clients.md).
For example:

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

Method [[\Yiisoft\Yii\AuthClient\OAuth::api()]] is very basic: it returns the decoded JSON response body as an
array, or throws [[\Yiisoft\Yii\AuthClient\Exception\InvalidResponseException]] (which exposes the failed
`ResponseInterface` via `getResponse()`) if the response status is not `200`.

If you need more control, use [[\Yiisoft\Yii\AuthClient\OAuth::createApiRequest()]] instead - it returns a PSR-7
`RequestInterface`, so you can shape it with the usual `with*()` methods, and with
[[\Yiisoft\Yii\AuthClient\RequestUtil::addParams()]]/[[\Yiisoft\Yii\AuthClient\RequestUtil::addHeaders()]] to add
several query parameters/headers at once. `createApiRequest()`/`createRequest()` only build the request - sending
it is up to you, via your own PSR-18 `ClientInterface` instance (the auth client's internal HTTP client is not
exposed):

```php
use Psr\Http\Client\ClientInterface;

/** @var MyClient $client */
/** @var ClientInterface $httpClient your own PSR-18 client */
$request = $client->createApiRequest('GET', 'users');
$request = \Yiisoft\Yii\AuthClient\RequestUtil::addParams($request, ['id' => $userId]);
$request = \Yiisoft\Yii\AuthClient\RequestUtil::addHeaders($request, ['MyHeader' => 'my-value']);

// createApiRequest() does not sign the request; apply the access token before sending, see below
$request = $client->beforeApiRequestSend($request);

$response = $httpClient->sendRequest($request);
```

Request created via [[\Yiisoft\Yii\AuthClient\OAuth::createApiRequest()]] still needs the access token applied
before being sent - `api()` does this automatically via `beforeApiRequestSend()`. If you build the request via
[[\Yiisoft\Yii\AuthClient\AuthClient::createRequest()]] directly instead, you're responsible for calling
[[\Yiisoft\Yii\AuthClient\OAuth2::applyAccessTokenToRequest()]] yourself:

```php
/** @var MyClient $client */
/** @var ClientInterface $httpClient your own PSR-18 client */
$request = $client->createRequest('GET', 'https://www.my.com/apis/oauth2/v1/users');

$token = $client->getAccessToken();
if ($token !== null) {
    $request = $client->applyAccessTokenToRequest($request, $token); // use custom access token for API
}

$response = $httpClient->sendRequest($request);
```
