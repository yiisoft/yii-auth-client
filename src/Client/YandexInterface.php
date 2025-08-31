<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

interface YandexInterface extends OAuth2Interface
{
    public function applyAccessTokenToRequest(RequestInterface $request, OAuthToken $accessToken): RequestInterface;
    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array;
    public function getName(): string;
    public function getTitle(): string;
}