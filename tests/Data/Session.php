<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Yiisoft\Session\SessionInterface;

/**
 * Web session class mock.
 */
class Session implements SessionInterface
{
    private array $data = [];

    public function __construct()
    {
        // blank, preventing shutdown function registration
    }

    #[\Override]
    public function open(): void
    {
        // blank, preventing session start
    }

    #[\Override]
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    #[\Override]
    public function set(string $key, $value): void
    {
        $this->open();
        $this->data[$key] = $value;
    }

    #[\Override]
    public function close(): void
    {
        // blank, preventing session close
    }

    #[\Override]
    public function isActive(): bool
    {
        return true;
    }

    #[\Override]
    public function getId(): ?string
    {
        return null;
    }

    #[\Override]
    public function regenerateId(): void
    {
        // blank, preventing session re-generate id
    }

    #[\Override]
    public function discard(): void
    {
        // blank, preventing session discard
    }

    #[\Override]
    public function getName(): string
    {
        return 'mock-session';
    }

    #[\Override]
    public function all(): array
    {
        $this->open();
        return $this->data;
    }

    #[\Override]
    public function remove(string $key): void
    {
        $this->open();
        unset($this->data[$key]);
    }

    #[\Override]
    public function has(string $key): bool
    {
        $this->open();
        return isset($this->data[$key]);
    }

    #[\Override]
    public function pull(string $key, $default = '')
    {
        /**
         * @psalm-suppress MixedAssignment
         */
        $value = $this->data[$key] ?? $default;
        $this->remove($key);
        return $value;
    }

    #[\Override]
    public function clear(): void
    {
        $this->open();
        $this->data = [];
    }

    #[\Override]
    public function destroy(): void
    {
        // blank, preventing session destroy
    }

    #[\Override]
    public function getCookieParameters(): array
    {
        return [];
    }

    #[\Override]
    public function setId(string $sessionId): void
    {
        // blank, preventing session id
    }
}
