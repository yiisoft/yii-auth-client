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
     * Constructor.
     *
     * @param ResponseInterface $response HTTP response instance
     * @param string $message error message
     * @param int $code error code
     * @param Throwable $previous The previous exception used for the exception chaining.
     */
    public function __construct(private $response, $message = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
