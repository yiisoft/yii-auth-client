<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
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

    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array
    {
        $tokenString = (string)$token->getParam('access_token');
        if (strlen($tokenString) > 0) {
            /** https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2#api-request-to-retreive-member-details */
            $ch = curl_init('https://api.linkedin.com/v2/userinfo');
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

    public function getName(): string
    {
        return 'linkedin';
    }

    public function getTitle(): string
    {
        return 'LinkedIn';
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
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'openid profile email w_member_social';
    }
}
