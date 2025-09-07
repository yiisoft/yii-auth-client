<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

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

    #[\Override]
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

    function getCurrentUserJsonArray(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        string $accessToken
    ): array {
        // Yandex API endpoint for current user info
        $url = 'https://login.yandex.ru/info?format=json';

        // Build PSR-7 Request
        $request = $requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'OAuth ' . $accessToken);

        // Send request via PSR-18 client
        $response = $client->sendRequest($request);

        // Check status code
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Yandex API request failed: ' . $response->getStatusCode());
        }

        // Get and decode JSON response body
        $body = (string) $response->getBody();
        $data = (array) json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON received from Yandex API: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @see https://oauth.yandex.com/client/<client_id>/info
     * @see https://yandex.com/dev/id/doc/en/user-information#common
     * @return string
     *
     * @psalm-return 'login:info'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'login:info';
    }

    public function getName(): string
    {
        return 'yandex';
    }

    public function getTitle(): string
    {
        return 'Yandex';
    }
}
