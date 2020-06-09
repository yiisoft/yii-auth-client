<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\FactoryInterface;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\Signature\BaseMethod;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\Web\Session\SessionInterface;

/**
 * OAuth2 serves as a client for the OAuth 2 flow.
 *
 * In oder to acquire access token perform following sequence:
 *
 * ```php
 * use Yiisoft\Yii\AuthClient\OAuth2;
 *
 * // assuming class MyAuthClient extends OAuth2
 * $oauthClient = new MyAuthClient();
 * $url = $oauthClient->buildAuthUrl(); // Build authorization URL
 * Yii::getApp()->getResponse()->redirect($url); // Redirect to authorization URL.
 * // After user returns at our site:
 * $code = Yii::getApp()->getRequest()->get('code');
 * $accessToken = $oauthClient->fetchAccessToken($code); // Get access token
 * ```
 *
 * @see http://oauth.net/2/
 * @see https://tools.ietf.org/html/rfc6749
 */
abstract class OAuth2 extends BaseOAuth
{
    /**
     * @var string OAuth client ID.
     */
    protected string $clientId;
    /**
     * @var string OAuth client secret.
     */
    protected string $clientSecret;
    /**
     * @var string token request URL endpoint.
     */
    protected string $tokenUrl;
    /**
     * @var bool whether to use and validate auth 'state' parameter in authentication flow.
     * If enabled - the opaque value will be generated and applied to auth URL to maintain
     * state between the request and callback. The authorization server includes this value,
     * when redirecting the user-agent back to the client.
     * The option is used for preventing cross-site request forgery.
     */
    protected bool $validateAuthState = true;
    private SessionInterface $session;

    public function __construct(
        \Psr\Http\Client\ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage,
        SessionInterface $session,
        FactoryInterface $factory
    ) {
        parent::__construct($httpClient, $requestFactory, $stateStorage, $factory);
        $this->session = $session;
    }

