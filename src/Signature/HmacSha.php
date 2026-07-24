<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

use Override;
use Yiisoft\Yii\AuthClient\Exception\NotSupportedException;

use function function_exists;

/**
 * HmacSha represents 'HMAC SHA' signature method.
 *
 * > **Note:** This class requires PHP "Hash" extension(<https://php.net/manual/en/book.hash.php>).
 */
final class HmacSha extends Signature
{
    /**
     * @param string $algorithm hash algorithm, e.g. `sha1`, `sha256` and so on.
     *
     * @link https://php.net/manual/ru/function.hash-algos.php
     */
    public function __construct(
        private readonly string $algorithm
    ) {
        // @codeCoverageIgnoreStart
        /**
         * @infection-ignore-all
         * The "Hash" extension is bundled and enabled by default since PHP 7.4, so this guard
         * is unreachable in any environment capable of running this test suite.
         */
        if (!function_exists('hash_hmac')) {
            throw new NotSupportedException('PHP "Hash" extension is required.');
        }
        // @codeCoverageIgnoreEnd
    }

    #[Override]
    public function getName(): string
    {
        return 'HMAC-' . strtoupper($this->algorithm);
    }

    #[Override]
    public function generateSignature(string $baseString, string $key): string
    {
        return base64_encode(hash_hmac($this->algorithm, $baseString, $key, true));
    }
}
