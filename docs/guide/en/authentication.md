Authentication configuration and recovery
=========================================

## Using one authentication provider

To send guests directly to a provider, use the auth route from [Quick Start](quick-start.md) as your
application's login destination. Generate its URL with the configured client name:

```php
use Yiisoft\Router\UrlGeneratorInterface;

/** @var UrlGeneratorInterface $urlGenerator */
$loginUrl = $urlGenerator->generate('site/auth', ['authclient' => 'google']);
```

Use this URL in login links or in the middleware that redirects guests to login. Keep the auth route
accessible to guests. The `authclient` route argument is the key from the configured `Collection`,
not the OAuth client ID issued by the provider. Register the matching callback URL with the provider
and configure it as `oauth2ReturnUrl` on the client.

`AuthAction` requires that route argument even if the collection has only one client. It does not
select a default provider or read the client name from the query string.

## Session cookies on authentication callbacks

The default `SessionStateStorage` stores authentication state in the application session. The browser
must send the same session cookie when it returns from the provider. `SameSite=Strict` can prevent
the cookie from being sent on a cross-site callback, causing invalid authentication state or lost
session data. For a top-level GET callback, use `SameSite=Lax`.

When constructing `Yiisoft\Session\Session`, these options configure the cookie:

```php
use Yiisoft\Session\Session;

$session = new Session([
    'cookie_samesite' => 'Lax',
    'cookie_secure' => true,
    'cookie_httponly' => true,
]);
```

These values are already the defaults in `yiisoft/session` 3.x. Apply any overrides to the session
instance used by your application's session middleware and `SessionStateStorage`; creating a second
session inside the auth action does not configure the existing one. Use HTTPS with secure cookies.

A custom callback handling cross-site POST responses, such as OIDC `form_post`, needs
`SameSite=None` and `Secure`. `Lax` does not cover cross-site POST callbacks. Allowing POST on a route
alone does not add `form_post` support: `AuthAction` reads the authorization code from the query string.
See [SameSite cookie behavior](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie#samesitesamesite-value).
Keep OAuth state validation enabled; disabling it does not restore the missing session.

## Handling a rejected refresh token

An expired or revoked refresh token cannot obtain a new access token. The base `OAuth2` refresh method
throws `Yiisoft\Yii\AuthClient\Exception\InvalidResponseException` for an unsuccessful HTTP response.
Inspect the response through `getResponse()` and follow the provider's documented error format.
For example, an OAuth `invalid_grant` error can indicate an expired, revoked, or otherwise invalid grant.
Do not treat every HTTP error or network failure as token expiration.

With automatic refresh enabled, restoring an expired token through `getAccessToken()` can raise this
exception. `getUserAttributes()` and `api()` can also trigger token restoration. Handle recovery in the
application by removing the rejected token from its state storage and starting a new authorization flow.

For a provider that returns a JSON `invalid_grant` response, an action can use:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use Yiisoft\Yii\AuthClient\OAuth2;

/** @var OAuth2 $client */
/** @var ResponseFactoryInterface $responseFactory */
/** @var UrlGeneratorInterface $urlGenerator */
/** @var callable(): void $clearRejectedToken Application-specific storage cleanup. */

try {
    $attributes = $client->getUserAttributes();
} catch (InvalidResponseException $exception) {
    $response = $exception->getResponse();
    $error = json_decode((string) $response->getBody(), true);
    if (
        $response->getStatusCode() !== 400
        || !is_array($error)
        || ($error['error'] ?? null) !== 'invalid_grant'
    ) {
        throw $exception;
    }

    $clearRejectedToken();
    $loginUrl = $urlGenerator->generate('site/auth', ['authclient' => $client->getName()]);
    return $responseFactory->createResponse(303)->withHeader('Location', $loginUrl);
}
```

`$clearRejectedToken` must be supplied by your application and remove this client's token from the
`StateStorageInterface` implementation used by the client. This package has no public token-clearing
method: `setAccessToken(null)` is not supported, and `setAccessToken([])` does not clear a token.
Discard the client instance after cleanup so a cached token cannot be reused. Do not clear the whole
application session unless ending the user's local login is also intended.

Use the client collection and route for the failed authorization flow. Token cleanup and redirecting
to the provider do not themselves log the user out locally or end the provider's session. If the user
cancels reauthorization, stop dependent API calls and let them retry explicitly instead of redirecting
in a loop.
