Creating your own auth clients
==============================

You may create your own auth client for any external auth provider that supports the OAuth 2 protocol
(this includes OpenID Connect providers - extend [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect]] instead if the
provider exposes a standard `.well-known/openid-configuration` discovery document).

Extend [[\Yiisoft\Yii\AuthClient\OAuth2]] and provide, at minimum:

- `authUrl` - the provider's authorization endpoint.
- `tokenUrl` - the provider's access token endpoint.
- `endpoint` - the API base URL used by `api()`/`createApiRequest()` (see [Getting additional data via extra API calls](usage-api.md)).
- `getName()`, `getTitle()`, `getButtonClass()` - required by [[\Yiisoft\Yii\AuthClient\AuthClientInterface]].
- `getClientId()` - required by [[\Yiisoft\Yii\AuthClient\OAuth2Interface]]; already implemented by `OAuth2` via
  the `clientId` property set through `setClientId()`, so you rarely need to override it.
- `getCurrentUserJsonArray(OAuthToken $token): array` - by convention (used by all built-in clients, though not
  part of the interface), the method that fetches the authenticated user's data. `OAuth2` provides a
  `fetchCurrentUserJsonArray()` helper that applies the token as a `Bearer` `Authorization` header for you.
- `initUserAttributes(): array` - a protected hook on [[\Yiisoft\Yii\AuthClient\AuthClient]] (default: empty
  array) feeding the public [[\Yiisoft\Yii\AuthClient\AuthClientInterface::getUserAttributes()|getUserAttributes()]].
  Override it to call your `getCurrentUserJsonArray()` once an access token is available - this is what every
  built-in client does.

For example, a minimal client for a hypothetical `my.com` OAuth2 provider:

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

`getDefaultScope()` sets the scope requested by every instance of your client. If you instead need to vary the
scope per configured instance without another subclass, use the inherited `setScope()` (e.g. `'scope' => '...'`
in the client's `clients` config entry, see below) to override it at registration time.

Then register it exactly like a built-in client (see [Installation](installation.md)):

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

Once authenticated, application code calls `$client->getUserAttributes()` (see [Quick Start](quick-start.md)) to
get the data returned by your `initUserAttributes()` override. If the provider's raw field names don't match what
your application expects, or you want a consistent shape across several different providers, override
`defaultNormalizeUserAttributeMap()` to remap/derive attributes without changing `initUserAttributes()` itself:

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

Each entry maps a normalized attribute name to either a raw attribute name (string), a path into nested raw
attributes (array of keys), or a callback receiving the raw attributes array. `getUserAttributes()` returns the
raw attributes merged with these normalized ones.

> Note: Some OAuth providers may not follow the OAuth standard clearly, introducing differences that require
  additional effort to implement a client for.
