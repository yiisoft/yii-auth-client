<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * MicrosoftOnline allows authentication via the Microsoft Identity Platform.
 *
 * In order to use the Microsoft Identity Platform, you must register your application at 
 * <https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize>
 * 
 * https://learn.microsoft.com/en-us/azure/active-directory-b2c/tutorial-register-applications
 *
 * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
 */
final class MicrosoftOnline extends OAuth2
{
    /**
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#protocol-details
     */
    protected string $authUrl = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize';
    
    protected string $tokenUrl = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token';
    
    protected string $endpoint = 'https://graph.microsoft.com';
    
    /**
     * tentant can be one of 'common', 'organisation', 'consumers', or the actual TenantID.
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#request-an-authorization-code
     */
    protected string $tenant = 'common';
    
    public function setTenant(string $tenant) : void
    {
        $this->tenant = $tenant;
    }
    
    public function getTenant() : string
    {
        return $this->tenant;
    }
    
    public function setAuthUrl(string $authUrl) : void
    {
        $this->authUrl = $authUrl;
    }
    
    public function getAuthUrlWithTenantInserted(string $tenant) : string
    {
        return 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/authorize';
    }    
    
    public function setTokenUrl(string $tokenUrl) : void
    {
        $this->tokenUrl = $tokenUrl;
    }
    
    public function getTokenUrlWithTenantInserted(string $tenant) : string
    {
        return 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token';
    }
    
    public function getCurrentUserJsonArray(OAuthToken $token) : array
    {
        /**
         * e.g. '{all the params}' => ''
         * @var array $params
         */
        $tokenParams = $token->getParams();
        
        /**
         * e.g. convert the above key, namely '{all the params}', into an array 
         * @var array $tokenArray
         */
        $tokenArray = array_keys($tokenParams);
        
        /**
         * @var string $jsonString
         */
        $jsonString = $tokenArray[0];
        
        /**
         * @var array $finalArray
         */
        $finalArray = json_decode($jsonString, true);
        
        /**
         * @var string $tokenString
         */
        $tokenString = $finalArray['access_token'] ?? '';
        
        if (strlen($tokenString) > 0) {
            
            $request = $this->createRequest('GET', 'graph.microsoft.com');
            
            $request = RequestUtil::addHeaders($request, 
                    [
                        'Authorization' => 'Bearer '.$tokenString,
                        'Host' => 'graph.microsoft.com'
                    ]);
            
            $response = $this->sendRequest($request);
            
            $user = [];
            
            return (array)json_decode($response->getBody()->getContents(), true);
        }
        
        return [];
        
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'microsoftonline'
     */
    public function getName(): string
    {
        return 'microsoftonline';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'MicrosoftOnline'
     */
    public function getTitle(): string
    {
        return 'MicrosoftOnline';
    }

    /**
     * @return string
     *
     * @psalm-return 'https://graph.microsoft.com/mail.read'
     */
    protected function getDefaultScope(): string
    {
        return 'https://graph.microsoft.com/mail.read';
    }

    protected function initUserAttributes(): array
    {
        return $this->api('me', 'GET');
    }
}
