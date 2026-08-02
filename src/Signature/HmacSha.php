<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * HmacSha represents 'HMAC SHA' signature method.
 *
 * Unlike {@see RsaSha} (which depends on the optional OpenSSL extension), this class has no runtime
 * dependency to guard: the "Hash" extension providing `hash_hmac()` has been an always-enabled core
 * extension since PHP 7.4 and cannot be excluded from a build.
 */
final class HmacSha extends Signature
{
    /**
     * @param string $algorithm hash algorithm, e.g. `sha1`, `sha256` and so on.
     *
     * @link https://php.net/manual/ru/function.hash-algos.php
     */
    public function __construct(
        private readonly string $algorithm,
    ) {}

    public function getName(): string
    {
        return 'HMAC-' . strtoupper($this->algorithm);
    }

    public function generateSignature(string $baseString, string $key): string
    {
        return base64_encode(hash_hmac($this->algorithm, $baseString, $key, true));
    }
}
