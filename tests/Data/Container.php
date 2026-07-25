<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function array_key_exists;

/**
 * Container stub resolving entries from a fixed map, used where a full DI container is unnecessary.
 */
final readonly class Container implements ContainerInterface
{
    public function __construct(private readonly array $entries = []) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ($id) extends RuntimeException implements NotFoundExceptionInterface {
                public function __construct(string $id)
                {
                    parent::__construct("Entry '{$id}' not found.");
                }
            };
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
