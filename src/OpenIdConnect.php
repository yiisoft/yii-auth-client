<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use HttpException;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

use function in_array;

/**
 * OpenIdConnect serves as a client for the OpenIdConnect flow.
 *
 * This class requires `spomky-labs/jose` library to be installed for JWS verification. This can be done via composer:
 *
 * ```
 * composer require --prefer-dist "spomky-labs/jose:~5.0.6"
 * ```
 *
 * Note: if you are using well-trusted OpenIdConnect provider, you may disable {@see validateJws}, making installation of
 * `spomky-labs/jose` library redundant, however it is not recommended as it violates the protocol specification.
 *
 * @link http://openid.net/connect/
 * @see OAuth2
 */
class OpenIdConnect extends OAuth2
{
    protected string $scope = 'openid';
    /**
     * @var string OpenID Issuer (provider) base URL, e.g. `https://example.com`.
     */
    private string $issuerUrl;
    /**
     * @var bool whether to validate/decrypt JWS received with Auth token.
     * Note: this functionality requires `web-token/*` composer package to be installed.
     * You can disable this option in case of usage of trusted OpenIDConnect provider, however this violates
     * the protocol rules, so you are doing it on your own risk.
     */
    private bool $validateJws = true;
    /**
     * @var array JWS algorithms, which are allowed to be used.
     * These are used by `spomky-labs/jose` library for JWS validation/decryption.
     * Make sure `spomky-labs/jose` supports the particular algorithm before adding it here.
     */
    private array $allowedJwsAlgorithms = [
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
     * @var string the prefix for the key used to store {@see configParams} data in cache.
     * Actual cache key will be formed addition {@see id} value to it.
     * @see cache
     */
    private string $configParamsCacheKeyPrefix = 'config-params-';

    /**
     * @var bool|null whether to use and validate auth 'nonce' parameter in authentication flow.
     * The option is used for preventing replay attacks.
     */
    private ?bool $validateAuthNonce;

    /**
     * @var array OpenID provider configuration parameters.
     */
    private array $configParams;
    private CacheInterface $cache;
    private string $name;
    private string $title;

    /**
     * @var JWSLoader JSON Web Signature
     */
    private JWSLoader $jwsLoader;
    /**
     * @var JWKSet Key Set
     */
    private JWKSet $jwkSet;

    /**
     * OpenIdConnect constructor.
     * @param string|null $endpoint
     * @param $name
     * @param $title
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @param CacheInterface $cache
     * @param StateStorageInterface $stateStorage
     */
    public function __construct(
        ?string $endpoint,
        $name,
        $title,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        CacheInterface $cache,
        StateStorageInterface $stateStorage
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->cache = $cache;
        parent::__construct($endpoint, $httpClient, $requestFactory, $stateStorage);
    }

    /**
     * @return bool whether to use and validate auth 'nonce' parameter in authentication flow.
     * @throws InvalidConfigException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getValidateAuthNonce(): bool
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
    public function setValidateAuthNonce($validateAuthNonce): void
    {
        $this->validateAuthNonce = $validateAuthNonce;
    }

    /**
     * @return array OpenID provider configuration parameters.
     * @throws InvalidConfigException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getConfigParams(): array
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
     * @throws InvalidConfigException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getConfigParam($name)
    {
        $params = $this->getConfigParams();
        return $params[$name];
    }

    /**
     * Discovers OpenID Provider configuration parameters.
     * @return array OpenID Provider configuration parameters.
     * @throws InvalidConfigException
     */
    private function discoverConfig(): array
    {
        if ($this->issuerUrl === null) {
            throw new InvalidConfigException('Cannot discover config because issuer URL is not set.');
        }
        $configUrl = $this->issuerUrl . '/.well-known/openid-configuration';
        $request = $this->createRequest('GET', $configUrl);
        $response = $this->sendRequest($request);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function buildAuthUrl(array $params = []): string
    {
        if ($this->authUrl === null) {
            $this->authUrl = $this->getConfigParam('authorization_endpoint');
        }
        return parent::buildAuthUrl($params);
    }

    public function fetchAccessToken($authCode, array $params = []): OAuthToken
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

    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        if ($this->tokenUrl === null) {
            $this->tokenUrl = $this->getConfigParam('token_endpoint');
        }
        return parent::refreshAccessToken($token);
    }

    protected function initUserAttributes(): array
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
            $request = RequestUtil::addParams(
                $request,
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
            $signatureMethod = new HmacSha('sha256');
            $signature = $signatureMethod->generateSignature($signatureBaseString, $this->clientSecret);

            $assertion = $signatureBaseString . '.' . $signature;

            $request = RequestUtil::addParams(
                $request,
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
        return $request;
    }

    protected function defaultReturnUrl(): string
    {
        $params = Yii::getApp()->getRequest()->getQueryParams();
        // OAuth2 specifics :
        unset($params['code'], $params['state']);
        // OpenIdConnect specifics :
        unset($params['nonce'], $params['authuser'], $params['session_state'], $params['prompt']);
        $params[0] = Yii::getApp()->controller->getRoute();

        return Yii::getApp()->getUrlManager()->createAbsoluteUrl($params);
    }

    protected function createToken(array $tokenConfig = []): OAuthToken
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
     * Return JwkSet, returning related data.
     * @return JWKSet object represents a key set.
     * @throws InvalidConfigException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function getJwkSet()
    {
        if ($this->jwkSet === null) {
            $cache = $this->cache;
            $cacheKey = $this->configParamsCacheKeyPrefix . 'jwkSet';
            if ($cache === null || ($jwkSet = $cache->get($cacheKey)) === false) {
                $request = $this->createRequest('GET', $this->getConfigParam('jwks_uri'));
                $response = $this->sendRequest($request);
                $jwkSet = JWKFactory::createFromValues($response);
            }

            $this->jwkSet = $jwkSet;

            if ($cache !== null) {
                $cache->set($cacheKey, $jwkSet);
            }
        }
        return $this->jwkSet;
    }

    /**
     * Return JWSLoader that validate the JWS token.
     * @return JWSLoader to do token validation.
     * @throws InvalidConfigException on invalid algorithm provide in configuration.
     */
    protected function getJwsLoader()
    {
        if ($this->jwsLoader === null) {
            $algorithms = [];
            foreach ($this->allowedJwsAlgorithms as $algorithm) {
                $class = '\Jose\Component\Signature\Algorithm\\' . $algorithm;
                if (!class_exists($class)) {
                    throw new InvalidConfigException("Alogrithm class $class doesn't exist");
                }
                $algorithms[] = new $class();
            }
            $this->jwsLoader = new JWSLoader(
                new JWSSerializerManager([new CompactSerializer()]),
                new JWSVerifier(new AlgorithmManager($algorithms)),
                new HeaderCheckerManager(
                    [new AlgorithmChecker($this->allowedJwsAlgorithms)],
                    [new JWSTokenSupport()]
                )
            );
        }
        return $this->jwsLoader;
    }

    /**
     * Decrypts/validates JWS, returning related data.
     * @param string $jws raw JWS input.
     * @return array JWS underlying data.
     * @throws HttpException on invalid JWS signature.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function loadJws($jws)
    {
        try {
            $jwsLoader = $this->getJwsLoader();
            $signature = null;
            $jwsVerified = $jwsLoader->loadAndVerifyWithKeySet($jws, $this->getJwkSet(), $signature);
            return Json::decode($jwsVerified->getPayload());
        } catch (\Exception $e) {
            $message = YII_DEBUG ? 'Unable to verify JWS: ' . $e->getMessage() : 'Invalid JWS';
            throw new HttpException(400, $message, $e->getCode(), $e);
        }
    }

    /**
     * Validates the claims data received from OpenID provider.
     * @param array $claims claims data.
     * @throws HttpException on invalid claims.
     */
    protected function validateClaims(array $claims)
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
     * @throws \Exception
     */
    protected function generateAuthNonce()
    {
        return Random::string();
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
