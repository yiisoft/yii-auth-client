<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;
use Yiisoft\Yii\JQuery\YiiAsset;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 *
 * @see AuthChoiceStyleAsset
 */
class AuthChoiceAsset extends AssetBundle
{
    public ?string $sourcePath = __DIR__ . '../../assets';
    public array $js = [
        'authchoice.js',
    ];
    public array $depends = [
        AuthChoiceStyleAsset::class,
        YiiAsset::class,
    ];
}
