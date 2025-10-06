<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * VKontakte allows authentication via VKontakte OAuth 2.0
 *
 * In order to use VKontakte OAuth you must register your application at <https://dev.vk.ru>.
 * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
 * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/how-auth-works/auth-flow-web
 * @see https://id.vk.ru/about/business/go/accounts/{USER}/apps/{APPLICATION_ID}/edit
 *
 * Authorization Code Workflow Client Id => VKontakte Application Id
 * Authorization Code Workflow Secret Id => Access Keys: Protected Key ... to perform requests to the VKontakte API on behalf of the application (used here)
 *                                          Access Keys: Service Key ... to perform requests to the VKontakte API on behalf of the application (not used here)
 *                                                                        when user authorization is not required
 */
final class VKontakte extends OAuth2
{
    protected string $authUrl = 'https://id.vk.ru/authorize';

    protected string $tokenUrl = 'https://id.vk.ru/oauth2/auth';

    protected string $endpoint = 'https://id.vk.ru/oauth2/user_info';

    /**
     * Example answer: [
     *      'access_token' => 'XXXXX',
     *      'refresh_token' => 'XXXXX',
     *      'expires_in' => 0,
     *      'user_id' => 1234567890,
     *      'state' => 'XXX',
     *      'scope' => 'email phone'
     * ]
     *
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     * Step 6: Getting New Access Token After Previous Token Expires
     *
     * @param string $refreshToken
     * @param string $clientId
     * @param string $deviceId
     * @param string $state
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @return mixed
     */
    public function step6GettingNewAccessTokenAfterPreviousExpires(
        string $refreshToken,
        string $clientId,
        string $deviceId,
        string $state,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): mixed {
        $url = $this->tokenUrl;
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'device_id' => $deviceId,
            'state' => $state,
        ];

        $request = $requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Add form body
        $request->getBody()->write(http_build_query($data));

        try {
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if ($response->getStatusCode() >= 400) {
                return [
                    'error' => 'Error:' . $response->getReasonPhrase(),
                ];
            }
            if (strlen($body) > 0) {
                return json_decode($body, true);
            }
        } catch (\Throwable $e) {
            return [
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }

        return [];
    }

    /**
     * Example answer: ["response" => 1]
     *
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 7. Token invalidation (logout)
     *
     * Converted to use PSR-18 ClientInterface and PSR-17 RequestFactoryInterface instead of curl.
     */
    public function step7TokenInvalidationWithClientId(
        OAuthToken $token,
        string $clientId,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): array {
        $url = 'https://id.vk.ru/oauth2/user_info';
        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) === 0) {
            return [];
        }

        $fullUrl = $url . '?client_id=' . urlencode($clientId) . '&access_token=' . urlencode($tokenString);

        $request = $requestFactory->createRequest('GET', $fullUrl);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (!empty($body)) {
                return (array) json_decode($body, true);
            }
        } catch (\Throwable) {
            // Optionally log error: $e->getMessage()
            return [];
        }

        return [];
    }

    /**
    * Example Answer:
    * [
    * "user" => [
    *              "user_id" => "1234567890",
    *              "first_name" => "Ivan",
    *              "last_name" => "Ivanov",
    *              "phone" => "79991234567",
    *              "avatar" => "https://pp.userapi.com/60tZWMo4SmwcploUVl9XEt8ufnTTvDUmQ6Bj1g/mmv1pcj63C4.png",
    *              "email" => "ivan_i123@vk.ru",
    *              "sex" => 2,
    *              "verified" => false,
    *              "birthday" => "01.01.2000"
    *          ]
    * ]
    *
    * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
    *      #Step 8. (Optional) Obtaining user data
    */
    public function step8ObtainingUserDataArrayWithClientId(
        OAuthToken $token,
        string $clientId,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): array {
        $url = 'https://id.vk.ru/oauth2/user_info';
        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) === 0) {
            return [];
        }

        $fullUrl = $url . '?client_id=' . urlencode($clientId) . '&access_token=' . urlencode($tokenString);

        $request = $requestFactory->createRequest('GET', $fullUrl);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (strlen($body) > 0) {
                return (array)json_decode($body, true);
            }
        } catch (\Throwable) {
            // Optionally log error: $e->getMessage()
            return [];
        }

        return [];
    }

    /**
     * Example answer: [
     *   "user" => [
     *     "user_id" => "1234567890",
     *     "first_name" => "Ivan",
     *     "last_name" => "Ivanov",
     *     "avatar" => "https://pp.userapi.com/60tZWMo4SmwcploUVl9XEt8ufnTTvDUmQ6Bj1g/mmv1pcj63C4.png",
     *     "sex" => 2,
     *     "verified" => false
     *   ]
     * ]
     *
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 9. (Optional) Getting public user data
     */
    public function step9GetPublicUserDataArrayWithClientId(
        string $clientId,
        string $userId,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): array {
        $fullUrl = $this->endpoint . '?client_id=' . urlencode($clientId) . '&user_id=' . urlencode($userId);

        $request = $requestFactory->createRequest('GET', $fullUrl);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
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

    #[\Override]
    public function getName(): string
    {
        return 'vkontakte';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'VKontakte';
    }

    #[\Override]
    public function getButtonClass(): string
    {
        return 'btn btn-dark';
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
     * @psalm-return 'email phone'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'email phone';
    }
}
