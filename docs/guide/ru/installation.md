Установка
============

## Установка расширения

Для установки расширения используйте Composer. Запустите

```
composer require yiisoft/yii-auth-client
```

или добавьте

```json
"yiisoft/yii-auth-client": "^1.0.0"
```

в секцию `require` вашего composer.json.

Пакет поставляется с собственными `config/di.php` и `config/params.php`, которые подключаются автоматически,
если вы используете [yiisoft/config](https://github.com/yiisoft/config). Они регистрируют сервис
[[\Yiisoft\Yii\AuthClient\Collection]], построенный на основе параметра `yiisoft/yii-auth-client.clients`.

## Настройка приложения

После установки расширения перечислите нужные клиенты аутентификации в параметрах приложения и зарегистрируйте
каждый класс клиента как DI-определение, что бы задать его `clientId`/`clientSecret`.

`config/common/params.php` (объединяется с собственным `params.php` пакета):

```php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'google' => Yiisoft\Yii\AuthClient\Client\Google::class,
            'facebook' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
            // и т.д.
        ],
    ],
];
```

`config/common/di.php`:

```php
use Yiisoft\Yii\AuthClient\Client\Facebook;
use Yiisoft\Yii\AuthClient\Client\Google;

return [
    Google::class => [
        'setClientId()' => [$_ENV['GOOGLE_CLIENT_ID']],
        'setClientSecret()' => [$_ENV['GOOGLE_CLIENT_SECRET']],
        // Обязательно: у OAuth2::getOauth2ReturnUrl() нет запасного варианта, определяемого
        // из запроса, поэтому не заданный redirect_uri отправляется пустым. Google отклоняет
        // такой запрос ("Missing required parameter: redirect_uri"); значение должно точно
        // совпадать с callback-адресом, зарегистрированным в Google Cloud Console.
        'setOauth2ReturnUrl()' => ['https://example.com/auth/google'],
    ],
    Facebook::class => [
        'setClientId()' => [$_ENV['FACEBOOK_CLIENT_ID']],
        'setClientSecret()' => [$_ENV['FACEBOOK_CLIENT_SECRET']],
    ],
];
```

Из коробки предоставляются следующие клиенты (все в пространстве имён `Yiisoft\Yii\AuthClient\Client`):

- [[\Yiisoft\Yii\AuthClient\Client\Facebook|Facebook]].
- [[\Yiisoft\Yii\AuthClient\Client\GitHub|GitHub]].
- [[\Yiisoft\Yii\AuthClient\Client\Google|Google]].
- [[\Yiisoft\Yii\AuthClient\Client\LinkedIn|LinkedIn]].
- [[\Yiisoft\Yii\AuthClient\Client\MicrosoftOnline|Microsoft Online]].
- [[\Yiisoft\Yii\AuthClient\Client\TikTok|TikTok]].
- [[\Yiisoft\Yii\AuthClient\Client\VKontakte|VKontakte]].
- [[\Yiisoft\Yii\AuthClient\Client\X|X (Twitter)]].
- [[\Yiisoft\Yii\AuthClient\Client\Yandex|Yandex]].
- [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect|OpenIdConnect]] - для любого провайдера, поддерживающего протокол
  OpenID Connect (Auth0, Okta, Google, Microsoft Entra ID и т.д.) - см. руководство [OpenID Connect](open-id-connect.md).

Конфигурация для каждого клиента немного отличается. Для всех них требуется ID клиента и секретный ключ,
выданные сервисом, который вы собираетесь использовать.

## Хранение данных авторизации

Для того, что бы считать пользователя аутентифицированным при помощи внешнего сервиса, мы должны сохранить ID,
предоставленный при первой аутентификации, а потом проверять его при последующих попытках. Ограничивать варианты
аутентификации только внешними сервисами - не самая лучшая идея, так как такой вид аутентификации может
потерпеть неудачу, не оставив пользователю других вариантов входа. Вместо этого лучше обеспечить как возможность
аутентификации через внешние сервисы, так и обычный вход по логину и паролю.

Если мы храним информацию о пользователях в базе данных, минимальная схема потребует таблицу `user` и таблицу
`auth`, связывающую каждого пользователя с одной или несколькими внешними учётными записями:

- `auth.user_id` - внешний ключ на `user.id`.
- `auth.source` - имя использованного провайдера аутентификации ([[\Yiisoft\Yii\AuthClient\AuthClientInterface::getName()|$client->getName()]],
  например `'google'`).
- `auth.source_id` - уникальный идентификатор пользователя, предоставленный внешним сервисом после успешной
  аутентификации.

Каждый пользователь может пройти аутентификацию, используя несколько внешних сервисов, поэтому каждая запись в
`user` может относиться к нескольким записям в `auth`. Способ создания этих таблиц зависит от инструмента миграций,
используемого в вашем приложении; данный пакет не требует и не поставляет собственный.
