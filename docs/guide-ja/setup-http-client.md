HTTP クライアントをセットアップする
===================================

このエクステンションは、HTTP リクエストのために [yii2-httpclient](https://github.com/yiisoft/yii2-httpclient) を使用します。
例えば、特別なリクエスト伝送方法を使う必要があるなどに、使用される HTTP クライアントのデフォルト構成を
修正する必要があるでしょう。

各認証クライアントは `httpClient` というプロパティを持っており、これを通じて、認証クライアントによって使用される HTTP クライアントを設定することが出来ます。
例えば、

```php
use Yiisoft\Yii\AuthClient\Google;

$authClient = new Google([
    'httpClient' => [
        'transport' => \yii\httpclient\CurlTransport::class,
    ],
]);
```

[[\Yiisoft\Yii\AuthClient\Collection]] コンポーネントを使うのであれば、その中の全ての認証クライアントに対して、
`httpClient` プロパティを使って HTTP クライアントの構成を一度にまとめてセットアップすることが出来ます。
アプリケーション構成の例を示します。

```php
return [
    'components' => [
        'authClientCollection' => [
            '__class' => Yiisoft\Yii\AuthClient\Collection::class,
            // 全ての認証クライアントは HTTP クライアントにこの構成を使用する
            'httpClient' => [
                'transport' => yii\httpclient\CurlTransport::class,
            ],
            'clients' => [
                'google' => [
                    '__class' => Yiisoft\Yii\AuthClient\Clients\Google::class,
                    'clientId' => 'google_client_id',
                    'clientSecret' => 'google_client_secret',
                ],
                'facebook' => [
                    '__class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
                    'clientId' => 'facebook_client_id',
                    'clientSecret' => 'facebook_client_secret',
                ],
                // etc.
            ],
        ]
        //...
    ],
    // ...
];
```
