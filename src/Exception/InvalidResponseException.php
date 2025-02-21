<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * InvalidResponseException represents an exception caused by invalid remote server response.
 */
class InvalidResponseException extends RuntimeException
{
    /**
     * @var ResponseInterface HTTP response instance.
     */
    private ResponseInterface $response;

    /**
     * Constructor.
     *
     * @param ResponseInterface $response HTTP response instance
     * @param string $message error message
     * @param int $code error code
     * @param Throwable $previous The previous exception used for the exception chaining.
     */
    public function __construct(ResponseInterface $response, string $message, $code = 0, Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }
}
