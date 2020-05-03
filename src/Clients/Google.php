<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Clients;

use Yiisoft\Yii\AuthClient\OAuth2;

/**
 * Google allows authentication via Google OAuth.
 *
 * In order to use Google OAuth you must create a project at <https://console.developers.google.com/project>
 * and setup its credentials at <https://console.developers.google.com/project/[yourProjectId]/apiui/credential>.
 * In order to enable using scopes for retrieving user attributes, you should also enable Google+ API at
 * <https://console.developers.google.com/project/[yourProjectId]/apiui/api/plus>
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'google' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Google::class,
 *                 'clientId' => 'google_client_id',
 *                 'clientSecret' => 'google_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://console.developers.google.com/project
 */
class Google extends OAuth2
{
    public $authUrl = 'https://accounts.google.com/o/oauth2/auth';
    public $tokenUrl = 'https://accounts.google.com/o/oauth2/token';
    public $endpoint = 'https://www.googleapis.com/plus/v1';

    protected function getDefaultScope(): string
    {
        return 'profile email';
    }

    protected function initUserAttributes()
    {
        return $this->api('people/me', 'GET');
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'google';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'Google';
    }
}
