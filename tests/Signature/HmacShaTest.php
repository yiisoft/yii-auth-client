<?php

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use Yiisoft\Yii\AuthClient\Signature\HmacSha;

class HmacShaTest extends \yii\tests\TestCase
{
    public function testGenerateSignature()
    {
        $signatureMethod = new HmacSha('sha256');

        $baseString = 'test_base_string';
        $key = 'test_key';

        $signature = $signatureMethod->generateSignature($baseString, $key);
        $this->assertNotEmpty($signature, 'Unable to generate signature!');
    }
}
