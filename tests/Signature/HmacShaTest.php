<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Signature\HmacSha;

final class HmacShaTest extends TestCase
{
    public function testGetName(): void
    {
        $signature = new HmacSha('sha1');

        $name = $signature->getName();

        $this->assertSame('HMAC-SHA1', $name);
    }

    public function testGenerateSignature(): void
    {
        $signature = new HmacSha('sha1');

        $generated = $signature->generateSignature('base-string', 'key');

        $this->assertSame(base64_encode(hash_hmac('sha1', 'base-string', 'key', true)), $generated);
    }

    public function testGenerateSignatureIsDeterministic(): void
    {
        $signature = new HmacSha('sha256');

        $first = $signature->generateSignature('base-string', 'key');
        $second = $signature->generateSignature('base-string', 'key');

        $this->assertSame($first, $second);
    }

    public function testVerifyValidSignature(): void
    {
        $signature = new HmacSha('sha1');
        $generated = $signature->generateSignature('base-string', 'key');

        $isValid = $signature->verify($generated, 'base-string', 'key');

        $this->assertTrue($isValid);
    }

    public function testVerifyInvalidSignature(): void
    {
        $signature = new HmacSha('sha1');

        $isValid = $signature->verify('not-a-real-signature', 'base-string', 'key');

        $this->assertFalse($isValid);
    }
}
