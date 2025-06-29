<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use DateTimeZone;
use DateTime;

/**
 * @see https://github.com/MicrosoftDocs/dynamics365smb-docs/blob/main/business-central/LocalFunctionality/UnitedKingdom/fraud-prevention-data.md
 */

final class DeveloperSandboxHmrc extends OAuth2
{
    /**
     * @var array<string, array<string, string>> Environment configuration for URLs.
     */
    private const array ENVIRONMENTS = [
        'dev' => [
            'authUrl' => 'https://test-api.service.hmrc.gov.uk/oauth/authorize',
            'tokenUrl' => 'https://test-api.service.hmrc.gov.uk/oauth/token',
            'apiBaseUrl1' => 'https://test-api.service.hmrc.gov.uk',
            'apiBaseUrl2' => 'https://test-www.tax.service.gov.uk',
        ],
        'prod' => [
            'authUrl' => 'https://api.service.hmrc.gov.uk/oauth/authorize',
            'tokenUrl' => 'https://api.service.hmrc.gov.uk/oauth/token',
            'apiBaseUrl1' => 'https://api.service.hmrc.gov.uk',
            'apiBaseUrl2' => 'https://www.tax.service.gov.uk',
        ],
    ];

    private array $authorizedIpAddressValidatorEndPoints =
        [
            'db-ip' => 'https://api.db-ip.com/v2/free/self',
            'cloudflare' => 'https://www.cloudflare.com/cdn-cgi/trace',
            'ipify' => 'https://api.ipify.org',
            'jsonip' => 'https://jsonip.com',
        ];

    public array $vatReturnStatuses = [
        'open', 'released', 'rejected', 'submitted', 'accepted', 'partially accepted', 'closed',
    ];

    private string $environment;

    /**
     * Initialize the environment.
     *
     * @param string $environment Environment type ('dev' or 'prod').
     *
     * @throws \InvalidArgumentException If the environment is invalid.
     */
    public function setEnvironment(string $environment = 'dev'): void
    {
        if (!array_key_exists($environment, self::ENVIRONMENTS)) {
            throw new \InvalidArgumentException('Invalid environment: ' . $environment);
        }
        $this->environment = $environment;
        $this->authUrl = $this->getEnvironmentConfig('authUrl');
        $this->tokenUrl = $this->getEnvironmentConfig('tokenUrl');
    }

    /**
     * @use AuthController function callbackDeveloperSandboxHmrc
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get the configuration value for the current environment.
     *
     * @param string $key Configuration key.
     *
     * @throws \InvalidArgumentException If the key does not exist.
     * @return string The configuration value.
     */
    private function getEnvironmentConfig(string $key): string
    {
        $config = self::ENVIRONMENTS[$this->environment][$key] ?? null;

        if ($config === null) {
            throw new \InvalidArgumentException("Configuration key '$key' not found for environment: " . $this->environment);
        }

        return $config;
    }

    /**
     * Get the base API URL (1) based on the environment.
     *
     * @return string
     */
    public function getApiBaseUrl1(): string
    {
        return $this->getEnvironmentConfig('apiBaseUrl1');
    }

    /**
     * Get the base API URL (2) based on the environment.
     *
     * @return string
     */
    public function getApiBaseUrl2(): string
    {
        return $this->getEnvironmentConfig('apiBaseUrl2');
    }

    public function clientConnectionMethods(): array
    {
        return [
            // A desktop application that communicates directly with the API.
            'desktopAppDirect' => 'DESKTOP_APP_DIRECT',

            // A desktop application that routes its requests through a server before reaching the API.
            'desktopAppViaServer' => 'DESKTOP_APP_VIA_SERVER',

            // A web application making direct calls to the API from the client browser.
            'webAppDirect' => 'WEB_APP_DIRECT',

            // A web application that routes requests through a backend server.
            'webAppViaServer' => 'WEB_APP_VIA_SERVER',

            // A mobile application that directly communicates with the API.
            'mobileAppDirect' => 'MOBILE_APP_DIRECT',

            // A mobile application routing its requests through a server.
            'mobileAppViaServer' => 'MOBILE_APP_VIA_SERVER',

            // A system that sends batch data directly to the API.
            'batchProcessDirect' => 'BATCH_PROCESS_DIRECT',

            // A system that routes batch data through a server before reaching the API.
            'batchProcessViaServer' => 'BATCH_PROCESS_VIA_SERVER',

            // A gateway service that abstracts and forwards requests to the API.
            'apiGateway' => 'API_GATEWAY',

            // 'A third-party service acting as an intermediary between the client and the API.'
            'thirdPartyService' => 'THIRD_PARTY_SERVICE',
        ];
    }

