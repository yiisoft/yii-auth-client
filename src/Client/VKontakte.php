<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * VKontakte allows authentication via VKontakte OAuth.
 *
 * In order to use VKontakte OAuth you must register your application at <https://vk.com/editapp?act=create>.
 *
 * @link https://vk.com/editapp?act=create
 * @link https://vk.com/developers.php?oid=-1&p=users.get
 */
final class VKontakte extends OAuth2
{
    protected string $authUrl = 'https://oauth.vk.com/authorize';
    protected string $tokenUrl = 'https://oauth.vk.com/access_token';
    protected string $endpoint = 'https://api.vk.com/method';
    
    /**
     * @var string the API version to send in the API request.
     *
     * @see https://vk.com/dev/versions
     */
    private string $apiVersion = '3.0';

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams(
            $request,
            [
                'v' => $this->apiVersion,
                'uids' => $accessToken->getParam('user_id'),
                'access_token' => $accessToken->getToken(),
            ]
        );
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'vkontakte'
     */
    public function getName(): string
    {
        return 'vkontakte';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'VKontakte'
     */
    public function getTitle(): string
    {
        return 'VKontakte';
    }

    protected function initUserAttributes(): array
    {
        $response = $this->api(
            'users.get.json',
            'GET',
            [
                'fields' => implode(',', 
                    [
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
                        'photo',
                    ]
                ),
            ]
        );

        if (empty($response['response'])) {
            throw new RuntimeException(
                'Unable to init user attributes due to unexpected response: ' . Json::encode($response)
            );
        }
        /**
         * @psalm-suppress MixedAssignment $aattributes
         * @psalm-suppress MixedArgument $response['response']
         */
        $attributes = array_shift($response['response']);
        if (is_array($attributes)) {
            $accessToken = $this->getAccessToken();
            if (is_object($accessToken)) {
                $accessTokenParams = $accessToken->getParams();
                unset($accessTokenParams['access_token'], $accessTokenParams['expires_in']);
                $attributes = array_merge($accessTokenParams, $attributes);
            }

            return $attributes;
        }
        return [];
    }

    /**
     * @return string[]
     *
     * @psalm-return array{id: 'uid'}
     */
    protected function defaultNormalizeUserAttributeMap(): array
    {
        return [
            'id' => 'uid',
        ];
    }
}
