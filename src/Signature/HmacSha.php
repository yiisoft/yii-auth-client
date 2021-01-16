<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

use function function_exists;

use Yiisoft\Yii\AuthClient\Exception\NotSupportedException;

/**
 * HmacSha represents 'HMAC SHA' signature method.
 *
 * > **Note:** This class requires PHP "Hash" extension(<http://php.net/manual/en/book.hash.php>).
 */
final class HmacSha extends AbstractSignature
{
    /**
     * @var string hash algorithm, e.g. `sha1`, `sha256` and so on.
     *
     * @link http://php.net/manual/ru/function.hash-algos.php
     */
    private string $algorithm;

    public function __construct(string $algorithm)
    {
        if (!function_exists('hash_hmac')) {
            throw new NotSupportedException('PHP "Hash" extension is required.');
        }

        $this->algorithm = $algorithm;
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
