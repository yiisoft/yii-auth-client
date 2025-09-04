<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

interface XInterface extends OAuth2Interface
{
    public function getCurrentUserJsonArrayUsingCurl(OAuthToken $token): array;

    public function getName(): string;

    public function getTitle(): string;
}
