<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
 *
 * @link https://developer.linkedin.com/docs/oauth2
 * @link https://www.linkedin.com/secure/developer
 * @link https://developer.linkedin.com/docs/rest-api
 */
final class LinkedIn extends OAuth2
{
    protected string $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    protected string $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
    protected string $endpoint = 'https://api.linkedin.com/v1';
    
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
            
            $request = $this->createRequest('GET', 'https://api.linkedin.com/v1/user');
            
            $request = RequestUtil::addHeaders($request, 
                    [
                        'Authorization' => 'Bearer '.$tokenString
                    ]);
            
            $response = $this->sendRequest($request);
            
            $user = [];
            
            return (array)json_decode($response->getBody()->getContents(), true);
        }
        
        return [];
        
    }
    
    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams(
            $request,
            [
                'oauth2_access_token' => $accessToken->getToken(),
            ]
        );
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'linkedin'
     */
    public function getName(): string
    {
        return 'linkedin';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'LinkedIn'
     */
    public function getTitle(): string
    {
        return 'LinkedIn';
    }

    /**
     * @return string
     *
     * @psalm-return 'r_basicprofile r_emailaddress'
     */
    protected function getDefaultScope(): string
    {
        return 'r_basicprofile r_emailaddress';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{email: 'email-address', first_name: 'first-name', last_name: 'last-name'}
     */
    protected function defaultNormalizeUserAttributeMap(): array
    {
        return [
            'email' => 'email-address',
            'first_name' => 'first-name',
            'last_name' => 'last-name',
        ];
    }

    protected function initUserAttributes(): array
    {
        return $this->api('people/~:(' . implode(',', 
            [
                'id',
                'email-address',
                'first-name',
                'last-name',
                'public-profile-url'
            ]
        ) . ')', 'GET');
    }
}
