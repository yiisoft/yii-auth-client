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

После установки расширения настройте нужные клиенты аутентификации в параметрах приложения.

`config/common/params.php` (объединяется с собственным `params.php` пакета):

```php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'google' => [
                'class' => Yiisoft\Yii\AuthClient\Client\Google::class,
                'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
                'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
                // Обязательно: у oauth2ReturnUrl нет запасного варианта, определяемого из запроса,
                // поэтому не заданный redirect_uri отправляется пустым. Google отклоняет такой запрос
                // ("Missing required parameter: redirect_uri"); значение должно точно совпадать с
                // callback-адресом, зарегистрированным в Google Cloud Console.
                'oauth2ReturnUrl' => 'https://example.com/auth/google',
            ],
            'facebook' => [
                'class' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
                'clientId' => $_ENV['FACEBOOK_CLIENT_ID'],
                'clientSecret' => $_ENV['FACEBOOK_CLIENT_SECRET'],
            ],
            // Поддерживается несколько экземпляров одного и того же класса:
            'auth0' => [
                'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
                'title' => 'Auth0',
                'clientId' => $_ENV['AUTH0_CLIENT_ID'],
                'clientSecret' => $_ENV['AUTH0_CLIENT_SECRET'],
                'issuerUrl' => 'https://your-tenant.auth0.com/',
            ],
            'okta' => [
                'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
                'title' => 'Okta',
                'clientId' => $_ENV['OKTA_CLIENT_ID'],
                'clientSecret' => $_ENV['OKTA_CLIENT_SECRET'],
                'issuerUrl' => 'https://your-org.okta.com/',
            ],
        ],
    ],
];
```

Ключ массива (`'google'`, `'facebook'` и т.д.) используется в качестве `name` клиента. Остальные ключи
соответствуют методам-сеттерам класса клиента — доступные сеттеры смотрите в API нужного класса:

| Ключ конфигурации | Сеттер | Доступно для |
|-----------|--------|-------------|
| `title` | `setTitle()` | Все клиенты |
| `clientId` | `setClientId()` | Все OAuth2-клиенты |
| `clientSecret` | `setClientSecret()` | Все OAuth2-клиенты, кроме VKontakte (только PKCE, секрет не отправляется) |
| `oauth2ReturnUrl` | `setOauth2ReturnUrl()` | Все OAuth2-клиенты |
| `scope` | `setScope()` | Все OAuth-клиенты |
| `authUrl` | `setAuthUrl()` | Все OAuth-клиенты |
| `tokenUrl` | `setTokenUrl()` | Все OAuth2-клиенты |
| `authParams` | `setAuthParams()` | Все OAuth2-клиенты |
| `returnUrl` | `setReturnUrl()` | Все OAuth-клиенты |
| `issuerUrl` | `setIssuerUrl()` | OpenIdConnect |
| `validateAuthNonce` | `setValidateAuthNonce()` | OpenIdConnect |
| `tenant` | `setTenant()` | Microsoft |

Из коробки предоставляются следующие клиенты (все в пространстве имён `Yiisoft\Yii\AuthClient\Client`):

- [[\Yiisoft\Yii\AuthClient\Client\Discord|Discord]].
- [[\Yiisoft\Yii\AuthClient\Client\Facebook|Facebook]].
- [[\Yiisoft\Yii\AuthClient\Client\GitHub|GitHub]].
- [[\Yiisoft\Yii\AuthClient\Client\Google|Google]].
- [[\Yiisoft\Yii\AuthClient\Client\LinkedIn|LinkedIn]].
- [[\Yiisoft\Yii\AuthClient\Client\Microsoft|Microsoft]].
- [[\Yiisoft\Yii\AuthClient\Client\TikTok|TikTok]].
- [[\Yiisoft\Yii\AuthClient\Client\VKontakte|VKontakte]].
- [[\Yiisoft\Yii\AuthClient\Client\X|X (Twitter)]].
- [[\Yiisoft\Yii\AuthClient\Client\Yandex|Yandex]].
- [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect|OpenIdConnect]] - для любого провайдера, поддерживающего протокол
  OpenID Connect (Auth0, Okta, Google, Microsoft Entra ID и т.д.) - см. руководство [OpenID Connect](open-id-connect.md).

Конфигурация для каждого клиента немного отличается. Для большинства из них требуется ID клиента и секретный
ключ, выданные сервисом, который вы собираетесь использовать — исключение VKontakte, который использует PKCE
и требует только ID клиента.

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
