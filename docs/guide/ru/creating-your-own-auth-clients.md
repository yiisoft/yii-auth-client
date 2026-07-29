Создание собственных клиентов аутентификации
==============================

Вы можете создать собственный клиент аутентификации для любого внешнего провайдера, поддерживающего протокол
OAuth 2 (сюда же входят провайдеры OpenID Connect - в этом случае расширяйте
[[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect]], если провайдер публикует стандартный документ обнаружения
`.well-known/openid-configuration`).

Расширьте [[\Yiisoft\Yii\AuthClient\OAuth2]] и определите как минимум:

- `authUrl` - URL авторизации провайдера.
- `tokenUrl` - URL получения токена доступа провайдера.
- `endpoint` - базовый URL API, используемый `api()`/`createApiRequest()` (см.
  [Получение дополнительных данных с помощью дополнительных обращений к API](usage-api.md)).
- `getName()`, `getTitle()`, `getButtonClass()` - требуются интерфейсом [[\Yiisoft\Yii\AuthClient\AuthClientInterface]].
- `getClientId()` - требуется интерфейсом [[\Yiisoft\Yii\AuthClient\OAuth2Interface]]; уже реализован в `OAuth2`
  через свойство `clientId`, задаваемое методом `setClientId()`, поэтому переопределять его обычно не нужно.
- `getCurrentUserJsonArray(OAuthToken $token): array` - по соглашению (используется всеми встроенными клиентами,
  хотя формально не входит в интерфейс) метод, получающий данные аутентифицированного пользователя. `OAuth2`
  предоставляет вспомогательный метод `fetchCurrentUserJsonArray()`, который сам применяет токен как заголовок
  `Authorization: Bearer`.
- `initUserAttributes(): array` - защищённый метод-хук в [[\Yiisoft\Yii\AuthClient\AuthClient]] (по умолчанию
  возвращает пустой массив), из которого строится публичный
  [[\Yiisoft\Yii\AuthClient\AuthClientInterface::getUserAttributes()|getUserAttributes()]]. Переопределите его,
  что бы вызвать ваш `getCurrentUserJsonArray()`, когда токен доступа уже получен - именно так поступают все
  встроенные клиенты.

Например, минимальный клиент для гипотетического провайдера `my.com` с OAuth2:

```php
<?php

declare(strict_types=1);

namespace App\AuthClient;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Override;

final class MyAuthClient extends OAuth2
{
    protected string $authUrl = 'https://www.my.com/oauth2/auth';

    protected string $tokenUrl = 'https://www.my.com/oauth2/token';

    protected string $endpoint = 'https://www.my.com/apis/oauth2/v1';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray($token, $this->endpoint . '/userinfo');
    }

    #[Override]
    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();

        return $token instanceof OAuthToken ? $this->getCurrentUserJsonArray($token) : [];
    }

    #[Override]
    public function getName(): string
    {
        return 'my_auth_client';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'My Auth Client';
    }

    #[Override]
    public function getButtonClass(): string
    {
        return 'btn btn-primary';
    }

    #[Override]
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 800,
            'popupHeight' => 500,
        ];
    }

    #[Override]
    protected function getDefaultScope(): string
    {
        return 'profile email';
    }
}
```

`getDefaultScope()` задаёт scope, запрашиваемый каждым экземпляром вашего клиента. Если вам нужно менять scope
для конкретного настроенного экземпляра без создания ещё одного подкласса, используйте унаследованный метод
`setScope()` (например, `'scope' => '...'` в конфигурации клиента в массиве `clients`, см. ниже), что бы
переопределить его при регистрации.

Затем зарегистрируйте его точно так же, как встроенный клиент (см. [Установка](installation.md)):

```php
// config/common/params.php
'yiisoft/yii-auth-client' => [
    'clients' => [
        'my_auth_client' => [
            'class' => \App\AuthClient\MyAuthClient::class,
            'clientId' => $_ENV['MY_CLIENT_ID'],
            'clientSecret' => $_ENV['MY_CLIENT_SECRET'],
        ],
    ],
],
```

После аутентификации код приложения вызывает `$client->getUserAttributes()` (см. [Быстрый старт](quick-start.md)),
что бы получить данные, возвращённые вашим переопределением `initUserAttributes()`. Если исходные имена полей
провайдера не совпадают с ожидаемыми в приложении, или вы хотите привести данные разных провайдеров к единому
виду, переопределите `defaultNormalizeUserAttributeMap()`, не меняя сам `initUserAttributes()`:

```php
#[Override]
protected function defaultNormalizeUserAttributeMap(): array
{
    return [
        'about' => 'bio',
        'language' => ['languages', 0, 'name'],
        'fullName' => static fn (array $attributes) => $attributes['firstName'] . ' ' . $attributes['lastName'],
    ];
}
```

Каждый элемент связывает нормализованное имя атрибута с исходным именем атрибута (строка), путём во вложенных
исходных атрибутах (массив ключей) или колбэком, принимающим массив исходных атрибутов. `getUserAttributes()`
возвращает исходные атрибуты, объединённые с этими нормализованными.

> Примечание: Некоторые OAuth-провайдеры могут не следовать стандарту OAuth в точности, что может потребовать
  дополнительных усилий при реализации клиента для них.
