<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * TwitterOAuth2 allows authentication via Twitter OAuth 2.
 *
 * Note, that at the time these docs are written, Twitter does not provide full support for OAuth 2 protocol.
 * It is supported only for [application-only authentication](https://dev.twitter.com/oauth/application-only) workflow.
 * Thus only {@see authenticateClient()} method of this class has a practical usage.
 *
 * Any authentication attempt on behalf of the end-user will fail for this client. You should use {@see Twitter} class for
 * this workflow.
 *
 * @see Twitter
 * @link https://dev.twitter.com/
 */
final class TwitterOAuth2 extends OAuth2
{
    protected string $authUrl = 'https://api.twitter.com/oauth2/authenticate';
    protected string $tokenUrl = 'https://api.twitter.com/oauth2/token';
    protected string $endpoint = 'https://api.twitter.com/1.1';

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        $accessToken = $accessToken->getToken();
        if (null!==$accessToken) {
            return $request->withHeader('Authorization', 'Bearer ' . $accessToken);
        }
        return $request;
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'twitter'
     */
    public function getName(): string
    {
        return 'twitter';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'Twitter'
     */
    public function getTitle(): string
    {
        return 'Twitter';
    }

    protected function initUserAttributes(): array
    {
        return $this->api('account/verify_credentials.json', 'GET');
    }
}
