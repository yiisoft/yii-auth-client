Setup HTTP Client
=================

This extension uses [yii2-httpclient](https://github.com/yiisoft/yii2-httpclient) for HTTP requests.
You may need to adjust default HTTP client configuration to be used, for example, in case you need to use
special request transport.

Each Auth client has a property `httpClient`, which can be used to setup HTTP client used by Auth client.
For example:

```php
use yii\authclient\Google;

$authClient = new Google([
    'httpClient' => [
        'transport' => \yii\httpclient\CurlTransport::class,
    ],
]);
```

In case you are using [[\yii\authclient\Collection]] component, you can use its property `httpClient` to setup
HTTP client configuration to all internal Auth clients at once.
Application configuration example:

```php
return [
    'components' => [
        'authClientCollection' => [
            'class' => yii\authclient\Collection::class,
            // all Auth clients will use this configuration for HTTP client:
            'httpClient' => [
                'transport' => yii\httpclient\CurlTransport::class,
            ],
            'clients' => [
                'google' => [
                    'class' => yii\authclient\clients\Google::class,
                    'clientId' => 'google_client_id',
                    'clientSecret' => 'google_client_secret',
                ],
                'facebook' => [
                    'class' => yii\authclient\clients\Facebook::class,
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
