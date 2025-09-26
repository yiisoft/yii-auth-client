<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tested 09/01/2025
 * @see https://learn.microsoft.com/en-gb/entra/identity/authentication/how-to-authentication-methods-manage?WT.mc_id=Portal-Microsoft_AAD_IAM
 * Note if you are to use this client, you will have to migrate to the converged Authentication methods policy.
 * Please migrate your authentication methods off the legacy MFA and SSPR policies by September 2025 to avoid any service impact.
 *
 * MicrosoftOnline allows authentication via the Microsoft Identity Platform.
 *
 * In order to use the Microsoft Identity Platform, you must register your application at
 * <https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize>
 *
 * https://learn.microsoft.com/en-us/azure/active-directory-b2c/tutorial-register-applications
 *
 * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
 */
final class MicrosoftOnline extends OAuth2
{
    /**
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#protocol-details
     */
    protected string $authUrl = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize';

    protected string $tokenUrl = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token';

    protected string $endpoint = 'https://graph.microsoft.com/v1.0/me';

    /**
     * tenant can be one of 'common', 'organisation', 'consumers', or the actual TenantID.
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#request-an-authorization-code
     */
    protected string $tenant = 'common';

    public function setTenant(string $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }

    #[\Override]
    public function setAuthUrl(string $authUrl): void
    {
        $this->authUrl = $authUrl;
    }

    public function getAuthUrlWithTenantInserted(string $tenant): string
    {
        return 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize';
    }

    #[\Override]
    public function setTokenUrl(string $tokenUrl): void
    {
        $this->tokenUrl = $tokenUrl;
    }

    public function getTokenUrlWithTenantInserted(string $tenant): string
    {
        return 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token';
    }

    /**
     * Fetch current user information using PSR-18 HTTP Client and PSR-17 Request Factory.
     *
     * @param OAuthToken $token
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     * @return array
     */
    public function getCurrentUserJsonArray(
        OAuthToken $token,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): array {
        $tokenString = (string)$token->getParam('access_token');
        if (strlen($tokenString) === 0) {
            return [];
        }

        $request = $requestFactory->createRequest('GET', 'https://graph.microsoft.com/v1.0/me')
            ->withHeader('Authorization', 'Bearer ' . $tokenString)
            ->withHeader('Content-Type', 'application/json');

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (strlen($body) > 0) {
                return (array)json_decode($body, true);
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            // Use $this->httpClient and $this->requestFactory from the parent OAuth2 class
            return $this->getCurrentUserJsonArray($token, $this->httpClient, $this->requestFactory);
        }
        return [];
    }

    #[\Override]
    public function getName(): string
    {
        return 'microsoftonline';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'MicrosoftOnline';
    }

    #[\Override]
    public function getButtonClass(): string
    {
        return 'btn btn-warning bi bi-microsoft';
    }

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    #[\Override]
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    /**
     * Purpose: Use this scope to be able to get the User's id and to build a suitable login using a sub string of the user id
     * @return string
     *
     * @psalm-return 'offline_access User.Read'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'offline_access User.Read';
    }
}
