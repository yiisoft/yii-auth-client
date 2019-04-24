<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Clients;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * Facebook allows authentication via Facebook OAuth.
 *
 * In order to use Facebook OAuth you must register your application at <https://developers.facebook.com/apps>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'facebook' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
 *                 'clientId' => 'facebook_client_id',
 *                 'clientSecret' => 'facebook_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://developers.facebook.com/apps
 * @see http://developers.facebook.com/docs/reference/api
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Facebook extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://www.facebook.com/dialog/oauth';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://graph.facebook.com/oauth/access_token';
    /**
     * {@inheritdoc}
     */
    public $endpoint = 'https://graph.facebook.com';

    /**
     * @var array list of attribute names, which should be requested from API to initialize user attributes.
     * @since 2.0.5
     */
    public $attributeNames = [
        'name',
        'email',
    ];
    /**
     * {@inheritdoc}
     */
    public $autoRefreshAccessToken = false; // Facebook does not provide access token refreshment
    /**
     * @var bool whether to automatically upgrade short-live (2 hours) access token to long-live (60 days) one, after fetching it.
     * @see exchangeToken()
     * @since 2.1.3
     */
    public $autoExchangeAccessToken = false;
    /**
     * @var string URL endpoint for the client auth code generation.
     * @see https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @see fetchClientAuthCode()
     * @see fetchClientAccessToken()
     * @since 2.1.3
     */
    public $clientAuthCodeUrl = 'https://graph.facebook.com/oauth/client_code';


    /**
     * {@inheritdoc}
     */
    protected function initUserAttributes()
    {
        return $this->api('me', 'GET', [
            'fields' => implode(',', $this->attributeNames),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $request = parent::applyAccessTokenToRequest($request, $accessToken);

        $params = [];
        if (($machineId = $accessToken->getParam('machine_id')) !== null) {
            $params['machine_id'] = $machineId;
        }
        $params['appsecret_proof'] = hash_hmac('sha256', $accessToken->getToken(), $this->clientSecret);
        return RequestUtil::addParams($request, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultViewOptions()
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAccessToken($authCode, array $params = [])
    {
        $token = parent::fetchAccessToken($authCode, $params);
        if ($this->autoExchangeAccessToken) {
            $token = $this->exchangeAccessToken($token);
        }
        return $token;
    }

    /**
     * Exchanges short-live (2 hours) access token to long-live (60 days) one.
     * Note that this method will success for already long-live token, but will not actually prolong it any further.
     * Pay attention, that this method will fail on already expired access token.
     * @see https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @param OAuthToken $token short-live access token.
     * @return OAuthToken long-live access token.
     * @since 2.1.3
     */
    public function exchangeAccessToken(OAuthToken $token)
    {
        $params = [
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => $token->getToken(),
        ];

        $request = $this->createRequest('POST', $this->tokenUrl);
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
     * @see https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @see fetchClientAccessToken()
     * @param OAuthToken|null $token access token, if not set [[accessToken]] will be used.
     * @param array $params additional request params.
     * @return string client auth code.
     * @since 2.1.3
     */
    public function fetchClientAuthCode(OAuthToken $token = null, $params = [])
    {
        if ($token === null) {
            $token = $this->getAccessToken();
        }

        $params = array_merge([
            'access_token' => $token->getToken(),
            'redirect_uri' => $this->getReturnUrl(),
        ], $params);

        $request = $this->createRequest('POST', $this->clientAuthCodeUrl);
        $request = RequestUtil::addParams($request, $params);

        $request = $this->applyClientCredentialsToRequest($request);

        $response = $this->sendRequest($request);

        // TODO: parse response!

        return $response['code'];
    }

    /**
     * Fetches access token from client-specific authorization code.
     * This make sense for the distributed applications, which provides several Auth clients (web and mobile)
     * to avoid triggering Facebook's automated spam systems.
     * @see https://developers.facebook.com/docs/facebook-login/access-tokens/expiration-and-extension
     * @see fetchClientAuthCode()
     * @param string $authCode client auth code.
     * @param array $params
     * @return OAuthToken long-live client-specific access token.
     * @since 2.1.3
     */
    public function fetchClientAccessToken($authCode, array $params = [])
    {
        $params = array_merge([
            'code' => $authCode,
            'redirect_uri' => $this->getReturnUrl(),
            'client_id' => $this->clientId,
        ], $params);

        $request = $this->createRequest('POST', $this->tokenUrl);
        $request = RequestUtil::addParams($request, $params);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'facebook';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'Facebook';
    }

    protected function getDefaultScope(): string
    {
        return 'email';
    }
}
