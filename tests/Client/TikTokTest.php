<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Yiisoft\Yii\AuthClient\Client\TikTok;
use Yiisoft\Yii\AuthClient\OAuth2;

final class TikTokTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(TikTok::class);
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('tiktok', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('TikTok', $client->getTitle());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('user.info.profile', $client->getScope());
    }
}
