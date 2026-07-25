<?php

/**
 * Note this client has not been tested yet and will fail and is just a 'shell'
 */

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Override;

final class TikTok extends OAuth2
{
    protected string $authUrl = '';

    protected string $tokenUrl = '';

    protected string $endpoint = '';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        /**
         * @see ... useful endpoints
         */
        $url = '';

        return $this->fetchCurrentUserJsonArray($token, $url);
    }

    #[Override]
    public function getButtonClass(): string
    {
        return '';
    }

    #[Override]
    public function getName(): string
    {
        return 'tiktok';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'TikTok';
    }

    #[Override]
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
    #[Override]
    protected function getDefaultScope(): string
    {
        return 'user.info.profile';
    }
}
