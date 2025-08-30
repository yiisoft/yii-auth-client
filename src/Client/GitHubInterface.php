<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;

interface GitHubInterface
{
    public function getCurrentUserJsonArray(OAuthToken $token): array;
    public function getName(): string;
    public function getTitle(): string;
}