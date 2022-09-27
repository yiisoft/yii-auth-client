<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

use Yiisoft\Yii\AuthClient\Exception\NotSupportedException;

use function function_exists;

/**
 * HmacSha represents 'HMAC SHA' signature method.
 *
 * > **Note:** This class requires PHP "Hash" extension(<http://php.net/manual/en/book.hash.php>).
 */
final class HmacSha extends Signature
{
    public function __construct(/**
     * @var string hash algorithm, e.g. `sha1`, `sha256` and so on.
     *
     * @link http://php.net/manual/ru/function.hash-algos.php
     */
    private string $algorithm
    )
    {
        if (!function_exists('hash_hmac')) {
            throw new NotSupportedException('PHP "Hash" extension is required.');
        }
    }

    public function getName(): string
    {
        return 'HMAC-' . strtoupper($this->algorithm);
    }

    public function generateSignature(string $baseString, string $key): string
    {
        return base64_encode(hash_hmac($this->algorithm, $baseString, $key, true));
    }
}
