<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Json\Json;

/**
 * OAuth1 serves as a client for the OAuth 1/1.0a flow.
 *
 * In order to acquire access token perform following sequence:
 *
 * ```php
 * use Yiisoft\Yii\AuthClient\OAuth1;
 *
 * // assuming class MyAuthClient extends OAuth1
 * $oauthClient = new MyAuthClient();
 * $requestToken = $oauthClient->fetchRequestToken(); // Get request token
 * $url = $oauthClient->buildAuthUrl($requestToken); // Get authorization URL
 * return Yii::getApp()->getResponse()->redirect($url); // Redirect to authorization URL
 *
 * // After user returns at our site:
 * $accessToken = $oauthClient->fetchAccessToken(Yii::getApp()->request->get('oauth_token'), $requestToken); // Upgrade to access token
 * ```
 *
 * @see https://oauth.net/1/
 * @see https://tools.ietf.org/html/rfc5849
 */
abstract class OAuth1 extends OAuth
{
    private const string PROTOCOL_VERSION = '1.0';

    /**
     * @var string OAuth consumer key.
     */
    protected string $consumerKey = '';
    /**
     * @var string OAuth consumer secret.
     */
    protected string $consumerSecret = '';
    /**
     * @var string OAuth request token URL.
     */
    protected string $requestTokenUrl;
    /**
     * @var string request token HTTP method.
     */
    protected string $requestTokenMethod = 'GET';
    /**
     * @var string OAuth access token URL.
     */
    protected string $accessTokenUrl;
    /**
     * @var string access token HTTP method.
     */
    protected string $accessTokenMethod = 'GET';
    /**
     * @var array|null list of the request methods, which require adding 'Authorization' header.
     * By default only POST requests will have 'Authorization' header.
     * You may set this option to `null` in order to make all requests to use 'Authorization' header.
     */
    protected ?array $authorizationHeaderMethods = ['POST'];

    /**
     * Composes user authorization URL.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param array $params additional request params.
     * @psalm-suppress MixedAssignment
     * @return string authorize URL
     */
    public function buildAuthUrl(
        ServerRequestInterface $incomingRequest,
        array $params = []
    ): string {
        $requestToken = $this->fetchRequestToken($incomingRequest);
        if (!($requestToken) instanceof OAuthToken) {
            $requestToken = $this->getState('requestToken');
            if (!($requestToken) instanceof OAuthToken) {
                throw new InvalidArgumentException('Request token is required to build authorize URL!');
            }
        }
        $params['oauth_token'] = $requestToken->getToken();

        return RequestUtil::composeUrl($this->authUrl, $params);
    }

    /**
     * Fetches the OAuth request token.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param array $params additional request params.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     * @return OAuthToken request token.
     */
    public function fetchRequestToken(ServerRequestInterface $incomingRequest, array $params = []): OAuthToken
    {
        $this->setAccessToken([]);
        $defaultParams = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_callback' => $this->getReturnUrl($incomingRequest),
            //'xoauth_displayname' => Yii::getApp()->name,
        ];
        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        $request = $this->createRequest(
            $this->requestTokenMethod,
            $this->requestTokenUrl . '?' . http_build_query(
                array_merge($defaultParams, $params)
            )
        );

        $request = $this->signRequest($request);
        $response = $this->sendRequest($request);        
        
        /** @psalm-suppress MixedAssignment **/
        $content = Json::decode((string) $response->getBody());
        /** @psalm-suppress MixedAssignment **/
        $tokenConfig = $content ?: [];
        /**
         * @psalm-suppress MixedArgument $tokenConfig
         */
        $token = $this->createToken($tokenConfig);
        $this->setState('requestToken', $token);

