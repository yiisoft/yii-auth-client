<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * Facebook allows authentication via Facebook OAuth.
 *
 * In order to use Facebook OAuth you must register your application at <https://developers.facebook.com/apps>
 *
 * Example application configuration:
 *
 * config/common/params.php
 *
 * 'yiisoft/yii-auth-client' => [
 *       'enabled' => true,
 *       'clients' => [
 *           'facebook' => [
 *               'class' => 'Yiisoft\Yii\AuthClient\Client\Facebook::class',
 *               'clientId' => $_ENV['FACEBOOK_API_CLIENT_ID'] ?? '',
 *               'clientSecret' => $_ENV['FACEBOOK_API_CLIENT_SECRET'] ?? '',
 *               'returnUrl' => $_ENV['FACEBOOK_API_CLIENT_RETURN_URL'] ?? '',
 *           ],
 *       ],
 *   ],
 *
 * @link https://developers.facebook.com/apps
 * @link https://developers.facebook.com/docs/graph-api
 */
final class Facebook extends OAuth2
{
    protected string $graphApiVersion = 'v23.0';
    protected string $authUrl = 'https://www.facebook.com/dialog/oauth';
    protected string $tokenUrl = 'https://graph.facebook.com/oauth/access_token';
    protected string $endpoint = 'https://graph.facebook.com';
    /** @var string[] */
    protected array $endpointFields = ['id', 'name', 'first_name', 'last_name'];
    protected bool $autoRefreshAccessToken = false; // Facebook does not provide access token refreshment

    /**
     * @var bool whether to automatically upgrade short-live (2 hours) access token to long-live (60 days) one, after fetching it.
     *
     * @see exchangeToken()
     */
    private bool $autoExchangeAccessToken = false;

    /**
     * @var string URL endpoint for the client auth code generation.
     *
     * @link https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @see fetchClientAuthCode()
     * @see fetchClientAccessToken()
     */
    private string $clientAuthCodeUrl = 'https://graph.facebook.com/oauth/client_code';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        $params = $token->getParams();
        $finalValue = '';
        $finalValue = array_key_last($params);

        /**
         * @var string $finalValue
         * @var array $array
         */
        $array = json_decode($finalValue, true);
        $tokenString = (string)($array['access_token'] ?? '');

        if ($tokenString !== '') {
            $queryParams = [
                'fields' => implode(',', $this->endpointFields),
            ];
            $url = sprintf(
                $this->endpoint . '/%s/me?%s',
                urlencode($this->graphApiVersion),
                http_build_query($queryParams)
            );
            $request = $this->createRequest('GET', $url);
            $request = RequestUtil::addHeaders(
                $request,
                [
                    'Authorization' => 'Bearer ' . $tokenString,
                ]
            );
            $response = $this->sendRequest($request);
            return (array) json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
    }

    #[\Override]
    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $request = parent::applyAccessTokenToRequest($request, $accessToken);
        $params = [];
        if (!empty($machineId = (string)$accessToken->getParam('machine_id'))) {
            $params['machine_id'] = $machineId;
        }
        $token = $accessToken->getToken();
        if (null !== $token) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, $this->clientSecret);
        }
        return RequestUtil::addParams($request, $params);
    }

    #[\Override]
    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        $token = parent::fetchAccessToken($incomingRequest, $authCode, $params);
        if ($this->autoExchangeAccessToken) {
            $token = $this->exchangeAccessToken($token);
        }
        return $token;
    }

    /**
     * Exchanges short-live (2 hours) access token to long-live (60 days) one.
     * Note that this method will success for already long-live token, but will not actually prolong it any further.
     * Pay attention, that this method will fail on already expired access token.
     *
     * @link https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     *
     * @param OAuthToken $token short-live access token.
     *
     * @return OAuthToken long-live access token.
     */
    public function exchangeAccessToken(OAuthToken $token): OAuthToken
    {
        [
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => $token->getToken(),
        ];

        $request = $this->createRequest('POST', $this->getTokenUrl());
        //->setParams($params);
        $this->applyClientCredentialsToRequest($request);
        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Requests the authorization code for the client-specific access token.
     * This make sense for the distributed applications, which provides several Auth clients (web and mobile)
     * to avoid triggering Facebook's automated spam systems.
     *
     * @link https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     *
     * @see fetchClientAccessToken()
     *
     * @param ServerRequestInterface $incomingRequest
     * @param OAuthToken|null $token access token, if not set {@see accessToken} will be used.
     * @param array $params additional request params.
     *
     * @return numeric-string client auth code.
     */
    public function fetchClientAuthCode(
        ServerRequestInterface $incomingRequest,
        ?OAuthToken $token = null,
        array $params = []
    ): string {
        if ($token === null) {
            $token = $this->getAccessToken();
        }
        if (null !== $token) {
            $params = array_merge(
                [
                    'access_token' => $token->getToken(),
                    'redirect_uri' => $this->getReturnUrl($incomingRequest),
                ],
                $params
            );
        }
        $request = $this->createRequest('POST', $this->clientAuthCodeUrl);
        $request = RequestUtil::addParams($request, $params);

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        return (string)$response->getStatusCode();
    }

    /**
     * Fetches access token from client-specific authorization code.
     * This make sense for the distributed applications, which provides several Auth clients (web and mobile)
     * to avoid triggering Facebook's automated spam systems.
     *
     * @link https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @see fetchClientAuthCode()
     *
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode client auth code.
     * @param array $params
     *
     * @return OAuthToken long-live client-specific access token.
     */
    public function fetchClientAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken {
        $params = array_merge(
            [
                'code' => $authCode,
                'redirect_uri' => $this->getReturnUrl($incomingRequest),
                'client_id' => $this->clientId,
            ],
            $params
        );

        $request = $this->createRequest('POST', $this->getTokenUrl());
        $request = RequestUtil::addParams($request, $params);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    #[\Override]
    public function getName(): string
    {
        return 'facebook';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Facebook';
    }

    #[\Override]
    public function getButtonClass(): string
    {
        return 'btn btn-primary bi bi-facebook';
    }

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    #[\Override]
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'public_profile'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'public_profile';
    }
}
