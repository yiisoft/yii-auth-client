<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for OAuth2 client functionality.
 */
interface OAuth2Interface extends OAuthInterface
{
    public function setClientId(string $clientId): void;

    public function getClientId(): string;

    public function setClientSecret(string $clientSecret): void;

    public function getClientSecret(): string;

    public function setAuthParams(array $authParams): void;

    public function getAuthParams(): array;

    public function getCurrentUserJsonArray(OAuthToken $oauthToken): array;

    public function getOauth2ReturnUrl(): string;

    public function setEnvironment(string $devOrProd): void;

    public function setOauth2ReturnUrl(string $returnUrl): void;

    public function getTokenUrl(): string;

    public function setLogo(?string $logo): void;

    public function getLogo(): ?string;

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string;

    public function getSessionAuthState(): mixed;

    public function fetchAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = [],
    ): OAuthToken;

    public function fetchAccessTokenWithCodeVerifier(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = [],
    ): OAuthToken;
}
