<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;

interface XInterface
{
    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array;
    public function getName(): string;
    public function getTitle(): string;
}