<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;
use Yiisoft\Yii\AuthClient\Signature\Signature;

use function is_array;
use function is_object;

/**
 * BaseOAuth is a base class for the OAuth clients.
 *
 * @link https://oauth.net/
 */
abstract class OAuth extends AuthClient
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
    protected ?string $scope = null;
    /**
     * @var bool whether to automatically perform 'refresh access token' request on expired access token.
     */
    protected bool $autoRefreshAccessToken = true;

    /**
     * @var string URL, which user will be redirected after authentication at the OAuth provider web site.
     * Note: this should be absolute URL (with http:// or https:// leading).
     * By default current URL will be used.
     */
    protected ?string $returnUrl = null;
    /**
     * @var array|OAuthToken|null access token instance or its array configuration.
     */
    protected $accessToken = null;
    /**
     * @var array|Signature|null signature method instance or its array configuration.
     */
    protected array|Signature|null $signatureMethod = null;
    private Factory $factory;

    /**
     * @param ServerRequestInterface $request
     *
     * @return string return URL.
     */
    public function getReturnUrl(ServerRequestInterface $request): string
    {
        if ($this->returnUrl === null) {
            $this->returnUrl = $this->defaultReturnUrl($request);
        }
        return $this->returnUrl;
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
        return (string)$request->getUri();
    }

    /**
     * @return Signature|null|array signature method instance.
     */
    public function getSignatureMethod(): Signature|null|array
    {
        $signatureMethod = $this->signatureMethod;
        if ((!($signatureMethod) instanceof Signature) && (null!== $signatureMethod)) {
            $this->signatureMethod = $this->createSignatureMethod($signatureMethod);
        }

        return $this->signatureMethod;
    }

    /**
     * Creates signature method instance from its configuration.
     * @param array $signatureMethodConfig
     * @psalm-suppress InvalidReturnType
     */
    protected function createSignatureMethod(array $signatureMethodConfig): Signature|null|array
    {
        if (!array_key_exists('class', $signatureMethodConfig)) {
            $signatureMethodConfig['class'] = HmacSha::class;
            $signatureMethodConfig['__construct()'] = ['sha1'];
        }
        /**
         * @psalm-suppress MixedAssignment
         */
        $signature = $this->factory->create($signatureMethodConfig);
        if ($signature instanceof Signature) {
            return $signature;
        }
        if (is_array($signature)) {
            return $signature;
        }
        if (null==$signature) {
            return null;
        }
    }

    /**
     * Performs request to the OAuth API returning response data.
     * You may use {@see createApiRequest()} method instead, gaining more control over request execution.
     *
     * @param string $apiSubUrl API sub URL, which will be append to {@see apiBaseUrl}, or absolute API URL.
     * @param string $method request method.
     * @param array|string $data request data or content.
     * @param array $headers additional request headers.
     *
     * @throws Exception
     *
     * @return array API response data.
     *
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

        return (array)Json::decode($response->getBody()->getContents());
    }

    /**
     * Creates an HTTP request for the API call.
     * The created request will be automatically processed adding access token parameters and signature
     * before sending. You may use {@see createRequest()} to gain full control over request composition and execution.
     *
     * @param string $method
     * @param string $uri
     *
     * @return RequestInterface HTTP request instance.
     *
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
     * @return OAuthToken|null auth token instance.
     */
    public function getAccessToken(): ?OAuthToken
    {
        if (!is_object($this->accessToken)) {
            $this->accessToken = $this->restoreAccessToken();
        }

        return $this->accessToken;
    }

    /**
     * Sets access token to be used.
     *
     * @param array|OAuthToken $token access token or its configuration.
     */
    public function setAccessToken(array|OAuthToken $token): void
    {
        if (is_array($token)) {
            /**
             * @psalm-suppress MixedAssignment $newToken
             */
            $newToken = $this->createToken($token);
            /**
             * @psalm-suppress MixedAssignment $this->accessToken
             */
            $this->accessToken = $newToken;
            /**
             * @psalm-suppress MixedArgument $newToken
             */
            $this->saveAccessToken($newToken);
        }
        if ($token instanceof OAuthToken) {
            $this->accessToken = $token;
            $this->saveAccessToken($token);
        }
    }

    /**
     * Restores access token.
     *
     * @return null|OAuthToken 
     */
    protected function restoreAccessToken(): ?OAuthToken
    {
        /**
         * @psalm-suppress MixedAssignment $token
         */
        if (($token = $this->getState('token')) instanceof OAuthToken) {
            if ($token->getIsExpired() && $this->autoRefreshAccessToken) {
                $refreshedToken = $this->refreshAccessToken($token);
                return $refreshedToken;
            }            
            return $token;
        }
        return null;
    }

    /**
     * Gets new auth token to replace expired one.
     *
     * @param OAuthToken $token expired auth token.
     *
     * @return OAuthToken new auth token.
     */
    abstract public function refreshAccessToken(OAuthToken $token): OAuthToken;

    /**
     * Applies access token to the HTTP request instance.
     *
     * @param RequestInterface $request HTTP request instance.
     * @param OAuthToken $accessToken access token instance.
     */
    abstract public function applyAccessTokenToRequest(
        RequestInterface $request,
        OAuthToken $accessToken
    ): RequestInterface;

    /**
     * Creates token from its configuration.
     *
     * @param array $tokenConfig token configuration.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     * @see Yiisoft\Factory\Factory
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType OAuthToken
     */
    protected function createToken(array $tokenConfig = []) : OAuthToken
    {
        if (!array_key_exists('class', $tokenConfig)) {
            $tokenConfig['class'] = OAuthToken::class;
        }
        return $this->factory->create($tokenConfig);
    }

    /**
     * Saves token as persistent state.
     *
     * @param OAuthToken|null $token auth token to be saved.
     *
     * @return $this the object itself.
     */
    protected function saveAccessToken($token): self
    {
        return $this->setState('token', $token);
    }

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
     * @return string
     *
     * @psalm-return ''
     */
    protected function getDefaultScope(): string
    {
        return '';
    }
}
