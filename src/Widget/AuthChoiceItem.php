<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widget;

use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\ClientInterface;

/**
 * AuthChoiceItem is a base class for creating widgets, which can be used to render link
 * for auth client at {@see AuthChoice}.
 */
class AuthChoiceItem extends Widget
{
    /**
     * @var ClientInterface auth client instance.
     */
    public ClientInterface $client;
    /**
     * @var AuthChoice parent AuthChoice widget
     */
    public AuthChoice $authChoice;
}