    public function getCurrentUserJsonArray(OAuthToken $token): array
    {
        $tokenParams = $token->getParams();
        $tokenArray = array_keys($tokenParams);

        // Ensure tokenArray contains at least one element
        if (empty($tokenArray) || !is_string($tokenArray[0])) {
            throw new \InvalidArgumentException('Invalid token parameters or missing token array.');
        }

        // No redundant cast
        $jsonString = $tokenArray[0];

        // Decode JSON into an associative array
        $finalArray = json_decode($jsonString, true);
        if (!is_array($finalArray)) {
            throw new \InvalidArgumentException('Invalid JSON string in token parameters.');
        }

        // Ensure tokenString is a string
        $tokenString = $finalArray['access_token'] ?? '';
        if (!is_string($tokenString) || $tokenString === '') {
            throw new \InvalidArgumentException('Missing or invalid access_token in token parameters.');
        }

        $request = $this->createRequest('GET', $this->getApiBaseUrl1() . '/self-assessment/individual/details');

        // Parse the host and handle null/false explicitly
        $host = parse_url($this->getApiBaseUrl1(), PHP_URL_HOST);
        if ($host === false || $host === null) {
            throw new \InvalidArgumentException('Invalid API Base URL for host parsing.');
        }

        // Add headers with strict type validation
        $request = RequestUtil::addHeaders(
            $request,
            [
                'Authorization' => 'Bearer ' . $tokenString,
                'Host' => $host,
                'Content-length' => '0',
            ]
        );

        /** @var ResponseInterface $response */
        $response = $this->sendRequest($request);

        // Decode response body into an associative array
        $user = json_decode($response->getBody()->getContents(), true);

        // Validate that the decoded JSON is an associative array with string keys
        if (!is_array($user) || array_keys($user) !== array_filter(array_keys($user), 'is_string')) {
            throw new \InvalidArgumentException('Invalid JSON response from the API. Expected an associative array with string keys.');
        }

        return $user;
    }

    public function createTestUserIndividual(OAuthToken $token, array $requestBody): array
    {
        // Retrieve the access token string from the OAuth token
        $tokenString = (string)$token->getParam('access_token');

        if (strlen($tokenString) > 0) {
            // Define the URL for the create-test-user/individuals endpoint
            $url = 'https://test-api.service.hmrc.gov.uk/create-test-user/individuals';

            // Create a POST request
            $request = $this->createRequest('POST', $url);

            // Add necessary headers, including the access token
            $request = RequestUtil::addHeaders(
                $request,
                [
                    'Authorization' => 'Bearer ' . $tokenString,
                    'Content-Type' => 'application/json',
                ]
            );

            // Add the JSON payload to the request body
            $request = $request->withBody(
                \GuzzleHttp\Psr7\Utils::streamFor(json_encode($requestBody))
            );

            // Send the request and retrieve the response
            $response = $this->sendRequest($request);

            // Decode the JSON response into an array and return it
            return (array)json_decode($response->getBody()->getContents(), true);
        }

        return [];
    }

    /**
     * Date Received: 01 May 2025
     */
    public function getTestUserArray(): array
    {
        return $testUser = [
            'userId' => '341862201113',
            'password' => 'vKY3XTIS0q6O',
            'userFullName' => 'Fay Izzard',
            'emailAddress' => 'fay.izzard@example.com',
            'individualDetails' => [
                'firstName' => 'Fay',
                'lastName' => 'Izzard',
                'dateOfBirth' => '1946-01-31',
                'address' => [
                    'line1' => '10 Jamaica Road',
                    'line2' => 'Oakham',
                    'postcode' => 'TS17 1PA',
                ],
            ],
            'nino' => 'YJ776619A',
            'groupIdentifier' => '916423927905',
        ];
    }

    /**
     * Get the service name.
     *
     * @return string
     *
     * @psalm-return 'developersandboxhmrc'
     */
    public function getName(): string
    {
        return 'developersandboxhmrc';
    }

    /**
     * Get the service title.
     *
     * @return string
     *
     * @psalm-return 'DeveloperSandboxHmrc'
     */
    public function getTitle(): string
    {
        return 'DeveloperSandboxHmrc';
    }

    /**
     * Get the default scope for the service.
     *
     * @return string
     *
     * @psalm-return 'read:self-assessment write:self-assessment'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'read:self-assessment write:self-assessment';
    }

    /**
     * Format as UTC+00:00
     * @return string
     */
    private function dateTimeZone(): string
    {
        $timezone = date_default_timezone_get();
        $dateTimeZone = new DateTimeZone($timezone);
        $offset = $dateTimeZone->getOffset(new DateTime('now', $dateTimeZone)) / 3600;
        return $formattedOffset = ($offset >= 0 ? 'UTC+' : 'UTC') . sprintf('%02d:00', abs($offset));
    }

    public function getAuthorizedIpAddressEndpoints(): array
    {
        return $this->authorizedIpAddressValidatorEndPoints;
    }
}
