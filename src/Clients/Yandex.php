<?php

declare(strict_types=1);

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
final class Yandex extends OAuth2
{
    protected string $authUrl = 'https://oauth.yandex.ru/authorize';
    protected string $tokenUrl = 'https://oauth.yandex.ru/token';
    protected string $endpoint = 'https://login.yandex.ru';


    protected function initUserAttributes(): array
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
