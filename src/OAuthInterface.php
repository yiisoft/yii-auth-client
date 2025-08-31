<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory as YiisoftFactory;

/**
 * Interface for OAuth clients.
 */
interface OAuthInterface extends AuthClientInterface
{
    public function setYiisoftFactory(YiisoftFactory $factory): void;
    public function getYiisoftFactory(): YiisoftFactory;
    public function setAuthUrl(string $authUrl): void;

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    public function getReturnUrl(ServerRequestInterface $request): string;

    /**
     * Performs request to the OAuth API returning response data.
     *
     * @param string $apiSubUrl
     * @param string $method
     * @param array|string $data
     * @param array $headers
     * @return array
     */
    public function api(string $apiSubUrl, string $method = 'GET', array|string $data = [], array $headers = []): array;

    /**
     * Creates an HTTP request for the API call.
     *
     * @param string $method
     * @param string $uri
     * @return RequestInterface
     */
    public function createApiRequest(string $method, string $uri): RequestInterface;

    public function beforeApiRequestSend(RequestInterface $request): RequestInterface;

    /**
     * @return OAuthToken|null
     */
    public function getAccessToken(): ?OAuthToken;

    /**
     * Sets access token to be used.
     *
     * @param array|OAuthToken $token
     */
    public function setAccessToken(array|OAuthToken $token): void;

    /**
     * Gets new auth token to replace expired one.
     *
     * @param OAuthToken $token
     * @return OAuthToken
     */
    public function refreshAccessToken(OAuthToken $token): OAuthToken;

    /**
     * Applies access token to the HTTP request instance.
     *
     * @param RequestInterface $request
     * @param OAuthToken $accessToken
     * @return RequestInterface
     */
    public function applyAccessTokenToRequest(
        RequestInterface $request,
        OAuthToken $accessToken
    ): RequestInterface;

    /**
     * @return string
     */
    public function getScope(): string;
}