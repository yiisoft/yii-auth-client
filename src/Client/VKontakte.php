<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Exception;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

use function is_string;
use function strlen;

use const PHP_QUERY_RFC3986;

/**
 * VKontakte allows authentication via VKontakte OAuth 2.0
 *
 * In order to use VKontakte OAuth you must register your application at <https://dev.vk.ru>.
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'vkontakte' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\VKontakte::class,
 *             'clientId' => $_ENV['VKONTAKTE_CLIENT_ID'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
 * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/how-auth-works/auth-flow-web
 * @see https://id.vk.ru/about/business/go/accounts/{USER}/apps/{APPLICATION_ID}/edit
 *
 * Authorization Code Workflow Client Id => VKontakte Application Id
 *
 * VK ID's web flow is PKCE-based (RFC 7636) and does not use a client secret for the authorization-code
 * or refresh-token exchange - {@see buildAuthUrl()}/{@see fetchAccessToken()}/{@see refreshAccessToken()}
 * are overridden here to generate/store a `code_verifier`, send the matching `code_challenge`, and carry
 * the `device_id` VK ID returns on the callback (persisted on the token so a later refresh can reuse it).
 * No `clientSecret` is needed or sent by this class - {@see setClientSecret()} exists only for interface
 * compatibility with the rest of {@see OAuth2}.
 */
final class VKontakte extends OAuth2
{
    protected string $authUrl = 'https://id.vk.ru/authorize';
    protected string $tokenUrl = 'https://id.vk.ru/oauth2/auth';
    protected string $endpoint = 'https://id.vk.ru/oauth2/user_info';

    /**
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     * Step 3: adds the PKCE `code_challenge`/`code_challenge_method` VK ID requires, storing the matching
     * `code_verifier` for {@see fetchAccessToken()} to retrieve after the callback.
     */
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        $codeVerifier = $this->generateCodeVerifier();
        $this->setState('codeVerifier', $codeVerifier);

