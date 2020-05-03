<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widgets;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for [[AuthChoice]] widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public ?string $sourcePath = '@Yiisoft/Yii/AuthClient/assets';
    public array $css = [
        'authchoice.css',
    ];
}
