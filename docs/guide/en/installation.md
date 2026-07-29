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

After the extension is installed, configure the auth clients you want to use in your application's params.

`config/common/params.php` (merged over the package's own `params.php`):

```php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'google' => [
                'class' => Yiisoft\Yii\AuthClient\Client\Google::class,
                'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
                'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
                // Required: oauth2ReturnUrl has no request-derived fallback, so an unset redirect_uri
                // is sent as empty. Google rejects that outright ("Missing required parameter:
                // redirect_uri"); must match the callback URL registered in the Google Cloud Console
                // exactly.
                'oauth2ReturnUrl' => 'https://example.com/auth/google',
            ],
            'facebook' => [
                'class' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
                'clientId' => $_ENV['FACEBOOK_CLIENT_ID'],
                'clientSecret' => $_ENV['FACEBOOK_CLIENT_SECRET'],
            ],
            // Multiple instances of the same class are supported:
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

The array key (`'google'`, `'facebook'`, etc.) is used as the client's `name`. Additional options map to
setter methods on the client class — see the class API for available setters:

| Config key | Setter | Available on |
|-----------|--------|-------------|
| `title` | `setTitle()` | All clients |
| `clientId` | `setClientId()` | All OAuth2 clients |
| `clientSecret` | `setClientSecret()` | All OAuth2 clients |
| `oauth2ReturnUrl` | `setOauth2ReturnUrl()` | All OAuth2 clients |
| `scope` | `setScope()` | All OAuth clients |
| `authUrl` | `setAuthUrl()` | All OAuth clients |
| `tokenUrl` | `setTokenUrl()` | All OAuth2 clients |
| `authParams` | `setAuthParams()` | All OAuth2 clients |
| `returnUrl` | `setReturnUrl()` | All OAuth clients |
| `issuerUrl` | `setIssuerUrl()` | OpenIdConnect |
| `validateAuthNonce` | `setValidateAuthNonce()` | OpenIdConnect |
| `tenant` | `setTenant()` | MicrosoftOnline |

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
