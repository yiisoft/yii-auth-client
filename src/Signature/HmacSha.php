<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Signature;

use yii\exceptions\NotSupportedException;

/**
 * HmacSha represents 'HMAC SHA' signature method.
 *
 * > **Note:** This class requires PHP "Hash" extension(<http://php.net/manual/en/book.hash.php>).
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1.3
 */
class HmacSha extends BaseMethod
{
    /**
     * @var string hash algorithm, e.g. `sha1`, `sha256` and so on.
     * @see http://php.net/manual/ru/function.hash-algos.php
     */
    private $algorithm;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $algorithm)
    {
        if (!\function_exists('hash_hmac')) {
            throw new NotSupportedException('PHP "Hash" extension is required.');
        }

        $this->algorithm = $algorithm;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'HMAC-' . strtoupper($this->algorithm);
    }

    /**
     * {@inheritdoc}
     */
    public function generateSignature(string $baseString, string $key): string
    {
        return base64_encode(hash_hmac($this->algorithm, $baseString, $key, true));
    }
}
