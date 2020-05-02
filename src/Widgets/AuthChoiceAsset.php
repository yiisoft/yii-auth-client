<?php

namespace Yiisoft\Yii\AuthClient\Widgets;

use Yiisoft\Yii\JQuery\YiiAsset;
use yii\web\AssetBundle;

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
