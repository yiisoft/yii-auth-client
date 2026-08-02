<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * Discord allows authentication via Discord OAuth2.
 *
 * In order to use Discord OAuth2 you must register your application at
 * <https://discord.com/developers/applications>.
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'discord' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Discord::class,
 *             'clientId' => $_ENV['DISCORD_CLIENT_ID'],
 *             'clientSecret' => $_ENV['DISCORD_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @link https://discord.com/developers/docs/topics/oauth2
 * @link https://discord.com/developers/applications
 */
final class Discord extends OAuth2
{
    protected string $authUrl = 'https://discord.com/oauth2/authorize';
    protected string $tokenUrl = 'https://discord.com/api/oauth2/token';
    protected string $endpoint = 'https://discord.com/api';

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        return $this->fetchCurrentUserJsonArray(
            $oauthToken,
            $this->endpoint . '/users/@me',
        );
    }

    public function getName(): string
    {
        return $this->name ?: 'discord';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Discord';
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
     * identify - Access to the user's basic account information (excluding email)
     * email - Access to the user's email address
     *
     * @return string
     *
     * @psalm-return 'identify email'
     */
    protected function getDefaultScope(): string
    {
        return 'identify email';
    }
}
