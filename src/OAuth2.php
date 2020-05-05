<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\Signature\BaseMethod;

/**
 * OAuth2 serves as a client for the OAuth 2 flow.
 *
 * In oder to acquire access token perform following sequence:
 *
 * ```php
 * use Yiisoft\Yii\AuthClient\OAuth2;
 *
 * // assuming class MyAuthClient extends OAuth2
 * $oauthClient = new MyAuthClient();
 * $url = $oauthClient->buildAuthUrl(); // Build authorization URL
 * Yii::getApp()->getResponse()->redirect($url); // Redirect to authorization URL.
 * // After user returns at our site:
 * $code = Yii::getApp()->getRequest()->get('code');
 * $accessToken = $oauthClient->fetchAccessToken($code); // Get access token
 * ```
 *
 * @see http://oauth.net/2/
 * @see https://tools.ietf.org/html/rfc6749
 */
abstract class OAuth2 extends BaseOAuth
{
    /**
     * @var string OAuth client ID.
     */
    protected string $clientId;
    /**
     * @var string OAuth client secret.
     */
    protected string $clientSecret;
    /**
     * @var string token request URL endpoint.
     */
    protected string $tokenUrl;
    /**
     * @var bool whether to use and validate auth 'state' parameter in authentication flow.
     * If enabled - the opaque value will be generated and applied to auth URL to maintain
     * state between the request and callback. The authorization server includes this value,
     * when redirecting the user-agent back to the client.
     * The option is used for preventing cross-site request forgery.
     */
    protected bool $validateAuthState = true;


    /**
     * Composes user authorization URL.
     * @param array $params additional auth GET params.
     * @return string authorization URL.
     */
    public function buildAuthUrl(array $params = []): string
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getReturnUrl(),
            'xoauth_displayname' => Yii::getApp()->name,
        ];
        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            $this->setState('authState', $authState);
            $defaultParams['state'] = $authState;
        }

        return $this->composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    /**
     * Fetches access token from authorization code.
     * @param string $authCode authorization code, usually comes at GET parameter 'code'.
     * @param array $params additional request params.
     * @return OAuthToken access token.
     * @throws HttpException on invalid auth state in case [[enableStateValidation]] is enabled.
     */
    public function fetchAccessToken($authCode, array $params = []): OAuthToken
    {
        if ($this->validateAuthState) {
            $authState = $this->getState('authState');
            $incomingRequest = Yii::getApp()->getRequest();
            $incomingState = $incomingRequest->get('state', $incomingRequest->post('state'));
            if (!isset($incomingState) || empty($authState) || strcmp($incomingState, $authState) !== 0) {
                throw new HttpException(400, 'Invalid auth state parameter.');
            }
            $this->removeState('authState');
        }

        $defaultParams = [
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getReturnUrl(),
        ];

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setParams(array_merge($defaultParams, $params));

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
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
     * Applies client credentials (e.g. [[clientId]] and [[clientSecret]]) to the HTTP request instance.
     * This method should be invoked before sending any HTTP request, which requires client credentials.
     * @param RequestInterface $request HTTP request instance.
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
     * Gets new auth token to replace expired one.
     * @param OAuthToken $token expired auth token.
     * @return OAuthToken new auth token.
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $params = [
            'grant_type' => 'refresh_token'
        ];
        $params = array_merge($token->getParams(), $params);

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setParams($params);

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Composes default [[returnUrl]] value.
     * @return string return URL.
     */
    protected function defaultReturnUrl():string
    {
        $params = Yii::getApp()->getRequest()->getQueryParams();
        unset($params['code']);
        unset($params['state']);
        $params[0] = Yii::getApp()->controller->getRoute();

        return Yii::getApp()->getUrlManager()->createAbsoluteUrl($params);
    }

    /**
     * Generates the auth state value.
     * @return string auth state value.
     */
    protected function generateAuthState()
    {
        $baseString = get_class($this) . '-' . time();
        if (Yii::getApp()->has('session')) {
            $baseString .= '-' . Yii::getApp()->session->getId();
        }
        return hash('sha256', uniqid($baseString, true));
    }

    /**
     * Creates token from its configuration.
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = []):OAuthToken
    {
        $tokenConfig['tokenParamKey'] = 'access_token';

        return parent::createToken($tokenConfig);
    }

    /**
     * Authenticate OAuth client directly at the provider without third party (user) involved,
     * using 'client_credentials' grant type.
     * @see http://tools.ietf.org/html/rfc6749#section-4.4
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateClient($params = [])
    {
        $defaultParams = [
            'grant_type' => 'client_credentials',
        ];

        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setParams(array_merge($defaultParams, $params));

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticates user directly by 'username/password' pair, using 'password' grant type.
     * @see https://tools.ietf.org/html/rfc6749#section-4.3
     * @param string $username user name.
     * @param string $password user password.
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateUser($username, $password, $params = [])
    {
        $defaultParams = [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];

        if (!empty($this->getScope())) {
            $defaultParams['scope'] = $this->getScope();
        }

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setParams(array_merge($defaultParams, $params));

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticates user directly using JSON Web Token (JWT).
     * @see https://tools.ietf.org/html/rfc7515
     * @param string $username
     * @param BaseMethod|array $signature signature method or its array configuration.
     * If empty - [[signatureMethod]] will be used.
     * @param array $options additional options. Valid options are:
     *
     * - header: array, additional JWS header parameters.
     * - payload: array, additional JWS payload (message or claim-set) parameters.
     * - signatureKey: string, signature key to be used, if not set - [[clientSecret]] will be used.
     *
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateUserJwt($username, $signature = null, $options = [], $params = [])
    {
        if (empty($signature)) {
            $signatureMethod = $this->getSignatureMethod();
        } elseif (is_object($signature)) {
            $signatureMethod = $signature;
        } else {
            $signatureMethod = $this->createSignatureMethod($signature);
        }

        $header = isset($options['header']) ? $options['header'] : [];
        $payload = isset($options['payload']) ? $options['payload'] : [];

        $header = array_merge(
            [
                'typ' => 'JWT'
            ],
            $header
        );
        if (!isset($header['alg'])) {
            $signatureName = $signatureMethod->getName();
            if (preg_match('/^([a-z])[a-z]*\-([a-z])[a-z]*([0-9]+)$/is', $signatureName, $matches)) {
                // convert 'RSA-SHA256' to 'RS256' :
                $signatureName = $matches[1] . $matches[2] . $matches[3];
            }
            $header['alg'] = $signatureName;
        }

        $payload = array_merge(
            [
                'iss' => $username,
                'scope' => $this->getScope(),
                'aud' => $this->tokenUrl,
                'iat' => time(),
            ],
            $payload
        );
        if (!isset($payload['exp'])) {
            $payload['exp'] = $payload['iat'] + 3600;
        }

        $signatureBaseString = base64_encode(Json::encode($header)) . '.' . base64_encode(Json::encode($payload));
        $signatureKey = isset($options['signatureKey']) ? $options['signatureKey'] : $this->clientSecret;
        $signature = $signatureMethod->generateSignature($signatureBaseString, $signatureKey);

        $assertion = $signatureBaseString . '.' . $signature;

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setParams(
                array_merge(
                    [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $assertion,
                    ],
                    $params
                )
            );

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function setTokenUrl(string $tokenUrl): void
    {
        $this->tokenUrl = $tokenUrl;
    }

    public function withValidateAuthState(): self
    {
        $new = clone $this;
        $new->validateAuthState = true;
        return $new;
    }

    public function withoutValidateAuthState(): self
    {
        $new = clone $this;
        $new->validateAuthState = false;
        return $new;
    }
}
