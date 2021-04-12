<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;

use function in_array;

/**
 * GitHub allows authentication via GitHub OAuth.
 *
 * In order to use GitHub OAuth you must register your application at <https://github.com/settings/applications/new>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         'class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'github' => [
 *                 'class' => Yiisoft\Yii\AuthClient\Clients\GitHub::class,
 *                 'clientId' => 'github_client_id',
 *                 'clientSecret' => 'github_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @link http://developer.github.com/v3/oauth/
 * @link https://github.com/settings/applications/new
 */
final class GitHub extends OAuth2
{
    protected string $authUrl = 'https://github.com/login/oauth/authorize';
    protected string $tokenUrl = 'https://github.com/login/oauth/access_token';
    protected string $endpoint = 'https://api.github.com';

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'github';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'GitHub';
    }

    protected function getDefaultScope(): string
    {
        return 'user';
    }

    protected function initUserAttributes(): array
    {
        $attributes = $this->api('user', 'GET');

        if (empty($attributes['email'])) {
            // in case user set 'Keep my email address private' in GitHub profile, email should be retrieved via extra API request
            $scopes = explode(' ', $this->getScope());
            if (in_array('user:email', $scopes, true) || in_array('user', $scopes, true)) {
                $emails = $this->api('user/emails', 'GET');
                if (!empty($emails)) {
                    foreach ($emails as $email) {
                        if ($email['primary'] && $email['verified']) {
                            $attributes['email'] = $email['email'];
                            break;
                        }
                    }
                }
            }
        }

        return $attributes;
    }
}
