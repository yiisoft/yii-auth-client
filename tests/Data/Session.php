<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Yiisoft\Session\SessionInterface;

/**
 * Web session class mock.
 */
final class Session implements SessionInterface
{
    private array $data = [];

    public function __construct()
    {
        // blank, preventing shutdown function registration
    }

    public function open(): void
    {
        // blank, preventing session start
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->open();
        $this->data[$key] = $value;
    }

    public function close(): void
    {
        // blank, preventing session close
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getId(): ?string
    {
        return null;
    }

    public function regenerateId(): void
    {
        // blank, preventing session re-generate id
    }

    public function discard(): void
    {
        // blank, preventing session discard
    }

    public function getName(): string
    {
        return 'mock-session';
    }

    public function all(): array
    {
        $this->open();
        return $this->data;
    }

    public function remove(string $key): void
    {
        $this->open();
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        $this->open();
        return isset($this->data[$key]);
    }

    public function pull(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;
        $this->remove($key);
        return $value;
    }

    public function clear(): void
    {
        $this->open();
        $this->data = [];
    }

    public function destroy(): void
    {
        // blank, preventing session destroy
    }

    public function getCookieParameters(): array
    {
        return [];
    }

    public function setId(string $sessionId): void
    {
        // blank, preventing session id
    }
}
