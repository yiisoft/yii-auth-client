OpenID Connect
==============

このエクステンションは [[\yii\authclient\OpenIdConnect]] クラスを通じて、[OpenId Connect](http://openid.net/connect/) 
認証プロトコルのサポートを提供します。

アプリケーション構成例:

```php
'components' => [
    'authClientCollection' => [
        '__class' => yii\authclient\Collection::class,
        'clients' => [
            'google' => [
                '__class' => yii\authclient\OpenIdConnect::class,
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

認証のワークフローは、OAuth2 と全く一緒です。

**警告!** 'OpenID Connect' プロトコルは、認証プロセスを安全にするために、[JWS](http://tools.ietf.org/html/draft-ietf-jose-json-web-signature) 検証を使用します。
この検証を使用するためには、`spomky-labs/jose` ライブラリをインストールする必要があります。このエクステンションはデフォルトではこのライブラリを要求していませんので、
composer によって依存を設定します。

```
composer require --prefer-dist "spomky-labs/jose:~5.0.6"
```

または、composer.json の `require` セクションに

```json
"spomky-labs/jose": "~5.0.6"
```

を追加します。

> Note: 十分に信用できる 'OpenID Connect' プロバイダを使用する場合は、[[\yii\authclient\OpenIdConnect::$validateJws]] を無効にして、`spomky-labs/jose` ライブラリのインストールを不要にすることが出来ます。
  ただし、これはプロトコルの仕様に違反することですので、推奨は出来ません。
