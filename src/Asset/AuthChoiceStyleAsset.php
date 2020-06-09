<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public ?string $sourcePath = '@auth-client/assets';
    public array $css = [
        'authchoice.css',
    ];
}
