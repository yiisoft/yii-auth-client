<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuthToken;

interface FacebookInterface
{
    public function getCurrentUserJsonArray(OAuthToken $token): array;

    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface;

    public function fetchAccessToken(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken;

    public function exchangeAccessToken(OAuthToken $token): OAuthToken;

    public function fetchClientAuthCode(
        ServerRequestInterface $incomingRequest,
        OAuthToken $token = null,
        array $params = []
    ): string;

    public function fetchClientAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken;

    public function getName(): string;

    public function getTitle(): string;
}