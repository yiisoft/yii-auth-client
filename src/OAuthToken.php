<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

/**
 * Token represents OAuth token.
 */
final class OAuthToken
{
    /**
     * @var string key in {@see params} array, which stores token key.
     */
    private string $tokenParamKey = 'oauth_token';
    /**
     * @var string key in {@see params} array, which stores token secret key.
     */
    private string $tokenSecretParamKey = 'oauth_token_secret';
    /**
     * @var int object creation timestamp.
     */
    private int $createTimestamp;

    /**
     * @var string|null key in {@see params} array, which stores token expiration duration.
     * If not set will attempt to fetch its value automatically.
     */
    private ?string $expireDurationParamKey = null;
    /**
     * @var array token parameters.
     */
    private array $params = [];

    /**
     * Returns the token secret value.     
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType
     * @return string token secret value.
     */
    public function getTokenSecret(): string
    {
        return $this->getParam($this->tokenSecretParamKey);
    }

    /**
     * Returns param by name.
     *
     * @param string $name param name.
     *
     * @return mixed param value.
     */
    public function getParam(string $name) : mixed
    {
        return $this->params[$name] ?? null;
    }

    /**
     * @return string expire duration param key.
     */
    public function getExpireDurationParamKey(): string
    {
        if ($this->expireDurationParamKey === null) {
            $this->expireDurationParamKey = $this->defaultExpireDurationParamKey();
        }

        return $this->expireDurationParamKey;
    }

    /**
     * Fetches default expire duration param key.
     *
     * @return string expire duration param key.
     */
    protected function defaultExpireDurationParamKey(): string
    {
        $expireDurationParamKey = 'expires_in';
        /**
         * @var string $name
         */
        foreach ($this->getParams() as $name) {
            if (strpos($name, 'expir') !== false) {
                $expireDurationParamKey = $name;
                break;
            }
        }

        return $expireDurationParamKey;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Checks if token is valid.
     *
     * @return bool is token valid.
     */
    public function getIsValid(): bool
    {
        $token = $this->getToken();

        return strlen($token ?? '') > 0 && !$this->getIsExpired();
    }

    /**
     * Returns token value.
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType
     */
    public function getToken(): ?string
    {
        return $this->getParam($this->tokenParamKey);
    }

    /**
     * Checks if token has expired.
     *
     * @return bool is token expired.
     */
    public function getIsExpired(): bool
    {
        $expirationDuration = $this->getExpireDuration();
        if (!is_int($expirationDuration)) {
            return false;
        }

        return time() >= ($this->createTimestamp + $expirationDuration);
    }

    /**
     * Returns the token expiration duration.
     *
     * return mixed token expiration duration.
     */
    public function getExpireDuration(): mixed
    {
        return $this->getParam($this->getExpireDurationParamKey());
    }
}
