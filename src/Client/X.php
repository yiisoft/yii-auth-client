<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * Date: 10/01/2025
 * X allows authentication via OAuth2.0 Authorization Code Flow with PKCE.
 * 
 * @see https://developer.twitter.com/en/portal/projects/YOURS/apps/YOURS/auth-settings
 * Developer Portal: Projects and Apps: User Authentication Settings: Edit
 * -> App Permissions: Read
 * -> Type of App: Native App: Public Client (Not Confidential Client) 
 * 
 * PKCE: An extension to the authorization code flow to prevent several attacks and to be able
 * to perform the OAuth exchange from public clients securely using two parameters code_challenge and 
 * code_challenge_method.
 * 
 * OAuth 2.0 is an industry-standard authorization protocol that allows for greater
 * control over an application’s scope, and authorization flows across multiple devices. 
 * OAuth 2.0 allows you to pick specific fine-grained scopes which give you specific permissions
 * on behalf of a user.
 * 
 * By default, the access token you create through the Authorization Code Flow with PKCE will
 * only stay valid for two hours unless you have used the offline.access scope.
 * 
 * Refresh tokens allow an application to obtain a new access token without prompting the user 
 * via the refresh token flow.

 * If the scope offline.access is applied, an OAuth 2.0 refresh token will be issued. With this refresh token, 
 * you obtain an access token. If this scope is not passed, we will not generate a refresh token.
 *
 * Grant Types Available: Authorization code (used here), client credentials, device code, and refresh token.
 * 
 * @see https://developer.x.com/en/docs/authentication/oauth-2-0/authorization-code
 */

final class X extends OAuth2
{
    protected string $authUrl = 'https://x.com/i/oauth2/authorize';
    
    protected string $tokenUrl = 'https://api.x.com/2/oauth2/token';
    
    protected string $endpoint = 'https://api.x.com/2/users/me';

    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token) : array {
        /**
         * @see https://docs.x.com/resources/fundamentals/authentication/guides/v2-authentication-mapping ... useful endpoints 
         * AI: Github Copilot Question:
         * "What is the most popular php code for a twitter oauth2.0 public client
         *  AND confidential client in order to use the access token 
         *  to retrieve user information with scopes included?"
         */
        $url = 'https://api.x.com/2/users/me';
        
        $tokenString = (string)$token->getParam('access_token');
        
        if (strlen($tokenString) > 0) {
            
            $ch = curl_init($url);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokenString,
            ]);
            
            $response = curl_exec($ch);
            
            curl_close($ch);
            
            if (is_string($response) && strlen($response) > 0) {
                return (array)json_decode($response, true);
            } else {
                return [];
            }    
        }
        
        return [];
    }
    
    /**
     * @return string
     *
     * @psalm-return 'users.read tweet.read offline.access'
     */
    protected function getDefaultScope(): string
    {
        return 'users.read tweet.read offline.access';
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'x'
     */
    public function getName(): string
    {
        return 'x';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'X'
     */
    public function getTitle(): string
    {
        return 'X';
    }
}
