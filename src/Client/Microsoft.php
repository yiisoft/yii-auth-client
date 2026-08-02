<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * Tested 09/01/2025
 * @see https://learn.microsoft.com/en-gb/entra/identity/authentication/how-to-authentication-methods-manage?WT.mc_id=Portal-Microsoft_AAD_IAM
 * Note if you are to use this client, you will have to migrate to the converged Authentication methods policy.
 * Please migrate your authentication methods off the legacy MFA and SSPR policies by September 2025 to avoid any service impact.
 *
 * Microsoft allows authentication via the Microsoft Identity Platform.
 *
 * In order to use the Microsoft Identity Platform, you must register your application at
 * <https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize>
 *
 * Example application configuration:
 *
 * ```php
 * // config/common/params.php
 * 'yiisoft/yii-auth-client' => [
 *     'clients' => [
 *         'microsoft' => [
 *             'class' => Yiisoft\Yii\AuthClient\Client\Microsoft::class,
 *             'clientId' => $_ENV['MICROSOFT_CLIENT_ID'],
 *             'clientSecret' => $_ENV['MICROSOFT_CLIENT_SECRET'],
 *         ],
 *     ],
 * ],
 * ```
 *
 * https://learn.microsoft.com/en-us/azure/active-directory-b2c/tutorial-register-applications
 *
 * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
 */
final class Microsoft extends OAuth2
{
    private const AUTH_URL_TEMPLATE = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize';
    private const TOKEN_URL_TEMPLATE = 'https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token';

    /**
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#protocol-details
     */
    protected string $authUrl = self::AUTH_URL_TEMPLATE;
    protected string $tokenUrl = self::TOKEN_URL_TEMPLATE;
    protected string $endpoint = 'https://graph.microsoft.com/v1.0/me';

    /**
     * tenant can be one of 'common', 'organisation', 'consumers', or the actual TenantID.
     * @see https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow#request-an-authorization-code
     */
    protected string $tenant = 'common';

    /**
     * Whether {@see setAuthUrl()}/{@see setTokenUrl()} were called with an explicit override, in which
     * case {@see substituteTenantPlaceholders()} must leave that URL alone instead of rebuilding it from
     * {@see tenant}.
     */
    private bool $authUrlOverridden = false;
    private bool $tokenUrlOverridden = false;

    public function setTenant(string $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }

    public function setAuthUrl(string $authUrl): void
    {
        $this->authUrl = $authUrl;
        $this->authUrlOverridden = true;
    }

    public function getAuthUrlWithTenantInserted(string $tenant): string
    {
        return str_replace('{$tenant}', $tenant, self::AUTH_URL_TEMPLATE);
    }

    public function setTokenUrl(string $tokenUrl): void
    {
        $this->tokenUrl = $tokenUrl;
        $this->tokenUrlOverridden = true;
    }

    public function getTokenUrlWithTenantInserted(string $tenant): string
    {
        return str_replace('{$tenant}', $tenant, self::TOKEN_URL_TEMPLATE);
    }

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
    {
        $this->substituteTenantPlaceholders();
        return parent::buildAuthUrl($incomingRequest, $params);
    }

    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        $this->substituteTenantPlaceholders();
        return parent::fetchAccessToken($incomingRequest, $authCode, $params);
    }

    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        $this->substituteTenantPlaceholders();
        return parent::refreshAccessToken($token);
    }

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array
    {
        return $this->fetchCurrentUserJsonArray(
            $oauthToken,
            $this->endpoint,
            ['Content-Type' => 'application/json'],
        );
    }

    public function getName(): string
    {
        return $this->name ?: 'microsoft';
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Microsoft';
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
     * Purpose: Use this scope to be able to get the User's id and to build a suitable login using a sub string of the user id
     * @return string
     *
     * @psalm-return 'offline_access User.Read'
     */
    protected function getDefaultScope(): string
    {
        return 'offline_access User.Read';
    }

    /**
     * Rebuilds {@see authUrl}/{@see tokenUrl} from the current {@see tenant}, unless either URL was
     * explicitly overridden via {@see setAuthUrl()}/{@see setTokenUrl()}. Runs on every call instead of
     * only while the `{$tenant}` placeholder is still present, so a later {@see setTenant()} call keeps
     * taking effect rather than being stuck with whichever tenant was substituted first.
     */
    private function substituteTenantPlaceholders(): void
    {
        if (!$this->authUrlOverridden) {
            $this->authUrl = $this->getAuthUrlWithTenantInserted($this->tenant);
        }
        if (!$this->tokenUrlOverridden) {
            $this->tokenUrl = $this->getTokenUrlWithTenantInserted($this->tenant);
        }
    }
}
