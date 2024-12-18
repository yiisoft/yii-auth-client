<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

use function in_array;

/**
 * GitHub allows authentication via GitHub OAuth.
 *
 * In order to use GitHub OAuth you must register your application at <https://github.com/settings/applications/new>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         'class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'github' => [
 *                 'class' => Yiisoft\Yii\AuthClient\Clients\GitHub::class,
 *                 'clientId' => 'github_client_id',
 *                 'clientSecret' => 'github_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
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
    
    public function getCurrentUserJsonArray(OAuthToken $token) : array
    {
        // Here is the actual 'access-token' which the user has allowed us to access their basic info.
        $tokenString = (string)$token->getParam('access_token');
        
        if (strlen($tokenString) > 0) {
        
            $request = $this->createRequest('GET', 'https://api.github.com/user');

            $request = RequestUtil::addHeaders($request, 
                    [
                        'Authorization' => 'Bearer '. $tokenString,
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ]);
            
            $response = $this->sendRequest($request);
            
            $user = [];
            // the array returns basic info of the user including login  
            return (array)json_decode($response->getBody()->getContents(), true);
        }
        
        return [];
        
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'github'
     */
    public function getName(): string
    {
        return 'github';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'GitHub'
     */
    public function getTitle(): string
    {
        return 'GitHub';
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

    /**
     * @return array
     */
    protected function initUserAttributes(): array
    {
        $attributes = $this->api('user', 'GET') ?: ['email', 'name'];

        if (empty($attributes['email'])) {
            // in case user set 'Keep my email address private' in GitHub profile, email should be retrieved via extra API request
            $scopes = explode(' ', $this->getScope());
            if (in_array('user:email', $scopes, true) || in_array('user', $scopes, true)) {
                $emails = $this->api('user/emails', 'GET');
                if (!empty($emails)) {
                    /**
                     * @var array $emails
                     * @var array $email
                     */
                    foreach ($emails as $email) {
                        if ($email['primary'] && $email['verified']) {
                            /**
                             * @var string $email['email']
                             * @var string $attributes['email']
                             */
                            $attributes['email'] = $email['email'];
                            break;
                        }
                    }
                }
            }
        }
        return $attributes;
    }
}
