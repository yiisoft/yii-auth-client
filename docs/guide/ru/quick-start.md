Быстрый старт
===========

## Регистрация маршрута аутентификации

[[\Yiisoft\Yii\AuthClient\AuthAction]] - это PSR-15 middleware, которое выполняет процесс OAuth2 (перенаправление
к провайдеру, затем обработка его callback-запроса). Настройте его как DI-определение с нужными URL и колбэками,
а затем привяжите к маршруту.

`config/common/di.php`:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\View\WebView;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\AuthClient\Collection;

return [
    AuthAction::class => static fn (
        Collection $clientCollection,
        Aliases $aliases,
        WebView $view,
        ResponseFactoryInterface $responseFactory,
    ) => (new AuthAction($clientCollection, $aliases, $view, $responseFactory))
        ->withSuccessUrl('/site/index')
        ->withCancelUrl('/site/login')
        ->withSuccessCallback(function (AuthClientInterface $client) {
            (new AuthHandler($client))->handle();
        })
        ->withCancelCallback(function (AuthClientInterface $client) {
            // flash-сообщение, логирование и т.д.
        }),
];
```

`config/common/routes.php`:

```php
use Yiisoft\Router\Route;
use Yiisoft\Yii\AuthClient\AuthAction;

return [
    Route::methods(['GET', 'POST'], '/auth/{authclient}')
        ->name('site/auth')
        ->action(AuthAction::class),
];
```

Реализация `AuthHandler` может выглядеть так:

```php
<?php

namespace App\Auth;

use Yiisoft\Yii\AuthClient\AuthClientInterface;

final class AuthHandler
{
    public function __construct(
        private readonly AuthClientInterface $client,
    ) {
    }

    public function handle(): void
    {
        $attributes = $this->client->getUserAttributes();
        $email = $attributes['email'] ?? null;
        $id = $attributes['id'] ?? null;

        // ищем существующую запись `auth` с [source => $this->client->getName(), source_id => $id]
        // (см. раздел "Хранение данных авторизации" в руководстве по установке)
        $auth = $this->findAuth($this->client->getName(), $id);

        if ($this->currentUserIsGuest()) {
            if ($auth !== null) {
                $this->login($auth->userId);
            } else {
                // создаём нового пользователя и запись `auth`, связывающую 'source'/'source_id' с ним, затем входим
            }
        } elseif ($auth === null) {
            // привязываем этот внешний аккаунт к текущему авторизованному пользователю
        }
    }
}
```

`findAuth()`, `currentUserIsGuest()` и `login()` выше - это заглушки для вашего собственного кода хранения данных
и управления сессией; данный пакет не предоставляет управление пользователями/сессией.

`successCallback`/`cancelCallback` вызываются после успешной или отменённой аутентификации. Если колбэк
возвращает экземпляр `ResponseInterface`, он используется как ответ middleware; иначе выполняется перенаправление
на настроенный URL успеха/отмены.

> Примечание: Могут потребоваться различные подходы к обработке успешной аутентификации для разных клиентов.
  Например, X (Twitter) не позволяет получить электронную почту пользователя, и с этим так или иначе нужно
  что-то делать.

### Базовая структура клиента аутентификации

Хоть все клиенты и разные, все они реализуют базовый интерфейс [[\Yiisoft\Yii\AuthClient\AuthClientInterface]],
который управляет общим API:

- `getName()` - имя внешнего провайдера аутентификации, которому соответствует клиент, например `'google'`.
  Это значение используется в URL (как параметр маршрута `authclient`), в CSS-классах и хорошо подходит для
  колонки `source`, описанной в разделе [Установка](installation.md).
- `getTitle()` - удобное для пользователя имя внешнего сервиса аутентификации, используется для представления
  клиента на уровне отображения (например, `'Google'`).
- `getClientId()` - ID клиента OAuth.
- `getViewOptions()` - параметры отображения, такие как `popupWidth`/`popupHeight`, используемые виджетом
  [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]].
- `getUserAttributes()` - данные аутентифицированного пользователя, например `id`/`email`, полученные от
  провайдера после успешной аутентификации. Что именно возвращает провайдер - зависит от него самого (см.
  [Создание собственных клиентов аутентификации](creating-your-own-auth-clients.md) о том, как клиент получает эти
  данные и как привести имена полей к единому виду через `normalizeUserAttributeMap`).

Каждый клиент аутентификации имеет собственный процесс аутентификации. Параметр OAuth `scope`, запрашиваемый у
провайдера, по умолчанию берётся из `getDefaultScope()` конкретного класса клиента, и может быть переопределён
для отдельного клиента через `setScope()` (например, `'setScope()' => ['profile email']` в его DI-определении)
без создания подкласса; поля, реально присутствующие в ответе, всё равно полностью зависят от провайдера и
выданного scope.

Дополнительные параметры auth URL, специфичные для провайдера, можно настроить точно так же через
`setAuthParams()` (например, `'setAuthParams()' => [['prompt' => 'select_account']]` в DI-определении клиента
Google, чтобы принудительно показать выбор аккаунта). Они добавляются в каждый вызов `buildAuthUrl()`, поэтому
их не нужно передавать при каждом вызове.

## Добавление виджета в представление входа

Для представлений есть готовый к использованию виджет [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]]. Он получает
список настроенных клиентов через DI-сервис [[\Yiisoft\Yii\AuthClient\Collection]] и должен знать имя маршрута,
зарегистрированного выше:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth') ?>
```

По умолчанию он выводит ссылку для каждого настроенного клиента, открывая процесс аутентификации во всплывающем
окне.
