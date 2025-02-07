<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * Yandex allows authentication via Yandex OAuth.
 *
 * In order to use Yandex OAuth you must register your application at <https://oauth.yandex.ru/client/new>.
 *
 * @link https://oauth.yandex.ru/client/new
 * @link https://api.yandex.ru/login/doc/dg/reference/response.xml
 */
final class Yandex extends OAuth2
{
    protected string $authUrl = 'https://oauth.yandex.com/authorize';
    
    protected string $tokenUrl = 'https://oauth.yandex.com/token';
    
    protected string $endpoint = 'https://login.yandex.ru';

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $params = RequestUtil::getParams($request);

        $paramsToAdd = [];
        
        if (!isset($params['format'])) {
        
            $paramsToAdd['format'] = 'json';
                    
        }
        
        $paramsToAdd['oauth_token'] = $accessToken->getToken();
        
        return RequestUtil::addParams($request, $paramsToAdd);
    }
    
    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token) : array {
        
        /**
         * @see https://yandex.com/dev/id/doc/en/codes/code-url
         */
        
        $url = 'https://login.yandex.ru/info';
        
        $tokenString = (string)$token->getParam('access_token');
        
        if (strlen($tokenString) > 0) {
            
            $headers = [
                "Authorization: OAuth $tokenString"
            ];
            
            $ch = curl_init();
            
            if ($ch <> false) {

                curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);

                curl_close($ch);

                if (is_string($response) && strlen($response) > 0) {

                    $data = (array)json_decode($response, true); 

                    return $data;

                } else {

                    return [];
                }
            
            } else {
                
                return [];
                
            }    
            
        }
        
        return [];
    }
    
   /**
    * @see https://oauth.yandex.com/client/<client_id>/info
    * @see https://yandex.com/dev/id/doc/en/user-information#common
    * @return string
    *
    * @psalm-return 'login:info'
    */
    protected function getDefaultScope(): string
    {
        return 'login:info';
    }
    
    /**
     * @return string service name.
     *
     * @psalm-return 'yandex'
     */
    public function getName(): string
    {
        return 'yandex';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'Yandex'
     */
    public function getTitle(): string
    {
        return 'Yandex';
    }
}
