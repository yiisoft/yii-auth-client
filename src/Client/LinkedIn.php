<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'linkedin' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\LinkedIn::class,
 *             'clientId' => $_ENV['LINKEDIN_CLIENT_ID'],
 *             'clientSecret' => $_ENV['LINKEDIN_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @link https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?source=recommendations&tabs=HTTPS1
 * @link https://developer.linkedin.com/docs/oauth2
 * @link https://www.linkedin.com/secure/developer
 * @link https://developer.linkedin.com/docs/rest-api
 */
final class LinkedIn extends OAuth2
{
    protected string $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    protected string $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
    protected string $endpoint = 'https://api.linkedin.com/v2';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray(
            $token,
            'https://api.linkedin.com/v2/userinfo',
        );
    }

    public function getName(): string
    {
        return $this->name ?: 'linkedin';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'LinkedIn';
    }

    public function getButtonClass(): string
    {
        return 'btn btn-info bi bi-linkedin';
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
     * openid - Use your name and photo
     * profile - Use your name and photo
     * email - Use the primary email address associated with your LinkedIn account
     * w_member_social - Create, modify, and delete posts, comments, and reactions on your behalf
     *
     * @return string
     *
     * @psalm-return 'openid profile email w_member_social'
     */
    protected function getDefaultScope(): string
    {
        return 'openid profile email w_member_social';
    }
}
