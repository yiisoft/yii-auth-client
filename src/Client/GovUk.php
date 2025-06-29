<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use phpseclib3\Crypt\PublicKeyLoader;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use InvalidArgumentException;
use RuntimeException;
use Exception;

/**
 * As at: 24/04/2025
 * @see Json Gov Uk Endpoints https://oidc.integration.account.gov.uk/.well-known/openid-configuration
 * @see Documentation Updates https://tech-docs.account.gov.uk/#documentation-updates
 * @see Technical Documents https://github.com/govuk-one-login/tech-docs
 */
class GovUk extends OAuth2
{
    protected string $authUrl = 'https://oidc.integration.account.gov.uk/authorize';
    protected string $jwksUrl = 'https://oidc.integration.account.gov.uk/.well-known/jwks.json';
    protected string $tokenUrl = 'https://oidc.integration.account.gov.uk/token';
    protected string $registrationUrl = 'https://oidc.integration.account.gov.uk/register';
    protected string $userInfoEndPoint = 'https://oidc.integration.account.gov.uk/userinfo';
    protected string $sessionEndEndPoint = 'https://oidc.integration.account.gov.uk/logout';
    protected string $endPoint = 'https://oidc.integration.account.gov.uk';

    /**
     * Beta: This function is untested.
     * @see https://www.sign-in.service.gov.uk/register
     * @see oidc vs oauth2
     * @param OAuthToken $token
     * @return array
     */
    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        /**
         * e.g. '{all the params}' => ''
         * @var array $params
         */
        $tokenParams = $token->getParams();

        /**
         * e.g. convert the above key, namely '{all the params}', into an array
         * @var array $tokenArray
         */
        $tokenArray = array_keys($tokenParams);

        /**
         * @var string $jsonString
         */
        $jsonString = $tokenArray[0];

        /**
         * @var array $finalArray
         */
        $finalArray = json_decode($jsonString, true);

        /**
         * @var string $tokenString
         */
        $tokenString = $finalArray['access_token'] ?? '';

        if (strlen($tokenString) > 0) {
            $request = $this->createRequest('GET', $this->userInfoEndPoint);

            $request = RequestUtil::addHeaders(
                $request,
                [
                    'Authorization' => 'Bearer ' . $tokenString,

                    'Host' => $this->endPoint,

                    'Content-length' => 0,
                ]
            );

            $response = $this->sendRequest($request);

            return (array)json_decode($response->getBody()->getContents(), true);
        }

