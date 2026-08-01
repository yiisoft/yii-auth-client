OpenID Connect
==============

Данное расширение поддерживает протокол аутентификации [OpenId Connect](https://openid.net/connect/) через класс
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]].

Пример конфигурации приложения (общий подход см. в разделе [Установка](installation.md)):

```php
// config/common/params.php
'yiisoft/yii-auth-client' => [
    'clients' => [
        'google' => [
            'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
            'title' => 'Google OpenID Connect',
            'issuerUrl' => 'https://accounts.google.com',
            'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
        ],
    ],
],
```

Ключ массива (`'google'`) используется в качестве `name` клиента, поэтому приложение может регистрировать
несколько по-разному именованных провайдеров `OpenIdConnect` (Auth0, Okta и т.д.) одновременно, не
сталкиваясь с их коллизией по состоянию сессии или кэшированным данным обнаружения — см. пример
`auth0`/`okta` в разделе [Установка](installation.md).

Процесс аутентификации полностью совпадает с процессом для OAuth2.

**Внимание!** Протокол 'OpenID Connect' использует проверку [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature)
для защиты процесса аутентификации. Данное расширение требует библиотеку
[`web-token/jwt-library`](https://github.com/web-token/jwt-library) для такой проверки; она является обычной
зависимостью пакета в `composer.json`, поэтому дополнительная установка не требуется.

> Примечание: если вы используете доверенного провайдера 'OpenID Connect', вы можете вызвать
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withoutValidateJws()]] на полученном экземпляре клиента, что бы
  отключить проверку JWS, однако это не рекомендуется, так как нарушает спецификацию протокола. Это
  wither-метод (возвращает новый экземпляр вместо изменения текущего), поэтому его нельзя задать через массив
  `clients` — там ключи конфигурации сопоставляются только с методами `set*()`, см. [Установка](installation.md).
  Используйте [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withValidateJws()]], что бы включить её обратно.
