<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public ?string $sourcePath = '@Yiisoft/Yii/AuthClient/assets';
    public array $css = [
        'authchoice.css',
    ];
}
