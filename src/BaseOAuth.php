<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Exception;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use Yiisoft\Yii\AuthClient\Signature\BaseMethod;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

use function is_array;
use function is_object;

/**
 * BaseOAuth is a base class for the OAuth clients.
 *
 * @link http://oauth.net/
 */
abstract class BaseOAuth extends BaseClient
{
    /**
     * @var string API base URL.
     * This field will be used as {@see UriInterface::getPath()}} value of {@see httpClient}.
     * Note: changing this property will take no effect after {@see httpClient} is instantiated.
     */
    protected string $endpoint;
    /**
     * @var string authorize URL.
     */
    protected string $authUrl;
    /**
     * @var string string auth request scope.
     */
    protected string $scope;
    /**
     * @var bool whether to automatically perform 'refresh access token' request on expired access token.
     */
    protected bool $autoRefreshAccessToken = true;

    /**
     * @var string URL, which user will be redirected after authentication at the OAuth provider web site.
     * Note: this should be absolute URL (with http:// or https:// leading).
     * By default current URL will be used.
     */
    protected string $returnUrl;
    /**
     * @var OAuthToken|array access token instance or its array configuration.
     */
    protected array $accessToken;
    /**
     * @var array|BaseMethod signature method instance or its array configuration.
     */
    protected array $signatureMethod = [];

