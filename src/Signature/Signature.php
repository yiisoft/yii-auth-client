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
     * Verifies given OAuth request.
     *
     * @param string $signature signature to be verified.
     * @param string $baseString signature base string.
     * @param string $key signature key.
     *
     * @return bool success.
     */
    public function verify(string $signature, string $baseString, string $key): bool
    {
        $expectedSignature = $this->generateSignature($baseString, $key);
        /**
         * @infection-ignore-all
         * `||` vs `&&` only produce different return values when the two conditions disagree, which
         * for empty() on strings requires $signature and $expectedSignature to be unequal to begin
         * with (empty() is true only for '' and '0'). Whenever exactly one is empty(), the two strings
         * are therefore never strcmp()-equal, so the strcmp() check below always falls through to the
         * same `false` result the early return would have given — behaviorally unobservable.
         */
        if (empty($signature) || empty($expectedSignature)) {
            return false;
        }

        return strcmp($expectedSignature, $signature) === 0;
    }

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
