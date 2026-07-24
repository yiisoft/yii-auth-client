<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
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

    public function testGetNameWithIntAlgorithmResolvesOpensslConstantName(): void
    {
        $signature = new RsaSha(OPENSSL_ALGO_SHA256);

        $name = $signature->getName();

        $this->assertSame('RSA-SHA256', $name);
    }

    public function testGetNameThrowsForUnrecognizedIntAlgorithm(): void
    {
        $signature = new RsaSha(999999);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage("Unable to determine name of algorithm '999999'");

        $signature->getName();
    }

    public function testGetPrivateCertificateReturnsEmptyStringWhenFileNotSet(): void
    {
        $signature = new RsaSha('SHA1');

        $this->assertSame('', $signature->getPrivateCertificate());
    }

    /**
     * chmod(0000) only restricts reads on POSIX filesystems; Windows doesn't enforce Unix permission
     * bits the same way, so this simulated read failure is only reproducible on Linux/macOS/BSD.
     */
    #[RequiresOperatingSystemFamily('Linux')]
    public function testGetPrivateCertificateThrowsWhenFileCannotBeRead(): void
    {
        $unreadableFile = tempnam(sys_get_temp_dir(), 'rsasha-test-');
        $this->assertNotFalse($unreadableFile);
        chmod($unreadableFile, 0000);
        try {
            $signature = new RsaSha('SHA1');
            $signature->setPrivateCertificateFile($unreadableFile);

            $this->expectException(InvalidConfigException::class);
            $this->expectExceptionMessage('Failed to fetch private certificate file');

            @$signature->getPrivateCertificate();
        } finally {
            chmod($unreadableFile, 0644);
            unlink($unreadableFile);
        }
    }

    public function testVerifyReturnsFalseWhenPublicCertificateIsNotAValidKey(): void
    {
        $signature = new RsaSha('SHA1');

        $isValid = @$signature->verify('signature', 'base-string', 'unused');

        $this->assertFalse($isValid);
    }
}
