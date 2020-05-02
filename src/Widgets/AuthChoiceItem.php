<?php

namespace Yiisoft\Yii\AuthClient\Widgets;

use yii\base\Widget;

/**
 * AuthChoiceItem is a base class for creating widgets, which can be used to render link
 * for auth client at [[AuthChoice]].
 *
 * @see AuthChoice
 */
class AuthChoiceItem extends Widget
{
    /**
     * @var \Yiisoft\Yii\AuthClient\ClientInterface auth client instance.
     */
    public $client;
    /**
     * @var AuthChoice parent AuthChoice widget
     */
    public $authChoice;
}
