<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

interface MicrosoftOnlineInterface extends OAuth2Interface
{
    public function setTenant(string $tenant): void;
    public function getTenant(): string;
    public function setAuthUrl(string $authUrl): void;
    public function getAuthUrlWithTenantInserted(string $tenant): string;
    public function setTokenUrl(string $tokenUrl): void;
    public function getTokenUrlWithTenantInserted(string $tenant): string;
    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array;
    public function getName(): string;
    public function getTitle(): string;
}