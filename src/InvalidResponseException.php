<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\ResponseInterface;
use yii\exceptions\Exception;

/**
 * InvalidResponseException represents an exception caused by invalid remote server response.
 */
class InvalidResponseException extends Exception
{
    /**
     * @var ResponseInterface HTTP response instance.
     */
    public $response;


    /**
     * Constructor.
     * @param ResponseInterface $response HTTP response instance
     * @param string $message error message
     * @param int $code error code
     * @param \Throwable $previous The previous exception used for the exception chaining.
     */
    public function __construct($response, $message = null, $code = 0, \Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }
}
