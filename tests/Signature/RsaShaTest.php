<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\Signature\RsaSha;

final class RsaShaTest extends TestCase
{
    private function createSignature(): RsaSha
    {
        $signature = new RsaSha('SHA1');
        $signature->setPrivateCertificateFile(__DIR__ . '/../Data/private.key');
        $signature->setPublicCertificateFile(__DIR__ . '/../Data/public.key');

        return $signature;
    }

    public function testGetName(): void
    {
        $signature = new RsaSha('SHA1');

        $name = $signature->getName();

        $this->assertSame('RSA-SHA1', $name);
    }

    public function testGetNameUppercasesLowercaseAlgorithm(): void
    {
        $signature = new RsaSha('sha1');

        $name = $signature->getName();

        $this->assertSame('RSA-SHA1', $name);
    }

    public function testGenerateSignature(): void
    {
        $signature = $this->createSignature();

        $generated = $signature->generateSignature('base-string', 'unused');

        $this->assertNotSame('', $generated);
        $this->assertNotFalse(base64_decode($generated, true));
    }

    public function testVerifyValidSignature(): void
    {
        $signature = $this->createSignature();
        $generated = $signature->generateSignature('base-string', 'unused');

        $isValid = $signature->verify($generated, 'base-string', 'unused');

        $this->assertTrue($isValid);
    }

    public function testVerifyRejectsTamperedBaseString(): void
    {
        $signature = $this->createSignature();
        $generated = $signature->generateSignature('base-string', 'unused');

        $isValid = $signature->verify($generated, 'tampered-string', 'unused');

        $this->assertFalse($isValid);
    }

    public function testGetPrivateCertificateThrowsExceptionOnMissingFile(): void
    {
        $signature = new RsaSha('SHA1');
        $signature->setPrivateCertificateFile(__DIR__ . '/../Data/non-existing.key');

        $this->expectException(InvalidConfigException::class);

        $signature->getPrivateCertificate();
    }

    public function testGetPublicCertificateThrowsExceptionOnMissingFile(): void
    {
        $signature = new RsaSha('SHA1');
        $signature->setPublicCertificateFile(__DIR__ . '/../Data/non-existing.key');

        $this->expectException(InvalidConfigException::class);

        $signature->getPublicCertificate();
    }
}
