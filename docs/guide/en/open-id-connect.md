OpenID Connect
==============

This extension provides support for [OpenId Connect](https://openid.net/connect/) authentication protocol via
[[\Yiisoft\Yii\AuthClient\OpenIdConnect]] class.

Application configuration example (see [Installation](installation.md) for the general pattern):

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

The array key (`'google'`) is used as the client's `name`, so an application can register several
differently-named `OpenIdConnect` providers (Auth0, Okta, ...) side by side without them colliding on
session state or cached discovery data — see the [Installation](installation.md) guide's `auth0`/`okta`
example.

Authentication workflow is exactly the same as for OAuth2.

**Heads up!** 'OpenID Connect' protocol uses [JWS](https://tools.ietf.org/html/draft-ietf-jose-json-web-signature) verification
for securing the authentication process. This extension requires the [`web-token/jwt-library`](https://github.com/web-token/jwt-library)
library for such verification; it is a regular `composer.json` dependency of this package, so no extra installation
step is needed.

> Note: if you are using a well-trusted 'OpenID Connect' provider, you may call
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withoutValidateJws()]] on the resolved client instance to skip
  JWS validation, however it is not recommended as it violates the protocol specification. This is a wither
  (it returns a new instance rather than mutating the client), so it cannot be set via the `clients` params
  array — that only maps config keys to `set*()` methods, see [Installation](installation.md). Use
  [[\Yiisoft\Yii\AuthClient\Client\OpenIdConnect::withValidateJws()]] to switch it back on.

Reading group claims
--------------------

`OpenIdConnect::getUserAttributes()` reads the provider's UserInfo endpoint. Configure the provider
and any required scopes to include the group claim there. Claim names vary between providers, so map
them in your authentication success callback. For a provider returning nested `realm_access.roles`:

```php
use Yiisoft\Yii\AuthClient\Client\OpenIdConnect;

/** @var OpenIdConnect $client */
$attributes = $client->getUserAttributes();
$groups = $attributes['realm_access']['roles'] ?? [];
if (!is_array($groups)) {
    $groups = [];
}
```

Use the returned groups with your application's own membership or permission rules. Reading or mapping
a group claim does not request additional claims or assign application roles automatically.

This client requires a discovered `userinfo_endpoint` for `getUserAttributes()`. It does not fall back
to ID-token claims when that endpoint is absent, and it does not merge UserInfo with ID-token claims.
If groups appear only in the ID token, configure the provider to return them from UserInfo or implement
an application-specific claims integration. Keep JWS validation enabled.

`OpenIdConnect` is `final` and has no normalization-map setter. Use application-level mapping as above;
the normalization hook described in [Creating your own auth clients](creating-your-own-auth-clients.md)
is for clients you implement yourself.

RP-Initiated Logout
-------------------

`OpenIdConnect::buildLogoutUrl()` builds a browser redirect URL for
[OpenID Connect RP-Initiated Logout](https://openid.net/specs/openid-connect-rpinitiated-1_0.html).
The provider must support this specification; support for OIDC login alone is not enough.

The client uses `end_session_endpoint` from provider discovery. To override it, call
`setLogoutUrl('https://provider.example/logout')` or set `logoutUrl` in the client configuration.
Passing `null` or an empty string restores discovery. If no endpoint is available, the method throws
`Yiisoft\Yii\AuthClient\Exception\InvalidConfigException`. When discovery is not cached, building the
URL makes a blocking HTTP request. Discovery, decoding, or cache access can fail, so local logout
must still complete when URL generation throws.

Build the URL before removing the session that holds the ID token. For example, the body of a logout
action can use the following injected dependencies. Here, `$logoutLocally` is an application-provided
callback that ends local authentication, clears stored provider tokens, and handles any persistent
login cookies. Protect the action with POST-only routing and CSRF middleware.

```php
/** @var \Yiisoft\Yii\AuthClient\Client\OpenIdConnect $client */
/** @var \Psr\Http\Message\ResponseFactoryInterface $responseFactory */
/** @var \Psr\Log\LoggerInterface $logger */
/** @var callable(): void $logoutLocally */

$logoutUrl = null;
try {
    $logoutUrl = $client->buildLogoutUrl([
        'post_logout_redirect_uri' => 'https://app.example/logged-out',
    ]);
} catch (\Exception $exception) {
    $logger->warning('Unable to initiate provider logout.', ['exceptionClass' => $exception::class]);
} finally {
    $logoutLocally();
}

return $responseFactory->createResponse(303)->withHeader(
    'Location',
    $logoutUrl ?? 'https://app.example/logged-out',
);
```

Use the client that authenticated the current user. Register the exact `post_logout_redirect_uri`
with the provider and allow guests to access it. This URI is separate from the login `returnUrl`
and is not added automatically. Without it, the provider decides where the browser ends up.
When provider logout fails, tell the user that the provider session may still be active.

The helper includes nonempty `client_id` and stored `id_token_hint` defaults. It reads the current
or stored token without refreshing it, even when expired, and leaves later token reads unchanged.
Explicit parameters override defaults. Pass `'id_token_hint' => null` to omit the stored ID token,
or supply an ID token explicitly. You can also pass `logout_hint`, `ui_locales`, and provider-specific
parameters. The provider may ask for confirmation, especially without an ID token hint.

The generated URL uses GET. Its ID token may appear in browser history or request logs, and large
tokens may exceed URL length limits. Omit `id_token_hint` if your provider supports logout without it.

The optional `state` parameter is passed through without generation, storage, or validation.
If the callback needs to correlate the request, generate an unpredictable value, retain it across
local logout, then compare and consume it in the callback. A session destroyed during local logout
cannot retain that state. A public informational logged-out page need not change session state.

This method only builds the URL. End the local session even if the browser never returns from the
provider. Do not reuse a client instance holding the old token after local logout. The helper does
not revoke tokens or implement front-channel or back-channel logout notifications.
