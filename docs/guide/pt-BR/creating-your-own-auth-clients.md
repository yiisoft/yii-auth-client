Creating your own auth clients
==============================

You may create your own auth client for any external auth provider, which supports
OpenId or OAuth protocol. To do so, first of all, you need to find out which protocol is
supported by the external auth provider, this will give you the name of the base class
for your extension:

 - For OAuth 2 use [[Yiisoft\Yii\AuthClient\OAuth2]].
 - For OAuth 1/1.0a use [[Yiisoft\Yii\AuthClient\OAuth1]].
 - For OpenID use [[Yiisoft\Yii\AuthClient\OpenId]].

At this stage you can determine auth client default name, title and view options, declaring
corresponding methods:

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

Depending on actual base class, you will need to redeclare different fields and methods.

## [[Yiisoft\Yii\AuthClient\OAuth2]]

You will need to specify:

- Auth URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth2::authUrl|authUrl]] field.
- Token request URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth2::tokenUrl|tokenUrl]] field.
- API base URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth2::apiBaseUrl|apiBaseUrl]] field.
- User attribute fetching strategy by redeclaring [[Yiisoft\Yii\AuthClient\OAuth2::initUserAttributes()|initUserAttributes()]] 
method.

For example:

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

You may also specify default auth scopes.

> Note: Some OAuth providers may not follow OAuth standards clearly, introducing
  differences, and may require additional efforts to implement clients for.

## [[Yiisoft\Yii\AuthClient\OAuth1]]

You will need to specify:

- Auth URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth1::authUrl|authUrl]] field.
- Request token URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth1::requestTokenUrl|requestTokenUrl]] field.
- Access token URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth1::accessTokenUrl|accessTokenUrl]] field.
- API base URL by redeclaring [[Yiisoft\Yii\AuthClient\OAuth1::apiBaseUrl|apiBaseUrl]] field.
- User attribute fetching strategy by redeclaring [[Yiisoft\Yii\AuthClient\OAuth1::initUserAttributes()|initUserAttributes()]] 
method.

For example:

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

You may also specify default auth scopes.

> Note: Some OAuth providers may not follow OAuth standards clearly, introducing
  differences, and may require additional efforts to implement clients for.

