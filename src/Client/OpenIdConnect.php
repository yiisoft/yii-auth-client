<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Exception;
use HttpException;
use function in_array;
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
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Yiisoft\Factory\FactoryInterface;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;

use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

/**
 * OpenIdConnect serves as a client for the OpenIdConnect flow.
 *
 * This class requires `web-token/jwt-checker`,`web-token/jwt-key-mgmt`, `web-token/jwt-signature`, `web-token/jwt-signature-algorithm-hmac`,
 * `web-token/jwt-signature-algorithm-ecdsa` and `web-token/jwt-signature-algorithm-rsa` libraries to be installed for
 * JWS verification. This can be done via composer:
 *
 * ```
 * composer require --prefer-dist "web-token/jwt-checker:>=1.0 <3.0" "web-token/jwt-signature:>=1.0 <3.0"
 * "web-token/jwt-signature:>=1.0 <3.0" "web-token/jwt-signature-algorithm-hmac:>=1.0 <3.0"
 * "web-token/jwt-signature-algorithm-ecdsa:>=1.0 <3.0" "web-token/jwt-signature-algorithm-rsa:>=1.0 <3.0"
 * ```
 *
 * Note: if you are using well-trusted OpenIdConnect provider, you may disable {@see validateJws}, making installation of
 * `web-token` library redundant, however it is not recommended as it violates the protocol specification.
 *
 * @link http://openid.net/connect/
 * @see OAuth2
 */
final class OpenIdConnect extends OAuth2
{
    protected ?string $scope = 'openid';
    /**
     * @var string OpenID Issuer (provider) base URL, e.g. `https://example.com`.
     */
    private string $issuerUrl;
    /**
     * @var bool whether to validate/decrypt JWS received with Auth token.
     * Note: this functionality requires `web-token/jwt-checker`, `web-token/jwt-key-mgmt`, `web-token/jwt-signature`
     * composer package to be installed. You can disable this option in case of usage of trusted OpenIDConnect provider,
     * however this violates the protocol rules, so you are doing it on your own risk.
     */
    private bool $validateJws = true;
    /**
     * @var array JWS algorithms, which are allowed to be used.
     * These are used by `web-token` library for JWS validation/decryption.
     * Make sure to install `web-token/jwt-signature-algorithm-hmac`, `web-token/jwt-signature-algorithm-ecdsa`
     * and `web-token/jwt-signature-algorithm-rsa` packages that support the particular algorithm before adding it here.
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
        'PS512',
    ];

    /**
     * @var string the prefix for the key used to store {@see configParams} data in cache.
     * Actual cache key will be formed addition {@see id} value to it.
     *
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
    private array $configParams = [];
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
     *
     * @param string|null $endpoint
     * @param $name
     * @param $title
     * @param AuthClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @param CacheInterface $cache
     * @param StateStorageInterface $stateStorage
     * @param SessionInterface $session
     */
    public function __construct(
        $name,
        $title,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        CacheInterface $cache,
        StateStorageInterface $stateStorage,
        SessionInterface $session,
        FactoryInterface $factory
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->cache = $cache;
        parent::__construct($httpClient, $requestFactory, $stateStorage, $session, $factory);
    }

    public function buildAuthUrl(
        ServerRequestInterface $incomingRequest,
        array $params = []
    ): string {
        if ($this->authUrl === null) {
            $this->authUrl = $this->getConfigParam('authorization_endpoint');
        }
        return parent::buildAuthUrl($incomingRequest, $params);
    }

