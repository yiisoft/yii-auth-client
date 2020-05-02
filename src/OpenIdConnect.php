<?php

namespace Yiisoft\Yii\AuthClient;

use Exception;
use Jose\Factory\JWKFactory;
use Jose\Loader;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;
use yii\exceptions\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\Yii;
use yii\web\HttpException;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;

use function in_array;

/**
 * OpenIdConnect serves as a client for the OpenIdConnect flow.
 *
 * Application configuration example:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'google' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\OpenIdConnect::class,
 *                 'issuerUrl' => 'https://accounts.google.com',
 *                 'clientId' => 'google_client_id',
 *                 'clientSecret' => 'google_client_secret',
 *                 'name' => 'google',
 *                 'title' => 'Google OpenID Connect',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * This class requires `spomky-labs/jose` library to be installed for JWS verification. This can be done via composer:
 *
 * ```
 * composer require --prefer-dist "spomky-labs/jose:~5.0.6"
 * ```
 *
 * Note: if you are using well-trusted OpenIdConnect provider, you may disable [[validateJws]], making installation of
 * `spomky-labs/jose` library redundant, however it is not recommended as it violates the protocol specification.
 *
 * @see http://openid.net/connect/
 * @see OAuth2
 */
class OpenIdConnect extends OAuth2
{
    public $scope = 'openid';
    /**
     * @var string OpenID Issuer (provider) base URL, e.g. `https://example.com`.
     */
    private $issuerUrl;
    /**
     * @var bool whether to validate/decrypt JWS received with Auth token.
     * Note: this functionality requires `spomky-labs/jose` composer package to be installed.
     * You can disable this option in case of usage of trusted OpenIDConnect provider, however this violates
     * the protocol rules, so you are doing it on your own risk.
     */
    public $validateJws = true;
    /**
     * @var array JWS algorithms, which are allowed to be used.
     * These are used by `spomky-labs/jose` library for JWS validation/decryption.
     * Make sure `spomky-labs/jose` supports the particular algorithm before adding it here.
     */
    public $allowedJwsAlgorithms = [
        'HS256',
        'HS384',
        'HS512',
        'ES256',
        'ES384',
        'ES512',
        'RS256',
        'RS384',
        'RS512',
        'PS256',
        'PS384',
        'PS512'
    ];
    /**
     * @var string the prefix for the key used to store [[configParams]] data in cache.
     * Actual cache key will be formed addition [[id]] value to it.
     * @see cache
     */
    public $configParamsCacheKeyPrefix = 'config-params-';

    /**
     * @var bool|null whether to use and validate auth 'nonce' parameter in authentication flow.
     * The option is used for preventing replay attacks.
     */
    private $validateAuthNonce;
    /**
     * @var array OpenID provider configuration parameters.
     */
    private $configParams;
    /**
     * @var CacheInterface the cache object or the ID of the cache application component that
     * is used for caching. This can be one of the following:
     *
     * - an application component ID (e.g. `cache`)
     * - a configuration array
     * - a [[\yii\caching\Cache]] object
     *
     * When this is not set, it means caching is not enabled.
     */
    private $cache;

    private $name;
    private $title;

    /**
     * OpenIdConnect constructor.
     * @param CacheInterface $cache
     */
    public function __construct(
        ?string $endpoint,
        $name,
        $title,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        CacheInterface $cache
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->cache = $cache;
        parent::__construct($endpoint, $httpClient, $requestFactory);
    }

    /**
     * @return bool whether to use and validate auth 'nonce' parameter in authentication flow.
     */
    public function getValidateAuthNonce()
    {
        if ($this->validateAuthNonce === null) {
            $this->validateAuthNonce = $this->validateJws && in_array(
                    'nonce',
                    $this->getConfigParam('claims_supported')
                );
        }
        return $this->validateAuthNonce;
    }

    /**
     * @param bool $validateAuthNonce whether to use and validate auth 'nonce' parameter in authentication flow.
     */
    public function setValidateAuthNonce($validateAuthNonce)
    {
        $this->validateAuthNonce = $validateAuthNonce;
    }

    /**
     * @return array OpenID provider configuration parameters.
     */
    public function getConfigParams()
    {
        if ($this->configParams === null) {
            $cacheKey = $this->configParamsCacheKeyPrefix . $this->getName();
            if (($configParams = $this->cache->get($cacheKey)) === null) {
                $configParams = $this->discoverConfig();
            }

            $this->configParams = $configParams;
            $this->cache->set($cacheKey, $configParams);
        }
        return $this->configParams;
    }

    /**
     * Returns particular configuration parameter value.
     * @param string $name configuration parameter name.
     * @return mixed configuration parameter value.
     */
    public function getConfigParam($name)
    {
        $params = $this->getConfigParams();
        return $params[$name];
    }

