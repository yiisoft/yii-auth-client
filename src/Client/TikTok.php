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

    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array
    {
        /**
         * @see ... useful endpoints
         */
        $url = '';

        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) > 0) {
            $ch = curl_init($url);

            if ($ch != false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $tokenString,
                ]);

                $response = curl_exec($ch);

                curl_close($ch);

                if (is_string($response) && strlen($response) > 0) {
                    return (array)json_decode($response, true);
                }

                return [];
            }

            return [];
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

    /**
     * @return string service name.
     *
     * @psalm-return 'tiktok'
     */
    public function getName(): string
    {
        return 'tiktok';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'TikTok'
     */
    public function getTitle(): string
    {
        return 'TikTok';
    }
}
