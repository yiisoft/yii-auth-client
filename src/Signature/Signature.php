<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * BaseMethod is a base class for the OAuth signature methods.
 */
abstract class Signature
{
    /**
     * Return the canonical name of the Signature Method.
     *
     * @return string method name.
     */
    abstract public function getName(): string;

    /**
     * Generates OAuth request signature.
     *
     * @param string $baseString signature base string.
     * @param string $key signature key.
     *
     * @return string signature string.
     */
    abstract public function generateSignature(string $baseString, string $key): string;
}
