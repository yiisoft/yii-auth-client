<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * Google allows authentication via Google OAuth2 using HTTP client. Here we are NOT using the alternative Client Libraries
 * namely @see https://developers.google.com/people/v1/libraries#php
 * In order to use Google OAuth2 you must create a project at <https://console.cloud.google.com/cloud-resource-manager>
 * and setup its credentials at <https://console.cloud.google.com/apis/credentials?project=[yourProjectId]>.
 * Create an Oauth2 Web Application and record the resultant Client Id and Client Secret in e.g a .env file and insert your website's returnUrl e.g. https:\\example.com\callbackGoogle
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'google' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Google::class,
 *             'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
 *             'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
 *             'oauth2ReturnUrl' => 'https://example.com/auth/google',
 *         ],
 *     ],
 * ],
 * ```
 *
 * @see https://developers.google.com/oauthplayground
 * @see <https://console.cloud.google.com/welcome?project=[yourProjectId]>
 */
final class Google extends OAuth2
{
    protected string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenUrl = 'https://oauth2.googleapis.com/token';
    protected string $endpoint = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        return $this->fetchCurrentUserJsonArray(
            $token,
            'https://www.googleapis.com/oauth2/v2/userinfo',
        );
    }

    public function getName(): string
    {
        return $this->name ?: 'google';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Google';
    }

    public function getButtonClass(): string
    {
        return 'btn btn-primary bi bi-google';
    }

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
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    /**
     * @see https://www.googleapis.com/auth/userinfo.profile will output userinfo.profile
     * @see https://www.googleapis.com/auth/userinfo.email will output userinfo.email
     * @psalm-return 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email'
     */
    protected function getDefaultScope(): string
    {
        return 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email';
    }
}
