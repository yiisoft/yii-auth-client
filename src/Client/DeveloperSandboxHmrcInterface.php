<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuthToken;

interface DeveloperSandboxHmrcInterface
{
    public function setEnvironment(string $environment = 'dev'): void;
    public function getEnvironment(): string;
    public function getApiBaseUrl1(): string;
    public function getApiBaseUrl2(): string;
    public function clientConnectionMethods(): array;
    public function getCurrentUserJsonArray(OAuthToken $token): array;
    public function createTestUserIndividual(OAuthToken $token, array $requestBody): array;
    public function getTestUserArray(): array;
    public function getName(): string;
    public function getTitle(): string;
    public function getAuthorizedIpAddressEndpoints(): array;
}