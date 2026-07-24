<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Signature\PlainText;

final class PlainTextTest extends TestCase
{
    public function testGetName(): void
    {
        $signature = new PlainText();

        $name = $signature->getName();

        $this->assertSame('PLAINTEXT', $name);
    }

    public function testGenerateSignatureReturnsKeyAsIs(): void
    {
        $signature = new PlainText();

        $generated = $signature->generateSignature('base-string', 'the-key');

        $this->assertSame('the-key', $generated);
    }

    public function testVerifyValidSignature(): void
    {
        $signature = new PlainText();

        $isValid = $signature->verify('the-key', 'base-string', 'the-key');

        $this->assertTrue($isValid);
    }

    public function testVerifyInvalidSignature(): void
    {
        $signature = new PlainText();

        $isValid = $signature->verify('wrong-key', 'base-string', 'the-key');

        $this->assertFalse($isValid);
    }

    public function testVerifyReturnsFalseWhenBothSignatureAndKeyAreEmpty(): void
    {
        $signature = new PlainText();

        $isValid = $signature->verify('', 'base-string', '');

        $this->assertFalse($isValid);
    }
}
