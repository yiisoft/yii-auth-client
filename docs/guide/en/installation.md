Installation
============

## Installing an extension

In order to install extension use Composer. Either run:

```
composer require yiisoft/yii-auth-client
```

or add

```json
"yiisoft/yii-auth-client": "^1.0.0"
```

to the `require` section of your composer.json.

The package ships its own `config/di.php` and `config/params.php`, which are picked up automatically if you
use [yiisoft/config](https://github.com/yiisoft/config). They register a [[\Yiisoft\Yii\AuthClient\Collection]]
service built from the `yiisoft/yii-auth-client.clients` parameter.

## Configuring application

After the extension is installed, list the auth clients you want to use in your application's params, and
register each client class as a DI definition so its `clientId`/`clientSecret` can be set.

`config/common/params.php` (merged over the package's own `params.php`):

```php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'google' => Yiisoft\Yii\AuthClient\Client\Google::class,
            'facebook' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
            // etc.
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
    ],
    Facebook::class => [
        'setClientId()' => [$_ENV['FACEBOOK_CLIENT_ID']],
        'setClientSecret()' => [$_ENV['FACEBOOK_CLIENT_SECRET']],
    ],
];
```

Out of the box the following clients are provided (all under `Yiisoft\Yii\AuthClient\Client`):

- [[\Yiisoft\Yii\AuthClient\Client\Facebook|Facebook]].
- [[\Yiisoft\Yii\AuthClient\Client\GitHub|GitHub]].
- [[\Yiisoft\Yii\AuthClient\Client\Google|Google]].
- [[\Yiisoft\Yii\AuthClient\Client\LinkedIn|LinkedIn]].
- [[\Yiisoft\Yii\AuthClient\Client\MicrosoftOnline|Microsoft Online]].
- [[\Yiisoft\Yii\AuthClient\Client\TikTok|TikTok]].
- [[\Yiisoft\Yii\AuthClient\Client\VKontakte|VKontakte]].
- [[\Yiisoft\Yii\AuthClient\Client\X|X (Twitter)]].
- [[\Yiisoft\Yii\AuthClient\Client\Yandex|Yandex]].
- [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect|OpenIdConnect]], for any provider speaking the OpenID Connect
  protocol (Auth0, Okta, Google, Microsoft Entra ID, ...) — see the [OpenID Connect](open-id-connect.md) guide.

Configuration for each client is a bit different. All of them require a client ID and secret key issued by the
service you're going to use.

## Storing authorization data

In order to recognize the user authenticated via external service we need to store ID provided on first authentication
and then check against it on subsequent authentications. It's not a good idea to limit login options to external
services only since these may fail and there won't be a way for the user to log in. Instead it's better to provide
both external authentication and good old login and password.

If we're storing user information in a database, a minimal schema needs a `user` table plus an `auth` table
linking each user to one or more external identities:

- `auth.user_id` — foreign key to `user.id`.
- `auth.source` — the name of the auth provider used ([[\Yiisoft\Yii\AuthClient\AuthClientInterface::getName()|$client->getName()]],
  e.g. `'google'`).
- `auth.source_id` — the unique user identifier provided by the external service after successful authentication.

Each user can authenticate using multiple external services, so each `user` record can relate to multiple `auth`
records. How you create these tables depends on the migration tool used by your application; this package does
not require or ship one.
