<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * BaseMethod is a base class for the OAuth signature methods.
 */
abstract class BaseMethod
{
    /**
     * Return the canonical name of the Signature Method.
     * @return string method name.
     */
    abstract public function getName(): string;

    /**
     * Generates OAuth request signature.
     * @param string $baseString signature base string.
     * @param string $key signature key.
     * @return string signature string.
     */
    abstract public function generateSignature(string $baseString, string $key): string;

    /**
     * Verifies given OAuth request.
     * @param string $signature signature to be verified.
     * @param string $baseString signature base string.
     * @param string $key signature key.
     * @return bool success.
     */
    public function verify(string $signature, string $baseString, string $key): bool
    {
        $expectedSignature = $this->generateSignature($baseString, $key);
        if (empty($signature) || empty($expectedSignature)) {
            return false;
        }

        return (strcmp($expectedSignature, $signature) === 0);
    }
}
