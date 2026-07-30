<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Widget\LogoRegistry;

final class LogoRegistryTest extends TestCase
{
    public function testGetLogoReturnsSvgForKnownClient(): void
    {
        $svg = LogoRegistry::getLogo('google');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('linearGradient', $svg);
    }

    public function testGetLogoReturnsNullForUnknownClient(): void
    {
        $svg = LogoRegistry::getLogo('unknown');

        $this->assertNull($svg);
    }

    public function testGetViewBoxReturnsViewBoxForKnownClient(): void
    {
        $viewBox = LogoRegistry::getViewBox('google');

        $this->assertNotNull($viewBox);
        $this->assertSame('0 0 268.152 273.883', $viewBox);
    }

    public function testGetViewBoxReturnsNullForUnknownClient(): void
    {
        $viewBox = LogoRegistry::getViewBox('unknown');

        $this->assertNull($viewBox);
    }

    public function testHasLogoReturnsTrueForKnownClient(): void
    {
        $has = LogoRegistry::hasLogo('github');

        $this->assertTrue($has);
    }

    public function testHasLogoReturnsFalseForUnknownClient(): void
    {
        $has = LogoRegistry::hasLogo('unknown');

        $this->assertFalse($has);
    }

    public function testGetClientNamesReturnsAllRegisteredClients(): void
    {
        $names = LogoRegistry::getClientNames();

        $this->assertIsArray($names);
        $this->assertContains('google', $names);
        $this->assertContains('github', $names);
        $this->assertContains('x', $names);
        $this->assertContains('facebook', $names);
        $this->assertContains('linkedin', $names);
        $this->assertContains('microsoft', $names);
        $this->assertContains('vkontakte', $names);
        $this->assertContains('tiktok', $names);
        $this->assertContains('yandex', $names);
        $this->assertCount(9, $names);
    }
}
