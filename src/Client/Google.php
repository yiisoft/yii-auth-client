<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;

/**
 * Google allows authentication via Google OAuth2 using HTTP client. Here we are NOT using the alternative Client Libraries
 * namely @see https://developers.google.com/people/v1/libraries#php
 *
 * In order to use Google OAuth2 you must create a project at <https://console.cloud.google.com/cloud-resource-manager>
 * and setup its credentials at <https://console.cloud.google.com/apis/credentials?project=[yourProjectId]>.
 * @see Google+ Api is being shutdown https://developers.google.com/+/api-shutdown
 * @see Google People Api is being used instead https://developers.google.com/people/api/rest/v1/people/get
 */
class Google extends OAuth2
{
    protected string $authUrl = 'https://www.googleapis.com/auth';
    protected string $tokenUrl = 'https://www.googleapis.com/token';
    protected string $endpoint = 'https://www.googleapis.com/people/v1';

    /**
     * @return string service name.
     *
     * @psalm-return 'google'
     */
    public function getName(): string
    {
        return 'google';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'Google'
     */
    public function getTitle(): string
    {
        return 'Google';
    }

    /**
     * @return string
     * @see https://developers.google.com/people/api/rest/v1/people/get#authorization-scopes
     * @see https://developers.google.com/identity/protocols/oauth2/scopes#people
     * @see https://developers.google.com/resources/api-libraries/documentation/people/v1/php/latest/class-Google_Service_PeopleService.html
     * @see userinfo.profile https://www.googleapis.com/auth/userinfo.profile
     * @see userinfo.email https://www.googleapis.com/auth/userinfo.email
     * @psalm-return 'userinfo.profile userinfo.email'
     */
    protected function getDefaultScope(): string
    {
        return 'userinfo.profile userinfo.email';
    }

    /**
     * Previously:
     * @see https://www.googleapis.com/auth/people/me
     * ...which returns: 
     * 
     * ..."You are receiving this error either because 
     * your input OAuth2 scope name is invalid or 
     * it refers to a newer scope that is outside the domain of this legacy API.
     * This API was built at a time when the scope name format was not yet standardized. 
     * This is no longer the case and all valid scope names (both old and new) are catalogued at 
     * https://developers.google.com/identity/protocols/oauth2/scopes." 
     * 
     * Use that webpage to lookup (manually) the scope name associated with the API you are trying to call
     * and use it to craft your OAuth2 request.
     * 
     * @see https://developers.google.com/identity/protocols/oauth2/scopes#oauth2
     * 
     * ...which returns two scopes
     * 
     * 1. https://www.googleapis.com/auth/userinfo.email  See your Primary Google Email Address
     * 2. https://www.googleapis.com/auth/userinfo.profile  See your personal info, including any personal info you've made publicly available
     * 
     * @return array
     */
    protected function initUserAttributes(): array
    {
        return $this->api('auth', 'GET');
    }
}
