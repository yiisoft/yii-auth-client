<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * OpenBanking OAuth2 client for UK
 */
final class OpenBanking extends OAuth2
{
    /**
     * @var string|null
     */
    protected ?string $scope = 'openid accounts payments';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'openbanking';
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): string
    {
        return 'Open Banking';
    }

    /**
     * Exchanges the authorization code for an access token, using PKCE (code_verifier).
     *
     * @param ServerRequestInterface $incomingRequest
     * @param string|null $authCode
     * @param array $params
     * @return OAuthToken
     */
    public function fetchAccessTokenWithCurlAndCodeVerifier(ServerRequestInterface $incomingRequest, $authCode = null, array $params = []): OAuthToken
    {
        $tokenUrl = $this->getTokenUrl();
        /** @var string|null $redirectUri */
        $redirectUri = isset($params['redirect_uri']) && is_string($params['redirect_uri']) ? $params['redirect_uri'] : null;
        /** @var string|null $codeVerifier */
        $codeVerifier = isset($params['code_verifier']) && is_string($params['code_verifier']) ? $params['code_verifier'] : null;

        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
        ];

        if ($redirectUri !== null) {
            $postFields['redirect_uri'] = $redirectUri;
        }
        if ($codeVerifier !== null) {
            $postFields['code_verifier'] = $codeVerifier;
        }

        // Add client_id and client_secret if needed
        $clientId = $this->getClientId();
        if ($clientId !== '') {
            $postFields['client_id'] = $clientId;
        }
        $clientSecret = $this->getClientSecret();
        if ($clientSecret !== '') {
            $postFields['client_secret'] = $clientSecret;
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /** @var false|string $response */
        $response = curl_exec($ch);
        if ($response === false) {
            throw new \RuntimeException('Failed to get access token: ' . curl_error($ch));
        }
        curl_close($ch);

        /** @var array<string, mixed>|null $output */
        $output = json_decode($response, true);
        if (!is_array($output)) {
            throw new \RuntimeException('Failed to decode access token response');
        }

        $token = new OAuthToken();
        /**
         * @var string $key
         * @var mixed $value
         */
        foreach ($output as $key => $value) {
            $token->setParam($key, $value);

            // Decode id_token if present
            if ($key === 'id_token' && is_string($value)) {
                $token->setParam('id_token_payload', $this->decodeIdToken($value));
            }
        }

        return $token;
    }

    /**
     * Decode the id_token JWT payload.
     *
     * @param string $idToken
     * @return array<string, mixed>
     */
    public function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return [];
        }
        $payload = $parts[1];
        // JWT uses URL-safe base64 encoding with no padding
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) {
            $payload .= str_repeat('=', 4 - $remainder);
        }
        /** @var false|string $payloadJson */
        $payloadJson = base64_decode(strtr($payload, '-_', '+/'));
        if ($payloadJson === false) {
            return [];
        }
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payloadJson, true);
        return is_array($decoded) ? $decoded : [];
    }
}
