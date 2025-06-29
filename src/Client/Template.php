<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

class Template extends OAuth2
{
    protected string $authUrl = '';
    protected string $tokenUrl = '';
    protected string $endPoint = '';

    public function getCurrentUserJsonArray(OAuthToken $token): array
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
            $request = $this->createRequest('GET', 'https://www.example.com/oauth2/v2/userinfo');

            $request = RequestUtil::addHeaders(
                $request,
                [
                    'Authorization' => 'Bearer ' . $tokenString,
                    
                    'Host' => '',
                    
                    'Content-length' => 0,
                ]
            );

            $response = $this->sendRequest($request);

            $user = [];

            return (array)json_decode($response->getBody()->getContents(), true);
        }

        return [];
    }

    /**
     * @return string service name.
     *
     * @psalm-return ''
     */
    public function getName(): string
    {
        return '';
    }

    /**
     * @return string service title.
     *
     * @psalm-return ''
     */
    public function getTitle(): string
    {
        return '';
    }
    
    #[\Override]
    protected function getDefaultScope(): string
    {
        return '';
    }
}
