<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Clients;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'linkedin' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\LinkedIn::class,
 *                 'clientId' => 'linkedin_client_id',
 *                 'clientSecret' => 'linkedin_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @see https://developer.linkedin.com/docs/oauth2
 * @see https://www.linkedin.com/secure/developer
 * @see https://developer.linkedin.com/docs/rest-api
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class LinkedIn extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
    /**
     * {@inheritdoc}
     */
    public $endpoint = 'https://api.linkedin.com/v1';
    /**
     * @var array list of attribute names, which should be requested from API to initialize user attributes.
     * @since 2.0.4
     */
    public $attributeNames = [
        'id',
        'email-address',
        'first-name',
        'last-name',
        'public-profile-url',
    ];

    protected function getDefaultScope(): string
    {
        return 'r_basicprofile r_emailaddress';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultNormalizeUserAttributeMap()
    {
        return [
            'email' => 'email-address',
            'first_name' => 'first-name',
            'last_name' => 'last-name',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function initUserAttributes()
    {
        return $this->api('people/~:(' . implode(',', $this->attributeNames) . ')', 'GET');
    }

    /**
     * {@inheritdoc}
     */
    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface
    {
        return RequestUtil::addParams($request, [
            'oauth2_access_token' => $accessToken->getToken()
        ]);
    }

    /**
     * @return string service name.
     */
    public function getName(): string
    {
        return 'linkedin';
    }

    /**
     * @return string service title.
     */
    public function getTitle(): string
    {
        return 'LinkedIn';
    }
}
