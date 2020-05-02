<?php

namespace Yiisoft\Yii\AuthClient\Clients;

use Yiisoft\Yii\AuthClient\OAuth1;

/**
 * Twitter allows authentication via Twitter OAuth.
 *
 * In order to use Twitter OAuth you must register your application at <https://dev.twitter.com/apps/new>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'twitter' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\Twitter::class,
 *                 'attributeParams' => [
 *                     'include_email' => 'true'
 *                 ],
 *                 'consumerKey' => 'twitter_consumer_key',
 *                 'consumerSecret' => 'twitter_consumer_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * > Note: some auth workflows provided by Twitter, such as [application-only authentication](https://dev.twitter.com/oauth/application-only),
 *   uses OAuth 2 protocol and thus are impossible to be used with this class. You should use [[TwitterOAuth2]] for these.
 *
 * @see TwitterOAuth2
 * @see https://apps.twitter.com/
 * @see https://dev.twitter.com/
 */
class Twitter extends OAuth1
{
    public $authUrl = 'https://api.twitter.com/oauth/authenticate';
    public $requestTokenUrl = 'https://api.twitter.com/oauth/request_token';
    public $requestTokenMethod = 'POST';
    public $accessTokenUrl = 'https://api.twitter.com/oauth/access_token';
    public $accessTokenMethod = 'POST';
    public $endpoint = 'https://api.twitter.com/1.1';
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
     * @see https://dev.twitter.com/rest/reference/get/account/verify_credentials
     */
    public $attributeParams = [];


    protected function initUserAttributes()
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
