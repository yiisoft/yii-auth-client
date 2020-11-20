<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Signature\BaseMethod;

class BaseMethodTest extends TestCase
{
    /**
     * Creates test signature method instance.
     *
     * @return BaseMethod
     */
    protected function createTestSignatureMethod()
    {
        $signatureMethod = $this->getMockBuilder('\Yiisoft\Yii\AuthClient\Signature\BaseMethod')
            ->setMethods(['getName', 'generateSignature'])
            ->getMock();
        $signatureMethod->expects($this->any())->method('getName')->willReturn('testMethodName');
        $signatureMethod->expects($this->any())->method('generateSignature')->willReturn('testSignature');

        return $signatureMethod;
    }

    // Tests :

    public function testGenerateSignature()
    {
        $signatureMethod = $this->createTestSignatureMethod();

        $baseString = 'test_base_string';
        $key = 'test_key';

        $signature = $signatureMethod->generateSignature($baseString, $key);

        $this->assertNotEmpty($signature, 'Unable to generate signature!');
    }

    /**
     * @depends testGenerateSignature
     */
    public function testVerify()
    {
        $signatureMethod = $this->createTestSignatureMethod();

        $baseString = 'test_base_string';
        $key = 'test_key';
        $signature = 'unsigned';
        $this->assertFalse($signatureMethod->verify($signature, $baseString, $key), 'Unsigned signature is valid!');

        $generatedSignature = $signatureMethod->generateSignature($baseString, $key);
        $this->assertTrue(
            $signatureMethod->verify($generatedSignature, $baseString, $key),
            'Generated signature is invalid!'
        );
    }
}