    /**
     * Returns particular configuration parameter value.
     *
     * @param string $name configuration parameter name.
     *
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     *
     * @return mixed configuration parameter value.
     */
    public function getConfigParam($name)
    {
        $params = $this->getConfigParams();
        return $params[$name];
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     *
     * @return array OpenID provider configuration parameters.
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
     * @return string service name.
     */
    public function getName(): string
    {
        return 'open_id_connect';
    }

    /**
     * Discovers OpenID Provider configuration parameters.
     *
     * @throws InvalidConfigException
     *
     * @return array OpenID Provider configuration parameters.
     */
    private function discoverConfig(): array
    {
        if ($this->issuerUrl === null) {
            throw new InvalidConfigException('Cannot discover config because issuer URL is not set.');
        }
        $configUrl = $this->issuerUrl . '/.well-known/openid-configuration';
        $request = $this->createRequest('GET', $configUrl);
        $response = $this->sendRequest($request);

        return Json::decode($response->getBody()->getContents());
    }

    public function fetchAccessToken(ServerRequestInterface $request, $authCode, array $params = []): OAuthToken
    {
        if ($this->tokenUrl === null) {
            $this->tokenUrl = $this->getConfigParam('token_endpoint');
        }

        if (!isset($params['nonce']) && $this->getValidateAuthNonce()) {
            $nonce = $this->generateAuthNonce();
            $this->setState('authNonce', $nonce);
            $params['nonce'] = $nonce;
        }

        return parent::fetchAccessToken($request, $authCode, $params);
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     *
     * @return bool whether to use and validate auth 'nonce' parameter in authentication flow.
     */
    public function getValidateAuthNonce(): bool
    {
        if ($this->validateAuthNonce === null) {
            $this->validateAuthNonce = $this->validateJws && in_array(
                    'nonce',
                    $this->getConfigParam('claims_supported'),
                    true
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
     * Generates the auth nonce value.
     *
     * @throws Exception
     *
     * @return string auth nonce value.
     */
    protected function generateAuthNonce(): string
    {
        return Random::string();
    }

    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        if ($this->tokenUrl === null) {
            $this->tokenUrl = $this->getConfigParam('token_endpoint');
        }
        return parent::refreshAccessToken($token);
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
                'Unable to authenticate request: none of following auth methods is supported: ' . implode(
                    ', ',
                    $supportedAuthMethods
                )
            );
        }
        return $request;
    }

    protected function defaultReturnUrl(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        // OAuth2 specifics :
        unset($params['code'], $params['state'], $params['nonce'], $params['authuser'], $params['session_state'], $params['prompt']);
        // OpenIdConnect specifics :


        return $request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986))->__toString();
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
                    throw new HttpException('Invalid auth nonce', 400);
                }

                $this->removeState('authNonce');
            }
        }

        return parent::createToken($tokenConfig);
    }

    /**
     * Decrypts/validates JWS, returning related data.
     *
     * @param string $jws raw JWS input.
     *
     * @throws HttpException on invalid JWS signature.
     * @throws InvalidArgumentException
     *
     * @return array JWS underlying data.
     */
    protected function loadJws(string $jws): array
    {
        try {
            $jwsLoader = $this->getJwsLoader();
            $signature = null;
            $jwsVerified = $jwsLoader->loadAndVerifyWithKeySet($jws, $this->getJwkSet(), $signature);
            return Json::decode($jwsVerified->getPayload());
        } catch (Exception $e) {
            $message = YII_DEBUG ? 'Unable to verify JWS: ' . $e->getMessage() : 'Invalid JWS';
            throw new HttpException($message, $e->getCode(), $e);
        }
    }

    /**
     * Return JWSLoader that validate the JWS token.
     *
     * @throws InvalidConfigException on invalid algorithm provide in configuration.
     *
     * @return JWSLoader to do token validation.
     */
    protected function getJwsLoader(): JWSLoader
    {
        if ($this->jwsLoader === null) {
            $algorithms = [];
            foreach ($this->allowedJwsAlgorithms as $algorithm) {
                $class = '\Jose\Component\Signature\Algorithm\\' . $algorithm;
                if (!class_exists($class)) {
                    throw new InvalidConfigException("Algorithm class $class doesn't exist");
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
     * Return JwkSet, returning related data.
     *
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     *
     * @return JWKSet object represents a key set.
     */
    protected function getJwkSet(): JWKSet
    {
        if ($this->jwkSet === null) {
            $cacheKey = $this->configParamsCacheKeyPrefix . 'jwkSet';
            if (($jwkSet = $this->cache->get($cacheKey)) === false) {
                $request = $this->createRequest('GET', $this->getConfigParam('jwks_uri'));
                $response = $this->sendRequest($request);
                $jwkSet = JWKFactory::createFromValues($response);
            }

            $this->jwkSet = $jwkSet;
            $this->cache->set($cacheKey, $jwkSet);
        }
        return $this->jwkSet;
    }

    /**
     * Validates the claims data received from OpenID provider.
     *
     * @param array $claims claims data.
     *
     * @throws HttpException on invalid claims.
     */
    protected function validateClaims(array $claims): void
    {
        if (!isset($claims['iss']) || (strcmp(rtrim($claims['iss'], '/'), rtrim($this->issuerUrl, '/')) !== 0)) {
            throw new HttpException('Invalid "iss"', 400);
        }
        if (!isset($claims['aud']) || (strcmp($claims['aud'], $this->clientId) !== 0)) {
            throw new HttpException('Invalid "aud"', 400);
        }
    }
}
