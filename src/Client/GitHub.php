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
 * config/common/params.php:
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'github' => GitHub::class,
 *     ],
 * ],
 *
 * config/common/di.php:
 * GitHub::class => [
 *     'setClientId()' => [$_ENV['GITHUB_API_CLIENT_ID'] ?? ''],
 *     'setClientSecret()' => [$_ENV['GITHUB_API_CLIENT_SECRET'] ?? ''],
 * ],
 *
 * @link https://developer.github.com/v3/oauth/
 * @link https://github.com/settings/applications/new
 */
final class GitHub extends OAuth2
{
    /**
     * @see https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps#1-request-a-users-github-identity
     */
    protected string $authUrl = 'https://github.com/login/oauth/authorize';

    /**
     * @see https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps#2-users-are-redirected-back-to-your-site-by-github
     */
    protected string $tokenUrl = 'https://github.com/login/oauth/access_token';

    protected string $endpoint = 'https://api.github.com';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray($token, 'https://api.github.com/user');
    }

    public function getName(): string
    {
        return 'github';
    }

    public function getTitle(): string
    {
        return 'GitHub';
    }

    public function getButtonClass(): string
    {
        return 'btn btn-primary bi bi-github';
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
