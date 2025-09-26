<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * GitHub allows authentication via GitHub OAuth.
 *
 * In order to use GitHub OAuth you must register your application at <https://github.com/settings/applications/new>.
 *
 * Example application configuration:
 *
 * config/common/params.php
 *
 * 'yiisoft/yii-auth-client' => [
 *       'enabled' => true,
 *       'clients' => [
 *           'github' => [
 *               'class' => 'Yiisoft\Yii\AuthClient\Client\Github::class',
 *               'clientId' => $_ENV['GITHUB_API_CLIENT_ID'] ?? '',
 *               'clientSecret' => $_ENV['GITHUB_API_CLIENT_SECRET'] ?? '',
 *               'returnUrl' => $_ENV['GITHUB_API_CLIENT_RETURN_URL'] ?? '',
 *           ],
 *       ],
 *   ],
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
        // Here is the actual 'access-token' which the user has allowed us to access their basic info.
        $tokenString = (string)$token->getParam('access_token');

        if ($tokenString !== '') {
            $request = $this->createRequest('GET', 'https://api.github.com/user');

            $request = RequestUtil::addHeaders(
                $request,
                [
                    'Authorization' => 'Bearer ' . $tokenString,
                ]
            );

            $response = $this->sendRequest($request);

            // the array returns basic info of the user including login i.e. username, and github id
            // which will be used later to concatenate or build-up a username for our purposes.
            return (array)json_decode($response->getBody()->getContents(), true);
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
    public function getName(): string
    {
        return 'github';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'GitHub';
    }
        
    #[\Override]
    public function getButtonClass(): string
    {
        return 'btn btn-primary bi bi-github';
    }    
    
    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    #[\Override]
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
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'user';
    }
}
