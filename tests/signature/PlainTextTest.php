<?php

namespace yii\authclient\tests\signature;

use yii\authclient\signature\PlainText;

class PlainTextTest extends \yii\tests\TestCase
{
    public function testGenerateSignature()
    {
        $signatureMethod = new PlainText();

        $baseString = 'test_base_string';
        $key = 'test_key';

        $signature = $signatureMethod->generateSignature($baseString, $key);
        $this->assertNotEmpty($signature, 'Unable to generate signature!');
    }
}
