 <?php

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Handles OAuth2 token exchange with PKCE using cURL.
 */
final class OAuth2PkceClient extends OAuth2
{
    /**
     * Fetches an access token using the authorization code and PKCE code verifier.
     *
     * @param ServerRequestInterface $incomingRequest The incoming HTTP request object.
     * @param string $authCode The authorization code received from the authorization server.
     * @param array<array-key, mixed> $params Additional parameters such as 'redirect_uri' and 'code_verifier'.
     *
     * @return OAuthToken The generated OAuth token.
     *
     * @throws InvalidArgumentException If the state validation fails.
     * @throws RuntimeException If the cURL request fails or the token response is invalid.
     */
    public function fetchAccessTokenWithCurlAndCodeVerifier(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken {
        // Validate state
        if ($this->validateAuthState) {
            /** @var string|null $authState */
            $authState = $this->getState('authState');
            if (!is_string($authState)) {
                throw new InvalidArgumentException('Invalid auth state.');
            }

            $queryParams = $incomingRequest->getQueryParams();
            $bodyParams = $incomingRequest->getParsedBody();
            $incomingState = $queryParams['state'] ?? ($bodyParams['state'] ?? null);

            if (!is_string($incomingState) || strcmp($incomingState, $authState) !== 0) {
                throw new InvalidArgumentException('Invalid auth state parameter.');
            }

            $this->removeState('authState');
        }

        // Prepare request body
        $requestBody = [
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'redirect_uri' => $params['redirect_uri'] ?? '',
            'code_verifier' => $params['code_verifier'] ?? '',
        ];

        $requestBodyString = http_build_query($requestBody);

        // Execute cURL request
        $curl = curl_init($this->tokenUrl);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBodyString);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($curl);
        if (!is_string($response)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('cURL error: ' . $error);
        }

        curl_close($curl);

        // Parse response
        $output = json_decode($response, true);
        if ($output === null) {
            parse_str($response, $output);
        }

        if (!is_array($output)) {
            throw new RuntimeException('Invalid token response.');
        }

        $token = new OAuthToken();
        /** @var array<string, scalar> $output */
        foreach ($output as $key => $value) {
            $token->setParam($key, $value);
        }

        return $token;
    }

    /**
     * @return string service name.
     *
     * @psalm-return 'oauth2pkceclient'
     */
    public function getName(): string
    {
        return 'oauth2pkceclient';
    }
    
    /**
     * @return string service title.
     *
     * @psalm-return 'OAuth2PkceClient'
     */
    public function getTitle(): string
    {
        return 'OAuth2PkceClient';
    }
    
    protected function getDefaultScope(): string
    {
        return '';
    }

}