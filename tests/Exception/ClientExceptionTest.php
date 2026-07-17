<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Yiisoft\Yii\AuthClient\Exception\ClientException;

final class ClientExceptionTest extends TestCase
{
    public function testDefaultCodeIs400(): void
    {
        $exception = new ClientException('message');

        $this->assertSame(400, $exception->getCode());
    }

    public function testImplementsClientExceptionInterface(): void
    {
        $exception = new ClientException('message');

        $this->assertInstanceOf(ClientExceptionInterface::class, $exception);
    }

    public function testCarriesMessageAndPrevious(): void
    {
        $previous = new \RuntimeException('cause');

        $exception = new ClientException('message', 500, $previous);

        $this->assertSame('message', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
