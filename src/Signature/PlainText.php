<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * PlainText represents 'PLAINTEXT' signature method.
 */
final class PlainText extends Signature
{
    public function getName(): string
    {
        return 'PLAINTEXT';
    }

    public function generateSignature(string $baseString, string $key): string
    {
        return $key;
    }
}
