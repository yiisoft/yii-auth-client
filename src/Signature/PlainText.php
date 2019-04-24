<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Signature;

/**
 * PlainText represents 'PLAINTEXT' signature method.
 */
class PlainText extends BaseMethod
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'PLAINTEXT';
    }

    /**
     * {@inheritdoc}
     */
    public function generateSignature(string $baseString, string $key): string
    {
        return $key;
    }
}