        return parent::buildAuthUrl($incomingRequest, array_merge(
            [
                'code_challenge' => $this->base64UrlEncode(hash('sha256', $codeVerifier, true)),
                'code_challenge_method' => 'S256',
            ],
            $params,
        ));
    }

    /**
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     * Step 5: exchanges the code for a token using the stored `code_verifier` and the `device_id` VK ID
     * appended to the callback, per VK ID's PKCE protocol (no `client_secret` is sent).
     */
    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        if ($this->validateAuthState) {
            /**
             * @var string|null $authState 'authState' is only ever written by
             * {@see buildAuthUrl()} with the string returned from {@see generateAuthState()}.
             */
            $authState = $this->getState('authState');
            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();
            /**
             * @psalm-suppress MixedAssignment
             */
            $incomingState = $queryParams['state'] ?? ($bodyParams['state'] ?? null);
            if (
                !is_string($incomingState)
                || empty($authState)
                || strcmp($incomingState, $authState) !== 0
            ) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }
            $this->removeState('authState');
        }

        $codeVerifier = (string) $this->getState('codeVerifier');
        $this->removeState('codeVerifier');

        /**
         * @infection-ignore-all
         * PSR-7 query params are always string|array (never another scalar type), and the `?? ''`
         * fallback is already a string, so this cast is only reachable by a non-string value if a caller
         * sends a malformed `device_id[]=...` array param - not a realistic input to assert against, and
         * the resulting behavior (an array flowing into `array_merge()`/`http_build_query()` either way)
         * isn't meaningfully different with or without the cast.
         */
        $deviceId = (string) ($incomingRequest->getQueryParams()['device_id'] ?? '');

        $defaultParams = [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'code_verifier' => $codeVerifier,
            'client_id' => $this->clientId,
            'device_id' => $deviceId,
            'redirect_uri' => $this->getOauth2ReturnUrl(),
        ];

        $request = $this->createTokenRequest(array_merge($defaultParams, $params));
        $response = $this->sendRequest($request);
        $output = (array) Json::decode($response->getBody()->getContents());
        $output['device_id'] ??= $deviceId;

        $token = $this->createToken(['params' => $output]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * @see https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
     * Step 6: refreshes using the `device_id` persisted on the expired token (no `client_secret` is sent).
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $deviceId = (string) $token->getParam('device_id');

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $token->getParam('refresh_token'),
            'client_id' => $this->clientId,
            'device_id' => $deviceId,
            'state' => $this->generateAuthState(),
        ];

        $request = $this->createTokenRequest($params);
        $response = $this->sendRequest($request);
        $output = (array) Json::decode($response->getBody()->getContents());
        $output['device_id'] ??= $deviceId;

        return $this->createToken(['params' => $output]);
    }

    /**
     * Manual/explicit equivalent of {@see refreshAccessToken()}, which now performs this same Step 6 request
     * automatically (reusing the `device_id` persisted on the expired token) as part of the standard
     * {@see OAuth::restoreAccessToken()} auto-refresh lifecycle. Kept for callers managing their own
     * `httpClient`/`requestFactory`/`device_id`/`state` outside that lifecycle.
     *
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
        RequestFactoryInterface $requestFactory,
    ): mixed {
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'device_id' => $deviceId,
            'state' => $state,
        ];

        $request = $requestFactory->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Add form body
        $request->getBody()->write(http_build_query($data, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986));

        try {
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if ($response->getStatusCode() >= 400) {
                return [
                    'error' => 'Error:' . $response->getReasonPhrase(),
                ];
            }
            if (strlen($body) > 0) {
                return Json::decode($body);
            }
        } catch (Throwable $e) {
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
        RequestFactoryInterface $requestFactory,
    ): array {
        return $this->requestWithClientIdAndAccessToken(
            'https://id.vk.ru/oauth2/logout',
            $token,
            $clientId,
            $httpClient,
            $requestFactory,
        );
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
        RequestFactoryInterface $requestFactory,
    ): array {
        return $this->getUserInfoByClientId($token, $clientId, $httpClient, $requestFactory);
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
        RequestFactoryInterface $requestFactory,
    ): array {
        $fullUrl = $this->endpoint . '?client_id=' . urlencode($clientId) . '&user_id=' . urlencode($userId);

        $request = $requestFactory->createRequest('GET', $fullUrl);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            return (array) Json::decode($body);
        } catch (Throwable) {
            // Optionally log error: $e->getMessage()
        }

        return [];
    }

    public function getName(): string
    {
        return $this->name ?: 'vkontakte';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'VKontakte';
    }

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        $data = $this->step8ObtainingUserDataArrayWithClientId(
            $oauthToken,
            $this->getClientId(),
            $this->httpClient,
            $this->requestFactory,
        );
        return (array) ($data['user'] ?? []);
    }

    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if (!$token instanceof OAuthToken) {
            return [];
        }
        return $this->getCurrentUserJsonArray($token);
    }

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
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
    protected function getDefaultScope(): string
    {
        return 'email phone';
    }

    /**
     * Fetches user data from the `user_info` endpoint, used by {@see step8ObtainingUserDataArrayWithClientId()}.
     */
    private function getUserInfoByClientId(
        OAuthToken $token,
        string $clientId,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
    ): array {
        return $this->requestWithClientIdAndAccessToken(
            'https://id.vk.ru/oauth2/user_info',
            $token,
            $clientId,
            $httpClient,
            $requestFactory,
        );
    }

    /**
     * Shared GET request builder for VK ID endpoints authenticated by `client_id` and `access_token`
     * query parameters, used by {@see step7TokenInvalidationWithClientId()} and {@see getUserInfoByClientId()}.
     */
    private function requestWithClientIdAndAccessToken(
        string $url,
        OAuthToken $token,
        string $clientId,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
    ): array {
        $tokenString = (string) $token->getParam('access_token');

        if (strlen($tokenString) === 0) {
            return [];
        }

        $fullUrl = $url . '?' . http_build_query([
            'client_id'    => $clientId,
            'access_token' => $tokenString,
        ]);

        $request = $requestFactory->createRequest('GET', $fullUrl);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            return (array) Json::decode($body);
        } catch (Throwable) {
            // Optionally log error: $e->getMessage()
        }

        return [];
    }

    /**
     * Generates a PKCE `code_verifier` per RFC 7636 §4.1 (43-128 chars from the unreserved URI charset).
     *
     * @throws Exception on failure to gather sufficient entropy.
     */
    private function generateCodeVerifier(): string
    {
        return $this->base64UrlEncode(random_bytes(64));
    }

    /**
     * Base64url-encodes (RFC 4648 §5) without padding, as required for PKCE's `code_challenge`/`code_verifier`.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
