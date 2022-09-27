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

    public function __construct()
    {
        $this->createTimestamp = time();
    }

    /**
     * Sets token value.
     *
     * @param string $token token value.
     */
    public function setToken(string $token): void
    {
        $this->setParam($this->tokenParamKey, $token);
    }

    /**
     * Sets param by name.
     *
     * @param string $name param name.
     * @param mixed $value param value,
     */
    public function setParam(string $name, mixed $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Sets the token secret value.
     *
     * @param string $tokenSecret token secret.
     */
    public function setTokenSecret(string $tokenSecret): void
    {
        $this->setParam($this->tokenSecretParamKey, $tokenSecret);
    }

    /**
     * Returns the token secret value.
     *
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
    public function getParam(string $name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Sets token expire duration.
     *
     * @param int $expireDuration token expiration duration.
     */
    public function setExpireDuration(int $expireDuration): void
    {
        $this->setParam($this->getExpireDurationParamKey(), $expireDuration);
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
     * @param string $expireDurationParamKey expire duration param key.
     */
    public function setExpireDurationParamKey(string $expireDurationParamKey): void
    {
        $this->expireDurationParamKey = $expireDurationParamKey;
    }

    /**
     * Fetches default expire duration param key.
     *
     * @return string expire duration param key.
     */
    protected function defaultExpireDurationParamKey(): string
    {
        $expireDurationParamKey = 'expires_in';
        foreach ($this->getParams() as $name => $value) {
            if (str_contains($name, 'expir')) {
                $expireDurationParamKey = $name;
                break;
            }
        }

        return $expireDurationParamKey;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Checks if token is valid.
     *
     * @return bool is token valid.
     */
    public function getIsValid(): bool
    {
        $token = $this->getToken();

        return !empty($token) && !$this->getIsExpired();
    }

    /**
     * Returns token value.
     *
     * @return string token value.
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
        if ($expirationDuration === null) {
            return false;
        }

        return time() >= ($this->createTimestamp + $expirationDuration);
    }

    /**
     * Returns the token expiration duration.
     *
     * @return int|null token expiration duration.
     */
    public function getExpireDuration(): ?int
    {
        return $this->getParam($this->getExpireDurationParamKey());
    }

    public function getCreateTimestamp(): int
    {
        return $this->createTimestamp;
    }
}
