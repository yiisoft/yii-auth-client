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
 * Yandex allows authentication via Yandex OAuth.
 *
 * In order to use Yandex OAuth you must register your application at <https://oauth.yandex.ru/client/new>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'yandex' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Yandex::class,
 *                 'clientId' => 'yandex_client_id',
 *                 'clientSecret' => 'yandex_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://oauth.yandex.ru/client/new
 * @see http://api.yandex.ru/login/doc/dg/reference/response.xml
 */
class Yandex extends OAuth2
{
    public $authUrl = 'https://oauth.yandex.ru/authorize';
    public $tokenUrl = 'https://oauth.yandex.ru/token';
    public $endpoint = 'https://login.yandex.ru';


    protected function initUserAttributes()
    {
        return $this->api('info', 'GET');
    }

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $params = RequestUtil::getParams($request);

        $paramsToAdd = [];
        if (!isset($params['format'])) {
            $paramsToAdd['format'] = 'json';
        }
        $paramsToAdd['oauth_token'] = $accessToken->getToken();
        return RequestUtil::addParams($request, $paramsToAdd);
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'yandex';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'Yandex';
    }
}
