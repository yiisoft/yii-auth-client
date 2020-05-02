<?php

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * PlainText represents 'PLAINTEXT' signature method.
 */
class PlainText extends BaseMethod
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