        return $token;
    }

    /**
     * Sign given request with {@see signatureMethod}.
     *
     * @param RequestInterface $request request instance.
     * @param OAuthToken|null $token OAuth token to be used for signature, if not set {@see accessToken} will be used.
     * @psalm-suppress MixedArgument
     * @return RequestInterface
     */
    public function signRequest(RequestInterface $request, ?OAuthToken $token = null): RequestInterface
    {
        $params = RequestUtil::getParams($request);

        if (isset($params['oauth_signature_method']) || $request->hasHeader('authorization')) {
            // avoid double sign of request
            return $request;
        }

        if (empty($request->getUri()->getQuery())) {
            $params = $this->generateCommonRequestParams();
        } else {
            $params = array_merge($this->generateCommonRequestParams(), $params);
        }

        $url = (string)$request->getUri();

        $signatureMethod = $this->getSignatureMethod();
        if (null!==$signatureMethod) {
            /**
             * @psalm-suppress PossiblyInvalidMethodCall $signatureMethod->getName()
             */
            $params['oauth_signature_method'] = $signatureMethod->getName();
            $signatureBaseString = $this->composeSignatureBaseString($request->getMethod(), $url, $params);
            $signatureKey = $this->composeSignatureKey($token);
            /**
             * @psalm-suppress PossiblyInvalidMethodCall generateSignature
             */
            $params['oauth_signature'] = $signatureMethod->generateSignature($signatureBaseString, $signatureKey);
        }    
        if (
            $this->authorizationHeaderMethods === null || in_array(
                strtoupper($request->getMethod()),
                array_map(
                    'strtoupper',
                    $this->authorizationHeaderMethods
                ),
                true
            )
        ) {
            $authorizationHeader = $this->composeAuthorizationHeader($params);
            foreach ($authorizationHeader as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            // removing authorization header params, avoiding duplicate param server error :
            foreach ($params as $key => $value) {
                if (substr_compare($key, 'oauth', 0, 5) === 0) {
                    unset($params[$key]);
                }
            }           
        }

        $uri = $request->getUri()->withQuery(http_build_query($params));
        return $request->withUri($uri);
    }

    /**
     * Generate common request params like version, timestamp etc.
     *
     * @return (int|string)[]
     *
     * @psalm-return array{oauth_version: '1.0', oauth_nonce: string, oauth_timestamp: int<1, max>}
     */
    protected function generateCommonRequestParams(): array
    {
        return [
            'oauth_version' => self::PROTOCOL_VERSION,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_timestamp' => $this->generateTimestamp(),
        ];
    }

    /**
     * Generates nonce value.
     *
     * @return string nonce value.
     */
    protected function generateNonce(): string
    {
        return md5(microtime() . mt_rand());
    }

    /**
     * Generates timestamp.
     *
     * @return int timestamp.
     *
     * @psalm-return int<1, max>
     */
    protected function generateTimestamp(): int
    {
        return time();
    }

    /**
     * Creates signature base string, which will be signed by {@see signatureMethod}.
     *
     * @param string $method request method.
     * @param string $url request URL.
     * @param array $params request params.
     *
     * @return string base signature string.
     */
    protected function composeSignatureBaseString($method, string $url, array $params): string
    {
        
        if (strpos($url, '?') !== false) {
            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset $queryString
             */
            [$url, $queryString] = explode('?', $url, 2);
            parse_str($queryString, $urlParams);
            $params = array_merge($urlParams, $params);
        }
        unset($params['oauth_signature']);
        /**
         * @psalm-suppress MixedArgumentTypeCoercion 
         */
        uksort(
            $params,
            'strcmp'
        ); // Parameters are sorted by name, using lexicographical byte value ordering. Ref: Spec: 9.1.1
        $parts = [
            strtoupper($method),
            $url,
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        ];
        $parts = array_map('rawurlencode', $parts);

        return implode('&', $parts);
    }

    /**
     * Composes request signature key.
     *
     * @param OAuthToken|null $token OAuth token to be used for signature key.
     *
     * @return string signature key.
     */
    protected function composeSignatureKey($token = null): string
    {
        $signatureKeyParts = [
            $this->consumerSecret,
        ];

        if ($token === null) {
            $token = $this->getAccessToken();
        }
        if (is_object($token)) {
            $signatureKeyParts[] = $token->getTokenSecret();
        } else {
            $signatureKeyParts[] = '';
        }

        $signatureKeyParts = array_map('rawurlencode', $signatureKeyParts);

        return implode('&', $signatureKeyParts);
    }

    /**
     * Composes authorization header.
     *
     * @param array $params request params.
     * @param string $realm authorization realm.
     *
     * @return string[]
     *
     * @psalm-return array{Authorization: string}
     */
    public function composeAuthorizationHeader(array $params, $realm = ''): array
    {
        $header = 'OAuth';
        $headerParams = [];
        if (!empty($realm)) {
            $headerParams[] = 'realm="' . rawurlencode($realm) . '"';
        }
        /**
         * @var string $key
         * @var string $value
         */
        foreach ($params as $key => $value) {
            if (substr_compare($key, 'oauth', 0, 5)) {
                continue;
            }
            $headerParams[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        if (!empty($headerParams)) {
            $header .= ' ' . implode(', ', $headerParams);
        }

        return ['Authorization' => $header];
    }

    /**
     * Fetches OAuth access token.
     *
     * @param ServerRequestInterface $incomingRequest
     * @param string $oauthToken OAuth token returned with redirection back to client.
     * @param OAuthToken $requestToken OAuth request token.
     * @param string $oauthVerifier OAuth verifier.
     * @param array $params additional request params.
     * @psalm-suppress MixedReturnStatement 
     * @return OAuthToken|array OAuth access token.
     */
    public function fetchAccessToken(
        ServerRequestInterface $incomingRequest,
        string $oauthToken = null,
        OAuthToken $requestToken = null,
        string $oauthVerifier = null,
        array $params = []
    ): array|OAuthToken {
        $queryParams = $incomingRequest->getQueryParams();
        $bodyParams = $incomingRequest->getParsedBody();
        if ($oauthToken === null) {
            /**
             * @psalm-suppress MixedAssignment
             */
            $oauthToken = $queryParams['oauth_token'] ?? $bodyParams['oauth_token'] ?? null;
        }

        if (!is_object($requestToken)) {
            /**
             * @psalm-suppress MixedAssignment
             */
            $requestToken = $this->getState('requestToken');
            if (!is_object($requestToken)) {
                throw new InvalidArgumentException('Request token is required to fetch access token!');
            }
        }
        
        /**
         * @psalm-suppress MixedAssignment
         * @psalm-suppress MixedArgument
         */
        if (null!==$oauthToken) {
            /**
             * @psalm-suppress MixedMethodCall
             */
            $getRequestToken = $requestToken->getToken();
            if (null!==$getRequestToken) {
                if (strcmp($getRequestToken, $oauthToken) !== 0) {
                    throw new InvalidArgumentException('Invalid auth state parameter.');
                }
            }    
        }    

        $this->removeState('requestToken');
        
        /**
         * @psalm-suppress MixedMethodCall
         */
        $defaultParams = [
            'oauth_consumer_key' => $this->consumerKey,            
            'oauth_token' => $requestToken->getToken(),
        ];
        if ($oauthVerifier === null) {
            /**
             * @var string|null $queryParams['oauth_verifier']
             * @var string $bodyParams['oauth_verifier']
             */
            $oauthVerifier = $queryParams['oauth_verifier'] ?? $bodyParams['oauth_verifier'];
        }
        
        if (strlen($oauthVerifier) > 0) {
            $defaultParams['oauth_verifier'] = $oauthVerifier;
        }

        $request = $this->createRequest(
            $this->accessTokenMethod,
            RequestUtil::composeUrl($this->accessTokenUrl, array_merge($defaultParams, $params))
        );

        /**
         * @psalm-suppress ArgumentTypeCoercion $requestToken
         */
        $request = $this->signRequest($request, $requestToken);

        $request = $this->signRequest($request);
        $response = $this->sendRequest($request);

        /**
         * @psalm-suppress MixedAssignment
         */
        $token = $this->createToken(
            [
                'setParams()' => [Json::decode($response->getBody()->getContents())],
            ]
        );
        $this->setAccessToken($token);    

        return $token;
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $data = RequestUtil::getParams($request);
        $data['oauth_consumer_key'] = $this->consumerKey;
        $data['oauth_token'] = $accessToken->getToken();
        return RequestUtil::addParams($request, $data);
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
        // @todo
        return $token;
    }

    /**
     * Composes default {@see returnUrl} value.
     *
     * @return string return URL.
     */
    protected function defaultReturnUrl(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        unset($params['oauth_token']);

        return (string)$request->getUri()->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }
}
