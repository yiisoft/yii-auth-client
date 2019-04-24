Получение дополнительных данных с помощью дополнительных обращений к API
========================================================================

Оба клиента, [[Yiisoft\Yii\AuthClient\OAuth1]] и [[Yiisoft\Yii\AuthClient\OAuth2]], обеспечивают метод `api()`, который может быть
использован для доступа к REST API внешнего сервиса аутентификации.

Что бы использовать дополнительные обращения к API, необходимо настроить [[Yiisoft\Yii\AuthClient\BaseOAuth::apiBaseUrl]] в
соответствии со спецификацией API. Тогда Вы сможете вызвать метод [[Yiisoft\Yii\AuthClient\BaseOAuth::api()]]:

```php
use Yiisoft\Yii\AuthClient\OAuth2;

$client = new OAuth2;

// ...

$client->apiBaseUrl = 'https://www.googleapis.com/oauth2/v1';
$userInfo = $client->api('userinfo', 'GET');
```

Метод [[\Yiisoft\Yii\AuthClient\BaseOAuth::api()]] очень простой и не предоставляет достаточную гибкость необходимую для
некоторых API команд. Вмето него вы можете воспользоваться методом [[\Yiisoft\Yii\AuthClient\BaseOAuth::createApiRequest()]] -
он создает экземпляр [[\yii\httpclient\Request]], который дает вам гораздо больший контроль над построением HTTP запроса.
Например:

```php
/* @var $client \Yiisoft\Yii\AuthClient\OAuth2 */
$client = Yii::getApp()->authClientCollection->getClient('someOAuth2');

// находим пользователя для добавлениея во внешний сервис:
$user = User::find()->andWhere(['email' => 'johndoe@domain.com'])->one();

$response = $client->createApiRequest()
    ->setMethod('GET')
    ->setUrl('users')
    ->setData([
        'id' => $user->id,
    ])
    ->send();

if ($response->statusCode != 404) {
    throw new \Exception('User "johndoe@domain.com" already exist');
}

$response = $client->createApiRequest()
    ->setMethod('PUT')
    ->setUrl('users')
    ->setData($user->attributes)
    ->addHeaders([
        'MyHeader' => 'my-value'
    ])
    ->send();

if (!$response->isOk) {
    // failure
}
echo $response->data['id'];
```

Пожалуйста ознакомтесь с документацией по [yii2-httpclient](https://github.com/yiisoft/yii2-httpclient) для выяснения
деталей о построении HTTP запросов.

Запросы, созданные через [[\Yiisoft\Yii\AuthClient\BaseOAuth::createApiRequest()]], будут автоматичеси подписаны (в случае
использования OAuth 1.0) и, к ним будет применен маркер доступа (access token), до того как они будут оправлены.
Если вы желаете получить полный контроль за этими процессами, вам следует использовать [[\Yiisoft\Yii\AuthClient\BaseClient::createRequest()]].
Вы можете воспользоваться методами [[\Yiisoft\Yii\AuthClient\BaseOAuth::applyAccessTokenToRequest()]] и [[Yiisoft\Yii\AuthClient\OAuth1::signRequest()]]
для проведения недостающих операций до отправки API запроса.
Например:

```php
/* @var $client \Yiisoft\Yii\AuthClient\OAuth1 */
$client = Yii::getApp()->authClientCollection->getClient('someOAuth1');

$request = $client->createRequest()
    ->setMethod('GET')
    ->setUrl('users');

$client->applyAccessTokenToRequest($request, $myAccessToken); // use custom access token for API
$client->signRequest($request, $myAccessToken); // sign request with custom access token

$response = $request->send();
```
