OpenID Connect
==============

This extension provides support for [OpenId Connect](https://openid.net/connect/) authentication protocol via
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]] class.

Application configuration example:

```php
'components' => [
    'authClientCollection' => [
        'class' => Yiisoft\Yii\AuthClient\Collection::class,
        'clients' => [
            'google' => [
                'class' => Yiisoft\Yii\AuthClient\OpenIdConnect::class,
                'issuerUrl' => 'https://accounts.google.com',
                'clientId' => 'google_client_id',
                'clientSecret' => 'google_client_secret',
                'name' => 'google',
                'title' => 'Google OpenID Connect',
            ],
        ],
    ]
    // ...
]
```

Authentication workflow is exactly the same as for OAuth2.

**Heads up!** 'OpenID Connect' protocol uses [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature) verification
for the authentication process securing. You will need to install `spomky-labs/jose` library, which is not required by this
extension by default, in order to use such verification. This can be done via composer:

```
composer require "spomky-labs/jose:~5.0.6"
```

or add

```json
"spomky-labs/jose": "~5.0.6"
```

to the `require` section of your composer.json.

> Note: if you are using well-trusted 'OpenID Connect' provider, you may disable [[\Yiisoft\Yii\AuthClient\OpenIdConnect::$validateJws]],
  making installation of `spomky-labs/jose` library redundant, however it is not recommended as it violates the protocol specification.
