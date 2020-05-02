<?php

namespace Yiisoft\Yii\AuthClient\Widgets;

use yii\web\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for [[AuthChoice]] widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public $sourcePath = '@Yiisoft/Yii/AuthClient/assets';
    public $css = [
        'authchoice.css',
    ];
}
