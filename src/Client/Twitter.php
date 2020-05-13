<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth1;

/**
 * Twitter allows authentication via Twitter OAuth.
 *
 * In order to use Twitter OAuth you must register your application at <https://dev.twitter.com/apps/new>.
 *
 * > Note: some auth workflows provided by Twitter, such as [application-only authentication](https://dev.twitter.com/oauth/application-only),
 *   uses OAuth 2 protocol and thus are impossible to be used with this class. You should use {@see TwitterOAuth2} for these.
 *
 * @see TwitterOAuth2
 * @link https://apps.twitter.com/
 * @link https://dev.twitter.com/
 */
final class Twitter extends OAuth1
{
    private string $authUrl = 'https://api.twitter.com/oauth/authenticate';
    private string $requestTokenUrl = 'https://api.twitter.com/oauth/request_token';
    private string $requestTokenMethod = 'POST';
    private string $accessTokenUrl = 'https://api.twitter.com/oauth/access_token';
    private string $accessTokenMethod = 'POST';
    private string $endpoint = 'https://api.twitter.com/1.1';
    /**
     * @var array list of extra parameters, which should be used, while requesting user attributes from Twitter API.
     * For example:
     *
     * ```php
     * [
     *     'include_email' => 'true'
     * ]
     * ```
     *
     * @link https://dev.twitter.com/rest/reference/get/account/verify_credentials
     */
    private array $attributeParams = [];

    protected function initUserAttributes(): array
    {
        return $this->api('account/verify_credentials.json', 'GET', $this->attributeParams);
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'twitter';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'Twitter';
    }
}
