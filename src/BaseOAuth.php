<?php

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

use function is_array;
use function is_object;

/**
 * BaseOAuth is a base class for the OAuth clients.
 *
 * @see http://oauth.net/
 *
 * @property OAuthToken $accessToken Auth token instance. Note that the type of this property differs in
 * getter and setter. See [[getAccessToken()]] and [[setAccessToken()]] for details.
 * @property string $returnUrl Return URL.
 * @property signature\BaseMethod $signatureMethod Signature method instance. Note that the type of this
 * property differs in getter and setter. See [[getSignatureMethod()]] and [[setSignatureMethod()]] for details.
 */
abstract class BaseOAuth extends BaseClient
{

    /**
     * @var string API base URL.
     * This field will be used as [[\yii\httpclient\Client::baseUrl]] value of [[httpClient]].
     * Note: changing this property will take no effect after [[httpClient]] is instantiated.
     */
    private $endpoint;
    /**
     * @var string authorize URL.
     */
    public $authUrl;
    /**
     * @var string auth request scope.
     */
    private $scope;
    /**
     * @var bool whether to automatically perform 'refresh access token' request on expired access token.
     * @since 2.0.6
     */
    public $autoRefreshAccessToken = true;

    /**
     * @var string URL, which user will be redirected after authentication at the OAuth provider web site.
     * Note: this should be absolute URL (with http:// or https:// leading).
     * By default current URL will be used.
     */
    private $_returnUrl;
    /**
     * @var OAuthToken|array access token instance or its array configuration.
     */
    private $_accessToken;
    /**
     * @var signature\BaseMethod|array signature method instance or its array configuration.
     */
    private $_signatureMethod = [];

    /**
     * BaseOAuth constructor.
     */
    public function __construct(
        ?string $endpoint,
        \Psr\Http\Client\ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        parent::__construct($httpClient, $requestFactory);
    }

    /**
     * @param string $returnUrl return URL
     */
    public function setReturnUrl($returnUrl)
    {
        $this->_returnUrl = $returnUrl;
    }

    /**
     * @return string return URL.
     */
    public function getReturnUrl()
    {
        if ($this->_returnUrl === null) {
            $this->_returnUrl = $this->defaultReturnUrl();
        }
        return $this->_returnUrl;
    }

    /**
     * Sets access token to be used.
     * @param array|OAuthToken $token access token or its configuration.
     */
    public function setAccessToken($token)
    {
        if (!is_object($token) && $token !== null) {
            $token = $this->createToken($token);
        }
        $this->_accessToken = $token;
        $this->saveAccessToken($token);
    }

    /**
     * @return OAuthToken auth token instance.
     */
    public function getAccessToken()
    {
        if (!is_object($this->_accessToken)) {
            $this->_accessToken = $this->restoreAccessToken();
        }

        return $this->_accessToken;
    }

    /**
     * Set signature method to be used.
     * @param array|signature\BaseMethod $signatureMethod signature method instance or its array configuration.
     * @throws InvalidArgumentException on wrong argument.
     */
    public function setSignatureMethod($signatureMethod)
    {
        if (!is_object($signatureMethod) && !is_array($signatureMethod)) {
            throw new InvalidArgumentException(
                '"' . get_class(
                    $this
                ) . '::signatureMethod" should be instance of "\yii\autclient\signature\BaseMethod" or its array configuration. "' . gettype(
                    $signatureMethod
                ) . '" has been given.'
            );
        }
        $this->_signatureMethod = $signatureMethod;
    }

    /**
     * @return signature\BaseMethod signature method instance.
     */
    public function getSignatureMethod()
    {
        if (!is_object($this->_signatureMethod)) {
            $this->_signatureMethod = $this->createSignatureMethod($this->_signatureMethod);
        }

        return $this->_signatureMethod;
    }

    /**
     * Composes default [[returnUrl]] value.
     * @return string return URL.
     */
    protected function defaultReturnUrl()
    {
        return Yii::getApp()->getRequest()->getAbsoluteUrl();
    }

    /**
     * Creates signature method instance from its configuration.
     * @param array $signatureMethodConfig signature method configuration.
     * @return signature\BaseMethod signature method instance.
     */
    protected function createSignatureMethod(array $signatureMethodConfig)
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
    protected function createToken(array $tokenConfig = [])
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
    protected function composeUrl($url, array $params = [])
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
    protected function saveAccessToken($token)
    {
        return $this->setState('token', $token);
    }

    /**
     * Restores access token.
     * @return OAuthToken auth token.
     */
    protected function restoreAccessToken()
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
     * before sending. You may use [[createRequest()]] to gain full control over request composition and execution.
     * @return RequestInterface HTTP request instance.
     * @see createRequest()
     */
    public function createApiRequest(string $method, string $uri): RequestInterface
    {
        $request = $this->createRequest($method, $this->endpoint . $uri);
        return $request;
    }


    public function beforeApiRequestSend(RequestInterface $request)
    {
        $accessToken = $this->getAccessToken();
        if (!is_object($accessToken) || !$accessToken->getIsValid()) {
            throw new \Exception('Invalid access token.');
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
     * @see createApiRequest()
     */
    public function api($apiSubUrl, $method = 'GET', $data = [], $headers = [])
    {
        $request = $this->createApiRequest($method, $apiSubUrl)
            ->addHeaders($headers);

        if (!empty($data)) {
            if (is_array($data)) {
                $request->setParams($data);
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
    abstract public function refreshAccessToken(OAuthToken $token);

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
