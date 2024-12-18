<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

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
        
    protected YiisoftFactory $factory;
    /**
     * @var string OAuth client secret.
     */
    protected string $clientSecret;
    /**
     * @var string token request URL endpoint.
     * @see e.g. 'https://github.com/login/oauth/access_token'
     */
    protected string $tokenUrl;
    
    protected string $returnUrl = '';
    
    /**
     * @var bool whether to use and validate auth 'state' parameter in authentication flow.
     * If enabled - the opaque value will be generated and applied to auth URL to maintain
     * state between the request and callback. The authorization server includes this value,
     * when redirecting the user-agent back to the client.
     * The option is used for preventing cross-site request forgery.
     */
    protected bool $validateAuthState = true;
    protected SessionInterface $session;
    
    /**
     * BaseOAuth constructor.
     *
     * @param \GuzzleHttp\Client $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @param StateStorageInterface $stateStorage
     * @param YiisoftFactory $factory
     */
    public function __construct(
        \GuzzleHttp\Client $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage,
        YiisoftFactory $factory,
        SessionInterface $session,    
    ) {
        $this->factory = $factory;
        $this->session = $session;
        parent::__construct($httpClient, $requestFactory, $stateStorage, $factory);
    }

    /**
     * Composes user authorization URL.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param array $params additional auth GET params.
     *
     * @return string authorization URL.
     */
    public function buildAuthUrl(
        ServerRequestInterface $incomingRequest,
        array $params = []
    ): string {
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

        return RequestUtil::composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    /**
     * Generates the auth state value.
     *
     * @return string auth state value.
     */
    protected function generateAuthState(): string
    {
        $baseString = static::class . '-' . time();
        $sessionId = $this->session->getId();
        if (null!==$sessionId) {
            if ($this->session->isActive()) {
                $baseString .= '-' . $sessionId;
            }
        }
        return hash('sha256', uniqid($baseString, true));
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
        array $params = []
    ): OAuthToken {
        if ($this->validateAuthState) {
            /**
             * @psalm-suppress MixedAssignment
             */
            $authState = $this->getState('authState');
            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();
            /**
             * @psalm-suppress MixedAssignment
             */
            $incomingState = $queryParams['state'] ?? ($bodyParams['state'] ?? null);
            
            if (is_string($incomingState)) {
                if (strcmp($incomingState, (string)$authState) !== 0) {
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
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getOauth2ReturnUrl(),
        ];        
       
        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams($request, array_merge($defaultParams, $params));
        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);
        $contents = $response->getBody()->getContents();
        parse_str($contents, $output);
        $token = new OAuthToken();
        /**
         * @var string $key
         * @var string $value
         */
        foreach ($output as $key => $value) {
            $token->setParam($key, $value);    
        }
        return $token;
    }
    
    /**
     * Applies client credentials (e.g. {@see clientId} and {@see clientSecret}) to the HTTP request instance.
     * This method should be invoked before sending any HTTP request, which requires client credentials.
     *
     * @param RequestInterface $request HTTP request instance.
     *
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
     *
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = []): OAuthToken
    {
        $tokenConfig['tokenParamKey'] = 'access_token';

        return parent::createToken($tokenConfig);
    }
    
    public function setClientId(string $clientId) : void
    {
        $this->clientId = $clientId;
    }
    
    public function getClientId() : string
    {
        return $this->clientId;
    }
    
     public function setClientSecret(string $clientSecret) : void
    {
        $this->clientSecret = $clientSecret;
    }
    
    public function getClientSecret() : string
    {
        return $this->clientSecret;
    }
    
    public function getOauth2ReturnUrl() : string
    {
        return $this->returnUrl;
    }
    
    public function setOauth2ReturnUrl(string $returnUrl) : void
    {
        $this->returnUrl = $returnUrl;
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

        $request = $this->createRequest('POST', $this->tokenUrl);
        
        $request = RequestUtil::addParams($request, $params);

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $contents = $response->getBody()->getContents();
        parse_str($contents, $output);
        $token = new OAuthToken();
        /**
         * @var string $key
         * @var string $value
         */
        foreach ($output as $key => $value) {
            $token->setParam($key, $value);    
        }
        return $token;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
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

        return (string)$request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }
}
