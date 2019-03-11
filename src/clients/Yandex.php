<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\authclient\clients;

use Psr\Http\Message\RequestInterface;
use SebastianBergmann\CodeCoverage\Util;
use yii\authclient\OAuth2;
use yii\authclient\OAuthToken;
use yii\authclient\RequestUtil;

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
 *         '__class' => yii\authclient\Collection::class,
 *         'clients' => [
 *             'yandex' => [
 *                 '__class' => yii\authclient\clients\Yandex::class,
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
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Yandex extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://oauth.yandex.ru/authorize';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://oauth.yandex.ru/token';
    /**
     * {@inheritdoc}
     */
    public $endpoint = 'https://login.yandex.ru';


    /**
     * {@inheritdoc}
     */
    protected function initUserAttributes()
    {
        return $this->api('info', 'GET');
    }

    /**
     * {@inheritdoc}
     */
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
