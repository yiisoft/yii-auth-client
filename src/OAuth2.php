<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

use function count;
use function is_array;
use function is_string;
use function strlen;

use const PHP_QUERY_RFC3986;

/**
 * OAuth2 serves as a client for the OAuth 2 flow.
 *
 * @see https://oauth.net/2/
 * @see https://tools.ietf.org/html/rfc6749
 */
abstract class OAuth2 extends OAuth
{
    /**
     * @var string OAuth client ID.
     */
    protected string $clientId = '';
    /**
     * @var string OAuth client secret.
     */
    protected string $clientSecret = '';
    /**
     * @var string token request URL endpoint.
     * @see e.g. 'https://github.com/login/oauth/access_token'
     */
    protected string $tokenUrl = '';

    protected string $returnUrl = '';

    /**
     * @var bool whether to use and validate auth 'state' parameter in authentication flow.
     * If enabled - the opaque value will be generated and applied to auth URL to maintain
     * state between the request and callback. The authorization server includes this value,
     * when redirecting the user-agent back to the client.
     * The option is used for preventing cross-site request forgery.
     */
    protected bool $validateAuthState = true;

    /**
     * @var array additional auth GET params, merged into every {@see buildAuthUrl()} call.
     * Useful for provider-specific options (e.g. Google's `prompt` or `access_type`) that should
     * always be applied without having to pass them at every call site.
     */
    protected array $authParams = [];

    /**
     * @var string|null SVG markup for the client's logo icon (e.g. brand glyph).
     * If set, {@see Widget\AuthChoice} renders this inline SVG instead of falling back to a sprite.
     */
    protected ?string $logo = null;

