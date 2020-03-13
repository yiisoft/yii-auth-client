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
use yii\helpers\Json;

/**
 * VKontakte allows authentication via VKontakte OAuth.
 *
 * In order to use VKontakte OAuth you must register your application at <http://vk.com/editapp?act=create>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'vkontakte' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\VKontakte::class,
 *                 'clientId' => 'vkontakte_client_id',
 *                 'clientSecret' => 'vkontakte_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see http://vk.com/editapp?act=create
 * @see http://vk.com/developers.php?oid=-1&p=users.get
 */
class VKontakte extends OAuth2
{
    public $authUrl = 'https://oauth.vk.com/authorize';
    public $tokenUrl = 'https://oauth.vk.com/access_token';
    public $endpoint = 'https://api.vk.com/method';
    /**
     * @var array list of attribute names, which should be requested from API to initialize user attributes.
     */
    public $attributeNames = [
        'uid',
        'first_name',
        'last_name',
        'nickname',
        'screen_name',
        'sex',
        'bdate',
        'city',
        'country',
        'timezone',
        'photo'
    ];
    /**
     * @var string the API version to send in the API request.
     * @see https://vk.com/dev/versions
     */
    public $apiVersion = '3.0';


    protected function initUserAttributes()
    {
        $response = $this->api('users.get.json', 'GET', [
            'fields' => implode(',', $this->attributeNames),
        ]);

        if (empty($response['response'])) {
            throw new \RuntimeException('Unable to init user attributes due to unexpected response: ' . Json::encode($response));
        }

        $attributes = array_shift($response['response']);

        $accessToken = $this->getAccessToken();
        if (is_object($accessToken)) {
            $accessTokenParams = $accessToken->getParams();
            unset($accessTokenParams['access_token']);
            unset($accessTokenParams['expires_in']);
            $attributes = array_merge($accessTokenParams, $attributes);
        }

        return $attributes;
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams($request, [
            'v' => $this->apiVersion,
            'uids' => $accessToken->getParam('user_id'),
            'access_token' => $accessToken->getToken()
        ]);
    }

    protected function defaultNormalizeUserAttributeMap()
    {
        return [
            'id' => 'uid'
        ];
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'vkontakte';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'VKontakte';
    }
}
