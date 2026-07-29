Quick Start
===========

## Registering the auth route

[[\Yiisoft\Yii\AuthClient\AuthAction]] is a PSR-15 middleware that drives the OAuth2 flow (redirect to the
provider, then handle its callback). Configure it as a DI definition with the URLs and callbacks your
application needs, then attach it to a route.

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
            // set flash, logging, etc.
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

Where `AuthHandler` implementation could be like this:

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

        // find an existing `auth` record matching [source => $this->client->getName(), source_id => $id]
        // (see the "Storing authorization data" section in the Installation guide)
        $auth = $this->findAuth($this->client->getName(), $id);

        if ($this->currentUserIsGuest()) {
            if ($auth !== null) {
                $this->login($auth->userId);
            } else {
                // create a new user + an `auth` record linking 'source'/'source_id' to it, then log in
            }
        } elseif ($auth === null) {
            // link this external account to the currently logged-in user
        }
    }
}
```

`findAuth()`, `currentUserIsGuest()` and `login()` above are placeholders for your application's own persistence
and session-handling code — this package does not provide user/session management.

`successCallback`/`cancelCallback` are invoked once authentication succeeded or was cancelled. If the callback
returns a `ResponseInterface` instance, it is used as the middleware response; otherwise a redirect to the
configured success/cancel URL is performed.

> Note: different Auth clients may require different approaches while handling authentication success. For
  example, X (Twitter) does not allow returning the user's email, so you have to deal with this somehow.

### Auth client basic structure

Although all clients are different, they share the same basic interface [[\Yiisoft\Yii\AuthClient\AuthClientInterface]],
which governs the common API:

- `getName()` - external auth provider name that this client is matched to, e.g. `'google'`. This value is used
  in URLs (as the `authclient` route parameter), CSS classes, and is a natural fit for the `source` column
  described in [Installation](installation.md).
- `getTitle()` - user-friendly name for the external auth provider, used to present the auth client at the view
  layer (e.g. `'Google'`).
- `getClientId()` - the OAuth client ID.
- `getViewOptions()` - view options such as `popupWidth`/`popupHeight`, consumed by [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]].
- `getUserAttributes()` - the authenticated user's data, e.g. `id`/`email`, fetched from the provider once
  authentication succeeded. What each provider returns varies (see [Creating your own auth clients](creating-your-own-auth-clients.md)
  for how a client fetches this data, and how to normalize field names across providers with `normalizeUserAttributeMap`).

Each auth client has a different auth flow. The OAuth `scope` requested from the provider defaults to each
built-in client class's `getDefaultScope()`, and can be overridden per client via `setScope()` (e.g.
`'scope' => 'profile email'` in its `clients` config entry, see [Installation](installation.md)) without
subclassing; the fields actually present in the user data response still depend entirely on the provider and
the scope it was granted.

Additional provider-specific auth URL parameters can be configured the same way via `setAuthParams()` (e.g.
`'authParams' => ['prompt' => 'select_account']` in a Google client's config, to force Google's account
chooser). These are merged into every `buildAuthUrl()` call, so they don't need to be passed at each call site.

## Adding the widget to the login view

There's a ready-to-use [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]] widget for views. It relies on
[[\Yiisoft\Yii\AuthClient\Collection]] being available via DI to list the configured clients, and needs to know
the name of the route registered above:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth') ?>
```

By default, it renders a link per configured client, opening the auth flow in a popup window.
