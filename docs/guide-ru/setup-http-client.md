Настройка HTTP клиента
======================

Расширение использует [yii2-httpclient](https://github.com/yiisoft/yii2-httpclient) для отправки HTTP запросов.
Вам может понадобиться изменить конфигурацию по умолчанию для используемого HTTP клиента, например, в случае если вам
нужно использовать особый транспорт для запросов.

Каждый Auth клиент имеет свойство `httpClient`, которое может быть использовано для задания HTTP клиента для Auth клиента.
Например:

```php
use Yiisoft\Yii\AuthClient\Google;

$authClient = new Google([
    'httpClient' => [
        'transport' => 'yii\httpclient\CurlTransport',
    ],
]);
```

В случае, если вы используете компонент [[\Yiisoft\Yii\AuthClient\Collection]], вы можете воспользоваться его свойством `httpClient`
для задания конфигурации HTTP клиента для всех внутренних Auth клиентов.
Пример конфигурации приложения:

```php
return [
    'components' => [
        'authClientCollection' => [
            '__class' => Yiisoft\Yii\AuthClient\Collection::class,
            // все Auth клиенты будут использовать эту конфигурацию для HTTP клиента:
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
