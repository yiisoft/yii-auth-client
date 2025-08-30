<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;

interface VKontakteInterface
{
    public function step6GettingNewAccessTokenAfterPreviousExpires(
        string $refreshToken,
        string $clientId,
        string $deviceId,
        string $state
    ): mixed;

    public function step7TokenInvalidationUsingCurlWithClientId(OAuthToken $token, string $clientId): array;

    public function step8ObtainingUserDataArrayUsingCurlWithClientId(OAuthToken $token, string $clientId): array;

    public function step9GetPublicUserDataArrayUsingCurlWithClientId(OAuthToken $token, string $clientId): array;

    public function getName(): string;

    public function getTitle(): string;
}