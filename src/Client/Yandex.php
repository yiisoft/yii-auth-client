<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Override;
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
 * @link https://yandex.com/dev/id/doc/en/codes/code-url
 */
final class Yandex extends OAuth2
{
    protected string $authUrl = 'https://oauth.yandex.com/authorize';

    protected string $tokenUrl = 'https://oauth.yandex.com/token';

    protected string $endpoint = 'https://login.yandex.ru';

    #[Override]
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

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray($token, $this->endpoint, authScheme: 'OAuth');
    }

    #[Override]
    public function getButtonClass(): string
    {
        return 'btn btn-dark bi';
    }

    #[Override]
    public function getName(): string
    {
        return 'yandex';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Yandex';
    }

    #[Override]
    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
    }

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    #[Override]
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    /**
     * @see https://oauth.yandex.com/client/<client_id>/info
     * @see https://yandex.com/dev/id/doc/en/user-information#common
     * @return string
     *
     * @psalm-return 'login:info'
     */
    #[Override]
    protected function getDefaultScope(): string
    {
        return 'login:info';
    }
}
