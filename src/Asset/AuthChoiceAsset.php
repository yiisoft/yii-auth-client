<?php

namespace Yiisoft\Yii\AuthClient\Widgets;

use Yiisoft\Assets\AssetBundle;
use Yiisoft\Yii\JQuery\YiiAsset;

/**
 * AuthChoiceAsset is an asset bundle for [[AuthChoice]] widget.
 *
 * @see AuthChoiceStyleAsset
 */
class AuthChoiceAsset extends AssetBundle
{
    public ?string $sourcePath = '@Yiisoft/Yii/AuthClient/assets';
    public array $js = [
        'authchoice.js',
    ];
    public array $depends = [
        AuthChoiceStyleAsset::class,
        YiiAsset::class,
    ];
}
