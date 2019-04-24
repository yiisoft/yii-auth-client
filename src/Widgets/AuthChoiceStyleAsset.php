<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient\Widgets;

use yii\web\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for [[AuthChoice]] widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public $sourcePath = '@yii/authclient/assets';
    public $css = [
        'authchoice.css',
    ];
}
