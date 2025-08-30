<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

/**
 * OpenBanking OAuth2 client for UK and other providers.
 * The endpoints and scope must be set by the consuming controller before usage.
 */
final class OpenBanking extends OAuth2
{
    /**
     * @var string
     * These must match the parent's type exactly for Psalm invariance.
     */
    protected string $authUrl = '';

    /**
     * @var string
     */
    protected string $tokenUrl = '';

    /**
     * @var string|null
     */
    protected ?string $scope = null;

    /**
     * Set the authorization URL (for the current provider).
     * @param string $authUrl
     */
    public function setAuthUrl(string $authUrl): void
    {
        $this->authUrl = $authUrl;
    }

    /**
     * Set the token URL (for the current provider).
     * @param string $tokenUrl
     */
    public function setTokenUrl(string $tokenUrl): void
    {
        $this->tokenUrl = $tokenUrl;
    }

    /**
     * Set the scope (for the current provider).
     * @param string|null $scope
     */
    public function setScope(?string $scope): void
    {
        $this->scope = $scope;
    }

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
     * Override the auth URL to use the selected provider.
     * No fallback to parent is possible: the controller MUST set it.
     */
    public function getAuthUrl(): string
    {
        return $this->authUrl;
    }

    /**
     * Override the token URL to use the selected provider.
     * No fallback to parent is possible: the controller MUST set it.
     */
    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    /**
     * Override the scope to use that of the selected provider.
     */
    public function getScope(): string
    {
        // Parent's getScope() returns string
        return $this->scope ?? parent::getScope();
    }

    /**
     * Exchanges the authorization code for an access token, using PKCE (code_verifier).
     *
     * @param ServerRequestInterface $incomingRequest
     * @param string $authCode
     * @param array $params
     * @return OAuthToken
     */
    public function fetchAccessTokenWithCurlAndCodeVerifier(ServerRequestInterface $incomingRequest, string $authCode, array $params = []): OAuthToken
    {
        $tokenUrl = $this->getTokenUrl();
        $redirectUri = isset($params['redirect_uri']) && is_string($params['redirect_uri']) ? $params['redirect_uri'] : null;
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
        $payloadJson = base64_decode(strtr($payload, '-_', '+/'));
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payloadJson ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }
}
