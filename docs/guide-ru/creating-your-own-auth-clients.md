Создание собственных клиентов аутентификации
==============================

Вы можете создать собственный клиент для любого внешнего сервиса аутентификации, который поддерживает протокол OpenId 
или OAuth. Для этого, в первую очередь, необходимо выяснить, какой протокол поддерживает внешний сервис аутентификации, 
что даст Вам имя базового класса для расширения:

 - Для OAuth 2 используйте [[Yiisoft\Yii\AuthClient\OAuth2]].
 - Для OAuth 1/1.0a используйте [[Yiisoft\Yii\AuthClient\OAuth1]].
 - Для OpenID используйте [[Yiisoft\Yii\AuthClient\OpenId]].

На данном этапе можно установить для клиента аутентификации базовые значения имени, заголовка и параметров 
представления, переопределив соответствующие методы:

```php
use Yiisoft\Yii\AuthClient\OAuth2;

class MyAuthClient extends OAuth2
{
    protected function defaultName()
    {
        return 'my_auth_client';
    }

    protected function defaultTitle()
    {
        return 'My Auth Client';
    }

    protected function defaultViewOptions()
    {
        return [
            'popupWidth' => 800,
            'popupHeight' => 500,
        ];
    }
}
```

В зависимости от актуального базового класса, Вам нужно будет переопределить различные свойства и методы.

## [[Yiisoft\Yii\AuthClient\OpenId]]

Всё, что Вам нужно - это задать URL аутентификации, путём определения свойства 
[[Yiisoft\Yii\AuthClient\OpenId::authUrl|authUrl]].
Вы так же можете настроить обязательные и/или дополнительные атрибуты по умолчанию.
Например:

```php
use Yiisoft\Yii\AuthClient\AbstractOpenId;

class MyAuthClient extends AbstractOpenId
{
    public $authUrl = 'https://www.my.com/openid/';

    public $requiredAttributes = [
        'contact/email',
    ];

    public $optionalAttributes = [
        'namePerson/first',
        'namePerson/last',
    ];
}
```

## [[Yiisoft\Yii\AuthClient\OAuth2]]

Вам нужно будет указать:

- URL аутентификации путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth2::authUrl|authUrl]].
- URL получения токена путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth2::tokenUrl|tokenUrl]].
- Базовый URL к API путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth2::apiBaseUrl|apiBaseUrl]].
- Стратегии извлечения пользовательских атрибутов путём определения метода 
[[Yiisoft\Yii\AuthClient\OAuth2::initUserAttributes()|initUserAttributes()]].

Например:

```php
use Yiisoft\Yii\AuthClient\OAuth2;

class MyAuthClient extends OAuth2
{
    public $authUrl = 'https://www.my.com/oauth2/auth';

    public $tokenUrl = 'https://www.my.com/oauth2/token';

    public $apiBaseUrl = 'https://www.my.com/apis/oauth2/v1';

    protected function initUserAttributes()
    {
        return $this->api('userinfo', 'GET');
    }
}
```

Вы так же можете указать области доступа аутентификации по умолчанию.

> Примечание: Некоторые  OAuth сервисы могут не следовать четким стандартам протокола OAuth, имея отличия, что может 
потребовать дополнительных усилий при реализации клиентов для таких сервисов.

## [[Yiisoft\Yii\AuthClient\OAuth1]]

Вам нужно будет указать:

- URL аутентификации путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth1::authUrl|authUrl]].
- URL получения токена путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth1::requestTokenUrl|requestTokenUrl]].
- URL получения токена доступа путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth1::accessTokenUrl|accessTokenUrl]].
- Базовый URL к API путём определения свойства [[Yiisoft\Yii\AuthClient\OAuth1::apiBaseUrl|apiBaseUrl]].
- Стратегии извлечения пользовательских атрибутов путём определения метода 
[[Yiisoft\Yii\AuthClient\OAuth1::initUserAttributes()|initUserAttributes()]].

Например:

```php
use Yiisoft\Yii\AuthClient\OAuth1;

class MyAuthClient extends OAuth1
{
    public $authUrl = 'https://www.my.com/oauth/auth';

    public $requestTokenUrl = 'https://www.my.com/oauth/request_token';

    public $accessTokenUrl = 'https://www.my.com/oauth/access_token';

    public $apiBaseUrl = 'https://www.my.com/apis/oauth/v1';

    protected function initUserAttributes()
    {
        return $this->api('userinfo', 'GET');
    }
}
```

Вы так же можете указать области доступа аутентификации по умолчанию.

> Примечание: Некоторые  OAuth сервисы могут не следовать четким стандартам протокола OAuth, имея отличия, что может 
потребовать дополнительных усилий при реализации клиентов для таких сервисов.

