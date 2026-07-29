<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Exception;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\Algorithm;
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
use Yiisoft\Factory\Factory;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Exception\ClientException;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

use function in_array;
use function is_array;
use function is_string;
use function strlen;

use const PHP_QUERY_RFC3986;

/**
 * OpenIdConnect serves as a client for the OpenIdConnect flow.
 *
 * @link https://github.com/web-token/jwt-framework
 *
 * @link https://openid.net/connect/
 *
 * e.g.'s: https://{IdentityProviderDomain}/.well-known/openid-configuration
 *
 * https://accounts.google.com/.well-known/openid-configuration
 * https://login.microsoftonline.com/{tenant}/v2.0/.well-known/openid-configuration
 * https://oidc.account.gov.uk/.well-known/openid-configuration
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'my-oidc' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
 *             'clientId' => $_ENV['OIDC_CLIENT_ID'],
 *             'clientSecret' => $_ENV['OIDC_CLIENT_SECRET'],
 *             'issuerUrl' => 'https://your-issuer.example.com',
 *         ],
 *     ],
 * ],
 * ```
 *
 * @see OAuth2
 */
final class OpenIdConnect extends OAuth2
{
    protected string $authUrl = '';

    protected ?string $scope = 'openid';
    /**
     * @var string OpenID Issuer
     */
    private string $issuerUrl = 'https://{IdentityProviderDomain}';
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
    private ?bool $validateAuthNonce = null;

    /**
     * @var array OpenID provider configuration parameters.
     */
    private array $configParams = [];

    /**
     * @var JWSLoader|null JSON Web Signature
     */
    private ?JWSLoader $jwsLoader = null;

    private ?JWKSet $jwkSet = null;

