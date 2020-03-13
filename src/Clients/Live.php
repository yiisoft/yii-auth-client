<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Clients;

use Yiisoft\Yii\AuthClient\OAuth2;

/**
 * Live allows authentication via Microsoft Live OAuth.
 *
 * In order to use Microsoft Live OAuth you must register your application at <https://account.live.com/developers/applications>
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'live' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Live::class,
 *                 'clientId' => 'live_client_id',
 *                 'clientSecret' => 'live_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://account.live.com/developers/applications
 * @see http://msdn.microsoft.com/en-us/library/live/hh243647.aspx
 */
class Live extends OAuth2
{
    public $authUrl = 'https://login.live.com/oauth20_authorize.srf';
    public $tokenUrl = 'https://login.live.com/oauth20_token.srf';
    public $endpoint = 'https://apis.live.net/v5.0';

    protected function getDefaultScope(): string
    {
        return 'wl.basic wl.emails';
    }

    protected function initUserAttributes()
    {
        return $this->api('me', 'GET');
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'live';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'Live';
    }
}
