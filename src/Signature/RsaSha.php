<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Signature;

use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\Exception\NotSupportedException;

use function function_exists;
use function is_int;

/**
 * RsaSha1 represents 'SHAwithRSA' (also known as RSASSA-PKCS1-V1_5-SIGN with the SHA hash) signature method.
 *
 * > **Note:** This class requires PHP "OpenSSL" extension({@link http://php.net/manual/en/book.openssl.php}).
 */
final class RsaSha extends BaseMethod
{
    /**
     * @var string path to the file, which holds private key certificate.
     */
    private string $privateCertificateFile;
    /**
     * @var string path to the file, which holds public key certificate.
     */
    private string $publicCertificateFile;
    /**
     * @var int|string signature hash algorithm, e.g. `OPENSSL_ALGO_SHA1`, `OPENSSL_ALGO_SHA256` and so on.
     * @link http://php.net/manual/en/openssl.signature-algos.php
     */
    private $algorithm;

    /**
     * @var string OpenSSL private key certificate content.
     * This value can be fetched from file specified by [[privateCertificateFile]].
     */
    private string $privateCertificate;
    /**
     * @var string OpenSSL public key certificate content.
     * This value can be fetched from file specified by [[publicCertificateFile]].
     */
    private string $publicCertificate;


    public function __construct($algorithm = null)
    {
        $this->algorithm = $algorithm;

        if (!function_exists('openssl_sign')) {
            throw new NotSupportedException('PHP "OpenSSL" extension is required.');
        }
    }

    /**
     * @param string $publicCertificate public key certificate content.
     */
    public function setPublicCertificate($publicCertificate): void
    {
        $this->publicCertificate = $publicCertificate;
    }

    /**
     * @return string public key certificate content.
     */
    public function getPublicCertificate(): string
    {
        if ($this->publicCertificate === null) {
            $this->publicCertificate = $this->initPublicCertificate();
        }

        return $this->publicCertificate;
    }

    /**
     * @param string $privateCertificate private key certificate content.
     */
    public function setPrivateCertificate($privateCertificate): void
    {
        $this->privateCertificate = $privateCertificate;
    }

    /**
     * @return string private key certificate content.
     */
    public function getPrivateCertificate(): string
    {
        if ($this->privateCertificate === null) {
            $this->privateCertificate = $this->initPrivateCertificate();
        }

        return $this->privateCertificate;
    }

    public function getName(): string
    {
        if (is_int($this->algorithm)) {
            $constants = get_defined_constants(true);
            if (isset($constants['openssl'])) {
                foreach ($constants['openssl'] as $name => $value) {
                    if (strpos($name, 'OPENSSL_ALGO_') !== 0) {
                        continue;
                    }
                    if ($value === $this->algorithm) {
                        $algorithmName = substr($name, strlen('OPENSSL_ALGO_'));
                        break;
                    }
                }
            }

            if (!isset($algorithmName)) {
                throw new InvalidConfigException("Unable to determine name of algorithm '{$this->algorithm}'");
            }
        } else {
            $algorithmName = strtoupper($this->algorithm);
        }
        return 'RSA-' . $algorithmName;
    }

    /**
     * Creates initial value for [[publicCertificate]].
     * This method will attempt to fetch the certificate value from [[publicCertificateFile]] file.
     * @return string public certificate content.
     * @throws InvalidConfigException on failure.
     */
    protected function initPublicCertificate(): string
    {
        if (!empty($this->publicCertificateFile)) {
            if (!file_exists($this->publicCertificateFile)) {
                throw new InvalidConfigException(
                    "Public certificate file '{$this->publicCertificateFile}' does not exist!"
                );
            }
            return file_get_contents($this->publicCertificateFile);
        }
        return '';
    }

    /**
     * Creates initial value for [[privateCertificate]].
     * This method will attempt to fetch the certificate value from [[privateCertificateFile]] file.
     * @return string private certificate content.
     * @throws InvalidConfigException on failure.
     */
    protected function initPrivateCertificate(): string
    {
        if (!empty($this->privateCertificateFile)) {
            if (!file_exists($this->privateCertificateFile)) {
                throw new InvalidConfigException(
                    "Private certificate file '{$this->privateCertificateFile}' does not exist!"
                );
            }
            return file_get_contents($this->privateCertificateFile);
        }
        return '';
    }

    public function generateSignature(string $baseString, string $key): string
    {
        $privateCertificateContent = $this->getPrivateCertificate();
        // Pull the private key ID from the certificate
        $privateKeyId = openssl_pkey_get_private($privateCertificateContent);
        // Sign using the key
        openssl_sign($baseString, $signature, $privateKeyId, $this->algorithm);
        // Release the key resource
        openssl_free_key($privateKeyId);

        return base64_encode($signature);
    }

    public function verify(string $signature, string $baseString, string $key): bool
    {
        $decodedSignature = base64_decode($signature);
        // Fetch the public key cert based on the request
        $publicCertificate = $this->getPublicCertificate();
        // Pull the public key ID from the certificate
        $publicKeyId = openssl_pkey_get_public($publicCertificate);
        // Check the computed signature against the one passed in the query
        $verificationResult = openssl_verify($baseString, $decodedSignature, $publicKeyId, $this->algorithm);
        // Release the key resource
        openssl_free_key($publicKeyId);

        return ($verificationResult == 1);
    }
}
