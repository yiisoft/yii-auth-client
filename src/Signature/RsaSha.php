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
 * > **Note:** This class requires PHP "OpenSSL" extension({@link https://php.net/manual/en/book.openssl.php}).
 */
final class RsaSha extends Signature
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
     *
     * @link https://php.net/manual/en/openssl.signature-algos.php
     */
    private $algorithm;

    /**
     * @var string|null OpenSSL private key certificate content.
     * This value can be fetched from file specified by {@see privateCertificateFile}.
     */
    private ?string $privateCertificate = null;
    /**
     * @var string|null OpenSSL public key certificate content.
     * This value can be fetched from file specified by {@see publicCertificateFile}.
     */
    private ?string $publicCertificate = null;

    public function __construct(string $algorithm = '')
    {
        if (!function_exists('openssl_sign')) {
            throw new NotSupportedException('PHP "OpenSSL" extension is required.');
        }
    }

    /**
     * @param string $publicCertificateFile public key certificate file.
     */
    public function setPublicCertificateFile(string $publicCertificateFile): void
    {
        $this->publicCertificateFile = $publicCertificateFile;
    }

    /**
     * @param string $privateCertificateFile private key certificate file.
     */
    public function setPrivateCertificateFile(string $privateCertificateFile): void
    {
        $this->privateCertificateFile = $privateCertificateFile;
    }

    #[\Override]
    public function getName(): string
    {
        if (is_int($this->algorithm)) {
            $constants = get_defined_constants(true);
            if (isset($constants['openssl'])) {
                foreach ($constants['openssl'] as $name => $value) {
                    if (!str_starts_with($name, 'OPENSSL_ALGO_')) {
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
        return 'RSA-' . (string) $algorithmName;
    }

    #[\Override]
    public function generateSignature(string $baseString, string $key): string
    {
        $privateCertificateContent = $this->getPrivateCertificate();

        // For PHP 8+, you can pass the PEM string directly to openssl_sign()
        openssl_sign($baseString, $signature, $privateCertificateContent, $this->algorithm);

        return base64_encode($signature);
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

    /**
     * Creates initial value for {@see privateCertificate}.
     * This method will attempt to fetch the certificate value from {@see privateCertificateFile} file.
     *
     * @throws InvalidConfigException on failure.
     *
     * @return string private certificate content.
     */
    protected function initPrivateCertificate(): string
    {
        if (!empty($this->privateCertificateFile)) {
            if (!file_exists($this->privateCertificateFile)) {
                throw new InvalidConfigException(
                    "Private certificate file '{$this->privateCertificateFile}' does not exist!"
                );
            }
            $privateCertificateFile = file_get_contents($this->privateCertificateFile);
            if ($privateCertificateFile === false) {
                throw new InvalidConfigException('Failed to fetch private certificate file');
            }
            return $privateCertificateFile;
        }
        return '';
    }

    #[\Override]
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
        if (PHP_MAJOR_VERSION < 8) {
            openssl_pkey_free($publicKeyId);
        }

        return $verificationResult === 1;
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
     * Creates initial value for {@see publicCertificate}.
     * This method will attempt to fetch the certificate value from {@see publicCertificateFile} file.
     *
     * @throws InvalidConfigException on failure.
     *
     * @return string public certificate content.
     */
    protected function initPublicCertificate(): string
    {
        $content = '';
        if (!empty($this->publicCertificateFile)) {
            if (!file_exists($this->publicCertificateFile)) {
                throw new InvalidConfigException(
                    "Public certificate file '{$this->publicCertificateFile}' does not exist!"
                );
            }
            $fp = fopen($this->publicCertificateFile, 'rb');

            $fgetsFp = fgets($fp);
            while (!feof($fp) && is_string($fgetsFp)) {
                $content .= $fgetsFp;
            }
            fclose($fp);
        }
        return $content;
    }
}
