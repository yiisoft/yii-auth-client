OpenID Connect
==============

This extension provides support for [OpenId Connect](https://openid.net/connect/) authentication protocol via
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]] class.

Application configuration example (see [Installation](installation.md) for the general pattern):

```php
// config/common/params.php
'yiisoft/yii-auth-client' => [
    'clients' => [
        'google' => [
            'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
            'title' => 'Google OpenID Connect',
            'issuerUrl' => 'https://accounts.google.com',
            'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
        ],
    ],
],
```

The array key (`'google'`) is used as the client's `name`, so an application can register several
differently-named `OpenIdConnect` providers (Auth0, Okta, ...) side by side without them colliding on
session state or cached discovery data — see the [Installation](installation.md) guide's `auth0`/`okta`
example.

Authentication workflow is exactly the same as for OAuth2.

**Heads up!** 'OpenID Connect' protocol uses [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature) verification
for securing the authentication process. This extension requires the [`web-token/jwt-framework`](https://github.com/web-token/jwt-framework)
library for such verification; it is a regular `composer.json` dependency of this package, so no extra installation
step is needed.

> Note: if you are using a well-trusted 'OpenID Connect' provider, you may call
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withoutValidateJws()]] on the resolved client instance to skip
  JWS validation, however it is not recommended as it violates the protocol specification. This is a wither
  (it returns a new instance rather than mutating the client), so it cannot be set via the `clients` params
  array — that only maps config keys to `set*()` methods, see [Installation](installation.md). Use
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withValidateJws()]] to switch it back on.