    /**
     * BaseOAuth constructor.
     * @param string|null $endpoint
     * @param \Psr\Http\Client\ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     */
    public function __construct(
        ?string $endpoint,
        \Psr\Http\Client\ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        parent::__construct($httpClient, $requestFactory, $stateStorage);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getAuthUrl(): string
    {
        return $this->authUrl;
    }

    public function setAuthUrl(string $authUrl): void
    {
        $this->authUrl = $authUrl;
    }

    /**
     * @param string $returnUrl return URL
     */
    public function setReturnUrl($returnUrl): void
    {
        $this->returnUrl = $returnUrl;
    }

    /**
     * @return string return URL.
     */
    public function getReturnUrl(): string
    {
        if ($this->returnUrl === null) {
            $this->returnUrl = $this->defaultReturnUrl();
        }
        return $this->returnUrl;
    }

    /**
     * Sets access token to be used.
     * @param array|OAuthToken $token access token or its configuration.
     */
    public function setAccessToken($token): void
    {
        if (!is_object($token) && $token !== null) {
            $token = $this->createToken($token);
        }
        $this->accessToken = $token;
        $this->saveAccessToken($token);
    }

    /**
     * @return OAuthToken auth token instance.
     */
    public function getAccessToken(): OAuthToken
    {
        if (!is_object($this->accessToken)) {
            $this->accessToken = $this->restoreAccessToken();
        }

        return $this->accessToken;
    }

    /**
     * Set signature method to be used.
     * @param array|BaseMethod $signatureMethod signature method instance or its array configuration.
     * @throws InvalidArgumentException on wrong argument.
     */
    public function setSignatureMethod($signatureMethod): void
    {
        if (!is_object($signatureMethod) && !is_array($signatureMethod)) {
            throw new InvalidArgumentException(
                '"' . get_class($this) . '::signatureMethod"'
                . ' should be instance of "\Yiisoft\Yii\AuthClient\Signature\BaseMethod" or its array configuration. "'
                . gettype($signatureMethod) . '" has been given.'
            );
        }
        $this->signatureMethod = $signatureMethod;
    }

    /**
     * @return BaseMethod signature method instance.
     */
    public function getSignatureMethod(): BaseMethod
    {
        if (!is_object($this->signatureMethod)) {
            $this->signatureMethod = $this->createSignatureMethod($this->signatureMethod);
        }

        return $this->signatureMethod;
    }

    public function withAutoRefreshAccessToken(): self
    {
        $this->autoRefreshAccessToken = true;
        return $this;
    }

    public function withoutAutoRefreshAccessToken(): self
    {
        $this->autoRefreshAccessToken = false;
        return $this;
    }

    /**
     * Composes default [[returnUrl]] value.
     * @return string return URL.
     */
    protected function defaultReturnUrl(): string
    {
        return Yii::getApp()->getRequest()->getAbsoluteUrl();
    }

    /**
     * Creates signature method instance from its configuration.
     * @param array $signatureMethodConfig signature method configuration.
     * @return BaseMethod signature method instance.
     */
    protected function createSignatureMethod(array $signatureMethodConfig): BaseMethod
    {
        if (!array_key_exists('__class', $signatureMethodConfig)) {
            $signatureMethodConfig['__class'] = Signature\HmacSha::class;
            $signatureMethodConfig['__construct()'] = ['sha1'];
        }
        return Yii::createObject($signatureMethodConfig);
    }

    /**
     * Creates token from its configuration.
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = []): OAuthToken
    {
        if (!array_key_exists('__class', $tokenConfig)) {
            $tokenConfig['__class'] = OAuthToken::class;
        }
        return Yii::createObject($tokenConfig);
    }

    /**
     * Composes URL from base URL and GET params.
     * @param string $url base URL.
     * @param array $params GET params.
     * @return string composed URL.
     */
    protected function composeUrl($url, array $params = []): string
    {
        if (!empty($params)) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * Saves token as persistent state.
     * @param OAuthToken|null $token auth token to be saved.
     * @return $this the object itself.
     */
    protected function saveAccessToken($token): self
    {
        return $this->setState('token', $token);
    }

    /**
     * Restores access token.
     * @return OAuthToken auth token.
     */
    protected function restoreAccessToken(): OAuthToken
    {
        $token = $this->getState('token');
        if (is_object($token)) {
            /* @var $token OAuthToken */
            if ($token->getIsExpired() && $this->autoRefreshAccessToken) {
                $token = $this->refreshAccessToken($token);
            }
        }
        return $token;
    }

    /**
     * Creates an HTTP request for the API call.
     * The created request will be automatically processed adding access token parameters and signature
     * before sending. You may use {@see createRequest()} to gain full control over request composition and execution.
     * @param string $method
     * @param string $uri
     * @return RequestInterface HTTP request instance.
     * @see createRequest()
     */
    public function createApiRequest(string $method, string $uri): RequestInterface
    {
        return $this->createRequest($method, $this->endpoint . $uri);
    }

    public function beforeApiRequestSend(RequestInterface $request): RequestInterface
    {
        $accessToken = $this->getAccessToken();
        if (!is_object($accessToken) || !$accessToken->getIsValid()) {
            throw new Exception('Invalid access token.');
        }

        return $this->applyAccessTokenToRequest($request, $accessToken);
    }

    /**
     * Performs request to the OAuth API returning response data.
     * You may use [[createApiRequest()]] method instead, gaining more control over request execution.
     * @param string $apiSubUrl API sub URL, which will be append to [[apiBaseUrl]], or absolute API URL.
     * @param string $method request method.
     * @param array|string $data request data or content.
     * @param array $headers additional request headers.
     * @return array API response data.
     * @throws Exception
     * @see createApiRequest()
     */
    public function api($apiSubUrl, $method = 'GET', $data = [], $headers = []): array
    {
        $request = $this->createApiRequest($method, $apiSubUrl);
        $request = RequestUtil::addHeaders($request, $headers);

        if (!empty($data)) {
            if (is_array($data)) {
                $request = RequestUtil::addParams($request, $data);
            } else {
                $request->getBody()->write($data);
            }
        }

        $request = $this->beforeApiRequestSend($request);
        $response = $this->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new InvalidResponseException(
                $response,
                'Request failed with code: ' . $response->getStatusCode() . ', message: ' . $response->getBody()
            );
        }

        // TODO: parse response body into array
        return $response->getBody();
    }

    /**
     * Gets new auth token to replace expired one.
     * @param OAuthToken $token expired auth token.
     * @return OAuthToken new auth token.
     */
    abstract public function refreshAccessToken(OAuthToken $token): OAuthToken;

    /**
     * Applies access token to the HTTP request instance.
     * @param RequestInterface $request HTTP request instance.
     * @param OAuthToken $accessToken access token instance.
     */
    abstract public function applyAccessTokenToRequest(
        RequestInterface $request,
        OAuthToken $accessToken
    ): RequestInterface;

    /**
     * @return string
     */
    public function getScope(): string
    {
        if ($this->scope === null) {
            return $this->getDefaultScope();
        }

        return $this->scope;
    }

    /**
     * @param string $scope
     */
    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    protected function getDefaultScope(): string
    {
        return '';
    }
}
