<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * VKontakte allows authentication via VKontakte OAuth 2.0
 *
 * In order to use VKontakte OAuth you must register your application at <https://dev.vk.com>.
 * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
 * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/how-auth-works/auth-flow-web
 * @see https://id.vk.com/about/business/go/accounts/{USER}/apps/{APPLICATION_ID}/edit
 *
 * Authorization Code Workflow Client Id => VKontakte Application Id
 * Authorization Code Workflow Secret Id => Access Keys: Protected Key ... to perform requests to the VKontakte API on behalf of the application (used here)
 *                                          Access Keys: Service Key ... to perform requests to the VKontakte API on behalf of the application (not used here)
 *                                                                        when user authorization is not required
 */
final class VKontakte extends OAuth2 implements VKontakteInterface
{
    protected string $authUrl = 'https://id.vk.com/authorize';

    protected string $tokenUrl = 'https://id.vk.com/oauth2/auth';

    protected string $endpoint = 'https://id.vk.com/oauth2/user_info';

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
     * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 6. Getting New Access Token After Previous Token Expires
     *
     * @param string $refreshToken
     * @param string $clientId
     * @param string $deviceId
     * @param string $state
     * @return mixed
     */
    public function step6GettingNewAccessTokenAfterPreviousExpires(
        string $refreshToken,
        string $clientId,
        string $deviceId,
        string $state
    ): mixed {
        $url = 'https://id.vk.com/oauth2/auth';

        $data = [
            'grant_type' => 'refresh_token',

            'refresh_token' => $refreshToken,

            'client_id' => $clientId,

            'device_id' => $deviceId,

            'state' => $state,
        ];

        $ch = curl_init($url);

        if ($ch != false) {
            curl_setopt($ch, CURLOPT_POST, 1);

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            /**
             * $response is json array string e.g. '{data}'
             */
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                return [
                    'error' => 'Error:' . curl_error($ch),
                ];
            }

            curl_close($ch);

            if (is_string($response) && strlen($response) > 0) {
                return (array)json_decode($response, true);
            }

            return [];
        }

        return [];



        return [];
    }

    /**
     * Example answer: ["response" => 1]
     *
     * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 7. Token invalidation (logout)
     */
    public function step7TokenInvalidationUsingCurlWithClientId(OAuthToken $token, string $clientId): array
    {
        $url = 'https://id.vk.com/oauth2/user_info';

        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) > 0) {
            $ch = curl_init();

            if ($ch != false) {
                curl_setopt($ch, CURLOPT_URL, $url . '?client_id=' . $clientId . '&access_token=' . $tokenString);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
     * Example Answer:
     * [
     * "user" => [
     *              "user_id" => "1234567890",
     *              "first_name" => "Ivan",
     *              "last_name" => "Ivanov",
     *              "phone" => "79991234567",
     *              "avatar" => "https://pp.userapi.com/60tZWMo4SmwcploUVl9XEt8ufnTTvDUmQ6Bj1g/mmv1pcj63C4.png",
     *              "email" => "ivan_i123@vk.com",
     *              "sex" => 2,
     *              "verified" => false,
     *              "birthday" => "01.01.2000"
     *          ]
     * ]
     *
     * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 8. (Optional) Obtaining user data
     */
    public function step8ObtainingUserDataArrayUsingCurlWithClientId(OAuthToken $token, string $clientId): array
    {
        $url = 'https://id.vk.com/oauth2/user_info';

        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) > 0) {
            $headers = [
                "Authorization: Bearer $tokenString",
            ];

            $ch = curl_init();

            if ($ch != false) {
                curl_setopt($ch, CURLOPT_URL, $url . '?client_id=' . $clientId);

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
     * Example answer:
     * [
     *      "user" => [
     *          "user_id" => "1234567890",
     *          "first_name" => "Ivan",
     *          "last_name" => "I.",
     *          "phone" => "+42872 *** ** 29",
     *          "avatar" => "https://pp.userapi.com/60tZWMo4SmwcploUVl9XEt8ufnTTvDUmQ6Bj1g/mmv1pcj63C4.png",
     *          "email" => "iv***@vk.vom"
     *        ]
     * ]
     *
     * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     *      #Step 9. (Optional) Get public user data
     */
    public function step9GetPublicUserDataArrayUsingCurlWithClientId(OAuthToken $token, string $clientId): array
    {
        $url = 'https://id.vk.com/oauth2/user_info';

        $tokenString = (string)$token->getParam('id_token');

        if (strlen($tokenString) > 0) {
            $headers = [
                "Authorization: Bearer $tokenString",
            ];

            $ch = curl_init();

            if ($ch != false) {
                curl_setopt($ch, CURLOPT_URL, $url . '?client_id=' . $clientId);

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
     * @psalm-return 'email phone'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'email phone';
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'vkontakte'
     */
    public function getName(): string
    {
        return 'vkontakte';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'VKontakte'
     */
    public function getTitle(): string
    {
        return 'VKontakte';
    }
}
