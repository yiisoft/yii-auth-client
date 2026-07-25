<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\AuthClient\Exception\InvalidResponseException;
use RuntimeException;

final class InvalidResponseExceptionTest extends TestCase
{
    public function testDefaultCodeIsZero(): void
    {
        $exception = new InvalidResponseException($this->createStub(ResponseInterface::class), 'message');

        $this->assertSame(0, $exception->getCode());
    }

    public function testCarriesMessageCodeAndPrevious(): void
    {
        $previous = new RuntimeException('cause');

        $exception = new InvalidResponseException(
            $this->createStub(ResponseInterface::class),
            'message',
            500,
            $previous,
        );

        $this->assertSame('message', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testGetResponseReturnsResponsePassedToConstructor(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $exception = new InvalidResponseException($response, 'message');

        $this->assertSame($response, $exception->getResponse());
    }
}