        return [];
    }

    /**
     * Converts a Base64 URL-safe string to a standard Base64 string.
     *
     * @param string $input The Base64 URL-safe string to convert.
     * @return string The standard Base64 string.
     */
    protected function base64UrlToBase64(string $input): string
    {
        return strtr($input, '-_', '+/');
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'govuk'
     */
    public function getName(): string
    {
        return 'govuk';
    }

    /**
     * @return string service title.
     *
     * @psalm-return 'GovUk'
     */
    public function getTitle(): string
    {
        return 'GovUk';
    }

    /**
     * Fetches the JSON Web Key Set (JWKS) from the configured URL.
     *
     * @throws RuntimeException If fetching or decoding the JWKS fails.
     * @return array The decoded JWKS as an associative array.
     */
    protected function fetchJWKS(): array
    {
        // Fetch the JWKS JSON from the URL
        $json = file_get_contents($this->jwksUrl);

        if ($json === false) {
            throw new RuntimeException('Failed to fetch JWKS from URL: ' . $this->jwksUrl);
        }

        // Decode the JSON
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to decode JWKS JSON from URL: ' . $this->jwksUrl);
        }

        return $decoded;
    }

    /**
     * e.g. Json Web Token $id_token eyJhbGciOiJSUzI1NiIsImtpZCI6IjE2YTI4In0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibm9uY2UiOiJhYjEyMyIsImV4cCI6MTYzNjg4MDAwMH0.SGVsbG8gdGhpcyBpcyBhIHNpZ25lZCBtZXNzYWdl
     * Split the id_token string into its three parts: header, payload (user information, nonce), and signature. Each part separated by a dot.
     */
    protected function splitIdTokenIntoJwtHeader(string $id_token): string
    {
        return $jwtHeader = explode('.', $id_token)[0];
    }

    /**
     * JWT => Json Web Token
     * e.g. [
     *          'alg' => 'RS256',
     *          'kid' => '16a28',
     *          'typ' => 'JWT'
     * ]
     *
     * Decodes a JWT header from Base64URL format into an associative array.
     *
     * @param string $jwtHeader The Base64URL-encoded JWT header string.
     * @throws InvalidArgumentException If the JWT header is invalid or cannot be decoded.
     * @return array The decoded JWT header as an associative array.
     */
    protected function decodeJwtHeaderIntoArray(string $jwtHeader): array
    {
        // Base64-decode the JWT header
        $decodedHeader = base64_decode($jwtHeader, true); // Use strict decoding

        if ($decodedHeader === false) {
            throw new InvalidArgumentException('Invalid Base64-encoded JWT header.');
        }

        // JSON-decode the decoded header
        $decodedArray = json_decode($decodedHeader, true);

        if (!is_array($decodedArray)) {
            throw new InvalidArgumentException('Invalid JSON in JWT header.');
        }

        return $decodedArray;
    }

    /**
     * Extracts the 'kid' value from a JWT header.
     *
     * @param string $jwtHeader The Base64URL-encoded JWT header string.
     * @throws InvalidArgumentException If the 'kid' key is missing or the JWT header is invalid.
     * @return string The 'kid' value from the decoded JWT header.
     */
    protected function getKidFromJwtHeader(string $jwtHeader): string
    {
        // Decode the JWT header into an associative array
        $decodedHeader = $this->decodeJwtHeaderIntoArray($jwtHeader);

        // Ensure the 'kid' key exists in the decoded header
        if (!isset($decodedHeader['kid']) || !is_string($decodedHeader['kid'])) {
            throw new InvalidArgumentException('The "kid" key is missing or invalid in the JWT header.');
        }

        return $decodedHeader['kid'];
    }

    /**
     * Find the matching key in the JWKS
     */
    protected function getPublicKeyFromJwtHeader(string $jwtHeader): null|array|string
    {
        $kid = $this->getKidFromJwtHeader($jwtHeader);
        $publicKey = null;
        $jwks = $this->fetchJWKS();

        /**
         * @var array $jwks['keys']
         * @var array $key
         */
        foreach ($jwks['keys'] as $key) {
            if ($key['kid'] === $kid) {
                /**
                 * @var string $key['n']
                 * @var string $key['e']
                 */
                $publicKey = $this->createPemFromModulusAndExponent($key['n'], $key['e']);
                break;
            }
        }

        if (strlen($publicKey) == 0) {
            throw new Exception('Public key not found for the specified kid');
        }

        return $publicKey;
    }

    /**
     * Creates a PEM-formatted public key from a modulus and exponent.
     *
     * @param string $modulus The Base64URL-encoded modulus.
     * @param string $exponent The Base64URL-encoded exponent.
     * @throws InvalidArgumentException If decoding or key creation fails.
     * @return string The PEM-formatted public key.
     * @psalm-return null|array<array-key, mixed>|string
     */
    protected function createPemFromModulusAndExponent(string $modulus, string $exponent): null|array|string
    {
        // Convert Base64URL to Base64
        $modulusForDecoding = $this->base64UrlToBase64($modulus);
        $exponentForDecoding = $this->base64UrlToBase64($exponent);

        // Decode Base64
        $modulusHex = base64_decode($modulusForDecoding, true);
        $exponentHex = base64_decode($exponentForDecoding, true);

        if ($modulusHex === false || $exponentHex === false) {
            throw new InvalidArgumentException('Invalid Base64 encoding for modulus or exponent.');
        }

        // Convert Hex to Binary
        $modulusBin = pack('H*', bin2hex($modulusHex));
        $exponentBin = pack('H*', bin2hex($exponentHex));

        if ($modulusBin === '' || $exponentBin === '') {
            throw new InvalidArgumentException('Failed to convert modulus or exponent to binary.');
        }

        // Construct the RSA key array
        $rsaKey = [
            'modulus' => $modulusBin,
            'publicExponent' => $exponentBin,
        ];

        // Load the key using phpseclib's PublicKeyLoader
        $rsa = PublicKeyLoader::load([
            'n' => $rsaKey['modulus'],
            'e' => $rsaKey['publicExponent'],
        ], 'raw');

        // Return the PEM-formatted public key
        return $rsa->toString('PKCS8');
    }

    protected function supportedScopes(): array
    {
        return ['open_id', 'email', 'phone', 'offline_access'];
    }

    protected function supportedBackChannelLogout(): bool
    {
        return false;
    }

    protected function supportedBackChannelLogoutSession(): bool
    {
        return true;
    }

    protected function supportedCodeChallengeMethods(): array
    {
        return ['S256'];
    }

    protected function supportedGrantTypes(): array
    {
        return ['authorization_code'];
    }

    protected function supportedTokenEndpointAuthMethods(): array
    {
        return ['private_key_jwt', 'client_secret_post'];
    }

    protected function supportedRequestUriParameter(): bool
    {
        return false;
    }

    protected function supportedResponseTypes(): array
    {
        return ['code'];
    }

    protected function supportedTokenEndpointAuthSigningAlgValues(): array
    {
        return ['RS256', 'RS384', 'RS512', 'PS256', 'PS384', 'PS512'];
    }

    protected function supportedUserInterfaceLocales(): array
    {
        return ['en', 'cy'];
    }

    protected function getClaimTypesSupported(): array
    {
        return ['normal'];
    }

    protected function getClaimsSupported(): array
    {
        return [
            'sub',
            'email',
            'email_verified',
            'phone_number',
            'phone_number_verified',
            'wallet_subject_id',
            'https://vocab.account.gov.uk/v1/passport',
            'https://vocab.account.gov.uk/v1/drivingPermit',
            'https://vocab.account.gov.uk/v1/coreIdentityJWT',
            'https://vocab.account.gov.uk/v1/address',
            'https://vocab.account.gov.uk/v1/inheritedIdentityJWT',
            'https://vocab.account.gov.uk/v1/returnCode',
        ];
    }

    protected function getJsonTrustMarkUri(): string
    {
        return 'https://oidc.integration.account.gov.uk/trustmark';
    }

    protected function getPolicyUri(): string
    {
        return 'https://signin.integration.account.gov.uk/privacy-notice';
    }

    protected function getTermsAndConditionsUri(): string
    {
        return 'https://signin.integration.account.gov.uk/terms-and-conditions';
    }

    protected function getDefaultScopes(): string
    {
        return 'openid email phone';
    }

    /**
     * @return string
     *
     * @psalm-return 'openid'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'openid';
    }
}