    /**
     * BaseOAuth constructor.
     *
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @param StateStorageInterface $stateStorage
     * @param YiisoftFactory $factory
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage,
        protected YiisoftFactory $factory,
        protected SessionInterface $session,
    ) {
        parent::__construct($httpClient, $requestFactory, $stateStorage, $this->factory);
    }

    /**
     * Composes user authorization URL.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param array $params additional auth GET params.
     *
     * @return string authorization URL.
     */
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getOauth2ReturnUrl(),
            'xoauth_displayname' => $incomingRequest->getAttribute(AuthAction::AUTH_NAME),
        ];
        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }
        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            $this->setState('authState', $authState);
            $defaultParams['state'] = $authState;
        }
        return RequestUtil::composeUrl($this->authUrl, array_merge($defaultParams, $this->authParams, $params));
    }

    /**
     * Compare a callback query parameter 'state' with the saved Auth Client's 'authState'  parameter
     * in order to prevent CSRF attacks
     *
     * Use: Typically used in a AuthController's callback function specifically  for an Identity Provider e.g. Facebook
     *
     * @return mixed
     */
    public function getSessionAuthState(): mixed
    {
        /**
         * @see src\AuthClient protected function getState('authState')
         */
        return $this->getState('authState');
    }

    /**
     * Fetches access token from authorization code.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode authorization code, usually comes at GET parameter 'code'.
     * @param array $params additional request params.
     *
     * @return OAuthToken access token.
     */
    public function fetchAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = [],
    ): OAuthToken {
        if ($this->validateAuthState) {
            /**
             * @var string|null $authState 'authState' is only ever written by
             * {@see buildAuthUrl()} with the string returned from {@see generateAuthState()}.
             */
            $authState = $this->getState('authState');
            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();
            /**
             * @psalm-suppress MixedAssignment
             */
            $incomingState = $queryParams['state'] ?? ($bodyParams['state'] ?? null);
            if (is_string($incomingState)) {
                if (strcmp($incomingState, (string) $authState) !== 0) {
                    throw new InvalidArgumentException('Invalid auth state parameter.');
                }
            }
            if ($incomingState === null) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            if (empty($authState)) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            $this->removeState('authState');
        }

        $defaultParams = [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => $this->getOauth2ReturnUrl(),
        ];

        $request = $this->createTokenRequest(array_merge($defaultParams, $params));
        $request = $this->applyClientCredentialsToRequest($request);
        $response = $this->sendRequest($request);
        $contents = $response->getBody()->getContents();
        $output = $this->parseTokenResponse($contents);

        $token = $this->createToken(['params' => $output]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Note: This function will be adapted later to accomodate the 'confidential client'.
     * @see https://docs.x.com/resources/fundamentals/authentication/oauth-2-0/authorization-code
     * Used specifically for the X i.e. Twitter OAuth2.0 Authorization code with PKCE and public client i.e.
     * client id included in request body;
     * and NOT Confidential Client i.e. Client id not included in the request body
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode
     * @param array $params
     * @throws InvalidArgumentException
     * @return OAuthToken
     */
    public function fetchAccessTokenWithCodeVerifier(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = [],
    ): OAuthToken {
        if ($this->validateAuthState) {
            /**
             * @var string|null $authState 'authState' is only ever written by
             * {@see buildAuthUrl()} with the string returned from {@see generateAuthState()}.
             */
            $authState = $this->getState('authState');

            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();

            /**
             * @psalm-suppress MixedAssignment
             */
            $incomingState = $queryParams['state'] ?? ($bodyParams['state'] ?? null);

            if (is_string($incomingState)) {
                if (strcmp($incomingState, (string) $authState) !== 0) {
                    throw new InvalidArgumentException('Invalid auth state parameter.');
                }
            }
            if ($incomingState === null) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            if (empty($authState)) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            $this->removeState('authState');
        }

        $requestBody = [
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $params['redirect_uri'] ?? '',
            'code_verifier' => $params['code_verifier'] ?? '',
        ];

        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $request->getBody()->write(http_build_query($requestBody));

        try {
            $response = $this->httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (strlen($body) > 0) {
                $output = (array) json_decode($body, true);
            } else {
                $output = [];
            }
        } catch (Throwable) {
            $output = [];
        }

        $token = $this->createToken(['params' => $output]);
        $this->setAccessToken($token);

        return $token;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setAuthParams(array $authParams): void
    {
        $this->authParams = $authParams;
    }

    public function getAuthParams(): array
    {
        return $this->authParams;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getOauth2ReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function setOauth2ReturnUrl(string $returnUrl): void
    {
        $this->returnUrl = $returnUrl;
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams(
            $request,
            [
                'access_token' => $accessToken->getToken(),
            ],
        );
    }

    /**
     * Gets new auth token to replace expired one.
     *
     * @see https://developers.google.com/oauthplayground
     *
     * @param OAuthToken $token expired auth token.
     *
     * @return OAuthToken new auth token.
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $params = [
            'grant_type' => 'refresh_token',
        ];
        $params = array_merge($token->getParams(), $params);

        $request = $this->createTokenRequest($params);

        $request = $this->applyClientCredentialsToRequest($request);
        $response = $this->sendRequest($request);
        $contents = $response->getBody()->getContents();

        $output = $this->parseTokenResponse($contents);

        return $this->createToken(['params' => $output]);
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function setTokenUrl(string $tokenUrl): void
    {
        $this->tokenUrl = $tokenUrl;
    }

    public function withValidateAuthState(): self
    {
        $new = clone $this;
        $new->validateAuthState = true;
        return $new;
    }

    public function withoutValidateAuthState(): self
    {
        $new = clone $this;
        $new->validateAuthState = false;
        return $new;
    }

    /**
     * Builds the seed string used by {@see generateAuthState()}. Extracted into its own method so the
     * seed's composition can be tested directly, since the final hashed/uniqid()-mixed auth state value
     * is opaque and can't reveal how its input was assembled.
     *
     * @return string auth state seed.
     */
    protected function generateAuthStateBaseString(): string
    {
        $baseString = static::class . '-' . time();
        $sessionId = $this->session->getId();
        if (null !== $sessionId) {
            if ($this->session->isActive()) {
                $baseString .= '-' . $sessionId;
            }
        }
        return $baseString;
    }

    /**
     * Generates the auth state value.
     *
     * @return string auth state value.
     */
    protected function generateAuthState(): string
    {
        return hash('sha256', uniqid($this->generateAuthStateBaseString(), true));
    }

    /**
     * Applies client credentials (e.g. {@see clientId} and {@see clientSecret}) to the HTTP request instance.
     * This method should be invoked before sending any HTTP request, which requires client credentials.
     *
     * Assumes `$request` already carries a `createTokenRequest()`-built `application/x-www-form-urlencoded`
     * body - the credentials are appended to that body, not the URI query string, matching how every
     * caller of this method builds its request. Overrides (e.g. `OpenIdConnect`, which may instead add
     * an `Authorization` header for `client_secret_basic`) aren't bound by that assumption.
     *
     * @param RequestInterface $request HTTP request instance.
     *
     * @return RequestInterface
     */
    protected function applyClientCredentialsToRequest(RequestInterface $request): RequestInterface
    {
        $request->getBody()->write('&' . http_build_query(
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        ));

        return $request;
    }

    /**
     * Fetches current user data as JSON array from the given endpoint, authenticating the request with
     * the access token as an `Authorization` header.
     *
     * @param OAuthToken $token access token, whose `access_token` param is used for authentication.
     * @param string $url endpoint URL to fetch user data from.
     * @param array $headers additional request headers, merged over the default `Authorization` header.
     * @param string $authScheme `Authorization` header scheme, e.g. `Bearer` or `OAuth`.
     *
     * @return array decoded user data, or an empty array if there is no access token or the request fails.
     */
    protected function fetchCurrentUserJsonArray(
        OAuthToken $token,
        string $url,
        array $headers = [],
        string $authScheme = 'Bearer',
    ): array {
        $tokenString = (string) $token->getParam('access_token');
        if ($tokenString === '') {
            return [];
        }

        $request = RequestUtil::addHeaders(
            $this->createRequest('GET', $url),
            array_merge(['Authorization' => $authScheme . ' ' . $tokenString], $headers),
        );

        try {
            $body = $this->sendRequest($request)->getBody()->getContents();
        } catch (Throwable) {
            return [];
        }

        return $body === '' ? [] : (array) json_decode($body, true);
    }

    /**
     * Creates token from its configuration.
     *
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = []): OAuthToken
    {
        $tokenConfig['tokenParamKey'] = 'access_token';
        return parent::createToken($tokenConfig);
    }

    /**
     * Composes default {@see returnUrl} value.
     *
     * @param ServerRequestInterface $request
     *
     * @return string return URL.
     */
    protected function defaultReturnUrl(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        unset($params['code'], $params['state']);
        return (string) $request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * Builds a `POST` request to {@see tokenUrl} with `$params` as an `application/x-www-form-urlencoded`
     * body. RFC 6749 §4.1.3 requires token-endpoint parameters in the request body, not the URI query
     * string - a strict provider like Google rejects a query-string-only request outright (with no
     * usable `access_token` in its error response), while a lenient one like GitHub's legacy endpoint
     * happens to tolerate it. {@see applyClientCredentialsToRequest()} is expected to append further
     * params to this same body afterward, not build a request of its own.
     */
    protected function createTokenRequest(array $params): RequestInterface
    {
        $request = $this->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        return $request;
    }

    /**
     * Parses a token endpoint response body. RFC 6749 §5.1 mandates a JSON object, which every
     * modern provider (Google, Microsoft, LinkedIn, etc.) sends - {@see parse_str_clean()} can't
     * parse that (it expects `key=value&key=value` query-string form) and silently returns
     * unusable, mangled keys instead of throwing, so a naive `parse_str()`-only implementation
     * here would leave every token empty without ever surfacing an error. GitHub's legacy
     * `/login/oauth/access_token` endpoint still defaults to the query-string form, so that path
     * is kept as a fallback for providers not sending valid JSON.
     */
    private function parseTokenResponse(string $contents): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : $this->parse_str_clean($contents);
    }

    /**
     * Purpose: Prevent, inter alia, underscores in keys of an array
     * @see https://www.php.net/manual/en/function.parse-str.php#126789
     */
    private function parse_str_clean(string $querystr): array
    {
        $qquerystr = str_ireplace(['.','%2E','+',' ','%20'], ['QQleQPunT', 'QQleQPunT', 'QQleQSpaTIE', 'QQleQSpaTIE', 'QQleQSpaTIE'], $querystr);
        $arr = null;
        parse_str($qquerystr, $arr);
        return $this->sanitizeKeys($arr, $querystr);
    }

    private function sanitizeKeys(array &$arr, string $querystr): array
    {
        /**
         * @var array|string $val
         */
        foreach ($arr as $key => $val) {
            // restore values to original

            $newval = $val;

            if (is_string($val)) {
                $newval = str_replace(['QQleQPunT', 'QQleQSpaTIE'], ['.',' '], $val);
            }

            $newkey = str_replace(['QQleQPunT', 'QQleQSpaTIE'], ['.',' '], (string) $key);

            if (str_contains($newkey, '_')) {
                // periode of space or [ or ] converted to _. Restore with querystring
                // $key is guaranteed to be a string here: an int key never contains '_'.
                /** @var string $key */
                $regex = '/&(' . str_replace('_', '[ \.\[\]]', preg_quote($newkey, '/')) . ')=/';
                $matches = null ;
                preg_match_all($regex, '&' . urldecode($querystr), $matches);
                $candidateKeys = array_unique($matches[1]);
                $candidateKey = reset($candidateKeys);
                if (count($candidateKeys) === 1 && $key != $candidateKey) {
                    $newkey = $candidateKey ;
                }
            }

            if ($newkey !== $key) {
                unset($arr[$key]);
                $arr[$newkey] = $newval ;
            } elseif ($val !== $newval) {
                $arr[$key] = $newval;
            }

            if (is_array($val)) {
                /** @var array $arr[$newkey] */
                $this->sanitizeKeys($arr[$newkey], $querystr);
            }
        }
        return $arr;
    }
}
