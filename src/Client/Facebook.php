<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

use function sprintf;

use const PHP_QUERY_RFC3986;

/**
 * Facebook allows authentication via Facebook OAuth.
 *
 * In order to use Facebook OAuth you must register your application at <https://developers.facebook.com/apps>
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'facebook' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Facebook::class,
 *             'clientId' => $_ENV['FACEBOOK_CLIENT_ID'],
 *             'clientSecret' => $_ENV['FACEBOOK_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @link https://developers.facebook.com/apps
 * @link https://developers.facebook.com/docs/graph-api
 */
final class Facebook extends OAuth2
{
    protected string $graphApiVersion = 'v25.0';
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

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        $queryParams = [
            'fields' => implode(',', $this->endpointFields),
        ];
        $url = sprintf(
            $this->endpoint . '/%s/me?%s',
            urlencode($this->graphApiVersion),
            http_build_query($queryParams),
        );

        return $this->fetchCurrentUserJsonArray($oauthToken, $url);
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $request = parent::applyAccessTokenToRequest($request, $accessToken);
        $params = [];
        if (!empty($machineId = (string) $accessToken->getParam('machine_id'))) {
            $params['machine_id'] = $machineId;
        }
        $token = $accessToken->getToken();
        if (null !== $token) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, $this->clientSecret);
        }
        return RequestUtil::addParams($request, $params);
    }

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
        $params = [
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => $token->getToken(),
        ];

        $request = $this->createTokenRequest($params);
        $request = $this->applyClientCredentialsToRequest($request);
        $response = $this->sendRequest($request);

        $responseParams = (array) Json::decode($response->getBody()->getContents());
        $token = $this->createToken(['params' => $responseParams]);
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
     * @return string client auth code.
     */
    public function fetchClientAuthCode(
        ServerRequestInterface $incomingRequest,
        ?OAuthToken $token = null,
        array $params = [],
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
                $params,
            );
        }
        $request = $this->createRequest('POST', $this->clientAuthCodeUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        /**
         * @infection-ignore-all
         * The `(array)` cast only changes behavior when the body decodes to a non-array scalar (e.g. a bare
         * number). In that case `$responseParams['code']` still safely evaluates to `??`'s fallback with or
         * without the cast (PHP does not warn/error on `??`-guarded offset access into a scalar), so removing
         * the cast is behaviorally unobservable through this method's return value.
         */
        $responseParams = (array) Json::decode($response->getBody()->getContents());

        return (string) ($responseParams['code'] ?? '');
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
        array $params = [],
    ): OAuthToken {
        $params = array_merge(
            [
                'code' => $authCode,
                'redirect_uri' => $this->getReturnUrl($incomingRequest),
                'client_id' => $this->clientId,
            ],
            $params,
        );

        $request = $this->createTokenRequest($params);

        $response = $this->sendRequest($request);

        $responseParams = (array) Json::decode($response->getBody()->getContents());
        $token = $this->createToken(['params' => $responseParams]);
        $this->setAccessToken($token);

        return $token;
    }

    public function getName(): string
    {
        return $this->name ?: 'facebook';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Facebook';
    }

    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
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

    /**
     * @return string
     *
     * @psalm-return 'public_profile'
     */
    protected function getDefaultScope(): string
    {
        return 'public_profile';
    }
}
