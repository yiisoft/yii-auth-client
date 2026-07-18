OpenID Connect
==============

Данное расширение поддерживает протокол аутентификации [OpenId Connect](https://openid.net/connect/) через класс
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]].

Пример конфигурации приложения (общий подход см. в разделе [Установка](installation.md)):

```php
// config/common/params.php
'yiisoft/yii-auth-client' => [
    'clients' => [
        'google' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
    ],
],

// config/common/di.php
Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class => [
    '__construct()' => [
        'name' => 'google',
        'title' => 'Google OpenID Connect',
    ],
    'setIssuerUrl()' => ['https://accounts.google.com'],
    'setClientId()' => [$_ENV['GOOGLE_CLIENT_ID']],
    'setClientSecret()' => [$_ENV['GOOGLE_CLIENT_SECRET']],
],
```

`name` и `title` являются аргументами конструктора (и не могут быть заданы позже) именно для того, что бы
приложение могло регистрировать несколько по-разному именованных провайдеров `OpenIdConnect` (Auth0, Okta и т.д.)
одновременно, не сталкиваясь с их коллизией по состоянию сессии или кэшированным данным обнаружения.

Процесс аутентификации полностью совпадает с процессом для OAuth2.

**Внимание!** Протокол 'OpenID Connect' использует проверку [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature)
для защиты процесса аутентификации. Данное расширение требует библиотеку
[`web-token/jwt-framework`](https://github.com/web-token/jwt-framework) для такой проверки; она является обычной
зависимостью пакета в `composer.json`, поэтому дополнительная установка не требуется.

> Примечание: если вы используете доверенного провайдера 'OpenID Connect', вы можете вызвать
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withoutValidateJws()]] (например, `'withoutValidateJws()' => []`
  в DI-определении клиента выше), что бы отключить проверку JWS, однако это не рекомендуется, так как нарушает
  спецификацию протокола. Используйте [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withValidateJws()]], что бы
  включить её обратно.