    /**
     * Discovers OpenID Provider configuration parameters.
     * @return array OpenID Provider configuration parameters.
     * @throws InvalidResponseException on failure.
     */
    private function discoverConfig()
    {
        if ($this->issuerUrl === null) {
            throw new InvalidConfigException('Cannot discover config because issuer URL is not set.');
        }
        $configUrl = $this->issuerUrl . '/.well-known/openid-configuration';
        $request = $this->createRequest('GET', $configUrl);
        $response = $this->sendRequest($request);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function buildAuthUrl(array $params = [])
    {
        if ($this->authUrl === null) {
            $this->authUrl = $this->getConfigParam('authorization_endpoint');
        }
        return parent::buildAuthUrl($params);
    }

    public function fetchAccessToken($authCode, array $params = [])
    {
        if ($this->tokenUrl === null) {
            $this->tokenUrl = $this->getConfigParam('token_endpoint');
        }

        if (!isset($params['nonce']) && $this->getValidateAuthNonce()) {
            $nonce = $this->generateAuthNonce();
            $this->setState('authNonce', $nonce);
            $params['nonce'] = $nonce;
        }

        return parent::fetchAccessToken($authCode, $params);
    }

    public function refreshAccessToken(OAuthToken $token)
    {
        if ($this->tokenUrl === null) {
            $this->tokenUrl = $this->getConfigParam('token_endpoint');
        }
        return parent::refreshAccessToken($token);
    }

    protected function initUserAttributes()
    {
        return $this->api($this->getConfigParam('userinfo_endpoint'), 'GET');
    }

    protected function applyClientCredentialsToRequest(RequestInterface $request): RequestInterface
    {
        $supportedAuthMethods = $this->getConfigParam('token_endpoint_auth_methods_supported');

        if (in_array('client_secret_basic', $supportedAuthMethods, true)) {
            $request = $request->withHeader(
                'Authorization',
                'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
            );
        } elseif (in_array('client_secret_post', $supportedAuthMethods, true)) {
            $request->addParams(
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            );
        } elseif (in_array('client_secret_jwt', $supportedAuthMethods, true)) {
            $header = [
                'typ' => 'JWT',
                'alg' => 'HS256',
            ];
            $payload = [
                'iss' => $this->clientId,
                'sub' => $this->clientId,
                'aud' => $this->tokenUrl,
                'jti' => $this->generateAuthNonce(),
                'iat' => time(),
                'exp' => time() + 3600,
            ];

            $signatureBaseString = base64_encode(Json::encode($header)) . '.' . base64_encode(Json::encode($payload));
            $signatureMethod = new HmacSha(['algorithm' => 'sha256']);
            $signature = $signatureMethod->generateSignature($signatureBaseString, $this->clientSecret);

            $assertion = $signatureBaseString . '.' . $signature;

            $request->addParams(
                [
                    'assertion' => $assertion,
                ]
            );
        } else {
            throw new InvalidConfigException(
                'Unable to authenticate request: none of following auth methods is suported: ' . implode(
                    ', ',
                    $supportedAuthMethods
                )
            );
        }
    }

    protected function defaultReturnUrl()
    {
        $params = Yii::getApp()->getRequest()->getQueryParams();
        // OAuth2 specifics :
        unset($params['code'], $params['state']);
        // OpenIdConnect specifics :
        unset($params['nonce'], $params['authuser'], $params['session_state'], $params['prompt']);
        $params[0] = Yii::getApp()->controller->getRoute();

        return Yii::getApp()->getUrlManager()->createAbsoluteUrl($params);
    }

    protected function createToken(array $tokenConfig = [])
    {
        if ($this->validateJws) {
            $jwsData = $this->loadJws($tokenConfig['params']['id_token']);
            $this->validateClaims($jwsData);
            $tokenConfig['params'] = array_merge($tokenConfig['params'], $jwsData);

            if ($this->getValidateAuthNonce()) {
                $authNonce = $this->getState('authNonce');
                if (!isset($jwsData['nonce']) || empty($authNonce) || strcmp($jwsData['nonce'], $authNonce) !== 0) {
                    throw new HttpException(400, 'Invalid auth nonce');
                }

                $this->removeState('authNonce');
            }
        }

        return parent::createToken($tokenConfig);
    }

    /**
     * Decrypts/validates JWS, returning related data.
     * @param string $jws raw JWS input.
     * @return array JWS underlying data.
     * @throws HttpException on invalid JWS signature.
     */
    protected function loadJws($jws)
    {
        try {
            $jwkSet = JWKFactory::createFromJKU($this->getConfigParam('jwks_uri'));
            $loader = new Loader();
            return $loader->loadAndVerifySignatureUsingKeySet($jws, $jwkSet, $this->allowedJwsAlgorithms)->getPayload();
        } catch (Exception $e) {
            $message = YII_DEBUG ? 'Unable to verify JWS: ' . $e->getMessage() : 'Invalid JWS';
            throw new HttpException(400, $message, $e->getCode(), $e);
        }
    }

    /**
     * Validates the claims data received from OpenID provider.
     * @param array $claims claims data.
     * @throws HttpException on invalid claims.
     */
    private function validateClaims(array $claims)
    {
        if (!isset($claims['iss']) || (strcmp(rtrim($claims['iss'], '/'), rtrim($this->issuerUrl, '/')) !== 0)) {
            throw new HttpException(400, 'Invalid "iss"');
        }
        if (!isset($claims['aud']) || (strcmp($claims['aud'], $this->clientId) !== 0)) {
            throw new HttpException(400, 'Invalid "aud"');
        }
    }

    /**
     * Generates the auth nonce value.
     * @return string auth nonce value.
     */
    protected function generateAuthNonce()
    {
        return Yii::getApp()->security->generateRandomString();
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'open_id_connect';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'OpenID Connect';
    }

    public function setIssuerUrl(string $url): void
    {
        $this->issuerUrl = rtrim($url, '/');
    }
}
