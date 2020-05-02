<?php

namespace Yiisoft\Yii\AuthClient\Widgets;

use yii\web\AssetBundle;
use Yiisoft\Yii\JQuery\YiiAsset;

/**
 * AuthChoiceAsset is an asset bundle for [[AuthChoice]] widget.
 *
 * @see AuthChoiceStyleAsset
 */
class AuthChoiceAsset extends AssetBundle
{
    public $sourcePath = '@Yiisoft/Yii/AuthClient/assets';
    public $js = [
        'authchoice.js',
    ];
    public $depends = [
        AuthChoiceStyleAsset::class,
        YiiAsset::class,
    ];
}
