<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Exception;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
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
use Yiisoft\Http\Header;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Exception\ClientException;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

use function in_array;
use function is_array;
use function is_string;

use const PHP_QUERY_RFC3986;

/**
 * OpenIdConnect serves as a client for the OpenIdConnect flow.
 *
 * @link https://github.com/web-token/jwt-library
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
    protected ?string $scope = 'openid';
    /**
     * @var string OpenID Issuer
     */
    private string $issuerUrl = 'https://{IdentityProviderDomain}';
    /**
     * @var bool whether to validate/decrypt JWS received with Auth token.
     * You can disable this option in case of usage of a trusted OpenID Connect provider, however this violates
     * the protocol rules, so you are doing it at your own risk.
     */
    private bool $validateJws = true;
    /**
     * @var array JWS algorithms, which are allowed to be used.
     * These are used by the `web-token/jwt-library` package for JWS validation/decryption; all algorithms listed
     * below are included in that package.
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
     * @var string the prefix for the discovery-related cache keys: {@see configParams} (suffixed with
     * {@see getName()}) and the resolved JWK set (suffixed with `'jwkSet'`).
     *
     * @see cache
     */
    private string $cacheKeyPrefix = 'config-params-';

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

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        if (empty($this->authUrl)) {
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
            $cacheKey = $this->cacheKeyPrefix . $this->getName();
            $configParams = (array) $this->cache->get($cacheKey);
            if (empty($configParams)) {
                $configParams = $this->discoverConfig();
                $this->cache->set($cacheKey, $configParams);
            }

            $this->configParams = $configParams;
        }
        return $this->configParams;
    }

    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        $this->resolveTokenUrl();
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

    public function setValidateAuthNonce(bool $validateAuthNonce): void
    {
        $this->validateAuthNonce = $validateAuthNonce;
    }

    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $this->resolveTokenUrl();
        return parent::refreshAccessToken($token);
    }

    public function getName(): string
    {
        return $this->name ?: 'openid-connect';
    }

    public function getTitle(): string
    {
        if ($this->title !== '') {
            return $this->title;
        }

        return $this->name !== '' ? ucfirst($this->name) : 'OpenID Connect';
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

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        return $this->fetchCurrentUserJsonArray($oauthToken, (string) $this->getConfigParam('userinfo_endpoint'));
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
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
    }

    protected function applyClientCredentialsToRequest(RequestInterface $request): RequestInterface
    {
        $supportedAuthMethods = (array) $this->getConfigParam('token_endpoint_auth_methods_supported');
        if (in_array('client_secret_basic', $supportedAuthMethods, true)) {
            return $this->applyClientSecretBasic($request);
        }
        if (in_array('client_secret_post', $supportedAuthMethods, true)) {
            return $this->applyClientSecretPost($request);
        }
        if (in_array('client_secret_jwt', $supportedAuthMethods, true)) {
            return $this->applyClientSecretJwt($request);
        }
        throw new InvalidConfigException('Unable to authenticate request: No auth method supported');
    }

    protected function defaultReturnUrl(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        unset($params['code'], $params['state'], $params['nonce'], $params['authuser'], $params['session_state'], $params['prompt']);
        return (string) $request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
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
            return (array) Json::decode((string) $jwsVerified->getPayload());
        } catch (Exception $e) {
            throw new ClientException('Loading JWS: Exception: ' . $e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Returns the JWSLoader that validates the JWS token.
     *
     * @throws InvalidConfigException on an invalid algorithm provided in the configuration.
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
        if ($this->jwkSet instanceof JWKSet) {
            return $this->jwkSet;
        }

        $cacheKey = $this->cacheKeyPrefix . 'jwkSet';

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
            /** @var mixed $fetched */
            $fetched = JWKFactory::createFromValues($jsonBody);
            $this->cache->set($cacheKey, $fetched);
            // JWKFactory::createFromValues() may return a plain JWK (single-key response)
            // instead of a JWKSet; $this->jwkSet is strictly typed, so only a real JWKSet is kept.
            $jwkSet = $fetched instanceof JWKSet ? $fetched : null;
        }

        if ($jwkSet instanceof JWKSet) {
            $this->jwkSet = $jwkSet;
        }

        return $jwkSet;
    }

    /**
     * Validates the claims data received from the OpenID provider.
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

        try {
            // "aud" may legally be a string or an array of strings (RFC 7519 §4.1.3); AudienceChecker
            // handles both, unlike a plain string comparison which would reject a valid array audience.
            (new AudienceChecker($this->clientId))->checkClaim($claims['aud'] ?? null);
        } catch (InvalidClaimException) {
            throw new ClientException('Invalid "aud"', 400);
        }
    }

    /**
     * Authenticates via the `Authorization: Basic` header (RFC 6749 §2.3.1).
     */
    private function applyClientSecretBasic(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(
            Header::AUTHORIZATION,
            'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        );
    }

    /**
     * Authenticates by appending client credentials to the request body. The request already carries
     * an application/x-www-form-urlencoded body written by fetchAccessToken()/refreshAccessToken()
     * (RFC 6749 §4.1.3), so the params are appended to it rather than a query string.
     */
    private function applyClientSecretPost(RequestInterface $request): RequestInterface
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
     * Authenticates via a client-secret-signed JWT assertion (RFC 7523 / OpenID Connect Core §9).
     */
    private function applyClientSecretJwt(RequestInterface $request): RequestInterface
    {
        $payload = [
            'iss' => $this->clientId,
            'sub' => $this->clientId,
            'aud' => $this->tokenUrl,
            'jti' => $this->generateAuthNonce(),
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $jwk = JWKFactory::createFromSecret($this->clientSecret);
        $jwsBuilder = new JWSBuilder(new AlgorithmManager([new HS256()]));
        $jws = $jwsBuilder
            ->create()
            ->withPayload(Json::encode($payload))
            ->addSignature($jwk, ['typ' => 'JWT', 'alg' => 'HS256'])
            ->build();
        $assertion = (new CompactSerializer())->serialize($jws, 0);

        $request->getBody()->write('&' . http_build_query(['assertion' => $assertion], '', '&', PHP_QUERY_RFC3986));
        return $request;
    }

    /**
     * Resolves {@see tokenUrl} from the discovery document if it hasn't been set explicitly.
     */
    private function resolveTokenUrl(): void
    {
        if (empty($this->tokenUrl)) {
            $this->tokenUrl = (string) $this->getConfigParam('token_endpoint');
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

        return (array) Json::decode($response->getBody()->getContents());
    }
}
