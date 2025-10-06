<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Exception;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \RuntimeException implements ClientExceptionInterface
{
    public function __construct(string $message, int $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
