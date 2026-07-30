<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widget;

/**
 * Controls what {@see AuthChoice::clientLink()} renders inside a client button by default, when no explicit
 * `$text` is given.
 */
enum AuthChoiceDisplayMode: string
{
    /**
     * Provider icon only (default) - an `<span class="auth-icon {name}">` sprite.
     */
    case Icon = 'icon';

    /**
     * Provider title only, e.g. `'Google'`, HTML-encoded.
     */
    case Text = 'text';

    /**
     * Both the icon and the title, the latter wrapped in `<span class="auth-title">`.
     */
    case Both = 'both';
}
