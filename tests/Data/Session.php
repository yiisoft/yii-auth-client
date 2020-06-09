<?php

namespace Yiisoft\Yii\AuthClient\Tests\Data;

/**
 * Web session class mock.
 */
class Session extends \Yiisoft\Yii\Web\Session\Session
{
    public function __construct()
    {
        // blank, override, preventing shutdown function registration
    }

    public function open(): void
    {
        // blank, override, preventing session start
    }
}
