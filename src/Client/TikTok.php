<?php

/**
 * Note this client has not been tested yet and will fail and is just a 'shell'
 */

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

final class TikTok extends OAuth2
{
    protected string $authUrl = '';

    protected string $tokenUrl = '';

    protected string $endpoint = '';

    public function getCurrentUserJsonArray(
        OAuthToken $token
    ): array {
        /**
         * @see ... useful endpoints
         */
        $url = '';

        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) === 0) {
            return [];
        }

        $request = $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $tokenString);

        try {
            $response = $this->httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (strlen($body) > 0) {
                return (array) json_decode($body, true);
            }
        } catch (\Throwable) {
            // Optionally log error: $e->getMessage()
            return [];
        }

        return [];
    }
    
    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
    }
    
    #[\Override]
    public function getButtonClass(): string
    {
        return '';
    }   

    /**
     * @return string
     *
     * @psalm-return 'user.info.profile'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'user.info.profile';
    }

    #[\Override]
    public function getName(): string
    {
        return 'tiktok';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'TikTok';
    }
}
