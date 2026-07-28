<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

use function sprintf;

/**
 * Google allows authentication via Google OAuth2 using HTTP client. Here we are NOT using the alternative Client Libraries
 * namely @see https://developers.google.com/people/v1/libraries#php
 * In order to use Google OAuth2 you must create a project at <https://console.cloud.google.com/cloud-resource-manager>
 * and setup its credentials at <https://console.cloud.google.com/apis/credentials?project=[yourProjectId]>.
 * Create an Oauth2 Web Application and record the resultant Client Id and Client Secret in e.g a .env file and insert your website's returnUrl e.g. https:\\example.com\callbackGoogle
 * @see Google+ Api is being shutdown https://developers.google.com/+/api-shutdown
 * @see https://developers.google.com/oauthplayground
 * @see <https://console.cloud.google.com/welcome?project=[yourProjectId]>
 */
final class Google extends OAuth2
{
    protected string $version = 'v2';
    protected string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenUrl = 'https://oauth2.googleapis.com/token';
    protected string $endpoint = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        $url = sprintf('https://www.googleapis.com/oauth2/%s/userinfo', $this->version);

        /**
         * @infection-ignore-all
         * $url above is always this same www.googleapis.com host, and PSR-7 implementations derive
         * the Host header from the request URI, so this explicit 'Host' item is redundant with (and
         * unobservably identical to) the auto-derived one.
         */
        $headers = [
            'Host' => 'www.googleapis.com',
            'Content-length' => '0',
        ];

        return $this->fetchCurrentUserJsonArray($token, $url, $headers);
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getTitle(): string
    {
        return 'Google';
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
