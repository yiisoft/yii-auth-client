<?php

/**
 * TikTok allows authentication via TikTok OAuth 2.0.
 *
 * @see https://developers.tiktok.com/documentation/oauth/user-access-token
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'tiktok' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\TikTok::class,
 *             'clientId' => $_ENV['TIKTOK_CLIENT_ID'],
 *             'clientSecret' => $_ENV['TIKTOK_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 */

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

final class TikTok extends OAuth2
{
    protected string $authUrl = 'https://www.tiktok.com/v2/auth/authorize/';
    protected string $tokenUrl = 'https://open.tiktokapis.com/v2/oauth/token/';
    protected string $endpoint = 'https://open.tiktokapis.com/v2/user/info/';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray(
            $token,
            'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url',
        );
    }

    public function getButtonClass(): string
    {
        return '';
    }

    public function getName(): string
    {
        return $this->name ?: 'tiktok';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'TikTok';
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
     * @return string
     *
     * @psalm-return 'user.info.profile'
     */
    protected function getDefaultScope(): string
    {
        return 'user.info.profile';
    }
}
