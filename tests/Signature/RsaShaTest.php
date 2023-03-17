<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Signature\RsaSha;

use function dirname;

class RsaShaTest extends TestCase
{
    /**
     * Data provider for {@see testGetName()}
     *
     * @return array test data
     */
    public function dataProviderGetName(): array
    {
        return [
            [OPENSSL_ALGO_SHA1, 'RSA-SHA1'],
            [OPENSSL_ALGO_SHA256, 'RSA-SHA256'],
            ['sha256', 'RSA-SHA256'],
        ];
    }

    /**
     * @dataProvider dataProviderGetName
     *
     * @param $algorithm
     * @param $expectedName
     */
    public function testGetName($algorithm, $expectedName): void
    {
        $signatureMethod = new RsaSha($algorithm);
        $this->assertEquals($expectedName, $signatureMethod->getName());
    }

    public function testGenerateSignature(): void
    {
//        if (PHP_VERSION_ID >= 80000) {
//            $this->markTestSkipped('The test should be fixed in PHP 8.0.');
//        }

        $signatureMethod = new RsaSha(OPENSSL_ALGO_SHA1);
        $signatureMethod->setPrivateCertificateFile(dirname(__DIR__, 1) . '/Data/private.key');
        $signatureMethod->setPublicCertificateFile(dirname(__DIR__, 1) . '/Data/public.key');

        $baseString = 'test_base_string';
        $key = 'test_key';

        $signature = $signatureMethod->generateSignature($baseString, $key);
        $this->assertNotEmpty($signature, 'Unable to generate signature!');
    }

    /**
     * @depends testGenerateSignature
     */
    public function testVerify(): void
    {
        $signatureMethod = new RsaSha(OPENSSL_ALGO_SHA1);
        $signatureMethod->setPrivateCertificateFile(dirname(__DIR__, 1) . '/Data/private.key');
        $signatureMethod->setPublicCertificateFile(dirname(__DIR__, 1) . '/Data/public.key');

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

    public function testInitPrivateCertificate(): void
    {
        $signatureMethod = new RsaSha(OPENSSL_ALGO_SHA1);

        $certificateFileName = __FILE__;
        $signatureMethod->setPrivateCertificateFile($certificateFileName);
        $this->assertEquals(
            file_get_contents($certificateFileName),
            $signatureMethod->getPrivateCertificate(),
            'Unable to fetch private certificate from file!'
        );
    }

    public function testInitPublicCertificate(): void
    {
        $signatureMethod = new RsaSha(OPENSSL_ALGO_SHA1);

        $certificateFileName = __FILE__;
        $signatureMethod->setPublicCertificateFile($certificateFileName);
        $this->assertStringEqualsFile(
            $certificateFileName,
            $signatureMethod->getPublicCertificate(),
            'Unable to fetch public certificate from file!'
        );
    }
}