    /**
     * OpenIdConnect constructor.
     *
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @param StateStorageInterface $stateStorage
     * @param Factory $factory
     * @param SessionInterface $session
     * @param string $name
     * @param string $title
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StateStorageInterface $stateStorage,
        Factory $factory,
        SessionInterface $session,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($httpClient, $requestFactory, $stateStorage, $factory, $session);
    }

    /**
     * @param ServerRequestInterface $incomingRequest
     * @param array $params
     * @return string
     */
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        if (strlen($this->authUrl) == 0) {
            $this->authUrl = (string) $this->getConfigParam('authorization_endpoint');
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
    public function getConfigParam(string $name): mixed
    {
        $params = $this->getConfigParams();
        return $params[$name] ?? null;
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     *
     * @return array OpenID provider configuration parameters.
     */
    public function getConfigParams(): array
    {
        if (empty($this->configParams)) {
            $cacheKey = $this->configParamsCacheKeyPrefix . $this->getName();
            if (empty($configParams = (array) $this->cache->get($cacheKey))) {
                $configParams = $this->discoverConfig();
            }

            $this->configParams = $configParams;
            $this->cache->set($cacheKey, $configParams);
        }
        return $this->configParams;
    }

    /**
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode
     * @param array $params
     * @return OAuthToken
     */
    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        if (empty($this->tokenUrl)) {
            $this->tokenUrl = (string) $this->getConfigParam('token_endpoint');
        }
        if (!isset($params['nonce']) && $this->getValidateAuthNonce()) {
            $nonce = $this->generateAuthNonce();
            $this->setState('authNonce', $nonce);
            $params['nonce'] = $nonce;
        }
        return parent::fetchAccessToken($incomingRequest, $authCode, $params);
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
                (array) $this->getConfigParam('claims_supported'),
                true,
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
     * @param OAuthToken $token
     * @return OAuthToken
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        if (strlen($this->tokenUrl) == 0) {
            $this->tokenUrl = (string) $this->getConfigParam('token_endpoint');
        }
        return parent::refreshAccessToken($token);
    }

    public function getName(): string
    {
        return $this->name ?: 'openid-connect';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'OpenID Connect';
    }

    public function getButtonClass(): string
    {
        return '';
    }

    public function setIssuerUrl(string $url): void
    {
        $this->issuerUrl = rtrim($url, '/');
    }

    /**
     * Enables JWS validation/decryption of the auth token (the default). See {@see validateJws} for details.
     */
    public function withValidateJws(): self
    {
        $new = clone $this;
        $new->validateJws = true;
        return $new;
    }

    /**
     * Disables JWS validation/decryption of the auth token. See {@see validateJws} for the trade-offs of
     * doing so.
     */
    public function withoutValidateJws(): self
    {
        $new = clone $this;
        $new->validateJws = false;
        return $new;
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

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    protected function initUserAttributes(): array
    {
        return $this->api((string) $this->getConfigParam('userinfo_endpoint'), 'GET');
    }

    protected function applyClientCredentialsToRequest(RequestInterface $request): RequestInterface
    {
        $supportedAuthMethods = (array) $this->getConfigParam('token_endpoint_auth_methods_supported');
        if (in_array('client_secret_basic', $supportedAuthMethods, true)) {
            $request = $request->withHeader(
                'Authorization',
                'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            );
        } elseif (in_array('client_secret_post', $supportedAuthMethods, true)) {
            // Appended to the body, not the query string: fetchAccessToken()/refreshAccessToken() already
            // wrote their own params into an application/x-www-form-urlencoded body per RFC 6749 §4.1.3.
            $request->getBody()->write('&' . http_build_query(
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986,
            ));
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

            $request->getBody()->write('&' . http_build_query(['assertion' => $assertion], '', '&', PHP_QUERY_RFC3986));
        } else {
            throw new InvalidConfigException(
                'Unable to authenticate request: No auth method supported',
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
        $params = (array) $tokenConfig['params'];
        $idToken = (string) ($params['id_token'] ?? '');
        if ($this->validateJws) {
            $jwsData = $this->loadJws($idToken);
            $this->validateClaims($jwsData);
            $tokenConfig['params'] = array_merge($params, $jwsData);

            if ($this->getValidateAuthNonce()) {
                $nonce = isset($jwsData['nonce']) ? (string) $jwsData['nonce'] : '';
                $authNonce = (string) $this->getState('authNonce');
                if (!isset($jwsData['nonce']) || empty($authNonce) || strcmp($nonce, $authNonce) !== 0) {
                    throw new ClientException('Invalid auth nonce', 400);
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
     * @throws ClientException on invalid JWS signature.
     *
     * @return array JWS underlying data.
     */
    protected function loadJws(string $jws): array
    {
        try {
            $jwsLoader = $this->getJwsLoader();
            $signature = null;
            $jwkSet = $this->getJwkSet();
            if ($jwkSet === null) {
                throw new ClientException('JWK Set is not available.', 400);
            }
            $jwsVerified = $jwsLoader->loadAndVerifyWithKeySet($jws, $jwkSet, $signature);
            return (array) Json::decode((string) $jwsVerified->getPayload(), true);
        } catch (Exception $e) {
            throw new ClientException('Loading JWS: Exception: ' . $e->getMessage(), (int) $e->getCode());
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
        if (!($this->jwsLoader instanceof JWSLoader)) {
            $algorithms = [];
            /** @var string $algorithm */
            foreach ($this->allowedJwsAlgorithms as $algorithm) {
                /** @var class-string<Algorithm> $class */
                $class = '\Jose\Component\Signature\Algorithm\\' . $algorithm;
                if (!class_exists($class)) {
                    throw new InvalidConfigException("Algorithm class $class doesn't exist");
                }
                $algorithms[] = new $class();
            }
            $algorithmManager = new AlgorithmManager($algorithms);
            $compactSerializer = new CompactSerializer();
            /** @psalm-var string[] $this->allowedJwsAlgorithms */
            $checker = new AlgorithmChecker($this->allowedJwsAlgorithms);
            $this->jwsLoader = new JWSLoader(
                new JWSSerializerManager([$compactSerializer]),
                new JWSVerifier($algorithmManager),
                /**
                 * @infection-ignore-all
                 * $checker enforces the same $allowedJwsAlgorithms list that $algorithmManager above
                 * is built from, so JWSVerifier already rejects any "alg" this checker would reject;
                 * dropping it from the array is behaviorally unobservable from the outside.
                 */
                new HeaderCheckerManager(
                    [$checker],
                    [new JWSTokenSupport()],
                ),
            );
        }
        return $this->jwsLoader;
    }

    protected function getJwkSet(): ?JWKSet
    {
        $jwkSet = $this->jwkSet;
        if (!($this->jwkSet instanceof JWKSet)) {
            $cacheKey = $this->configParamsCacheKeyPrefix . 'jwkSet';

            /** @var mixed $jwkSetRaw */
            $jwkSetRaw = $this->cache->get($cacheKey);

            /** @var JWKSet|null $jwkSet */
            $jwkSet = $jwkSetRaw instanceof JWKSet ? $jwkSetRaw : null;

            if ($jwkSet === null) {
                /** @var mixed $jwksUriRaw */
                $jwksUriRaw = $this->getConfigParam('jwks_uri');
                $jwksUri = is_string($jwksUriRaw) ? $jwksUriRaw : '';
                $request = $this->createRequest('GET', $jwksUri);
                $response = $this->sendRequest($request);
                /** @var mixed $jsonBody */
                $jsonBody = Json::decode($response->getBody()->getContents());
                $jsonBody = is_array($jsonBody) ? $jsonBody : [];
                $jwkSet = JWKFactory::createFromValues($jsonBody);
            }
            $this->cache->set($cacheKey, $jwkSet);
        }
        return $jwkSet instanceof JWKSet ? $jwkSet : null;
    }

    /**
     * Validates the claims data received from OpenID provider.
     *
     * @param array $claims claims data.
     *
     * @throws ClientException on invalid claims.
     */
    protected function validateClaims(array $claims): void
    {
        $iss = isset($claims['iss']) ? (string) $claims['iss'] : '';
        $issuerUrl = $this->issuerUrl;
        if (!isset($claims['iss']) || strcmp(rtrim($iss, '/'), rtrim($issuerUrl, '/')) !== 0) {
            throw new ClientException('Invalid "iss"', 400);
        }
        if (!isset($claims['aud']) || (strcmp((string) $claims['aud'], $this->clientId) !== 0)) {
            throw new ClientException('Invalid "aud"', 400);
        }
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
        if (empty($this->issuerUrl)) {
            throw new InvalidConfigException('Cannot discover config because issuer URL is not set.');
        }
        $configUrl = $this->issuerUrl . '/.well-known/openid-configuration';
        $request = $this->createRequest('GET', $configUrl);
        $response = $this->sendRequest($request);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
