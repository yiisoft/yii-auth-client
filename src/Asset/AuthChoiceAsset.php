<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 *
 * @see AuthChoiceStyleAsset
 */
class AuthChoiceAsset extends AssetBundle
{
    public ?string $sourcePath = __DIR__ . '../../resources/assets';

    /**
     * @psalm-suppress NonInvariantDocblockPropertyType $js
     */
    public array $js = [
        'authchoice.js',
    ];
    
    public array $depends = [
        AuthChoiceStyleAsset::class,
    ];
}
