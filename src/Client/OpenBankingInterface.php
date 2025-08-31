<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

interface OpenBankingInterface extends OAuth2Interface
{
    public function setAuthUrl(string $authUrl): void;
    public function setTokenUrl(string $tokenUrl): void;
    public function setScope(?string $scope): void;
    public function getName(): string;
    public function getTitle(): string;
    public function getAuthUrl(): string;
    public function getTokenUrl(): string;
    public function getScope(): string;
    public function fetchAccessTokenWithCurlAndCodeVerifier(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken;
    public function decodeIdToken(string $idToken): array;
}