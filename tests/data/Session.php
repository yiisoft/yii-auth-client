<?php

namespace yii\authclient\tests\data;

/**
 * Web session class mock.
 */
class Session extends \yii\web\Session
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        // blank, override, preventing shutdown function registration
    }

    public function open()
    {
        // blank, override, preventing session start
    }
}
