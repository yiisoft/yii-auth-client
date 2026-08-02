<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * GitHub allows authentication via GitHub OAuth.
 *
 * In order to use GitHub OAuth you must register your application at <https://github.com/settings/applications/new>.
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'github' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\GitHub::class,
 *             'clientId' => $_ENV['GITHUB_CLIENT_ID'],
 *             'clientSecret' => $_ENV['GITHUB_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @link https://developer.github.com/v3/oauth/
 * @link https://github.com/settings/applications/new
 */
final class GitHub extends OAuth2
{
    protected string $authUrl = 'https://github.com/login/oauth/authorize';
    protected string $tokenUrl = 'https://github.com/login/oauth/access_token';
    protected string $endpoint = 'https://api.github.com';

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        return $this->fetchCurrentUserJsonArray(
            $oauthToken,
            $this->endpoint . '/user',
        );
    }

    public function getName(): string
    {
        return $this->name ?: 'github';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'GitHub';
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
     * @psalm-return 'user'
     */
    protected function getDefaultScope(): string
    {
        return 'user';
    }
}
