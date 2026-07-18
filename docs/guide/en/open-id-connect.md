OpenID Connect
==============

This extension provides support for [OpenId Connect](https://openid.net/connect/) authentication protocol via
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]] class.

Application configuration example (see [Installation](installation.md) for the general pattern):

```php
// config/common/params.php
'yiisoft/yii-auth-client' => [
    'clients' => [
        'google' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
    ],
],

// config/common/di.php
Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class => [
    '__construct()' => [
        'name' => 'google',
        'title' => 'Google OpenID Connect',
    ],
    'setIssuerUrl()' => ['https://accounts.google.com'],
    'setClientId()' => [$_ENV['GOOGLE_CLIENT_ID']],
    'setClientSecret()' => [$_ENV['GOOGLE_CLIENT_SECRET']],
],
```

`name` and `title` are constructor arguments (not settable afterwards) precisely so an application can register
several differently-named `OpenIdConnect` providers (Auth0, Okta, ...) side by side without them colliding on
session state or cached discovery data.

Authentication workflow is exactly the same as for OAuth2.

**Heads up!** 'OpenID Connect' protocol uses [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature) verification
for securing the authentication process. This extension requires the [`web-token/jwt-framework`](https://github.com/web-token/jwt-framework)
library for such verification; it is a regular `composer.json` dependency of this package, so no extra installation
step is needed.

> Note: if you are using a well-trusted 'OpenID Connect' provider, you may call
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withoutValidateJws()]] (e.g. `'withoutValidateJws()' => []` in
  the client's DI definition above) to skip JWS validation, however it is not recommended as it violates the
  protocol specification. Use [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withValidateJws()]] to switch it
  back on.