    /**
     * Composes user authorization URL.
     * @param ServerRequestInterface $incomingRequest
     * @param array $params additional auth GET params.
     * @return string authorization URL.
     */
    public function buildAuthUrl(
        ServerRequestInterface $incomingRequest,
        array $params = []
    ): string {
        $defaultParams = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getReturnUrl($incomingRequest),
            //'xoauth_displayname' => Yii::getApp()->name,
        ];
        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            $this->setState('authState', $authState);
            $defaultParams['state'] = $authState;
        }

        return RequestUtil::composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    /**
     * Generates the auth state value.
     * @return string auth state value.
     */
    protected function generateAuthState(): string
    {
        $baseString = get_class($this) . '-' . time();
        if ($this->session->isActive()) {
            $baseString .= '-' . $this->session->getId();
        }
        return hash('sha256', uniqid($baseString, true));
    }

    /**
     * Fetches access token from authorization code.
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode authorization code, usually comes at GET parameter 'code'.
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function fetchAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken {
        if ($this->validateAuthState) {
            $authState = $this->getState('authState');
            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();
            $incomingState = $queryParams['state'] ?? $bodyParams['state'] ?? null;
            if ($incomingState !== null || empty($authState) || strcmp($incomingState, $authState) !== 0) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            $this->removeState('authState');
        }

        $defaultParams = [
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getReturnUrl($incomingRequest),
        ];

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams($request, array_merge($defaultParams, $params));
        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(
            [
                'setParams' => [Json::decode($response->getBody()->getContents())]
            ]
        );
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Applies client credentials (e.g. {@see clientId} and {@see clientSecret}) to the HTTP request instance.
     * This method should be invoked before sending any HTTP request, which requires client credentials.
     * @param RequestInterface $request HTTP request instance.
     * @return RequestInterface
     */
    protected function applyClientCredentialsToRequest(RequestInterface $request): RequestInterface
    {
        return RequestUtil::addParams(
            $request,
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]
        );
    }

    /**
     * Creates token from its configuration.
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = []): OAuthToken
    {
        $tokenConfig['tokenParamKey'] = 'access_token';

        return parent::createToken($tokenConfig);
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams(
            $request,
            [
                'access_token' => $accessToken->getToken(),
            ]
        );
    }

    /**
     * Gets new auth token to replace expired one.
     * @param OAuthToken $token expired auth token.
     * @return OAuthToken new auth token.
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $params = [
            'grant_type' => 'refresh_token'
        ];
        $params = array_merge($token->getParams(), $params);

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams($request, $params);

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(
            [
                'setParams' => [Json::decode($response->getBody()->getContents())]
            ]
        );
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticate OAuth client directly at the provider without third party (user) involved,
     * using 'client_credentials' grant type.
     * @link http://tools.ietf.org/html/rfc6749#section-4.4
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateClient(array $params = []): OAuthToken
    {
        $defaultParams = [
            'grant_type' => 'client_credentials',
        ];

        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams(
            $request,
            array_merge($defaultParams, $params)
        );

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(
            [
                'setParams' => [Json::decode($response->getBody()->getContents())]
            ]
        );
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticates user directly by 'username/password' pair, using 'password' grant type.
     * @link https://tools.ietf.org/html/rfc6749#section-4.3
     * @param string $username user name.
     * @param string $password user password.
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateUser(string $username, string $password, array $params = []): OAuthToken
    {
        $defaultParams = [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];

        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams(
            $request,
            array_merge($defaultParams, $params)
        );

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(
            [
                'setParams' => [Json::decode($response->getBody()->getContents())]
            ]
        );
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticates user directly using JSON Web Token (JWT).
     * @link https://tools.ietf.org/html/rfc7515
     * @param string $username
     * @param BaseMethod|array $signature signature method or its array configuration.
     * If empty - {@see signatureMethod} will be used.
     * @param array $options additional options. Valid options are:
     *
     * - header: array, additional JWS header parameters.
     * - payload: array, additional JWS payload (message or claim-set) parameters.
     * - signatureKey: string, signature key to be used, if not set - {@see clientSecret} will be used.
     *
     * @param array $params additional request params.
     * @return OAuthToken access token.
     * @throws JsonException
     */
    public function authenticateUserJwt(
        string $username,
        $signature = null,
        array $options = [],
        array $params = []
    ): OAuthToken {
        if (empty($signature)) {
            $signatureMethod = $this->getSignatureMethod();
        } elseif (is_object($signature)) {
            $signatureMethod = $signature;
        } else {
            $signatureMethod = $this->createSignatureMethod($signature);
        }

        $header = $options['header'] ?? [];
        $payload = $options['payload'] ?? [];

        $header = array_merge(
            [
                'typ' => 'JWT'
            ],
            $header
        );
        if (!isset($header['alg'])) {
            $signatureName = $signatureMethod->getName();
            if (preg_match('/^([a-z])[a-z]*-([a-z])[a-z]*(\d+)$/i', $signatureName, $matches)) {
                // convert 'RSA-SHA256' to 'RS256' :
                $signatureName = $matches[1] . $matches[2] . $matches[3];
            }
            $header['alg'] = $signatureName;
        }

        $payload = array_merge(
            [
                'iss' => $username,
                'scope' => $this->getScope(),
                'aud' => $this->tokenUrl,
                'iat' => time(),
            ],
            $payload
        );
        if (!isset($payload['exp'])) {
            $payload['exp'] = $payload['iat'] + 3600;
        }

        $signatureBaseString = base64_encode(Json::encode($header)) . '.' . base64_encode(Json::encode($payload));
        $signatureKey = $options['signatureKey'] ?? $this->clientSecret;
        $signature = $signatureMethod->generateSignature($signatureBaseString, $signatureKey);

        $assertion = $signatureBaseString . '.' . $signature;

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams(
            $request,
            array_merge(
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                $params
            )
        );

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
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
     * Composes default {@see returnUrl} value.
     * @return string return URL.
     */
    protected function defaultReturnUrl(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        unset($params['code'], $params['state']);

        return $request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986))->__toString();
    }
}
